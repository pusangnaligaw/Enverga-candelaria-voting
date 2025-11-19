<?php
session_start();
include "dbhandler.php";

// === CHECK LOGIN ===
if (!isset($_SESSION['user_ID']) || !isset($_SESSION['role'])) {
    $_SESSION['error'] = 'Please login first.';
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: changepassword.php");
    exit();
}

$user_id = $_SESSION['user_ID'];
$role    = $_SESSION['role'];

$old_password     = trim($_POST['old_password'] ?? '');
$new_password     = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// === VALIDATION ===
if ($old_password === '' || $new_password === '' || $confirm_password === '') {
    $_SESSION['error'] = 'All fields are required.';
    header("Location: changepassword.php");
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error'] = 'New passwords do not match.';
    header("Location: changepassword.php");
    exit();
}

if (strlen($new_password) < 4) {
    $_SESSION['error'] = 'Password too short.';
    header("Location: changepassword.php");
    exit();
}

// === DETERMINE TABLE ===
$is_admin = in_array($role, ['elem_admin', 'hs_admin', 'collage_admin']);
$table = $is_admin ? 'tbladmin' : 'tblstudent';

// === GET CURRENT PASSWORD (WORKS WITH MySQLi OR PDO) ===
$current_password = '';

if ($conn instanceof mysqli) {
    // MySQLi
    $stmt = $conn->prepare("SELECT password FROM $table WHERE userID = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_password = $row['password'];
    }
    $stmt->close();
} else {
    // PDO
    $stmt = $conn->prepare("SELECT password FROM $table WHERE userID = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    $current_password = $row['password'] ?? '';
}

if ($current_password === '') {
    $_SESSION['error'] = 'User not found.';
    header("Location: login.php");
    exit();
}

// === CHECK OLD PASSWORD ===
if ($old_password !== $current_password) {
    $_SESSION['error'] = 'Old password is incorrect.';
    header("Location: changepassword.php");
    exit();
}

$success = false;

if ($conn instanceof mysqli) {
    // MySQLi
    $update = $conn->prepare("UPDATE $table SET password = ? WHERE userID = ?");
    $update->bind_param("ss", $new_password, $user_id);
    $success = $update->execute();
    $update->close();
} else {
    // PDO
    $update = $conn->prepare("UPDATE $table SET password = ? WHERE userID = ?");
    $success = $update->execute([$new_password, $user_id]);
}

if ($success) {
    $_SESSION['success'] = 'Password updated successfully!';
} else {
    $_SESSION['error'] = 'ERROR: Password NOT updated. Check database connection.';
}


?>