<?php
// dbhandler.php
date_default_timezone_set('Asia/Manila');

$host = '153.92.15.6';
$db   = 'u579076463_dbvotesystem';
$user = 'u579076463_shann';
$pass = 'Votenow@2025';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sanitize input
function sanitize($data) {
    global $conn;
    return htmlspecialchars(trim($conn->real_escape_string($data)));
}

// Get schedule
function get_schedule($conn, $category) {
    $stmt = $conn->prepare("SELECT * FROM tblschedule WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    $stmt->close();

    // Auto-delete expired schedule
    if ($schedule && $schedule['status'] === 'active' && date('Y-m-d H:i:s') > $schedule['end_datetime']) {
        $delete_stmt = $conn->prepare("DELETE FROM tblschedule WHERE category = ?");
        $delete_stmt->bind_param("s", $category);
        $delete_stmt->execute();
        $delete_stmt->close();
        return null; // Return null since schedule is deleted
    }

    return $schedule;
}

// Check if student can vote
function can_vote($conn, $category) {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT * FROM tblschedule WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    $stmt->close();

    if (!$schedule) {
        return ['allowed' => false, 'reason' => 'No voting schedule has been set.'];
    }

    if ($schedule['status'] !== 'active') {
        return ['allowed' => false, 'reason' => 'Voting is currently ' . ucfirst($schedule['status']) . '.'];
    }

    if ($now < $schedule['start_datetime']) {
        return ['allowed' => false, 'reason' => 'Voting starts on ' . date('M d, Y g:i A', strtotime($schedule['start_datetime']))];
    }

    if ($now > $schedule['end_datetime']) {
        // Auto-delete schedule when ended
        $delete_stmt = $conn->prepare("DELETE FROM tblschedule WHERE category = ?");
        $delete_stmt->bind_param("s", $category);
        $delete_stmt->execute();
        $delete_stmt->close();

        return ['allowed' => false, 'reason' => 'Voting has ended.'];
    }

    return ['allowed' => true, 'reason' => ''];
}

// Map category to candidate table
function get_candidate_table($category) {
    $map = [
        'elementary' => 'tblelementarycandidate',
        'highschool' => 'tblhscandidate',
        'college'    => 'tblcollegecandidate'
    ];
    return $map[$category] ?? '';
}
?>