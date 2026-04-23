import argparse
import hashlib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime
from pathlib import Path

import cv2
import easyocr
from ultralytics import YOLO

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
WATCH_FOLDER = os.path.join(SCRIPT_DIR, "auto_detect")
PROCESSED_FOLDER = os.path.join(SCRIPT_DIR, "processed")
API_ENREGISTRER_URL = os.environ.get(
    "PFA_ENREGISTRER_URL",
    "http://127.0.0.1/PFA/admin/api/enregistrer_detection.php",
)

# YOLOv8n entraîné sur les plaques
WEIGHTS_DIR = os.path.join(SCRIPT_DIR, "weights")
PLATE_MODEL_FILE = os.path.join(WEIGHTS_DIR, "license_plate_yolov8n.pt")
PLATE_MODEL_URL = (
    "https://huggingface.co/Koushim/yolov8-license-plate-detection/resolve/main/best.pt"
)

def ensure_plate_yolo_weights(dest_path=None):
    """Télécharge le .pt YOLO « plaques » si absent."""
    target = os.path.abspath(dest_path or PLATE_MODEL_FILE)
    if os.path.isfile(target):
        return target
    parent = os.path.dirname(target)
    if parent:
        os.makedirs(parent, exist_ok=True)
    print("Téléchargement du modèle YOLO plaques (~6 Mo)…")
    try:
        from urllib.request import urlretrieve
        urlretrieve(PLATE_MODEL_URL, target)
    except Exception as exc:
        raise RuntimeError(
            "Impossible de télécharger le modèle plaques.\n"
            f"  URL : {PLATE_MODEL_URL}\n"
            f"  Enregistrez le fichier sous : {target}\n"
            f"  Détail : {exc}"
        ) from exc
    print(f"Modèle enregistré : {target}")
    return target

def convert_arabic_digits(text):
    arabic_to_west = str.maketrans("٠١٢٣٤٥٦٧٨٩", "0123456789")
    return text.translate(arabic_to_west)

def detect_plates_on_image(image, reader, model, debug_draw=None):
    """
    Détecte les textes de plaque sur une image BGR.
    Retourne liste de (texte, recadrage BGR copié) pour chaque détection valide.
    """
    found = []
    results = model(image)

    for r_idx, r in enumerate(results):
        if r.boxes is None or len(r.boxes.xyxy) == 0:
            continue
        for b_idx, box in enumerate(r.boxes.xyxy):
            # Baisser le seuil de confiance
            if hasattr(r.boxes, 'conf'):
                conf = r.boxes.conf[b_idx].item()
                if conf < 0.2:  # Seuil baissé de 0.3 à 0.2
                    continue
            else:
                continue
            x1, y1, x2, y2 = map(int, box)
            padding = 25
            x1, y1 = max(0, x1 - padding), max(0, y1 - padding)
            x2, y2 = min(image.shape[1], x2 + padding), min(image.shape[0], y2 + padding)
            if x2 <= x1 or y2 <= y1:
                continue

            plate_img = image[y1:y2, x1:x2]
            plate_gray = cv2.cvtColor(plate_img, cv2.COLOR_BGR2GRAY)
            plate_gray = cv2.equalizeHist(plate_gray)
            plate_resized = cv2.resize(
                plate_gray, None, fx=4, fy=4, interpolation=cv2.INTER_CUBIC
            )
            _, plate_thresh = cv2.threshold(
                plate_resized, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU
            )

            result_texts = reader.readtext(plate_thresh)
            print(f"OCR results for plate region: {result_texts}")  # Debug
            full_plate = ""
            for (_, t, _prob) in result_texts:
                print(f"OCR text: '{t}' (conf: {_prob:.3f})")  # Debug
                # Chiffres arabes/latins + lettres latines
                filtered = re.sub(r"[^\u0600-\u06FF0-9A-Za-z]", "", t)
                print(f"Filtered: '{filtered}'")  # Debug
                full_plate += filtered

            full_plate = convert_arabic_digits(full_plate).strip()
            print(f"Final plate: '{full_plate}'")  # Debug
            if full_plate:
                found.append((full_plate, plate_img.copy()))
                if debug_draw is not None:
                    cv2.rectangle(debug_draw, (x1, y1), (x2, y2), (0, 255, 0), 2)
                    cv2.putText(
                        debug_draw,
                        full_plate[:32],
                        (x1, max(20, y1 - 8)),
                        cv2.FONT_HERSHEY_SIMPLEX,
                        0.6,
                        (0, 255, 0),
                        2,
                    )

    return found

def enregistrer_detection_mysql(matricule, source="auto_photo"):
    """Envoie la plaque à l'API PHP → table MySQL (entrée / sortie)."""
    text = (matricule or "").strip()
    if not text:
        return
    payload = json.dumps({"matricule": text, "source": source}, ensure_ascii=False).encode(
        "utf-8"
    )
    req = urllib.request.Request(
        API_ENREGISTRER_URL,
        data=payload,
        headers={"Content-Type": "application/json; charset=utf-8"},
    )
    try:
        with urllib.request.urlopen(req, timeout=12) as resp:
            raw = resp.read().decode("utf-8")
        body = json.loads(raw)
        if body.get("ok"):
            ev = body.get("evenement", "?")
            st = body.get("statut", "")
            print(f"[MySQL] {text} → {ev} | statut={st}")
            return True
        print(f"[API] réponse: {body}")
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, OSError) as exc:
        print(f"[API] hors ligne ({exc})")
    except json.JSONDecodeError as exc:
        print(f"[API] JSON invalide ({exc})")
    return False

def monitor_folder(watch_folder, model, reader, interval_sec=5.0):
    """
    Surveille un dossier et détecte automatiquement les plaques sur les nouvelles images.
    """
    processed_files = set()
    
    # Créer les dossiers
    os.makedirs(watch_folder, exist_ok=True)
    os.makedirs(PROCESSED_FOLDER, exist_ok=True)
    
    print(f"Surveillance du dossier : {watch_folder}")
    print("Déposez des images dans ce dossier pour détection automatique.")
    print("CTRL+C pour arrêter.")
    
    try:
        while True:
            try:
                # Lister les fichiers images
                image_extensions = {'.jpg', '.jpeg', '.png', '.bmp', '.tiff'}
                current_files = set()
                
                for file in os.listdir(watch_folder):
                    if Path(file).suffix.lower() in image_extensions:
                        current_files.add(file)
                
                # Traiter les nouveaux fichiers
                new_files = current_files - processed_files
                
                for filename in new_files:
                    filepath = os.path.join(watch_folder, filename)
                    print(f"\nNouvelle image détectée : {filename}")
                    
                    try:
                        image = cv2.imread(filepath)
                        if image is None:
                            print(f"Impossible de lire l'image : {filename}")
                            continue
                        
                        plates = detect_plates_on_image(image, reader, model)
                        
                        if plates:
                            for plate_text, plate_img in plates:
                                print(f"Plaque détectée : {plate_text}")
                                success = enregistrer_detection_mysql(plate_text, "auto_photo")
                                if success:
                                    # Déplacer vers processed
                                    processed_path = os.path.join(PROCESSED_FOLDER, filename)
                                    os.rename(filepath, processed_path)
                                    print(f"Image déplacée vers : processed/{filename}")
                                    break
                        else:
                            print("Aucune plaque détectée")
                            
                    except Exception as e:
                        print(f"Erreur traitement {filename}: {e}")
                    
                    processed_files.add(filename)
                
                # Nettoyer les fichiers supprimés
                processed_files &= current_files
                
            except Exception as e:
                print(f"Erreur surveillance: {e}")
            
            time.sleep(interval_sec)
            
    except KeyboardInterrupt:
        print("\nArrêt de la surveillance.")

def main():
    parser = argparse.ArgumentParser(
        description="Détection automatique de plaques depuis dossier."
    )
    parser.add_argument(
        "--folder",
        default=WATCH_FOLDER,
        help=f"Dossier à surveiller ( défaut: {WATCH_FOLDER} )",
    )
    parser.add_argument(
        "--interval",
        type=float,
        default=5.0,
        help="Secondes entre vérifications ( défaut: 5 )",
    )
    parser.add_argument(
        "--model",
        default=None,
        metavar="CHEMIN.pt",
        help="Fichier poids YOLO plaques ( défaut : télécharge weights/license_plate_yolov8n.pt )",
    )
    args = parser.parse_args()

    os.chdir(SCRIPT_DIR)
    weights_path = (
        os.path.abspath(args.model)
        if args.model
        else ensure_plate_yolo_weights()
    )
    if args.model and not os.path.isfile(weights_path):
        raise FileNotFoundError(f"Modèle introuvable : {weights_path}")
    
    model = YOLO(weights_path)
    reader = easyocr.Reader(["ar", "en"], gpu=False)
    
    monitor_folder(args.folder, model, reader, interval_sec=args.interval)

if __name__ == "__main__":
    main()
