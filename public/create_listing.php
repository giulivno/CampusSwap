<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$error = '';
$success = '';

$categories = $db->query('SELECT * FROM categories')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $price       = (float)$_POST['price'];
    $condition   = $_POST['condition'];
    $availability= $_POST['availability'];
    $location    = trim($_POST['location']);
    $description = trim($_POST['description']);

    // Basic validation
    if (!$title || !$category_id || !$price || !$condition || !$availability) {
        $error = 'Please fill out all required fields.';
    } elseif (empty($_FILES['images']['name'][0])) {
        $error = 'Please upload at least one photo.';
    } else {
        // Insert listing
        $stmt = $db->prepare('INSERT INTO listings (user_id, campus_id, category_id, title, description, price, `condition`, availability, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiissdsss', $_SESSION['user_id'], $_SESSION['campus_id'], $category_id, $title, $description, $price, $condition, $availability, $location);
        $stmt->execute();
        $listing_id = $stmt->insert_id;
        $stmt->close();

        // Handle image uploads
        $upload_dir = __DIR__ . '/../uploads/' . $listing_id . '/';
        mkdir($upload_dir, 0755, true);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $first = true;

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['images']['type'][$i], $allowed)) continue;

            $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
            $filename = $first ? 'primary.' . $ext : uniqid() . '.' . $ext;
            move_uploaded_file($tmp, $upload_dir . $filename);

            $stmt = $db->prepare('INSERT INTO listing_images (listing_id, image_path, is_primary) VALUES (?, ?, ?)');
            $path = $listing_id . '/' . $filename;
            $is_primary = $first ? 1 : 0;
            $stmt->bind_param('isi', $listing_id, $path, $is_primary);
            $stmt->execute();
            $stmt->close();

            $first = false;
        }

        header('Location: /CampusSwap/public/index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell an item — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .sell-layout { max-width: 640px; margin: 0 auto; }
        .sell-layout h1 { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .image-upload-box { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 32px; text-align: center; cursor: pointer; transition: border-color 0.15s; }
        .image-upload-box:hover { border-color: var(--blue); }
        .image-upload-box p { font-size: 13px; color: var(--text-muted); margin-top: 6px; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand"><span>Campus</span>Swap</a>
    <div class="navbar-links">
        <a href="index.php">Browse</a>
        <a href="create_listing.php" class="active">Sell</a>
        <a href="messages.php">Messages</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container page">
    <div class="sell-layout">
        <h1>Post a listing</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="card mb-3">
                <div class="form-group">
                    <label class="form-label">Title <span style="color:var(--orange)">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Biology Textbook 9th Edition" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Describe the item, any wear, edition, extras included..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Price <span style="color:var(--orange)">*</span></label>
                        <input type="number" name="price" class="form-control" placeholder="0.00" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category <span style="color:var(--orange)">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Condition <span style="color:var(--orange)">*</span></label>
                        <select name="condition" class="form-control" required>
                            <option value="">Select condition</option>
                            <option value="new">New</option>
                            <option value="like_new">Like new</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Availability <span style="color:var(--orange)">*</span></label>
                        <select name="availability" class="form-control" required>
                            <option value="">Select availability</option>
                            <option value="immediate">Available now</option>
                            <option value="future">Available later</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g. Turlington Hall, Reitz Union...">
                </div>
            </div>

            <div class="card mb-3">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Photos <span style="color:var(--orange)">*</span></label>
                    <label class="image-upload-box" for="images">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--text-muted);margin:0 auto">
                            <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        <p>Click to upload photos (JPEG, PNG, WebP)</p>
                        <input type="file" id="images" name="images[]" accept="image/*" multiple style="display:none">
                    </label>
                    <div id="preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Post listing</button>
            <a href="index.php" class="btn btn-ghost w-100 mt-1" style="text-align:center;display:block;">Cancel</a>

        </form>
    </div>
</div>

<script>
document.getElementById('images').addEventListener('change', function() {
    const preview = document.getElementById('preview');
    preview.innerHTML = '';
    [...this.files].forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:0.5px solid var(--border)';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
});
</script>

</body>
</html>