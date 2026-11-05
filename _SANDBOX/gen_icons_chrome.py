# _SANDBOX/gen_icons_chrome.py
"""
Génère les PNG depuis les SVG via Chrome/Edge headless.
Aucune dépendance externe (Chrome/Edge déjà installé sur Windows 10/11).
"""
import os
import subprocess
import sys
import tempfile
from pathlib import Path

BASE_DIR  = Path(__file__).resolve().parent.parent
ICONS_DIR = BASE_DIR / 'assets' / 'icons'

# Localisation de Chrome ou Edge
BROWSERS = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
    os.path.expandvars(r"%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"),
    os.path.expandvars(r"%LOCALAPPDATA%\Microsoft\Edge\Application\msedge.exe"),
]

def find_browser():
    for p in BROWSERS:
        if os.path.exists(p):
            return p
    return None


def render(browser, svg_path: Path, png_path: Path, size: int):
    """Utilise Chrome headless pour screenshot le SVG."""
    # HTML temporaire qui affiche le SVG à la bonne taille
    html = f"""<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
    html, body {{ margin: 0; padding: 0; background: transparent; }}
    img {{ width: {size}px; height: {size}px; display: block; }}
</style></head>
<body><img src="file:///{svg_path.as_posix()}"></body>
</html>"""
    tmp_html = Path(tempfile.gettempdir()) / f'iceanotes_render_{size}.html'
    tmp_html.write_text(html, encoding='utf-8')

    # Supprimer le PNG s'il existe (Chrome refuse d'écraser silencieusement dans certains cas)
    if png_path.exists():
        png_path.unlink()

    subprocess.run([
        browser,
        '--headless=new',
        '--disable-gpu',
        '--hide-scrollbars',
        '--no-sandbox',
        '--default-background-color=00000000',  # fond transparent
        f'--screenshot={png_path}',
        f'--window-size={size},{size}',
        f'file:///{tmp_html.as_posix()}',
    ], check=True, capture_output=True, timeout=30)


def main():
    if not ICONS_DIR.is_dir():
        print(f"❌ Dossier introuvable : {ICONS_DIR}")
        sys.exit(1)

    browser = find_browser()
    if not browser:
        print("❌ Chrome ou Edge introuvable.")
        print("   Installe Chrome : https://www.google.com/chrome/")
        sys.exit(1)

    print(f"🌐 Navigateur : {browser}\n")

    jobs = [
        ('icon.svg',          'icon-192.png',          192),
        ('icon.svg',          'icon-512.png',          512),
        ('icon-maskable.svg', 'icon-maskable-192.png', 192),
        ('icon-maskable.svg', 'icon-maskable-512.png', 512),
        ('icon.svg',          'favicon-32.png',        32),
        ('icon.svg',          'apple-touch-icon.png',   180),
    ]

    for src_name, out_name, size in jobs:
        src = ICONS_DIR / src_name
        out = ICONS_DIR / out_name
        if not src.exists():
            print(f"⚠️  {src_name} introuvable — ignoré")
            continue
        try:
            render(browser, src, out, size)
            size_kb = round(out.stat().st_size / 1024, 1)
            print(f"✅ {out_name:24s} ({size}×{size}) — {size_kb} Ko")
        except subprocess.CalledProcessError as e:
            err = (e.stderr or b'').decode('utf-8', errors='ignore')[:200]
            print(f"❌ {out_name} : {err}")
        except subprocess.TimeoutExpired:
            print(f"❌ {out_name} : timeout")

    print("\n🎉 Terminé.")


if __name__ == '__main__':
    main()