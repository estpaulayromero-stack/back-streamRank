<?php

// ============================================================
// STREAMRANK — refresh_data.php
// Ejecuta scripts Python y actualiza JSON
// ============================================================

// ─────────────────────────────────────────────────────────────
// HEADERS
// ─────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error'   => 'Método no permitido'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ─────────────────────────────────────────────────────────────
// LEER INPUT
// ─────────────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');

$input = json_decode($rawInput, true);

$category = isset($input['category'])
    ? strtolower(trim($input['category']))
    : 'amazon';

// ─────────────────────────────────────────────────────────────
// MAPA DE SCRIPTS
// ─────────────────────────────────────────────────────────────
$map = [
    'amazon'       => 'amazon.py',
    'marvel'       => 'top_marvel.py',
    'netflix'      => 'netflix.py',
    'warner'       => 'warner.py',
    'disney'       => 'disney.py',
    'hbo'          => 'hbo.py',
    'universal'    => 'Universal.py',
    'starwars'     => 'starwars.py',
    'lucasfilm'    => 'lucasfilms.py',
    'pixar'        => 'pixar.py',
    'dreamworks'   => 'dreamworks.py',
    'ghibli'       => 'ghibli.py',
    'dc_studios'   => 'dcestudios.py',
    'dc_universe'  => 'dcuniverse.py',
    'harry_potter' => 'harrypotter.py',
    'fast_furious' => 'fastfurious.py',
    'jurassic'     => 'jurassic.py'
];

// ─────────────────────────────────────────────────────────────
// VALIDAR CATEGORÍA
// ─────────────────────────────────────────────────────────────
if (!isset($map[$category])) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error'   => 'Categoría inválida',
        'category_received' => $category
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ─────────────────────────────────────────────────────────────
// RUTAS
// ─────────────────────────────────────────────────────────────
$scriptPath = realpath(__DIR__ . '/../scraping/' . $map[$category]);

$jsonPath = realpath(__DIR__ . '/../json');

if (!$scriptPath || !file_exists($scriptPath)) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'error'   => 'Script Python no encontrado',
        'script'  => $map[$category],
        'path'    => $scriptPath
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ─────────────────────────────────────────────────────────────
// PYTHON
// ─────────────────────────────────────────────────────────────
$python = "C:\\Python314\\python.exe";

if (!file_exists($python)) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => 'Python no encontrado',
        'python_path' => $python
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ─────────────────────────────────────────────────────────────
// COMANDO
// ─────────────────────────────────────────────────────────────
$command = "\"{$python}\" \"{$scriptPath}\" 2>&1";

$output = [];
$returnCode = 0;

// Ejecutar script
exec($command, $output, $returnCode);

// ─────────────────────────────────────────────────────────────
// ERROR PYTHON
// ─────────────────────────────────────────────────────────────
if ($returnCode !== 0) {

    http_response_code(500);

    echo json_encode([
        'success'     => false,
        'error'       => 'Error ejecutando script Python',
        'return_code' => $returnCode,
        'command'     => $command,
        'output'      => $output
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ─────────────────────────────────────────────────────────────
// RESPUESTA EXITOSA
// ─────────────────────────────────────────────────────────────
http_response_code(200);

echo json_encode([
    'success'      => true,
    'category'     => $category,
    'message'      => 'Datos actualizados correctamente',
    'return_code'  => $returnCode,
    'command'      => $command,
    'output'       => $output
], JSON_UNESCAPED_UNICODE);

exit;

?>