<?php
require_once '../src/auth.php';
require_login();

$db = get_db();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$listing_id = (int)$_GET['id'];

// Get listing info
$stmt = $db->prepare('
    SELECT l.*, c.name AS category_name, u.name AS seller_name
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    JOIN users u ON l.user_id = u.id
    WHERE l.id = ? AND l.is_active = 1
');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    echo "Listing not found.";
    exit();
}

// Get images
$stmt = $db->prepare('SELECT * FROM listing_images WHERE listing_id = ?');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$condition_labels = [
    'new' => 'New',
    'like_new' => 'Like new',
    'good' => 'Good',
    'fair' => 'Fair',
    'poor' => 'Poor',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($listing['title']) ?> — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .listing-page { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
        .image-gallery img { width: 100%; border-radius: var(--radius-lg); margin-bottom: 10px; }
        .listing-info h1 { font-size: 22px; margin-bottom: 8px; }
        .listing-price { font-size: 20px; font-weight: 700; color: var(--orange); margin-bottom: 12px; }
        .meta { font-size: 14px; color: var(--text-muted); margin-bottom: 12px; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand"><span>Campus</span>Swap</a>
    <div class="navbar-links">
        <a href="index.php">Browse</a>
        <a href="create_listing.php">Sell</a>
        <a href="messages.php">Messages</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container page">
    <div class="listing-page">

        <!-- Images -->
        <div class="image-gallery">
            <?php foreach ($images as $img): ?>
                <img src="/CampusSwap/uploads/<?= htmlspecialchars($img['image_path']) ?>">
            <?php endforeach; ?>
        </div>

        <!-- Info -->
        <div class="listing-info">
            <h1><?= htmlspecialchars($listing['title']) ?></h1>
            <div class="listing-price">$<?= number_format($listing['price'], 2) ?></div>

            <div class="meta">
                <?= htmlspecialchars($listing['category_name']) ?> • 
                <?= $condition_labels[$listing['condition']] ?? $listing['condition'] ?>
            </div>

            <p><?= nl2br(htmlspecialchars($listing['description'])) ?></p>

            <div class="meta mt-2">
                📍 <?= htmlspecialchars($listing['location'] ?: 'No location specified') ?>
            </div>

            <div class="meta">
                👤 Seller: <?= htmlspecialchars($listing['seller_name']) ?>
            </div>

            <button class="btn btn-primary mt-3 w-100">
                Message Seller
            </button>
        </div>

    </div>
</div>

</body>
</html>