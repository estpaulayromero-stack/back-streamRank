<?php
// ============================================================
//  StreamRank — Gestión de listas (agregar / obtener / eliminar)
//  Ruta:    htdocs/streamrank/api/listas.php
//
//  GET    /api/listas.php?email=...        → obtener listas del usuario
//  POST   /api/listas.php                  → agregar ítem
//  DELETE /api/listas.php?id=...           → eliminar ítem
// ============================================================

require_once __DIR__ . '/config.php';
set_headers();

$method = $_SERVER['REQUEST_METHOD'];

match ($method) {
    'GET'    => obtener_listas(),
    'POST'   => agregar_lista(),
    'DELETE' => eliminar_lista(),
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


// ── GET: obtener listas ────────────────────────────────────

function obtener_listas(): void {
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
        "SELECT id, titulo, tipo, genero, plataforma, imagen_url, rating, fecha_agregado
         FROM listas
         WHERE user_id = ?
         ORDER BY fecha_agregado DESC"
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


// ── POST: agregar ítem ─────────────────────────────────────

function agregar_lista(): void {
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

    // Normalizar tipo al ENUM de la BD
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
    $stmt = $conn->prepare("SELECT id FROM listas WHERE user_id = ? AND titulo = ?");
    $stmt->bind_param("is", $user_id, $titulo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        responder(400, ["error" => "Ya está en tu lista"]);
    }
    $stmt->close();

    // Insertar
    $stmt = $conn->prepare(
        "INSERT INTO listas (user_id, titulo, tipo, genero, plataforma, imagen_url, rating)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isssssd", $user_id, $titulo, $tipo_norm, $genero, $plataforma, $imagen_url, $rating);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        responder(500, ["error" => "Error al guardar en la lista"]);
    }

    $stmt->close();
    $conn->close();
    responder(201, ["message" => "Agregado a tu lista correctamente"]);
}


// ── DELETE: eliminar ítem ──────────────────────────────────

function eliminar_lista(): void {
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

    // Verificar que el ítem pertenece al usuario
    $stmt = $conn->prepare("SELECT id FROM listas WHERE id = ? AND user_id = ?");
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
    $stmt = $conn->prepare("DELETE FROM listas WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    responder(200, ["message" => "Eliminado correctamente"]);
}