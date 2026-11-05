# _SANDBOX/gen_icons.py
"""
Génère les PNG depuis les SVG d'ICEANOTES.
Usage : python gen_icons.py
"""
import os
import sys

try:
    import cairosvg
except ImportError:
    print("❌ cairosvg non installé. Lance : pip install cairosvg")
    sys.exit(1)

# Chemins
BASE_DIR   = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ICONS_DIR  = os.path.join(BASE_DIR, 'assets', 'icons')

# Fichiers à générer : (source_svg, output_png, taille)
JOBS = [
    ('icon.svg',            'icon-192.png',          192),
    ('icon.svg',            'icon-512.png',          512),
    ('icon-maskable.svg',   'icon-maskable-192.png', 192),
    ('icon-maskable.svg',   'icon-maskable-512.png', 512),
    ('icon.svg',            'favicon-32.png',        32),
    ('icon.svg',            'apple-touch-icon.png',   180),
]

def main():
    if not os.path.isdir(ICONS_DIR):
        print(f"❌ Dossier introuvable : {ICONS_DIR}")
        sys.exit(1)

    print(f"📁 Dossier source : {ICONS_DIR}\n")

    for src_name, out_name, size in JOBS:
        src_path = os.path.join(ICONS_DIR, src_name)
        out_path = os.path.join(ICONS_DIR, out_name)

        if not os.path.exists(src_path):
            print(f"⚠️  {src_name} introuvable — ignoré")
            continue

        try:
            cairosvg.svg2png(
                url=src_path,
                write_to=out_path,
                output_width=size,
                output_height=size,
            )
            print(f"✅ {out_name} ({size}×{size}) généré")
        except Exception as e:
            print(f"❌ Erreur sur {out_name} : {e}")

    print("\n🎉 Terminé.")

if __name__ == '__main__':
    main()