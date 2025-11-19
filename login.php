

<?php
session_start();
include "dbhandler.php";

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$username = sanitize($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    $_SESSION['error'] = ' Please enter your ID and password';
    header("Location: index.php");
    exit;
}

// Check student (match actual table columns: voteID, userID, password, firstname, lastname, grade, role)
$stmt = $conn->prepare("SELECT voteID, userID, password, firstname, lastname, role FROM tblustudent WHERE userID = ? LIMIT 1");
if (!$stmt) {
    $_SESSION['error'] = ' System issue - please try again';
    header("Location: index.php");
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Use plain-text comparison if passwords are stored unhashed in the DB
if ($student && $password === $student['password']) {
    // Student login successful
    $_SESSION['userID'] = $student['userID'];
    $_SESSION['user_id'] = $student['voteID'];
    $_SESSION['fullname'] = trim(($student['firstname'] ?? '') . ' ' . ($student['lastname'] ?? ''));
    $_SESSION['role'] = $student['role'];
    $_SESSION['type'] = 'student';

    // Redirect based on role
    if ($student['role'] === 'elementary') {
        header("Location: vote_elementary.php");
    } elseif ($student['role'] === 'highschool' || $student['role'] === 'hs') {
        header("Location: vote_highschool.php");
    } else {
        header("Location: vote_college.php");
    }
    exit;
}

// Check admin
$stmt = $conn->prepare("SELECT  userID, password, role, fullname FROM tbladmin WHERE userID = ? LIMIT 1");
if (!$stmt) {
    $_SESSION['error'] = ' System issue - please try again';
    header("Location: index.php");
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($admin) {
    // If passwords are not hashed, compare plain text password
    if ($password === $admin['password']) {
        // Admin login successful
        $_SESSION['userID'] = $admin['userID'];
        $_SESSION['fullname'] = $admin['fullname'];
        $_SESSION['role'] = $admin['role'];
        $_SESSION['type'] = 'admin';

        // Redirect based on role
        if ($admin['role'] === 'elem') {
            header("Location: admin_elementary.php");
        } elseif ($admin['role'] === 'hs') {
            header("Location: admin_highschool.php");
        } else {
            header("Location: admin_college.php");
        }
        exit;
    } else {
        // Password incorrect
        $_SESSION['error'] = 'ID or password is incorrect';
    }
} else {
    // If admin not found
    $_SESSION['error'] = 'ID or password is incorrect';
}
exit;

?>