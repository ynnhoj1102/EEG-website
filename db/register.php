<?php
include 'db.php';

$username = $_POST['username'];
$password = $_POST['password'];
$full_name = $_POST['full_name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$age = $_POST['age'];
$gender = $_POST['gender'];

$checkStmt = $conn->prepare("SELECT id FROM users WHERE username=?");
$checkStmt->bind_param("s", $username);
$checkStmt->execute();
$checkStmt->store_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Status</title>
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="login-form">
        <?php if ($checkStmt->num_rows > 0): ?>
            <h2>Registration failed!</h2>
            <p>Username already exists. Please choose another.</p>
            <a href="register.html">Try again</a>
        <?php else: ?>
            <?php
            // Username doesn't exist yet, insert new user
            $insertStmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, age, gender) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssssis", $username, $password, $full_name, $email, $phone, $age, $gender);
            
            if ($insertStmt->execute()):
            ?>
                <h2>Registration successful!</h2>
                <p>You can now login.</p>
                <a href="login.html">Login here</a>
            <?php else: ?>
                <h2>Registration failed!</h2>
                <p>An unexpected error occurred. Please try again later.</p>
                <a href="register.html">Try again</a>
            <?php endif; ?>
            <?php
            $insertStmt->close();
            ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$checkStmt->close();
$conn->close();
?>