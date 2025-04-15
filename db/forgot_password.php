<?php
include 'db.php';

$username = $_POST['username'];

$stmt = $conn->prepare("SELECT password FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Retrieved</title>
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="login-form">
        <?php if ($stmt->num_rows > 0):
            $stmt->bind_result($password);
            $stmt->fetch(); ?>
            <h2>Your Password</h2>
            <p class="info">Your password is:</p>
            <p class="password-box"><?php echo htmlspecialchars($password); ?></p> <!-- 直接显示明文密码 -->
        <?php else: ?>
            <h2>Error!</h2>
            <p class="info">Username not found!</p>
        <?php endif; ?>
        <a href="login.html">Login here</a>
    </div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>