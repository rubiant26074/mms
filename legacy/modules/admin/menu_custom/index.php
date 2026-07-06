<?php
// modules/admin/menu_custom/index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

render_header("Custom Menu Manager");

if (!has_permission('admin_menu_manage') && $_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger m-4'>Akses Ditolak. Anda bukan Super Administrator.</div>";
    render_footer();
    exit;
}

function ensure_menu_custom_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) NOT NULL,
        setting_value VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_custom_menus (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        menu_slug VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_user_menu (user_id, menu_slug),
        KEY idx_ucm_user (user_id),
        CONSTRAINT fk_user_custom_menus_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $idCol = $pdo->query("SHOW COLUMNS FROM user_custom_menus LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    if (!$idCol) {
        throw new Exception("Struktur tabel user_custom_menus tidak valid: kolom id tidak ditemukan.");
    }
    $extra = strtolower((string)($idCol['Extra'] ?? ''));
    if (strpos($extra, 'auto_increment') === false) {
        $pdo->exec("ALTER TABLE user_custom_menus MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    }

    $idx = $pdo->query("SHOW INDEX FROM user_custom_menus WHERE Key_name = 'uniq_user_menu'")->fetch(PDO::FETCH_ASSOC);
    if (!$idx) {
        $pdo->exec("DELETE t1 FROM user_custom_menus t1
                    INNER JOIN user_custom_menus t2
                        ON t1.user_id = t2.user_id
                       AND t1.menu_slug = t2.menu_slug
                       AND t1.id > t2.id");
        $pdo->exec("ALTER TABLE user_custom_menus ADD UNIQUE KEY uniq_user_menu (user_id, menu_slug)");
    }
}

$menu_tree = [
    ['type' => 'single', 'slug' => 'dashboard', 'legacy' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    ['type' => 'group', 'legacy' => 'sales', 'label' => 'Sales & Marketing', 'icon' => 'bi-currency-dollar', 'children' => [
        ['slug' => 'sales-customers', 'label' => 'Master Customer'],
        ['slug' => 'sales-quote', 'label' => 'Penawaran (Quote)'],
        ['slug' => 'sales-so', 'label' => 'Sales Order'],
    ]],
    ['type' => 'group', 'legacy' => 'eng', 'label' => 'Engineering', 'icon' => 'bi-tools', 'children' => [
        ['slug' => 'eng-items', 'label' => 'Master Barang'],
        ['slug' => 'eng-bom', 'label' => 'BOM'],
        ['slug' => 'eng-partlist', 'label' => 'Partlist & Drawing'],
    ]],
    ['type' => 'group', 'legacy' => 'ppic', 'label' => 'PPIC', 'icon' => 'bi-calendar-range', 'children' => [
        ['slug' => 'ppic-spk', 'label' => 'SPK Produksi'],
        ['slug' => 'ppic-mps', 'label' => 'Jadwal (MPS)'],
        ['slug' => 'ppic-pr', 'label' => 'Purchase Req (PR)'],
        ['slug' => 'ppic-inventory', 'label' => 'Inventory'],
    ]],
    ['type' => 'group', 'legacy' => 'purch', 'label' => 'Purchasing', 'icon' => 'bi-bag-check', 'children' => [
        ['slug' => 'purch-po', 'label' => 'Purchase Order'],
        ['slug' => 'purch-vendor', 'label' => 'Vendor List'],
    ]],
    ['type' => 'group', 'legacy' => 'prod', 'label' => 'Produksi', 'icon' => 'bi-gear-wide', 'children' => [
        ['slug' => 'prod-task', 'label' => 'Task Assignment'],
        ['slug' => 'prod-scan', 'label' => 'Operator Scan'],
        ['slug' => 'prod-report', 'label' => 'Laporan Harian'],
    ]],
    ['type' => 'single', 'slug' => 'prod-operator', 'legacy' => 'operator', 'label' => 'Operator Panel', 'icon' => 'bi-phone'],
    ['type' => 'group', 'legacy' => 'qc', 'label' => 'Quality Control', 'icon' => 'bi-patch-check', 'children' => [
        ['slug' => 'qc-incoming', 'label' => 'QC Incoming'],
        ['slug' => 'qc-production', 'label' => 'QC Production'],
        ['slug' => 'qc-ncr', 'label' => 'NCR Form'],
    ]],
    ['type' => 'group', 'legacy' => 'whse', 'label' => 'Warehouse', 'icon' => 'bi-house-door', 'children' => [
        ['slug' => 'whse-receive', 'label' => 'Penerimaan (GR)'],
        ['slug' => 'whse-issue', 'label' => 'Material Issue'],
        ['slug' => 'whse-return', 'label' => 'Return Material'],
        ['slug' => 'whse-sj', 'label' => 'Surat Jalan (SJ)'],
    ]],
    ['type' => 'group', 'legacy' => 'fin', 'label' => 'Finance', 'icon' => 'bi-cash-coin', 'children' => [
        ['slug' => 'fin-ar', 'label' => 'AR / Invoice'],
        ['slug' => 'fin-ap', 'label' => 'AP / Payment'],
        ['slug' => 'fin-cash', 'label' => 'Cash / Chasier'],
        ['slug' => 'fin-tax', 'label' => 'Perpajakan'],
    ]],
    ['type' => 'group', 'legacy' => 'acc', 'label' => 'Accounting', 'icon' => 'bi-journal-bookmark', 'children' => [
        ['slug' => 'acc-coa', 'label' => 'COA'],
        ['slug' => 'acc-journal', 'label' => 'Jurnal Umum'],
        ['slug' => 'acc-ledger', 'label' => 'Buku Besar'],
        ['slug' => 'acc-assets', 'label' => 'Fixed Assets'],
        ['slug' => 'acc-report', 'label' => 'Laporan Keuangan'],
    ]],
    ['type' => 'group', 'legacy' => 'hrd', 'label' => 'HRD', 'icon' => 'bi-people-fill', 'children' => [
        ['slug' => 'hrd-attendance', 'label' => 'Absensi'],
        ['slug' => 'hrd-payroll', 'label' => 'Payroll'],
        ['slug' => 'hrd-employees', 'label' => 'Karyawan'],
    ]],
    ['type' => 'group', 'legacy' => 'exec', 'label' => 'Executive', 'icon' => 'bi-briefcase-fill', 'children' => [
        ['slug' => 'exec-kpi', 'label' => 'KPI Dashboard'],
        ['slug' => 'exec-logs', 'label' => 'System Logs'],
    ]],
    ['type' => 'group', 'legacy' => 'tv', 'label' => 'TV Monitoring', 'icon' => 'bi-tv', 'children' => [
        ['slug' => 'tv-exec', 'label' => 'TV Executive'],
        ['slug' => 'tv-lobby', 'label' => 'TV Lobby'],
        ['slug' => 'tv-prod', 'label' => 'TV Produksi'],
    ]],
];

$menu_permission_map = [
    'dashboard' => 'dashboard_view',
    'sales' => 'sales_view',
    'eng' => 'eng_view',
    'ppic' => 'ppic_view',
    'purch' => 'purch_view',
    'prod' => 'prod_view',
    'operator' => 'prod_operator_access',
    'qc' => 'qc_view',
    'whse' => 'whse_view',
    'fin' => 'fin_view',
    'acc' => 'acc_view',
    'hrd' => 'hrd_view',
    'exec' => 'owner_view',
    'tv' => 'dashboard_view',
];

try {
    ensure_menu_custom_schema($pdo);

    if (isset($_POST['save_mode'])) {
        $mode = $_POST['menu_mode'];
        $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('menu_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?";
        $pdo->prepare($sql)->execute([$mode, $mode]);
        echo "<script>alert('Mode Menu Berhasil Diubah!'); window.location='index.php?page=admin-menu';</script>";
    }

    if (isset($_POST['save_user_menu'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $menus = $_POST['menus'] ?? [];
        $menus = array_values(array_unique(array_filter(array_map('trim', $menus))));

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM user_custom_menus WHERE user_id = ?")->execute([$uid]);
        $stmt = $pdo->prepare("INSERT INTO user_custom_menus (user_id, menu_slug) VALUES (?, ?)");
        foreach ($menus as $slug) {
            $stmt->execute([$uid, $slug]);
        }
        $pdo->commit();
        echo "<script>alert('Akses menu tersimpan!'); window.location='index.php?page=admin-menu&edit_user=$uid';</script>";
    }

    if (isset($_POST['clone_user_menu'])) {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        $source_uid = (int)($_POST['source_user_id'] ?? 0);

        if ($target_uid <= 0 || $source_uid <= 0) {
            throw new Exception("Pilih user sumber clone terlebih dahulu.");
        }
        if ($target_uid === $source_uid) {
            throw new Exception("User sumber clone tidak boleh sama dengan user target.");
        }

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM user_custom_menus WHERE user_id = ?")->execute([$target_uid]);

        $stmt_src = $pdo->prepare("SELECT menu_slug FROM user_custom_menus WHERE user_id = ?");
        $stmt_src->execute([$source_uid]);
        $src_slugs = $stmt_src->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($src_slugs)) {
            $stmt_ins = $pdo->prepare("INSERT INTO user_custom_menus (user_id, menu_slug) VALUES (?, ?)");
            foreach (array_unique($src_slugs) as $slug) {
                $stmt_ins->execute([$target_uid, $slug]);
            }
        }

        $pdo->commit();
        echo "<script>alert('Akses menu berhasil di-clone.'); window.location='index.php?page=admin-menu&edit_user=$target_uid';</script>";
    }

    if (isset($_POST['reset_role_menu'])) {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        if ($target_uid <= 0) {
            throw new Exception("User target tidak valid.");
        }

        $stmt_role = $pdo->prepare("SELECT r.role_slug
                                    FROM users u
                                    JOIN roles r ON r.id = u.role_id
                                    WHERE u.id = ?
                                    LIMIT 1");
        $stmt_role->execute([$target_uid]);
        $target_role_slug = $stmt_role->fetchColumn();
        if (!$target_role_slug) {
            throw new Exception("Role user target tidak ditemukan.");
        }

        $default_slugs = [];
        if ($target_role_slug === 'admin') {
            foreach ($menu_tree as $node) {
                if ($node['type'] === 'single') {
                    $default_slugs[] = $node['slug'];
                } else {
                    foreach ($node['children'] as $child) {
                        $default_slugs[] = $child['slug'];
                    }
                }
            }
        } else {
            $stmt_role_perms = $pdo->prepare("SELECT p.permission_slug
                                              FROM users u
                                              JOIN roles r ON r.id = u.role_id
                                              JOIN role_permissions rp ON rp.role_id = r.id
                                              JOIN permissions p ON p.id = rp.permission_id
                                              WHERE u.id = ?");
            $stmt_role_perms->execute([$target_uid]);
            $role_perms = $stmt_role_perms->fetchAll(PDO::FETCH_COLUMN);

            foreach ($menu_tree as $node) {
                $legacy = $node['legacy'] ?? null;
                $perm = ($legacy && isset($menu_permission_map[$legacy])) ? $menu_permission_map[$legacy] : null;
                if (!$perm || !in_array($perm, $role_perms, true)) {
                    continue;
                }
                if ($node['type'] === 'single') {
                    $default_slugs[] = $node['slug'];
                } else {
                    foreach ($node['children'] as $child) {
                        $default_slugs[] = $child['slug'];
                    }
                }
            }
        }
        $default_slugs = array_values(array_unique($default_slugs));

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM user_custom_menus WHERE user_id = ?")->execute([$target_uid]);
        if (!empty($default_slugs)) {
            $stmt_ins = $pdo->prepare("INSERT INTO user_custom_menus (user_id, menu_slug) VALUES (?, ?)");
            foreach ($default_slugs as $slug) {
                $stmt_ins->execute([$target_uid, $slug]);
            }
        }
        $pdo->commit();
        echo "<script>alert('Akses menu di-reset ke bawaan role user target.'); window.location='index.php?page=admin-menu&edit_user=$target_uid';</script>";
    }

    $chk = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($chk->rowCount() == 0) {
        throw new Exception("Tabel database belum lengkap. Silakan jalankan script <b>setup_menu_db.php</b> terlebih dahulu.");
    }

    $current_mode = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='menu_mode'")->fetchColumn();
    if (!$current_mode) $current_mode = 'role';

    $users = $pdo->query("SELECT u.id, u.fullname, u.username, r.role_name 
                          FROM users u 
                          LEFT JOIN roles r ON u.role_id = r.id 
                          ORDER BY u.fullname ASC")->fetchAll();

    $edit_user_id = isset($_GET['edit_user']) ? (int)$_GET['edit_user'] : null;
    $user_access = [];
    $user_role_perms = [];
    if ($edit_user_id) {
        $stmt = $pdo->prepare("SELECT menu_slug FROM user_custom_menus WHERE user_id = ?");
        $stmt->execute([$edit_user_id]);
        $user_access = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt_perm_user = $pdo->prepare("SELECT p.permission_slug
                                         FROM users u
                                         JOIN roles r ON r.id = u.role_id
                                         JOIN role_permissions rp ON rp.role_id = r.id
                                         JOIN permissions p ON p.id = rp.permission_id
                                         WHERE u.id = ?");
        $stmt_perm_user->execute([$edit_user_id]);
        $user_role_perms = $stmt_perm_user->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger m-4'><h5>Error Database:</h5>".$e->getMessage()."<br><br>Saran: Jalankan script setup database menu.</div>";
    render_footer();
    exit;
}

$is_checked = function($slug, $legacy = null) use ($user_access) {
    if (in_array($slug, $user_access, true)) return true;
    if (!empty($legacy) && in_array($legacy, $user_access, true)) return true;
    return false;
};
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold">1. Mode Tampilan Menu</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Pilih Mode:</label>
                        <select name="menu_mode" class="form-select bg-light fw-bold">
                            <option value="role" <?= $current_mode == 'role' ? 'selected' : '' ?>>Original (Sesuai Role)</option>
                            <option value="custom" <?= $current_mode == 'custom' ? 'selected' : '' ?>>Custom (Manual Per Menu)</option>
                        </select>
                        <div class="form-text mt-2">
                            Mode <strong>Custom</strong>: centang akses per submenu/menu.
                        </div>
                    </div>
                    <button type="submit" name="save_mode" class="btn btn-primary w-100">Simpan Mode</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light fw-bold">2. Pilih User</div>
            <div class="list-group list-group-flush" style="max-height: 420px; overflow-y: auto;">
                <?php foreach($users as $u): $active = ($edit_user_id == $u['id']) ? 'active' : ''; ?>
                    <a href="index.php?page=admin-menu&edit_user=<?= $u['id'] ?>" class="list-group-item list-group-item-action <?= $active ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($u['fullname']) ?></strong>
                            <span class="badge bg-secondary rounded-pill" style="font-size:0.6rem"><?= $u['role_name'] ?></span>
                        </div>
                        <small class="text-muted small">@<?= htmlspecialchars($u['username']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php if($edit_user_id): 
            $user_name = '';
            foreach($users as $u){ if((int)$u['id']===$edit_user_id) $user_name=$u['fullname']; }
        ?>
        <div class="card shadow-sm border-primary h-100">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-grid-fill me-2"></i> Akses Menu: <?= htmlspecialchars($user_name) ?></span>
            </div>
            <div class="card-body">
                <?php if($current_mode != 'custom'): ?>
                    <div class="alert alert-warning border-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Saat ini sistem memakai mode <strong>Original (Role Based)</strong>. 
                        Pengaturan checklist tetap disimpan, namun aktif setelah mode diubah ke <strong>Custom</strong>.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="target_user_id" value="<?= $edit_user_id ?>">
                    <div class="border rounded p-3 bg-light mb-3">
                        <div class="fw-bold mb-2"><i class="bi bi-diagram-2 me-1"></i> Clone Akses Menu</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label small mb-1">Ambil akses dari user lain</label>
                                <select name="source_user_id" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih User Sumber --</option>
                                    <?php foreach($users as $ux): if ((int)$ux['id'] === (int)$edit_user_id) continue; ?>
                                        <option value="<?= (int)$ux['id'] ?>"><?= htmlspecialchars($ux['fullname']) ?> (@<?= htmlspecialchars($ux['username']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-grid">
                                <button type="submit" name="clone_user_menu" class="btn btn-sm btn-outline-primary" onclick="return confirm('Clone akses akan menimpa akses menu user ini. Lanjutkan?')">
                                    <i class="bi bi-arrow-down-up me-1"></i> Clone Akses
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <form method="POST" class="mb-3">
                    <input type="hidden" name="target_user_id" value="<?= $edit_user_id ?>">
                    <button type="submit" name="reset_role_menu" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Reset akses ke bawaan role akan menimpa checklist saat ini. Lanjutkan?')">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset ke Bawaan Role
                    </button>
                </form>

                <form method="POST">
                    <input type="hidden" name="user_id" value="<?= $edit_user_id ?>">
                    <p class="text-muted mb-3">
                        Menu induk bisa dibuka untuk memilih sub menu. Menu tanpa sub menu langsung tersedia sebagai checkbox.
                    </p>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAllMenus(true)">
                            <i class="bi bi-check2-square me-1"></i> Check Semua
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleAllMenus(false)">
                            <i class="bi bi-square me-1"></i> Uncheck Semua
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="expandAllMenus()">
                            <i class="bi bi-arrows-expand me-1"></i> Expand all
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="collapseAllMenus()">
                            <i class="bi bi-arrows-collapse me-1"></i> Collapse all
                        </button>
                    </div>

                    <div class="accordion" id="menuAccordion">
                        <?php $idx = 0; foreach ($menu_tree as $node): $idx++; ?>
                            <?php if ($node['type'] === 'group'): ?>
                                <?php
                                $checked_count = 0;
                                foreach ($node['children'] as $child) {
                                    if ($is_checked($child['slug'], $node['legacy'])) $checked_count++;
                                }
                                $all_count = count($node['children']);
                                $req_perm = (!empty($node['legacy']) && isset($menu_permission_map[$node['legacy']])) ? $menu_permission_map[$node['legacy']] : null;
                                $has_req_perm = (!$req_perm || in_array($req_perm, $user_role_perms, true));
                                ?>
                                <div class="accordion-item mb-2 border rounded" data-group-index="<?= $idx ?>">
                                    <h2 class="accordion-header" id="heading<?= $idx ?>">
                                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>" aria-expanded="false" aria-controls="collapse<?= $idx ?>">
                                            <i class="bi <?= $node['icon'] ?> me-2"></i>
                                            <span class="fw-bold"><?= htmlspecialchars($node['label']) ?></span>
                                            <span class="badge bg-secondary ms-2" id="groupBadge<?= $idx ?>"><?= $checked_count ?>/<?= $all_count ?></span>
                                        </button>
                                    </h2>
                                    <div id="collapse<?= $idx ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $idx ?>" data-bs-parent="#menuAccordion">
                                        <div class="accordion-body py-2">
                                            <?php if ($req_perm && !$has_req_perm): ?>
                                                <div class="text-danger small mb-2">
                                                    <i class="bi bi-exclamation-triangle me-1"></i> Butuh izin: <span class="fw-semibold"><?= htmlspecialchars($req_perm) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-end gap-2 mb-2">
                                                <button type="button" class="btn btn-xs btn-outline-success py-0 px-2" onclick="setGroupChecks(<?= $idx ?>, true)">Check all</button>
                                                <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2" onclick="setGroupChecks(<?= $idx ?>, false)">Uncheck</button>
                                            </div>
                                            <div class="row g-2">
                                                <?php foreach ($node['children'] as $child): 
                                                    $checked = $is_checked($child['slug'], $node['legacy']) ? 'checked' : '';
                                                ?>
                                                    <div class="col-md-6">
                                                        <div class="form-check border rounded p-2 bg-light">
                                                            <input class="form-check-input me-2 menu-checkbox menu-group-<?= $idx ?>" data-group="<?= $idx ?>" type="checkbox" name="menus[]" value="<?= htmlspecialchars($child['slug']) ?>" id="menu_<?= $idx ?>_<?= htmlspecialchars($child['slug']) ?>" <?= $checked ?>>
                                                            <label class="form-check-label small fw-bold" for="menu_<?= $idx ?>_<?= htmlspecialchars($child['slug']) ?>">
                                                                <?= htmlspecialchars($child['label']) ?>
                                                            </label>
                                                            <div class="text-muted small"><?= htmlspecialchars($child['slug']) ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php
                                $checked = $is_checked($node['slug'], $node['legacy']) ? 'checked' : '';
                                $req_perm = null;
                                if (!empty($node['legacy']) && isset($menu_permission_map[$node['legacy']])) {
                                    $req_perm = $menu_permission_map[$node['legacy']];
                                } elseif (!empty($node['slug']) && isset($menu_permission_map[$node['slug']])) {
                                    $req_perm = $menu_permission_map[$node['slug']];
                                }
                                $has_req_perm = (!$req_perm || in_array($req_perm, $user_role_perms, true));
                                ?>
                                <div class="form-check border rounded p-3 mb-2 bg-light">
                                    <input class="form-check-input menu-checkbox" type="checkbox" name="menus[]" value="<?= htmlspecialchars($node['slug']) ?>" id="menu_single_<?= $idx ?>" <?= $checked ?>>
                                    <label class="form-check-label fw-bold" for="menu_single_<?= $idx ?>">
                                        <i class="bi <?= $node['icon'] ?> me-1"></i> <?= htmlspecialchars($node['label']) ?>
                                    </label>
                                    <div class="text-muted small"><?= htmlspecialchars($node['slug']) ?></div>
                                    <?php if ($req_perm && !$has_req_perm): ?>
                                        <div class="text-danger small mt-1">
                                            <i class="bi bi-exclamation-triangle me-1"></i> Butuh izin: <span class="fw-semibold"><?= htmlspecialchars($req_perm) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-secondary mt-3 small">
                        <i class="bi bi-info-circle"></i> Menu <strong>Administrator</strong> tidak diatur dari halaman ini untuk menjaga keamanan sistem.
                    </div>

                    <div class="text-end">
                        <button type="submit" name="save_user_menu" class="btn btn-success px-5 fw-bold shadow">
                            <i class="bi bi-save me-2"></i> Simpan Akses Menu
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
            <div class="alert alert-secondary text-center py-5 h-100 d-flex flex-column justify-content-center align-items-center border-dashed">
                <i class="bi bi-person-gear display-1 text-secondary mb-3 opacity-50"></i>
                <h5>Pilih User di sebelah kiri</h5>
                <p class="text-muted">Klik nama user untuk mulai mengatur akses menu custom.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function setGroupChecks(groupIndex, checked) {
    document.querySelectorAll('.menu-group-' + groupIndex).forEach(cb => { cb.checked = checked; });
    updateGroupBadge(groupIndex);
}

function toggleAllMenus(checked) {
    document.querySelectorAll('.menu-checkbox').forEach(cb => { cb.checked = checked; });
    updateAllGroupBadges();
}

function expandAllMenus() {
    document.querySelectorAll('#menuAccordion .accordion-collapse').forEach(el => {
        const instance = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        instance.show();
    });
}

function collapseAllMenus() {
    document.querySelectorAll('#menuAccordion .accordion-collapse').forEach(el => {
        const instance = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        instance.hide();
    });
}

function updateGroupBadge(groupIndex) {
    const boxes = Array.from(document.querySelectorAll('.menu-group-' + groupIndex));
    const badge = document.getElementById('groupBadge' + groupIndex);
    if (!badge) return;
    const total = boxes.length;
    const checked = boxes.filter(x => x.checked).length;
    badge.textContent = checked + '/' + total;
}

function updateAllGroupBadges() {
    document.querySelectorAll('[data-group-index]').forEach(el => {
        const idx = el.getAttribute('data-group-index');
        updateGroupBadge(idx);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.menu-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const g = this.getAttribute('data-group');
            if (g) updateGroupBadge(g);
        });
    });
    updateAllGroupBadges();
});
</script>

<?php render_footer(); ?>
