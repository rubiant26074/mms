<?php
// modules/auth/login.php

// 1. Cek Koneksi Database
if (!isset($pdo)) { 
    // Coba load config manual jika diakses langsung
    if (file_exists('../../config/database.php')) {
        require_once '../../config/database.php';
        session_start();
    } elseif (file_exists('config/database.php')) {
        require_once 'config/database.php';
    } else {
        die("Koneksi database tidak ditemukan.");
    }
}

// 2. Ambil Identitas Perusahaan
$company_name = "MMS System"; // Default
$logo_html = '<i class="bi bi-gear-wide-connected logo-icon"></i>'; // Default Icon

try {
    $stmt_comp = $pdo->query("SELECT * FROM company_profile WHERE id = 1");
    $comp = $stmt_comp->fetch(PDO::FETCH_ASSOC);
    
    if ($comp) {
        $company_name = !empty($comp['company_name']) ? $comp['company_name'] : $company_name;

        $logo_url = function_exists('mms_asset_url')
            ? mms_asset_url((string)($comp['logo_path'] ?? ''), true)
            : (string)($comp['logo_path'] ?? '');

        if (!empty($logo_url)) {
            // Tampilkan Logo Gambar
            $logo_html = '<img src="'.$logo_url.'" alt="Logo Perusahaan" class="img-fluid" style="max-height: 100px; max-width: 200px; margin-bottom: 10px;">';
        }
    }
} catch (Exception $e) {
    // Ignore error jika tabel belum ada
}

$error = '';

// 3. Proses Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars(stripslashes(trim($_POST['username'])));
    $password = $_POST['password'];

    try {
        $sql = "SELECT u.*, r.role_slug, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.username = :username LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role_slug'];
            $_SESSION['user_role'] = $user['role_slug']; // Backward compatibility untuk helper lama
            $_SESSION['role_name'] = $user['role_name'];

            // Permissions
            $stmt_perm = $pdo->prepare("SELECT p.permission_slug FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id = ?");
            $stmt_perm->execute([$user['role_id']]);
            $_SESSION['permissions'] = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);

            header("Location: index.php");
            exit();
        } else {
            $error = "Username atau Password salah!";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= $company_name ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }
        .login-header {
            background-color: #ffffff;
            padding: 40px 30px 20px 30px;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
        }
        .login-body {
            padding: 30px;
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            font-weight: 600;
            padding: 12px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .logo-icon {
            font-size: 4rem;
            color: #2c3e50;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <!-- LOGO DARI DATABASE -->
            <?= $logo_html ?>
            
            <!-- NAMA PERUSAHAAN DARI DATABASE -->
            <h4 class="fw-bold mt-3 mb-1" style="color: #2c3e50; font-size: 1.25rem;"><?= $company_name ?></h4>
            <small class="text-muted d-block">Manufacturing Management System</small>
            <small class="text-muted d-block">Software version: v1.0.0</small>
        </div>

        <div class="login-body">
            <?php if($error): ?>
                <div class="alert alert-danger d-flex align-items-center small py-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-floating mb-3">
                    <input type="text" name="username" class="form-control" id="username" placeholder="Username" required autofocus>
                    <label for="username">Username</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Masuk Sistem</button>
                </div>
            </form>
            
            <div class="mt-4 text-center">
                <small class="text-muted">Gunakan akun Anda untuk melanjutkan.</small>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
