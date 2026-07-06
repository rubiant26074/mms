<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ $company->company_name ?: 'MMS System' }}</title>
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
        .login-body { padding: 30px; }
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
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo Perusahaan" class="img-fluid" style="max-height: 100px; max-width: 200px; margin-bottom: 10px;">
            @else
                <i class="bi bi-gear-wide-connected logo-icon"></i>
            @endif
            <h4 class="fw-bold mt-3 mb-1" style="color: #2c3e50; font-size: 1.25rem;">{{ $company->company_name ?: 'MMS System' }}</h4>
            <small class="text-muted d-block">Manufacturing Management System</small>
            <small class="text-muted d-block">Software version: v1.0.0</small>
        </div>

        <div class="login-body">
            @if($errors->any())
                <div class="alert alert-danger d-flex align-items-center small py-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <form action="{{ route('login.store') }}" method="POST">
                @csrf
                <div class="form-floating mb-3">
                    <input type="text" name="username" class="form-control" id="username" placeholder="Username" value="{{ old('username') }}" required autofocus>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
