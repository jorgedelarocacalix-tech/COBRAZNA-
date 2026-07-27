<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'alertas';

if ($method === 'GET') {
    if ($resource === 'lecturas') {
        $usuario = $_GET['usuario'] ?? '';
        $stmt = db()->prepare('SELECT alerta_id FROM alertas_lecturas WHERE usuario = ?');
        $stmt->execute([$usuario]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
    $sql = 'SELECT id, tipo, titulo, mensaje, cliente, autor, created_at FROM alertas';
    $params = [];
    if (isset($_GET['created_at_gt'])) { $sql .= ' WHERE created_at > ?'; $params[] = toMysqlDatetime($_GET['created_at_gt']) ?? '1970-01-01 00:00:00'; }
    $sql .= ' ORDER BY created_at ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];

    if ($resource === 'lecturas') {
        if (empty($d['alerta_id']) || empty($d['usuario'])) {
            http_response_code(400);
            echo json_encode(['error' => 'alerta_id y usuario son obligatorios.']);
            exit;
        }
        $stmt = db()->prepare('INSERT IGNORE INTO alertas_lecturas (alerta_id, usuario) VALUES (?,?)');
        $stmt->execute([$d['alerta_id'], $d['usuario']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (empty($d['titulo']) || empty($d['mensaje'])) {
        http_response_code(400);
        echo json_encode(['error' => 'titulo y mensaje son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        'INSERT INTO alertas (tipo, titulo, mensaje, cliente, autor) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        $d['tipo'] ?? 'info',
        $d['titulo'],
        $d['mensaje'],
        $d['cliente'] ?? null,
        $d['autor'] ?? null,
    ]);
    echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
