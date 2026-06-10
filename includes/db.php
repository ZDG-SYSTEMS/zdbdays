<?php
// ============================================================
// ZD Birthdays — Database Connection
// ============================================================

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Return JSON for API callers, HTML for page loads
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Database unavailable.']);
    } else {
        http_response_code(503);
        echo '<h1>Service Unavailable</h1><p>Could not connect to the database. Please try again later.</p>';
    }
    exit;
}
