<?php
// Serves deposit proof files to authorized admins only (works regardless of static-file config).
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
$admin = current_admin();
if (!$admin || !in_array($admin['role'] ?? '', ['super_admin', 'admin', 'finance', 'support'], true)) {
    http_response_code(403);
    exit('Forbidden.');
}
$id = (int)($_GET['id'] ?? 0);
try {
    $st = db()->prepare('SELECT proof_path FROM deposits WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    $base = realpath(__DIR__ . '/../uploads/proofs');
    $file = $row && !empty($row['proof_path']) ? realpath(__DIR__ . '/../' . ltrim($row['proof_path'], '/')) : false;
    if (!$file || !$base || strpos($file, $base) !== 0 || !is_file($file)) {
        http_response_code(404);
        exit('Proof file not found. It may have been removed.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    header('Cache-Control: private, max-age=60');
    readfile($file);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to load proof.');
}
