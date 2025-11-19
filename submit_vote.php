<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'dbhandler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Check if logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'student') {
    $_SESSION['error'] = '🔒 Please log in first';
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$category = sanitize($_POST['category'] ?? '');

// Validate category
$valid_categories = ['elementary', 'highschool', 'college'];
if (!in_array($category, $valid_categories)) {
    $_SESSION['error'] = '⚠️ Invalid voting category';
    header("Location: index.php");
    exit;
}

// Get the correct student ID from tblustudent
$stmt = $conn->prepare("SELECT id FROM tblustudent WHERE voteID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student_result) {
    $_SESSION['error'] = 'Student record not found';
    header("Location: index.php");
    exit;
}

$student_id = $student_result['id'];

// Check voting eligibility
$check_vote = can_vote($conn, $category);
if (!$check_vote['allowed']) {
    $_SESSION['error'] = $check_vote['reason'];
    header("Location: vote_$category.php");
    exit;
}

// Check if already voted
$stmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM tblvotes WHERE student_id = ? AND category = ?");
$stmt->bind_param("is", $student_id, $category);
$stmt->execute();
$vote_check = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($vote_check['vote_count'] > 0) {
    $_SESSION['error'] = '✓ Your vote has already been recorded';
    header("Location: vote_$category.php");
    exit;
}

// Get candidate table based on category
$candidate_table = get_candidate_table($category);

// Process votes (start transaction)
$conn->begin_transaction();

try {
    $success = true;
    
    // Define positions
    $positions_map = [
        'elementary' => ['President', 'Vice President', 'Secretary', 'Treasurer'],
        'highschool' => ['President', 'Vice President', 'Secretary', 'Auditor', 'Treasurer', 'PIO'],
        'college' => ['President', 'Vice President', 'Secretary', 'Auditor', 'Treasurer', 'PIO']
    ];
    
    $positions = $positions_map[$category] ?? [];
    
    foreach ($positions as $position) {
        if (!isset($_POST['vote'][$position])) {
            continue; // Skip positions with no vote submitted (no candidates)
        }

        $candidate_id = (int)$_POST['vote'][$position];
        
        // Verify candidate exists
        $stmt = $conn->prepare("SELECT id FROM $candidate_table WHERE id = ?");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception("Invalid candidate selected");
        }
        $stmt->close();
        
        // Record the vote
        $stmt = $conn->prepare("INSERT INTO tblvotes (student_id, candidate_id, position, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $student_id, $candidate_id, $position, $category);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record vote");
        }
        $stmt->close();
        
        // Increment candidate vote count
        $stmt = $conn->prepare("UPDATE $candidate_table SET votes = votes + 1 WHERE id = ?");
        $stmt->bind_param("i", $candidate_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update vote count");
        }
        $stmt->close();
    }
    
    // Mark student as voted
    $stmt = $conn->prepare("UPDATE tblustudent SET voted = TRUE WHERE voteID = ?");
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update student record");
    }
    $stmt->close();
    
    $conn->commit();
    
    $_SESSION['success'] = 'Your vote has been recorded successfully!';
    header("Location: results_$category.php");
    
}catch (Exception $e) {
    $conn->rollback();
    
    // Log full error
    $error_msg = "Vote Error [User ID: $user_id, Category: $category] - " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($error_msg);
    
    // Show user-friendly message + debug (remove in production)
    $_SESSION['error'] = 'Vote failed: ' . $e->getMessage();
    header("Location: vote_$category.php");
    exit;
}

exit;
?>