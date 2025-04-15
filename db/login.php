<?php
include 'db.php';
session_start();

$username = $_POST['username'];
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT password FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Status</title>
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="login-form">
    <?php if ($stmt->num_rows > 0): ?>
        <?php 
            $stmt->bind_result($stored_password);
            $stmt->fetch();
            if ($password === $stored_password):
                $_SESSION['username'] = $username;
                header("Location: Selfprofile.php");
                exit();
            else: ?>
                <h2>Incorrect Password!</h2>
                <p>Please check and try again.</p>
                <a href="login.html">Try Again</a>
            <?php endif; ?>
    <?php else: ?>
        <h2>User Not Found!</h2>
        <p>No such user exists.</p>
        <a href="register.html">Register Here</a>
    <?php endif; ?>
    </div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>