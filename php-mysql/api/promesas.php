<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = db()->query('SELECT cartera_id, cliente_nombre, estado, fecha, monto, nota, mora_pendiente, adelantado, cero_prima, updated_at FROM promesas');
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? 'upsert';
    $d = json_decode(file_get_contents('php://input'), true) ?: [];

    if ($action === 'delete') {
        if (empty($d['cartera_id']) || !isset($d['cliente_nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'cartera_id y cliente_nombre son obligatorios.']);
            exit;
        }
        $stmt = db()->prepare('DELETE FROM promesas WHERE cartera_id = ? AND cliente_nombre = ?');
        $stmt->execute([$d['cartera_id'], $d['cliente_nombre']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete_all') {
        if (empty($d['cartera_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'cartera_id es obligatorio.']);
            exit;
        }
        $stmt = db()->prepare('DELETE FROM promesas WHERE cartera_id = ?');
        $stmt->execute([$d['cartera_id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // upsert
    if (empty($d['cartera_id']) || empty($d['cliente_nombre'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cartera_id y cliente_nombre son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        'INSERT INTO promesas (cartera_id, cliente_nombre, estado, fecha, monto, nota, mora_pendiente, adelantado, cero_prima)
         VALUES (?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE estado = VALUES(estado), fecha = VALUES(fecha), monto = VALUES(monto),
           nota = VALUES(nota), mora_pendiente = VALUES(mora_pendiente), adelantado = VALUES(adelantado),
           cero_prima = VALUES(cero_prima)'
    );
    $stmt->execute([
        $d['cartera_id'],
        $d['cliente_nombre'],
        $d['estado'] ?? null,
        $d['fecha'] ?: null,
        $d['monto'] ?? null,
        $d['nota'] ?? null,
        !empty($d['mora_pendiente']) ? 1 : 0,
        !empty($d['adelantado']) ? 1 : 0,
        !empty($d['cero_prima']) ? 1 : 0,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
