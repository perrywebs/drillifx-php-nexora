<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/payment_methods.php';
require_once __DIR__ . '/includes/layout.php';

$admin = require_admin(['super_admin', 'admin']);
$pdo = db();

function pm_fields_from_post(): array {
    $labels = $_POST['field_label'] ?? [];
    $values = $_POST['field_value'] ?? [];
    $types = $_POST['field_type'] ?? [];
    $copies = $_POST['field_copy'] ?? [];
    $orders = $_POST['field_order'] ?? [];
    $allowed = array_keys(pm_allowed_display_types());
    $out = [];
    $n = max(count((array)$labels), count((array)$values));
    for ($i = 0; $i < $n; $i++) {
        $label = trim((string)(is_array($labels) ? ($labels[$i] ?? '') : ''));
        $value = trim((string)(is_array($values) ? ($values[$i] ?? '') : ''));
        if ($label === '' && $value === '') continue;
        $type = (string)(is_array($types) ? ($types[$i] ?? 'single_line') : 'single_line');
        if (!in_array($type, $allowed, true)) $type = 'single_line';
        $copy = 0;
        if (is_array($copies)) {
            // checkboxes post per-row hidden+checkbox pair; accept '1' at same index
            $copy = isset($copies[$i]) && (string)$copies[$i] === '1' ? 1 : 0;
        }
        $order = (int)(is_array($orders) ? ($orders[$i] ?? $i) : $i);
        $out[] = ['label' => mb_substr($label, 0, 100), 'value' => $value,
                  'display_type' => $type, 'copy_enabled' => $copy, 'display_order' => $order];
    }
    // Normalise order to 0..n so display is stable even if admin leaves gaps.
    usort($out, fn($a, $b) => $a['display_order'] <=> $b['display_order']);
    foreach ($out as $i => &$f) $f['display_order'] = $i;
    return $out;
}

function pm_save_fields(PDO $pdo, int $methodId, array $fields): void {
    $pdo->prepare('DELETE FROM payment_method_fields WHERE payment_method_id = ?')->execute([$methodId]);
    $st = $pdo->prepare('INSERT INTO payment_method_fields (payment_method_id, `label`, `value`, display_type, copy_enabled, display_order) VALUES (?,?,?,?,?,?)');
    foreach ($fields as $f) {
        $st->execute([$methodId, $f['label'], $f['value'], $f['display_type'], $f['copy_enabled'], $f['display_order']]);
    }
}

$action = $_GET['action'] ?? '';
$mid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        admin_flash('error', 'Invalid session.');
        redirect('/admin/payment_methods.php');
    }
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $active = isset($_POST['active']) && $_POST['active'] === '1' ? 1 : 0;
        $order = max(0, (int)($_POST['display_order'] ?? 0));
        $fields = pm_fields_from_post();
        if ($name === '') {
            admin_flash('error', 'Payment method name is required.');
            redirect('/admin/payment_methods.php' . ($action === 'edit' && $mid > 0 ? '?action=edit&id=' . $mid : ''));
        }
        // Unique name (excluding self on edit).
        $st = $pdo->prepare($action === 'edit'
            ? 'SELECT 1 FROM payment_methods WHERE name = ? AND id <> ? LIMIT 1'
            : 'SELECT 1 FROM payment_methods WHERE name = ? LIMIT 1');
        $st->execute($action === 'edit' ? [$name, $mid] : [$name]);
        if ($st->fetch()) {
            admin_flash('error', "A payment method named '$name' already exists.");
            redirect('/admin/payment_methods.php' . ($action === 'edit' && $mid > 0 ? '?action=edit&id=' . $mid : ''));
        }
        $pdo->beginTransaction();
        try {
            if ($action === 'add') {
                $pdo->prepare('INSERT INTO payment_methods (name, description, active, display_order) VALUES (?,?,?,?)')
                    ->execute([$name, $desc !== '' ? $desc : null, $active, $order]);
                $mid = (int)$pdo->lastInsertId();
                pm_save_fields($pdo, $mid, $fields);
                $pdo->commit();
                admin_log((int)$admin['id'], 'payment_method_add', "Payment method '$name' created with " . count($fields) . " field(s)", 'payment_method', $mid);
                admin_flash('success', "Payment method '$name' added.");
            } else {
                $cur = pm_get_method($mid);
                if (!$cur) { $pdo->rollBack(); admin_flash('error', 'Payment method not found.'); redirect('/admin/payment_methods.php'); }
                $pdo->prepare('UPDATE payment_methods SET name=?, description=?, active=?, display_order=? WHERE id=?')
                    ->execute([$name, $desc !== '' ? $desc : null, $active, $order, $mid]);
                pm_save_fields($pdo, $mid, $fields);
                $pdo->commit();
                admin_log((int)$admin['id'], 'payment_method_edit', "Payment method '$name' updated (" . count($fields) . " field(s))", 'payment_method', $mid);
                admin_flash('success', "Payment method '$name' updated. Existing deposits keep their original snapshots.");
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            admin_flash('error', 'Save failed. Try again.');
        }
        redirect('/admin/payment_methods.php');
    }
    if (in_array($action, ['activate', 'deactivate', 'delete'], true) && $mid > 0) {
        $cur = pm_get_method($mid);
        if (!$cur) { admin_flash('error', 'Payment method not found.'); redirect('/admin/payment_methods.php'); }
        if ($action === 'activate' || $action === 'deactivate') {
            $pdo->prepare('UPDATE payment_methods SET active=? WHERE id=?')->execute([$action === 'activate' ? 1 : 0, $mid]);
            admin_log((int)$admin['id'], $action === 'activate' ? 'payment_method_activate' : 'payment_method_deactivate', "Payment method '{$cur['name']}' " . ($action === 'activate' ? 'activated' : 'deactivated'), 'payment_method', $mid);
            admin_flash('success', "Payment method '{$cur['name']}' " . ($action === 'activate' ? 'activated.' : 'deactivated. Existing deposits are preserved.'));
        } else {
            // Safe deletion: never orphan history. Block when deposits reference it.
            $st = $pdo->prepare('SELECT COUNT(*) FROM deposits WHERE payment_method_id = ?');
            $st->execute([$mid]);
            $used = (int)$st->fetchColumn();
            if ($used > 0) {
                admin_flash('error', "Cannot delete '{$cur['name']}': $used deposit(s) use it. Deactivate it instead — history is preserved.");
            } else {
                $pdo->prepare('DELETE FROM payment_methods WHERE id=?')->execute([$mid]);
                admin_log((int)$admin['id'], 'payment_method_delete', "Payment method '{$cur['name']}' deleted", 'payment_method', $mid);
                admin_flash('success', "Payment method '{$cur['name']}' deleted.");
            }
        }
        redirect('/admin/payment_methods.php');
    }
}

$editMethod = null;
$editFields = [];
if ($action === 'edit' && $mid > 0) {
    $editMethod = pm_get_method($mid);
    if ($editMethod) $editFields = pm_method_fields($mid);
    else { admin_flash('error', 'Payment method not found.'); redirect('/admin/payment_methods.php'); }
}

$methods = $pdo->query('SELECT m.*, (SELECT COUNT(*) FROM payment_method_fields f WHERE f.payment_method_id = m.id) AS field_count, (SELECT COUNT(*) FROM deposits d WHERE d.payment_method_id = m.id) AS deposit_count FROM payment_methods m ORDER BY m.display_order ASC, m.id ASC')->fetchAll();
$typeOptions = pm_allowed_display_types();

admin_head('Payment Methods');
admin_sidebar($admin, 'payment_methods');
echo admin_flashes();
?>
<div class="px-4 py-4">
<h1 class="text-xl font-bold text-slate-800 mb-1">Payment Methods</h1>
<p class="text-sm text-slate-500 mb-4">Methods users can pay with. Changes apply to new deposits only — existing deposits keep their saved snapshot.</p>

<div class="bg-white rounded-2xl p-4 shadow-sm mb-6 <?php echo $editMethod ? 'border-2 border-blue-200' : ''; ?>">
<h2 class="font-bold text-sm mb-3"><?php echo $editMethod ? 'Edit: ' . e($editMethod['name']) : 'Add Payment Method'; ?></h2>
<form method="POST" action="/admin/payment_methods.php?action=<?php echo $editMethod ? 'edit&id=' . (int)$editMethod['id'] : 'add'; ?>" class="space-y-4">
<?php echo csrf_field(); ?>
<?php if ($editMethod): ?><input type="hidden" name="id" value="<?php echo (int)$editMethod['id']; ?>"><?php endif; ?>
<div class="grid md:grid-cols-2 gap-3">
    <div><label class="text-sm text-slate-600">Method name *</label>
        <input name="name" type="text" required maxlength="100" value="<?php echo e($editMethod['name'] ?? ''); ?>" placeholder="e.g. Bank Transfer" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
    <div><label class="text-sm text-slate-600">Display order</label>
        <input name="display_order" type="number" min="0" value="<?php echo (int)($editMethod['display_order'] ?? 0); ?>" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></div>
</div>
<div><label class="text-sm text-slate-600">Description / intro instructions</label>
    <textarea name="description" rows="2" maxlength="2000" placeholder="Short intro shown to users when they view this method" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"><?php echo e($editMethod['description'] ?? ''); ?></textarea></div>
<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="active" value="1" <?php echo !$editMethod || (int)$editMethod['active'] === 1 ? 'checked' : ''; ?> class="w-4 h-4"> Active (only active methods can be selected for new deposits)</label>

<div>
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-bold text-sm text-slate-700">Payment detail fields</h3>
        <button type="button" onclick="pmAddField()" class="bg-slate-900 text-white px-3 py-1.5 rounded-xl text-xs font-semibold">+ Add field</button>
    </div>
    <p class="text-xs text-slate-400 mb-2">Each field has a label, value, display order, type, and an optional Copy button. Use ↑ ↓ to reorder.</p>
    <div id="pmFields" class="space-y-2"></div>
</div>

<div class="flex gap-3">
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-semibold"><?php echo $editMethod ? 'Save Changes' : 'Create Method'; ?></button>
    <?php if ($editMethod): ?><a href="/admin/payment_methods.php" class="bg-slate-200 px-4 py-2 rounded-xl text-sm">Cancel</a><?php endif; ?>
</div>
</form>
</div>

<div class="bg-white rounded-2xl p-4 shadow-sm">
<h2 class="font-bold text-sm mb-3">Existing Methods (<?php echo count($methods); ?>)</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm min-w-[640px]">
<thead><tr class="text-left text-xs text-slate-400 border-b">
    <th class="p-3">Name</th><th class="p-3">Fields</th><th class="p-3">Deposits</th><th class="p-3">Status</th><th class="p-3 text-right">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($methods as $m):
    $isAct = (int)$m['active'] === 1;
    $bCls = $isAct ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    $rowCls = $isAct ? '' : 'bg-slate-50';
?>
<tr class="border-b border-slate-100 last:border-0 <?php echo $rowCls; ?>">
<td class="p-3"><p class="font-medium"><?php echo e($m['name']); ?></p><?php if (!empty($m['description'])): ?><p class="text-xs text-slate-400"><?php echo e(mb_substr($m['description'], 0, 80)); ?></p><?php endif; ?></td>
<td class="p-3 text-center"><?php echo (int)$m['field_count']; ?></td>
<td class="p-3 text-center"><?php echo (int)$m['deposit_count']; ?></td>
<td class="p-3"><span class="px-2 py-1 rounded text-xs font-semibold <?php echo $bCls; ?>"><?php echo $isAct ? 'Active' : 'Inactive'; ?></span></td>
<td class="p-3 text-right whitespace-nowrap">
    <form method="POST" action="/admin/payment_methods.php?action=<?php echo $isAct ? 'deactivate' : 'activate'; ?>&id=<?php echo (int)$m['id']; ?>" class="inline">
        <?php echo csrf_field(); ?>
        <button type="submit" class="<?php echo $isAct ? 'text-red-600' : 'text-green-600'; ?> text-xs hover:underline"><?php echo $isAct ? 'Deactivate' : 'Activate'; ?></button>
    </form>
    <a href="/admin/payment_methods.php?action=edit&id=<?php echo (int)$m['id']; ?>" class="text-blue-600 text-xs hover:underline ml-2">Edit</a>
    <?php if ((int)$m['deposit_count'] === 0): ?>
        <form method="POST" action="/admin/payment_methods.php?action=delete&id=<?php echo (int)$m['id']; ?>" class="inline ml-2" onsubmit="return confirm('Delete this payment method permanently?');">
            <?php echo csrf_field(); ?>
            <button type="submit" class="text-red-600 text-xs hover:underline">Delete</button>
        </form>
    <?php else: ?>
        <span class="text-gray-400 text-xs ml-2" title="Used by deposits — deactivate instead of deleting">Delete (<?php echo (int)$m['deposit_count']; ?> deposits)</span>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$methods): ?><tr><td colspan="5" class="p-4 text-center text-sm text-slate-400">No payment methods yet. Add one above — users will only see active methods.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>

<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4 mt-6">
<h3 class="font-bold text-sm mb-2">Important</h3>
<ul class="list-disc list-inside text-sm">
<li>Only <strong>active</strong> methods appear on the user deposit flow.</li>
<li>Editing a method affects <strong>new deposits only</strong> — submitted deposits keep their saved snapshot.</li>
<li>A method used by deposits cannot be deleted. Deactivate it instead.</li>
</ul>
</div>
</div>

<script>
var PM_TYPES = <?php echo json_encode($typeOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var PM_EXISTING = <?php echo json_encode($editFields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function pmFieldRow(f) {
    f = f || {label:'', value:'', display_type:'single_line', copy_enabled:0, display_order: document.querySelectorAll('#pmFields .pm-row').length};
    var div = document.createElement('div');
    div.className = 'pm-row border border-slate-200 rounded-xl p-3 bg-slate-50';
    var opts = '';
    Object.keys(PM_TYPES).forEach(function(k){
        opts += '<option value="'+k+'"'+(f.display_type===k?' selected':'')+'>'+PM_TYPES[k]+'</option>';
    });
    div.innerHTML =
        '<div class="grid md:grid-cols-2 gap-2">' +
        '<div><label class="text-xs text-slate-500">Label</label><input name="field_label[]" value="" maxlength="100" placeholder="e.g. Account Number" class="mt-1 w-full border rounded-lg px-2 py-1.5 text-sm bg-white"></div>' +
        '<div><label class="text-xs text-slate-500">Value</label><input name="field_value[]" value="" placeholder="e.g. 0123456789" class="mt-1 w-full border rounded-lg px-2 py-1.5 text-sm bg-white"></div>' +
        '</div>' +
        '<div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 items-end">' +
        '<div><label class="text-xs text-slate-500">Type</label><select name="field_type[]" class="mt-1 w-full border rounded-lg px-2 py-1.5 text-sm bg-white">'+opts+'</select></div>' +
        '<div><label class="text-xs text-slate-500">Order</label><input name="field_order[]" type="number" min="0" class="mt-1 w-full border rounded-lg px-2 py-1.5 text-sm bg-white"></div>' +
        '<label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" class="pm-copy w-4 h-4"> Copy button</label>' +
        '<div class="flex gap-1 justify-end"><button type="button" class="pm-up text-xs px-2 py-1 bg-white border rounded-lg" title="Move up">↑</button><button type="button" class="pm-down text-xs px-2 py-1 bg-white border rounded-lg" title="Move down">↓</button><button type="button" class="pm-del text-xs px-2 py-1 bg-red-50 text-red-600 border border-red-200 rounded-lg">Remove</button></div>' +
        '</div>' +
        '<input type="hidden" class="pm-copyval" name="field_copy[]">';
    var inputs = div.querySelectorAll('input[name="field_label[]"], input[name="field_value[]"]');
    inputs[0].value = f.label || '';
    inputs[1].value = f.value || '';
    div.querySelector('input[name="field_order[]"]').value = (f.display_order != null ? f.display_order : 0);
    var chk = div.querySelector('.pm-copy');
    var hid = div.querySelector('.pm-copyval');
    chk.checked = !!+f.copy_enabled;
    hid.value = chk.checked ? '1' : '0';
    chk.addEventListener('change', function(){ hid.value = chk.checked ? '1' : '0'; });
    div.querySelector('.pm-del').addEventListener('click', function(){ div.remove(); pmRenumber(); });
    div.querySelector('.pm-up').addEventListener('click', function(){ var p = div.previousElementSibling; if (p) { div.parentNode.insertBefore(div, p); pmRenumber(); } });
    div.querySelector('.pm-down').addEventListener('click', function(){ var n = div.nextElementSibling; if (n) { div.parentNode.insertBefore(n, div); pmRenumber(); } });
    return div;
}
function pmRenumber() {
    document.querySelectorAll('#pmFields .pm-row').forEach(function(row, i){
        row.querySelector('input[name="field_order[]"]').value = i;
    });
}
function pmAddField(f) {
    document.getElementById('pmFields').appendChild(pmFieldRow(f));
    pmRenumber();
}
(function(){
    if (PM_EXISTING && PM_EXISTING.length) PM_EXISTING.forEach(function(f){ pmAddField(f); });
    else pmAddField();
})();
</script>

<?php admin_footer(); ?>
