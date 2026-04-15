<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$user_id = $_SESSION['user_id'];
$success = get_flash_message('success');
$error = get_flash_message('error');

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? max(0, (float)$_GET['min_price']) : '';
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? max(0, (float)$_GET['max_price']) : '';
$condition_filter = isset($_GET['condition']) ? trim($_GET['condition']) : '';

$condition_labels = [
    'new'      => 'New',
    'like_new' => 'Like new',
    'good'     => 'Good',
    'fair'     => 'Fair',
    'poor'     => 'Poor',
];

if ($condition_filter && !array_key_exists($condition_filter, $condition_labels)) {
    $condition_filter = '';
}

// Fetch categories
$categories = $db->query('SELECT * FROM categories')->fetch_all(MYSQLI_ASSOC);

// Build listings query
$sql = 'SELECT l.*, c.name AS category_name, u.name AS seller_name, li.image_path AS primary_image_path
        FROM listings l
        JOIN categories c ON l.category_id = c.id
        JOIN users u ON l.user_id = u.id
        LEFT JOIN listing_images li
            ON li.listing_id = l.id
           AND li.is_primary = 1
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

if ($min_price !== '') {
    $sql .= ' AND l.price >= ?';
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price !== '') {
    $sql .= ' AND l.price <= ?';
    $params[] = $max_price;
    $types .= 'd';
}

if ($condition_filter) {
    $sql .= ' AND l.`condition` = ?';
    $params[] = $condition_filter;
    $types .= 's';
}

$sql .= ' ORDER BY l.created_at DESC';

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Category slug map for badges
$category_slugs = [
    'Textbooks'    => 'textbooks',
    'Furniture'    => 'furniture',
    'Dorm Supplies'=> 'dorm',
    'Electronics'  => 'electronics',
    'Tutoring'     => 'tutoring',
];

$current_filters = [];
if ($search !== '') $current_filters['search'] = $search;
if ($min_price !== '') $current_filters['min_price'] = $min_price;
if ($max_price !== '') $current_filters['max_price'] = $max_price;
if ($condition_filter !== '') $current_filters['condition'] = $condition_filter;
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
        .search-panel { background: var(--white); border: 0.5px solid var(--border); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 20px; }
        .search-bar { display: grid; grid-template-columns: 1fr repeat(3, minmax(120px, 150px)) auto auto; gap: 10px; align-items: end; }
        .feed-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .feed-title { font-size: 16px; font-weight: 600; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state p { margin-top: 8px; font-size: 14px; }
        .listing-card-shell { background: var(--white); border: 0.5px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
        .listing-card-link { display: block; color: inherit; }
        .listing-card-link:hover { text-decoration: none; }
        .listing-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px; border-top: 0.5px solid var(--border); }
        .listing-card-seller { font-size: 12px; color: var(--text-muted); }
        @media (max-width: 760px) {
            .page-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .search-bar { grid-template-columns: 1fr; }
        }
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
    <?php if ($success): ?>
        <div class="alert alert-success mb-2"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error mb-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-layout">

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-card">
                <div class="sidebar-title">Categories</div>
                <?php $all_params = $current_filters; ?>
                <a href="index.php<?= $all_params ? '?' . http_build_query($all_params) : '' ?>" class="sidebar-item <?= !$category_id ? 'active' : '' ?>">All listings</a>
                <?php foreach ($categories as $cat): ?>
                    <?php
                        $category_params = array_merge($current_filters, ['category' => $cat['id']]);
                        $category_url = 'index.php?' . http_build_query($category_params);
                    ?>
                    <a href="<?= htmlspecialchars($category_url) ?>" class="sidebar-item <?= $category_id === (int)$cat['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main feed -->
        <div class="main">

            <!-- Search and filters -->
            <div class="search-panel">
                <form method="GET" class="search-bar">
                    <?php if ($category_id): ?>
                        <input type="hidden" name="category" value="<?= $category_id ?>">
                    <?php endif; ?>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control"
                               placeholder="Search textbooks, furniture..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Min price</label>
                        <input type="number" name="min_price" class="form-control" min="0" step="0.01"
                               value="<?= htmlspecialchars((string)$min_price) ?>" placeholder="0">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Max price</label>
                        <input type="number" name="max_price" class="form-control" min="0" step="0.01"
                               value="<?= htmlspecialchars((string)$max_price) ?>" placeholder="Any">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Condition</label>
                        <select name="condition" class="form-control">
                            <option value="">Any</option>
                            <?php foreach ($condition_labels as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $condition_filter === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Filter</button>
                    <?php if ($search || $min_price !== '' || $max_price !== '' || $condition_filter): ?>
                        <a href="index.php<?= $category_id ? '?category='.$category_id : '' ?>" class="btn btn-ghost">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

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
                            $is_owner = (int)$listing['user_id'] === $user_id;
                            $image_path = get_listing_image_path($listing);
                        ?>
                        <div class="listing-card-shell">
                            <a href="listing.php?id=<?= $listing['id'] ?>" class="listing-card-link">
                                <div class="listing-card">
                                <?php if ($image_path): ?>
                                    <img class="listing-card-img" src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($listing['title']) ?>">
                                <?php else: ?>
                                    <img class="listing-card-img" src="" onerror="this.style.background='#f0f0f0';this.removeAttribute('src');" alt="<?= htmlspecialchars($listing['title']) ?>">
                                <?php endif; ?>
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
                            <div class="listing-card-footer">
                                <span class="listing-card-seller"><?= htmlspecialchars($listing['seller_name']) ?></span>
                                <?php if ($is_owner): ?>
                                    <a href="my_listings.php" class="btn btn-ghost btn-sm">Manage</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>
