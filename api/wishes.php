<?php
// ============================================================
// Birthday Wishes API
// ============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessionStart();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// ---- SUBMIT WISH ----------------------------------------
if ($method === 'POST' && !$action) {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $author_name = trim($_POST['author_name'] ?? '');
    $message     = trim($_POST['message']     ?? '');

    // Validate
    if (!$employee_id) { echo json_encode(['success'=>false,'error'=>'Invalid employee.']); exit; }
    if (!$author_name) { echo json_encode(['success'=>false,'error'=>'Your name is required.']); exit; }
    if (!$message)     { echo json_encode(['success'=>false,'error'=>'Message cannot be empty.']); exit; }
    if (mb_strlen($message) > 500) { echo json_encode(['success'=>false,'error'=>'Message exceeds 500 characters.']); exit; }

    // Confirm it's the employee's birthday today
    $stmt = $pdo->prepare("SELECT id, birthdate FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    if (!$emp || $emp['birthdate'] !== appToday()) {
        echo json_encode(['success'=>false,'error'=>'Wishes can only be submitted on this person\'s birthday.']);
        exit;
    }

    // Rate limit: one wish per session per employee
    if (hasSessionWished($employee_id)) {
        echo json_encode(['success'=>false,'error'=>'You\'ve already sent a birthday wish to this person today.']);
        exit;
    }

    $session_id = session_id();
    $stmt = $pdo->prepare("
        INSERT INTO birthday_wishes (employee_id, author_name, message, session_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$employee_id, $author_name, $message, $session_id]);
    $wish_id    = (int)$pdo->lastInsertId();
    $created_at = date('Y-m-d H:i:s');

    markSessionWished($employee_id);

    echo json_encode([
        'success'     => true,
        'wish_id'     => $wish_id,
        'created_at'  => $created_at,
        'author_name' => sanitize($author_name),
        'message'     => nl2br(sanitize($message)),
        'edit_window' => WISH_EDIT_WINDOW,
    ]);
    exit;
}

// ---- EDIT WISH ------------------------------------------
if ($method === 'POST' && $action === 'edit' && $id) {
    $message = trim($_POST['message'] ?? '');
    if (!$message) { echo json_encode(['success'=>false,'error'=>'Message cannot be empty.']); exit; }
    if (mb_strlen($message) > 500) { echo json_encode(['success'=>false,'error'=>'Message exceeds 500 characters.']); exit; }

    // Fetch wish
    $stmt = $pdo->prepare("SELECT * FROM birthday_wishes WHERE id = ?");
    $stmt->execute([$id]);
    $wish = $stmt->fetch();
    if (!$wish) { echo json_encode(['success'=>false,'error'=>'Wish not found.']); exit; }

    // Check session ownership
    $isAdmin = isAdminLoggedIn($pdo);
    if (!$isAdmin && $wish['session_id'] !== session_id()) {
        echo json_encode(['success'=>false,'error'=>'You cannot edit this wish.']);
        exit;
    }

    // Enforce 30s window for non-admins
    if (!$isAdmin) {
        $elapsed = time() - strtotime($wish['created_at']);
        if ($elapsed > WISH_EDIT_WINDOW) {
            echo json_encode(['success'=>false,'error'=>'Edit window has expired.']);
            exit;
        }
    }

    $pdo->prepare("UPDATE birthday_wishes SET message = ? WHERE id = ?")->execute([$message, $id]);
    echo json_encode(['success'=>true, 'message'=>nl2br(sanitize($message))]);
    exit;
}

// ---- DELETE WISH ----------------------------------------
if ($method === 'POST' && $action === 'delete' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM birthday_wishes WHERE id = ?");
    $stmt->execute([$id]);
    $wish = $stmt->fetch();
    if (!$wish) { echo json_encode(['success'=>false,'error'=>'Wish not found.']); exit; }

    $isAdmin = isAdminLoggedIn($pdo);
    if (!$isAdmin && $wish['session_id'] !== session_id()) {
        echo json_encode(['success'=>false,'error'=>'You cannot delete this wish.']);
        exit;
    }
    if (!$isAdmin) {
        $elapsed = time() - strtotime($wish['created_at']);
        if ($elapsed > WISH_EDIT_WINDOW) {
            echo json_encode(['success'=>false,'error'=>'Delete window has expired.']);
            exit;
        }
    }

    $pdo->prepare("DELETE FROM birthday_wishes WHERE id = ?")->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

http_response_code(400);
echo json_encode(['success'=>false,'error'=>'Invalid request.']);
