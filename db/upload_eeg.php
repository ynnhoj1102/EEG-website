<?php
include 'db.php';
session_start();

if (!isset($_SESSION['username'])) {
    exit('Not logged in');
}

$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['eeg_file'])) {
    $filename = $_FILES['eeg_file']['name'];
    $filedata = file_get_contents($_FILES['eeg_file']['tmp_name']);

    if (in_array(pathinfo($filename, PATHINFO_EXTENSION), ['csv', 'txt'])) {
        $stmt = $conn->prepare("UPDATE users SET eeg_filename=?, eeg_file=? WHERE username=?");
        $stmt->bind_param("sss", $filename, $filedata, $username);
        $stmt->execute();
        $stmt->close();
        echo 'File uploaded successfully';
    } else {
        echo 'Invalid file type';
    }
}
?>