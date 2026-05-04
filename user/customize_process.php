<?php
include '../alert.php';
require_once '../db_manager.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}
$user_id = $_SESSION['user_id'];

$db = new DBManager();
$write_conn = $db->getWriteConn();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $type = $_POST['type'] ?? '';

    // 1. Handle Text & Colors
    if ($action == 'save_customization') {
        
        if ($type == 'personal') {
            $fname = $_POST['fname'];
            $mname = $_POST['mname'];
            $lname = $_POST['lname'];
            $query = "UPDATE USERS SET fName = ?, mName = ?, lName = ? WHERE user_id = ?";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("sssi", $fname, $mname, $lname, $user_id);
            $stmt->execute();
            echo "Success";
        } 
        elseif ($type == 'username') {
            $username = $_POST['username'];
            $query = "UPDATE USERS SET username = ? WHERE user_id = ?";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            $_SESSION['username'] = $username; 
            echo "Success";
        } 
        // Color updates
        elseif (in_array($type, ['bg_color', 'btn_color', 'theme_color'])) {
            $val = $_POST['value'];
            // Dynamic column name since $type matches our DB column exactly
            $query = "UPDATE USERS SET $type = ? WHERE user_id = ?";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("si", $val, $user_id);
            $stmt->execute();
            echo "Success";
        }
    }

    // 2. Handle Image Banner Upload
    if ($action == 'save_banner' && isset($_FILES['banner'])) {
        $file = $_FILES['banner'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($ext), $allowed)) {
            // Generate a unique filename: banner_5_16823901.jpg
            $new_filename = "banner_" . $user_id . "_" . time() . "." . $ext;
            
            // IMPORTANT: Make sure the "uploads" folder exists in your root directory
            $destination = "../uploads/" . $new_filename; 
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $query = "UPDATE USERS SET profile_banner = ? WHERE user_id = ?";
                $stmt = $write_conn->prepare($query);
                $stmt->bind_param("si", $new_filename, $user_id);
                $stmt->execute();
                echo "Success! Banner updated.";
            } else {
                echo "Failed to upload image. Does the 'uploads' folder exist?";
            }
        } else {
            echo "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        }
    }
}
?>