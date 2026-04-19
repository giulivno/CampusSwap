<?php
require_once '../src/auth.php';
require_once 'mailer.php';
require_login();

$db = get_db();
$current_user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listing_id = (int)($_POST['listing_id'] ?? 0);
    $other_user_id = (int)($_POST['other_user_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    if (!$listing_id || !$other_user_id || $body === '') {
        $error = 'Please enter a message.';
    } else {
        $stmt = $db->prepare('
            INSERT INTO messages (listing_id, sender_id, receiver_id, body)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->bind_param('iiis', $listing_id, $current_user_id, $other_user_id, $body);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->bind_param("i", $other_user_id);
        $stmt->execute();
        $stmt->bind_result($receiver_email);
        $stmt->fetch();
        $stmt->close();

        $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $stmt->bind_result($sender_name);
        $stmt->fetch();
        $stmt->close();

        if (!empty($receiver_email)) {
            $subject = "New message from " . ($sender_name ?: "CampusSwap User");

            $email_body = "
                <h3>New message from " . htmlspecialchars($sender_name ?: "CampusSwap User") . "</h3>
                <p>" . nl2br(htmlspecialchars($body)) . "</p>
                <p>Log into the app to view and respond.</p>
            ";

            send_email($receiver_email, $subject, $email_body);
        }

        header("Location: /CampusSwap/public/messages.php?listing_id=$listing_id&user_id=$other_user_id");
        exit();
    }
}

$stmt = $db->prepare('
    SELECT 
        m.id,
        m.listing_id,
        m.sender_id,
        m.receiver_id,
        m.body,
        m.is_read,
        m.created_at,
        l.title AS listing_title,
        sender.name AS sender_name,
        receiver.name AS receiver_name
    FROM messages m
    JOIN listings l ON m.listing_id = l.id
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC, m.id DESC
');
$stmt->bind_param('ii', $current_user_id, $current_user_id);
$stmt->execute();
$all_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conversations = [];

foreach ($all_messages as $msg) {
    $is_sent_by_me = (int)$msg['sender_id'] === $current_user_id;
    $other_user_id = $is_sent_by_me ? (int)$msg['receiver_id'] : (int)$msg['sender_id'];
    $other_user_name = $is_sent_by_me ? $msg['receiver_name'] : $msg['sender_name'];
    $conversation_key = $msg['listing_id'] . '-' . $other_user_id;

    if (!isset($conversations[$conversation_key])) {
        $conversations[$conversation_key] = [
            'listing_id' => (int)$msg['listing_id'],
            'listing_title' => $msg['listing_title'],
            'other_user_id' => $other_user_id,
            'other_user_name' => $other_user_name,
            'last_message' => $msg['body'],
            'last_message_time' => $msg['created_at']
        ];
    }
}

$conversations = array_values($conversations);

$selected_listing_id = (int)($_GET['listing_id'] ?? 0);
$selected_other_user_id = (int)($_GET['user_id'] ?? 0);

if (!$selected_listing_id || !$selected_other_user_id) {
    if (!empty($conversations)) {
        $selected_listing_id = $conversations[0]['listing_id'];
        $selected_other_user_id = $conversations[0]['other_user_id'];
    }
}

$selected_conversation = null;
$thread_messages = [];

if ($selected_listing_id && $selected_other_user_id) {
    foreach ($conversations as $conversation) {
        if (
            $conversation['listing_id'] === $selected_listing_id &&
            $conversation['other_user_id'] === $selected_other_user_id
        ) {
            $selected_conversation = $conversation;
            break;
        }
    }

    $stmt = $db->prepare('
        SELECT 
            m.id,
            m.sender_id,
            m.receiver_id,
            m.body,
            m.created_at,
            sender.name AS sender_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        WHERE m.listing_id = ?
          AND (
                (m.sender_id = ? AND m.receiver_id = ?)
             OR (m.sender_id = ? AND m.receiver_id = ?)
          )
        ORDER BY m.created_at ASC, m.id ASC
    ');
    $stmt->bind_param(
        'iiiii',
        $selected_listing_id,
        $current_user_id,
        $selected_other_user_id,
        $selected_other_user_id,
        $current_user_id
    );
    $stmt->execute();
    $thread_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $db->prepare('
        UPDATE messages
        SET is_read = 1
        WHERE listing_id = ?
          AND sender_id = ?
          AND receiver_id = ?
          AND is_read = 0
    ');
    $stmt->bind_param('iii', $selected_listing_id, $selected_other_user_id, $current_user_id);
    $stmt->execute();
    $stmt->close();
}

function format_message_time($datetime) {
    return date('M j, g:i A', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — CampusSwap</title>
    <link rel="stylesheet" href="/CampusSwap/public/assets/css/style.css">
    <style>
        .messages-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 25px;
        }

        .conversation-list,
        .chat-panel {
            background: var(--white);
            border: 0.5px solid var(--border);
        }

        .conversation-list-header,
        .chat-header {
            padding: 16px 18px;
            border-bottom: 0.5px solid var(--border);
            background: var(--white);
        }

        .conversation-list-header h2,
        .chat-header h2 {
            font-size: 16px;
        }

        .conversation-items {
            max-height: 70vh;
            overflow-y: auto;
        }

        .conversation-item {
            display: block;
            padding: 14px 16px;
            border-bottom: 0.5px solid var(--border);
            color: var(--text);
        }

        .conversation-item.active {
            background: var(--blue-light);
        }

        .conversation-top {
            display: flex;
            justify-content: space-between;
        }

        .conversation-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .conversation-time {
            font-size: 12px;
            color: var(--text-muted);
        }

        .conversation-listing {
            font-size: 12px;
            color: var(--blue);
        }

        .conversation-preview {
            font-size: 13px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-subtitle {
            font-size: 13px;
            color: var(--text-muted);
        }

        .chat-messages {
            
            padding: 20px;
            background: #fcfcfc;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 420px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .message-row {
            display: flex;
        }

        .message-row.sent {
            justify-content: flex-end;
        }

        .message-row.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 14px;
        }

        .message-row.sent .message-bubble {
            background: var(--orange);
            color: white;
            border-bottom-right-radius: 6px;
        }

        .message-row.received .message-bubble {
            background: var(--blue-light);
            color: var(--text);
            border-bottom-left-radius: 6px;
        }

        .message-meta {
            font-size: 11px;
            margin-top: 6px;
        }

        .chat-form {
            border-top: 0.5px solid var(--border);
            padding: 16px 18px;
            background: var(--white);
        }

        .chat-form-inner {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .chat-form textarea {
            resize: vertical;
            min-height: 48px;
            max-height: 140px;
        }

        .empty-chat,
        .empty-conversations {
            padding: 32px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        @media (max-width: 900px) {
            .messages-layout {
                grid-template-columns: 1fr;
            }

            .conversation-items,
            .chat-messages {
                max-height: none;
            }

            .message-bubble {
                max-width: 88%;
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
        <a href="messages.php" class="active">Messages</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container page">
    <div class="messages-layout">

        <div class="conversation-list">
            <div class="conversation-list-header">
                <h2>Messages</h2>
            </div>

            <div class="conversation-items">
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                            $is_active =
                                $conv['listing_id'] === $selected_listing_id &&$conv['other_user_id'] === $selected_other_user_id;

                            $conversation_link = 'messages.php?listing_id=' . $conv['listing_id'] . '&user_id=' . $conv['other_user_id'];
                            $conversation_class = 'conversation-item' . ($is_active ? ' active' : '');
                            $conversation_date = date('M j', strtotime($conv['last_message_time']));
                        ?>
                        <a class="<?= $conversation_class ?>" href="<?= $conversation_link ?>">
                            <div class="conversation-top">
                                <div class="conversation-name"><?= htmlspecialchars($conv['other_user_name']) ?></div>
                                <div class="conversation-time"><?= htmlspecialchars($conversation_date) ?></div>
                            </div>
                            <div class="conversation-listing"><?= htmlspecialchars($conv['listing_title']) ?></div>
                            <div class="conversation-preview"><?= htmlspecialchars($conv['last_message']) ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-conversations">
                        <p>No conversations yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-panel">
            <?php if ($selected_conversation): ?>
                <div class="chat-header">
                    <h2><?= htmlspecialchars($selected_conversation['other_user_name']) ?></h2>
                    <div class="chat-subtitle">
                        <?= htmlspecialchars($selected_conversation['listing_title']) ?>
                    </div>
                </div>

                <div class="chat-messages">
                    <?php if (!empty($thread_messages)): ?>
                        <?php foreach ($thread_messages as $message): ?>
                            <?php
                                $is_sent = (int)$message['sender_id'] === $current_user_id;
                                $message_class = 'message-row ' . ($is_sent ? 'sent' : 'received');
                                $formatted_time = format_message_time($message['created_at']);
                            ?>
                            <div class="<?= $message_class ?>">
                                <div class="message-bubble">
                                    <div><?= nl2br(htmlspecialchars($message['body'])) ?></div>
                                    <div class="message-meta"><?= htmlspecialchars($formatted_time) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-chat">
                            <p>No messages in this conversation yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" class="chat-form">
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <input type="hidden" name="listing_id" value="<?= $selected_listing_id ?>">
                    <input type="hidden" name="other_user_id" value="<?= $selected_other_user_id ?>">

                    <div class="chat-form-inner">
                        <textarea
                            name="body"
                            class="form-control"
                            placeholder="Write a message..."
                            required
                        ></textarea>
                        <button type="submit" class="btn btn-primary">Send</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-chat">
                    <h2 style="margin-bottom:8px;">Your messages</h2>
                    <p>Select a conversation from the left to start chatting.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>