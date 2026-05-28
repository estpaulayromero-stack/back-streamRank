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

require_once __DIR__ . '/config.php'; // importa get_db(), set_headers(), get_body()
set_headers();                         // aplica CORS y Content-Type JSON

$method = $_SERVER['REQUEST_METHOD']; // lee el método HTTP de la petición (GET, POST, etc.)

// según el método, ejecuta la función correspondiente
match ($method) {
    'GET'    => obtener_historial(),
    'POST'   => agregar_historial(),
    'PUT'    => actualizar_historial(),
    'DELETE' => eliminar_historial(),
    default  => responder(405, ["error" => "Método no permitido"]), // método no soportado
};


// ── HELPERS ────────────────────────────────────────────────

// envía el código HTTP, imprime el JSON y termina el script
function responder(int $code, array $data): void {
    http_response_code($code);                          // ej: 200, 400, 404, 500
    echo json_encode($data, JSON_UNESCAPED_UNICODE);    // convierte array PHP a JSON (sin escapar tildes)
    exit;                                               // detiene la ejecución
}

// recibe email, devuelve el id del usuario o false si no existe
function get_user_id(mysqli $conn, string $email): int|false {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?"); // prepared statement, evita SQL injection
    $stmt->bind_param("s", $email);  // "s" = string, sustituye el ?
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc(); // obtiene la fila como array asociativo
    $stmt->close();
    return $row ? (int)$row['id'] : false; // retorna el id si existe, false si no
}


// ── GET: obtener historial ─────────────────────────────────

function obtener_historial(): void {
    $email = trim($_GET['email'] ?? ''); // lee ?email= de la URL, elimina espacios

    if (!$email) {
        responder(400, ["error" => "Falta el email"]); // 400 = bad request
    }

    $conn    = get_db();
    $user_id = get_user_id($conn, $email);

    if (!$user_id) {
        $conn->close();
        responder(404, ["error" => "Usuario no encontrado"]);
    }

    // trae todos los campos del historial del usuario, del más reciente al más antiguo
    $stmt = $conn->prepare(
        "SELECT id, titulo, tipo, genero, plataforma, imagen_url, rating, 
                mi_calificacion, reaccion, notas, fecha_visto
         FROM historial
         WHERE user_id = ?
         ORDER BY fecha_visto DESC"
    );
    $stmt->bind_param("i", $user_id); // "i" = integer
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) { // recorre cada fila del resultado
        $items[] = $row;                     // la agrega al array
    }

    $stmt->close();
    $conn->close();

    responder(200, $items); // devuelve el array completo como JSON
}


// ── POST: agregar al historial ────────────────────────────

function agregar_historial(): void {
    $data       = get_body();                           // lee el JSON del body
    $email      = trim($data['email']      ?? '');
    $titulo     = trim($data['titulo']     ?? '');
    $tipo       = trim($data['tipo']       ?? '');
    $genero     = trim($data['genero']     ?? '');
    $plataforma = trim($data['plataforma'] ?? '');
    $imagen_url = trim($data['imagen_url'] ?? '');
    $rating     = $data['rating'] !== '' ? (float)$data['rating'] : null; // convierte a decimal o null

    if (!$email || !$titulo || !$tipo) {
        responder(400, ["error" => "Faltan datos obligatorios (email, titulo, tipo)"]);
    }

    // estandariza el tipo al valor exacto que acepta el ENUM de la BD
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

    // verifica que el título no esté ya en el historial del usuario
    $stmt = $conn->prepare("SELECT id FROM historial WHERE user_id = ? AND titulo = ?");
    $stmt->bind_param("is", $user_id, $titulo);
    $stmt->execute();
    $stmt->store_result(); // necesario para poder leer num_rows

    if ($stmt->num_rows > 0) {  // si ya existe, rechaza
        $stmt->close();
        $conn->close();
        responder(400, ["error" => "Ya está en tu historial"]);
    }
    $stmt->close();

    // inserta el nuevo registro en la BD
    $stmt = $conn->prepare(
        "INSERT INTO historial (user_id, titulo, tipo, genero, plataforma, imagen_url, rating)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    // "isssssd" = int, string, string, string, string, string, double
    $stmt->bind_param("isssssd", $user_id, $titulo, $tipo_norm, $genero, $plataforma, $imagen_url, $rating);

    if (!$stmt->execute()) { // si falla la inserción
        $stmt->close();
        $conn->close();
        responder(500, ["error" => "Error al guardar en historial"]);
    }

    $stmt->close();
    $conn->close();
    responder(201, ["message" => "Agregado al historial correctamente"]); // 201 = created
}


// ── PUT: actualizar calificación/notas ─────────────────────

function actualizar_historial(): void {
    $item_id = (int)($_GET['id'] ?? 0); // id del ítem viene en la URL: ?id=5
    $data    = get_body();
    $email   = trim($data['email'] ?? '');

    $mi_calificacion = isset($data['mi_calificacion']) ? (int)$data['mi_calificacion'] : null;
    $reaccion        = trim($data['reaccion'] ?? 'neutro'); // valor por defecto: neutro
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

    // verifica que el ítem existe Y pertenece al usuario (seguridad)
    $stmt = $conn->prepare("SELECT id FROM historial WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) { // no encontrado o no es suyo
        $stmt->close();
        $conn->close();
        responder(404, ["error" => "Ítem no encontrado o no te pertenece"]);
    }
    $stmt->close();

    // actualiza solo los campos editables por el usuario
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
    $item_id = (int)($_GET['id'] ?? 0); // id del ítem a eliminar viene en la URL
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

    // verifica que el ítem existe Y pertenece al usuario antes de borrar
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

    // elimina el registro de la BD
    $stmt = $conn->prepare("DELETE FROM historial WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    responder(200, ["message" => "Eliminado del historial"]);
}