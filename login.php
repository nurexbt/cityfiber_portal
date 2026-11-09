<?php
// login.php - Portal Operations Login Screen
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $authenticated = false;

        if ($user) {
            // Check password hash OR direct password match
            if (password_verify($password, $user['password']) || $user['password'] === $password || ($username === 'Admin' && $password === 'Team0@247')) {
                $authenticated = true;
            }
        } elseif ($username === 'Admin' && $password === 'Team0@247') {
            // Auto create or authenticate default Admin
            $authenticated = true;
            $user = [
                'id' => 1,
                'username' => 'Admin',
                'full_name' => 'ISP Super Administrator',
                'role' => 'Admin'
            ];
        }

        if ($authenticated) {
            if (($user['status'] ?? 'Active') === 'Suspended') {
                $error = 'Your account has been suspended. Please contact your system administrator.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['role_id'] = $user['role_id'] ?? 1;

                // Update last login
                try {
                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                } catch (Exception $e) {}

                // Load permissions into session
                load_user_permissions($user['role_id'] ?? null, $user['role']);

                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Invalid credentials. Please verify your username and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars($app_settings['company_name']) ?></title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom Theme -->
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('isp_theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('light');
            } else {
                document.documentElement.classList.add('light');
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>
<body class="bg-noc d-flex align-items-center justify-content-center min-vh-100 p-3">

<div class="login-wrapper w-100" style="max-width: 440px;">
    <!-- NOC Header Status -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white border border-slate-700 text-xs font-mono mb-3 shadow-sm">
            <span class="radar-dot"></span>
            <span class="text-emerald-400 font-semibold">ISP OPERATIONS ONLINE</span>
            <span class="text-slate-400">•</span>
            <span class="text-slate-500">Node: 103.166.230.229</span>
        </div>
        <h3 class="fw-bold text-dark tracking-wide mb-1 font-sans">
            <i class="fa-solid fa-network-wired text-cyan-400 me-2"></i><?= htmlspecialchars($app_settings['company_name']) ?>
        </h3>
        <p class="text-slate-400 text-xs mb-0"><?= htmlspecialchars($app_settings['company_tagline']) ?></p>
    </div>

    <!-- Login Card -->
    <div class="card card-noc shadow-sm">
        <div class="card-header border-bottom border-slate-800 py-3 px-4 d-flex align-items-center justify-content-between">
            <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-700">
                <i class="fa-solid fa-shield-halved text-cyan-400 me-2"></i>Authentication
            </span>
            <span class="badge bg-cyan-950 text-cyan-400 border border-cyan-800 font-mono text-xs">v2.4 ISP</span>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger bg-danger-subtle border border-danger-subtle text-danger text-xs py-2 px-3 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <div class="mb-3">
                    <label class="form-label text-slate-700 text-xs text-uppercase fw-semibold tracking-wider">Username</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-slate-700 text-slate-500">
                            <i class="bi bi-person-fill"></i>
                        </span>
                        <input type="text" name="username" id="username" class="form-control form-control-noc" value="Admin" required autofocus placeholder="Enter operator username">
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label text-slate-700 text-xs text-uppercase fw-semibold tracking-wider mb-0">Password</label>
                        <span class="text-slate-500 text-xs font-mono">Team0@247</span>
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-slate-700 text-slate-500">
                            <i class="bi bi-key-fill"></i>
                        </span>
                        <input type="password" name="password" id="password" class="form-control form-control-noc" value="Team0@247" required placeholder="Enter password">
                        <button class="btn btn-outline-secondary border-slate-700 text-slate-500" type="button" onclick="togglePasswordVisibility()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-cyan fw-semibold py-2">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Authenticate &amp; Enter Portal
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary border-slate-700 py-1.5" onclick="fillAdminCreds()">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Fill (Admin / Team0@247)
                    </button>
                </div>
            </form>
        </div>
        <div class="card-footer border-top border-slate-800 py-2.5 px-4 bg-light text-center text-xs text-slate-500 font-mono">
            ISP Operations Management Platform &copy; <?= date('Y') ?>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function fillAdminCreds() {
    document.getElementById('username').value = 'Admin';
    document.getElementById('password').value = 'Team0@247';
}
</script>

</body>
</html>
