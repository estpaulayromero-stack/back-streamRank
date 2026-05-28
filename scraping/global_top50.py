"""
StreamRank — scraper_global.py  (con duración real)
Genera: json/global_top50.json
Requiere: get_duracion.py en la misma carpeta
"""
import os, json, time, requests
from datetime import datetime
from get_duracion import get_duracion

API_KEY  = "904483be1b785c0c354704cfef7156bc"
BASE_URL = "https://api.themoviedb.org/3"
IMG_BASE = "https://image.tmdb.org/t/p/w500"
TOP_N    = 50
JSON_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "json")

GENEROS = {
    28:"Acción", 12:"Aventura", 16:"Animación", 35:"Comedia",
    80:"Crimen",  18:"Drama",   14:"Fantasía",  27:"Terror",
    878:"Ciencia Ficción", 53:"Suspense"
}

def request_tmdb(endpoint, params={}):
    params["api_key"] = API_KEY
    params["language"] = "es-ES"
    r = requests.get(f"{BASE_URL}/{endpoint}", params=params, timeout=10)
    r.raise_for_status()
    return r.json().get("results", [])

def get_trailer(media_type, media_id):
    try:
        for lang in ["es-ES", "en-US"]:
            r = requests.get(
                f"{BASE_URL}/{media_type}/{media_id}/videos",
                params={"api_key": API_KEY, "language": lang}, timeout=8
            )
            for v in r.json().get("results", []):
                if v.get("site") == "YouTube" and v.get("type") == "Trailer":
                    return f"https://www.youtube.com/embed/{v['key']}"
    except: pass
    return ""

def collect():
    data = []
    sources = [
        ("movie/top_rated",    "película"),
        ("tv/top_rated",       "serie"),
        ("movie/popular",      "película"),
        ("tv/popular",         "serie"),
        ("trending/movie/week","película"),
        ("trending/tv/week",   "serie"),
    ]
    for endpoint, tipo in sources:
        for page in range(1, 4):
            try:
                items = request_tmdb(endpoint, {"page": page})
                for item in items:
                    item["_tipo"] = tipo
                    data.append(item)
            except Exception as e:
                print(f"  ⚠ {endpoint} p{page}: {e}")
    return data

def build():
    raw    = collect()
    vistos = set()
    final  = []

    for item in raw:
        tmdb_id  = item.get("id")
        key      = f"{tmdb_id}_{item['_tipo']}"
        if key in vistos: continue
        vistos.add(key)

        votos = item.get("vote_count", 0)
        if votos < 1000: continue

        rating     = item.get("vote_average", 0)
        popularidad= item.get("popularity", 0)
        score      = rating * 0.7 + (popularidad / 100) * 0.3
        genres     = item.get("genre_ids", [])
        titulo     = item.get("title") or item.get("name") or "Sin título"

        final.append({
            "tmdb_id":      tmdb_id,
            "titulo":       titulo,
            "tipo":         item["_tipo"],
            "rating":       round(rating, 1),
            "popularidad":  round(popularidad),
            "score_global": round(score, 2),
            "genero":       GENEROS.get(genres[0], "Drama") if genres else "Drama",
            "imagen_url":   f"{IMG_BASE}{item['poster_path']}" if item.get("poster_path") else "",
            "descripcion":  item.get("overview", "")[:300],
            "fecha":        item.get("release_date") or item.get("first_air_date") or "",
            "trailer":      "",
            # duración real — se rellena abajo
            "duracion_min": 0,
            "duracion":     "—",
        })

    final.sort(key=lambda x: (x["score_global"], x["rating"], x["popularidad"]), reverse=True)
    top = final[:TOP_N]

    print(f"  Obteniendo tráilers y duraciones ({len(top)} items)...")
    for i, item in enumerate(top, 1):
        item["posicion"] = i

        # Tráiler
        media_type    = "movie" if item["tipo"] == "película" else "tv"
        item["trailer"] = get_trailer(media_type, item["tmdb_id"])

        # ── Duración real ──────────────────────────────
        dur = get_duracion(item["tmdb_id"], item["tipo"])
        item["duracion_min"] = dur["minutos"]   # número entero para filtros
        item["duracion"]     = dur["texto"]      # texto legible para mostrar

        print(f"    {i:02d}. {item['titulo']:40s} ⭐{item['rating']}  ⏱ {item['duracion']}")
        time.sleep(0.15)   # respetar rate-limit TMDB

    return top

def main():
    print("🎬 Scrapeando Global Top 50...")
    top = build()
    output = {
        "categoria":           "global",
        "nombre":              "Top Global",
        "plataforma":          "GLOBAL",
        "descripcion":         "Mejor contenido mundial combinado",
        "fecha_actualizacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total":               len(top),
        "peliculas":           top
    }
    os.makedirs(JSON_DIR, exist_ok=True)
    path = os.path.join(JSON_DIR, "global_top50.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(output, f, ensure_ascii=False, indent=2)
    print(f"  ✅ Guardado en {path}")

if __name__ == "__main__":
    main()