<?php
session_start();
include 'db.php';

$error = '';
// Handle login submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($password_hash);

    if ($stmt->fetch() && password_verify($password_input, $password_hash)) {
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
    <style>
        body { background-color: #f8f9fa; }
        .login-card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
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
                                       placeholder="Enter your username" 
                                       value="">
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control form-control-lg" 
                                       id="password" name="password" required 
                                       placeholder="Enter your password" 
                                       value="">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Login
                                </button>
                            </div>
                        </form>
                        <div class="mt-3 text-center">
                            <p class="mb-0">Don't have an account? 
                                <a href="register.php" class="text-decoration-none">Register here</a>
                            </p>
                            <p class="mt-2">
                                <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
