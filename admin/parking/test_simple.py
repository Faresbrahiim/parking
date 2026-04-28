import cv2
import easyocr
import re
import numpy as np
import os
from pathlib import Path

def detect_plates_simple(image_path):
    """Détection simple avec EasyOCR sans YOLO"""
    
    # Charger l'image
    image = cv2.imread(image_path)
    if image is None:
        print(f"Impossible de lire: {image_path}")
        return []
    
    print(f"Image: {image.shape}")
    
    # Convertir en gris
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    
    # Améliorer le contraste
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
    enhanced = clahe.apply(gray)
    
    # Essayer plusieurs seuillages
    plates = []
    reader = easyocr.Reader(['ar', 'en'], gpu=False)
    
    # Méthode 1: OCR direct sur l'image entière
    print("Test OCR direct...")
    results = reader.readtext(enhanced)
    
    for (bbox, text, prob) in results:
        if prob > 0.3:  # Seuil de confiance
            # Filtrer pour garder seulement les caractères de plaque
            filtered = re.sub(r"[^\u0600-\u06FF0-9A-Za-z]", "", text)
            if len(filtered) >= 4:  # Au moins 4 caractères
                print(f"Texte trouvé: '{text}' → '{filtered}' (conf: {prob:.2f})")
                plates.append(filtered)
    
    # Méthode 2: Détection de contours
    print("Test détection contours...")
    blurred = cv2.GaussianBlur(enhanced, (5,5), 0)
    thresh = cv2.adaptiveThreshold(blurred, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, 11, 2)
    
    # Trouver les contours
    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    
    for contour in contours:
        # Filtrer par forme et taille
        x, y, w, h = cv2.boundingRect(contour)
        aspect_ratio = w / h
        
        # Critères pour une plaque: rectangle, bonne taille
        if 2.0 <= aspect_ratio <= 6.0 and w > 80 and h > 20:
            # Extraire la région
            plate_region = enhanced[y:y+h, x:x+w]
            
            # OCR sur cette région
            results = reader.readtext(plate_region)
            
            for (bbox, text, prob) in results:
                if prob > 0.4:
                    filtered = re.sub(r"[^\u0600-\u06FF0-9A-Za-z]", "", text)
                    if len(filtered) >= 4:
                        print(f"Plaque potentielle: '{filtered}' (conf: {prob:.2f})")
                        plates.append(filtered)
                        
                        # Dessiner rectangle
                        cv2.rectangle(image, (x, y), (x+w, y+h), (0, 255, 0), 2)
                        cv2.putText(image, filtered, (x, y-10), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
    
    # Sauvegarder le résultat
    cv2.imwrite("test_simple_result.jpg", image)
    print("Résultat sauvegardé: test_simple_result.jpg")
    
    # Retourner les plaques uniques
    return list(set(plates))

if __name__ == "__main__":
    # Tester avec ton image
    image_path = "auto_detect/Capture d'écran 2026-04-07 032207.png"
    if os.path.exists(image_path):
        plates = detect_plates_simple(image_path)
        print(f"\nPlaques trouvées: {plates}")
    else:
        print(f"Image non trouvée: {image_path}")
