<?php
// modules/roles/form.php

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$data = ['role_name'=>'', 'role_slug'=>'', 'description'=>''];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['role_name']);
    $slug = strtolower(str_replace(' ', '_', clean($_POST['role_slug']))); // Auto format slug
    $desc = clean($_POST['description']);

    if ($is_edit) {
        $sql = "UPDATE roles SET role_name=?, role_slug=?, description=? WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $slug, $desc, $id]);
    } else {
        $sql = "INSERT INTO roles (role_name, role_slug, description) VALUES (?, ?, ?)";
        $pdo->prepare($sql)->execute([$name, $slug, $desc]);
    }
    
    echo "<script>alert('Role tersimpan!'); window.location='index.php?page=roles';</script>";
    exit;
}

render_header($is_edit ? "Edit Role" : "Tambah Role");
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">Form Role</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>Nama Role (Tampilan)</label>
                        <input type="text" name="role_name" class="form-control" value="<?= $data['role_name'] ?>" placeholder="Contoh: Supervisor QC" required>
                    </div>
                    <div class="mb-3">
                        <label>Role Slug (Kode Unik)</label>
                        <input type="text" name="role_slug" class="form-control" value="<?= $data['role_slug'] ?>" placeholder="Contoh: spv_qc" required>
                        <small class="text-muted">Gunakan huruf kecil, tanpa spasi (ganti dengan _)</small>
                    </div>
                    <div class="mb-3">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control"><?= $data['description'] ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="index.php?page=roles" class="btn btn-light border">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>