<?php
session_start();
require_once 'dbhandler.php';




$candidate_id = (int)$_GET['id'];
$category = sanitize($_GET['category']);
$candidate_table = get_candidate_table($category);

// Get candidate data
$stmt = $conn->prepare("SELECT * FROM $candidate_table WHERE id = ?");
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$candidate) {
    $_SESSION['error'] = 'Candidate not found';
    header("Location: admin_$category.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = sanitize($_POST['firstname'] ?? '');
    $lastname = sanitize($_POST['lastname'] ?? '');
    $position = sanitize($_POST['position'] ?? '');
    $partylist = sanitize($_POST['partylist'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    
    // Handle photo update
    $photo = $candidate['photo']; // Keep existing photo by default
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($mime, $allowed_mimes)) {
            $photo = file_get_contents($_FILES['photo']['tmp_name']);
        }
    }
    
    // Handle photo removal
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        $photo = NULL;
    }
    
    $fullname = $firstname . ' ' . $lastname;
    
    // Update candidate
    $stmt = $conn->prepare("UPDATE $candidate_table SET firstname = ?, lastname = ?, fullname = ?, position = ?, partylist = ?, photo = ?, bio = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $firstname, $lastname, $fullname, $position, $partylist, $photo, $bio, $candidate_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Candidate updated successfully';
        header("Location: admin_$category.php");
        exit;
    } else {
        $_SESSION['error'] = 'Failed to update candidate';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Candidate - <?php echo ucfirst($category); ?></title>
    <link rel="stylesheet" href="style_admin.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo-section">
                <img src="mseuflogo.png" alt="MSEU Logo" width="60">
            </div>
            <h1>Update Candidate - <?php echo ucfirst($category); ?></h1>
            <div class="user-section">
                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </nav>
    </header>

    <main class="admin-container">
        <div class="form-container">
            <h2>Update Candidate</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <form action="update_candidate.php?id=<?php echo $candidate_id; ?>&category=<?php echo $category; ?>" method="POST" enctype="multipart/form-data" class="candidate-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="firstname" value="<?php echo htmlspecialchars($candidate['firstname']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="lastname" value="<?php echo htmlspecialchars($candidate['lastname']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Position *</label>
                    <select name="position" required>
                        <option value="">Select Position</option>
                        <?php
                        $positions = [
                            'elementary' => ['President', 'Vice President', 'Secretary', 'Treasurer'],
                            'highschool' => ['President', 'Vice President', 'Secretary', 'Auditor', 'Treasurer', 'PIO'],
                            'college' => ['President', 'Vice President', 'Secretary', 'Auditor', 'Treasurer', 'PIO']
                        ];
                        foreach ($positions[$category] as $position): ?>
                            <option value="<?php echo $position; ?>" <?php echo $candidate['position'] === $position ? 'selected' : ''; ?>>
                                <?php echo $position; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Partylist</label>
                    <input type="text" name="partylist" value="<?php echo htmlspecialchars($candidate['partylist']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Current Photo</label>
                    <div class="current-photo">
                        <?php if ($candidate['photo']): ?>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($candidate['photo']); ?>" 
                                 alt="Current Photo" class="candidate-thumb">
                            <label class="remove-photo">
                                <input type="checkbox" name="remove_photo" value="1"> Remove Photo
                            </label>
                        <?php else: ?>
                            <span class="no-photo">No photo uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Update Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Update Candidate</button>
                    <a href="admin_<?php echo $category; ?>.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>