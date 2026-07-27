<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = db()->query('SELECT id, empresa, fecha_emision, clientes, load_history FROM carteras');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['clientes'] = json_decode($r['clientes'], true) ?: [];
        $r['load_history'] = json_decode($r['load_history'], true) ?: [];
    }
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? 'upsert';
    $d = json_decode(file_get_contents('php://input'), true) ?: [];

    if ($action === 'delete') {
        if (empty($d['id'])) { http_response_code(400); echo json_encode(['error' => 'id es obligatorio.']); exit; }
        $stmt = db()->prepare('DELETE FROM carteras WHERE id = ?');
        $stmt->execute([$d['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (empty($d['id']) || empty($d['empresa'])) {
        http_response_code(400);
        echo json_encode(['error' => 'id y empresa son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        'INSERT INTO carteras (id, empresa, fecha_emision, clientes, load_history)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE empresa = VALUES(empresa), fecha_emision = VALUES(fecha_emision),
           clientes = VALUES(clientes), load_history = VALUES(load_history)'
    );
    $stmt->execute([
        $d['id'],
        $d['empresa'],
        $d['fecha_emision'] ?? null,
        json_encode($d['clientes'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($d['load_history'] ?? [], JSON_UNESCAPED_UNICODE),
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
