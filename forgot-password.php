<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
include 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database
        $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user['id'], $token, $expires_at);
        $stmt->execute();
        
        // Send email with reset link
        $reset_link = "http://localhost/reset-password.php?token=" . $token;
        
       
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'huycter2019@gmail.com'; 
            $mail->Password = 'viov wymj yzwv fvxq'; 
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('huycter2019@gmail.com', 'Notes App');
            $mail->addAddress($email, $user['username']);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "Hello <b>{$user['username']}</b>,<br><br>We received a request to reset your password. Click the link below to reset it:<br><a href='$reset_link'>$reset_link</a><br><br>This link expires in 1 hour.";
            $mail->AltBody = "Password reset link: $reset_link";

            $mail->send();
            $message = "Password reset link has been sent to your email.";
        } catch (Exception $e) {
            $message = "Error sending email: " . $e->getMessage();
        }
    } else {
        $message = "If an account exists with that email, a reset link has been sent.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .forgot-card { max-width: 500px; margin: 50px auto; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card forgot-card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center">Forgot Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>
                        <form action="forgot-password.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Send Reset Link</button>
                            </div>
                        </form>
                        <div class="mt-3 text-center">
                            <a href="login.php" class="text-decoration-none">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>