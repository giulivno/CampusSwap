<?php

function redirect_to($path) {
    header('Location: ' . $path);
    exit();
}

function set_flash_message($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash_message($type) {
    $key = 'flash_' . $type;
    $message = $_SESSION[$key] ?? '';
    unset($_SESSION[$key]);

    return $message;
}

function is_listing_saved($db, $user_id, $listing_id) {
    $stmt = $db->prepare('SELECT id FROM saved_listings WHERE user_id = ? AND listing_id = ? LIMIT 1');
    $stmt->bind_param('ii', $user_id, $listing_id);
    $stmt->execute();
    $stmt->store_result();
    $is_saved = $stmt->num_rows > 0;
    $stmt->close();

    return $is_saved;
}

function save_listing($db, $user_id, $listing_id) {
    if (is_listing_saved($db, $user_id, $listing_id)) {
        return false;
    }

    $stmt = $db->prepare('INSERT INTO saved_listings (user_id, listing_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $user_id, $listing_id);
    $saved = $stmt->execute();
    $stmt->close();

    return $saved;
}

function unsave_listing($db, $user_id, $listing_id) {
    $stmt = $db->prepare('DELETE FROM saved_listings WHERE user_id = ? AND listing_id = ?');
    $stmt->bind_param('ii', $user_id, $listing_id);
    $removed = $stmt->execute();
    $stmt->close();

    return $removed;
}

function get_listing_image_path($listing) {
    if (!empty($listing['primary_image_path'])) {
        return '/CampusSwap/uploads/' . $listing['primary_image_path'];
    }

    if (!empty($listing['image_path'])) {
        return '/CampusSwap/uploads/' . $listing['image_path'];
    }

    return '';
}
?>
