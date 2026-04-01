<?php
require_once '../src/auth.php';
require_login();

$db = get_db();
$user_id = $_SESSION['user_id'];
$success = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch full user info
$stmt = $db->prepare('
    SELECT u.id, u.name, u.email, u.created_at, c.name AS campus_name
    FROM users u
    JOIN campuses c ON u.campus_id = c.id
    WHERE u.id = ?
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['update_info', 'change_password'])) {
        $action = '';
    }

    // Update name
    if ($action === 'update_info') {
        $new_name = trim($_POST['name'] ?? '');

        if ($new_name === '') {
            $_SESSION['flash_error'] = 'Name cannot be empty.';
            header('Location: profile.php');
            exit;
        } else {
            $stmt = $db->prepare('UPDATE users SET name = ? WHERE id = ?');
            $stmt->bind_param('si', $new_name, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user_name'] = $new_name;
            $user['name'] = $new_name;
            $_SESSION['flash_success'] = 'Profile updated successfully.';
            header('Location: profile.php');
            exit;
        }
    }

    // Change password
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Fetch current hash
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current_password, $password_hash)) {
            $_SESSION['flash_error'] = 'Current password is incorrect.';
            header('Location: profile.php');
            exit;
        } elseif (strlen($new_password) < 8) {
            $_SESSION['flash_error'] = 'New password must be at least 8 characters.';
            header('Location: profile.php');
            exit;
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['flash_error'] = 'Passwords do not match.';
            header('Location: profile.php');
            exit;
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bind_param('si', $new_hash, $user_id);
            $stmt->execute();
            $stmt->close();
            session_destroy();
            session_start();
            header('Location: /CampusSwap/public/login.php');
            exit;
        }
    }
}

// Member since formatted
$member_since = date('F j, Y', strtotime($user['created_at']));

// Count user's active listings
$stmt = $db->prepare('SELECT COUNT(*) FROM listings WHERE user_id = ? AND is_active = 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($active_listing_count);
$stmt->fetch();
$stmt->close();

// Count saved listings
$stmt = $db->prepare('SELECT COUNT(*) FROM saved_listings WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($saved_count);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .profile-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            align-items: start;
        }

        .profile-sidebar {
            background: var(--white);
            border: 0.5px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .profile-sidebar-header {
            background: var(--blue);
            padding: 28px 20px;
            text-align: center;
        }

        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--orange);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin: 0 auto 12px;
            border: 3px solid rgba(255,255,255,0.25);
        }

        .profile-sidebar-name {
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .profile-sidebar-campus {
            color: rgba(255,255,255,0.65);
            font-size: 12px;
        }

        .profile-sidebar-body {
            padding: 16px 20px;
        }

        .profile-stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 0.5px solid var(--border);
            font-size: 13px;
        }

        .profile-stat-row:last-child {
            border-bottom: none;
        }

        .profile-stat-label {
            color: var(--text-muted);
        }

        .profile-stat-value {
            font-weight: 600;
            color: var(--text);
        }

        .profile-stat-value.orange {
            color: var(--orange);
        }

        .profile-sidebar-links {
            border-top: 0.5px solid var(--border);
            padding: 12px 0;
        }

        .profile-sidebar-link {
            display: block;
            padding: 9px 20px;
            font-size: 13px;
            color: var(--text);
            transition: background 0.1s;
        }

        .profile-sidebar-link:hover {
            background: var(--surface);
            text-decoration: none;
            color: var(--blue);
        }

        /* Main content */
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .profile-section {
            background: var(--white);
            border: 0.5px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .profile-section-header {
            padding: 16px 20px;
            border-bottom: 0.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .profile-section-header h2 {
            font-size: 15px;
            font-weight: 600;
        }

        .profile-section-body {
            padding: 20px;
        }

        .profile-email-note {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 4px;
        }

        @media (max-width: 800px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
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

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- Sidebar -->
        <div class="profile-sidebar">
            <div class="profile-sidebar-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div class="profile-sidebar-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="profile-sidebar-campus"><?= htmlspecialchars($user['campus_name']) ?></div>
            </div>

            <div class="profile-sidebar-body">
                <div class="profile-stat-row">
                    <span class="profile-stat-label">Member since</span>
                    <span class="profile-stat-value"><?= $member_since ?></span>
                </div>
                <div class="profile-stat-row">
                    <span class="profile-stat-label">Active listings</span>
                    <span class="profile-stat-value orange"><?= $active_listing_count ?></span>
                </div>
                <div class="profile-stat-row">
                    <span class="profile-stat-label">Saved listings</span>
                    <span class="profile-stat-value"><?= $saved_count ?></span>
                </div>
            </div>

            <div class="profile-sidebar-links">
                <a href="my_listings.php" class="profile-sidebar-link">→ My Listings</a>
                <a href="saved_listings.php" class="profile-sidebar-link">→ Saved Listings</a>
                <a href="messages.php" class="profile-sidebar-link">→ Messages</a>
                <a href="logout.php" class="profile-sidebar-link" style="color: #c0392b;">→ Log Out</a>
            </div>
        </div>

        <!-- Main -->
        <div class="profile-main">

            <!-- Edit Info -->
            <div class="profile-section">
                <div class="profile-section-header">
                    <h2>Account Info</h2>
                </div>
                <div class="profile-section-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_info">

                        <div class="form-group">
                            <label class="form-label">Display Name</label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="<?= htmlspecialchars($user['name']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input
                                type="email"
                                class="form-control"
                                value="<?= htmlspecialchars($user['email']) ?>"
                                disabled
                            >
                            <div class="profile-email-note">Email cannot be changed.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Campus</label>
                            <input
                                type="text"
                                class="form-control"
                                value="<?= htmlspecialchars($user['campus_name']) ?>"
                                disabled
                            >
                            <div class="profile-email-note">Campus cannot be changed.</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="profile-section">
                <div class="profile-section-header">
                    <h2>Change Password</h2>
                </div>
                <div class="profile-section-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input
                                type="password"
                                name="current_password"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input
                                    type="password"
                                    name="new_password"
                                    class="form-control"
                                    minlength="8"
                                    required
                                >
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input
                                    type="password"
                                    name="confirm_password"
                                    class="form-control"
                                    minlength="8"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>