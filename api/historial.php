<?php
// ============================================================
//  StreamRank — Gestión de historial
//  Ruta: htdocs/streamrank/api/historial.php
//
//  GET    /api/historial.php?email=...          → obtener historial del usuario
//  POST   /api/historial.php                    → agregar ítem al historial
//  PUT    /api/historial.php?id=...             → actualizar calificación/notas
//  DELETE /api/historial.php?id=...             → eliminar del historial
// ============================================================

require_once __DIR__ . '/config.php';
set_headers();

$method = $_SERVER['REQUEST_METHOD'];

match ($method) {
    'GET'    => obtener_historial(),
    'POST'   => agregar_historial(),
    'PUT'    => actualizar_historial(),
    'DELETE' => eliminar_historial(),
    default  => responder(405, ["error" => "Método no permitido"]),
};


// ── HELPERS ────────────────────────────────────────────────

function responder(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_user_id(mysqli $conn, string $email): int|false {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : false;
}


// ── GET: obtener historial ─────────────────────────────────

function obtener_historial(): void {
    $email = trim($_GET['email'] ?? '');

    if (!$email) {
        responder(400, ["error" => "Falta el email"]);
    }

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    $stmt = $conn->prepare(
        "SELECT id, titulo, tipo, genero, plataforma, imagen_url, rating, 
                mi_calificacion, reaccion, notas, fecha_visto
         FROM historial
         WHERE user_id = ?
         ORDER BY fecha_visto DESC"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();
    $conn->close();

    responder(200, $items);
}


// ── POST: agregar al historial ────────────────────────────

function agregar_historial(): void {
    $data      = get_body();
    $email     = trim($data['email']     ?? '');
    $titulo    = trim($data['titulo']    ?? '');
    $tipo      = trim($data['tipo']      ?? '');
    $genero    = trim($data['genero']    ?? '');
    $plataforma= trim($data['plataforma']?? '');
    $imagen_url= trim($data['imagen_url']?? '');
    $rating    = $data['rating'] !== '' ? (float)$data['rating'] : null;

    if (!$email || !$titulo || !$tipo) {
        responder(400, ["error" => "Faltan datos obligatorios (email, titulo, tipo)"]);
    }

    // Normalizar tipo
    $tipo_norm = match (strtolower($tipo)) {
        'película', 'pelicula', 'movie' => 'pelicula',
        'serie', 'series', 'show'       => 'serie',
        default                          => strtolower($tipo),
    };

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    // Verificar duplicado
    $stmt = $conn->prepare("SELECT id FROM historial WHERE user_id = ? AND titulo = ?");
    $stmt->bind_param("is", $user_id, $titulo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        responder(400, ["error" => "Ya está en tu historial"]);
    }
    $stmt->close();

    // Insertar
    $stmt = $conn->prepare(
        "INSERT INTO historial (user_id, titulo, tipo, genero, plataforma, imagen_url, rating)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isssssd", $user_id, $titulo, $tipo_norm, $genero, $plataforma, $imagen_url, $rating);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        responder(500, ["error" => "Error al guardar en historial"]);
    }

    $stmt->close();
    $conn->close();
    responder(201, ["message" => "Agregado al historial correctamente"]);
}


// ── PUT: actualizar calificación/notas ─────────────────────

function actualizar_historial(): void {
    $item_id = (int)($_GET['id'] ?? 0);
    $data    = get_body();
    $email   = trim($data['email'] ?? '');

    $mi_calificacion = isset($data['mi_calificacion']) ? (int)$data['mi_calificacion'] : null;
    $reaccion        = trim($data['reaccion'] ?? 'neutro');
    $notas           = trim($data['notas'] ?? '');

    if (!$item_id || !$email) {
        responder(400, ["error" => "Faltan datos (id en URL, email en body)"]);
    }

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    // Verificar pertenencia
    $stmt = $conn->prepare("SELECT id FROM historial WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        responder(404, ["error" => "Ítem no encontrado o no te pertenece"]);
    }
    $stmt->close();

    // Actualizar
    $stmt = $conn->prepare(
        "UPDATE historial 
         SET mi_calificacion = ?, reaccion = ?, notas = ?
         WHERE id = ?"
    );
    $stmt->bind_param("issi", $mi_calificacion, $reaccion, $notas, $item_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    responder(200, ["message" => "Actualizado correctamente"]);
}


// ── DELETE: eliminar del historial ────────────────────────

function eliminar_historial(): void {
    $item_id = (int)($_GET['id'] ?? 0);
    $data    = get_body();
    $email   = trim($data['email'] ?? '');

    if (!$item_id || !$email) {
        responder(400, ["error" => "Faltan datos (id en URL, email en body)"]);
    }

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    // Verificar pertenencia
    $stmt = $conn->prepare("SELECT id FROM historial WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        responder(404, ["error" => "Ítem no encontrado o no te pertenece"]);
    }
    $stmt->close();

    // Eliminar
    $stmt = $conn->prepare("DELETE FROM historial WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    responder(200, ["message" => "Eliminado del historial"]);
}