<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
session_start();

function register_user($name, $email, $password) {
    $db = get_db();

    // Validate campus email
    $allowed_domains = ['ufl.edu', 'sfcollege.edu'];
    $domain = substr(strrchr($email, '@'), 1);

    if (!in_array($domain, $allowed_domains)) {
        return ['success' => false, 'message' => 'You must use a valid UF or Santa Fe email address.'];
    }

    // Check if email already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        return ['success' => false, 'message' => 'An account with that email already exists.'];
    }

    // Get campus_id from domain
    $stmt = $db->prepare('SELECT id FROM campuses WHERE email_domain = ?');
    $stmt->bind_param('s', $domain);
    $stmt->execute();
    $stmt->bind_result($campus_id);
    $stmt->fetch();
    $stmt->close();

    // Hash password and insert user
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare('INSERT INTO users (campus_id, name, email, password_hash) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $campus_id, $name, $email, $password_hash);
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Account created successfully!'];
}

function login_user($email, $password) {
    $db = get_db();

    $stmt = $db->prepare('SELECT id, name, password_hash, campus_id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($id, $name, $password_hash, $campus_id);
    $stmt->fetch();
    $stmt->close();

    if (!$id || !password_verify($password, $password_hash)) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    $_SESSION['user_id'] = $id;
    $_SESSION['user_name'] = $name;
    $_SESSION['campus_id'] = $campus_id;

    return ['success' => true];
}

function logout_user() {
    session_destroy();
    redirect_to('/CampusSwap/public/login.php');
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect_to('/CampusSwap/public/login.php');
    }
}
?>
