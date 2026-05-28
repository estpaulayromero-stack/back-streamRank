import os,json,requests
from datetime import datetime
from get_duracion import get_duracion

API_KEY="904483be1b785c0c354704cfef7156bc"
BASE_URL="https://api.themoviedb.org/3"
IMG_BASE="https://image.tmdb.org/t/p/w500"

TOP_N=50
TOP_CADA=40
PROVIDER_ID="350"

JSON_DIR=os.path.join(
    os.path.dirname(os.path.abspath(__file__)),
    "json"
)

GENEROS={
28:"Acción",12:"Aventura",16:"Animación",35:"Comedia",
80:"Crimen",18:"Drama",10751:"Familiar",14:"Fantasía",
27:"Terror",9648:"Misterio",878:"Ciencia Ficción",
53:"Suspense",10759:"Acción",10765:"Sci-Fi"
}

def get_trailer(mid,media="movie"):
    try:
        for lang in ["es-ES","en-US"]:
            r=requests.get(
                f"{BASE_URL}/{media}/{mid}/videos",
                params={"api_key":API_KEY,"language":lang},
                timeout=8
            )
            for v in r.json().get("results",[]):
                if v.get("site")=="YouTube" and v.get("type")=="Trailer":
                    return f"https://www.youtube.com/embed/{v['key']}"
    except:
        pass
    return ""

def fetch(tipo):
    items=[]
    endpoint="movie" if tipo=="pelicula" else "tv"

    for page in range(1,4):

        params={
            "api_key":API_KEY,
            "language":"es-ES",
            "with_watch_providers":PROVIDER_ID,
            "watch_region":"CO",
            "sort_by":"vote_average.desc",
            "vote_count.gte":50,
            "page":page
        }

        try:
            r=requests.get(
                f"{BASE_URL}/discover/{endpoint}",
                params=params,
                timeout=10
            )

            r.raise_for_status()

            items.extend(
                r.json().get("results",[])
            )

        except Exception as e:
            print(tipo,e)

    return items

def normalizar(x,tipo):

    gids=x.get("genre_ids",[])

    return{

        "tmdb_id":x["id"],
        "tipo":"película" if tipo=="pelicula" else "serie",

        "titulo":
        x.get("title",
        x.get("name","Sin título")),

        "rating":
        round(x.get("vote_average",0),1),

        "votos":
        x.get("vote_count",0),

        "genero":
        GENEROS.get(
            gids[0],
            "Drama"
        ) if gids else "Drama",

        "imagen_url":
        f"{IMG_BASE}{x['poster_path']}"
        if x.get("poster_path")
        else "",

        "fecha_estreno":
        x.get(
            "release_date",
            x.get(
                "first_air_date",
                ""
            )
        ),

        "descripcion":
        x.get("overview","")[:300],

        "duracion":
        "Serie TV"
        if tipo=="serie"
        else "—",

        "trailer":""
    }

def main():

    print("Scrapeando Apple TV+...")

    peliculas=[
        normalizar(x,"pelicula")
        for x in fetch("pelicula")
    ]

    series=[
        normalizar(x,"serie")
        for x in fetch("serie")
    ]

    todo=peliculas+series

    todo.sort(
        key=lambda x:
        (x["rating"],x["votos"]),
        reverse=True
    )

    top=todo[:TOP_N]

    for i,item in enumerate(top,1):

        item["posicion"]=i

        media=(
            "movie"
            if item["tipo"]=="película"
            else "tv"
        )

        item["trailer"]=get_trailer(
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

    resultado={

        "categoria":"appletv",
        "nombre":"Apple TV+",
        "plataforma":"APPLE TV",
        "color":"#000000",

        "descripcion":
        "Top Apple TV+",

        "fecha_actualizacion":
        datetime.now().strftime(
            "%Y-%m-%d %H:%M:%S"
        ),

        "total":len(top),

        "peliculas":top
    }

    path=os.path.join(
        JSON_DIR,
        "appletv_top50.json"
    )

    with open(
        path,
        "w",
        encoding="utf-8"
    ) as f:

        json.dump(
            resultado,
            f,
            ensure_ascii=False,
            indent=2
        )

    print("Guardado:",path)

if __name__=="__main__":
    main()