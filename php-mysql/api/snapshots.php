<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $cartId = $_GET['cartera_id'] ?? '';
    $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 60;
    if ($cartId === '') { http_response_code(400); echo json_encode(['error' => 'cartera_id es obligatorio.']); exit; }
    $stmt = db()->prepare('SELECT id, cartera_id, fecha, tramos, clientes_por_tramo, gestion, created_at FROM snapshots WHERE cartera_id = ? ORDER BY created_at DESC LIMIT ' . $limit);
    $stmt->execute([$cartId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['tramos'] = json_decode($r['tramos'], true) ?: [];
        $r['clientes_por_tramo'] = json_decode($r['clientes_por_tramo'], true) ?: [];
        $r['gestion'] = json_decode($r['gestion'], true) ?: [];
    }
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($d['cartera_id'])) { http_response_code(400); echo json_encode(['error' => 'cartera_id es obligatorio.']); exit; }
    $stmt = db()->prepare(
        'INSERT INTO snapshots (cartera_id, fecha, tramos, clientes_por_tramo, gestion) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        $d['cartera_id'],
        $d['fecha'] ?? null,
        json_encode($d['tramos'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($d['clientes_por_tramo'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($d['gestion'] ?? [], JSON_UNESCAPED_UNICODE),
    ]);
    echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
