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



// Handle note creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (!empty($title)) {
        $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $content);
        $stmt->execute();
        $note_id = $conn->insert_id;
        
        echo json_encode(['status' => 'success', 'note_id' => $note_id]);
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Note</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .note-editor {
            max-width: 800px;
            margin: 0 auto;
        }
        .auto-save-status {
            font-size: 0.8rem;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Create New Note</h2>
            <a href="home.php" class="btn btn-outline-secondary">Back to Notes</a>
        </div>
        
        <div class="card note-editor">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">New Note</h5>
            </div>
            <div class="card-body">
                <form id="noteForm">
                    <div class="mb-3">
                        <label for="noteTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="noteTitle" name="title" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="noteContent" class="form-label">Content</label>
                        <textarea class="form-control" id="noteContent" name="content" rows="10"></textarea>
                    </div>
                    <div class="auto-save-status text-end mb-2">
                        <span id="saveStatus">Changes will be saved automatically</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        let saveTimeout;
        const form = document.getElementById('noteForm');
        const saveStatus = document.getElementById('saveStatus');
        
        form.addEventListener('input', () => {
            clearTimeout(saveTimeout);
            saveStatus.textContent = 'Saving...';
            saveStatus.style.opacity = '1';
            
            saveTimeout = setTimeout(() => {
                const formData = new FormData(form);
                
                fetch('note-create.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        saveStatus.textContent = 'Note saved successfully!';
                        setTimeout(() => {
                            window.location.href = 'home.php';
                        }, 1500);
                    }
                });
            }, 1000);
        });


        document.getElementById('noteTitle').focus();
    </script>
</body>
</html>
