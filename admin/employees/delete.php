<?php
// ============================================================
// Delete Employee  (POST handler)
// ============================================================

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
sessionStart();
requireAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/admin/dashboard.php');
    exit;
}

requireCsrf();

$id = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: ' . APP_BASE . '/admin/dashboard.php'); exit; }

$admin_id = getAdminId();

// Check lock — cannot delete while someone else is editing
if (isLockedByOther($pdo, $id, $admin_id)) {
    setFlash('danger', 'Cannot delete: this record is currently being edited by another admin.');
    header('Location: ' . APP_BASE . '/admin/dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT full_name FROM employees WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    setFlash('danger', 'Employee not found.');
    header('Location: ' . APP_BASE . '/admin/dashboard.php');
    exit;
}

// Delete image files from disk
$imgStmt = $pdo->prepare("SELECT image_path FROM employee_images WHERE employee_id = ?");
$imgStmt->execute([$id]);
$imgFiles = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($imgFiles as $file) {
    $path = UPLOAD_PATH . '/employees/' . $id . '/' . $file;
    if (file_exists($path)) unlink($path);
}
// Remove the employee image directory if empty
$dir = UPLOAD_PATH . '/employees/' . $id;
if (is_dir($dir)) @rmdir($dir);

// Delete employee (CASCADE deletes images and wishes)
$stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
$stmt->execute([$id]);

setFlash('success', htmlspecialchars($emp['full_name']) . ' has been removed from the portal.');
header('Location: ' . APP_BASE . '/admin/dashboard.php');
exit;
