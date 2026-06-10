<?php
// ============================================================
// Lock API  — heartbeat & release
// ============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessionStart();

header('Content-Type: application/json');

if (!isAdminLoggedIn($pdo)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit;
}

$admin_id = getAdminId();

// Support both JSON body (sendBeacon) and form POST
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);
$action = $data['action'] ?? ($_POST['action'] ?? '');
$emp_id = (int)($data['id']   ?? ($_POST['id']   ?? 0));

if (!$emp_id) {
    echo json_encode(['success' => false, 'error' => 'Missing employee id']);
    exit;
}

switch ($action) {
    case 'heartbeat':
        $ok = refreshLock($pdo, $emp_id, $admin_id);
        echo json_encode(['success' => $ok]);
        break;

    case 'release':
        releaseLock($pdo, $emp_id, $admin_id);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
