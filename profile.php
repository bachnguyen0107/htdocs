<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Get user data
$stmt = $conn->prepare("SELECT id, username, email, avatar, bio, full_name, password FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_password_hash, $user['id']);
        
        if ($update_stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $error = "Error updating password: " . $conn->error;
        }
        $update_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }
        .profile-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .password-form {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="profile-container">
            <h2>User Profile</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <!-- Avatar Display -->
            <div class="text-center mb-4">
                <img src="uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" 
                     alt="Profile Avatar" class="avatar">
            </div>
            
            <!-- Profile Info -->
            <div class="mb-4">
                <h3><?= htmlspecialchars($user['full_name'] ?? 'Not set') ?></h3>
                <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                
                <!-- Bio -->
                <div class="bio mt-3">
                    <h4>About Me:</h4>
                    <p><?= htmlspecialchars($user['bio'] ?? 'No bio yet') ?></p>
                </div>
            </div>
            
            <!-- Password Change Form -->
            <div class="password-form">
                <h4>Change Password</h4>
                <form method="POST">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" 
                               name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" 
                               name="new_password" required minlength="8">
                        <div class="form-text">Minimum 8 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" 
                               name="confirm_password" required minlength="8">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        Change Password
                    </button>
                </form>
            </div>
            
            <!-- Navigation -->
            <div class="mt-4">
                <a href="edit_profile.php" class="btn btn-outline-primary">Edit Profile</a>
                <a href="home.php" class="btn btn-outline-secondary">Back to Home</a>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
            }
        });
    </script>
</body>
</html>