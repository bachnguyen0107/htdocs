<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "No note ID provided.";
    exit();
}

$note_id = intval($_GET['id']);

// Get user ID
$user = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user->bind_param("s", $_SESSION['username']);
$user->execute();
$user_result = $user->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data['id'];

// Get note details
$stmt = $conn->prepare("SELECT title, content, created_at FROM notes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $note_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    echo "Note not found or access denied.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Note</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <a href="home.php" class="btn btn-secondary mb-4">← Back to Notes</a>
    <div class="card">
        <div class="card-body">
            <h3><?= htmlspecialchars($note['title']) ?></h3>
            <p class="text-muted">Created at: <?= $note['created_at'] ?></p>
            <hr>
            <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>
        </div>
    </div>
<a href="edit-note.php?id=<?= $note_id ?>" class="btn btn-warning mt-3">Edit Note</a>
</a>
</div>
</body>
</html>
