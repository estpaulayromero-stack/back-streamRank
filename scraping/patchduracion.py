"""
StreamRank — patch_duracion.py
Agrega duración real a TODOS los scrapers existentes.
Ejecutar UNA sola vez desde la carpeta scraping/:
    python patch_duracion.py
Modifica en cada scraper:
  1. Agrega: from get_duracion import get_duracion
  2. Reemplaza:  "trailer": ""   por  "trailer":"", "duracion_min":0, "duracion":"—"
  3. Dentro del loop de tráilers agrega la llamada a get_duracion
"""
import os, re

SCRAPING_DIR = os.path.dirname(os.path.abspath(__file__))

# Scrapers a parchear (todos excepto global que ya está actualizado)
TARGETS = [
    "amazon.py",    "netflix.py",  "warner.py",
    "disney.py",    "hbo.py",       "universal.py",
    "top_marvel.py",    "starwars.py",  "lucasfilms.py",
    "pixar.py",     "dreamworks.py","ghibli.py",
    "dcestudios.py","dcuniverse.py","harrypotter.py",
    "fastfurious.py","jurassic.py",
]

def patch_file(path):
    with open(path, "r", encoding="utf-8") as f:
        src = f.read()

    changed = False

    # 1. Agregar import si no existe
    if "from get_duracion import get_duracion" not in src:
        src = src.replace(
            "from datetime import datetime",
            "from datetime import datetime\nfrom get_duracion import get_duracion"
        )
        changed = True

    # 2. Agregar campos duracion al dict de limpias si no están
    if '"duracion_min"' not in src:
        # Busca la línea con "trailer": "" dentro del dict de items
        src = src.replace(
            '"trailer":       ""\n        })',
            '"trailer":       "",\n            "duracion_min": 0,\n            "duracion":     "—"\n        })'
        )
        # Variante compacta (como en amazon)
        src = src.replace(
            '"trailer":""',
            '"trailer":"","duracion_min":0,"duracion":"—"'
        )
        changed = True

    # 3. Agregar llamada get_duracion en el loop de tráilers
    #    Busca el patrón:  p["trailer"]=get_trailer(...)
    #    y agrega debajo:  dur = get_duracion(...); p["duracion_min"]=...
    if "get_duracion" not in src or "p[\"duracion_min\"]" not in src:
        # Patrón: p["trailer"]=get_trailer(p["tmdb_id"])
        old = 'p["trailer"]=get_trailer(p["tmdb_id"])'
        new = (
            'p["trailer"]=get_trailer(p["tmdb_id"])\n'
            '        _dur=get_duracion(p["tmdb_id"],"pelicula")\n'
            '        p["duracion_min"]=_dur["minutos"]\n'
            '        p["duracion"]=_dur["texto"]'
        )
        if old in src:
            src = src.replace(old, new)
            changed = True

    if changed:
        with open(path, "w", encoding="utf-8") as f:
            f.write(src)
        print(f"  ✅ Parcheado: {os.path.basename(path)}")
    else:
        print(f"  ⏭  Ya tenía duración: {os.path.basename(path)}")

if __name__ == "__main__":
    print("🔧 Parcheando scrapers con duración real...\n")
    for nombre in TARGETS:
        ruta = os.path.join(SCRAPING_DIR, nombre)
        if os.path.exists(ruta):
            patch_file(ruta)
        else:
            print(f"  ⚠  No encontrado: {nombre}")
    print("\n✅ Listo. Ahora corre run_all_scrapers.py para regenerar los JSONs.")