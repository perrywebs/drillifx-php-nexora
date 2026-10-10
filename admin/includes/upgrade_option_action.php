<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../admin_auth.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['upgrade_option_action'] ?? '';
$id = (int)($_GET['tier_id'] ?? $_POST['tier_id'] ?? 0);
$pdo = db();

if ($action === 'create' || $action === 'update') {
    $name = trim($_POST['upgrade_option_name'] ?? '');
    $displayName = trim($_POST['upgrade_option_display_name'] ?? '');
    $displayRating = trim($_POST['upgrade_option_display_rating'] ?? '⭐⭐');
    $dailyHash = (int)($_POST['upgrade_option_daily_hash'] ?? 1);
    $dailySpin = (int)($_POST['upgrade_option_daily_spin'] ?? 1);
    $referralRequirement = (int)($_POST['upgrade_option_referral_requirement'] ?? 3);
    $requirementType = $_POST['upgrade_option_requirement_type'] ?? 'fixed';
    $price = (float)($_POST['upgrade_option_price'] ?? 0);
    $duration = (int)($_POST['upgrade_option_duration'] ?? 30);

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit;
    }

    if ($action === 'create') {
        // Check if name already exists in tiers
        $existing = $pdo->prepare("SELECT id FROM tiers WHERE name = ? LIMIT 1");
        $existing->execute([$name]);
        if ($existing->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A tier with this name already exists']);
            exit;
        }
        $pdo->prepare('INSERT INTO tiers (name, daily_hashes, daily_spins, price, duration_days, per_hash, display_name, display_rating, referral_requirement, requirement_type, daily_hash_allowance, daily_spin_allowance, active) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)')->execute([
            $name, $dailyHash, $dailySpin, $price, $duration, $price / max(1, $dailyHash), $displayName, $displayRating, $referralRequirement, $requirementType, $dailyHash, $dailySpin
        ]);
        echo json_encode(['success' => true, 'message' => 'Tier created successfully']);
    } elseif ($action === 'update') {
        $uo = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1')->execute([$id])->fetch();
        if (!$uo) {
            echo json_encode(['success' => false, 'message' => 'Tier not found']);
            exit;
        }
        $pdo->prepare('UPDATE tiers SET display_name = ?, display_rating = ?, daily_hashes = ?, daily_spins = ?, price = ?, duration_days = ?, per_hash = ?, referral_requirement = ?, requirement_type = ? WHERE id=?')->execute([
            $displayName, $displayRating, $dailyHash, $dailySpin, $price, $duration, $price / max(1, $dailyHash), $referralRequirement, $requirementType, $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Tier updated successfully']);
    }
} elseif ($action === 'delete') {
    // Check if any users are using this tier
    $using = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tier_id = ?")->execute([$id])->fetchColumn();
    if ($using > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: ' . $using . ' user(s) are on this tier. Deactivate first instead.']);
        exit;
    }
    $pdo->prepare('DELETE FROM tiers WHERE id = ?')->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Tier deleted successfully']);
} elseif ($action === 'edit') {
    $uo = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1')->execute([$id])->fetch();
    if (!$uo) {
        echo json_encode(['success' => false, 'message' => 'Tier not found']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Loaded', 'fields' => [
        'name' => $uo['name'],
        'display_name' => $uo['display_name'],
        'display_rating' => $uo['display_rating'],
        'daily_hash' => $uo['daily_hash_allowance'],
        'daily_spin' => $uo['daily_spin_allowance'],
        'referral_requirement' => $uo['referral_requirement'],
        'requirement_type' => $uo['requirement_type'],
        'price' => $uo['price'],
        'duration' => $uo['duration_days'],
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}