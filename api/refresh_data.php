<?php
/**
 * ============================================================
 *  StreamRank — refresh_data.php
 *  Ejecuta el script Python para actualizar los JSONs
 * ============================================================
 */

header('Content-Type: application/json');

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
    $scriptPath = __DIR__ . '/scraping/' . $script;
    $jsonPath = __DIR__ . '/json/' . $category . '_top50.json';

    // Verificar que el script existe
    if (!file_exists($scriptPath)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Script Python no encontrado',
            'path' => $scriptPath
        ]);
        exit;
    }

    // Ejecutar el script Python
    $pythonPath = 'python'; // O 'python3' si es necesario
    $command = escapeshellcmd($pythonPath . ' ' . $scriptPath);
    
    // Ejecutar con redirección de errores
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error ejecutando script Python',
            'return_code' => $returnCode,
            'output' => implode("\n", $output)
        ]);
        exit;
    }

    // Esperar un momento a que se escriba el JSON
    sleep(1);

    // Verificar que el JSON fue creado/actualizado
    if (!file_exists($jsonPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'El JSON no fue generado',
            'expected_path' => $jsonPath
        ]);
        exit;
    }

    // Leer la información del JSON
    $jsonContent = json_decode(file_get_contents($jsonPath), true);
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
        'message' => "Datos actualizados correctamente ({$totalMovies} películas)"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
