"""
============================================================
  StreamRank — scraper_marvel.py
  Scrapea Top 50 Marvel (películas + series) desde TMDb API
  Guarda en: json/marvel_top50.json
============================================================
"""
import os, json, requests
from datetime import datetime
from get_duracion import get_duracion

API_KEY  = "904483be1b785c0c354704cfef7156bc"
BASE_URL = "https://api.themoviedb.org/3"
IMG_BASE = "https://image.tmdb.org/t/p/w500"
TOP_N    = 50
TOP_CADA = 40
JSON_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "json")

GENEROS = {
    28:"Acción",12:"Aventura",16:"Animación",35:"Comedia",
    80:"Crimen",18:"Drama",10751:"Familiar",14:"Fantasía",
    27:"Terror",9648:"Misterio",878:"Ciencia Ficción",53:"Suspense",
    10765:"Ciencia Ficción",10759:"Acción",10762:"Infantil",10764:"Reality",
}

# Marvel Studios company_id = 420  |  keyword "marvel cinematic universe" = 180547
COMPANY_ID  = "420"
KEYWORD_MCU = "180547"

def get_trailer(mid, media_type="movie"):
    try:
        for lang in ["es-ES", "en-US"]:
            r = requests.get(f"{BASE_URL}/{media_type}/{mid}/videos",
                params={"api_key": API_KEY, "language": lang}, timeout=8)
            for v in r.json().get("results", []):
                if v.get("site") == "YouTube" and v.get("type") == "Trailer":
                    return f"https://www.youtube.com/embed/{v['key']}"
    except: pass
    return ""

def fetch_peliculas():
    items = []
    for page in range(1, 4):
        params = {"api_key": API_KEY, "language": "es-ES",
                  "with_companies": COMPANY_ID,
                  "sort_by": "vote_average.desc", "vote_count.gte": 200, "page": page}
        try:
            r = requests.get(f"{BASE_URL}/discover/movie", params=params, timeout=10)
            r.raise_for_status()
            items.extend(r.json().get("results", []))
        except Exception as e:
            print(f"  [películas] Error página {page}: {e}")
    return items

def fetch_series():
    items = []
    for page in range(1, 4):
        params = {"api_key": API_KEY, "language": "es-ES",
                  "with_keywords": KEYWORD_MCU,
                  "sort_by": "vote_average.desc", "vote_count.gte": 100, "page": page}
        try:
            r = requests.get(f"{BASE_URL}/discover/tv", params=params, timeout=10)
            r.raise_for_status()
            items.extend(r.json().get("results", []))
        except Exception as e:
            print(f"  [series] Error página {page}: {e}")
    return items

def normalizar_pelicula(p):
    gids = p.get("genre_ids", [])
    dur  = get_duracion(p["id"])
    return {
        "tmdb_id": p["id"], "tipo": "película",
        "titulo": p.get("title", "Sin título"),
        "rating": round(p.get("vote_average", 0), 1),
        "votos": p.get("vote_count", 0),
        "genero": GENEROS.get(gids[0], "Acción") if gids else "Acción",
        "imagen_url": f"{IMG_BASE}{p['poster_path']}" if p.get("poster_path") else "",
        "fecha_estreno": p.get("release_date", ""),
        "descripcion": p.get("overview", "")[:300],
        "duracion_min": dur[0] if isinstance(dur, tuple) else 0,
        "duracion": dur[1] if isinstance(dur, tuple) else "—",
        "trailer": "",
    }

def normalizar_serie(s):
    gids = s.get("genre_ids", [])
    return {
        "tmdb_id": s["id"], "tipo": "serie",
        "titulo": s.get("name", "Sin título"),
        "rating": round(s.get("vote_average", 0), 1),
        "votos": s.get("vote_count", 0),
        "genero": GENEROS.get(gids[0], "Acción") if gids else "Acción",
        "imagen_url": f"{IMG_BASE}{s['poster_path']}" if s.get("poster_path") else "",
        "fecha_estreno": s.get("first_air_date", ""),
        "descripcion": s.get("overview", "")[:300],
        "duracion_min": 0, "duracion": "Serie TV", "trailer": "",
    }

def main():
    print("Scrapeando Marvel (películas + series)...")
    raw_p = fetch_peliculas()
    vistos, peliculas = set(), []
    for p in raw_p:
        if not p.get("id") or p["id"] in vistos: continue
        vistos.add(p["id"]); peliculas.append(normalizar_pelicula(p))
    peliculas.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    peliculas = peliculas[:TOP_CADA]

    raw_s = fetch_series()
    sids, series = set(), []
    for s in raw_s:
        if not s.get("id") or s["id"] in sids: continue
        sids.add(s["id"]); series.append(normalizar_serie(s))
    series.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    series = series[:TOP_CADA]

    todo = peliculas + series
    todo.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    top = todo[:TOP_N]

    print(f"  Obteniendo tráilers para {len(top)} items...")
    for i, item in enumerate(top, 1):
        item["posicion"] = i
        media = "movie" if item["tipo"] == "película" else "tv"
        item["trailer"] = get_trailer(item["tmdb_id"], media)
        print(f"    {i:02d}. {'🎬' if media=='movie' else '📺'} {item['titulo']} ({item['rating']})")

    os.makedirs(JSON_DIR, exist_ok=True)
    n_p = sum(1 for x in top if x["tipo"] == "película")
    n_s = sum(1 for x in top if x["tipo"] == "serie")
    resultado = {
        "categoria": "marvel", "nombre": "Marvel", "plataforma": "MARVEL",
        "color": "#e7000b",
        "descripcion": f"Lo mejor del Universo Marvel · {n_p} películas · {n_s} series",
        "fecha_actualizacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total": len(top), "peliculas": top,
    }
    path = os.path.join(JSON_DIR, "marvel_top50.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(resultado, f, ensure_ascii=False, indent=2)
    print(f"\n✅ Guardado en {path}  🎬{n_p}  📺{n_s}")

if __name__ == "__main__": main()