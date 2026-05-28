🔧 Backend – Streamrank

Este repositorio contiene el backend del sistema desarrollado con php.

👥 Integrantes

Paula Yurany Romero Rojas - 1202544 

🎯 Objetivo del Backend

Implementar un servidor HTTP capaz de:

Gestionar solicitudes REST.

Procesar datos enviados desde el frontend.

Conectarse a base de datos.

Implementar operaciones CRUD.

Retornar respuestas en formato JSON.

🏗️ Arquitectura

El backend sigue una arquitectura modular basada en endpoints PHP:

api/

Contiene los endpoints que reciben peticiones del frontend:

login.php → autenticación
registro.php → creación de usuarios
listas.php → manejo de listas personales
historial.php → historial de visualización
tops.php → tops personalizados
perfil.php → configuración de usuario
refresh_data.php → ejecución de scrapers Python
json/

Archivos JSON generados automáticamente por los scrapers.

scraping/

Scripts Python encargados de consumir TMDb y generar rankings.

config.php

Archivo compartido para conexión a base de datos y configuración global.

📡 Endpoints
Usuarios
Método	Ruta	            Descripción
POST	/api/login.php	    Iniciar sesión
POST	/api/registro.php	Registrar usuario
POST	/api/perfil.php 	Actualizar perfil
Listas
Método	Ruta	            Descripción
GET	/api/listas.php	        Obtener listas
POST	/api/listas.php	    Crear lista
DELETE	/api/listas.php  	Eliminar lista
Historial
Método	Ruta            	Descripción
GET	/api/historial.php	    Obtener historial
POST	/api/historial.php	Agregar historial
DELETE	/api/historial.php	Eliminar historial
Scraping
Método	Ruta	                        Descripción
GET	/api/refresh_data.php?cat=netflix	Ejecuta scraper y actualiza JSON
🗄️ Base de Datos

Motor utilizado:

MariaDB (phpMyAdmin / XAMPP)

Tablas principales:

users
listas
historial
tops_personales
tops_items

Conexión usando:

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

Colocar aquí la URL generada por Render:

https://nombre-del-servicio.onrender.com

🚀 Despliegue en Render
1️⃣ Subir proyecto a GitHub
git init
git add .
git commit -m "Backend inicial"
git push origin main

2️⃣ Crear servicio en Render

Ir a https://render.com

New → Web Service

Conectar repositorio GitHub

Configurar:

Build Command: npm install

Start Command: npm start

Environment: Node

3️⃣ Variable de Puerto

Render asigna automáticamente el puerto mediante:

process.env.PORT

🗄️ Base de Datos

Si se usa base de datos externa (Render PostgreSQL o MySQL):

Configurar variables de entorno:

DB_HOST

DB_USER

DB_PASSWORD

DB_NAME

🔐 Validaciones

Validación de datos obligatorios.

Manejo de errores HTTP (200, 400, 404, 500).

Respuestas en formato JSON.

📚 Aprendizajes

Despliegue en la nube.

Configuración de variables de entorno.

Separación de responsabilidades.

Arquitectura básica de backend.

⚠️ Errores Comunes en Render

❌ Puerto fijo (3000 sin process.env.PORT)
❌ Falta de script "start"
❌ No subir package.json
❌ No hacer commit antes de conectar