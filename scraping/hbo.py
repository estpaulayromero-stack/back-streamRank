"""
============================================================
  StreamRank — scraper_hbo.py
  Scrapea Top 50 Max (películas + series) desde TMDb API
  Guarda en: json/hbo_top50.json
============================================================
"""

import os
import json
import requests
from datetime import datetime
from get_duracion import get_duracion

API_KEY = "904483be1b785c0c354704cfef7156bc"
BASE_URL = "https://api.themoviedb.org/3"
IMG_BASE = "https://image.tmdb.org/t/p/w500"

TOP_N = 50
TOP_CADA = 40

JSON_DIR = os.path.join(
    os.path.dirname(
        os.path.abspath(__file__)
    ),
    "json"
)

# MAX provider id en TMDb
PROVIDER_ID = "1899"

GENEROS = {
    28:"Acción",
    12:"Aventura",
    16:"Animación",
    35:"Comedia",
    80:"Crimen",
    18:"Drama",
    10751:"Familiar",
    14:"Fantasía",
    27:"Terror",
    9648:"Misterio",
    878:"Ciencia Ficción",
    53:"Suspense",
    10765:"Ciencia Ficción",
    10759:"Acción",
    10762:"Infantil",
    10764:"Reality"
}


def get_trailer(tmdb_id, media="movie"):

    try:

        for idioma in ["es-ES", "en-US"]:

            r = requests.get(

                f"{BASE_URL}/{media}/{tmdb_id}/videos",

                params={
                    "api_key": API_KEY,
                    "language": idioma
                },

                timeout=8

            )

            videos = r.json().get("results", [])

            for video in videos:

                if (
                    video.get("site") == "YouTube"
                    and
                    video.get("type") == "Trailer"
                ):

                    return (
                        f"https://www.youtube.com/embed/"
                        f"{video['key']}"
                    )

    except Exception:

        pass

    return ""


def fetch_movies():

    resultados = []

    for page in range(1,4):

        params = {

            "api_key": API_KEY,
            "language": "es-ES",

            "with_watch_providers":
            PROVIDER_ID,

            "watch_region":
            "CO",

            "sort_by":
            "vote_average.desc",

            "vote_count.gte":
            50,

            "page":
            page
        }

        try:

            r = requests.get(

                f"{BASE_URL}/discover/movie",

                params=params,

                timeout=10

            )

            r.raise_for_status()

            resultados.extend(

                r.json().get(
                    "results",
                    []
                )

            )

        except Exception as e:

            print(
                "Error películas:",
                e
            )

    print(
        "Películas encontradas:",
        len(resultados)
    )

    return resultados


def fetch_series():

    resultados = []

    for page in range(1,4):

        params = {

            "api_key": API_KEY,
            "language": "es-ES",

            "with_watch_providers":
            PROVIDER_ID,

            "watch_region":
            "CO",

            "sort_by":
            "vote_average.desc",

            "vote_count.gte":
            30,

            "page":
            page
        }

        try:

            r = requests.get(

                f"{BASE_URL}/discover/tv",

                params=params,

                timeout=10

            )

            r.raise_for_status()

            resultados.extend(

                r.json().get(
                    "results",
                    []
                )

            )

        except Exception as e:

            print(
                "Error series:",
                e
            )

    print(
        "Series encontradas:",
        len(resultados)
    )

    return resultados


def normalizar(item, tipo):

    gids = item.get(
        "genre_ids",
        []
    )

    duracion_texto = "Serie TV"
    duracion_min = 0

    if tipo == "película":

        try:

            dur = get_duracion(
                item["id"]
            )

            if isinstance(
                dur,
                tuple
            ):

                duracion_min = dur[0]
                duracion_texto = dur[1]

        except:

            pass

    return {

        "tmdb_id":
        item["id"],

        "tipo":
        tipo,

        "titulo":

        item.get(
            "title",
            item.get(
                "name",
                "Sin título"
            )
        ),

        "rating":
        round(
            item.get(
                "vote_average",
                0
            ),
            1
        ),

        "votos":
        item.get(
            "vote_count",
            0
        ),

        "genero":

        GENEROS.get(
            gids[0],
            "Drama"
        ) if gids else "Drama",

        "imagen_url":

        f"{IMG_BASE}"
        f"{item['poster_path']}"

        if item.get(
            "poster_path"
        )

        else "",

        "fecha_estreno":

        item.get(
            "release_date",
            item.get(
                "first_air_date",
                ""
            )
        ),

        "descripcion":

        item.get(
            "overview",
            ""
        )[:300],

        "duracion_min":
        duracion_min,

        "duracion":
        duracion_texto,

        "trailer":
        ""
    }


def main():

    print(
        "Scrapeando Max..."
    )

    peliculas = [

        normalizar(
            x,
            "película"
        )

        for x in fetch_movies()

    ]

    series = [

        normalizar(
            x,
            "serie"
        )

        for x in fetch_series()

    ]

    todo = peliculas + series

    todo.sort(

        key=lambda x:
        (
            x["rating"],
            x["votos"]
        ),

        reverse=True
    )

    top = todo[:TOP_N]

    for i, item in enumerate(top,1):

        media = (

            "movie"

            if item["tipo"]
            == "película"

            else

            "tv"
        )

        item["posicion"] = i

        item["trailer"] = get_trailer(

            item["tmdb_id"],
            media

        )

        print(

            i,
            item["titulo"]

        )

    os.makedirs(

        JSON_DIR,

        exist_ok=True

    )

    resultado = {

        "categoria":
        "hbo",

        "nombre":
        "Max",

        "plataforma":
        "MAX",

        "color":
        "#5822b4",

        "descripcion":
        "Top películas y series Max",

        "fecha_actualizacion":

        datetime.now().strftime(

            "%Y-%m-%d %H:%M:%S"

        ),

        "total":
        len(top),

        "peliculas":
        top
    }

    archivo = os.path.join(

        JSON_DIR,

        "hbo_top50.json"

    )

    with open(

        archivo,

        "w",

        encoding="utf-8"

    ) as f:

        json.dump(

            resultado,

            f,

            ensure_ascii=False,

            indent=2

        )

    print(
        "\nGuardado:",
        archivo
    )


if __name__ == "__main__":

    main()