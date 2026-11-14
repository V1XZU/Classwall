<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author   = trim($_POST['author'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $remember = isset($_POST['remember']);

    if ($author !== '' && $body !== '') {
        $stmt = $pdo->prepare(
            'INSERT INTO messages (author, body) VALUES (:author, :body)'
        );
        $stmt->execute([
            ':author' => mb_substr($author, 0, 100),
            ':body'   => $body,
        ]);

        if ($remember) {
            setcookie('author_name', $author, [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('author_name', '', time() - 3600, '/');
        }
    }

    $_SESSION['skip_count'] = true;
    header('Location: index.php');
    exit;
}

if (!empty($_SESSION['skip_count'])) {
    unset($_SESSION['skip_count']);
} else {
    $_SESSION['visits'] = ($_SESSION['visits'] ?? 0) + 1;
}

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT author, body, created_at FROM messages
         WHERE body ILIKE :term OR author ILIKE :term
         ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([':term' => '%' . $search . '%']);
} else {
    $stmt = $pdo->query(
        'SELECT author, body, created_at FROM messages
         ORDER BY created_at DESC, id DESC'
    );
}
$messages = $stmt->fetchAll();

$savedName = $_COOKIE['author_name'] ?? '';

function formatTime($value) {
    $dt = new DateTime($value, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format('M j, Y g:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class Wall</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: #eef1f6;
            color: #1f2937;
        }
        .wrap { max-width: 1000px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 24px; }
        h1 { margin: 0 0 6px; font-size: 2.2rem; }
        .visits { margin: 0; color: #6b7280; }
        .layout { display: grid; grid-template-columns: 1fr; gap: 16px; }
        .card {
            background: #fff;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            margin-bottom: 16px;
        }
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 10px; }
        input[type=text], textarea {
            width: 100%;
            margin-top: 4px;
            padding: 10px;
            font: inherit;
            border: 1px solid #d1d5db;
            border-radius: 8px;
        }
        textarea { resize: vertical; }
        .check { display: flex; align-items: center; gap: 8px; font-weight: 400; }
        button {
            padding: 10px 18px;
            font: inherit;
            font-weight: 600;
            color: #fff;
            background: #2563eb;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
        .search-row { display: flex; gap: 8px; }
        .search-row input { margin-top: 0; }
        .clear { display: inline-block; margin-top: 10px; color: #2563eb; }
        .msg strong { font-size: 1rem; }
        .meta { color: #6b7280; font-size: 0.8rem; margin-left: 6px; }
        .msg p { margin: 8px 0 0; line-height: 1.5; word-wrap: break-word; }
        .empty { text-align: center; color: #6b7280; }
        @media (min-width: 820px) {
            .layout { grid-template-columns: 340px 1fr; align-items: start; }
            .side { position: sticky; top: 24px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Class Wall</h1>
        <p class="visits">You've visited <?= (int) $_SESSION['visits'] ?> time(s) this session.</p>
    </header>

    <div class="layout">
        <div class="side">
            <form class="card" method="POST" action="index.php">
                <label>Name
                    <input type="text" name="author" maxlength="100" required
                           value="<?= htmlspecialchars($savedName, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Message
                    <textarea name="body" rows="4" required></textarea>
                </label>
                <label class="check">
                    <input type="checkbox" name="remember" <?= $savedName !== '' ? 'checked' : '' ?>>
                    Remember my name
                </label>
                <button type="submit">Post</button>
            </form>

            <form class="card" method="GET" action="index.php">
                <div class="search-row">
                    <input type="text" name="search" placeholder="Search messages..."
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit">Search</button>
                </div>
                <?php if ($search !== ''): ?>
                    <a class="clear" href="index.php">Clear search</a>
                <?php endif; ?>
            </form>
         </div>

          <div class="board">
            <?php if (!$messages): ?>
                <p class="empty">No messages found.</p>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <div class="card msg">
                    <strong><?= htmlspecialchars($m['author'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="meta"><?= htmlspecialchars(formatTime($m['created_at']), ENT_QUOTES, 'UTF-8') ?></span>
                    <p><?= nl2br(htmlspecialchars($m['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
     </div>
   </div>
</body>
</html>
