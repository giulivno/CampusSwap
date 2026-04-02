<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$user_id = $_SESSION['user_id'];
$success = get_flash_message('success');
$error = get_flash_message('error');

if (!isset($_GET['id'])) {
    redirect_to('/CampusSwap/public/index.php');
}

$listing_id = (int)$_GET['id'];

// Get listing info
$stmt = $db->prepare('
    SELECT l.*, c.name AS category_name, u.name AS seller_name
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    JOIN users u ON l.user_id = u.id
    WHERE l.id = ? AND (l.is_active = 1 OR l.user_id = ?)
');
$stmt->bind_param('ii', $listing_id, $user_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    echo "Listing not found.";
    exit();
}

$is_owner = (int)$listing['user_id'] === $user_id;
$is_saved = !$is_owner && is_listing_saved($db, $user_id, $listing_id);

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
        .status-pill { display: inline-block; margin-bottom: 12px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .status-pill.archived { background: #FCEBEB; color: #791F1F; }
        .action-row { display: flex; gap: 12px; margin-top: 24px; }
        .action-row form,
        .action-row a,
        .action-row button { flex: 1; }
        @media (max-width: 760px) {
            .listing-page { grid-template-columns: 1fr; }
            .action-row { flex-direction: column; }
        }
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
    <?php if ($success): ?>
        <div class="alert alert-success mb-2"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error mb-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
            <?php if ((int)$listing['is_active'] !== 1): ?>
                <div class="status-pill archived">Archived Listing</div>
            <?php endif; ?>
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

            <div class="action-row">
                <?php if ($is_owner): ?>
                    <a href="my_listings.php" class="btn btn-primary" style="text-align:center;">Manage Listing</a>
                <?php else: ?>
                    <form method="POST" action="toggle_save_listing.php">
                        <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                        <input type="hidden" name="action" value="<?= $is_saved ? 'unsave' : 'save' ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <button type="submit" class="btn btn-secondary w-100"><?= $is_saved ? 'Unsave Listing' : 'Save Listing' ?></button>
                    </form>
                    <a href="messages.php" class="btn btn-secondary" style="text-align:center;">Messages</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
