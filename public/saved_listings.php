<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$user_id = $_SESSION['user_id'];
$success = get_flash_message('success');
$error = get_flash_message('error');

$stmt = $db->prepare('
    SELECT
        l.*,
        c.name AS category_name,
        u.name AS seller_name,
        s.created_at AS saved_at,
        li.image_path AS primary_image_path
    FROM saved_listings s
    JOIN listings l ON s.listing_id = l.id
    JOIN categories c ON l.category_id = c.id
    JOIN users u ON l.user_id = u.id
    LEFT JOIN listing_images li
        ON li.listing_id = l.id
       AND li.is_primary = 1
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC, s.id DESC
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$saved_listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$category_slugs = [
    'Textbooks' => 'textbooks',
    'Furniture' => 'furniture',
    'Dorm Supplies' => 'dorm',
    'Electronics' => 'electronics',
    'Tutoring' => 'tutoring',
];

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Listings — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; }
        .page-header h1 { font-size: 22px; }
        .page-header p { color: var(--text-muted); font-size: 14px; }
        .listing-grid { align-items: start; }
        .listing-card-shell { background: var(--white); border: 0.5px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
        .listing-card-link { display: block; color: inherit; }
        .listing-card-link:hover { text-decoration: none; }
        .listing-card-footer { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px; border-top: 0.5px solid var(--border); }
        .saved-meta { color: var(--text-muted); font-size: 12px; }
        .empty-state-card { padding: 48px 24px; text-align: center; }
        .empty-state-card p { color: var(--text-muted); margin-top: 8px; }
        @media (max-width: 700px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .listing-card-footer { flex-direction: column; align-items: stretch; }
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
        <a href="profile.php" class="active">Profile</a>
    </div>
</nav>

<div class="container page">
    <div class="page-header">
        <div>
            <h1>Saved Listings</h1>
            <p>Keep track of items you want to come back to later.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">Browse Listings</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($saved_listings)): ?>
        <div class="card empty-state-card">
            <strong>No saved listings yet</strong>
            <p>Save items from the browse feed or listing page to see them here.</p>
        </div>
    <?php else: ?>
        <div class="listing-grid">
            <?php foreach ($saved_listings as $listing): ?>
                <?php
                    $slug = $category_slugs[$listing['category_name']] ?? 'textbooks';
                    $image_path = get_listing_image_path($listing);
                ?>
                <div class="listing-card-shell">
                    <a href="listing.php?id=<?= $listing['id'] ?>" class="listing-card-link">
                        <?php if ($image_path): ?>
                            <img class="listing-card-img" src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($listing['title']) ?>">
                        <?php else: ?>
                            <img class="listing-card-img" src="" alt="<?= htmlspecialchars($listing['title']) ?>" onerror="this.style.background='#f0f0f0';this.removeAttribute('src');">
                        <?php endif; ?>

                        <div class="listing-card-body">
                            <div class="listing-card-title"><?= htmlspecialchars($listing['title']) ?></div>
                            <div class="listing-card-price">$<?= number_format($listing['price'], 2) ?></div>
                            <div class="listing-card-meta">
                                <span class="badge badge-<?= $slug ?>"><?= htmlspecialchars($listing['category_name']) ?></span>
                                <span class="badge badge-<?= htmlspecialchars($listing['condition']) ?>" style="margin-left:4px;"><?= htmlspecialchars($condition_labels[$listing['condition']] ?? $listing['condition']) ?></span>
                            </div>
                            <div class="saved-meta mt-1">
                                Seller: <?= htmlspecialchars($listing['seller_name']) ?> • Saved <?= date('M j, Y', strtotime($listing['saved_at'])) ?>
                            </div>
                        </div>
                    </a>

                    <div class="listing-card-footer">
                        <span class="saved-meta"><?= (int)$listing['is_active'] === 1 ? 'Still active' : 'Archived by seller' ?></span>
                        <form method="POST" action="toggle_save_listing.php">
                            <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                            <input type="hidden" name="action" value="unsave">
                            <input type="hidden" name="redirect" value="/CampusSwap/public/saved_listings.php">
                            <button type="submit" class="btn btn-ghost">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
