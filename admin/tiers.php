<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';

$admin = require_admin(['super_admin', 'admin']);
$pdo   = db();

/** Helper: run a prepared query and return the statement. */
function q(PDO $pdo, string $sql, array $params = []): PDOStatement {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

$action = $_GET['action'] ?? '';
$tid    = (int)($_GET['tier_id'] ?? $_POST['tier_id'] ?? 0);

/* ----------------------------------------------------------------
 | POST-only mutations
 * ---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        admin_flash('error', 'Invalid session.');
        redirect('/admin/tiers.php');
    }

    // ---- ADD ----
    if ($action === 'add') {
        $name          = trim($_POST['tier_name'] ?? '');
        $displayName   = trim($_POST['display_name'] ?? '');
        $displayRating = trim($_POST['display_rating'] ?? '⭐⭐');
        $dailyHashes   = max(0, (int)($_POST['daily_hashes'] ?? 1));
        $dailySpins    = max(0, (int)($_POST['daily_spins'] ?? 1));
        $price         = max(0, (float)($_POST['price'] ?? 0));
        $durationDays  = max(1, (int)($_POST['duration_days'] ?? 30));
        $referralReq   = max(0, (int)($_POST['referral_requirement'] ?? 3));
        $reqType       = ($_POST['requirement_type'] ?? 'fixed') === 'threshold' ? 'threshold' : 'fixed';

        if ($name === '') {
            admin_flash('error', 'Tier name is required.');
        } elseif (q($pdo, 'SELECT 1 FROM tiers WHERE name = ? LIMIT 1', [$name])->fetch()) {
            admin_flash('error', "Tier name '$name' already exists.");
        } else {
            $perHash = $price / max(1, $dailyHashes);
            q($pdo,
                'INSERT INTO tiers
                    (name, daily_hashes, daily_spins, price, duration_days, per_hash,
                     display_name, display_rating, referral_requirement, requirement_type, active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,1)',
                [$name, $dailyHashes, $dailySpins, $price, $durationDays, $perHash,
                 $displayName, $displayRating, $referralReq, $reqType]
            );
            admin_log((int)$admin['id'], 'tier_add', "Tier '$name' created");
            admin_flash('success', "Tier '$displayName' added successfully.");
        }
        redirect('/admin/tiers.php');
    }

    // ---- EDIT ----
    if ($action === 'edit' && $tid > 0) {
        $dName     = trim($_POST['display_name'] ?? '');
        $dRating   = trim($_POST['display_rating'] ?? '');
        $dHashes   = max(0, (int)($_POST['daily_hashes'] ?? 0));
        $dSpins    = max(0, (int)($_POST['daily_spins'] ?? 0));
        $dPrice    = max(0, (float)($_POST['price'] ?? 0));
        $dDuration = max(1, (int)($_POST['duration_days'] ?? 30));
        $dRefReq   = max(0, (int)($_POST['referral_requirement'] ?? 3));
        $dReqType  = ($_POST['requirement_type'] ?? 'fixed') === 'threshold' ? 'threshold' : 'fixed';

        $tier = q($pdo, 'SELECT * FROM tiers WHERE id = ? LIMIT 1', [$tid])->fetch(PDO::FETCH_ASSOC);
        if (!$tier) {
            admin_flash('error', 'Tier not found.');
        } else {
            q($pdo,
                'UPDATE tiers
                    SET display_name=?, display_rating=?, daily_hashes=?, daily_spins=?,
                        price=?, duration_days=?, per_hash=?,
                        referral_requirement=?, requirement_type=?
                  WHERE id=?',
                [$dName, $dRating, $dHashes, $dSpins, $dPrice, $dDuration,
                 $dPrice / max(1, $dHashes), $dRefReq, $dReqType, $tid]
            );
            admin_log((int)$admin['id'], 'tier_edit', "Tier #$tid updated");
            admin_flash('success', 'Tier updated successfully.');
        }
        redirect('/admin/tiers.php');
    }

    // ---- ACTIVATE / DEACTIVATE / DELETE ----
    if (in_array($action, ['activate', 'deactivate', 'delete'], true) && $tid > 0) {
        $tier = q($pdo, 'SELECT * FROM tiers WHERE id = ? LIMIT 1', [$tid])->fetch(PDO::FETCH_ASSOC);
        if (!$tier) {
            admin_flash('error', 'Tier not found.');
            redirect('/admin/tiers.php');
        }

        $userCount = (int) q($pdo, 'SELECT COUNT(*) FROM users WHERE tier_id = ?', [$tid])->fetchColumn();

        if ($action === 'activate') {
            q($pdo, 'UPDATE tiers SET active = 1 WHERE id = ?', [$tid]);
            admin_log((int)$admin['id'], 'tier_activate', "Tier #$tid activated");
            admin_flash('success', 'Tier activated.');
        } elseif ($action === 'deactivate') {
            if ($userCount > 0) {
                admin_flash('error', "Cannot deactivate: $userCount user(s) are on this tier.");
            } else {
                q($pdo, 'UPDATE tiers SET active = 0 WHERE id = ?', [$tid]);
                admin_log((int)$admin['id'], 'tier_deactivate', "Tier #$tid deactivated");
                admin_flash('success', 'Tier deactivated.');
            }
        } elseif ($action === 'delete') {
            if ($userCount > 0) {
                admin_flash('error', "Cannot delete: $userCount user(s) are assigned. Deactivate instead.");
            } else {
                q($pdo, 'DELETE FROM tiers WHERE id = ?', [$tid]);
                admin_log((int)$admin['id'], 'tier_delete', "Tier #$tid deleted");
                admin_flash('success', 'Tier deleted.');
            }
        }
        redirect('/admin/tiers.php');
    }
}

/* ----------------------------------------------------------------
 | Render data
 * ---------------------------------------------------------------- */
$editTier = null;
if ($tid > 0 && $action === 'edit') {
    $editTier = q($pdo, 'SELECT * FROM tiers WHERE id = ? LIMIT 1', [$tid])->fetch(PDO::FETCH_ASSOC);
}

$allTiers      = q($pdo, 'SELECT * FROM tiers ORDER BY active DESC, id')->fetchAll(PDO::FETCH_ASSOC);
$activeTiers   = array_filter($allTiers, fn($t) => (int)$t['active'] === 1);
$inactiveTiers = array_filter($allTiers, fn($t) => (int)$t['active'] === 0);

$userCountByTier = [];
if ($allTiers) {
    $ids = array_column($allTiers, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $rows = q($pdo, "SELECT tier_id, COUNT(*) c FROM users WHERE tier_id IN ($in) GROUP BY tier_id", $ids)
              ->fetchAll(PDO::FETCH_KEY_PAIR);
    $userCountByTier = $rows;
}

admin_head('Tiers');
admin_sidebar($admin, 'tiers');
echo admin_flashes();
?>
<div class="px-4 py-4">
<h1 class="text-xl font-bold text-slate-800 mb-4">Tier Management</h1>

<?php if ($editTier): ?>
<!-- Edit Tier Form -->
<div class="bg-white rounded-2xl p-4 shadow-sm mb-6 border-2 border-blue-200">
<h2 class="font-bold text-sm mb-3">Edit Tier: <?php echo e($editTier['display_name'] ?: $editTier['name']); ?></h2>
<form method="POST" action="/admin/tiers.php?action=edit&tier_id=<?php echo (int)$editTier['id']; ?>" class="space-y-4">
<?php echo csrf_field(); ?>
<input type="hidden" name="tier_id" value="<?php echo (int)$editTier['id']; ?>">
<div class="grid md:grid-cols-2 gap-3">
    <div><label class="text-sm text-slate-600">Tier Name (read-only)</label>
        <input type="text" value="<?php echo e($editTier['name']); ?>" disabled class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-slate-100"></div>
    <div><label class="text-sm text-slate-600">Display Name</label>
        <input name="display_name" type="text" value="<?php echo e($editTier['display_name']); ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Display Rating</label>
        <input name="display_rating" type="text" value="<?php echo e($editTier['display_rating']); ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Daily Hashes</label>
        <input name="daily_hashes" type="number" min="1" value="<?php echo (int)$editTier['daily_hashes']; ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Daily Spins</label>
        <input name="daily_spins" type="number" min="1" value="<?php echo (int)$editTier['daily_spins']; ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Price (USD)</label>
        <input name="price" type="number" step="0.01" min="0" value="<?php echo e($editTier['price']); ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Duration (days)</label>
        <input name="duration_days" type="number" min="1" value="<?php echo (int)$editTier['duration_days']; ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Referral Requirement</label>
        <input name="referral_requirement" type="number" min="0" value="<?php echo (int)$editTier['referral_requirement']; ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Requirement Type</label>
        <select name="requirement_type" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">
            <option value="fixed" <?php echo $editTier['requirement_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
            <option value="threshold" <?php echo $editTier['requirement_type'] === 'threshold' ? 'selected' : ''; ?>>Threshold</option>
        </select></div>
</div>
<div class="flex gap-3">
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-semibold">Save Changes</button>
    <a href="/admin/tiers.php" class="bg-slate-200 px-4 py-2 rounded text-sm">Cancel</a>
</div>
</form>
</div>
<?php else: ?>
<!-- Add New Tier Form -->
<div class="bg-white rounded-2xl p-4 shadow-sm mb-6">
<h2 class="font-bold text-sm mb-3">Add New Tier</h2>
<form method="POST" action="/admin/tiers.php?action=add" class="space-y-4">
<?php echo csrf_field(); ?>
<div class="grid md:grid-cols-2 gap-3">
    <div><label class="text-sm text-slate-600">Tier Name</label><input name="tier_name" type="text" required class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Display Name</label><input name="display_name" type="text" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm" placeholder="e.g. Beginner"></div>
    <div><label class="text-sm text-slate-600">Display Rating</label><input name="display_rating" type="text" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm" value="⭐⭐" placeholder="e.g. ⭐⭐⭐"></div>
    <div><label class="text-sm text-slate-600">Daily Hashes</label><input name="daily_hashes" type="number" min="1" value="1" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Daily Spins</label><input name="daily_spins" type="number" min="1" value="1" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
</div>
<div class="grid md:grid-cols-3 gap-3">
    <div><label class="text-sm text-slate-600">Price (USD)</label><input name="price" type="number" step="0.01" min="0" value="0" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Duration (days)</label><input name="duration_days" type="number" min="1" value="30" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Referral Requirement</label><input name="referral_requirement" type="number" min="0" value="3" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
</div>
<div><label class="text-sm text-slate-600">Requirement Type</label>
<select name="requirement_type" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">
    <option value="fixed">Fixed</option>
    <option value="threshold">Threshold</option>
</select></div>
<div class="flex gap-3">
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold">Create Tier</button>
</div>
</form>
</div>
<?php endif; ?>

<!-- Existing Tiers Table -->
<div class="bg-white rounded-2xl p-4 shadow-sm">
<h2 class="font-bold text-sm mb-3">
    Existing Tiers
    <?php if (count($activeTiers) > 0): ?>
        <span class="text-green-600">(Active: <?php echo count($activeTiers); ?>)</span>
    <?php endif; ?>
    <?php if (count($inactiveTiers) > 0): ?>
        <span class="text-red-600">(Inactive: <?php echo count($inactiveTiers); ?>)</span>
    <?php endif; ?>
</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm min-w-[640px]">
<thead><tr class="text-left text-xs text-slate-400 border-b">
    <th class="p-3">Display Name</th><th class="p-3">Rating</th><th class="p-3">Hashes/Spins</th>
    <th class="p-3">Price</th><th class="p-3">Duration</th><th class="p-3">Referral</th>
    <th class="p-3">Status</th><th class="p-3 text-right">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($allTiers as $t):
    $uc    = (int)($userCountByTier[$t['id']] ?? 0);
    $isAct = $t['active'] ? 'Active' : 'Inactive';
    $bCls  = $t['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    $rowCls = $t['active'] ? 'bg-green-50' : 'bg-red-50';
?>
<tr class="border-b border-slate-100 last:border-0 <?php echo $rowCls; ?>">
<td class="p-3 font-medium"><?php echo e($t['display_name'] ?? $t['name']); ?></td>
<td class="p-3 text-center"><?php echo e($t['display_rating'] ?? ''); ?></td>
<td class="p-3 text-center"><?php echo (int)$t['daily_hashes']; ?>H / <?php echo (int)$t['daily_spins']; ?>S</td>
<td class="p-3 text-right">$<?php echo number_format((float)$t['price'], 2); ?></td>
<td class="p-3 text-center"><?php echo (int)$t['duration_days']; ?>d</td>
<td class="p-3 text-center"><?php echo (int)$t['referral_requirement']; ?><?php echo $t['requirement_type'] === 'threshold' ? ' (more than)' : ''; ?></td>
<td class="p-3"><span class="px-2 py-1 rounded text-xs font-semibold <?php echo $bCls; ?>"><?php echo $isAct; ?></span></td>
<td class="p-3 text-right whitespace-nowrap">
    <form method="POST" action="/admin/tiers.php?action=<?php echo $t['active'] ? 'deactivate' : 'activate'; ?>&tier_id=<?php echo (int)$t['id']; ?>" class="inline">
        <?php echo csrf_field(); ?>
        <button type="submit" class="<?php echo $t['active'] ? 'text-red-600' : 'text-green-600'; ?> text-xs hover:underline">
            <?php echo $t['active'] ? 'Deactivate' : 'Activate'; ?>
        </button>
    </form>
    <?php if ($uc > 0): ?><span class="text-xs text-slate-500 ml-1">(<?php echo $uc; ?> users)</span><?php endif; ?>
    <a href="/admin/tiers.php?action=edit&tier_id=<?php echo (int)$t['id']; ?>" class="text-blue-600 text-xs hover:underline ml-2">Edit</a>
    <?php if ($uc === 0): ?>
        <form method="POST" action="/admin/tiers.php?action=delete&tier_id=<?php echo (int)$t['id']; ?>" class="inline ml-2"
              onsubmit="return confirm('Delete this tier permanently?');">
            <?php echo csrf_field(); ?>
            <button type="submit" class="text-red-600 text-xs hover:underline">Delete</button>
        </form>
    <?php else: ?>
        <span class="text-gray-400 text-xs ml-2">Delete (<?php echo $uc; ?> users)</span>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
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
<?php echo render_toast_queue(); ?>