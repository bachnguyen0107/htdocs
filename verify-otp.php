<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
session_start();
include 'db.php';

// Debugging: Display all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['reset_email'])) {
    header("Location: request-otp.php");
    exit();
}

$error = '';
$email = strtolower(trim($_SESSION['reset_email'])); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = preg_replace('/\s+/', '', $_POST['otp']); 
    
    error_log("Checking OTP: $otp for email: $email at " . date('Y-m-d H:i:s'));
    
  
    $stmt = $conn->prepare("SELECT user_id FROM password_reset_tokens 
                       WHERE otp = ? 
                       AND otp_expires_at > DATE_SUB(NOW(), INTERVAL 7 HOUR)
                       AND user_id = (SELECT id FROM users WHERE email = ?)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $otp, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Found rows: " . $result->num_rows);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['otp_verified'] = true;
        $_SESSION['reset_user_id'] = $row['user_id'];
        header("Location: reset-password.php");
        exit();
    } else {
        $debug = $conn->query("SELECT otp, otp_expires_at FROM password_reset_tokens 
                              WHERE user_id = (SELECT id FROM users WHERE email = '$email')");
        $debug_data = $debug->fetch_assoc();
        error_log("Stored OTP: " . print_r($debug_data, true));
        
        $error = "Invalid or expired OTP. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .otp-card { max-width: 500px; margin: 50px auto; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .otp-input { letter-spacing: 2px; font-size: 1.5rem; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card otp-card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center">Verify OTP</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-center">We've sent a 6-digit OTP to <?= htmlspecialchars($email) ?></p>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form action="verify-otp.php" method="POST" autocomplete="off">
                            <div class="mb-3">
                                <label for="otp" class="form-label">Enter OTP</label>
                                <input type="text" class="form-control otp-input" id="otp" name="otp" 
                                       required maxlength="6" pattern="\d{6}" 
                                       placeholder="123456" inputmode="numeric"
                                       autocomplete="one-time-code">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Verify OTP</button>
                            </div>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <p>Didn't receive OTP? <a href="request-otp.php">Resend OTP</a></p>
                            <a href="login.php" class="text-decoration-none">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>