<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
if (setting('maintenance_mode', '0') !== '1') redirect('/index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_head(['title' => 'Maintenance - ' . site_name(), 'description' => site_name() . ' is briefly unavailable while the platform is improved. Please check back shortly.', 'canonical' => false, 'index' => false]); ?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<style>*{font-family:'Signika Negative',sans-serif}</style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 text-center">
<p class="text-4xl mb-3">🛠</p>
<h1 class="text-xl font-bold text-gray-800 mb-2">Under maintenance</h1>
<p class="text-gray-500 text-sm"><?php echo e(site_name()); ?> is briefly unavailable while we improve the platform. Please check back shortly.</p>
</div>
</body>
</html>
