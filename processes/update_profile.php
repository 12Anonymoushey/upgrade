<?php
session_start();
require_once(__DIR__ . '/../modal.php');
// Adjust this path if your db_manager.php is located elsewhere
require_once '../db_manager.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'warning',
                            title: 'Warning',
                            message: 'Please log in first.',
                            icon: '../assets/logo.png',
                            redirect: 'index.php'
                        });
                    };
                </script>";
        exit();
    }

    $db = new DBManager();
    $write_conn = $db->getWriteConn();

    $user_id = $_POST['user_id'];
    $email = $_POST['email'];
    $bio = $_POST['bio'];

    // Setup basic query variables
    $query_append = "";
    $types = "ss"; 
    $params = [$email, $bio];

    // Handle Profile Photo Upload if one was selected
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        
        // Ensure the directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp_path = $_FILES['profilePhoto']['tmp_name'];
        $file_name = $_FILES['profilePhoto']['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate it's actually an image
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_extension, $allowed_exts)) {
            // Create a unique filename to prevent overwriting
            $new_file_name = "profile_" . $user_id . "_" . time() . "." . $file_extension;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp_path, $destination)) {
                // Image uploaded successfully, add to query
                $query_append = ", profilePhoto = ?";
                $types .= "s";
                $params[] = $new_file_name;
            } else {
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'error',
                            title: 'Failed',
                            message: 'Failed to move uploaded file.',
                            icon: '../assets/logo.png',
                            redirect: 'javascript:history.back()'
                        });
                    };
                </script>";
                exit();
            }
        } else {
            echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'warning',
                            title: 'Invalid',
                            message: 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.',
                            icon: '../assets/logo.png',
                            redirect: 'javascript:history.back()'
                        });
                    };
                </script>";
            exit();
        }
    }

    // Add user_id to the end of the query parameters
    $types .= "i";
    $params[] = $user_id;

    // Prepare and execute the update statement
    $query = "UPDATE USERS SET email = ?, bio = ? $query_append WHERE user_id = ?";
    $stmt = $write_conn->prepare($query);
    
    // Bind dynamic parameters using splat operator (...)
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
       echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Updated',
                            message: 'Profile updated successfully!',
                            icon: '../assets/logo.png',
                            redirect: '../user/user_home.php'
                        });
                    };
                </script>";
    } else {
        echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'error',
                            title: 'Error',
                            message: 'Error updating profile. Please try again',
                            icon: '../assets/logo.png',
                            redirect: '../user/user_home.php'
                        });
                    };
                </script>";
    }

    $stmt->close();
    exit();
} else {
    // If someone accesses this file directly without submitting the form
    header("Location: ../index.php");
    exit();
}
?>