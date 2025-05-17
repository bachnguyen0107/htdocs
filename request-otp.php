<?php
session_start();
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
        $otp = rand(100000, 999999); // 6-digit OTP
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Store OTP in database
        $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, otp, otp_expires_at) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE otp = ?, otp_expires_at = ?");
        $stmt->bind_param("issss", $user['id'], $otp, $expires_at, $otp, $expires_at);
        $stmt->execute();
        
        
        
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
            $mail->Subject = 'Your Password Reset OTP';
            $mail->Body    = "Hello <b>{$user['username']}</b>,<br><br>Your OTP for password reset is: <h3>$otp</h3><br>This OTP is valid for 10 minutes.";
            $mail->AltBody = "Your OTP is: $otp (valid for 10 minutes)";

            $mail->send();
            $_SESSION['reset_email'] = $email;
            header("Location: verify-otp.php");
            exit();
        } catch (Exception $e) {
            $message = "Error sending email: " . $e->getMessage();
        }
    } else {
        $message = "If an account exists with that email, an OTP has been sent.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request OTP</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .otp-card { max-width: 500px; margin: 50px auto; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card otp-card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center">Request OTP</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>
                        <form action="request-otp.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Send OTP</button>
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