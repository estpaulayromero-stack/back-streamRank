<?php
// ============================================================
//  StreamRank — Registro de usuario
//  Ruta:    htdocs/streamrank/api/registro.php
//  Método:  POST
//  Body:    { "email": "...", "password": "..." }
// ============================================================

require_once __DIR__ . '/config.php';
set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}

$data     = get_body();
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

// ── Validación básica ──────────────────────────────────────
if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Correo inválido"]);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "La contraseña debe tener mínimo 8 caracteres"]);
    exit;
}

// ── Verificar si ya existe ─────────────────────────────────
$conn = get_db();
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $conn->close();
    http_response_code(400);
    echo json_encode(["error" => "El correo ya está registrado"]);
    exit;
}
$stmt->close();

// ── Insertar usuario ───────────────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $hash);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    echo json_encode(["error" => "Error al registrar usuario"]);
    exit;
}

$stmt->close();
$conn->close();

http_response_code(201);
echo json_encode(["message" => "Usuario registrado correctamente"]);