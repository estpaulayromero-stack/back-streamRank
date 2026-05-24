<?php
// ============================================================
//  StreamRank — Gestión de tops personalizados
//  Ruta: htdocs/streamrank/api/tops.php
//
//  GET    /api/tops.php?email=...               → obtener todos los tops del usuario
//  GET    /api/tops.php?id=...                  → obtener un top específico con sus items
//  POST   /api/tops.php                         → crear nuevo top
//  POST   /api/tops.php?id=...&action=add_item  → agregar ítem a un top
//  PUT    /api/tops.php?id=...                  → actualizar nombre/descripción
//  DELETE /api/tops.php?id=...                  → eliminar top completo
//  DELETE /api/tops.php?item_id=...             → eliminar ítem específico
// ============================================================

require_once __DIR__ . '/config.php';
set_headers();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match (true) {
    $method === 'GET'  && isset($_GET['id'])                     => obtener_top_detalle(),
    $method === 'GET'  && isset($_GET['email'])                  => obtener_tops_usuario(),
    $method === 'POST' && $action === 'add_item'                 => agregar_item_a_top(),
    $method === 'POST'                                           => crear_top(),
    $method === 'PUT'  && isset($_GET['id'])                     => actualizar_top(),
    $method === 'DELETE' && isset($_GET['item_id'])              => eliminar_item(),
    $method === 'DELETE' && isset($_GET['id'])                   => eliminar_top(),
    default                                                      => responder(405, ["error" => "Método no permitido"]),
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


// ── GET: obtener todos los tops del usuario ────────────────

function obtener_tops_usuario(): void {
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
        "SELECT id, nombre, descripcion, fecha_creado, fecha_modificado
         FROM tops_personales
         WHERE user_id = ?
         ORDER BY fecha_modificado DESC"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tops = [];
    while ($row = $result->fetch_assoc()) {
        // Contar items
        $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM tops_items WHERE top_id = ?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $count_result = $stmt2->get_result();
        $count_row    = $count_result->fetch_assoc();
        $stmt2->close();

        $row['items_count'] = (int)$count_row['count'];
        $tops[] = $row;
    }

    $stmt->close();
    $conn->close();

    responder(200, $tops);
}


// ── GET: obtener un top específico con items ───────────────

function obtener_top_detalle(): void {
    $top_id = (int)($_GET['id'] ?? 0);

    if (!$top_id) {
        responder(400, ["error" => "Falta el id del top"]);
    }

    $conn = get_db();

    // Obtener info del top
    $stmt = $conn->prepare(
        "SELECT id, nombre, descripcion, fecha_creado, fecha_modificado
         FROM tops_personales
         WHERE id = ?"
    );
    $stmt->bind_param("i", $top_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $top    = $result->fetch_assoc();
    $stmt->close();

    if (!$top) {
        $conn->close();
        responder(404, ["error" => "Top no encontrado"]);
    }

    // Obtener items
    $stmt = $conn->prepare(
        "SELECT id, titulo, imagen_url, posicion
         FROM tops_items
         WHERE top_id = ?
         ORDER BY posicion ASC"
    );
    $stmt->bind_param("i", $top_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();
    $conn->close();

    $top['items'] = $items;
    responder(200, $top);
}


// ── POST: crear nuevo top ──────────────────────────────────

function crear_top(): void {
    $data        = get_body();
    $email       = trim($data['email']       ?? '');
    $nombre      = trim($data['nombre']      ?? '');
    $descripcion = trim($data['descripcion'] ?? '');

    if (!$email || !$nombre) {
        responder(400, ["error" => "Faltan datos obligatorios (email, nombre)"]);
    }

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    // Insertar
    $stmt = $conn->prepare(
        "INSERT INTO tops_personales (user_id, nombre, descripcion)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $user_id, $nombre, $descripcion);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        responder(500, ["error" => "Error al crear top"]);
    }

    $top_id = $conn->insert_id;
    $stmt->close();
    $conn->close();

    responder(201, ["message" => "Top creado correctamente", "top_id" => $top_id]);
}


// ── POST: agregar ítem a un top ────────────────────────────

function agregar_item_a_top(): void {
    $top_id     = (int)($_GET['id'] ?? 0);
    $data       = get_body();
    $titulo     = trim($data['titulo']     ?? '');
    $imagen_url = trim($data['imagen_url'] ?? '');

    if (!$top_id || !$titulo) {
        responder(400, ["error" => "Faltan datos (id del top, titulo)"]);
    }

    $conn = get_db();

    // Verificar que el top existe
    $stmt = $conn->prepare("SELECT id FROM tops_personales WHERE id = ?");
    $stmt->bind_param("i", $top_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        responder(404, ["error" => "Top no encontrado"]);
    }
    $stmt->close();

    // Calcular próxima posición
    $stmt = $conn->prepare("SELECT MAX(posicion) as max_pos FROM tops_items WHERE top_id = ?");
    $stmt->bind_param("i", $top_id);
    $stmt->execute();
    $result   = $stmt->get_result();
    $row      = $result->fetch_assoc();
    $posicion = ((int)$row['max_pos']) + 1;
    $stmt->close();

    // Insertar ítem
    $stmt = $conn->prepare(
        "INSERT INTO tops_items (top_id, titulo, imagen_url, posicion)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("issi", $top_id, $titulo, $imagen_url, $posicion);
    $stmt->execute();
    $item_id = $conn->insert_id;
    $stmt->close();
    $conn->close();

    responder(201, ["message" => "Ítem agregado al top", "posicion" => $posicion, "item_id" => $item_id]);
}


// ── PUT: actualizar nombre/descripción ─────────────────────

function actualizar_top(): void {
    $top_id = (int)($_GET['id'] ?? 0);
    $data   = get_body();
    $email  = trim($data['email']       ?? '');
    $nombre = trim($data['nombre']      ?? '');
    $descripcion = trim($data['descripcion'] ?? '');

    if (!$top_id || !$email) {
        responder(400, ["error" => "Faltan datos (id del top, email)"]);
    }

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    // Verificar pertenencia
    $stmt = $conn->prepare("SELECT id FROM tops_personales WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $top_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        responder(404, ["error" => "Top no encontrado o no te pertenece"]);
    }
    $stmt->close();

    // Actualizar
    if ($nombre) {
        $stmt = $conn->prepare("UPDATE tops_personales SET nombre = ? WHERE id = ?");
        $stmt->bind_param("si", $nombre, $top_id);
        $stmt->execute();
        $stmt->close();
    }

    if ($descripcion !== '') {
        $stmt = $conn->prepare("UPDATE tops_personales SET descripcion = ? WHERE id = ?");
        $stmt->bind_param("si", $descripcion, $top_id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();
    responder(200, ["message" => "Top actualizado"]);
}


// ── DELETE: eliminar top completo ──────────────────────────

function eliminar_top(): void {
    $top_id = (int)($_GET['id'] ?? 0);
    $data   = get_body();
    $email  = trim($data['email'] ?? '');

    if (!$top_id || !$email) {
        responder(400, ["error" => "Faltan datos (id del top, email)"]);
    }

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    // Verificar pertenencia
    $stmt = $conn->prepare("SELECT id FROM tops_personales WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $top_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        responder(404, ["error" => "Top no encontrado o no te pertenece"]);
    }
    $stmt->close();

    // Eliminar (CASCADE borra los items automáticamente)
    $stmt = $conn->prepare("DELETE FROM tops_personales WHERE id = ?");
    $stmt->bind_param("i", $top_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    responder(200, ["message" => "Top eliminado correctamente"]);
}


// ── DELETE: eliminar ítem específico ───────────────────────

function eliminar_item(): void {
    $item_id = (int)($_GET['item_id'] ?? 0);

    if (!$item_id) {
        responder(400, ["error" => "Falta el item_id"]);
    }

    $conn = get_db();

    // Eliminar
    $stmt = $conn->prepare("DELETE FROM tops_items WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    responder(200, ["message" => "Ítem eliminado"]);
}