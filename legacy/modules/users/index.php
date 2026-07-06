<?php
// modules/users/index.php
render_header("Manajemen User");
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-people"></i> Manajemen User</h3>
        <p class="text-muted">Kelola akun pengguna dan hak akses.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=roles" class="btn btn-outline-dark me-2">
            <i class="bi bi-shield-lock"></i> Kelola Role
        </a>
        <a href="index.php?page=users&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah User
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>User Info</th>
                        <th>Role / Jabatan</th>
                        <th class="text-center">TTD</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // JOIN users dengan roles untuk mendapatkan nama role dan slug
                    $sql = "SELECT u.*, r.role_name, r.role_slug 
                            FROM users u 
                            LEFT JOIN roles r ON u.role_id = r.id 
                            ORDER BY u.id DESC";
                    $stmt = $pdo->query($sql);
                    
                    while ($user = $stmt->fetch()):
                        $slug = $user['role_slug'] ?? ''; 
                        
                        // Warna badge role
                        $badge = match($slug) {
                            'admin' => 'bg-dark',
                            'manager' => 'bg-primary',
                            'ppic' => 'bg-warning text-dark',
                            'staff' => 'bg-secondary',
                            default => 'bg-info', 
                        };

                        // Cek keberadaan file Tanda Tangan
                        $has_sig = (!empty($user['signature_path']) && file_exists($user['signature_path']));
                    ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <div class="fw-bold"><?= clean($user['fullname']) ?></div>
                            <small class="text-muted">@<?= clean($user['username']) ?></small>
                        </td>
                        <td>
                            <span class="badge <?= $badge ?>"><?= clean($user['role_name'] ?? 'Tanpa Role') ?></span>
                        </td>
                        <td class="text-center">
                            <?php if($has_sig): ?>
                                <span class="badge bg-success" title="Tanda tangan tersedia"><i class="bi bi-pen-fill"></i> OK</span>
                            <?php else: ?>
                                <span class="badge bg-secondary text-white-50" title="Belum upload tanda tangan"><i class="bi bi-dash-circle"></i></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="index.php?page=users&action=edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-warning text-white" title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </a>
                            
                            <!-- Proteksi: Admin Utama & Diri Sendiri tidak boleh dihapus -->
                            <?php if($slug !== 'admin' && $user['id'] !== $_SESSION['user_id']): ?>
                            <a href="index.php?page=users&action=delete&id=<?= $user['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Yakin ingin menghapus user ini?');" title="Hapus User">
                               <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>