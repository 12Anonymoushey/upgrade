<?php

require '../db_manager.php';
session_start();
$db = new DBManager();
$write_conn = $db->getWriteConn();
$read_conn = $db->getReadConn();

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_update']))
{

    $fName = $_POST['fname'];
    $mName = $_POST['mname'];
    $lName = $_POST['lname'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $user_id = $_POST['user_id'];

    $write_conn->begin_transaction();

    try{
        $query = "UPDATE USERS
                    SET fName = ?, mName =?, lName =?, email = ?, username =?, password = ?
                    WHERE user_id = ?";
        $update_stmt = $write_conn->prepare($query);
        $update_stmt->bind_param("ssssssi", $fName, $mName, $lName, $email, $username, $password, $user_id);
        $update_stmt->execute();
        $write_conn->commit();
        echo "<script>alert('User Successfully Updated!');</script>";
    } catch(Exception $e)
    {
        $write_conn->rollback();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Update User</title>
</head>
<body>
    <form id="form" action="userProfile.php" method="POST" style="display: none;">
        <input type="hidden" name="user_id" value=<?php echo $user_id; ?>>
        <input type="hidden" name="userProfile" value="true"> 
    </form>
    <script>
        document.getElementById("form").submit();
    </script>
</body>
</html>