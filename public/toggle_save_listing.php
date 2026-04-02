<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/CampusSwap/public/index.php');
}

$listing_id = (int)($_POST['listing_id'] ?? 0);
$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/CampusSwap/public/index.php';

if (!$listing_id || !in_array($action, ['save', 'unsave'], true)) {
    set_flash_message('error', 'Unable to update saved listings.');
    redirect_to($redirect);
}

$stmt = $db->prepare('SELECT id, user_id, is_active FROM listings WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$listing) {
    set_flash_message('error', 'Listing not found.');
    redirect_to($redirect);
}

if ($action === 'save') {
    if ((int)$listing['user_id'] === $user_id) {
        set_flash_message('error', 'You cannot save your own listing.');
    } elseif (!(bool)$listing['is_active']) {
        set_flash_message('error', 'Only active listings can be saved.');
    } else {
        save_listing($db, $user_id, $listing_id);
        set_flash_message('success', 'Listing saved.');
    }
} else {
    unsave_listing($db, $user_id, $listing_id);
    set_flash_message('success', 'Listing removed from saved listings.');
}

redirect_to($redirect);
?>
