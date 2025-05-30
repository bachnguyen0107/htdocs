<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Get user info including email and verification status
$stmt = $conn->prepare("SELECT id, email, is_verified FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$stmt->bind_result($user_id, $email, $is_verified);
$stmt->fetch();
$stmt->close();

$username = $_SESSION['username'];

// Handle label operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Existing pin action handling
    if (isset($_POST['pin_action'])) {
        $note_id = intval($_POST['note_id']);
        $is_pinned = $_POST['pin_action'] === 'pin' ? 1 : 0;

        $stmt = $conn->prepare("UPDATE notes SET is_pinned = ?, pinned_at = IF(?, NOW(), NULL), updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iiii", $is_pinned, $is_pinned, $note_id, $user_id);
        $stmt->execute();

        $timestamp_stmt = $conn->prepare("SELECT updated_at, pinned_at FROM notes WHERE id = ?");
        $timestamp_stmt->bind_param("i", $note_id);
        $timestamp_stmt->execute();
        $result = $timestamp_stmt->get_result();
        $note_data = $result->fetch_assoc();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'updated_at' => $note_data['updated_at'],
            'pinned_at' => $note_data['pinned_at']
        ]);
        exit();
    }

    // New label operations
    if (isset($_POST['label_action'])) {
        $action = $_POST['label_action'];

        switch ($action) {
            case 'rename':
                $label_id = intval($_POST['label_id']);
                $new_name = trim($_POST['new_label_name']);

                if (!empty($new_name)) {
                    $stmt = $conn->prepare("UPDATE labels SET name = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("sii", $new_name, $label_id, $user_id);
                    $stmt->execute();

                    $_SESSION['success'] = "Label renamed successfully";
                }
                break;

            case 'create':
                $name = trim($_POST['label_name']);
                $color =  '#6c757d';

                if (!empty($name)) {
                    $stmt = $conn->prepare("INSERT INTO labels (user_id, name, color) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $user_id, $name, $color);
                    $stmt->execute();
                }
                break;

            case 'update':
                $label_id = intval($_POST['label_id']);
                $name = trim($_POST['label_name']);
                $color = $_POST['label_color'] ?? '#6c757d';

                $stmt = $conn->prepare("UPDATE labels SET name = ?, color = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ssii", $name, $color, $label_id, $user_id);
                $stmt->execute();
                break;

            case 'delete':
                $label_id = intval($_POST['label_id']);

                $conn->begin_transaction();

                try {
                    // First, remove all associations with this label
                    $stmt = $conn->prepare("DELETE FROM note_labels WHERE label_id = ?");
                    $stmt->bind_param("i", $label_id);
                    $stmt->execute();

                    // Then delete the label itself
                    $stmt = $conn->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $label_id, $user_id);
                    $stmt->execute();

                    $conn->commit();

                    $_SESSION['success'] = "Label deleted successfully";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error deleting label: " . $e->getMessage();
                }
                break;

            case 'assign':
                $note_id = intval($_POST['note_id']);
                $label_ids = isset($_POST['label_ids']) ? $_POST['label_ids'] : [];

                // Start transaction
                $conn->begin_transaction();

                try {
                    // Remove all current labels
                    $stmt = $conn->prepare("DELETE FROM note_labels WHERE note_id = ?");
                    $stmt->bind_param("i", $note_id);
                    $stmt->execute();

                    // Add new labels
                    if (!empty($label_ids)) {
                        $stmt = $conn->prepare("INSERT INTO note_labels (note_id, label_id) VALUES (?, ?)");
                        foreach ($label_ids as $label_id) {
                            $stmt->bind_param("ii", $note_id, $label_id);
                            $stmt->execute();
                        }
                    }

                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                }
                break;
        }

        header("Location: home.php");
        exit();
    }

    // Existing note save handling
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $note_id = $_POST['note_id'] ?? null;

    if (!empty($title)) {
        if ($note_id) {
            $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssii", $title, $content, $note_id, $user_id);
        } else {
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

// Get user preferences
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

// Get all user labels
$labels_stmt = $conn->prepare("SELECT id, name, color FROM labels WHERE user_id = ? ORDER BY name");
$labels_stmt->bind_param("i", $user_id);
$labels_stmt->execute();
$user_labels = $labels_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

//query
$notes = $conn->prepare("
    SELECT n.id, n.title, n.content, n.updated_at, n.is_pinned, n.pinned_at, 
           n.is_password_protected,  /* Add this */
           GROUP_CONCAT(l.id) AS label_ids,
           GROUP_CONCAT(l.name) AS label_names,
           GROUP_CONCAT(l.color) AS label_colors
    FROM notes n
    LEFT JOIN note_labels nl ON n.id = nl.note_id
    LEFT JOIN labels l ON nl.label_id = l.id
    WHERE n.user_id = ?
    GROUP BY n.id
    ORDER BY n.is_pinned DESC, 
             CASE WHEN n.is_pinned = 1 THEN n.pinned_at ELSE n.id END DESC
");
$notes->bind_param("i", $user_id);
$notes->execute();
$all_notes = $notes->get_result()->fetch_all(MYSQLI_ASSOC);

// Process notes to create label arrays
foreach ($all_notes as &$note) {
    $note['labels'] = [];
    if ($note['label_ids']) {
        $ids = explode(',', $note['label_ids']);
        $names = explode(',', $note['label_names']);
        $colors = explode(',', $note['label_colors']);

        for ($i = 0; $i < count($ids); $i++) {
            $note['labels'][] = [
                'id' => $ids[$i],
                'name' => $names[$i],
                'color' => $colors[$i]
            ];
        }
    }
}
unset($note); // Break the reference


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
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>

        <?php if (!$is_verified): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                ⚠️ Your email is not verified. Please check your inbox to verify your email address.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

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

        <div class="row">
            <!-- Labels sidebar -->
            <div class="col-md-3">
                <div class="labels-sidebar">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Labels</h4>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createLabelModal">
                            + New
                        </button>
                    </div>

                    <div class="label-filters">
                        <div class="label-filter-item active" data-label-id="all">
                            <div class="label-color-preview" style="background-color: #6c757d"></div>
                            All Notes
                            <span class="badge bg-secondary ms-auto"><?= count($all_notes) ?></span>
                        </div>
                        <?php foreach ($user_labels as $label):
                            $note_count = 0;
                            foreach ($all_notes as $note) {
                                if (in_array($label['id'], array_column($note['labels'], 'id'))) {
                                    $note_count++;
                                }
                            }
                        ?>
                            <div class="label-filter-item d-flex justify-content-between align-items-center" data-label-id="<?= $label['id'] ?>">
                                <div class="d-flex align-items-center">
                                    <div class="label-color-preview" style="background-color: <?= $label['color'] ?>"></div>
                                    <?= htmlspecialchars($label['name']) ?>
                                    <span class="badge bg-secondary ms-2"><?= $note_count ?></span>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary rename-label-btn me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#renameLabelModal"
                                        data-label-id="<?= $label['id'] ?>"
                                        data-label-name="<?= htmlspecialchars($label['name']) ?>"
                                        title="Rename label">
                                        ✏️
                                    </button>
                                    <form method="POST" action="home.php" style="display: inline;">
                                        <input type="hidden" name="label_action" value="delete">
                                        <input type="hidden" name="label_id" value="<?= $label['id'] ?>">
                                        <button type="submit" class="delete-label-btn" title="Delete label">×</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Notes content -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between mb-3">
                    <h3>Your Notes</h3>
                    <div>
                        <div class="search-container">
                            <input type="text" id="searchInput" class="form-control me-2" placeholder="Search notes..." style="width: 200px; padding-right: 30px;">
                            <span id="clearSearch" class="position-absolute top-50 end-0 translate-middle-y me-2" style="cursor: pointer; display: none;">&times;</span>
                        </div>
                        <a href="note-create.php" class="btn btn-primary me-2">New Note</a>
                        <button class="btn btn-outline-secondary view-toggle active" data-view="grid">Grid</button>
                        <button class="btn btn-outline-secondary view-toggle" data-view="list">List</button>
                    </div>
                </div>

                <?php if (empty($all_notes)): ?>
                    <div class="empty-notes text-center py-5">
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
                                data-updated-at="<?= $note['updated_at'] ?>"
                                data-labels="<?= implode(',', array_column($note['labels'], 'id')) ?>"
                                <?= $note['is_pinned'] ? 'data-pinned-at="' . $note['pinned_at'] . '"' : '' ?>>
                                <div class="card h-100">
                                    <div class="card-body position-relative">
                                        <?php if ($note['is_pinned']): ?>
                                            <span class="badge bg-warning text-dark mb-2">Pinned</span>
                                        <?php endif; ?>
                                        <?php if ($note['is_password_protected']): ?>
                                            <span class="badge bg-info text-dark mb-2">🔒 Protected</span>
                                        <?php endif; ?>

                                        <!-- Labels display -->
                                        <div class="note-labels">
                                            <?php foreach ($note['labels'] as $label): ?>
                                                <span class="badge label-badge" style="background-color: <?= $label['color'] ?>">
                                                    <?= htmlspecialchars($label['name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>

                                        <h5 class="card-title"><?= htmlspecialchars($note['title']) ?></h5>
                                        <p class="card-text text-truncate"><?= nl2br(htmlspecialchars($note['content'])) ?></p>

                                        <div class="note-actions">
                                            <a href="view-note.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary manage-labels-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#manageLabelsModal"
                                                    data-note-id="<?= $note['id'] ?>">
                                                    Labels
                                                </button>
                                                <button class="btn btn-sm <?= $note['is_pinned'] ? 'btn-warning' : 'btn-outline-secondary' ?> pin-btn" onclick="togglePin(this, <?= $note['id'] ?>)">
                                                    <?= $note['is_pinned'] ? 'Unpin' : 'Pin' ?>
                                                    <span class="pin-icon">📌</span>
                                                </button>
                                                <a href="delete-note.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Are you sure you want to delete this note?')">
                                                    Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Label Modal -->
    <div class="modal fade" id="createLabelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Label</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="home.php">
                    <div class="modal-body">
                        <input type="hidden" name="label_action" value="create">
                        <div class="mb-3">
                            <label for="labelName" class="form-label">Label Name</label>
                            <input type="text" class="form-control" id="labelName" name="label_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Label</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Labels Modal -->
    <div class="modal fade" id="manageLabelsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Note Labels</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="home.php">
                    <div class="modal-body">
                        <input type="hidden" name="label_action" value="assign">
                        <input type="hidden" id="manageNoteId" name="note_id">

                        <h6>Available Labels</h6>
                        <div class="available-labels">
                            <?php if (empty($user_labels)): ?>
                                <p class="text-muted">No labels created yet. Create one first.</p>
                            <?php else: ?>
                                <?php foreach ($user_labels as $label): ?>
                                    <div class="form-check py-2">
                                        <input class="form-check-input" type="checkbox"
                                            id="manage-label-<?= $label['id'] ?>"
                                            name="label_ids[]"
                                            value="<?= $label['id'] ?>">
                                        <label class="form-check-label d-flex align-items-center" for="manage-label-<?= $label['id'] ?>">
                                            <span class="badge me-2" style="background-color: <?= $label['color'] ?>; width: 20px; height: 20px;"></span>
                                            <?= htmlspecialchars($label['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="renameLabelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rename Label</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="home.php">
                    <div class="modal-body">
                        <input type="hidden" name="label_action" value="rename">
                        <input type="hidden" id="renameLabelId" name="label_id">
                        <div class="mb-3">
                            <label for="newLabelName" class="form-label">New Label Name</label>
                            <input type="text" class="form-control" id="newLabelName" name="new_label_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Rename Label</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="home.js"></script>
</body>

</html>