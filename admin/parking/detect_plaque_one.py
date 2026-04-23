#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
OCR plaque (Maroc / arabe + chiffres) : lit le texte visible sur la photo, pas le nom du fichier.
Plusieurs pré-traitements + ordre de lecture gauche → droite.
Sortie : une seule ligne JSON stdout.
"""
import json
import os
import re
import sys
from typing import List, Tuple


def all_digits_to_west(s: str) -> str:
    # Chiffres arabes orientaux et persans
    s = s.translate(str.maketrans("٠١٢٣٤٥٦٧٨٩", "0123456789"))
    s = s.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789"))
    return s


def center_x(box) -> float:
    xs = [float(p[0]) for p in box]
    return sum(xs) / max(len(xs), 1)


def normalize_plate_text(s: str) -> str:
    s = all_digits_to_west(s)
    # Garder chiffres, lettres latines, lettres arabes, tiret
    s = re.sub(r"[^\u0600-\u06FFA-Za-z0-9\-]", "", s)
    s = re.sub(r"\s+", "", s)
    return s


def score_moroccan_plate_candidate(s: str) -> float:
    """Plus le score est haut, plus ça ressemble à une plaque (chiffres + évent. arabe)."""
    if len(s) < 3:
        return 0.0
    digits = len(re.findall(r"\d", s))
    arabic = len(re.findall(r"[\u0600-\u06FF]", s))
    latin = len(re.findall(r"[A-Za-z]", s))
    # Pénaliser trop de lettres latines (souvent bruit OCR)
    score = digits * 2.0 + arabic * 1.5 - latin * 0.3 + min(len(s), 20) * 0.15
    if digits >= 4:
        score += 2.0
    if digits >= 2 and (arabic >= 1 or digits >= 5):
        score += 1.5
    return score


def collect_from_results(results) -> List[Tuple[str, float]]:
    """Trie les boîtes de gauche à droite, concatène, + fragments individuels."""
    if not results:
        return []
    ordered = sorted(results, key=lambda r: center_x(r[0]))
    out: List[Tuple[str, float]] = []
    parts = []
    confs = []
    for item in ordered:
        if len(item) < 3:
            continue
        text, conf = item[1], float(item[2])
        t = normalize_plate_text(text)
        if t:
            parts.append(t)
            confs.append(conf)
            out.append((t, conf))
    if parts:
        merged = normalize_plate_text("".join(parts))
        avg_c = sum(confs) / max(len(confs), 1)
        if merged:
            out.append((merged, avg_c))
    return out


def preprocess_variants(bgr):
    import cv2

    variants = []
    h, w = bgr.shape[:2]
    # Agrandir petites images (plaque illisible en miniature)
    scale = 1.0
    if min(h, w) < 500:
        scale = 500.0 / min(h, w)
        scale = min(scale, 3.0)
    if scale > 1.01:
        bgr = cv2.resize(bgr, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)

    gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)

    variants.append(("bgr", bgr))
    variants.append(("gray_eq", cv2.equalizeHist(gray)))

    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    variants.append(("clahe", clahe.apply(gray)))

    blur = cv2.GaussianBlur(gray, (3, 3), 0)
    ath = cv2.adaptiveThreshold(
        blur, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 31, 11
    )
    variants.append(("adapt", ath))

    bilateral = cv2.bilateralFilter(gray, 5, 75, 75)
    variants.append(("bilateral_eq", cv2.equalizeHist(bilateral)))

    # Double taille sur gris égalisé (souvent utile pour petites plaques)
    g2 = cv2.resize(
        cv2.equalizeHist(gray),
        None,
        fx=2.0,
        fy=2.0,
        interpolation=cv2.INTER_CUBIC,
    )
    variants.append(("gray_eq_2x", g2))

    return variants


def main() -> None:
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "usage: detect_plaque_one.py <image_path>"}))
        return
    path = sys.argv[1]
    if not os.path.isfile(path):
        print(json.dumps({"ok": False, "error": "not a file"}))
        return
    try:
        import cv2
        import easyocr
    except ImportError as e:
        print(json.dumps({"ok": False, "error": "import: " + str(e)}))
        return

    img = cv2.imread(path)
    if img is None:
        print(json.dumps({"ok": False, "error": "cv2 cannot read image"}))
        return

    reader = easyocr.Reader(["ar", "en"], gpu=False, verbose=False)

    best_text = ""
    best_score = -1.0
    best_conf = 0.0
    seen = set()

    for _name, variant in preprocess_variants(img):
        try:
            results = reader.readtext(variant, paragraph=False, detail=1)
        except Exception:
            continue
        for text, conf in collect_from_results(results):
            if text in seen:
                continue
            seen.add(text)
            sc = score_moroccan_plate_candidate(text)
            if sc > best_score or (sc == best_score and conf > best_conf):
                best_score = sc
                best_text = text
                best_conf = conf

    if not best_text or len(best_text) < 3:
        print(
            json.dumps(
                {
                    "ok": False,
                    "error": "plaque_non_lue",
                    "hint": "Vérifiez netteté, lumière, cadrage; installez easyocr et opencv-python.",
                }
            )
        )
        return

    print(
        json.dumps(
            {
                "ok": True,
                "matricule": best_text,
                "confidence": round(min(best_conf, 0.999), 3),
                "score": round(best_score, 2),
            }
        )
    )


if __name__ == "__main__":
    main()
