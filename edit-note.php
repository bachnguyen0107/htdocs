<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "Note ID not provided.";
    exit();
}

$note_id = intval($_GET['id']);

// Get user ID
$user_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user_stmt->bind_param("s", $_SESSION['username']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data['id'];

// Get the note including password protection status
$note_stmt = $conn->prepare("SELECT title, content, is_password_protected FROM notes WHERE id = ? AND user_id = ?");
$note_stmt->bind_param("ii", $note_id, $user_id);
$note_stmt->execute();
$note_result = $note_stmt->get_result();
$note = $note_result->fetch_assoc();

if (!$note) {
    echo "Note not found or you don't have permission to edit it.";
    exit();
}

// Check password protection
if ($note['is_password_protected'] && !isset($_SESSION['verified_notes'][$note_id])) {
    header("Location: view-note.php?id=" . $note_id);
    exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_title = trim($_POST['title']);
    $new_content = trim($_POST['content']);

    if (!empty($new_title)) {
        $update_stmt = $conn->prepare("UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?");
        $update_stmt->bind_param("ssii", $new_title, $new_content, $note_id, $user_id);
        $update_stmt->execute();
        header("Location: view-note.php?id=" . $note_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Note</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <a href="view-note.php?id=<?= $note_id ?>" class="btn btn-secondary mb-4">← Back to Note</a>
    <div class="card">
        <div class="card-body">
            <h3>Edit Note</h3>
            <?php if ($note['is_password_protected']): ?>
                <div class="alert alert-info mb-3">
                    This note is password protected. Changes will maintain the protection.
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($note['title']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Content</label>
                    <textarea name="content" rows="10" class="form-control"><?= htmlspecialchars($note['content']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>