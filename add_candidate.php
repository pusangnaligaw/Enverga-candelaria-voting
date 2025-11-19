<?php
session_start();
include 'dbhandler.php';

// Check if admin
if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'admin') {
    die(json_encode(['error' => ' Admin access required']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_$category.php");
    exit;
}

$category = sanitize($_POST['category'] ?? '');
$firstname = sanitize($_POST['firstname'] ?? '');
$lastname = sanitize($_POST['lastname'] ?? '');
$position = sanitize($_POST['position'] ?? '');
$partylist = sanitize($_POST['partylist'] ?? '');

// Validate
if (empty($firstname) || empty($lastname) || empty($position)) {
    $_SESSION['error'] = ' Please fill in all required fields (Name and Position)';
    header("Location: admin_$category.php");
    exit;
}

// Get candidate table
$candidate_table = get_candidate_table($category);
$fullname = $firstname . ' ' . $lastname;

// Handle photo
$photo = NULL;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
    if (in_array($mime, $allowed_mimes)) {
        $photo = file_get_contents($_FILES['photo']['tmp_name']);
    }
}

// Insert candidate
$stmt = $conn->prepare("INSERT INTO tblhscandidate (firstname, lastname, position, partylist, photo)
                        VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    $_SESSION['error'] = '⚙️ System issue - please try again';
    header("Location: admin_$category.php");
    exit;
}

$stmt->bind_param("sssss", $firstname, $lastname, $position, $partylist, $photo);

if ($stmt->execute()) {
    $_SESSION['success'] = '✓ Candidate added successfully';
} else {
    $_SESSION['error'] = '❌ Could not add candidate - please try again';
}

$stmt->close();
header("Location: admin_$category.php");
exit;
?>