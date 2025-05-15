<?php
session_start();
require 'db.php';

// Check if user is logged in
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

// Verify note belongs to user before deleting
$verify_stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
$verify_stmt->bind_param("ii", $note_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    $_SESSION['error'] = "Note not found or you don't have permission to delete it.";
    header("Location: home.php");
    exit();
}

// Delete the note
$delete_stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
$delete_stmt->bind_param("ii", $note_id, $user_id);

if ($delete_stmt->execute()) {
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