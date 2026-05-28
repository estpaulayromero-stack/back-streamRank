"""
============================================================
  StreamRank — scraper_netflix.py
  Scrapea Top 50 Netflix (películas + series) desde TMDb API
  Guarda en: json/netflix_top50.json
  Uso: python scraper_netflix.py
============================================================
"""

import os, json, requests
from datetime import datetime
from get_duracion import get_duracion

API_KEY  = "904483be1b785c0c354704cfef7156bc"
BASE_URL = "https://api.themoviedb.org/3"
IMG_BASE = "https://image.tmdb.org/t/p/w500"
TOP_N    = 50          # total final (películas + series mezcladas y ordenadas)
TOP_CADA = 40          # cuántos brutos pedir de cada tipo antes de filtrar
JSON_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "json")

GENEROS = {
    28:"Acción", 12:"Aventura", 16:"Animación", 35:"Comedia",
    80:"Crimen", 18:"Drama", 10751:"Familiar", 14:"Fantasía",
    27:"Terror", 9648:"Misterio", 878:"Ciencia Ficción", 53:"Suspense",
    10765:"Ciencia Ficción",   # TV sci-fi
    10759:"Acción",            # TV action & adventure
    10762:"Infantil",
    10763:"Noticias",
    10764:"Reality",
    10766:"Telenovela",
    10767:"Talk Show",
    10768:"Política",
}

PROVIDER_NETFLIX = "8"   # watch_provider_id de Netflix en TMDb

# ── Tráiler ──────────────────────────────────────────────────
def get_trailer(mid, media_type="movie"):
    try:
        for lang in ["es-ES", "en-US"]:
            r = requests.get(
                f"{BASE_URL}/{media_type}/{mid}/videos",
                params={"api_key": API_KEY, "language": lang}, timeout=8
            )
            for v in r.json().get("results", []):
                if v.get("site") == "YouTube" and v.get("type") == "Trailer":
                    return f"https://www.youtube.com/embed/{v['key']}"
    except:
        pass
    return ""

# ── Fetch películas ───────────────────────────────────────────
def fetch_peliculas():
    items = []
    for page in range(1, 4):
        params = {
            "api_key": API_KEY, "language": "es-ES",
            "with_watch_providers": PROVIDER_NETFLIX,
            "watch_region": "US",
            "sort_by": "vote_average.desc",
            "vote_count.gte": 500,
            "page": page,
        }
        try:
            r = requests.get(f"{BASE_URL}/discover/movie", params=params, timeout=10)
            r.raise_for_status()
            items.extend(r.json().get("results", []))
        except Exception as e:
            print(f"  [películas] Error página {page}: {e}")
    return items

# ── Fetch series ──────────────────────────────────────────────
def fetch_series():
    items = []
    for page in range(1, 4):
        params = {
            "api_key": API_KEY, "language": "es-ES",
            "with_watch_providers": PROVIDER_NETFLIX,
            "watch_region": "US",
            "sort_by": "vote_average.desc",
            "vote_count.gte": 200,   # series tienen menos votos que películas
            "page": page,
        }
        try:
            r = requests.get(f"{BASE_URL}/discover/tv", params=params, timeout=10)
            r.raise_for_status()
            items.extend(r.json().get("results", []))
        except Exception as e:
            print(f"  [series] Error página {page}: {e}")
    return items

# ── Normalizar item película ──────────────────────────────────
def normalizar_pelicula(p):
    gids = p.get("genre_ids", [])
    dur = get_duracion(p["id"], "pelicula")

    return {
        "tmdb_id": p["id"],
        "tipo": "película",
        "titulo": p.get("title", "Sin título"),
        "rating": round(p.get("vote_average", 0), 1),
        "votos": p.get("vote_count", 0),
        "genero": GENEROS.get(gids[0], "Drama") if gids else "Drama",
        "imagen_url": f"{IMG_BASE}{p['poster_path']}" if p.get("poster_path") else "",
        "fecha_estreno": p.get("release_date", ""),
        "descripcion": p.get("overview", "")[:300],
        "duracion_min": dur["minutos"],
        "duracion": dur["texto"],
        "trailer": "",
    }

# ── Normalizar item serie ─────────────────────────────────────
def normalizar_serie(s):

    gids = s.get("genre_ids", [])
    dur = get_duracion(s["id"], "serie")

    return {
        "tmdb_id": s["id"],
        "tipo": "serie",
        "titulo": s.get("name", "Sin título"),
        "rating": round(s.get("vote_average", 0), 1),
        "votos": s.get("vote_count", 0),
        "genero": GENEROS.get(gids[0], "Drama") if gids else "Drama",
        "imagen_url": f"{IMG_BASE}{s['poster_path']}" if s.get("poster_path") else "",
        "fecha_estreno": s.get("first_air_date", ""),
        "descripcion": s.get("overview", "")[:300],
        "duracion_min": dur["minutos"],
        "duracion": dur["texto"],
        "trailer": "",
    }

# ── Main ──────────────────────────────────────────────────────
def main():
    print("Scrapeando Netflix (películas + series)...")

    # ── Películas ────────────────────────────────────────────
    print("  → Películas...")
    raw_peliculas = fetch_peliculas()
    print(f"     {len(raw_peliculas)} brutas")

    vistos, peliculas = set(), []
    for p in raw_peliculas:
        if not p.get("id") or p["id"] in vistos: continue
        vistos.add(p["id"])
        peliculas.append(normalizar_pelicula(p))
    peliculas.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    peliculas = peliculas[:TOP_CADA]

    # ── Series ──────────────────────────────────────────────
    print("  → Series...")
    raw_series = fetch_series()
    print(f"     {len(raw_series)} brutas")

    series_ids, series = set(), []
    for s in raw_series:
        if not s.get("id") or s["id"] in series_ids: continue
        series_ids.add(s["id"])
        series.append(normalizar_serie(s))
    series.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    series = series[:TOP_CADA]

    # ── Mezclar, ordenar y asignar posición ─────────────────
    todo = peliculas + series
    todo.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    top  = todo[:TOP_N]

    # ── Tráilers ─────────────────────────────────────────────
    print(f"  → Obteniendo tráilers para {len(top)} items...")
    for i, item in enumerate(top, 1):
        item["posicion"] = i
        media = "movie" if item["tipo"] == "película" else "tv"
        item["trailer"] = get_trailer(item["tmdb_id"], media)
        tipo_icon = "🎬" if item["tipo"] == "película" else "📺"
        print(f"    {i:02d}. {tipo_icon} {item['titulo']} ({item['rating']})")

    # ── Guardar JSON ─────────────────────────────────────────
    os.makedirs(JSON_DIR, exist_ok=True)
    n_pelis  = sum(1 for x in top if x["tipo"] == "película")
    n_series = sum(1 for x in top if x["tipo"] == "serie")

    resultado = {
        "categoria":           "netflix",
        "nombre":              "Netflix",
        "plataforma":          "NETFLIX",
        "color":               "#e50914",
        "descripcion":         f"Lo más destacado de Netflix · {n_pelis} películas · {n_series} series",
        "fecha_actualizacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total":               len(top),
        "peliculas":           top,   # ← mantiene el nombre "peliculas" para compatibilidad con tops.js
    }

    path = os.path.join(JSON_DIR, "netflix_top50.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(resultado, f, ensure_ascii=False, indent=2)

    print(f"\n✅ Guardado en {path}")
    print(f"   🎬 Películas: {n_pelis}  📺 Series: {n_series}  Total: {len(top)}")

if __name__ == "__main__":
    main()