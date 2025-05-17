<?php
session_start();
require 'db.php';


if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Check if note ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No note ID provided for deletion.";
    header("Location: home.php");
    exit();
}

$note_id = intval($_GET['id']);

// Get user ID
$user_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user_stmt->bind_param("s", $_SESSION['username']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data) {
    $_SESSION['error'] = "User not found.";
    header("Location: home.php");
    exit();
}

$user_id = $user_data['id'];

// Verify note belongs to user and check password protection
$verify_stmt = $conn->prepare("SELECT id, is_password_protected FROM notes WHERE id = ? AND user_id = ?");
$verify_stmt->bind_param("ii", $note_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();
$note_data = $verify_result->fetch_assoc();

if (!$note_data) {
    $_SESSION['error'] = "Note not found or you don't have permission to delete it.";
    header("Location: home.php");
    exit();
}

// Check if note is password protected and not verified in this session
if ($note_data['is_password_protected'] && !isset($_SESSION['verified_notes'][$note_id])) {
    // Handle password submission if this is a POST request
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
        } else {
            $_SESSION['error'] = "Incorrect password";
            header("Location: view-note.php?id=" . $note_id);
            exit();
        }
    } else {
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
                    <p>This note is protected with a password. Please enter the password to delete it.</p>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Delete Note</button>
                        <a href="view-note.php?id=<?= $note_id ?>" class="btn btn-secondary">Cancel</a>
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

$delete_stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
$delete_stmt->bind_param("ii", $note_id, $user_id);

if ($delete_stmt->execute()) {
    
    $delete_labels_stmt = $conn->prepare("DELETE FROM note_labels WHERE note_id = ?");
    $delete_labels_stmt->bind_param("i", $note_id);
    $delete_labels_stmt->execute();
    $delete_labels_stmt->close();
    
    if (isset($_SESSION['verified_notes'][$note_id])) {
        unset($_SESSION['verified_notes'][$note_id]);
    }
    
    $_SESSION['success'] = "Note deleted successfully.";
} else {
    $_SESSION['error'] = "Error deleting note: " . $conn->error;
}

$delete_stmt->close();
$conn->close();

// Redirect back to home page
header("Location: home.php");
exit();
?>