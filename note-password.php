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

// Get the note
$note_stmt = $conn->prepare("SELECT title, is_password_protected FROM notes WHERE id = ? AND user_id = ?");
$note_stmt->bind_param("ii", $note_id, $user_id);
$note_stmt->execute();
$note_result = $note_stmt->get_result();
$note = $note_result->fetch_assoc();

if (!$note) {
    echo "Note not found or you don't have permission to edit it.";
    exit();
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'set') {
        $password = $_POST['password'];
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE notes SET is_password_protected = 1, password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $note_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Password protection enabled successfully";
        header("Location: view-note.php?id=" . $note_id);
        exit();
    } elseif ($action === 'change') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM notes WHERE id = ?");
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->fetch();
        $stmt->close();
        
        if (password_verify($current_password, $password_hash)) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE notes SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $new_password_hash, $note_id);
            $stmt->execute();
            
            $_SESSION['success'] = "Password changed successfully";
            header("Location: view-note.php?id=" . $note_id);
            exit();
        } else {
            $error = "Current password is incorrect";
        }
    } elseif ($action === 'remove') {
        $stmt = $conn->prepare("UPDATE notes SET is_password_protected = 0, password_hash = NULL WHERE id = ?");
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        
        // Remove from verified notes if it exists
        if (isset($_SESSION['verified_notes'][$note_id])) {
            unset($_SESSION['verified_notes'][$note_id]);
        }
        
        $_SESSION['success'] = "Password protection removed successfully";
        header("Location: view-note.php?id=" . $note_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $note['is_password_protected'] ? 'Change' : 'Add' ?> Password</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <a href="view-note.php?id=<?= $note_id ?>" class="btn btn-secondary mb-4">← Back to Note</a>
    <div class="card">
        <div class="card-body">
            <h3><?= $note['is_password_protected'] ? 'Change Password Protection' : 'Add Password Protection' ?></h3>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($note['is_password_protected']): ?>
                <!-- Change password form -->
                <form method="POST">
                    <input type="hidden" name="action" value="change">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <button type="submit" formaction="note-password.php?id=<?= $note_id ?>" class="btn btn-danger" 
                            onclick="return confirm('Are you sure you want to remove password protection?')">
                        Remove Protection
                    </button>
                    <input type="hidden" name="action" value="remove">
                </form>
            <?php else: ?>
                <!-- Set password form -->
                <form method="POST">
                    <input type="hidden" name="action" value="set">
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Enable Protection</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>