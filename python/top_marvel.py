import os
import requests
import json
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
JSON_PATH = os.path.join(BASE_DIR, 'json', 'marvel_top10.json')

# ============================================================
#  SCRAPER MARVEL — TMDb API
#  Obtiene el Top 10 de películas Marvel y guarda a JSON
# ============================================================

API_KEY = "904483be1b785c0c354704cfef7156bc"
BASE_URL = "https://api.themoviedb.org/3/discover/movie"
IMAGE_BASE = "https://image.tmdb.org/t/p/w500"

def obtener_top_marvel():
    """
    Obtiene las 10 mejores películas de Marvel ordenadas por rating
    """
    
    params = {
        "api_key": API_KEY,
        "language": "es-ES",
        "with_companies": 420,              # Marvel Studios
        "sort_by": "vote_average.desc",     # Mejor rating primero
        "vote_count.gte": 1000,             # Mínimo 1000 votos
        "page": 1
    }
    
    try:
        response = requests.get(BASE_URL, params=params, timeout=10)
        response.raise_for_status()  # Lanza error si status != 200
        
        data = response.json()
        peliculas_raw = data.get("results", [])
        
        # Procesar y limpiar datos
        peliculas = []
        for i, peli in enumerate(peliculas_raw[:10], start=1):
            
            # Manejar poster nulo
            poster_path = peli.get("poster_path")
            imagen_url = f"{IMAGE_BASE}{poster_path}" if poster_path else ""
            
            pelicula_limpia = {
                "posicion": i,
                "titulo": peli.get("title", "Sin título"),
                "titulo_original": peli.get("original_title", ""),
                "rating": round(peli.get("vote_average", 0), 1),
                "votos": peli.get("vote_count", 0),
                "generos": peli.get("genre_ids", []),
                "imagen_url": imagen_url,
                "fecha_estreno": peli.get("release_date", ""),
                "descripcion": peli.get("overview", "")[:200]  # Primeros 200 chars
            }
            
            peliculas.append(pelicula_limpia)
        
        return peliculas
    
    except requests.exceptions.RequestException as e:
        print(f"❌ Error al conectar con TMDb API: {e}")
        return []
    except json.JSONDecodeError:
        print("❌ Error al parsear respuesta de la API")
        return []
    except Exception as e:
        print(f"❌ Error inesperado: {e}")
        return []


def guardar_json(peliculas, nombre_archivo=JSON_PATH):
    """
    Guarda las películas en un archivo JSON con timestamp
    """
    
    if not peliculas:
        print("⚠️  No hay películas para guardar")
        return False
    
    try:
        os.makedirs(os.path.dirname(nombre_archivo), exist_ok=True)

        resultado = {
            "plataforma": "MARVEL",
            "fecha_actualizacion": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "total_peliculas": len(peliculas),
            "peliculas": peliculas
        }
        
        with open(nombre_archivo, 'w', encoding='utf-8') as f:
            json.dump(resultado, f, ensure_ascii=False, indent=2)
        
        print(f"✅ Guardado exitosamente en {nombre_archivo}")
        return True
    
    except Exception as e:
        print(f"❌ Error al guardar JSON: {e}")
        return False


def mostrar_top(peliculas):
    """
    Imprime el top en consola con formato bonito
    """
    
    if not peliculas:
        print("⚠️  No hay películas para mostrar")
        return
    
    print("\n" + "="*60)
    print("🎬 TOP 10 MARVEL — THE MOVIE DATABASE")
    print("="*60 + "\n")
    
    for peli in peliculas:
        print(f"{peli['posicion']}. {peli['titulo']}")
        print(f"   ⭐ Rating: {peli['rating']}/10 ({peli['votos']:,} votos)")
        print(f"   📅 Estreno: {peli['fecha_estreno']}")
        print(f"   🖼  Poster: {peli['imagen_url'] if peli['imagen_url'] else 'Sin imagen'}")
        print()


# ============================================================
#  EJECUCIÓN PRINCIPAL
# ============================================================

if __name__ == "__main__":
    print("🔍 Obteniendo Top 10 Marvel desde TMDb...\n")
    
    peliculas = obtener_top_marvel()
    
    if peliculas:
        mostrar_top(peliculas)
        guardar_json(peliculas)
    else:
        print("❌ No se pudieron obtener las películas")