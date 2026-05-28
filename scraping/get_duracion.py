"""
Módulo reutilizable — get_duracion.py
Pegar en la misma carpeta scraping/ y hacer:
    from get_duracion import get_duracion
"""
import requests

API_KEY  = "904483be1b785c0c354704cfef7156bc"
BASE_URL = "https://api.themoviedb.org/3"

def get_duracion(tmdb_id: int, tipo: str) -> dict:
    """
    Devuelve {"minutos": 148, "texto": "148min"} para películas
    y      {"minutos": 45,  "texto": "~45min/ep"} para series.
    Si falla devuelve {"minutos": 0, "texto": "—"}.
    tipo: "pelicula" | "película" | "movie"  →  /movie/{id}
          cualquier otra cosa                →  /tv/{id}
    """
    es_pelicula = tipo.lower() in ("pelicula", "película", "movie")
    endpoint    = "movie" if es_pelicula else "tv"

    try:
        r = requests.get(
            f"{BASE_URL}/{endpoint}/{tmdb_id}",
            params={"api_key": API_KEY, "language": "es-ES"},
            timeout=8
        )
        r.raise_for_status()
        data = r.json()

        if es_pelicula:
            mins = data.get("runtime") or 0
            return {
                "minutos": mins,
                "texto":   f"{mins}min" if mins else "—"
            }
        else:
            # Para series TMDB da episode_run_time (lista) o last_episode_to_air.runtime
            rts = data.get("episode_run_time") or []
            mins = int(rts[0]) if rts else (
                (data.get("last_episode_to_air") or {}).get("runtime") or 0
            )
            return {
                "minutos": mins,
                "texto":   f"~{mins}min/ep" if mins else "—"
            }

    except Exception as e:
        return {"minutos": 0, "texto": "—"}