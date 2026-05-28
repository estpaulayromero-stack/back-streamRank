"""
StreamRank — scraper_dc_studios.py
Películas + Series DC Studios
Guarda: json/dc_studios_top50.json
"""

import os, json, requests
from datetime import datetime
from get_duracion import get_duracion

API_KEY="904483be1b785c0c354704cfef7156bc"
BASE_URL="https://api.themoviedb.org/3"
IMG_BASE="https://image.tmdb.org/t/p/w500"
TOP_N=50
TOP_CADA=40
COMPANY_ID="9993"
JSON_DIR=os.path.join(os.path.dirname(os.path.abspath(__file__)), "json")

GENEROS={
28:"Acción",12:"Aventura",16:"Animación",35:"Comedia",
80:"Crimen",18:"Drama",14:"Fantasía",27:"Terror",
878:"Ciencia Ficción",53:"Suspense",10759:"Acción",
10765:"Ciencia Ficción"
}

def get_trailer(mid,tipo="movie"):
    try:
        for lang in ["es-ES","en-US"]:
            r=requests.get(
                f"{BASE_URL}/{tipo}/{mid}/videos",
                params={"api_key":API_KEY,"language":lang},
                timeout=8
            )
            for v in r.json().get("results",[]):
                if v.get("site")=="YouTube" and v.get("type")=="Trailer":
                    return f"https://www.youtube.com/embed/{v['key']}"
    except:
        pass
    return ""

def fetch(media):
    datos=[]
    for page in range(1,4):
        params={
            "api_key":API_KEY,
            "language":"es-ES",
            "with_companies":COMPANY_ID,
            "sort_by":"vote_average.desc",
            "vote_count.gte":200,
            "page":page
        }

        try:
            r=requests.get(
                f"{BASE_URL}/discover/{media}",
                params=params,
                timeout=10
            )

            r.raise_for_status()
            datos.extend(r.json().get("results",[]))

        except Exception as e:
            print(media,e)

    return datos

def normalizar(item,tipo):
    gids=item.get("genre_ids",[])

    if tipo=="película":
        dur=get_duracion(item["id"])

        return {
            "tmdb_id":item["id"],
            "tipo":"película",
            "titulo":item.get("title","Sin título"),
            "rating":round(item.get("vote_average",0),1),
            "votos":item.get("vote_count",0),
            "genero":GENEROS.get(gids[0],"General") if gids else "General",
            "imagen_url":f"{IMG_BASE}{item['poster_path']}" if item.get("poster_path") else "",
            "fecha_estreno":item.get("release_date",""),
            "descripcion":item.get("overview","")[:300],
            "duracion_min":dur[0] if isinstance(dur,tuple) else 0,
            "duracion":dur[1] if isinstance(dur,tuple) else "—",
            "trailer":""
        }

    return {
        "tmdb_id":item["id"],
        "tipo":"serie",
        "titulo":item.get("name","Sin título"),
        "rating":round(item.get("vote_average",0),1),
        "votos":item.get("vote_count",0),
        "genero":GENEROS.get(gids[0],"General") if gids else "General",
        "imagen_url":f"{IMG_BASE}{item['poster_path']}" if item.get("poster_path") else "",
        "fecha_estreno":item.get("first_air_date",""),
        "descripcion":item.get("overview","")[:300],
        "duracion_min":0,
        "duracion":"Serie TV",
        "trailer":""
    }

def main():

    print("Scrapeando DC Studios...")

    peliculas=[
        normalizar(x,"película")
        for x in fetch("movie")
    ][:TOP_CADA]

    series=[
        normalizar(x,"serie")
        for x in fetch("tv")
    ][:TOP_CADA]

    todo=peliculas+series
    todo.sort(
        key=lambda x:(x["rating"],x["votos"]),
        reverse=True
    )

    top=todo[:TOP_N]

    for i,item in enumerate(top,1):

        item["posicion"]=i

        media="movie" if item["tipo"]=="película" else "tv"

        item["trailer"]=get_trailer(
            item["tmdb_id"],
            media
        )

        print(
            f"{i:02d}. "
            f"{'🎬' if media=='movie' else '📺'} "
            f"{item['titulo']}"
        )

    os.makedirs(JSON_DIR,exist_ok=True)

    resultado={
        "categoria":"dc_studios",
        "nombre":"DC Studios",
        "plataforma":"MAX",
        "color":"#0075c4",
        "descripcion":"Películas y series DC Studios",
        "fecha_actualizacion":datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total":len(top),
        "peliculas":top
    }

    path=os.path.join(
        JSON_DIR,
        "dc_studios_top50.json"
    )

    with open(path,"w",encoding="utf-8") as f:
        json.dump(
            resultado,
            f,
            ensure_ascii=False,
            indent=2
        )

    print("✅ Guardado:",path)

if __name__=="__main__":
    main()