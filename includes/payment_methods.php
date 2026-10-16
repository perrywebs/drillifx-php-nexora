<?php
declare(strict_types=1);
// Dynamic deposit payment methods: DB-driven, no hardcoded methods.
// Tables: payment_methods, payment_method_fields.
// Deposits link via deposits.payment_method_id + snapshot columns
// (payment_method_name, payment_snapshot) so history survives edits.

function pm_active_methods(): array {
    try {
        return db()->query("SELECT * FROM payment_methods WHERE active = 1 ORDER BY display_order ASC, id ASC")->fetchAll();
    } catch (Throwable $e) { return []; }
}

function pm_get_method(int $id): ?array {
    $st = db()->prepare('SELECT * FROM payment_methods WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $m = $st->fetch();
    return $m ?: null;
}

function pm_get_active_method(int $id): ?array {
    $st = db()->prepare('SELECT * FROM payment_methods WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([$id]);
    $m = $st->fetch();
    return $m ?: null;
}

function pm_method_fields(int $methodId): array {
    $st = db()->prepare('SELECT * FROM payment_method_fields WHERE payment_method_id = ? ORDER BY display_order ASC, id ASC');
    $st->execute([$methodId]);
    return $st->fetchAll();
}

function pm_method_with_fields(int $id): ?array {
    $m = pm_get_method($id);
    if (!$m) return null;
    $m['fields'] = pm_method_fields($id);
    return $m;
}

// Snapshot stored on the deposit at submit time: preserves history
// even if the admin later edits/deactivates/deletes the method.
function pm_build_snapshot(array $method, array $fields): array {
    $out = ['method_name' => $method['name'] ?? '', 'fields' => []];
    foreach ($fields as $f) {
        $out['fields'][] = ['label' => (string)($f['label'] ?? ''), 'value' => (string)($f['value'] ?? '')];
    }
    return $out;
}

function pm_allowed_display_types(): array {
    return ['single_line' => 'Single line', 'multiline' => 'Multiline', 'url' => 'URL', 'optional' => 'Optional'];
}
