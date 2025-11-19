<?php
session_start();
require_once 'dbhandler.php';

$category = 'highschool';
$candidate_table = get_candidate_table($category);

// Handle candidate deletion
if (isset($_GET['delete_candidate']) && is_numeric($_GET['delete_candidate'])) {
    $id = (int)$_GET['delete_candidate'];
    $stmt = $conn->prepare("DELETE FROM $candidate_table WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Candidate deleted successfully';
    } else {
        $_SESSION['error'] = 'Failed to delete candidate';
    }
    $stmt->close();
    header("Location: admin_highschool.php");
    exit;
}

// Handle schedule creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_schedule') {
        $start_datetime = sanitize($_POST['start_datetime']);
        $end_datetime = sanitize($_POST['end_datetime']);
        $status = sanitize($_POST['status']);

        $check_stmt = $conn->prepare("SELECT id FROM tblschedule WHERE category = ?");
        $check_stmt->bind_param("s", $category);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            $stmt = $conn->prepare("UPDATE tblschedule SET start_datetime = ?, end_datetime = ?, status = ? WHERE category = ?");
            $stmt->bind_param("ssss", $start_datetime, $end_datetime, $status, $category);
        } else {
            $admin_id = $_SESSION['user_id'] ?? null;
            if ($admin_id) {
                // Check if admin_id exists in tbladmin
                $check_admin = $conn->prepare("SELECT id FROM tbladmin WHERE id = ?");
                $check_admin->bind_param("i", $admin_id);
                $check_admin->execute();
                $admin_exists = $check_admin->get_result()->fetch_assoc();
                $check_admin->close();
                if (!$admin_exists) {
                    $admin_id = null;
                }
            }
            if ($admin_id) {
                $stmt = $conn->prepare("INSERT INTO tblschedule (category, start_datetime, end_datetime, status, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $category, $start_datetime, $end_datetime, $status, $admin_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO tblschedule (category, start_datetime, end_datetime, status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $category, $start_datetime, $end_datetime, $status);
            }
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Schedule updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update schedule';
        }
        $stmt->close();
        header("Location: admin_highschool.php");
        exit;
    } elseif ($_POST['action'] === 'delete_schedule') {
        $stmt = $conn->prepare("DELETE FROM tblschedule WHERE category = ?");
        $stmt->bind_param("s", $category);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Schedule deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete schedule';
        }
        $stmt->close();
        header("Location: admin_highschool.php");
        exit;
    }
}

// Get all candidates
$candidates = [];
$stmt = $conn->prepare("SELECT * FROM $candidate_table ORDER BY position ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[$row['position']][] = $row;
}
$stmt->close();

// Get schedule
$schedule = get_schedule($conn, $category);

// Auto-delete expired schedule on page load
if ($schedule && $schedule['status'] === 'active' && date('Y-m-d H:i:s') > $schedule['end_datetime']) {
    $delete_stmt = $conn->prepare("DELETE FROM tblschedule WHERE category = ?");
    $delete_stmt->bind_param("s", $category);
    $delete_stmt->execute();
    $delete_stmt->close();
    $schedule = null; // Set to null since deleted
    $_SESSION['success'] = 'Schedule automatically deleted as voting period has ended.';
}

// Get total voters
$stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as total_voters FROM tblvotes WHERE category = ?");
$stmt->bind_param("s", $category);
$stmt->execute();
$voter_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get stats
$stmt = $conn->prepare("SELECT COUNT(*) as total_students FROM tblustudent WHERE role = ?");
$stmt->bind_param("s", $category);
$stmt->execute();
$total_students = $stmt->get_result()->fetch_assoc()['total_students'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total_candidates FROM $candidate_table");
$stmt->execute();
$total_candidates = $stmt->get_result()->fetch_assoc()['total_candidates'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo ucfirst($category); ?></title>
    <link rel="stylesheet" href="style_admin.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo-section">
                <img src="mseuflogo.png" alt="MSEU Logo" width="60">
            </div>
            <h1>MSEUF Online Voting System - highschool</h1>
            <div class="user-section">
                <span><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </nav>
    </header>

    <main class="admin-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="admin-tabs">
            <button class="tab-btn active" onclick="showTab('candidates')">Candidates</button>
            <button class="tab-btn" onclick="showTab('schedule')">Schedule</button>
            <button class="tab-btn" onclick="showTab('results')">Results</button>
        </div>

        <!-- Candidates Tab -->
        <div id="candidates" class="tab-content active">
            <div class="tab-header">
                <h2>Manage Candidates</h2>
                <button class="btn-primary" onclick="openModal('addCandidateModal')">+ Add Candidate</button>
            </div>
            <table class="admin-table">
                <thead>
                    <tr><th>Photo</th><th>Name</th><th>Position</th><th>Partylist</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $position => $cands): ?>
                        <?php foreach ($cands as $cand): ?>
                        <tr>
                            <td>
                                <?php if ($cand['photo']): ?>
                                    <img src="data:image/jpeg;base64,<?php echo base64_encode($cand['photo']); ?>" class="candidate-thumb">
                                <?php else: ?>
                                    <span class="no-photo">No Photo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($cand['firstname'] . ' ' . $cand['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($cand['position']); ?></td>
                            <td><?php echo htmlspecialchars($cand['partylist']); ?></td>
                            <td>
                                <a href="update_candidate.php?id=<?php echo $cand['id']; ?>&category=<?php echo $category; ?>" class="btn-edit">Edit</a>
                                <a href="?delete_candidate=<?php echo $cand['id']; ?>" class="btn-danger" onclick="return confirm('Delete candidate?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Schedule Tab -->
        <div id="schedule" class="tab-content">
            <h2>Election Schedule</h2>
            <form action="" method="POST" class="schedule-form">
                <input type="hidden" name="action" value="save_schedule">
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date & Time *</label>
                        <input type="datetime-local" name="start_datetime" value="<?php echo $schedule ? date('Y-m-d\TH:i', strtotime($schedule['start_datetime'])) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date & Time *</label>
                        <input type="datetime-local" name="end_datetime" value="<?php echo $schedule ? date('Y-m-d\TH:i', strtotime($schedule['end_datetime'])) : ''; ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="inactive" <?php echo ($schedule['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="active" <?php echo ($schedule['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo ($schedule['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save Schedule</button>
                </div>
            </form>
            <?php if ($schedule): ?>
                <div class="current-schedule">
                    <h3>Current Schedule</h3>
                    <p><strong>Start:</strong> <?php echo date('M d, Y g:i A', strtotime($schedule['start_datetime'])); ?></p>
                    <p><strong>End:</strong> <?php echo date('M d, Y g:i A', strtotime($schedule['end_datetime'])); ?></p>
                    <p><strong>Status:</strong> <span class="status-<?php echo $schedule['status']; ?>"><?php echo ucfirst($schedule['status']); ?></span></p>
                </div>
                <script>
                    // Auto-delete schedule when end time is reached
                    const endTime = new Date('<?php echo $schedule['end_datetime']; ?>').getTime();
                    const now = new Date().getTime();
                    const timeUntilEnd = endTime - now;

                    if (timeUntilEnd > 0) {
                        setTimeout(() => {
                            if (confirm('The voting period has ended. The schedule will be automatically deleted.')) {
                                deleteSchedule();
                            }
                        }, timeUntilEnd);
                    } else if (timeUntilEnd <= 0 && '<?php echo $schedule['status']; ?>' === 'active') {
                        // If page loaded after end time and status is active, delete immediately
                        if (confirm('The voting period has ended. The schedule will be automatically deleted.')) {
                            deleteSchedule();
                        }
                    }
                </script>
            <?php endif; ?>
        </div>

        <!-- Results Tab -->
        <div id="results" class="tab-content">
            <h2>Voting Results</h2>
            <div class="stats-grid">
                <div class="stat-card"><h3>Total Students</h3><p><?php echo $total_students; ?></p></div>
                <div class="stat-card"><h3>Total Voted</h3><p><?php echo $voter_info['total_voters']; ?></p></div>
                <div class="stat-card"><h3>Total Candidates</h3><p><?php echo $total_candidates; ?></p></div>
            </div>
            <div class="results-actions">
                <a href="results_highschool.php" class="btn-primary">View Detailed Results</a>
        </div>
    </main>

    <!-- Add Candidate Modal -->
    <div id="addCandidateModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addCandidateModal')">×</span>
            <h2>Add New Candidate</h2>
            <form action="add_candidate.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="category" value="highschool">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="firstname" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="lastname" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Position *</label>
                    <select name="position" required>
                        <option value="">Select Position</option>
                        <?php
                        $positions = ['President', 'Vice President', 'Secretary', 'Auditor', 'Treasurer', 'PIO'];
                        foreach ($positions as $pos): ?>
                            <option value="<?php echo $pos; ?>"><?php echo $pos; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Partylist</label>
                    <input type="text" name="partylist">
                </div>
                <div class="form-group">
                    <label>Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>
                <button type="submit" class="btn-primary">Add Candidate</button>
            </form>
        </div>
    </div>

    <script src="admin.js"></script>
</body>
</html>