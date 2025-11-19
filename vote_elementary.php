<?php
session_start();
include 'dbhandler.php';

// Check if logged in
if (!isset($_SESSION['userID']) || $_SESSION['type'] !== 'student') {
    header("Location: main.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$category = 'elementary';

// Get voting schedule
$schedule = get_schedule($conn, $category);
$can_vote_result = can_vote($conn, $category);

// Get candidates grouped by position
$candidates = [];
$stmt = $conn->prepare("SELECT * FROM tblelementarycandidate ORDER BY position ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[$row['position']][] = $row;
}
$stmt->close();

// Check if user already voted
$stmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM tblvotes WHERE student_id = ? AND category = ?");
$stmt->bind_param("is", $user_id, $category);
$stmt->execute();
$vote_check = $stmt->get_result()->fetch_assoc();
$already_voted = $vote_check['vote_count'] > 0;
$stmt->close();

// Define positions for elementary
$positions = ['President', 'Vice President', 'Secretary', 'Treasurer'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elementary Voting</title>
    <link rel="stylesheet" href="style_voting.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo-section">
                <img src="mseuflogo.png" alt="MSEU Logo" width="60">
            </div>
            <h1>MSEUF Online Voting System</h1>
            <div class="user-section">
                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </nav>
    </header>

    <main class="voting-container">
        <?php if (!empty($_SESSION['fullname'])): ?>
            <div class="welcome-banner">Welcome to Elementary Voting, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</div>
        <?php else: ?>
            <div class="welcome-banner">Welcome to Elementary Voting!</div>
        <?php endif; ?>
        <h2>Elementary Voting Page</h2>
          <div class="nosched">
        <?php if (!$schedule) {
            echo "<p>there is no voting schedule.</p>";
          }?>
    </div>
        <?php if ($already_voted): ?>
            <div class="alert alert-success">
                <h3>✓ You have already voted!</h3>
                <p>Thank you for participating in the election.</p>
                <a href="results_elementary.php" class="btn-primary">View Results</a>
            </div>
        <?php elseif (!$can_vote_result['allowed']): ?>
            <div class="alert alert-error">
                <h3><?php echo htmlspecialchars($can_vote_result['reason']); ?></h3>
            </div>
        <?php else: ?>
            <form action="submit_vote.php" method="POST" class="voting-form">
                <input type="hidden" name="category" value="elementary">

                <?php foreach ($positions as $position): ?>
                    <?php if (isset($candidates[$position]) && count($candidates[$position]) > 0): ?>
                        <section class="position-section">
                            <h3><?php echo htmlspecialchars($position); ?></h3>
                            <div class="candidates-grid">
                                <?php foreach ($candidates[$position] as $candidate): ?>
                                    <label class="candidate-card">
                                        <input type="radio" name="vote[<?php echo htmlspecialchars($position); ?>]" 
                                               value="<?php echo $candidate['id']; ?>" required>
                                        <div class="card-content">
                                            <?php if ($candidate['photo']): ?>
                                                <img src="data:image/jpeg;base64,<?php echo base64_encode($candidate['photo']); ?>" 
                                                     alt="<?php echo htmlspecialchars($candidate['fullname']); ?>" 
                                                     class="candidate-photo">
                                            <?php else: ?>
                                                <div class="candidate-photo placeholder">No Photo</div>
                                            <?php endif; ?>
                                            <h4><?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?></h4>
                                            <p class="partylist"><?php echo htmlspecialchars($candidate['partylist']); ?></p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Submit Vote</button>
                </div>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>