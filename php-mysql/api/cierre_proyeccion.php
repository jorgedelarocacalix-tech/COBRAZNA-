<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$ALLOWED_COLS = ['id','cartera_id','mes_key','cerrado_por','cerrado_at','datos','pin_editor'];

if ($method === 'GET') {
    $where = [];
    $params = [];
    if (isset($_GET['cartera_id'])) { $where[] = 'cartera_id = ?'; $params[] = $_GET['cartera_id']; }
    if (isset($_GET['mes_key'])) { $where[] = 'mes_key = ?'; $params[] = $_GET['mes_key']; }

    $select = '*';
    if (isset($_GET['select'])) {
        $cols = array_filter(array_map('trim', explode(',', $_GET['select'])));
        $cols = array_values(array_intersect($cols, $ALLOWED_COLS));
        if ($cols) $select = implode(',', $cols);
    }

    $sql = "SELECT $select FROM cierre_proyeccion";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY mes_key DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if ($select === '*' || strpos($select, 'datos') !== false) {
        foreach ($rows as &$r) {
            if (isset($r['datos'])) $r['datos'] = json_decode($r['datos'], true) ?: [];
        }
    }
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? 'upsert';
    $d = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($d['cartera_id']) || empty($d['mes_key'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cartera_id y mes_key son obligatorios.']);
        exit;
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM cierre_proyeccion WHERE cartera_id = ? AND mes_key = ?');
        $stmt->execute([$d['cartera_id'], $d['mes_key']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $stmt = db()->prepare(
        'INSERT INTO cierre_proyeccion (cartera_id, mes_key, cerrado_por, cerrado_at, datos, pin_editor)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE cerrado_por = VALUES(cerrado_por), cerrado_at = VALUES(cerrado_at),
           datos = VALUES(datos), pin_editor = VALUES(pin_editor)'
    );
    $stmt->execute([
        $d['cartera_id'],
        $d['mes_key'],
        $d['cerrado_por'] ?? null,
        toMysqlDatetime($d['cerrado_at'] ?? null) ?? gmdate('Y-m-d H:i:s'),
        json_encode($d['datos'] ?? [], JSON_UNESCAPED_UNICODE),
        $d['pin_editor'] ?? null,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
