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

// Get note details including password protection status
$stmt = $conn->prepare("SELECT title, content, created_at, is_password_protected FROM notes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $note_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    echo "Note not found or access denied.";
    exit();
}

// Check if password is required and not already verified
$password_verified = false;
if ($note['is_password_protected'] && !isset($_SESSION['verified_notes'][$note_id])) {
    // Handle password submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        // Verify password
        $stmt = $conn->prepare("SELECT password_hash FROM notes WHERE id = ?");
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->fetch();
        $stmt->close();
        
        if (password_verify($_POST['password'], $password_hash)) {
            $_SESSION['verified_notes'][$note_id] = true;
            $password_verified = true;
        } else {
            $error = "Incorrect password";
        }
    }
    
    if (!$password_verified) {
        // Show password form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Enter Password</title>
            <link href="assets/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
        <div class="container py-5">
            <div class="card">
                <div class="card-body">
                    <h3>Password Required</h3>
                    <p>This note is protected with a password.</p>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Unlock Note</button>
                        <a href="home.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
        exit();
    }
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
            <?php if ($note['is_password_protected']): ?>
                <span class="badge bg-info text-dark mb-2">Password Protected</span>
            <?php endif; ?>
            <hr>
            <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>
        </div>
    </div>
    <div class="mt-3">
        <a href="edit-note.php?id=<?= $note_id ?>" class="btn btn-warning">Edit Note</a>
        <a href="note-password.php?id=<?= $note_id ?>" class="btn btn-info">
            <?= $note['is_password_protected'] ? 'Change Password' : 'Add Password' ?>
        </a>
    </div>
</div>
</body>
</html>