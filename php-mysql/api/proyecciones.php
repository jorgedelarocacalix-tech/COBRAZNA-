<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $cartId = $_GET['cartera_id'] ?? '';
    if ($cartId !== '') {
        $stmt = db()->prepare('SELECT * FROM proyecciones WHERE cartera_id = ?');
        $stmt->execute([$cartId]);
    } else {
        $stmt = db()->query('SELECT * FROM proyecciones');
    }
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($d['cartera_id']) || empty($d['cliente_nombre']) || empty($d['semana_inicio'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cartera_id, cliente_nombre y semana_inicio son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        'INSERT INTO proyecciones (cartera_id, cliente_nombre, semana_inicio, monto_proyectado, fecha_proyectada, comentario_cierre, gestor)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           monto_proyectado = VALUES(monto_proyectado),
           fecha_proyectada = VALUES(fecha_proyectada),
           comentario_cierre = VALUES(comentario_cierre),
           gestor = VALUES(gestor)'
    );
    $stmt->execute([
        $d['cartera_id'],
        $d['cliente_nombre'],
        $d['semana_inicio'],
        $d['monto_proyectado'] ?? null,
        $d['fecha_proyectada'] ?: null,
        $d['comentario_cierre'] ?? null,
        $d['gestor'] ?? null,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
