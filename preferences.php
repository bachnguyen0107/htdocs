<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get user ID
$user = $conn->prepare("SELECT id FROM users WHERE username = ?");
$user->bind_param("s", $_SESSION['username']);
$user->execute();
$user_result = $user->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data['id'];

// Get current preferences
$pref_stmt = $conn->prepare("SELECT theme, font_size, note_color FROM user_preferences WHERE user_id = ?");
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$pref_result = $pref_stmt->get_result();
$preferences = $pref_result->fetch_assoc();

// Set defaults if no preferences exist
if (!$preferences) {
    $preferences = [
        'theme' => 'light',
        'font_size' => 'medium',
        'note_color' => 'default'
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = $_POST['theme'] ?? 'light';
    $font_size = $_POST['font_size'] ?? 'medium';
    $note_color = $_POST['note_color'] ?? 'default';
    
    // Insert or update preferences
    $stmt = $conn->prepare("INSERT INTO user_preferences (user_id, theme, font_size, note_color) 
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE 
                           theme = VALUES(theme), 
                           font_size = VALUES(font_size), 
                           note_color = VALUES(note_color)");
    $stmt->bind_param("isss", $user_id, $theme, $font_size, $note_color);
    $stmt->execute();
    
    $_SESSION['success'] = "Preferences saved successfully!";
    
    header("Location: preferences.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $preferences['theme'] ?>">
<head>
    <meta charset="UTF-8">
    <title>User Preferences</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .preview-note {
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .font-small {
            font-size: 14px;
        }
        .font-medium {
            font-size: 16px;
        }
        .font-large {
            font-size: 18px;
        }
        .color-default {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .color-blue {
            background-color: #e7f5ff;
            border: 1px solid #a5d8ff;
        }
        .color-green {
            background-color: #ebfbee;
            border: 1px solid #b2f2bb;
        }
        .color-yellow {
            background-color: #fff9db;
            border: 1px solid #ffec99;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h2 class="mb-4">User Preferences</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Display Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Theme</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="themeLight" 
                                   value="light" <?= $preferences['theme'] === 'light' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="themeLight">Light</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="themeDark" 
                                   value="dark" <?= $preferences['theme'] === 'dark' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="themeDark">Dark</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Font Size</label>
                        <select class="form-select" name="font_size">
                            <option value="small" <?= $preferences['font_size'] === 'small' ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= $preferences['font_size'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= $preferences['font_size'] === 'large' ? 'selected' : '' ?>>Large</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Note Color</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="note_color" id="colorDefault" 
                                       value="default" <?= $preferences['note_color'] === 'default' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="colorDefault">Default</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="note_color" id="colorBlue" 
                                       value="blue" <?= $preferences['note_color'] === 'blue' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="colorBlue">Blue</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="note_color" id="colorGreen" 
                                       value="green" <?= $preferences['note_color'] === 'green' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="colorGreen">Green</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="note_color" id="colorYellow" 
                                       value="yellow" <?= $preferences['note_color'] === 'yellow' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="colorYellow">Yellow</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Preview</h5>
                </div>
                <div class="card-body">
                    <div class="preview-note <?= 'color-' . $preferences['note_color'] ?> <?= 'font-' . $preferences['font_size'] ?>">
                        <h5>Sample Note Title</h5>
                        <p>This is a preview of how your notes will look with your selected preferences.</p>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="home.php" class="btn btn-secondary">Back to Notes</a>
                <button type="submit" class="btn btn-primary">Save Preferences</button>
            </div>
        </form>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live preview updates
        document.querySelectorAll('input[name="theme"], input[name="note_color"], select[name="font_size"]').forEach(el => {
            el.addEventListener('change', updatePreview);
        });
        
        function updatePreview() {
            // Update theme preview
            document.documentElement.setAttribute('data-bs-theme', document.querySelector('input[name="theme"]:checked').value);
            
            // Update note color preview
            const previewNote = document.querySelector('.preview-note');
            previewNote.className = 'preview-note';
            previewNote.classList.add('color-' + document.querySelector('input[name="note_color"]:checked').value);
            previewNote.classList.add('font-' + document.querySelector('select[name="font_size"]').value);
        }
    </script>
</body>
</html>