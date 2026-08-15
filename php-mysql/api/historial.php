<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$ALLOWED_COLS = ['id','cartera_id','cliente_nombre','tipo','monto','nota','fecha_accion','fecha_visita','gestor','created_at'];

if ($method === 'GET') {
    $where = [];
    $params = [];

    if (isset($_GET['cartera_id'])) { $where[] = 'cartera_id = ?'; $params[] = $_GET['cartera_id']; }
    if (isset($_GET['cartera_id_in'])) {
        $ids = array_filter(explode(',', $_GET['cartera_id_in']));
        if ($ids) { $where[] = 'cartera_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; array_push($params, ...$ids); }
    }
    if (isset($_GET['cliente_nombre'])) { $where[] = 'cliente_nombre = ?'; $params[] = $_GET['cliente_nombre']; }
    if (isset($_GET['tipo'])) {
        $tipos = array_filter(explode(',', $_GET['tipo']));
        if ($tipos) { $where[] = 'tipo IN (' . implode(',', array_fill(0, count($tipos), '?')) . ')'; array_push($params, ...$tipos); }
    }
    if (isset($_GET['tipo_not_in'])) {
        $tipos = array_filter(explode(',', $_GET['tipo_not_in']));
        if ($tipos) { $where[] = 'tipo NOT IN (' . implode(',', array_fill(0, count($tipos), '?')) . ')'; array_push($params, ...$tipos); }
    }
    if (isset($_GET['fecha_accion_gte'])) { $where[] = 'fecha_accion >= ?'; $params[] = $_GET['fecha_accion_gte']; }
    if (isset($_GET['fecha_accion_lt'])) { $where[] = 'fecha_accion < ?'; $params[] = $_GET['fecha_accion_lt']; }
    if (isset($_GET['fecha_accion_lte'])) { $where[] = 'fecha_accion <= ?'; $params[] = $_GET['fecha_accion_lte']; }
    if (isset($_GET['fecha_visita_gte'])) { $where[] = 'fecha_visita >= ?'; $params[] = $_GET['fecha_visita_gte']; }
    if (isset($_GET['fecha_visita_lte'])) { $where[] = 'fecha_visita <= ?'; $params[] = $_GET['fecha_visita_lte']; }

    $select = '*';
    if (isset($_GET['select'])) {
        $cols = array_filter(array_map('trim', explode(',', $_GET['select'])));
        $cols = array_values(array_intersect($cols, $ALLOWED_COLS));
        if ($cols) $select = implode(',', $cols);
    }

    $orderCol = 'created_at';
    $orderDir = 'ASC';
    if (isset($_GET['order'])) {
        [$c, $d] = array_pad(explode(':', $_GET['order']), 2, 'asc');
        if (in_array($c, $ALLOWED_COLS, true)) $orderCol = $c;
        $orderDir = strtolower($d) === 'desc' ? 'DESC' : 'ASC';
    }

    $limit = isset($_GET['limit']) ? max(1, min(5000, (int)$_GET['limit'])) : 5000;

    $sql = "SELECT $select FROM historial_clientes";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= " ORDER BY $orderCol $orderDir LIMIT $limit";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? 'insert';
    $d = json_decode(file_get_contents('php://input'), true) ?: [];

    if ($action === 'delete') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'id es obligatorio.']); exit; }
        $stmt = db()->prepare('DELETE FROM historial_clientes WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (empty($d['cartera_id']) || empty($d['cliente_nombre']) || empty($d['tipo'])) {
        http_response_code(400);
        echo json_encode(['error' => 'cartera_id, cliente_nombre y tipo son obligatorios.']);
        exit;
    }
    $stmt = db()->prepare(
        'INSERT INTO historial_clientes (cartera_id, cliente_nombre, tipo, monto, nota, fecha_accion, fecha_visita, gestor)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $d['cartera_id'],
        $d['cliente_nombre'],
        $d['tipo'],
        $d['monto'] ?? null,
        $d['nota'] ?? null,
        $d['fecha_accion'] ?: null,
        $d['fecha_visita'] ?: null,
        $d['gestor'] ?? null,
    ]);
    echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
