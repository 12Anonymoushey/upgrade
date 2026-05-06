<?php
include 'modal.php';
session_start();
require 'db_manager.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db = new DBManager();
    $read_conn = $db->getReadConn();
    $write_conn = $db->getWriteConn();
    $action = $_POST['action'];

    //sign up ni siya
    if ($action == 'register') {
        $fName = $_POST['fname'];
        $mName = empty($_POST['mname']) ? $_POST['mname'] : "";
        $lName = $_POST['lname'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // --- NEW: Server-Side Password Matching Check ---
        if ($password !== $confirm_password) {
            echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
            exit();
        }

        // --- NEW: Server-Side Complexity Check ---
        // Checks for at least one letter [a-zA-Z], one number \d, and one special character [\W_]
        if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[\W_]/', $password)) {
            echo "<script>alert('Password must contain at least one letter, one number, and one special character.'); window.history.back();</script>";
            exit();
        }

        $query = "SELECT * FROM USERS WHERE email = ? OR username = ?";
        $check_stmt = $read_conn->prepare($query);
        $check_stmt->bind_param("ss", $email, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if($check_result->num_rows > 0)
        {
            while($row = $check_result->fetch_assoc())
            {
                if($row['email'] == trim($email))
                {
                    echo "<script>
                        window.onload = function() {
                            triggerModal({
                                theme: 'warning',
                                title: 'Warning',
                                message: 'Your email has already been used.',
                                icon: 'assets/logo.png',
                                redirect: 'index.php'
                            });
                        };
                    </script>";
                }
                else{
                    echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'warning',
                            title: 'Warning',
                            message: 'Username taken.',
                            icon: 'assets/logo.png',
                            redirect: 'index.php'
                        });
                    };
                </script>";
                }
            }
        }
        else{
            $write_conn->begin_transaction();
            
            try{
                $query = "INSERT INTO USERS(fName, mName, lName, username, password, email)
                    VALUES(?,?,?,?,?,?)";
                $user_stmt = $write_conn->prepare($query);
                
                // Security Note: Storing plain text passwords is a security risk. 
                // Consider using password_hash($password, PASSWORD_DEFAULT) here in the future.
                $user_stmt->bind_param("ssssss", $fName, $mName, $lName, $username, $password, $email);
                $user_stmt->execute();

                $new_user_id = $user_stmt->insert_id;

                $query = "INSERT INTO CAPYGRASSWALLET(user_id) VALUES(?)";
                $wallet_stmt = $write_conn->prepare($query);
                $wallet_stmt->bind_param("i", $new_user_id);
                $wallet_stmt->execute();

                $query = "INSERT INTO USERRANKS(user_id) VALUES(?)";
                $rank_stmt = $write_conn->prepare($query);
                $rank_stmt->bind_param("i", $new_user_id);
                $rank_stmt->execute();

                $query = "INSERT INTO USERFEATURES(user_id) VALUES(?)";
                $feature_stmt = $write_conn->prepare($query);
                $feature_stmt->bind_param("i", $new_user_id);
                $feature_stmt->execute();
                
                $write_conn->commit();
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Registration Succeed',
                            message: 'Succesfully created user.',
                            icon: 'assets/logo.png',
                            redirect: 'index.php'
                        });
                    };
                </script>";
            } catch(Exception $e)
            {
                $write_conn->rollback();
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'error',
                            title: 'Login Failed',
                            message: 'Cannot execute insert to user.',
                            icon: 'assets/logo.png',
                            redirect: 'index.php'
                        });
                    };
                </script>";
                
            }
        }
    }

    //login ni siya
    if ($action == 'login') {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $read_conn->prepare("SELECT user_id, username, password, isAdmin FROM USERS WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Note: If you switch to hashed passwords later, use password_verify() here
            if (trim($password) == trim($row['password'])) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['is_admin'] = $row['isAdmin'];

                if ($row['isAdmin'] == 1) {
                    $query = "SELECT * FROM ADMINS WHERE user_id = ?";
                    $admin_stmt = $read_conn->prepare($query);
                    $admin_stmt->bind_param("i", $_SESSION['user_id']);
                    $admin_stmt->execute();
                    $admin_result = $admin_stmt->get_result();
                    $admin_row = $admin_result->fetch_assoc();
                    $_SESSION['admin_id'] = $admin_row['admin_id'];

                    header("Location: admin/admin_home.php");
                } else {
                    header("Location: user/user_home.php");
                }
                exit();
            } else {
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'error',
                            title: 'Login Failed',
                            message: 'Invalid password.',
                            icon: 'assets/logo.png',
                            redirect: 'index.php'
                        });
                    };
                </script>";
            }
        } else {
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'error',
                            title: 'Login Failed',
                            message: 'User not found or does not exist.',
                            icon: 'assets/logo.png',
                            redirect: 'index.php'
                        });
                    };
                </script>";
        }
    }
}
?>