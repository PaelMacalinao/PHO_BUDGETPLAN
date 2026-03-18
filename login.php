<?php
/**
 * PHO Budgeting System — Login
 */
require_once __DIR__ . '/config.php';
initSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'index.php' : 'staff_dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $pdo  = getConnection();
            $stmt = $pdo->prepare("SELECT id, fullname, username, password, role FROM tbl_users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = (int)$user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role']     = $user['role'];

                header('Location: ' . ($user['role'] === 'admin' ? 'index.php' : 'staff_dashboard.php'));
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            error_log('Login DB Error: ' . $e->getMessage());
            $error = 'A server error occurred. Please try again.';
        }
    }
}

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {50:'#f0faf3',100:'#d5f0dc',200:'#a8e0b9',300:'#6ec98a',400:'#3aad5e',500:'#1a7a3a',600:'#0b4d26',700:'#093f1f',800:'#073218',900:'#052611'},
                        accent:{50:'#fef9e7',100:'#fdf0c4',200:'#fbe28a',300:'#f9d24f',400:'#f9ba15',500:'#e5a80e',600:'#bf8b09',700:'#8c6607',800:'#5f4504',900:'#332503'},
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?= e($base) ?>/style.css" />
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center font-sans p-4">

<div class="w-full max-w-md">
    <!-- Logo / Branding -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl shadow-lg mb-4" style="background:#0b4d26">
            <i class="fa-solid fa-building-columns text-2xl" style="color:#f9ba15"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800">PHO Budgeting System</h1>
        <p class="text-sm text-gray-500 mt-1">FY 2026 — Provincial Health Office</p>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">
        <h2 class="text-lg font-semibold text-gray-700 mb-1">Sign In</h2>
        <p class="text-sm text-gray-400 mb-6">Enter your credentials to continue.</p>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5 flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-600 mb-1">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fa-solid fa-user text-sm"></i></span>
                    <input type="text" id="username" name="username" required autofocus
                           value="<?= e($username ?? '') ?>"
                           class="form-control w-full pl-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none"
                           placeholder="Enter username" />
                </div>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-600 mb-1">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fa-solid fa-lock text-sm"></i></span>
                    <input type="password" id="password" name="password" required
                           class="form-control w-full pl-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none"
                           placeholder="Enter password" />
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-2.5 rounded-lg font-semibold text-sm">
                <i class="fa-solid fa-right-to-bracket mr-1"></i> Sign In
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">&copy; <?= date('Y') ?> <?= APP_ORG ?>. All rights reserved.</p>
</div>

</body>
</html>
