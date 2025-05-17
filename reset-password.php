<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
session_start();
include 'db.php';

$error = '';
$valid_token = false;
$email = '';
$user_id = null;

// Check for token-based reset (link method)
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token exists and is not expired - FIXED TABLE NAME HERE
    $stmt = $conn->prepare("SELECT user_id, email FROM password_reset_tokens 
                          WHERE token = ? AND expires_at > NOW()");
    if (!$stmt) {
        die("Error in SQL preparation: " . $conn->error);
    }
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];
        $email = $row['email'];
        $valid_token = true;
        $_SESSION['reset_token'] = $token;
    } else {
        $error = "Invalid or expired reset link.";
    }
} 
// Check for OTP-based reset
elseif (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified']) {
    $user_id = $_SESSION['reset_user_id'];
    $email = $_SESSION['reset_email'];
    $valid_token = true;
} 
// No valid reset method
else {
    header("Location: forgot-password.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // Clean up tokens - FIXED TABLE NAME HERE
            if (isset($_SESSION['reset_token'])) {
                $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                $stmt->bind_param("s", $_SESSION['reset_token']);
            } else {
                $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
            }
            $stmt->execute();
            
            // Clear all session data
            session_unset();
            session_destroy();
            
            // Set success message
            $_SESSION['password_reset_success'] = "Password reset successfully. Please login with your new password.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Error updating password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .reset-card {
            width: 100%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .password-strength {
            height: 5px;
            background-color: #e9ecef;
            margin-bottom: 15px;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            background-color: #28a745;
            transition: width 0.3s ease;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .reset-method-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card reset-card">
                    <div class="card-header text-center py-3">
                        <h3><i class="fas fa-key"></i> Reset Your Password</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <p class="mb-0">Resetting password for: <strong><?= htmlspecialchars($email) ?></strong></p>
                            <span class="badge reset-method-badge bg-<?= isset($_SESSION['otp_verified']) ? 'info' : 'primary' ?>">
                                <?= isset($_SESSION['otp_verified']) ? 'OTP Verified' : 'Reset Link' ?>
                            </span>
                        </div>
                        
                        <form id="resetForm" action="reset-password.php<?= isset($_GET['token']) ? '?token=' . htmlspecialchars($_GET['token']) : '' ?>" method="POST">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required minlength="8" placeholder="Enter new password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Must be at least 8 characters</div>
                                <div class="password-strength mt-2">
                                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required minlength="8" 
                                           placeholder="Re-enter new password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch" class="form-text"></div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" id="resetButton">
                                    <i class="fas fa-save me-2"></i> Reset Password
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Password strength checker and visibility toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('passwordStrengthBar');
            const passwordMatchText = document.getElementById('passwordMatch');
            const resetButton = document.getElementById('resetButton');
            const toggleButtons = document.querySelectorAll('.toggle-password');
            
            // Toggle password visibility
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.parentNode.querySelector('input');
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // Check password strength
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 1;
                if (password.length >= 12) strength += 1;
                
                // Character type checks
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                // Update strength bar
                const width = (strength / 5) * 100;
                strengthBar.style.width = width + '%';
                
                // Update color
                if (strength <= 2) {
                    strengthBar.style.backgroundColor = '#dc3545'; // Red
                } else if (strength <= 4) {
                    strengthBar.style.backgroundColor = '#ffc107'; // Yellow
                } else {
                    strengthBar.style.backgroundColor = '#28a745'; // Green
                }
            });
            
            // Check password match
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    passwordMatchText.textContent = "Passwords don't match";
                    passwordMatchText.style.color = '#dc3545';
                    resetButton.disabled = true;
                } else {
                    passwordMatchText.textContent = "Passwords match";
                    passwordMatchText.style.color = '#28a745';
                    resetButton.disabled = false;
                }
            });
        });
    </script>
</body>
</html>