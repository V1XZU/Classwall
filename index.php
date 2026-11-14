<?php
session_start();
require 'db.php';

$_SESSION['visits'] = ($_SESSION['visits'] ?? 0) + 1;

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

    header('Location: index.php');
    exit;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class Wall</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 0 12px; background: #f4f6f8; }
        h1 { margin-bottom: 4px; }
        .visits { color: #555; margin-top: 0; }
        form, .msg { background: #fff; padding: 12px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        input[type=text], textarea { width: 100%; padding: 8px; margin: 4px 0 8px; box-sizing: border-box; }
        button { padding: 8px 14px; cursor: pointer; }
        .meta { color: #777; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Class Wall</h1>
    <p class="visits">You've visited <?= (int) $_SESSION['visits'] ?> time(s) this session.</p>

    <form method="POST" action="index.php">
        <label>Name
            <input type="text" name="author" maxlength="100" required
                   value="<?= htmlspecialchars($savedName, ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>Message
            <textarea name="body" rows="3" required></textarea>
        </label>
        <label>
            <input type="checkbox" name="remember" <?= $savedName !== '' ? 'checked' : '' ?>>
            Remember me
        </label><br><br>
        <button type="submit">Post</button>
    </form>

    <form method="GET" action="index.php">
        <input type="text" name="search" placeholder="Search messages..."
               value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Search</button>
        <?php if ($search !== ''): ?>
            <a href="index.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (!$messages): ?>
        <p>No messages found.</p>
    <?php endif; ?>
    <?php foreach ($messages as $m): ?>
        <div class="msg">
            <strong><?= htmlspecialchars($m['author'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="meta"> &middot; <?= htmlspecialchars($m['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
            <p><?= nl2br(htmlspecialchars($m['body'], ENT_QUOTES, 'UTF-8')) ?></p>
        </div>
    <?php endforeach; ?> 
</body>
</html>
