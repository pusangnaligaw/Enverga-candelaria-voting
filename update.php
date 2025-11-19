
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>High School Voting</title>
    <link rel="stylesheet" href="style_login.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo-section">
                <img src="mseuflogo.png" alt="MSEU Logo" width="60">
            </div>
            <h1>MSEUF Online Voting System</h1>
        </nav>
    </header>
    <div class="container">
        <h1>Change Password</h1>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo $_SESSION['error']; ?></div>
        <?php endif; ?>
        <form action="updatebackend.php" method="post" class="login-form">
             <div class="form-group">
                <label for="user_ID">User ID:</label>
                <input type="text" id="user_ID" name="user_ID" required>
            </div>
            <div class="form-group">
                <label for="old_password">Old Password:</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Change Password</button>
        </form>
   
</body>
</html>