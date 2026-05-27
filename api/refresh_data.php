<?php
// ============================================================
//  StreamRank — refresh_data.php
//  Ruta: htdocs/back-streamRank/api/refresh_data.php
// ============================================================

// ── 1. Capturar CUALQUIER error fatal antes de que rompa ──
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Fatal PHP: ' . $err['message'],
            'file'    => $err['file'],
            'line'    => $err['line'],
        ]);
    }
});

// ── 2. Headers ─────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// ── 3. Solo POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// ── 4. Verificar que exec() esté disponible ─────────────────
if (!function_exists('exec')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'exec() está deshabilitado en PHP. Ve a php.ini, busca "disable_functions" y elimina "exec" de la lista. Luego reinicia Apache.',
    ]);
    exit;
}

// ── 5. Mapeo categoría → script Python ─────────────────────
$categoryMap = [
    'marvel'       => 'scraper_marvel.py',
    'netflix'      => 'scraper_netflix.py',
    'warner'       => 'scraper_warner.py',
    'disney'       => 'scraper_disney.py',
    'hbo'          => 'scraper_hbo.py',
    'amazon'       => 'scraper_amazon.py',
    'universal'    => 'scraper_universal.py',
    'starwars'     => 'scraper_starwars.py',
    'lucasfilm'    => 'scraper_lucasfilm.py',
    'pixar'        => 'scraper_pixar.py',
    'dreamworks'   => 'scraper_dreamworks.py',
    'ghibli'       => 'scraper_ghibli.py',
    'dc_studios'   => 'scraper_dc_studios.py',
    'dc_universe'  => 'scraper_dc_universe.py',
    'harry_potter' => 'scraper_harry_potter.py',
    'fast_furious' => 'scraper_fast_furious.py',
    'jurassic'     => 'scraper_jurassic.py',
    'hunger_games' => 'scraper_hunger_games.py',
];

// ── 6. Leer y validar input ─────────────────────────────────
$input    = json_decode(file_get_contents('php://input'), true);
$category = isset($input['category']) ? strtolower(trim($input['category'])) : null;

if (!$category || !isset($categoryMap[$category])) {
    http_response_code(400);
    echo json_encode([
        'success'           => false,
        'error'             => 'Categoría inválida o no encontrada',
        'category_received' => $category,
        'valid_categories'  => array_keys($categoryMap),
    ]);
    exit;
}

// ── 7. Rutas ────────────────────────────────────────────────
// __DIR__ = htdocs/back-streamRank/api
$scriptName = $categoryMap[$category];
$scriptPath = realpath(__DIR__ . '/../scraping/' . $scriptName);
$jsonDir    = realpath(__DIR__ . '/../json');
$jsonPath   = $jsonDir . DIRECTORY_SEPARATOR . $category . '_top50.json';

// Diagnóstico de rutas (útil para depurar)
$diag = [
    'script_path' => $scriptPath ?: (__DIR__ . '/../scraping/' . $scriptName . ' (no existe)'),
    'json_dir'    => $jsonDir    ?: (__DIR__ . '/../json (no existe)'),
    'json_path'   => $jsonPath,
];

if (!$scriptPath || !file_exists($scriptPath)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => "Script Python no encontrado: scraping/{$scriptName}",
        'diag'    => $diag,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$jsonDir || !is_dir($jsonDir)) {
    // Intentar crear la carpeta json si no existe
    if (!mkdir(__DIR__ . '/../json', 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'No existe la carpeta json/ y no se pudo crear',
            'diag'    => $diag,
        ]);
        exit;
    }
    $jsonDir  = realpath(__DIR__ . '/../json');
    $jsonPath = $jsonDir . DIRECTORY_SEPARATOR . $category . '_top50.json';
}

// ── 8. Detectar Python ─────────────────────────────────────
// En Windows con XAMPP Python suele no estar en el PATH de Apache.
// Probamos rutas comunes y también el PATH normal.
$pythonCandidates = [
    'python',                                      // PATH del sistema
    'python3',
    'C:\\Python312\\python.exe',
    'C:\\Python311\\python.exe',
    'C:\\Python310\\python.exe',
    'C:\\Python39\\python.exe',
    'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
    'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
    'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python310\\python.exe',
];

$pythonExe = null;
foreach ($pythonCandidates as $candidate) {
    $test   = [];
    $retVal = -1;
    exec(escapeshellcmd($candidate) . ' --version 2>&1', $test, $retVal);
    if ($retVal === 0) {
        $pythonExe = $candidate;
        break;
    }
}

if (!$pythonExe) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Python no encontrado. Instala Python y asegúrate de que esté en el PATH del sistema, o agrega su ruta completa en $pythonCandidates dentro de refresh_data.php.',
        'tried'   => $pythonCandidates,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 9. Ejecutar el script ───────────────────────────────────
$command    = escapeshellcmd($pythonExe) . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
$output     = [];
$returnCode = 0;

exec($command, $output, $returnCode);

// ── 10. Evaluar resultado ───────────────────────────────────
if ($returnCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'success'     => false,
        'error'       => 'El script Python terminó con error (código ' . $returnCode . ')',
        'output'      => $output,
        'command'     => $command,
        'python_used' => $pythonExe,
        'diag'        => $diag,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Esperar a que el archivo quede escrito en disco
usleep(600000); // 0.6 s

// ── 11. Leer y validar el JSON generado ────────────────────
if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode([
        'success'        => false,
        'error'          => 'El script corrió bien pero no generó el JSON esperado',
        'expected_path'  => $jsonPath,
        'command_output' => $output,
        'python_used'    => $pythonExe,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawJson     = file_get_contents($jsonPath);
$jsonContent = json_decode($rawJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        'success'    => false,
        'error'      => 'JSON generado inválido: ' . json_last_error_msg(),
        'raw_sample' => substr($rawJson, 0, 500),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 12. Respuesta exitosa ───────────────────────────────────
$updateTime   = $jsonContent['fecha_actualizacion'] ?? date('d/m/Y H:i');
$totalMovies  = isset($jsonContent['peliculas']) ? count($jsonContent['peliculas']) : 0;
$categoryName = $jsonContent['nombre'] ?? $category;

http_response_code(200);
echo json_encode([
    'success'        => true,
    'category'       => $category,
    'category_name'  => $categoryName,
    'update_time'    => $updateTime,
    'total_movies'   => $totalMovies,
    'message'        => "✅ {$categoryName} actualizado: {$totalMovies} películas",
    'python_used'    => $pythonExe,
    'command_output' => $output,
], JSON_UNESCAPED_UNICODE);