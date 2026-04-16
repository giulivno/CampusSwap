<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listing_id = (int)($_POST['listing_id'] ?? 0);
    $other_user_id = (int)($_POST['other_user_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listing_id   = (int)($_POST['listing_id'] ?? 0);
    $other_user_id = (int)($_POST['other_user_id'] ?? 0);
    $body         = trim($_POST['body'] ?? '');

    if ($listing_id && $other_user_id && $body !== '' && $other_user_id !== $current_user_id) {
        $stmt = $db->prepare('
            INSERT INTO messages (listing_id, sender_id, receiver_id, body)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->bind_param('iiis', $listing_id, $current_user_id, $other_user_id, $body);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: /CampusSwap/public/messages.php?listing_id=$listing_id&user_id=$other_user_id");
exit();
}