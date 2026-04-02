<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$user_id = $_SESSION['user_id'];
$success = get_flash_message('success');
$error = get_flash_message('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listing_id = (int)($_POST['listing_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (!$listing_id || !in_array($action, ['archive', 'restore'], true)) {
        set_flash_message('error', 'Unable to update listing status.');
        redirect_to('/CampusSwap/public/my_listings.php');
    }

    $stmt = $db->prepare('SELECT id, is_active FROM listings WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $listing_id, $user_id);
    $stmt->execute();
    $listing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$listing) {
        set_flash_message('error', 'Listing not found.');
        redirect_to('/CampusSwap/public/my_listings.php');
    }

    $next_status = $action === 'archive' ? 0 : 1;
    $stmt = $db->prepare('UPDATE listings SET is_active = ? WHERE id = ? AND user_id = ?');
    $stmt->bind_param('iii', $next_status, $listing_id, $user_id);
    $stmt->execute();
    $stmt->close();

    set_flash_message('success', $action === 'archive' ? 'Listing archived.' : 'Listing restored.');
    redirect_to('/CampusSwap/public/my_listings.php');
}

$stmt = $db->prepare('
    SELECT
        l.*,
        c.name AS category_name,
        li.image_path AS primary_image_path
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    LEFT JOIN listing_images li
        ON li.listing_id = l.id
       AND li.is_primary = 1
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC, l.id DESC
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$condition_labels = [
    'new' => 'New',
    'like_new' => 'Like new',
    'good' => 'Good',
    'fair' => 'Fair',
    'poor' => 'Poor',
];

$category_slugs = [
    'Textbooks' => 'textbooks',
    'Furniture' => 'furniture',
    'Dorm Supplies' => 'dorm',
    'Electronics' => 'electronics',
    'Tutoring' => 'tutoring',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; }
        .page-header h1 { font-size: 22px; }
        .page-header p { color: var(--text-muted); font-size: 14px; }
        .stack { display: grid; gap: 16px; }
        .manage-card { display: grid; grid-template-columns: 180px 1fr; gap: 18px; padding: 18px; }
        .manage-card img { width: 100%; height: 140px; object-fit: cover; border-radius: var(--radius-md); background: #f0f0f0; }
        .manage-card-body { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
        .manage-card-top { display: flex; justify-content: space-between; gap: 12px; align-items: start; }
        .manage-card-title { font-size: 18px; font-weight: 600; color: var(--text); }
        .manage-card-price { color: var(--orange); font-size: 20px; font-weight: 700; }
        .manage-meta { display: flex; flex-wrap: wrap; gap: 8px; }
        .status-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .status-pill.active { background: #EAF3DE; color: #27500A; }
        .status-pill.archived { background: #FCEBEB; color: #791F1F; }
        .manage-description { color: var(--text-muted); font-size: 14px; }
        .manage-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: auto; }
        .inline-form { display: inline-block; }
        .empty-state-card { padding: 48px 24px; text-align: center; }
        .empty-state-card p { color: var(--text-muted); margin-top: 8px; }
        @media (max-width: 760px) {
            .manage-card { grid-template-columns: 1fr; }
            .manage-card img { height: 220px; }
            .manage-card-top { flex-direction: column; }
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
            <h1>My Listings</h1>
            <p>Manage the items you have posted for UF and Santa Fe students.</p>
        </div>
        <a href="create_listing.php" class="btn btn-primary">Post a Listing</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($listings)): ?>
        <div class="card empty-state-card">
            <strong>No listings yet</strong>
            <p>Create your first listing to start selling on campus.</p>
        </div>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($listings as $listing): ?>
                <?php
                    $image_path = get_listing_image_path($listing);
                    $is_active = (int)$listing['is_active'] === 1;
                    $slug = $category_slugs[$listing['category_name']] ?? 'textbooks';
                ?>
                <div class="card manage-card">
                    <a href="listing.php?id=<?= $listing['id'] ?>">
                        <?php if ($image_path): ?>
                            <img src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($listing['title']) ?>">
                        <?php else: ?>
                            <img src="" alt="<?= htmlspecialchars($listing['title']) ?>" onerror="this.style.background='#f0f0f0';this.removeAttribute('src');">
                        <?php endif; ?>
                    </a>

                    <div class="manage-card-body">
                        <div class="manage-card-top">
                            <div>
                                <a href="listing.php?id=<?= $listing['id'] ?>" class="manage-card-title"><?= htmlspecialchars($listing['title']) ?></a>
                                <div class="manage-description mt-1">
                                    Posted <?= date('M j, Y', strtotime($listing['created_at'])) ?>
                                    <?php if (!empty($listing['location'])): ?>
                                        • <?= htmlspecialchars($listing['location']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="manage-card-price">$<?= number_format($listing['price'], 2) ?></div>
                        </div>

                        <div class="manage-meta">
                            <span class="badge badge-<?= $slug ?>"><?= htmlspecialchars($listing['category_name']) ?></span>
                            <span class="badge badge-<?= htmlspecialchars($listing['condition']) ?>"><?= htmlspecialchars($condition_labels[$listing['condition']] ?? $listing['condition']) ?></span>
                            <span class="status-pill <?= $is_active ? 'active' : 'archived' ?>"><?= $is_active ? 'Active' : 'Archived' ?></span>
                        </div>

                        <div class="manage-description">
                            <?= htmlspecialchars($listing['description'] ?: 'No description provided.') ?>
                        </div>

                        <div class="manage-actions">
                            <a href="listing.php?id=<?= $listing['id'] ?>" class="btn btn-secondary">View Listing</a>

                            <form method="POST" class="inline-form">
                                <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                                <input type="hidden" name="action" value="<?= $is_active ? 'archive' : 'restore' ?>">
                                <button type="submit" class="btn btn-secondary"><?= $is_active ? 'Archive' : 'Restore' ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
