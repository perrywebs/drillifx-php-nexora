<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin(['super_admin', 'admin']);
$pdo = db();

$action = $_GET['action'] ?? '';
$tid = (int)($_GET['tier_id'] ?? $_POST['tier_id'] ?? 0);

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    else {
        $name = trim($_POST['tier_name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $displayRating = trim($_POST['display_rating'] ?? '⭐⭐');
        $dailyHashes = (int)($_POST['daily_hashes'] ?? 1);
        $dailySpins = (int)($_POST['daily_spins'] ?? 1);
        $price = (float)($_POST['price'] ?? 0);
        $durationDays = (int)($_POST['duration_days'] ?? 30);
        $referralReq = (int)($_POST['referral_requirement'] ?? 3);
        $reqType = $_POST['requirement_type'] ?? 'fixed';

        if (empty($name)) { admin_flash('error', 'Tier name is required.'); }
        elseif ($pdo->query("SELECT 1 FROM tiers WHERE name = ? LIMIT 1")->execute([$name])->fetch()) {
            admin_flash('error', "Tier name '$name' already exists.");
        } else {
            $pdo->prepare('INSERT INTO tiers (name, daily_hashes, daily_spins, price, duration_days, per_hash, display_name, display_rating, referral_requirement, requirement_type, active) VALUES (?,?,?,?,?,?,?,?,?,?,1)')->execute([
                $name, $dailyHashes, $dailySpins, $price, $durationDays, $price / max(1, $dailyHashes), $displayName, $displayRating, $referralReq, $reqType
            ]);
            admin_flash('success', "Tier '$displayName' added successfully.");
        }
    }
    redirect('/admin/tiers.php');
}

if ($action === 'edit' && $tid > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    else {
        $dName = trim($_POST['display_name'] ?? '');
        $dRating = trim($_POST['display_rating'] ?? '');
        $dHashes = (int)($_POST['daily_hashes'] ?? 0);
        $dSpins = (int)($_POST['daily_spins'] ?? 0);
        $dPrice = (float)($_POST['price'] ?? 0);
        $dDuration = (int)($_POST['duration_days'] ?? 30);
        $dRefReq = (int)($_POST['referral_requirement'] ?? 3);
        $dReqType = $_POST['requirement_type'] ?? 'fixed';

        $tier = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1')->execute([$tid])->fetch();
        if (!$tier) { admin_flash('error', 'Tier not found.'); }
        elseif ($pdo->query("SELECT 1 FROM tiers WHERE name = ? AND id != ? LIMIT 1")->execute([$dName, $tid])->fetch()) {
            admin_flash('error', "Another tier has that name.");
        } else {
            $pdo->prepare('UPDATE tiers SET display_name=?, display_rating=?, daily_hashes=?, daily_spins=?, price=?, duration_days=?, referral_requirement=?, requirement_type=? WHERE id=?')->execute([
                $dName, $dRating, $dHashes, $dSpins, $dPrice, $dDuration, $dRefReq, $dReqType, $tid
            ]);
            admin_flash('success', "Tier updated successfully.");
        }
    }
    redirect('/admin/tiers.php?tier_id=' . $tid);
}

if ($action === 'activate' && $tid > 0) {
    $tier = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1')->execute([$tid])->fetch();
    if (!$tier) { admin_flash('error', 'Tier not found.'); }
    else {
        $pdo->prepare('UPDATE tiers SET active = 1 WHERE id = ?')->execute([$tid]);
        admin_flash('success', "Tier activated.");
    }
    redirect('/admin/tiers.php');
}

if ($action === 'deactivate' && $tid > 0) {
    $tier = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1')->execute([$tid])->fetch();
    if (!$tier) { admin_flash('error', 'Tier not found.'); }
    elseif ($pdo->query("SELECT COUNT(*) c FROM users WHERE tier_id = ?")->execute([$tid])->fetchColumn() > 0) {
        admin_flash('error', 'Cannot deactivate: ' . $pdo->query("SELECT COUNT(*) c FROM users WHERE tier_id = ?")->execute([$tid])->fetchColumn() . ' user(s) are on this tier.');
    } else {
        $pdo->prepare('UPDATE tiers SET active = 0 WHERE id = ?')->execute([$tid]);
        admin_flash('success', 'Tier deactivated.');
    }
    redirect('/admin/tiers.php');
}

if ($action === 'delete' && $tid > 0) {
    $count = $pdo->query("SELECT COUNT(*) c FROM users WHERE tier_id = ?")->execute([$tid])->fetchColumn();
    if ($count > 0) {
        admin_flash('error', "Cannot delete: $count user(s) are assigned to this tier. Deactivate first instead.");
    } else {
        $pdo->prepare('DELETE FROM tiers WHERE id = ?')->execute([$tid]);
        admin_flash('success', 'Tier deleted.');
    }
    redirect('/admin/tiers.php');
}

// Handle form reset after edit (show the edited tier)
$editTier = null;
if ($tid > 0) { $editTier = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1')->execute([$tid])->fetch(); }

$allTiers = $pdo->query("SELECT * FROM tiers ORDER BY active DESC, id")->fetchAll(PDO::FETCH_ASSOC);
$activeTiers = $pdo->query("SELECT * FROM tiers WHERE active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$inactiveTiers = $pdo->query("SELECT * FROM tiers WHERE active = 0 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$userCountByTier = [];
foreach ($allTiers as $t) { $userCountByTier[$t['id']] = $pdo->query("SELECT COUNT(*) c FROM users WHERE tier_id = ?")->execute([$t['id']])->fetchColumn(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Tier Management - <?php echo e(site_name()); ?> Admin</title>
<?php seo_head(['title' => 'Tier Management - ' . site_name(), 'description' => 'Manage drillifx tiers and upgrade options.', 'path' => '/admin/tiers.php']); ?>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Signika Negative','sans-serif']}}}}</script>
<style>body{font-family:'Signika Negative',sans-serif;padding-bottom:70px;background:linear-gradient(180deg,#f8fafc 0%,#eff6ff 100%);min-height:100vh}</style>
</head>
<body class="min-h-screen">
<?php admin_head('Tiers'); admin_sidebar($admin, 'tiers'); echo admin_flashes(); ?>
<div class="px-4 py-4">
<h1 class="text-xl font-bold text-slate-800 mb-4">Tier Management</h1>

<!-- Add New Tier Form -->
<div class="bg-white rounded-2xl p-4 shadow-sm mb-6">
<h2 class="font-bold text-sm mb-3">Add New Tier</h2>
<form method="POST" class="space-y-4">
<?php echo csrf_field(); ?>
<div class="grid md:grid-cols-2 gap-3">
    <div><label class="text-sm text-slate-600">Tier Name</label><input name="tier_name" type="text" required class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Display Name</label><input name="display_name" type="text" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm" placeholder="e.g. Beginner"></div>
    <div><label class="text-sm text-slate-600">Display Rating</label><input name="display_rating" type="text" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm" value="⭐⭐" placeholder="e.g. ⭐⭐⭐"></div>
    <div class="md:col-span-2"><label class="text-sm text-slate-600">Daily Hashes</label><input name="daily_hashes" type="number" min="1" value="1" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div class="md:col-span-2"><label class="text-sm text-slate-600">Daily Spins</label><input name="daily_spins" type="number" min="1" value="1" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
</div>
<div class="grid md:grid-cols-3 gap-3">
    <div><label class="text-sm text-slate-600">Price (USD)</label><input name="price" type="number" step="0.01" min="0" value="0" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Duration (days)</label><input name="duration_days" type="number" min="1" value="30" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Referral Requirement</label><input name="referral_requirement" type="number" min="0" value="3" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
</div>
<div><label class="text-sm text-slate-600">Requirement Type</label>
<select name="requirement_type" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">
    <option value="fixed" >Fixed</option>
    <option value="threshold">Threshold</option>
</select></div>
</div>
<div class="flex gap-3">
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold">Create Tier</button>
    <button type="button" onclick="history.back()" class="bg-slate-200 px-4 py-2 rounded text-sm">Cancel</button>
</div>
</form>
</div>

<!-- Existing Tiers Table -->
<div class="bg-white rounded-2xl p-4 shadow-sm">
<h2 class="font-bold text-sm mb-3">Existing Tiers <?php if (count($activeTiers) > 0): ?><span class="text-green-600">(Active: " . count($activeTiers) . ")": ?><?php endif; ?> <?php if (count($inactiveTiers) > 0): ?><span class="text-red-600">(Inactive: " . count($inactiveTiers) . ")": ?><?php endif; ?></span></h2>
<table class="w-full text-sm min-w-[640px]">
<thead><tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">Display Name</th><th class="p-3">Rating</th><th class="p-3">Hashes/Spins</th><th class="p-3">Price</th><th class="p-3">Duration</th><th class="p-3">Referral</th><th class="p-3">Status</th><th class="p-3 text-right">Actions</th></tr></thead>
<tbody>
<?php foreach ($allTiers as $t): $uc = $userCountByTier[$t['id']] ?? 0; $isAct = $t['active'] ? 'Active' : 'Inactive'; $bCls = $t['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>
<tr class="border-b border-slate-100 last:border-0<?php echo $t['active'] ? ' bg-green-50' : ' bg-red-50'; ?>">
<td class="p-3 font-medium"><?php echo e($t['display_name'] ?? $t['name']); ?></td>
<td class="p-3 text-center"><?php echo e($t['display_rating'] ?? ''); ?></td>
<td class="p-3 text-center"><?php echo (int)$t['daily_hashes']; ?>H / <?php (int)$t['daily_spins']; ?>S</td>
<td class="p-3 text-right">$<?php echo number_format((float)$t['price'], 2); ?></td>
<td class="p-3 text-center"><?php echo (int)$t['duration_days']; ?>d</td>
<td class="p-3 text-center"><?php echo (int)$t['referral_requirement']; ?><?php echo $t['requirement_type'] === 'threshold' ? ' (more than)' : ''; ?></td>
<td class="p-3"><span class="px-2 py-1 rounded text-xs font-semibold <?php echo $bCls; ?>"><?php echo $isAct; ?></span></td>
<td class="p-3 text-right">
<?php if ($t['active']): ?>
<a href="/admin/tiers.php?action=deactivate&tier_id=<?php echo $t['id']; ?>" class="text-red-600 text-xs hover:underline">Deactivate</a>
<?php else: ?>
<a href="/admin/tiers.php?action=activate&tier_id=<?php echo $t['id']; ?>" class="text-green-600 text-xs hover:underline">Activate</a>
<?php endif; ?>
<?php if ($uc > 0): ?>
<span class="text-xs text-slate-500">(<?php echo $uc; ?> users)</span>
<?php endif; ?>
<a href="/admin/tiers.php?action=edit&tier_id=<?php echo $t['id']; ?>" class="text-blue-600 text-xs hover:underline">Edit</a>
<?php if ($uc == 0): ?>
| <a href="/admin/tiers.php?action=delete&tier_id=<?php echo $t['id']; ?>" class="text-red-600 text-xs hover underline" onclick="return confirm('Delete this tier permanently? This will not affect user records.');">Delete</a>
<?php else: ?>
<span class="text-gray-400 text-xs">Delete (<?php echo $uc; ?> users)</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- Important Notes -->
<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4 mt-6">
<h3 class="font-bold text-sm mb-2">Important</h3>
<ul class="list-disc list-inside text-sm">
<li>Do not delete a tier that has users assigned. Use "Deactivate" instead.</li>
<li>Changing a tier name or requirements may affect existing users' displayed tier.</li>
<li>Only super_admins and admins can manage tiers.</li>
</ul>
</div>
</div>

<?php admin_footer(); ?>
</body>
</html>
<?php echo render_toast_queue(); ?>