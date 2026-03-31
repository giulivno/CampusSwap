<?php
require_once '../src/auth.php';

if (is_logged_in()) {
    header('Location: /CampusSwap/public/index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $result = login_user($email, $password);

    if ($result['success']) {
        header('Location: /CampusSwap/public/index.php');
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-logo">
            <span class="blue">Campus</span><span class="orange">Swap</span>
        </div>
        <p class="auth-subtitle">Student marketplace for UF & Santa Fe</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="you@ufl.edu" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-1">Log in</button>
        </form>

        <p class="text-center text-muted mt-2" style="font-size:13px;">
            Don't have an account? <a href="register.php">Register</a>
        </p>

    </div>
</div>
</body>
</html>