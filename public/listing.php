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
$stmt = $db->prepare('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY is_primary DESC, id ASC');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
    <title><?= htmlspecialchars($listing['title']) ?> — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .listing-page { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
        .image-gallery { min-width: 0; }
        .gallery-frame { position: relative; overflow: hidden; border-radius: var(--radius-lg); background: #f0f0f0; border: 0.5px solid var(--border); }
        .gallery-track { display: flex; transition: transform 0.25s ease; touch-action: pan-y; }
        .gallery-slide { min-width: 100%; }
        .gallery-slide img { width: 100%; height: 430px; object-fit: contain; display: block; background: #f0f0f0; }
        .gallery-button { position: absolute; top: 50%; transform: translateY(-50%); width: 42px; height: 42px; border: none; border-radius: 50%; background: rgba(255,255,255,0.92); color: var(--blue); font-size: 24px; line-height: 1; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.14); }
        .gallery-button:hover { opacity: 0.9; }
        .gallery-button.prev { left: 12px; }
        .gallery-button.next { right: 12px; }
        .gallery-count { position: absolute; right: 12px; bottom: 12px; background: rgba(26,26,26,0.75); color: #fff; border-radius: 999px; padding: 4px 10px; font-size: 12px; }
        .gallery-dots { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
        .gallery-dot { width: 8px; height: 8px; border-radius: 50%; border: none; background: var(--border); cursor: pointer; }
        .gallery-dot.active { background: var(--orange); }
        .empty-gallery { display: flex; align-items: center; justify-content: center; min-height: 320px; color: var(--text-muted); }
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
            .gallery-slide img { height: 320px; }
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
        <div class="image-gallery" data-gallery>
            <?php if (!empty($images)): ?>
                <div class="gallery-frame">
                    <div class="gallery-track" data-gallery-track>
                        <?php foreach ($images as $i => $img): ?>
                            <div class="gallery-slide">
                                <img src="/CampusSwap/uploads/<?= htmlspecialchars($img['image_path']) ?>" alt="<?= htmlspecialchars($listing['title']) ?> photo <?= $i + 1 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <button type="button" class="gallery-button prev" data-gallery-prev aria-label="Previous photo">‹</button>
                        <button type="button" class="gallery-button next" data-gallery-next aria-label="Next photo">›</button>
                        <div class="gallery-count"><span data-gallery-current>1</span> / <?= count($images) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="gallery-dots" aria-label="Listing photos">
                        <?php foreach ($images as $i => $img): ?>
                            <button type="button" class="gallery-dot <?= $i === 0 ? 'active' : '' ?>" data-gallery-dot="<?= $i ?>" aria-label="Show photo <?= $i + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="gallery-frame empty-gallery">No photos available</div>
            <?php endif; ?>
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

            <?php if (!empty($listing['latitude']) && !empty($listing['longitude'])): ?>
                <div id="map" style="height: 300px; margin-top: 15px; border-radius: 12px; overflow: hidden;"></div>
            <?php endif; ?>

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
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('msg-form').style.display='block';this.style.display='none';">Message Seller</button>
                    <form id="msg-form" method="POST" action="send_message.php" style="display:none; margin-top:12px;">
                        <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                        <input type="hidden" name="other_user_id" value="<?= $listing['user_id'] ?>">
                        <textarea name="body" class="form-control" placeholder="Write a message to the seller..." rows="3" required style="margin-bottom:8px;"></textarea>
                        <button type="submit" class="btn btn-primary w-100">Send Message</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
const gallery = document.querySelector('[data-gallery]');

if (gallery) {
    const track = gallery.querySelector('[data-gallery-track]');
    const slides = gallery.querySelectorAll('.gallery-slide');
    const prevButton = gallery.querySelector('[data-gallery-prev]');
    const nextButton = gallery.querySelector('[data-gallery-next]');
    const currentLabel = gallery.querySelector('[data-gallery-current]');
    const dots = gallery.querySelectorAll('[data-gallery-dot]');
    let currentIndex = 0;
    let touchStartX = 0;

    function showSlide(index) {
        if (!track || slides.length === 0) return;

        currentIndex = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;

        if (currentLabel) {
            currentLabel.textContent = currentIndex + 1;
        }

        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('active', dotIndex === currentIndex);
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => showSlide(currentIndex - 1));
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => showSlide(currentIndex + 1));
    }

    dots.forEach(dot => {
        dot.addEventListener('click', () => showSlide(Number(dot.dataset.galleryDot)));
    });

    if (track) {
        track.addEventListener('touchstart', event => {
            touchStartX = event.touches[0].clientX;
        }, { passive: true });

        track.addEventListener('touchend', event => {
            const touchEndX = event.changedTouches[0].clientX;
            const swipeDistance = touchEndX - touchStartX;

            if (Math.abs(swipeDistance) < 45) return;
            showSlide(swipeDistance < 0 ? currentIndex + 1 : currentIndex - 1);
        });
    }
}
</script>
<?php if (!empty($listing['latitude']) && !empty($listing['longitude'])): ?>
<script>
function initMap() {
    const location = {
        lat: <?= (float)$listing['latitude'] ?>,
        lng: <?= (float)$listing['longitude'] ?>
    };

    const map = new google.maps.Map(document.getElementById("map"), {
        zoom: 15,
        center: location,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true
    });

    new google.maps.Marker({
        position: location,
        map: map
    });
}
</script>

<script 
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFusa8QDcSf1mWwuWuGynna2Fhn2CRh28&callback=initMap"
    async
    defer
></script>
<?php endif; ?>

</body>
</html>
