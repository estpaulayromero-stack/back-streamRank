<?php
// ============================================================
//  StreamRank — Actualizar perfil de usuario
//  Ruta:    htdocs/streamrank/api/perfil.php
//  Método:  POST
//  Body:    { "email": "...", "username": "...", "foto_url": "...", "password": "..." }
//  (password es opcional)
// ============================================================

require_once __DIR__ . '/config.php';
set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit;
}

$data       = get_body();
$email      = trim($data['email']    ?? '');
$username   = trim($data['username'] ?? '');
$foto_url   = trim($data['foto_url'] ?? '');
$password   = trim($data['password'] ?? '');

if (!$email) {
    http_response_code(400);
    echo json_encode(["error" => "Falta el email"]);
    exit;
}

$conn = get_db();

// Verificar que el usuario existe
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    http_response_code(404);
    echo json_encode(["error" => "Usuario no encontrado"]);
    exit;
}

// Actualizar campos (con o sin nueva contraseña)
if ($password !== '') {
    if (strlen($password) < 8) {
        $conn->close();
        http_response_code(400);
        echo json_encode(["error" => "La contraseña debe tener mínimo 8 caracteres"]);
        exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare(
        "UPDATE users SET username = ?, foto_url = ?, password = ? WHERE email = ?"
    );
    $stmt->bind_param("ssss", $username, $foto_url, $hash, $email);
} else {
    $stmt = $conn->prepare(
        "UPDATE users SET username = ?, foto_url = ? WHERE email = ?"
    );
    $stmt->bind_param("sss", $username, $foto_url, $email);
}

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    echo json_encode(["error" => "Error al actualizar el perfil"]);
    exit;
}

$stmt->close();
$conn->close();

echo json_encode(["message" => "Perfil actualizado correctamente"]);