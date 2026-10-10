<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../admin_auth.php';
$pdo = db();
$options = $pdo->query("SELECT * FROM tiers WHERE active = 1 ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($options as $o) {
    $activeClass = $o['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    $activeText = $o['active'] ? 'Active' : 'Inactive';
    $reqType = $o['requirement_type'] ?? 'fixed';
    $id = $o['id'];
    echo '<div class="p-3 border rounded-xl ' . ($o['active'] ? 'bg-green-50' : 'bg-red-50') . '">
        <div class="flex items-center justify-between">
            <span class="font-medium text-sm">' . htmlspecialchars($o['display_name']) . ' <span class="text-xs ' . $activeClass . '">' . $activeText . '</span></span>
            <span class="text-xs text-slate-500">Rating: ' . htmlspecialchars($o['display_rating'] ?? '') . ' | Hash: ' . htmlspecialchars($o['daily_hash_allowance'] ?? 0) . ' | Spin: ' . htmlspecialchars($o['daily_spin_allowance'] ?? 0) . ' | Refer: ' . htmlspecialchars($o['referral_requirement'] ?? 0) . ' (' . ucfirst($reqType) . ') - $' . number_format($o['price'] ?? 0) . '/' . htmlspecialchars($o['duration_days'] ?? 30) . 'd</span>
        </div>
        <button class="text-blue-600 text-xs edit-upgrade-option-btn" data-id="' . $id . '">Edit</button>
        <button class="text-red-600 text-xs delete-upgrade-option-btn" data-id="' . $id . '">Delete</button>
    </div>';
}