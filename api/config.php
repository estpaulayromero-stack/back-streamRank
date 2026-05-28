<?php
// Archivo de configuración central — todos los endpoints hacen require de este archivo

// Constantes globales de conexión a la BD (inmutables, accesibles en todo el proyecto)
define('DB_HOST', 'localhost');      // servidor de BD (misma máquina)
define('DB_USER', 'root');           // usuario por defecto de XAMPP
define('DB_PASS', '');               // contraseña vacía por defecto en XAMPP
define('DB_NAME', 'streamrank');     // nombre de la base de datos
define('DB_CHARSET', 'utf8mb4');     // charset completo: soporta tildes y emojis

// Abre y retorna una conexión a MariaDB lista para usar
function get_db(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); // intenta conectar

    if ($conn->connect_error) {                              // si falló la conexión
        http_response_code(500);                             // responde error 500
        echo json_encode(["error" => "Error de conexión: " . $conn->connect_error]);
        exit;                                                // detiene el script
    }

    $conn->set_charset(DB_CHARSET); // evita que tildes y caracteres especiales se corrompan
    return $conn;                   // devuelve la conexión al endpoint que la pidió
}

// Envía las cabeceras HTTP necesarias — debe llamarse al inicio de cada endpoint
function set_headers(): void {
    header("Access-Control-Allow-Origin: *");                        // permite peticiones desde cualquier dominio
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS"); // métodos HTTP permitidos
    header("Access-Control-Allow-Headers: Content-Type");            // permite enviar JSON en el body
    header("Content-Type: application/json; charset=utf-8");         // la respuesta siempre es JSON

    // El navegador envía OPTIONS automáticamente antes de POST/DELETE para verificar permisos (pre-flight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200); // confirma que está permitido
        exit;                    // termina — el navegador luego envía la petición real
    }
}

// Lee y parsea el JSON que el frontend envía en el body de la petición
function get_body(): array {
    $raw = file_get_contents("php://input"); // lee el body crudo de la petición HTTP
    return json_decode($raw, true) ?? [];    // convierte JSON a array PHP; si falla retorna []
}