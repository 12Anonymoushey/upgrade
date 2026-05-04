<?php
include '../alert.php';
require_once '../db_manager.php';
require_once '../admin/admin_processes/Feature_checker.php';
require_once '../admin/admin_processes/Rank_checker.php';
session_start();
$db = new DBManager();
$write_conn = $db->getWriteConn();

if($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_POST['note']))
{
    $user_id = $_POST['user_id'];
    $note_id = isset($_POST['note_id']) ? $_POST['note_id'] : "";
    $capygrass = isset($_POST['totalCapygrass']) ? $_POST['totalCapygrass'] : "";
    $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : "";
    $action = $_POST['action'];

     $feature = new Feature_checker($user_id);
      $rank = new Rank_checker($user_id);

    if($action == "approve")
    {
        $write_conn->begin_transaction();
        try{
            $set_var_stmt = $write_conn->prepare("SET @current_admin_id = ?");
            $set_var_stmt->bind_param("i", $admin_id);
            $set_var_stmt->execute();

            $query = "UPDATE NOTES
                        SET pointsEarned = pointsEarned + 10, isApproved = 1
                        WHERE note_id = ? AND user_id = ?";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("ii", $note_id, $user_id);
            $stmt->execute();

            $query = "UPDATE CAPYGRASSWALLET
                        SET totalCapygrass = totalCapygrass + 10
                        WHERE user_id = ?";
            $wallet_stmt = $write_conn->prepare($query);
            $wallet_stmt->bind_param("i", $user_id);
            $wallet_stmt->execute();

            $e_rank = $rank->check_rank($capygrass);
            $e_feature = $feature->check_features($capygrass);

            if(!empty($e_rank) || !empty($e_feature))
            {
                $write_conn->rollback();
                echo " alert('{$e_rank->getMessage()}{$e_feature->getMessage()}');</script>";
            }
            else{
                $write_conn->commit();
                echo "<script>alert('Successfully approved note!');</script>";
            }
        } catch(Exception $e)
        {
            $write_conn->rollback();
            echo " alert('{$e->getMessage()}');</script>";
        }
    }
    elseif($action == "disapprove")
    {
        $write_conn->begin_transaction();
        try{
            $set_var_stmt = $write_conn->prepare("SET @current_admin_id = ?");
            $set_var_stmt->bind_param("i", $admin_id);
            $set_var_stmt->execute();
            
            $query = "UPDATE NOTES
                        SET pointsEarned = pointsEarned - 10, isApproved = 0
                        WHERE note_id = ? AND user_id = ?";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("ii", $note_id, $user_id);
            $stmt->execute();

            $query = "UPDATE CAPYGRASSWALLET
                        SET totalCapygrass = totalCapygrass - 10
                        WHERE user_id = ?";
            $wallet_stmt = $write_conn->prepare($query);
            $wallet_stmt->bind_param("i", $user_id);
            $wallet_stmt->execute();

            $e_rank = $rank->check_rank($capygrass);
            $e_feature = $feature->check_features($capygrass);

            if(!empty($e_rank) || !empty($e_feature))
            {
                $write_conn->rollback();
                echo " alert('{$e_rank->getMessage()}{$e_feature->getMessage()}');</script>";
            }
            else{
                $write_conn->commit();
                echo "<script>alert('Successfully disapproved note!');</script>";
            }
        } catch(Exception $e)
        {
            $write_conn->rollback();
            echo "<script>alert('{$e->getMessage()}');</script>";
        }
    }
    elseif($action == "update" || $action == "user_update")
    {
        $title = $_POST['title'];
        $content = $_POST['content'];

        $write_conn->begin_transaction();
        try{
            $query = "UPDATE NOTES
                        SET title = ?, content = ?
                        WHERE user_id = ? AND note_id = ?";
            $update_stmt = $write_conn->prepare($query);
            $update_stmt->bind_param("ssii", $title, $content, $user_id, $note_id);
            $update_stmt->execute();

            $write_conn->commit();
            echo "<script>alert('Successfully updated note!');</script>";
        } catch(Exception $e)
        {
            $write_conn->rollback();
            echo "<script>alert('{$e->getMessage()}');</script>";
        }
    }
    elseif($action == "delete" || $action == "user_delete")
    {
        $write_conn->begin_transaction();
        try{

            $query = "DELETE FROM NOTES
                        WHERE user_id = ? and note_id = ?";
            $del_stmt = $write_conn->prepare($query);
            $del_stmt->bind_param("ii", $user_id, $note_id);
            $del_stmt->execute();

            $write_conn->commit();
            echo "<script>alert('Successfully deleted note!');</script>";
        } catch(Exception $e)
        {
            $write_conn->rollback();
            echo "<script>alert('{$e->getMessage()}');</script>";
        }
    }
    elseif($action == "create")
    {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $write_conn->begin_transaction();
        try{
            $query = "INSERT INTO notes (user_id, title, content, pointsEarned, isApproved) VALUES (?, ?, ?, 0, 0)";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("iss", $user_id, $title, $content);
            $stmt->execute();
            $write_conn->commit();
            echo "<script>alert('Note Successfully Created')</script>";
        } catch(Exception $e) {
            $write_conn->rollback();
            echo "<script>alert('{$e->getMessage()}')</script>";
        }
    }
    else{
         echo "<script>alert('nothing happened');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Note Management</title>
</head>
<body>
    <form id="admin_note" action="../admin/userProfile.php" method="POST" style="display: none;">
        <input type="hidden" name="userProfile" value="true">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id);?>">
    </form>
    <form id="user_note" action="../user/user_home.php" method="POST" style="display: none;">
        <input type="hidden" name="user_home" value="true">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id);?>">
    </form>
    <?php
        if($action == "create" || $action == "user_update" || $action == "user_delete") {
            echo "<script>document.getElementById('user_note').submit();</script>";
        } else {
            echo "<script>document.getElementById('admin_note').submit();</script>";
        }
    ?>
</body>
</html>

<?php
exit();
?>