
<?php
require_once '../src/auth.php';

if (is_logged_in()) {
    header('Location: /CampusSwap/public/index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $result = register_user($name, $email, $password);

    if ($result['success']) {
        $success = $result['message'];
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
    <title>Register — CampusSwap</title>
</head>
<body>
    <h1>Create Account</h1>

    <?php if ($error): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?= htmlspecialchars($success) ?> <a href="login.php">Log in</a></p>
    <?php endif; ?>

    <form method="POST">
        <label>Name<br>
            <input type="text" name="name" required>
        </label><br><br>
        <label>Email (.ufl.edu or .sfcollege.edu)<br>
            <input type="email" name="email" required>
        </label><br><br>
        <label>Password<br>
            <input type="password" name="password" required>
        </label><br><br>
        <button type="submit">Register</button>
    </form>

    <p>Already have an account? <a href="login.php">Log in</a></p>
</body>
</html>