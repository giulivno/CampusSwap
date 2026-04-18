<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$error = '';
$success = '';

$categories = $db->query('SELECT * FROM categories')->fetch_all(MYSQLI_ASSOC);

function detect_uploaded_image_mime($tmp) {
    if (!$tmp || !is_uploaded_file($tmp)) {
        return '';
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);

            if ($mime) {
                return $mime;
            }
        }
    }

    $image_info = getimagesize($tmp);
    return $image_info['mime'] ?? '';
}

function image_extension_for_mime($mime) {
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    return $extensions[$mime] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $price       = (float)$_POST['price'];
    $condition   = $_POST['condition'];
    $availability= $_POST['availability'];
    $location    = trim($_POST['location']);
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? (float)$longitude = (float)$_POST['longitude'] : null;
    $description = trim($_POST['description']);
    $cover_index = (int)($_POST['cover_index'] ?? 0);

    $allowed_conditions = ['new', 'like_new', 'good', 'fair', 'poor'];
    $allowed_availability = ['immediate', 'future'];
    $valid_images = [];
    $upload_errors = 0;

    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                $upload_errors++;
                continue;
            }

            $mime = detect_uploaded_image_mime($tmp);
            $ext = image_extension_for_mime($mime);
            if (!$ext) continue;

            $valid_images[] = [
                'tmp' => $tmp,
                'ext' => $ext,
                'original_index' => $i
            ];
        }
    }

    // Basic validation
    if (!$title || !$category_id || !$price || !$condition || !$availability) {
        $error = 'Please fill out all required fields.';
    } elseif (!in_array($condition, $allowed_conditions, true) || !in_array($availability, $allowed_availability, true)) {
        $error = 'Please choose valid listing options.';
    } elseif (empty($valid_images)) {
        $error = $upload_errors > 0
            ? 'One or more photos could not be uploaded. Try smaller JPEG, PNG, or WebP files.'
            : 'Please upload at least one valid JPEG, PNG, or WebP photo.';
    } else {
        $uploads_root = __DIR__ . '/../uploads/';

        if (!is_dir($uploads_root)) {
            mkdir($uploads_root, 0777, true);
        }

        if (!is_dir($uploads_root) || !is_writable($uploads_root)) {
            $error = 'The uploads folder is not writable. Please check the uploads directory permissions.';
        }
    }

    if (!$error && !empty($valid_images)) {
        $valid_cover_indexes = array_column($valid_images, 'original_index');
        if (!in_array($cover_index, $valid_cover_indexes, true)) {
            $cover_index = $valid_images[0]['original_index'];
        }

        // Insert listing
        $stmt = $db->prepare('
        INSERT INTO listings (
            user_id,
            campus_id,
            category_id,
            title,
            description,
            price,
            `condition`,
            availability,
            location,
            latitude,
            longitude
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param(
            'iiissdsssdd',
            $_SESSION['user_id'],
            $_SESSION['campus_id'],
            $category_id,
            $title,
            $description,
            $price,
            $condition,
            $availability,
            $location,
            $latitude,
            $longitude
        );
        $stmt->execute();
        $listing_id = $stmt->insert_id;
        $stmt->close();

        // Handle image uploads
        $upload_dir = $uploads_root . $listing_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $inserted_image_ids = [];
        $primary_image_id = 0;

        if (is_dir($upload_dir) && is_writable($upload_dir)) {
            foreach ($valid_images as $image) {
                $filename = uniqid('listing_', true) . '.' . $image['ext'];
                if (!move_uploaded_file($image['tmp'], $upload_dir . $filename)) continue;

                $is_primary = (int)$image['original_index'] === $cover_index ? 1 : 0;
                $stmt = $db->prepare('INSERT INTO listing_images (listing_id, image_path, is_primary) VALUES (?, ?, ?)');
                $path = $listing_id . '/' . $filename;
                $stmt->bind_param('isi', $listing_id, $path, $is_primary);
                $stmt->execute();
                $image_id = $stmt->insert_id;
                $stmt->close();

                $inserted_image_ids[] = $image_id;
                if ($is_primary) {
                    $primary_image_id = $image_id;
                }
            }
        }

        if (empty($inserted_image_ids)) {
            $stmt = $db->prepare('DELETE FROM listings WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $listing_id, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();

            $error = is_dir($upload_dir) && is_writable($upload_dir)
                ? 'The listing was not posted because the photos could not be uploaded.'
                : 'The listing was not posted because the upload folder could not be written to.';
        } else {
            if (!$primary_image_id) {
                $stmt = $db->prepare('UPDATE listing_images SET is_primary = 1 WHERE id = ?');
                $stmt->bind_param('i', $inserted_image_ids[0]);
                $stmt->execute();
                $stmt->close();
            }

            header('Location: /CampusSwap/public/index.php');
            exit();
        }
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
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAFusa8QDcSf1mWwuWuGynna2Fhn2CRh28&libraries=places"></script>
    <style>
        .sell-layout { max-width: 640px; margin: 0 auto; }
        .sell-layout h1 { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .image-upload-box { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 32px; text-align: center; cursor: pointer; transition: border-color 0.15s; }
        .image-upload-box:hover { border-color: var(--blue); }
        .image-upload-box p { font-size: 13px; color: var(--text-muted); margin-top: 6px; }
        .photo-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin-top: 12px; }
        .photo-preview-card { border: 1px solid var(--border); border-radius: var(--radius-md); padding: 8px; background: var(--white); transition: border-color 0.15s, box-shadow 0.15s; }
        .photo-preview-card.selected { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(26,86,160,0.12); }
        .photo-preview-card img { width: 100%; height: 92px; object-fit: cover; border-radius: var(--radius-sm); display: block; margin-bottom: 6px; }
        .photo-preview-cover { display: flex; align-items: center; margin-bottom: 8px; cursor: pointer; }
        .photo-preview-cover input { margin-right: 5px; }
        .cover-label { font-size: 12px; color: var(--text-muted); }
        .photo-preview-card.selected .cover-label { color: var(--blue); font-weight: 600; }
        .remove-photo-btn { width: 100%; text-align: center; }
        @media (max-width: 700px) {
            .form-row { grid-template-columns: 1fr; }
        }
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
                    <label class="form-label">Location <span style="color:var(--orange)">*</span></label>

                    <input 
                        type="text" 
                        id="location-input"
                        name="location" 
                        class="form-control"
                        placeholder="Search UF or Santa Fe campus location..."
                        required
                    >

                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                </div>
            </div>

            <div class="card mb-3">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Photos <span style="color:var(--orange)">*</span></label>
                    <label class="image-upload-box" for="images">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--text-muted);margin:0 auto">
                            <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        <p>Click to upload photos (JPEG, PNG, WebP). You can choose one as the cover.</p>
                        <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
                    </label>
                    <div id="preview" class="photo-preview-grid"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Post listing</button>
            <a href="index.php" class="btn btn-ghost w-100 mt-1" style="text-align:center;display:block;">Cancel</a>

        </form>
    </div>
</div>

<script>
const imageInput = document.getElementById('images');
const preview = document.getElementById('preview');
let selectedFiles = [];
let coverIndex = 0;
let previewUrls = [];

function syncImageInput() {
    const dataTransfer = new DataTransfer();
    selectedFiles.forEach(file => dataTransfer.items.add(file));
    imageInput.files = dataTransfer.files;
}

function renderPhotoPreviews() {
    previewUrls.forEach(url => URL.revokeObjectURL(url));
    previewUrls = [];

    const preview = document.getElementById('preview');
    preview.innerHTML = '';

    if (selectedFiles.length === 0) {
        coverIndex = 0;
        return;
    }

    if (coverIndex >= selectedFiles.length) {
        coverIndex = 0;
    }

    selectedFiles.forEach((file, index) => {
        const card = document.createElement('div');
        card.className = 'photo-preview-card';
        if (index === coverIndex) {
            card.classList.add('selected');
        }

        const img = document.createElement('img');
        const objectUrl = URL.createObjectURL(file);
        previewUrls.push(objectUrl);
        img.src = objectUrl;
        img.alt = file.name;

        const coverOption = document.createElement('label');
        coverOption.className = 'photo-preview-cover';
        coverOption.setAttribute('for', `cover_index_${index}`);

        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'cover_index';
        radio.id = `cover_index_${index}`;
        radio.value = index;
        radio.checked = index === coverIndex;

        const label = document.createElement('span');
        label.className = 'cover-label';
        label.textContent = index === coverIndex ? 'Cover photo' : 'Set as cover';

        radio.addEventListener('change', () => {
            coverIndex = index;
            renderPhotoPreviews();
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-ghost btn-sm remove-photo-btn';
        removeButton.textContent = 'Remove';
        removeButton.addEventListener('click', () => {
            selectedFiles.splice(index, 1);

            if (coverIndex === index) {
                coverIndex = 0;
            } else if (index < coverIndex) {
                coverIndex--;
            }

            syncImageInput();
            renderPhotoPreviews();
        });

        coverOption.appendChild(radio);
        coverOption.appendChild(label);
        card.appendChild(img);
        card.appendChild(coverOption);
        card.appendChild(removeButton);
        preview.appendChild(card);
    });
}

imageInput.addEventListener('change', function() {
    selectedFiles = selectedFiles.concat([...this.files]);
    syncImageInput();
    renderPhotoPreviews();
});
</script>

<script>
function initAutocomplete() {
    const input = document.getElementById('location-input');

    const autocomplete = new google.maps.places.Autocomplete(input, {
        componentRestrictions: { country: "us" },
        fields: ["geometry", "name"],
    });

    // Restrict roughly to Gainesville area
    autocomplete.setBounds({
        north: 29.70,
        south: 29.60,
        east: -82.30,
        west: -82.40
    });

    autocomplete.addListener("place_changed", () => {
        const place = autocomplete.getPlace();

        if (!place.geometry) return;

        document.getElementById('latitude').value = place.geometry.location.lat();
        document.getElementById('longitude').value = place.geometry.location.lng();
    });
}

window.addEventListener("load", initAutocomplete);
</script>

</body>
</html>
