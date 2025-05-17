<?php
session_start();
include 'db.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($user_id, $password_hash);

    if ($stmt->fetch() && password_verify($password_input, $password_hash)) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $stmt->close();
        $conn->close();
        header("Location: home.php");
        exit();
    } else {
        $error = 'Invalid credentials. Please try again.';
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="assets/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .login-card { 
            border-radius: 10px; 
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 500px;
        }
        .reset-option-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .reset-option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="card login-card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center mb-0">Login</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control form-control-lg" 
                                       id="username" name="username" required 
                                       placeholder="Enter your username">
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control form-control-lg" 
                                       id="password" name="password" required 
                                       placeholder="Enter your password">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                        </form>
                        <div class="mt-3 text-center">
                            <p class="mb-0">Don't have an account? 
                                <a href="register.php" class="text-decoration-none">Register here</a>
                            </p>
                            <p class="mt-2">
                                <button class="btn btn-link text-decoration-none p-0" 
                                        data-bs-toggle="modal" data-bs-target="#resetOptionsModal">
                                    Forgot password?
                                </button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Options Modal -->
    <div class="modal fade" id="resetOptionsModal" tabindex="-1" aria-labelledby="resetOptionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="resetOptionsModalLabel">Reset Password Options</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card reset-option-card h-100 text-center" 
                                 onclick="window.location.href='request-otp.php'">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <i class="fas fa-mobile-alt fa-3x text-primary"></i>
                                    </div>
                                    <h5 class="card-title">Reset with OTP</h5>
                                    <p class="card-text">Get a one-time password sent to your email</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card reset-option-card h-100 text-center" 
                                 onclick="window.location.href='forgot-password.php'">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <i class="fas fa-link fa-3x text-primary"></i>
                                    </div>
                                    <h5 class="card-title">Reset with Link</h5>
                                    <p class="card-text">Get a secure link sent to your email</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>