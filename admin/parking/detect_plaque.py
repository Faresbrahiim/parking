import cv2
import easyocr
from ultralytics import YOLO
import os
import re

# --- 1️⃣ Charger modèle YOLO ---
model = YOLO("yolov8n.pt")  # Assurez-vous que yolov8n.pt est dans le même dossier

# --- 2️⃣ Charger image ---
image_path = "voiture.jpeg"  # Remplace par ton image
image = cv2.imread(image_path)
if image is None:
    raise FileNotFoundError(f"Impossible de lire {image_path}")

# --- 3️⃣ Créer lecteur EasyOCR pour arabe et anglais ---
reader = easyocr.Reader(['ar','en'], gpu=False)

# --- 4️⃣ Créer dossier pour plaques recadrées ---
os.makedirs("plaques_recadrees", exist_ok=True)

# --- 5️⃣ Fonction pour convertir chiffres arabes orientaux en occidentaux ---
def convert_arabic_digits(text):
    arabic_to_west = str.maketrans("٠١٢٣٤٥٦٧٨٩", "0123456789")
    return text.translate(arabic_to_west)

# --- 6️⃣ Détecter plaques avec YOLO ---
results = model(image)

for r_idx, r in enumerate(results):
    for b_idx, box in enumerate(r.boxes.xyxy):
        # Récupérer coordonnées avec padding
        x1, y1, x2, y2 = map(int, box)
        padding = 25
        x1, y1 = max(0, x1 - padding), max(0, y1 - padding)
        x2, y2 = min(image.shape[1], x2 + padding), min(image.shape[0], y2 + padding)

        # Recadrer plaque
        plate_img = image[y1:y2, x1:x2]

        # --- 7️⃣ Prétraitement pour OCR ---
        plate_gray = cv2.cvtColor(plate_img, cv2.COLOR_BGR2GRAY)
        plate_gray = cv2.equalizeHist(plate_gray)  # améliorer contraste
        plate_resized = cv2.resize(plate_gray, None, fx=4, fy=4, interpolation=cv2.INTER_CUBIC)
        _, plate_thresh = cv2.threshold(plate_resized, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

        # --- 8️⃣ OCR avec EasyOCR ---
        result_texts = reader.readtext(plate_thresh)
        full_plate = ""
        for (_, t, prob) in result_texts:
            # filtrer texte : garder lettres arabes et chiffres
            filtered = re.sub(r"[^\u0600-\u06FF0-9]", "", t)
            full_plate += filtered

        # convertir chiffres arabes orientaux en occidentaux
        full_plate = convert_arabic_digits(full_plate)

        print(f"Plaque détectée : {full_plate}")

        # --- 9️⃣ Sauvegarder plaque recadrée ---
        plate_filename = f"plaques_recadrees/{os.path.basename(image_path)}_plaque_{r_idx+1}_{b_idx+1}.jpg"
        cv2.imwrite(plate_filename, plate_img)
        print(f"Plaque recadrée sauvegardée : {plate_filename}")

        # --- 10️⃣ Dessiner rectangle sur image originale ---
        cv2.rectangle(image, (x1, y1), (x2, y2), (0, 255, 0), 2)

# --- 11️⃣ Sauvegarder image finale ---
output_filename = f"resultat_{os.path.basename(image_path)}"
cv2.imwrite(output_filename, image)
print(f"Image résultat enregistrée : {output_filename}")