"""
============================================================
  StreamRank — scraper_amazon.py
  Scrapea Top 50 Amazon Prime Video (películas + series) desde TMDb API
  Guarda en: json/amazon_top50.json
============================================================
"""

# Módulos estándar de Python
import os       # para rutas de archivos y carpetas
import json     # para escribir el archivo .json final
import requests # para hacer llamadas HTTP a la API de TMDb
from datetime import datetime       # para registrar la fecha de actualización
from get_duracion import get_duracion  # módulo propio que consulta duración real en TMDb

# ── Configuración global ─────────────────────────────────────
API_KEY  = "904483be1b785c0c354704cfef7156bc"  # clave de acceso a la API de TMDb
BASE_URL = "https://api.themoviedb.org/3"       # URL base de todos los endpoints de TMDb
IMG_BASE = "https://image.tmdb.org/t/p/w500"   # URL base para construir URLs de pósters (ancho 500px)
TOP_N    = 50    # total de ítems que se guardarán en el JSON final
TOP_CADA = 40    # máximo de películas Y máximo de series antes de mezclarlos

# Carpeta json/ al mismo nivel que este script
JSON_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "json")

# Diccionario que traduce los IDs de género de TMDb a nombres en español
GENEROS = {
    28:"Acción",    12:"Aventura",  16:"Animación", 35:"Comedia",
    80:"Crimen",    18:"Drama",     10751:"Familiar",14:"Fantasía",
    27:"Terror",    9648:"Misterio",878:"Ciencia Ficción",53:"Suspense",
    10765:"Ciencia Ficción",  # género de series en TMDb
    10759:"Acción",           # género de acción/aventura para series
    10762:"Infantil",
    10764:"Reality",
}

# ID del proveedor Amazon Prime Video en TMDb (watch_providers)
PROVIDER_ID = "9"


# ── Función: obtener tráiler de YouTube ──────────────────────
def get_trailer(mid, media_type="movie"):
    """
    Busca el primer tráiler en YouTube para una película o serie.
    mid        : ID de TMDb del contenido
    media_type : "movie" para películas, "tv" para series
    Retorna    : URL embed de YouTube o cadena vacía si no encuentra
    """
    try:
        # Intenta primero en español, luego en inglés como fallback
        for lang in ["es-ES", "en-US"]:
            r = requests.get(
                f"{BASE_URL}/{media_type}/{mid}/videos",
                params={"api_key": API_KEY, "language": lang},
                timeout=8  # abandona si tarda más de 8 segundos
            )
            # Recorre los videos devueltos por TMDb
            for v in r.json().get("results", []):
                # Solo acepta videos de YouTube que sean de tipo "Trailer"
                if v.get("site") == "YouTube" and v.get("type") == "Trailer":
                    return f"https://www.youtube.com/embed/{v['key']}"
    except:
        pass  # si falla la red o el parsing, simplemente retorna vacío
    return ""  # retorno por defecto si no se encontró tráiler


# ── Función: traer películas de Amazon Prime ─────────────────
def fetch_peliculas():
    """
    Consulta el endpoint /discover/movie de TMDb filtrando por
    proveedor Amazon Prime y ordenando por rating descendente.
    Recorre 3 páginas para obtener más resultados.
    """
    items = []  # lista acumuladora de resultados crudos
    for page in range(1, 4):  # páginas 1, 2 y 3
        params = {
            "api_key":             API_KEY,
            "language":            "es-ES",        # respuesta en español
            "with_watch_providers": PROVIDER_ID,   # solo contenido de Amazon
            "watch_region":        "US",            # región de disponibilidad
            "sort_by":             "vote_average.desc",  # mejor puntuados primero
            "vote_count.gte":      400,             # mínimo 400 votos para evitar resultados irrelevantes
            "page":                page             # número de página actual
        }
        try:
            r = requests.get(f"{BASE_URL}/discover/movie", params=params, timeout=10)
            r.raise_for_status()  # lanza excepción si el status HTTP no es 200
            items.extend(r.json().get("results", []))  # agrega los resultados a la lista
        except Exception as e:
            print(f"  [películas] Error página {page}: {e}")  # informa el error y continúa
    return items  # devuelve todos los resultados crudos sin filtrar


# ── Función: traer series de Amazon Prime ────────────────────
def fetch_series():
    """
    Igual que fetch_peliculas pero usa /discover/tv para series.
    El umbral de votos es más bajo (100) porque las series
    tienen menos votos en promedio que las películas.
    """
    items = []
    for page in range(1, 4):
        params = {
            "api_key":             API_KEY,
            "language":            "es-ES",
            "with_watch_providers": PROVIDER_ID,
            "watch_region":        "US",
            "sort_by":             "vote_average.desc",
            "vote_count.gte":      100,  # umbral más bajo para series
            "page":                page
        }
        try:
            r = requests.get(f"{BASE_URL}/discover/tv", params=params, timeout=10)
            r.raise_for_status()
            items.extend(r.json().get("results", []))
        except Exception as e:
            print(f"  [series] Error página {page}: {e}")
    return items


# ── Función: normalizar un ítem de película ──────────────────
def normalizar_pelicula(p):
    """
    Transforma el dict crudo de TMDb en el formato estándar
    que usa StreamRank. Llama a get_duracion para obtener
    el runtime real de la película.
    """
    gids = p.get("genre_ids", [])        # lista de IDs de géneros
    dur  = get_duracion(p["id"], "pelicula")  # consulta duración real a TMDb
    return {
        "tmdb_id":      p["id"],
        "tipo":         "película",       # literal para que los filtros JS funcionen
        "titulo":       p.get("title", "Sin título"),
        "rating":       round(p.get("vote_average", 0), 1),  # redondea a 1 decimal
        "votos":        p.get("vote_count", 0),
        "genero":       GENEROS.get(gids[0], "Drama") if gids else "Drama",  # primer género o "Drama" por defecto
        "imagen_url":   f"{IMG_BASE}{p['poster_path']}" if p.get("poster_path") else "",  # URL completa del póster
        "fecha_estreno": p.get("release_date", ""),   # formato "YYYY-MM-DD"
        "descripcion":  p.get("overview", "")[:300],  # recorta a 300 caracteres
        "duracion_min": dur["minutos"],   # número entero para el filtro de duración
        "duracion":     dur["texto"],     # texto legible ej: "148min"
        "trailer":      "",               # se rellena después en el loop principal
    }


# ── Función: normalizar un ítem de serie ────────────────────
def normalizar_serie(s):
    """
    Igual que normalizar_pelicula pero adaptado a la
    estructura de datos de series en TMDb (name en vez de title,
    first_air_date en vez de release_date).
    """
    gids = s.get("genre_ids", [])
    dur  = get_duracion(s["id"], "serie")  # duración por episodio
    return {
        "tmdb_id":      s["id"],
        "tipo":         "serie",          # literal para filtros JS
        "titulo":       s.get("name", "Sin título"),      # series usan "name" no "title"
        "rating":       round(s.get("vote_average", 0), 1),
        "votos":        s.get("vote_count", 0),
        "genero":       GENEROS.get(gids[0], "Drama") if gids else "Drama",
        "imagen_url":   f"{IMG_BASE}{s['poster_path']}" if s.get("poster_path") else "",
        "fecha_estreno": s.get("first_air_date", ""),     # series usan "first_air_date"
        "descripcion":  s.get("overview", "")[:300],
        "duracion_min": dur["minutos"],   # minutos por episodio
        "duracion":     dur["texto"],     # ej: "~45min/ep"
        "trailer":      "",
    }


# ── Función principal ────────────────────────────────────────
def main():
    print("Scrapeando Amazon Prime Video (películas + series)...")

    # ── PASO 1: obtener y limpiar películas ──────────────────
    raw_p = fetch_peliculas()          # trae resultados crudos de TMDb
    vistos, peliculas = set(), []      # set para deduplicar por ID
    for p in raw_p:
        if not p.get("id") or p["id"] in vistos:
            continue                   # salta duplicados o ítems sin ID
        vistos.add(p["id"])            # marca como procesado
        peliculas.append(normalizar_pelicula(p))  # normaliza y agrega

    # Ordena por rating y votos (mejor primero), luego recorta a TOP_CADA
    peliculas.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    peliculas = peliculas[:TOP_CADA]   # queda con las 40 mejores películas

    # ── PASO 2: obtener y limpiar series ─────────────────────
    raw_s = fetch_series()
    sids, series = set(), []           # set separado para no mezclar IDs de series con películas
    for s in raw_s:
        if not s.get("id") or s["id"] in sids:
            continue
        sids.add(s["id"])
        series.append(normalizar_serie(s))

    series.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)
    series = series[:TOP_CADA]         # queda con las 40 mejores series

    # ── PASO 3: mezclar y seleccionar el Top 50 global ───────
    todo = peliculas + series          # une las dos listas
    todo.sort(key=lambda x: (x["rating"], x["votos"]), reverse=True)  # re-ordena la mezcla
    top = todo[:TOP_N]                 # selecciona los 50 mejores del total

    # ── PASO 4: obtener tráilers (llamada extra por ítem) ────
    print(f"  Obteniendo tráilers para {len(top)} items...")
    for i, item in enumerate(top, 1):
        item["posicion"] = i           # asigna la posición final (1–50)
        media = "movie" if item["tipo"] == "película" else "tv"  # tipo para endpoint TMDb
        item["trailer"] = get_trailer(item["tmdb_id"], media)    # busca tráiler en YouTube
        print(f"    {i:02d}. {'🎬' if media=='movie' else '📺'} {item['titulo']} ({item['rating']})")

    # ── PASO 5: guardar JSON ─────────────────────────────────
    os.makedirs(JSON_DIR, exist_ok=True)  # crea la carpeta json/ si no existe

    # Contadores para el resumen en la descripción
    n_p = sum(1 for x in top if x["tipo"] == "película")
    n_s = sum(1 for x in top if x["tipo"] == "serie")

    # Estructura final del JSON con metadatos y la lista de ítems
    resultado = {
        "categoria":  "amazon",                 # clave que usa tops.js para identificar
        "nombre":     "Amazon Prime",           # nombre visible en el título de la página
        "plataforma": "PRIME VIDEO",            # etiqueta de la tarjeta
        "color":      "#00a8e1",                # color representativo de Amazon (azul)
        "descripcion": f"Lo mejor de Prime Video · {n_p} películas · {n_s} series",
        "fecha_actualizacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),  # timestamp actual
        "total":      len(top),                 # cantidad real de ítems guardados
        "peliculas":  top,                      # array principal que lee tops.js y search.js
    }

    # Escribe el archivo JSON en disco con encoding UTF-8 e indentación legible
    path = os.path.join(JSON_DIR, "amazon_top50.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(resultado, f, ensure_ascii=False, indent=2)

    print(f"\n✅ Guardado en {path}  🎬{n_p}  📺{n_s}")


# ── Punto de entrada ─────────────────────────────────────────
# Solo se ejecuta si corres el script directamente (no si lo importas)
if __name__ == "__main__":
    main()