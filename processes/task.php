<?php
require_once(__DIR__ . '/../modal.php');
require_once '../db_manager.php';
require_once '../admin/admin_processes/Feature_checker.php';
require_once '../admin/admin_processes/Rank_checker.php';

$db = new DBManager();
$write_conn = $db->getWriteConn();

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task']))
{
    $action = $_POST['action'];
    $user_id = $_POST['user_id'];
    $feature = new Feature_checker($user_id);
    $rank = new Rank_checker($user_id);

    if($action == "approve")
    {
        $task_id = $_POST['task_id']? $_POST['task_id'] : "";
        $capygrass = $_POST['totalCapygrass']? $_POST['totalCapygrass'] : "";
        $write_conn->begin_transaction();
        try{

            $query = "UPDATE TASKS
                        SET isApproved = 1, pointsEarned = pointsEarned + 10
                        WHERE user_id = ? AND task_id = ?";
            $task_stmt = $write_conn->prepare($query);
            $task_stmt->bind_param("ii", $user_id, $task_id);
            $task_stmt->execute();

            $query = "UPDATE CAPYGRASSWALLET
                        SET totalCapygrass = totalCapygrass + 10
                        WHERE user_id = ?";
            $wallet_stmt = $write_conn->prepare($query);
            $wallet_stmt->bind_param("i", $user_id);
            $wallet_stmt->execute();

            $exception_feature = $feature->check_features($capygrass);
            $exception_rank = $rank->check_rank($capygrass);

            if(!empty($exception_feature))
            {
                $write_conn->rollback();
                echo "<script>alert({$exception->getMessage()})</script>";
            }
            else{
                $write_conn->commit();
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Approved',
                            message: 'Task Successfully Approved',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
            }
        } catch(Exception $e)
        {
            echo "<script>alert('{$e->getMessage()}')</script>";
            $write_conn->rollback();
        }
    }
    elseif($action == "disapprove")
    {
        $task_id = $_POST['task_id']? $_POST['task_id'] : "";
        $capygrass = $_POST['totalCapygrass']? $_POST['totalCapygrass'] : "";
        $write_conn->begin_transaction();
        try{

            $query = "UPDATE TASKS
                        SET isApproved = 0, pointsEarned = pointsEarned - 10
                        WHERE user_id = ? AND task_id = ?";
            $task_stmt = $write_conn->prepare($query);
            $task_stmt->bind_param("ii", $user_id, $task_id);
            $task_stmt->execute();

            $query = "UPDATE CAPYGRASSWALLET
                        SET totalCapygrass = totalCapygrass - 10
                        WHERE user_id = ?";
            $wallet_stmt = $write_conn->prepare($query);
            $wallet_stmt->bind_param("i", $user_id);
            $wallet_stmt->execute();

            $exception_feature = $feature->check_features($capygrass);
            $exception_rank = $rank->check_rank($capygrass);

            if(!empty($exception_rank) || !empty($exception_feature))
            {
                $write_conn->rollback();
                 echo "<script>alert('{$exception_rank->getMessage()}')</script>";
            }
            else{
                $write_conn->commit();
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Disapproved',
                            message: 'Task Successfully Un-Approved',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
            }
        } catch(Exception $e)
        {
            echo "<script>alert('{$e->getMessage()}')</script>";
            $write_conn->rollback();
        }
    }
    elseif($action == "update" || $action == "user_update")
    {
        $capygrass = $_POST['totalCapygrass']? $_POST['totalCapygrass'] : "";
        $write_conn->begin_transaction();
        try{
            $description = $_POST['description'];

            $query = "UPDATE TASKS
                        SET description = ?
                        WHERE user_id = ? AND task_id = ?";
            $update_stmt = $write_conn->prepare($query);
            $update_stmt->bind_param("sii", $description, $user_id, $task_id);
            $update_stmt->execute();
            $write_conn->commit();
            echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Update',
                            message: 'Task Successfully Updated',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
        } catch(Exception $e)
        {
            echo "<script>alert('{$e->getMessage()}')</script>";
            $write_conn->rollback();
        }
    }
    elseif($action == "delete" || $action == "user_delete")
    {
        $write_conn->begin_transaction();
        try{

            $query = "DELETE FROM TASKS
                        WHERE user_id = ? AND task_id = ?";
            $del_stmt = $write_conn->prepare($query);
            $del_stmt->bind_param("ii", $user_id, $task_id);
            $del_stmt->execute();
            $write_conn->commit();
            echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Delete',
                            message: 'Task Successfully deleted.',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
        } catch(Exception $e)
        {
            echo "<script>alert('{$e->getMessage()}')</script>";
            $write_conn->rollback();
        }
    }
    elseif($action == "create")
    {
        $description = $_POST['description'];
        $tool_id = $_POST['tool_id'];
        $write_conn->begin_transaction();
        try{
            $query = "INSERT INTO TASKS(user_id, tool_id, description)
                                            VALUES(?,?,?)";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("iis", $user_id, $tool_id, $description);
            $stmt->execute();
            $write_conn->commit();
            echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Created',
                            message: 'Task Successfully created.',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
        } catch(Exception $e)
        {
            $write_conn->rollback();
            echo "<script>alert('{$e->getMessage()}')</script>";
        }
    }
    elseif($action == "finish")
    {
        $write_conn->begin_transaction();
        try{
            $query = "UPDATE TASKS
                        SET isFinished = 1, finishedAt = CURRENT_TIMESTAMP
                        WHERE user_id = ?";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $write_conn->commit();
            echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Finished',
                            message: 'Task Successfully finished, just wait for the approval to earn points!',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
        } catch(Exception $e)
        {
            $write_conn->rollback();
            echo "<script>alert('{$e->getMessage()}')</script>";
        }
    }
    elseif($action == "not_finish")
    {
        $write_conn->begin_transaction();
        try{
            $query = "UPDATE TASKS
                        SET isFinished = 0, finishedAt = null
                        WHERE user_id = ?";
            $stmt = $write_conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $write_conn->commit();
            echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Unfinished',
                            message: 'Task Successfully unfinished',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
        } catch(Exception $e)
        {
            $write_conn->rollback();
            echo "<script>alert('{$e->getMessage()}')</script>";
        }
    }
    else{
        echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'notification',
                            title: 'Notification',
                            message: 'None of it was a value of action hmmmm',
                            icon: 'assets/logo.png'
                        });
                    };
                </script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>task</title>
</head>
<body>
    <form id="admin_task" action="../admin/userProfile.php" method="POST" style="display: none;">
        <input type="hidden" name="userProfile" value="true">
        <input type="hidden" name="user_id" value=<?php echo htmlspecialchars($user_id);?>>
    </form>
    <form id="user_task" action="../user/user_home.php" method="POST" style="display: none;">
        <input type="hidden" name="user_home" value="true">
        <input type="hidden" name="user_id" value=<?php echo htmlspecialchars($user_id);?>>
    </form>
    <?php
        if($action == "create" || $action == "user_update" || $action == "user_delete" 
        || $action == "finish" || $action == "not_finish")
        {
            echo "<script>
                    document.getElementById('user_task').submit();
                </script>";
        }
        else{
             echo "<script>
                    document.getElementById('admin_task').submit();
                </script>";
        }
    ?>
</body>
</html>

<?php
exit();
?>