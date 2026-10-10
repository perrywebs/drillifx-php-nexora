<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_head(['title' => 'Page not found - ' . site_name(), 'description' => 'The page you are looking for was moved or never existed.', 'canonical' => false, 'index' => false]); ?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>*{font-family:'Signika Negative',sans-serif}</style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md text-center">
<img src="<?php echo e(site_logo()); ?>" alt="logo" class="w-14 h-14 mx-auto mb-4">
<h1 class="text-6xl font-bold text-blue-600 mb-2">404</h1>
<h2 class="text-xl font-bold text-gray-800 mb-2">This page doesn't exist</h2>
<p class="text-gray-500 text-sm mb-6">The page you are looking for was moved or never existed.</p>
<div class="flex gap-2 justify-center">
<a href="/index.php" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold text-sm">Go Home</a>
<a href="/login.php" class="bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold text-sm">Sign In</a>
</div>
<p class="text-center text-gray-400 text-xs mt-8">&copy; 2026 <?php echo e(site_name()); ?>. All rights reserved.</p>
</div>
</body>
</html>
