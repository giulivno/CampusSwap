<?php
require_once '../src/auth.php';
require_login();

$db = get_db();

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch categories
$categories = $db->query('SELECT * FROM categories')->fetch_all(MYSQLI_ASSOC);

// Build listings query
$sql = 'SELECT l.*, c.name AS category_name, u.name AS seller_name
        FROM listings l
        JOIN categories c ON l.category_id = c.id
        JOIN users u ON l.user_id = u.id
        WHERE l.is_active = 1 AND l.campus_id = ?';

$params = [$_SESSION['campus_id']];
$types = 'i';

if ($category_id) {
    $sql .= ' AND l.category_id = ?';
    $params[] = $category_id;
    $types .= 'i';
}

if ($search) {
    $sql .= ' AND (l.title LIKE ? OR l.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$sql .= ' ORDER BY l.created_at DESC';

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Category slug map for badges
$category_slugs = [
    'Textbooks'    => 'textbooks',
    'Furniture'    => 'furniture',
    'Dorm Supplies'=> 'dorm',
    'Electronics'  => 'electronics',
    'Tutoring'     => 'tutoring',
];

$condition_labels = [
    'new'      => 'New',
    'like_new' => 'Like new',
    'good'     => 'Good',
    'fair'     => 'Fair',
    'poor'     => 'Poor',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .page-layout { display: flex; gap: 24px; align-items: flex-start; }
        .sidebar { width: 200px; flex-shrink: 0; }
        .sidebar-card { background: #fff; border: 0.5px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
        .sidebar-title { font-size: 12px; font-weight: 600; color: var(--text-muted); padding: 14px 16px 8px; text-transform: uppercase; letter-spacing: 0.06em; }
        .sidebar-item { display: block; padding: 10px 16px; font-size: 14px; color: var(--text); border-top: 0.5px solid var(--border); transition: background 0.1s; }
        .sidebar-item:hover { background: var(--surface); text-decoration: none; }
        .sidebar-item.active { background: var(--blue-light); color: var(--blue); font-weight: 500; }
        .main { flex: 1; min-width: 0; }
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-bar input { flex: 1; }
        .feed-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .feed-title { font-size: 16px; font-weight: 600; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state p { margin-top: 8px; font-size: 14px; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand"><span>Campus</span>Swap</a>
    <div class="navbar-links">
        <a href="index.php" class="active">Browse</a>
        <a href="create_listing.php">Sell</a>
        <a href="messages.php">Messages</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container page">
    <div class="page-layout">

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-card">
                <div class="sidebar-title">Categories</div>
                <a href="index.php" class="sidebar-item <?= !$category_id ? 'active' : '' ?>">All listings</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php?category=<?= $cat['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                       class="sidebar-item <?= $category_id === (int)$cat['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main feed -->
        <div class="main">

            <!-- Search bar -->
            <form method="GET" class="search-bar">
                <?php if ($category_id): ?>
                    <input type="hidden" name="category" value="<?= $category_id ?>">
                <?php endif; ?>
                <input type="text" name="search" class="form-control"
                       placeholder="Search textbooks, furniture..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?>
                    <a href="index.php<?= $category_id ? '?category='.$category_id : '' ?>" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </form>

            <!-- Feed header -->
            <div class="feed-header">
                <span class="feed-title">
                    <?php if ($search): ?>
                        Results for "<?= htmlspecialchars($search) ?>"
                    <?php elseif ($category_id): ?>
                        <?= htmlspecialchars($categories[array_search($category_id, array_column($categories, 'id'))]['name'] ?? 'Listings') ?>
                    <?php else: ?>
                        All listings
                    <?php endif; ?>
                </span>
                <span class="text-muted" style="font-size:13px;"><?= count($listings) ?> item<?= count($listings) !== 1 ? 's' : '' ?></span>
            </div>

            <!-- Listing grid -->
            <?php if (empty($listings)): ?>
                <div class="empty-state">
                    <strong>No listings found</strong>
                    <p>Be the first to post something!</p>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                    <?php foreach ($listings as $listing): ?>
                        <?php
                            $slug = $category_slugs[$listing['category_name']] ?? 'textbooks';
                            $cond = $condition_labels[$listing['condition']] ?? $listing['condition'];
                        ?>
                        <a href="listing.php?id=<?= $listing['id'] ?>" style="text-decoration:none;">
                            <div class="listing-card">
                                <img class="listing-card-img"
                                     src="/CampusSwap/uploads/<?= htmlspecialchars($listing['id']) ?>/primary.jpg"
                                     onerror="this.style.background='#f0f0f0';this.src=''"
                                     alt="<?= htmlspecialchars($listing['title']) ?>">
                                <div class="listing-card-body">
                                    <div class="listing-card-title"><?= htmlspecialchars($listing['title']) ?></div>
                                    <div class="listing-card-price">$<?= number_format($listing['price'], 2) ?></div>
                                    <div class="listing-card-meta">
                                        <span class="badge badge-<?= $slug ?>"><?= htmlspecialchars($listing['category_name']) ?></span>
                                        <span class="badge badge-<?= $listing['condition'] ?>" style="margin-left:4px;"><?= $cond ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>