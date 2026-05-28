<?php
require_once __DIR__ . '/config.php'; // importa get_db(), set_headers(), get_body()
set_headers();                         // aplica CORS y Content-Type JSON

// este endpoint solo acepta POST — si llega otro método lo rechaza
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);                                    // 405 = Method Not Allowed
    echo json_encode(["error" => "Metodo no permitido"]);
    exit;
}

$data      = get_body();                      // lee el JSON del body
$categoria = trim($data['categoria'] ?? ''); // ej: "netflix", "marvel"

// whitelist: solo estas categorías son válidas — mapea nombre → archivo .py
$scrapers = [
    'marvel'     => 'marvel.py',
    'netflix'    => 'netflix.py',
    'warner'     => 'warner.py',
    'disney'     => 'disney.py',
    'hbo'        => 'hbo.py',
    'amazon'     => 'amazon.py',
    'universal'  => 'universal.py',
    'starwars'   => 'starwars.py',
    'appletv'    => 'appletv.py',
    'jamesbond'  => 'jamesbond.py',
    'dcestudios' => 'dcestudios.py',
    'ghibli'     => 'ghibli.py',
];

// si la categoría no existe en el mapa, rechaza la petición
if (!$categoria || !isset($scrapers[$categoria])) {
    http_response_code(400);                  // 400 = Bad Request
    echo json_encode(["error" => "Categoria invalida. Validas: " . implode(', ', array_keys($scrapers))]);
    exit;
}

$scraper_file = $scrapers[$categoria];                        // ej: "netflix.py"
$scraper_path = realpath(__DIR__ . '/../' . $scraper_file);  // ruta absoluta real del archivo

// verifica que el archivo .py existe en disco antes de intentar ejecutarlo
if (!$scraper_path || !file_exists($scraper_path)) {
    http_response_code(500);
    echo json_encode(["error" => "No se encontro {$scraper_file}. Verifica que este en la raiz del proyecto."]);
    exit;
}

$python = 'python';                                                   // comando Python del sistema
$cmd    = escapeshellcmd("$python \"$scraper_path\"") . " 2>&1";     // construye el comando; escapeshellcmd previene inyección; 2>&1 captura errores junto con la salida normal
$output = shell_exec($cmd);                                           // ejecuta el comando y guarda lo que imprime Python

// después de ejecutar el scraper, verifica que haya generado el JSON esperado
$json_path = __DIR__ . "/../json/{$categoria}_top50.json"; // ej: json/netflix_top50.json

// si el JSON no existe, el scraper falló
if (!file_exists($json_path)) {
    http_response_code(500);
    echo json_encode([
        "error"  => "El scraper no genero el JSON. Revisa que Python este instalado y requests este instalado (pip install requests).",
        "output" => $output  // muestra lo que imprimió Python para ayudar a diagnosticar
    ]);
    exit;
}

$json_data = json_decode(file_get_contents($json_path), true); // lee y parsea el JSON generado

// responde con un resumen del resultado
echo json_encode([
    "ok"                  => true,
    "categoria"           => $categoria,
    "total"               => $json_data['total']               ?? 0,                // cuántos ítems tiene
    "fecha_actualizacion" => $json_data['fecha_actualizacion'] ?? date('Y-m-d H:i:s'), // cuándo se actualizó
]);