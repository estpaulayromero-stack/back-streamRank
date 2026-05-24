<?php
// ============================================================
//  StreamRank — Login de usuario
//  Ruta:    htdocs/streamrank/api/login.php
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

// ── Buscar usuario ─────────────────────────────────────────
$conn = get_db();
$stmt = $conn->prepare("SELECT id, email, password, username, foto_url FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();
$conn->close();

// ── Verificar contraseña ───────────────────────────────────
if (!$user) {
    http_response_code(404);
    echo json_encode(["error" => "Cuenta no existe. Regístrate por favor."]);
    exit;
}

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(["error" => "Contraseña incorrecta"]);
    exit;
}

// ── Respuesta exitosa ──────────────────────────────────────
echo json_encode([
    "message"  => "Login exitoso",
    "email"    => $user['email'],
    "username" => $user['username'] ?? '',
    "foto_url" => $user['foto_url'] ?? '',
]);