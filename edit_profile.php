<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get current user data
$stmt = $conn->prepare("SELECT full_name, bio, avatar FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
            // Delete old avatar if not default
            if ($user['avatar'] !== 'default.jpg') {
                @unlink($upload_dir . $user['avatar']);
            }
            $avatar = $new_filename;
        }
    } else {
        $avatar = $user['avatar'];
    }
    
    // Update database
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, bio = ?, avatar = ? WHERE username = ?");
    $stmt->bind_param("ssss", $full_name, $bio, $avatar, $_SESSION['username']);
    $stmt->execute();
    $stmt->close();
    
    header("Location: profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <style>
        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Edit Profile</h2>
        <form action="edit_profile.php" method="post" enctype="multipart/form-data">
            
            <!-- Current Avatar Preview -->
            <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" 
                 class="avatar-preview" id="avatarPreview">
            
            <!-- Avatar Upload -->
            <div>
                <label for="avatar">Change Avatar:</label>
                <input type="file" name="avatar" id="avatar" accept="image/*">
            </div>
            
            <!-- Full Name -->
            <div>
                <label for="full_name">Full Name:</label>
                <input type="text" name="full_name" id="full_name" 
                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
            </div>
            
            <!-- Bio -->
            <div>
                <label for="bio">Bio:</label>
                <textarea name="bio" id="bio" rows="4"><?php 
                    echo htmlspecialchars($user['bio'] ?? ''); 
                ?></textarea>
            </div>
            
            <!-- Submit Button -->
            <button type="submit">Save Changes</button>
            <a href="profile.php">Cancel</a>
        </form>
    </div>

    <script>
        // Preview avatar before upload
        document.getElementById('avatar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>