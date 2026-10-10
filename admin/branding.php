<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin(['super_admin', 'admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    else {
        $which = $_POST['which'] ?? '';
        if (!in_array($which, ['site_logo', 'site_favicon'], true)) admin_flash('error', 'Invalid upload target.');
        elseif (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) admin_flash('error', 'Choose a file to upload.');
        else {
            $f = $_FILES['file'];
            $max = $which === 'site_favicon' ? 512 * 1024 : 2 * 1024 * 1024;
            if ($f['size'] > $max || $f['size'] <= 0) admin_flash('error', 'File too large (logo max 2MB, favicon max 512KB).');
            else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($f['tmp_name']);
                $ok = $which === 'site_favicon'
                    ? ['image/png' => '.png', 'image/x-icon' => '.ico', 'image/vnd.microsoft.icon' => '.ico', 'image/svg+xml' => '.svg']
                    : ['image/png' => '.png', 'image/jpeg' => '.jpg', 'image/webp' => '.webp', 'image/svg+xml' => '.svg'];
                if (!isset($ok[$mime])) admin_flash('error', 'Invalid file type. Upload a real image file.');
                else {
                    // Re-encode check for raster images to block polyglots.
                    // GD may be missing on minimal hosts: fall back to getimagesize()
                    // (core PHP) so uploads still work with basic validation.
                    if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
                        $valid = false;
                        if (function_exists('imagecreatefrompng')) {
                            $img = match ($mime) {
                                'image/png' => @imagecreatefrompng($f['tmp_name']),
                                'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
                                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : null,
                                default => null,
                            };
                            $valid = ($img !== false && $img !== null);
                            if (is_object($img) || (is_resource($img))) {
                                if (function_exists('imagedestroy')) imagedestroy($img);
                            }
                        } else {
                            $info = @getimagesize($f['tmp_name']);
                            $valid = is_array($info) && ($info['mime'] ?? '') === $mime;
                        }
                        if (!$valid) admin_flash('error', 'File is not a valid image.');
                        else {
                            $dir = __DIR__ . '/../uploads/branding';
                            if (!is_dir($dir)) mkdir($dir, 0755, true);
                            $name = ($which === 'site_favicon' ? 'favicon_' : 'logo_') . bin2hex(random_bytes(8)) . $ok[$mime];
                            if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                                $old = (string)setting($which, '');
                                set_setting($which, '/uploads/branding/' . $name);
                                if ($old && str_starts_with($old, '/uploads/branding/') && is_file(__DIR__ . '/..' . $old)) @unlink(__DIR__ . '/..' . $old);
                                admin_log((int)$admin['id'], 'branding_change', "Uploaded new $which: /uploads/branding/$name");
                                admin_flash('success', 'Uploaded. The site now uses the new ' . ($which === 'site_favicon' ? 'favicon' : 'logo') . '.');
                            } else admin_flash('error', 'Upload failed.');
                        }
                    } else {
                        // SVG/ICO: store with strict extension + size already validated
                        $dir = __DIR__ . '/../uploads/branding';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $name = ($which === 'site_favicon' ? 'favicon_' : 'logo_') . bin2hex(random_bytes(8)) . $ok[$mime];
                        if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                            set_setting($which, '/uploads/branding/' . $name);
                            admin_log((int)$admin['id'], 'branding_change', "Uploaded new $which: /uploads/branding/$name");
                            admin_flash('success', 'Uploaded.');
                        } else admin_flash('error', 'Upload failed.');
                    }
                }
            }
        }
    }
    redirect('/admin/branding.php');
}
admin_head('Branding');
admin_sidebar($admin, 'branding');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-4">Branding</h1>';
echo '<div class="grid md:grid-cols-2 gap-4">';
foreach ([['site_logo', 'Main logo', '2MB max. PNG, JPG, WEBP or SVG.'], ['site_favicon', 'Favicon', '512KB max. PNG, ICO or SVG.']] as [$key, $label, $hint]) {
    $cur = (string)setting($key, '');
    echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-1">' . $label . '</h2><p class="text-xs text-slate-400 mb-3">' . $hint . '</p>';
    if ($cur) echo '<p class="text-xs text-slate-500 mb-2">Current: <code>' . e($cur) . '</code></p><img src="' . e(strpos($cur, '/') === 0 ? url($cur) : $cur) . '" alt="current" class="h-12 mb-3 bg-slate-100 rounded-lg p-1">';
    else echo '<p class="text-xs text-slate-400 mb-2">Using default.</p>';
    echo '<form method="POST" enctype="multipart/form-data" class="flex gap-2">' . csrf_field() . '<input type="hidden" name="which" value="' . $key . '"><input type="file" name="file" required class="flex-1 text-sm border rounded-xl p-2"><button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Upload</button></form></div>';
}
echo '</div>';
admin_footer();
