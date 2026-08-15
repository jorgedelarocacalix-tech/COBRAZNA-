<?php
session_start();
require_once __DIR__ . '/config.php';

function requireAuth(): array {
    if (empty($_SESSION['cob_user'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    return $_SESSION['cob_user'];
}

function currentUser(): ?array {
    return $_SESSION['cob_user'] ?? null;
}

// El frontend manda timestamps con Date.toISOString() (formato ISO-8601 con
// milisegundos y sufijo "Z"), pero las columnas DATETIME de MySQL no aceptan
// ese formato en modo estricto. Esta funcion normaliza a 'Y-m-d H:i:s' (UTC).
function toMysqlDatetime(?string $iso): ?string {
    if ($iso === null || $iso === '') return null;
    $ts = strtotime($iso);
    if ($ts === false) return null;
    return gmdate('Y-m-d H:i:s', $ts);
}

function requireAdmin(): array {
    $u = requireAuth();
    if ($u['rol'] !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Solo el administrador puede hacer esto']);
        exit;
    }
    return $u;
}
