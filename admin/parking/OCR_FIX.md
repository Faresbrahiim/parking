# Pourquoi l'OCR ne détecte pas la plaque

## Diagnostic

Sur votre machine, Python se lance bien mais **se fige à l'import d'EasyOCR/torch** :

| Test                                       | Durée  | CPU utilisé |
|--------------------------------------------|--------|-------------|
| `python detect_plaque_one.py voiture.jpg` | 9 min  | 0.64 s      |
| `python -m pip install pytesseract`        | 10 min | 0.89 s      |

Un process bloqué à **0.6 s de CPU après 10 minutes** = il attend du disque ou du réseau,
pas du calcul. La cause quasi-certaine sur Windows 10/11 :
**Windows Defender scanne chaque DLL** que torch charge (il y en a plus de 300).
Chaque scan prend 1-3 s → l'import d'EasyOCR passe de 15 s à 15-30 min.

## Solution A — Exclusions Windows Defender (recommandé, 30 sec à faire)

1. Ouvrez l'Explorateur et allez dans `C:\xampp\htdocs\PFA\admin\parking\`
2. **Clic droit** sur `fix_defender_exclusions.bat` → **Exécuter en tant qu'administrateur**
3. Acceptez l'UAC, laissez le script finir (quelques secondes)
4. Rouvrez l'admin. La **première** détection prendra 30-60 s (chargement
   des modèles depuis le cache, maintenant sans scan). Les suivantes :
   < 5 s par image.

## Solution B — Installer Tesseract-OCR (alternative rapide)

Tesseract démarre en < 1 s, ne dépend PAS de torch → totalement immune aux
lenteurs Windows Defender. Votre projet contient déjà `ara.traineddata`.

1. **Clic droit** sur `install_tesseract.bat` → **Exécuter en tant qu'administrateur**
2. Le script télécharge l'installeur UB-Mannheim (40 MB), le lance, et
   installe `pytesseract` automatiquement
3. Dans l'installeur, cochez **Arabic** dans "Additional language data"
4. À la fin, le script copie `ara.traineddata` dans `C:\Program Files\Tesseract-OCR\tessdata\`
5. Rouvrez l'admin — la détection passe par Tesseract (quasi-instantanée)

Le script `detect_plaque_one.py` a été mis à jour : il essaie **Tesseract en
premier** (si installé), puis tombe sur EasyOCR en fallback.

## Vérifier que ça marche

Après avoir appliqué A ou B :

```
cd C:\xampp\htdocs\PFA\admin\parking
C:\Users\asosh\AppData\Local\Programs\Python\Python312\python.exe detect_plaque_one.py echec_ocr\echec_20260416_013306_voiture.jpeg
```

Vous devez voir une ligne JSON en < 30 s :

```json
{"ok": true, "matricule": "23242ج55", "confidence": 0.9, "backend": "tesseract"}
```

Le log détaillé se trouve dans `parking\ocr_debug.log`.

## Si aucune des deux solutions ne marche

Ouvrez dans le navigateur :

```
http://localhost/PFA/admin/api/ocr_status.php
```

Envoyez-moi le contenu + le fichier `parking\ocr_debug.log`.
