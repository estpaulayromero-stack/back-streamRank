<?php
// ============================================================
//  StreamRank — Configuración de conexión a MariaDB
//  Ruta: htdocs/streamrank/api/config.php
// ============================================================
 
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // usuario de XAMPP por defecto
define('DB_PASS', '');           // contraseña vacía por defecto en XAMPP
define('DB_NAME', 'streamrank');
define('DB_CHARSET', 'utf8mb4');
 
function get_db(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
 
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["error" => "Error de conexión: " . $conn->connect_error]);
        exit;
    }
 
    $conn->set_charset(DB_CHARSET);
    return $conn;
}
 
// Headers CORS + JSON (se aplican en todos los endpoints)
function set_headers(): void {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Content-Type: application/json; charset=utf-8");
 
    // Pre-flight OPTIONS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
 
// Leer JSON del body de la petición
function get_body(): array {
    $raw = file_get_contents("php://input");
    return json_decode($raw, true) ?? [];
}
 