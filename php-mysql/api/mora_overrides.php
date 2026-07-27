<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $cartId = $_GET['cartera_id'] ?? '';
    if ($cartId === '') { http_response_code(400); echo json_encode(['error' => 'cartera_id es obligatorio.']); exit; }
    $stmt = db()->prepare('SELECT cartera_id, cliente_nombre, num_cuotas_real, total_credito_real, nota FROM mora_overrides WHERE cartera_id = ?');
    $stmt->execute([$cartId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? 'upsert';
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($d['cartera_id']) || empty($d['cliente_nombre'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cartera_id y cliente_nombre son obligatorios.']);
        exit;
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM mora_overrides WHERE cartera_id = ? AND cliente_nombre = ?');
        $stmt->execute([$d['cartera_id'], $d['cliente_nombre']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $stmt = db()->prepare(
        'INSERT INTO mora_overrides (cartera_id, cliente_nombre, num_cuotas_real, total_credito_real, nota)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE num_cuotas_real = VALUES(num_cuotas_real),
           total_credito_real = VALUES(total_credito_real), nota = VALUES(nota)'
    );
    $stmt->execute([
        $d['cartera_id'],
        $d['cliente_nombre'],
        $d['num_cuotas_real'] ?? null,
        $d['total_credito_real'] ?? null,
        $d['nota'] ?? null,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
