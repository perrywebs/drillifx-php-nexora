<?php
// Fast user lookup for admin searchable dropdowns. Never dumps the whole table.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
header('Content-Type: application/json');
$a = current_admin();
if (!$a) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Please sign in.']); exit; }
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode(['success' => true, 'data' => []]); exit; }
try {
    // Escape LIKE wildcards so user input cannot broaden the search.
    $like = '%' . addcslashes(mb_substr($q, 0, 60), '%_\\') . '%';
    $st = db()->prepare('SELECT username, email FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY username LIMIT 8');
    $st->execute([$like, $like]);
    echo json_encode(['success' => true, 'data' => $st->fetchAll()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Search failed.']);
}
