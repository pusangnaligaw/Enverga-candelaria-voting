<?php
session_start();
include 'dbhandler.php';

if (!isset($_SESSION['userID'])) {
    header("Location: main.php");
    exit;
}

$category = 'elementary';
$candidate_table = 'tblelementarycandidate';

$results = [];
$stmt = $conn->prepare("
    SELECT c.position, c.id, c.firstname, c.lastname, c.partylist, c.votes
    FROM $candidate_table c
    ORDER BY c.position ASC, c.votes DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (!isset($results[$row['position']])) {
        $results[$row['position']] = [];
    }
    $results[$row['position']][] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as total_voters FROM tblvotes WHERE category = ?");
$stmt->bind_param("s", $category);
$stmt->execute();
$voter_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$positions = ['President', 'Vice President', 'Secretary', 'Treasurer'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - Elementary</title>
    <link rel="stylesheet" href="style_results.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo-section">
                <img src="mseuflogo.png" alt="MSEU Logo" width="60">
            </div>
            <h1>MSEU Online Voting System - Results</h1>
            <div class="user-section">
                <span><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </nav>
    </header>

    <main class="results-container">
        <h2>Elementary Election Results</h2>
        
        <div class="stats-box">
            <p><strong>Total Voters:</strong> <?php echo $voter_info['total_voters']; ?></p>
        </div>

        <?php foreach ($positions as $position): ?>
            <?php if (isset($results[$position]) && count($results[$position]) > 0): ?>
                <section class="results-position">
                    <h3><?php echo htmlspecialchars($position); ?></h3>
                    <div class="results-table">
                        <?php foreach ($results[$position] as $index => $candidate): ?>
                            <div class="result-row <?php echo $index === 0 ? 'winner' : ''; ?>">
                                <div class="rank"><?php echo $index + 1; ?></div>
                                <div class="info">
                                    <h4><?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?></h4>
                                    <p><?php echo htmlspecialchars($candidate['partylist']); ?></p>
                                </div>
                                <div class="votes">
                                    <strong><?php echo $candidate['votes']; ?></strong> votes
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="actions">
            <a href="vote_elementary.php" class="btn-primary">Back to Voting</a>
        </div>
    </main>
</body>
</html>