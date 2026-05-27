<?php
/**
 * ============================================================
 *  StreamRank — refresh_data.php
 *  Ejecuta el script Python para actualizar los JSONs
 * ============================================================
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Mapeo de categorías a scripts Python
$categoryMap = [
    'marvel'       => 'top_marvel.py',
    'netflix'      => 'netflix.py',
    'warner'       => 'warner.py',
    'disney'       => 'disney.py',
    'hbo'          => 'hbo.py',
    'amazon'       => 'amazon.py',
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
    'jurassic'     => 'jurassic.py',
];

try {
    // Obtener la categoría del request
    $input = json_decode(file_get_contents('php://input'), true);
    $category = isset($input['category']) ? strtolower(trim($input['category'])) : null;

    if (!$category || !isset($categoryMap[$category])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Categoría inválida',
            'category_received' => $category
        ]);
        exit;
    }


    $script = $categoryMap[$category];
    $scriptPath = __DIR__ . '/../scraping/' . $script;
    $jsonPath = __DIR__ . '/../json/' . $category . '_top50.json';

    // Verificar que el script existe
    if (!file_exists($scriptPath)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Script Python no encontrado',
            'path' => $scriptPath
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Ejecutar el script Python una sola vez
    $pythonPath = 'python'; // Cambiar a 'python3' si tu sistema lo requiere
    $pythonPathEsc = escapeshellcmd($pythonPath);
    $scriptArg = escapeshellarg($scriptPath);
    $command = $pythonPathEsc . ' ' . $scriptArg . ' 2>&1';

    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    // Si el script falló, devolver error con la salida
    if ($returnCode !== 0) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error ejecutando script Python',
            'command' => $command,
            'return_code' => $returnCode,
            'output' => $output,
            'script_path' => $scriptPath
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Pequeña espera para asegurar que el proceso haya escrito el JSON
    usleep(500000); // 0.5s

    // Verificar que el JSON fue creado/actualizado
    if (!file_exists($jsonPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'El JSON no fue generado',
            'expected_path' => $jsonPath,
            'command_output' => $output
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Leer la información del JSON y validarla
    $rawJson = file_get_contents($jsonPath);
    $jsonContent = json_decode($rawJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'El JSON generado es inválido',
            'json_error' => json_last_error_msg(),
            'raw' => substr($rawJson, 0, 2000)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updateTime = $jsonContent['fecha_actualizacion'] ?? date('Y-m-d H:i:s');
    $totalMovies = isset($jsonContent['peliculas']) ? count($jsonContent['peliculas']) : 0;
    $categoryName = $jsonContent['nombre'] ?? 'Desconocido';

    // Retornar éxito con información detallada
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'category' => $category,
        'update_time' => $updateTime,
        'total_movies' => $totalMovies,
        'category_name' => $categoryName,
        'message' => "Datos actualizados correctamente ({$totalMovies} películas)",
        'command' => $command,
        'command_output' => $output
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
