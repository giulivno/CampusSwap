<?php
require_once '../src/auth.php';

require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusSwap</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
    <a href="logout.php">Log out</a>
</body>
</html>