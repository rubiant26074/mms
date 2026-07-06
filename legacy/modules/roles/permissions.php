<?php
// modules/roles/permissions.php

// 1. Ambil Role yang sedang diedit (dari parameter URL atau default ke role pertama selain admin)
$selected_role_id = isset($_GET['role_id']) ? $_GET['role_id'] : '';

// Jika belum ada role dipilih, ambil role pertama yang bukan admin (biasanya Manager)
if (!$selected_role_id) {
    $first_role = $pdo->query("SELECT id FROM roles WHERE role_slug != 'admin' ORDER BY id ASC LIMIT 1")->fetch();
    if ($first_role) {
        $selected_role_id = $first_role['id'];
    }
}

// 2. PROSES SIMPAN DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_id = $_POST['role_id'];
    $perms   = isset($_POST['permissions']) ? $_POST['permissions'] : []; // Array ID permission

    try {
        $pdo->beginTransaction();

        // Hapus semua permission lama untuk role ini
        $stmt_del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt_del->execute([$role_id]);

        // Masukkan permission baru yang dicentang
        if (!empty($perms)) {
            $sql_ins = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt_ins = $pdo->prepare($sql_ins);
            foreach ($perms as $perm_id) {
                $stmt_ins->execute([$role_id, $perm_id]);
            }
        }

        $pdo->commit();
        echo "<script>alert('Hak akses berhasil diperbarui!'); window.location='index.php?page=role-permissions&role_id=$role_id';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Gagal menyimpan: " . $e->getMessage() . "');</script>";
    }
}

// 3. AMBIL DATA UNTUK TAMPILAN
// Daftar semua roles untuk dropdown
$roles = $pdo->query("SELECT * FROM roles WHERE role_slug != 'admin' ORDER BY id ASC")->fetchAll();

// Daftar semua permissions yang tersedia
$all_permissions = $pdo->query("SELECT * FROM permissions ORDER BY id ASC")->fetchAll();

// Ambil permission yang SUDAH dimiliki role terpilih (untuk auto-check checkbox)
$current_permissions = [];
if ($selected_role_id) {
    $stmt_curr = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt_curr->execute([$selected_role_id]);
    $current_permissions = $stmt_curr->fetchAll(PDO::FETCH_COLUMN);
}

render_header("Kontrol Hak Akses (RBAC)");
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-key-fill"></i> Kontrol Hak Akses (RBAC)</h3>
        <p class="text-muted">Atur fitur apa saja yang bisa diakses oleh setiap role/jabatan.</p>
    </div>
</div>

<div class="row">
    <!-- KOLOM KIRI: PILIH ROLE -->
    <div class="col-md-3 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                Pilih Role
            </div>
            <div class="list-group list-group-flush">
                <?php foreach($roles as $r): 
                    $active = ($selected_role_id == $r['id']) ? 'active fw-bold' : '';
                ?>
                <a href="index.php?page=role-permissions&role_id=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $active ?>">
                    <?= clean($r['role_name']) ?>
                    <i class="bi bi-chevron-right small"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="alert alert-info mt-3 small">
            <i class="bi bi-info-circle"></i> Role <b>Administrator</b> memiliki akses penuh secara otomatis dan tidak perlu dikonfigurasi di sini.
        </div>
    </div>

    <!-- KOLOM KANAN: CHECKBOX PERMISSION -->
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold">Setting Izin Akses</h5>
                <?php if($selected_role_id): ?>
                    <span class="badge bg-primary">Role ID: <?= $selected_role_id ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                
                <?php if($selected_role_id): ?>
                
                <!-- FILTER SEARCH BOX (BARU) -->
                <div class="mb-3 position-relative">
                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="permissionSearch" class="form-control ps-5" placeholder="Ketik untuk mencari izin (misal: 'approve', 'edit', 'po')...">
                </div>

                <form method="POST">
                    <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">

                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover align-middle table-sm" id="permTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="50" class="text-center"><input type="checkbox" id="checkAll"></th>
                                    <th>Nama Izin (Permission)</th>
                                    <th>Kode Slug</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($all_permissions as $p): 
                                    $checked = in_array($p['id'], $current_permissions) ? 'checked' : '';
                                    
                                    // Bikin Badge warna-warni biar gampang dibaca
                                    $slug = $p['permission_slug'];
                                    $color = 'secondary';
                                    if(strpos($slug, 'view')) $color = 'info text-dark';
                                    if(strpos($slug, 'create') || strpos($slug, 'input') || strpos($slug, 'manage')) $color = 'success';
                                    if(strpos($slug, 'edit')) $color = 'warning text-dark';
                                    if(strpos($slug, 'delete')) $color = 'danger';
                                    if(strpos($slug, 'approve')) $color = 'primary';
                                ?>
                                <tr class="perm-row">
                                    <td class="text-center">
                                        <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" class="form-check-input perm-check" <?= $checked ?>>
                                    </td>
                                    <td>
                                        <span class="fw-bold perm-name"><?= clean($p['permission_name']) ?></span>
                                    </td>
                                    <td>
                                        <code class="text-<?= $color ?> perm-slug"><?= $slug ?></code>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pesan jika tidak ditemukan -->
                        <div id="noResults" class="text-center py-4 text-muted d-none">
                            <i class="bi bi-emoji-frown display-6"></i>
                            <p class="mt-2">Tidak ada izin yang cocok dengan pencarian.</p>
                        </div>
                    </div>

                    <div class="mt-3 border-top pt-3 text-end sticky-bottom bg-white">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-arrow-left-circle display-4"></i>
                        <p class="mt-3">Silakan pilih Role di sebelah kiri untuk mengatur akses.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
    // 1. Script Check All
    document.getElementById('checkAll')?.addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.perm-check');
        // Hanya check yang sedang tampil (visible)
        checkboxes.forEach(function(cb) {
            if(cb.closest('tr').style.display !== 'none') {
                cb.checked = this.checked;
            }
        }.bind(this));
    });

    // 2. Script Filter Pencarian (Realtime)
    document.getElementById('permissionSearch')?.addEventListener('keyup', function() {
        var value = this.value.toLowerCase();
        var rows = document.querySelectorAll('.perm-row');
        var visibleCount = 0;

        rows.forEach(function(row) {
            var name = row.querySelector('.perm-name').textContent.toLowerCase();
            var slug = row.querySelector('.perm-slug').textContent.toLowerCase();
            
            // Cek apakah nama atau slug mengandung kata kunci
            if (name.indexOf(value) > -1 || slug.indexOf(value) > -1) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Tampilkan pesan jika tidak ada hasil
        var noRes = document.getElementById('noResults');
        if (visibleCount === 0) {
            noRes.classList.remove('d-none');
        } else {
            noRes.classList.add('d-none');
        }
    });
</script>

<?php render_footer(); ?>