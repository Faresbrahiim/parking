#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Test rapide : imports EasyOCR + OpenCV (utilisé par api/ocr_status.php)."""
import json
import sys

def main() -> None:
    try:
        import cv2  # noqa: F401
        import easyocr  # noqa: F401
        print(json.dumps({"ok": True, "message": "easyocr et opencv OK"}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
