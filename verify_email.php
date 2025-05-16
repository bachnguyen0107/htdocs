<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();
include 'db.php';

if (!isset($_GET['token'])) {
    $_SESSION['error'] = "Invalid verification link.";
    header("Location: home.php");
    exit();
}

$token = $_GET['token'];



$stmt = $conn->prepare("SELECT user_id FROM email_verification_tokens WHERE token = ? AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->store_result();  // Required when using bind_result() and fetch()
$stmt->bind_result($user_id);

if ($stmt->num_rows > 0 && $stmt->fetch()) {
    $stmt->close();

    // Mark user as verified
    $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Delete used token
    $stmt = $conn->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Your email has been verified!";
} else {
    $stmt->close();  // Safely close here
    $_SESSION['error'] = "Invalid or expired verification link.";
}

$conn->close();
header("Location: home.php");
exit();
?>