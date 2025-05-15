<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

include 'db.php';

// Get user info
$stmt = $conn->prepare("SELECT id, email FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$stmt->bind_result($user_id, $email);
$stmt->fetch();
$stmt->close();

$username = $_SESSION['username'];

$pref_stmt = $conn->prepare("SELECT theme, font_size, note_color FROM user_preferences WHERE user_id = ?");
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$pref_result = $pref_stmt->get_result();
$preferences = $pref_result->fetch_assoc();

if (!$preferences) {
    $preferences = [
        'theme' => 'light',
        'font_size' => 'medium',
        'note_color' => 'default'
    ];
}

// Handle note operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['pin_action'])) {
        // Handle pin/unpin action
        $note_id = intval($_POST['note_id']);
        $is_pinned = $_POST['pin_action'] === 'pin' ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE notes SET is_pinned = ?, pinned_at = IF(?, NOW(), NULL) WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iiii", $is_pinned, $is_pinned, $note_id, $user_id);
        $stmt->execute();
        echo "success";
        exit();
    }
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $note_id = $_POST['note_id'] ?? null;
    
    if (!empty($title)) {
        if ($note_id) {
            // Update existing note
            $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssii", $title, $content, $note_id, $user_id);
        } else {
            // Create new note
            $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $title, $content);
        }
        $stmt->execute();
        
        if (!$note_id) {
            $note_id = $conn->insert_id;
        }
        echo "note_id=$note_id";
        exit();
    }
}

// Get all notes for the user with proper sorting
$notes = $conn->prepare("
    SELECT id, title, content, updated_at, is_pinned, pinned_at, created_at 
    FROM notes 
    WHERE user_id = ? 
    ORDER BY 
        CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END,  
        pinned_at DESC,                             
        updated_at DESC                            
");
$notes->bind_param("i", $user_id);
$notes->execute();
$notes_result = $notes->get_result();
$all_notes = $notes_result->fetch_all(MYSQLI_ASSOC);



$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notes App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="home.css" rel="stylesheet">
    <style>
        .pinned-note {
            border-left: 4px solid #ffc107;
        }
        .pin-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            opacity: 0.7;
            transition: opacity 0.2s;
            z-index: 1;
        }
        .note-card:hover .pin-btn {
            opacity: 1;
        }
        .pinned-note .pin-btn {
            opacity: 1;
            background-color: #ffc107;
            border-color: #ffc107;
        }
        .pin-icon {
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- User Dropdown -->
        <div class="d-flex justify-content-end mb-4">
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                    <?= htmlspecialchars($username) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><a class="dropdown-item" href="preferences.php">Preferences</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="container">
            <!-- Header -->
            <div class="d-flex justify-content-between mb-3">
                <h3>Your Notes</h3>
                <div>
                <div class="position-relative" style="display: inline-block;">
    <input type="text" id="searchInput" class="form-control me-2" placeholder="Search notes..." style="width: 200px; padding-right: 30px;">
    <span id="clearSearch" class="position-absolute top-50 end-0 translate-middle-y me-2" style="cursor: pointer; display: none;">&times;</span>
</div>
                    <a href="note-create.php" class="btn btn-primary me-2">New Note</a>
                    <button class="btn btn-outline-secondary view-toggle active" data-view="grid">Grid</button>
                    <button class="btn btn-outline-secondary view-toggle" data-view="list">List</button>
                </div>
            </div>
    
            <?php if (empty($all_notes)): ?>
                <div class="empty-notes">
                    <h4>You don't have any notes yet</h4>
                    <p class="text-muted mb-4">Start by creating your first note</p>
                    <a href="note-create.php" class="btn btn-primary">Create Your First Note</a>
                </div>
            <?php else: ?>
                <div class="row" id="notesContainer">
                <?php foreach ($all_notes as $note): ?>
    <div class="col-md-4 mb-3 note-card <?= $note['is_pinned'] ? 'pinned-note' : '' ?>" 
         data-view="grid" 
         data-note-id="<?= $note['id'] ?>"
         data-updated-time="<?= $note['updated_at'] ?>"
         <?= $note['is_pinned'] ? 'data-pinned-time="' . $note['pinned_at'] . '"' : '' ?>>
        <div class="card h-100">
            <div class="card-body">
                <?php if ($note['is_pinned']): ?>
                    <span class="badge bg-warning text-dark mb-2">Pinned</span>
                <?php endif; ?>
                <h5 class="card-title"><?= htmlspecialchars($note['title']) ?></h5>
                <p class="card-text text-truncate"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                <div class="d-flex justify-content-between">
                    <a href="view-note.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                    <a href="delete-note.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Are you sure you want to delete this note?')">
                        Delete
                    </a>
                </div>
                <button class="pin-btn btn btn-sm <?= $note['is_pinned'] ? 'btn-warning' : 'btn-outline-secondary' ?>" onclick="togglePin(this, <?= $note['id'] ?>)">
                    <?= $note['is_pinned'] ? 'Unpin' : 'Pin' ?>
                    <span class="pin-icon">📌</span>
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    
        <script src="assets/js/bootstrap.bundle.min.js"></script>
        <script>
            const toggleButtons = document.querySelectorAll('.view-toggle');
            const notesContainer = document.getElementById('notesContainer');
    
            toggleButtons.forEach(button => {
                button.addEventListener('click', () => {
                    toggleButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    const view = button.getAttribute('data-view');
                    if (view === 'list') {
                        notesContainer.classList.add('list-group');
                        document.querySelectorAll('.note-card').forEach(card => {
                            card.classList.remove('col-md-4');
                            card.classList.add('list-group-item');
                        });
                    } else {
                        notesContainer.classList.remove('list-group');
                        document.querySelectorAll('.note-card').forEach(card => {
                            card.classList.add('col-md-4');
                            card.classList.remove('list-group-item');
                        });
                    }
                });
            });

            function togglePin(button, noteId) {
        const noteCard = button.closest('.note-card');
        const isPinned = noteCard.classList.contains('pinned-note');
        const action = isPinned ? 'unpin' : 'pin';
        const now = new Date().toISOString();
        
        fetch('home.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `note_id=${noteId}&pin_action=${action}`
        })
        .then(response => response.text())
        .then(data => {
            if (data === 'success') {
                // Update the timestamp attribute first
                if (action === 'pin') {
                    noteCard.setAttribute('data-pinned-time', now);
                } else {
                    noteCard.removeAttribute('data-pinned-time');
                    
                    noteCard.setAttribute('data-updated-time', now);
                }
                

                noteCard.classList.toggle('pinned-note');
                
                
                if (action === 'pin') {
                    button.innerHTML = 'Unpin <span class="pin-icon">📌</span>';
                    button.classList.remove('btn-outline-secondary');
                    button.classList.add('btn-warning');
                    // Add pinned badge if not exists
                    if (!noteCard.querySelector('.badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-warning text-dark mb-2';
                        badge.textContent = 'Pinned';
                        noteCard.querySelector('.card-body').insertBefore(badge, noteCard.querySelector('.card-title'));
                    }
                } else {
                    button.innerHTML = 'Pin <span class="pin-icon">📌</span>';
                    button.classList.remove('btn-warning');
                    button.classList.add('btn-outline-secondary');
                    // Remove pinned badge
                    const badge = noteCard.querySelector('.badge');
                    if (badge) {
                        badge.remove();
                    }
                }
                
                // Reorder all notes
                reorderNotes();
            }
        });
    }

    function reorderNotes() {
        const container = document.getElementById('notesContainer');
        const allNotes = Array.from(container.querySelectorAll('.note-card'));
        
        
        allNotes.sort((a, b) => {
            const aPinned = a.classList.contains('pinned-note');
            const bPinned = b.classList.contains('pinned-note');
            

            if (aPinned && bPinned) {
                const aTime = a.getAttribute('data-pinned-time') || '0';
                const bTime = b.getAttribute('data-pinned-time') || '0';
                return bTime.localeCompare(aTime);
            }
            else if (aPinned && !bPinned) {
                return -1;
            }
            else if (!aPinned && bPinned) {
                return 1;
            }
            
            else {
                const aTime = a.getAttribute('data-updated-time') || '0';
                const bTime = b.getAttribute('data-updated-time') || '0';
                return bTime.localeCompare(aTime);
            }
        });
        
        
        container.innerHTML = '';
        
        
        allNotes.forEach(note => container.appendChild(note));
    }
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');
    let searchTimeout;

// Clear button functionality
clearSearch.addEventListener('click', function() {
    searchInput.value = '';
    this.style.display = 'none';
    // Trigger the search to show all notes
    const event = new Event('input');
    searchInput.dispatchEvent(event);
    searchInput.focus();
});

// Search functionality
searchInput.addEventListener('input', function() {
    clearSearch.style.display = this.value ? '' : 'none';
    
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = this.value.toLowerCase().trim();
        
        if (searchTerm === '') {
            // If search is empty, show all notes
            document.querySelectorAll('.note-card').forEach(card => {
                card.style.display = '';
            });
            reorderNotes();
            return;
        }
        
        // Search both title and content
        document.querySelectorAll('.note-card').forEach(card => {
            const title = card.querySelector('.card-title').textContent.toLowerCase();
            const content = card.querySelector('.card-text').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || content.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Reorder visible notes
        reorderNotes();
    }, 300);
});


        </script>
</body>
</html>