"""
StreamRank — scraper_lucasfilm.py
Scrapea Top 50 Lucasfilm desde TMDb API
Guarda en: json/lucasfilm_top50.json
"""
import os, json, requests
from datetime import datetime

API_KEY  = "904483be1b785c0c354704cfef7156bc"
BASE_URL = "https://api.themoviedb.org/3"
IMG_BASE = "https://image.tmdb.org/t/p/w500"
TOP_N    = 50
JSON_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "json")
GENEROS  = {28:"Acción",12:"Aventura",16:"Animación",35:"Comedia",80:"Crimen",18:"Drama",14:"Fantasía",27:"Terror",878:"Ciencia Ficción",53:"Suspense"}

COMPANY_ID   = "1"
COMPANY_NAME = "Lucasfilm"

def get_trailer(mid):
    try:
        for lang in ["es-ES","en-US"]:
            r = requests.get(f"{BASE_URL}/movie/{mid}/videos", params={"api_key":API_KEY,"language":lang}, timeout=8)
            for v in r.json().get("results",[]):
                if v.get("site")=="YouTube" and v.get("type")=="Trailer":
                    return f"https://www.youtube.com/embed/{v['key']}"
    except: pass
    return ""

def fetch_peliculas():
    peliculas = []
    for page in range(1, 4):
        params = {"api_key":API_KEY,"language":"es-ES","with_companies":COMPANY_ID,"sort_by":"vote_average.desc","vote_count.gte":300,"page":page}
        try:
            r = requests.get(f"{BASE_URL}/discover/movie", params=params, timeout=10)
            r.raise_for_status()
            peliculas.extend(r.json().get("results",[]))
        except Exception as e:
            print(f"  Error página {page}: {e}")
    return peliculas

def main():
    print(f"🎬 Scrapeando {COMPANY_NAME}...")
    raw = fetch_peliculas()
    print(f"  {len(raw)} películas brutas")
    vistos, limpias = set(), []
    for p in raw:
        if not p.get("id") or p["id"] in vistos: continue
        vistos.add(p["id"])
        gids = p.get("genre_ids",[])
        limpias.append({
            "tmdb_id":       p["id"],
            "titulo":        p.get("title","Sin título"),
            "rating":        round(p.get("vote_average",0),1),
            "votos":         p.get("vote_count",0),
            "genero":        GENEROS.get(gids[0],"General") if gids else "General",
            "imagen_url":    f"{IMG_BASE}{p['poster_path']}" if p.get("poster_path") else "",
            "fecha_estreno": p.get("release_date",""),
            "descripcion":   p.get("overview","")[:300],
            "trailer":       ""
        })
    limpias.sort(key=lambda x:(x["rating"],x["votos"]), reverse=True)
    top = limpias[:TOP_N]
    print("  Obteniendo tráilers...")
    for i, p in enumerate(top, 1):
        p["posicion"] = i
        p["trailer"]  = get_trailer(p["tmdb_id"])
        print(f"    {i:02d}. {p['titulo']} ⭐{p['rating']}")
    os.makedirs(JSON_DIR, exist_ok=True)
    resultado = {
        "categoria":           "lucasfilm",
        "nombre":              "Lucasfilm",
        "plataforma":          "DISNEY+",
        "color":               "#ffe81f",
        "descripcion":         "Lo mejor de Lucasfilm",
        "fecha_actualizacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total":               len(top),
        "peliculas":           top
    }
    path = os.path.join(JSON_DIR, "lucasfilm_top50.json")
    with open(path,"w",encoding="utf-8") as f:
        json.dump(resultado, f, ensure_ascii=False, indent=2)
    print(f"  ✅ Guardado en {path}")

if __name__ == "__main__": main()