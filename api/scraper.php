<?php
// ============================================================
//  StreamRank — api/scraper.php
//  Ejecuta el scraper Python correspondiente a cada categoria
// ============================================================

require_once __DIR__ . '/config.php';
set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Metodo no permitido"]);
    exit;
}

$data      = get_body();
$categoria = trim($data['categoria'] ?? '');

// Mapa categoria -> archivo scraper
$scrapers = [
    'marvel'    => 'marvel.py',
    'netflix'   => 'netflix.py',
    'warner'    => 'warner.py',
    'disney'    => 'disney.py',
    'hbo'       => 'hbo.py',
    'amazon'    => 'amazon.py',
    'universal' => 'universal.py',
    'starwars'  => 'starwars.py',
    'appletv'   => 'appletv.py',
    'jamesbond'  => 'jamesbond.py',
    'dcestudios'  => 'dcestudios.py',
    'ghibli'  => 'ghibli.py',
];

if (!$categoria || !isset($scrapers[$categoria])) {
    http_response_code(400);
    echo json_encode(["error" => "Categoria invalida. Validas: " . implode(', ', array_keys($scrapers))]);
    exit;
}

$scraper_file = $scrapers[$categoria];
$scraper_path = realpath(__DIR__ . '/../' . $scraper_file);

if (!$scraper_path || !file_exists($scraper_path)) {
    http_response_code(500);
    echo json_encode(["error" => "No se encontro {$scraper_file}. Verifica que este en la raiz del proyecto."]);
    exit;
}

// Ejecutar Python
$python = 'python';
$cmd    = escapeshellcmd("$python \"$scraper_path\"") . " 2>&1";
$output = shell_exec($cmd);

// Verificar JSON generado
$json_path = __DIR__ . "/../json/{$categoria}_top50.json";

if (!file_exists($json_path)) {
    http_response_code(500);
    echo json_encode([
        "error"  => "El scraper no genero el JSON. Revisa que Python este instalado y requests este instalado (pip install requests).",
        "output" => $output
    ]);
    exit;
}

$json_data = json_decode(file_get_contents($json_path), true);

echo json_encode([
    "ok"                  => true,
    "categoria"           => $categoria,
    "total"               => $json_data['total']               ?? 0,
    "fecha_actualizacion" => $json_data['fecha_actualizacion'] ?? date('Y-m-d H:i:s'),
]);