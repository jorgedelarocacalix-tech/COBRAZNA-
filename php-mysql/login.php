<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$pin = (string)($data['pin'] ?? '');

if ($pin === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ingresa un PIN']);
    exit;
}

$stmt = db()->prepare('SELECT pin, nombre, rol, carteras FROM usuarios WHERE pin = ?');
$stmt->execute([$pin]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Código incorrecto']);
    exit;
}

$user = [
    'pin' => $row['pin'],
    'nombre' => $row['nombre'],
    'rol' => $row['rol'],
    'carteras' => json_decode($row['carteras'], true) ?: [],
];
$_SESSION['cob_user'] = $user;
echo json_encode(['ok' => true, 'user' => $user]);
