<?php
include '../alert.php';
require '../db_manager.php';

$db = new DBManager();
$write_conn = $db->getWriteConn();

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user']))
{
    $user_id = $_POST['user_id'];

    $write_conn->begin_transaction();
    try{

        $query = "DELETE FROM USERS
                    WHERE user_id = ?";
        $stmt = $write_conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $write_conn->commit();
        echo "<script>alert('Successfully deleted user')</script>";
    } catch(Exception $e)
    {
        $write_conn->rollback();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>delete User</title>
</head>
<body>
    <form id="form" action="admin_home.php" method="POST" style="display: none;">
        <input type="hidden" name="user_id" value=<?php echo $user_id; ?>>
        <input type="hidden" name="userProfile" value="true"> 
    </form>
    <script>
        document.getElementById("form").submit();
    </script>
</body>
</html>