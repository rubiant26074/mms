<?php
// modules/roles/index.php
render_header("Manajemen Role");
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-shield-lock"></i> Manajemen Role Access</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=users" class="btn btn-secondary me-2">Kembali ke Users</a>
        <a href="index.php?page=roles&action=create" class="btn btn-primary">+ Tambah Role</a>
    </div>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle"></i> <b>Role Slug</b> digunakan untuk logika sistem (sidebar/akses). Jangan ubah slug 'admin', 'manager', 'ppic', atau 'staff' jika tidak ingin mengubah kodingan.
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Role Name (Tampilan)</th>
                    <th>Slug (Kode Sistem)</th>
                    <th>Deskripsi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
                while ($row = $stmt->fetch()):
                ?>
                <tr>
                    <td><strong><?= clean($row['role_name']) ?></strong></td>
                    <td><code><?= clean($row['role_slug']) ?></code></td>
                    <td><?= clean($row['description']) ?></td>
                    <td>
                        <a href="index.php?page=roles&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-white">Edit</a>
                        <!-- Cegah hapus admin -->
                        <?php if($row['role_slug'] !== 'admin'): ?>
                            <a href="index.php?page=roles&action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus role ini? User dengan role ini akan kehilangan akses.')">Hapus</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>