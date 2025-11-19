<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSEUFCI Login</title>
    <link rel="stylesheet" href="style_login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-section">
                <img src="mseuflogo.png" alt="MSEU Logo">
                <h1>Welcome to MSEUFCI Voting System</h1>
                <h2>Manuel S. Enverga University Foundation</h2>
            </div>

            <form action="login.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Student/Admin ID</label>
                    <input type="text" id="username" name="username" placeholder="Enter your User ID" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                   </div>
                <button type="submit" class="btn-login">🔐 Sign In</button>
            </form>
            <a href="update.php">change Password</a>
        </div>
    </div>

</body>
</html>