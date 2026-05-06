<?php
session_start();
require_once(__DIR__ . '/../modal.php');
require_once '../db_manager.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    
    // Ensure the user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo "<script>alert('Please log in first.'); window.location.href='../index.php';</script>";
        exit();
    }

    $user_id = $_SESSION['user_id']; // Pulling from session is safer than a hidden input
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // Server-Side Verification: Do the passwords match?
    if ($new_password !== $confirm_new_password) {
        echo "<script>alert('Your new passwords do not match.'); window.history.back();</script>";
        echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'warning',
                            title: 'Note',
                            message: 'Your new password do not much',
                            icon: '../assets/logo.png',
                            redirect: 'javascript:history.back()'
                        });
                    };
                </script>";
        exit();
    }

    // Server-Side Verification: Password Complexity Check
    if (!preg_match('/[a-zA-Z]/', $new_password) || !preg_match('/\d/', $new_password) || !preg_match('/[\W_]/', $new_password)) {
        echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'warning',
                            title: 'Note',
                            message: 'Your new password must contain at least one letter, one number, and one special character',
                            icon: '../assets/logo.png',
                            redirect: 'javascript:history.back()'
                        });
                    };
                </script>";
        exit();
    }

    $db = new DBManager();
    $read_conn = $db->getReadConn();
    $write_conn = $db->getWriteConn();

    // 1. Fetch the user's CURRENT password from the database
    $stmt = $read_conn->prepare("SELECT password FROM USERS WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        
        // 2. Check if the provided "current password" matches the DB
        if (trim($current_password) !== trim($row['password'])) {
            echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'error',
                            title: 'Failed!',
                            message: 'The current password you entered is incorrect.',
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
                            title: 'Warning',
                            message: 'User account not found.',
                            icon: '../assets/logo.png',
                            redirect: '../index.php'
                        });
                    };
                </script>";
        exit();
    }
    $stmt->close();

    // 3. Update the database with the NEW password
    $update_stmt = $write_conn->prepare("UPDATE USERS SET password = ? WHERE user_id = ?");
    $update_stmt->bind_param("si", $new_password, $user_id);

    if ($update_stmt->execute()) {
        echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Success!',
                            message: 'Your password has been successfully updated!',
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
                            title: 'Failed!',
                            message: 'Error updating password. Please try again.',
                            icon: '../assets/logo.png',
                            redirect: 'javascript:history.back()'
                        });
                    };
                </script>";
    }
    
    $update_stmt->close();
    exit();
    
} else {
    // If accessed directly without submitting the form
    header("Location: ../index.php");
    exit();
}
?>