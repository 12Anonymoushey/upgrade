<?php

require_once '../db_manager.php';
session_start();

 if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['userProfile']))
{
    $user_id = $_POST['user_id'];

    $db = new DBManager();
    $read_conn = $db->getReadConn();

    //get USERS with CAPYGRASSWALLET
    $query = "SELECT * FROM USERS u
                JOIN CAPYGRASSWALLET c ON u.user_id = c.user_id
                WHERE u.user_id = ?";
    $user_stmt = $read_conn->prepare($query);
    $user_stmt->bind_param("i", $user_id);
    if($user_stmt->execute())
    {
        $user_result = $user_stmt->get_result();
        $user_row = $user_result->fetch_assoc();
    }
    else{
        echo "<script>window.alert('could not execute USERS query')</script>";
    }

    //get notes
    $query = "SELECT * FROM NOTES
                WHERE user_id = ?";

    $notes_stmt = $read_conn->prepare($query);
    $notes_stmt->bind_param("i", $user_id);
    $notes_stmt->execute();
    $notes_results = $notes_stmt->get_result();

    //get studysessions
    $query = "SELECT * FROM STUDYSESSIONS s
                JOIN STUDYTECHNIQUES st ON s.study_tech_id = st.study_tech_id
                WHERE s.user_id = ?";

    $sessions_stmt = $read_conn->prepare($query);
    $sessions_stmt->bind_param("i", $user_id);
    $sessions_stmt->execute();
    $sessions_results = $sessions_stmt->get_result();
                

    //get USERFEATURES
    $query = "SELECT * FROM USERFEATURES u
                JOIN FEATURES f ON u.feature_id = f.feature_id
                WHERE u.user_id = ?";

    $user_features_stmt = $read_conn->prepare($query);
    $user_features_stmt->bind_param("i", $user_id);
    $user_features_stmt->execute();
    $user_features_results = $user_features_stmt->get_result();

    //get USERRANKS
    $query = "SELECT * FROM USERRANKS ur
                JOIN USERS u ON ur.user_id = u.user_id
                JOIN RANKS r ON ur.rank_id = r.rank_id
                WHERE u.user_id = ?
                ORDER BY ur.assignedAt DESC";

    $user_ranks_stmt = $read_conn->prepare($query);
    $user_ranks_stmt->bind_param("i", $user_id);
    $user_ranks_stmt->execute();
    $user_ranks_results = $user_ranks_stmt->get_result();

    //get tasks
    $query = "SELECT * FROM TASKS
            WHERE user_id = ?";
    $task_stmt = $read_conn->prepare($query);
    $task_stmt->bind_param("i", $user_id);
    $task_stmt->execute();
    $task_results = $task_stmt->get_result();
}

if(!empty($_SESSION['alert']))
{
    echo "<script> alert({$_SESSION['alert']}); </script>";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="../css/userProfile.css? v=<?php echo filemtime('../css/userProfile.css'); ?>">
</head>
<body>
    <div>
        <button onclick="history.back()">Go back</button>
        <h1>User Profile of <?php echo htmlspecialchars($user_row['username'])?></h1>
        <h1>Current CapyGrass: <?php echo htmlspecialchars($user_row['totalCapygrass']) ?></h1>
        <div>
            <h3>CURRENT AND PREVIOUS RANKS</h3>
            <table>
                <thead>
                    <th>Rank Name</th>
                    <th>Assigned At</th>
                </thead>
                <tbody>
                    <?php
                        if($user_ranks_results->num_rows > 0)
                        {
                           while($rank_rows = $user_ranks_results->fetch_assoc())
                            {
                                echo "<tr>
                                    <td>{$rank_rows['name']}</td>
                                    <td>{$rank_rows['assignedAt']}</td>    
                                </tr>";
                            }
                        }
                        else{
                            echo "<tr>
                                <td>No Rank Yet</td>
                            </tr>";
                        }
                    ?>
                </tbody>
            <table>
        </div>
         <div>
            <h3>NOTES</h3>
            <table>
                <thead>
                    <th>Title</th>
                    <th>Content</th>
                    <th>Date and Time Created</th>
                    <th>Points Earned</th>
                    <th>Approval Status</th>
                    <th>Approve</th>
                </thead>
                <tbody>
                    <?php
                        if($notes_results->num_rows > 0)
                        {
                           while($notes_rows = $notes_results->fetch_assoc())
                            {
                                echo "<tr>
                                    <td>{$notes_rows['title']}</td>
                                    <td>{$notes_rows['content']}</td>
                                    <td>{$notes_rows['updatedAt']}</td>
                                    <td>{$notes_rows['pointsEarned']}</td>" . 
                                    ($notes_rows['isApproved'] == 1 ? "<td>Approved</td>" :
                                        "<td>Not Yet Approved</td>"
                                    ) .
                                     
                                     ($notes_rows['isApproved'] == 1 ? "
                                     <td><form action='note.php' method='POST'
                                      onsubmit=\"return confirm('Are you sure you wanna un-approve this note?')\">
                                            <input type='hidden' name='user_id' value={$user_id}>
                                            <input type='hidden' name='note_id' value={$notes_rows['note_id']}>
                                            <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                            <input type='hidden' name='action' value='disapprove'>
                                            <button type='submit' name='note'>Disapprove</button>
                                        </form></td>
                                     " :
                                    "<td>
                                        <form action='note.php' method='POST'
                                         onsubmit=\"return confirm('Are you sure you wanna Approve this note?')\">
                                            <input type='hidden' name='user_id' value={$user_id}>
                                            <input type='hidden' name='note_id' value={$notes_rows['note_id']}>
                                            <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                            <input type='hidden' name='action' value='approve'>
                                            <button type='submit' name='note'>Approve</button>
                                        </form>
                                    </td>") . 
                                "<td>
                                    <form action='note.php' method='POST'
                                    onsubmit=\"return confirm('Are you sure you wanna delete this note?')\">
                                        <input type='hidden' name='user_id' value={$user_id}>
                                        <input type='hidden' name='note_id' value={$notes_rows['note_id']}>
                                        <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                        <input type='hidden' name='action' value='delete'>
                                        <button type='submit' name='note'>delete</button>
                                    </form>
                                </td>
                                <td>
                                    <button onclick=\"openPanel('update_note')\">Update note</button>
                                    <div id='update_note' class='panel hide'>
                                        <form action='note.php' method='POST'>
                                            <input type='hidden' name='user_id' value={$user_id}>
                                            <input type='hidden' name='note_id' value={$notes_rows['note_id']}>
                                            <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                            <input type='hidden' name='action' value='update'>
                                            <label>
                                                Title: <input type='text' name='title' value={$notes_rows['title']}>
                                            </label>
                                            <label>
                                                Content : <textarea name='content'>{$notes_rows['content']}</textarea>
                                            </label>    
                                            <button type='submit' name='note'>Update</button>
                                        </form>
                                        <button onclick=\"closePanel('update_note')\">Close</button>
                                    </div>
                                </td>
                                </tr>";
                            }
                        }
                        else{
                            echo "<tr>
                                <td>No Notes Yet</td>
                            </tr>";
                        }
                    ?>
                </tbody>
            <table>
        </div>
        <div>
            <h3>TASKS</h3>
            <table>
                <thead>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Date Finished</th>
                    <th>Points Earned</th>
                    <th>Approval Status</th>
                    <th>Approve</th>
                </thead>
                <tbody>
                    <?php
                        if($task_results->num_rows > 0)
                        {
                            while($task_row = $task_results->fetch_assoc())
                            {
                                echo "<tr>
                                    <td>{$task_row['description']}</td>"
                                    . ($task_row['isFinished'] == 1 ? 
                                        "<td>Finished</td>" :
                                        "<td>Pending</td>"
                                    ) .
                                    "<td>{$task_row['createdAt']}</td> 
                                    <td>{$task_row['finishedAt']}</td>
                                    <td>{$task_row['pointsEarned']}</td> "
                                    . ($task_row['isApproved'] == 1 ? 
                                        "<td>Approved</td>" :
                                        "<td>Not Yet Approved</td>"
                                    ) .
                                    ($task_row['isApproved'] == 1 ?
                                        "<td>
                                            <form action='task.php' method='POST'
                                             onsubmit=\"return confirm('Are you sure you wanna Cancel the approval of this task?')\">
                                                <input type='hidden' name='user_id' value={$user_id}>
                                                <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                                <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                                <input type='hidden' name='action' value='disapprove'>
                                                <button type='submit' name='task'>Don't Approve</button>
                                            </form>
                                        </td>" :
                                        "<td>
                                            <form action='task.php' method='POST'
                                            onsubmit=\"return confirm('Are you sure you wanna Approve this task?')\">
                                                <input type='hidden' name='user_id' value={$user_id}>
                                                <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                                <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                                <input type='hidden' name='action' value='approve'>
                                                <button type='submit' name='task'>Approve</button>
                                            </form>
                                        </td>"
                                    ) .
                                "<td>
                                    <form action='task.php' method='POST'
                                    onsubmit=\"return confirm('Are you sure you wanna delete this task?')\">
                                        <input type='hidden' name='user_id' value={$user_id}>
                                        <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                        <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                        <input type='hidden' name='action' value='delete'>
                                        <button type='submit' name='task'>delete</button>
                                    </form>
                                </td>
                                <td>
                                    <button onclick=\"openPanel('update_task')\">Update Task</button>
                                    <div id='update_task' class='panel hide'>
                                        <form action='task.php' method='POST'>
                                            <input type='hidden' name='user_id' value={$user_id}>
                                            <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                            <input type='hidden' name='totalCapygrass' value={$user_row['totalCapygrass']}>
                                            <input type='hidden' name='action' value='update'>
                                            <label>
                                                Description : <textarea name='description'>{$task_row['description']}</textarea>
                                            </label>
                                            <button type='submit' name='task'>Update</button>
                                        </form>
                                        <button onclick=\"closePanel('update_task')\">Close</button>
                                    </div>
                                </td>
                                </tr>";
                            }
                        }
                        else{
                            echo "<td>No Tasks</td>";
                        }
                    ?>
                </tbody>
            </table>
        </div>
        <div>
            <h4>Unlocked Features</h4>
            <?php
                if($user_features_results->num_rows > 0)
                {
                    $user_feature = $user_features_results->fetch_assoc();
                    echo "
                        <table>
                            <thead>
                                <th>Feature</th>
                                <th>Date Unlocked</th>
                            <thead>
                            <tbody>
                                <tr>
                                    <td>{$user_feature['name']}</td>
                                    <td>{$user_feature['unlockedAt']}</td>
                                </tr>
                            </tbody>
                        </table>
                    ";
                }
                else{
                    echo "
                        <h2>No User Features</h2>
                    ";
                }
            ?>
        </div>
        <div>
            <h2>Study Sessions</h2>
            <table>
                <thead>
                    <th>Study Technique</th>
                    <th>Start Time</th>
                    <th>End time</th>
                    <th>Duration in Msinutes</th>
                    <th>Points Earned</th>
                </thead>
                <tbody>
                    <?php
                        if($sessions_results->num_rows > 0)
                        {
                            while($session_row = $sessions_results->fetch_assoc())
                            {
                                echo "
                                    <tr>
                                        <td>{$session_row['techniqueName']}</td>
                                        <td>{$session_row['startTime']}</td>
                                        <td>{$session_row['endTime']}</td>
                                        <td>{$session_row['durationMinutes']}</td>
                                        <td>{$session_row['pointsEarned']}</td>
                                    </tr>
                                ";
                            }
                        }
                        else{
                            echo "
                                <td>No Study Sessions Yet</td>                            
                            ";
                        }
                    ?>
                </tbody>
            </table>
        </div>
        <div>
            <form action="delete_user.php" method="POST" 
            onsubmit="return confirm('Are you sure you wanna delete this user?')">
                <input type="hidden" name="user_id" value=<?php echo $user_id; ?>>
                <button type="submit" name="delete_user">Delete User</button>
            </form>
            <button onclick="openPanel('update_panel')">Update Information</button>
        </div>
        <div class="panel hide" id="update_panel">
            <form action="update_user.php" method="POST">
                <div class="input-group">
                    <label>First Name</label>
                    <input type="text" name="fname" value=<?php echo $user_row['fName'];?> required>
                </div>
                <div class="input-group">
                    <label>Middle Name (Optional)</label>
                    <input type="text" name="mname" value=<?php echo $user_row['mName'];?>>
                </div>
                <div class="input-group">
                    <label>Last Name</label>
                    <input type="text" name="lname"  value=<?php echo $user_row['lName'];?> required>
                </div>
                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username"  value=<?php echo $user_row['username'];?> required>
                </div>
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email"  value=<?php echo $user_row['email'];?> required>
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password"  value=<?php echo $user_row['password'];?> required>
                </div>
                <input type="hidden" name="user_id" value=<?php echo $user_id; ?>>
                <button type="submit" name="confirm_update">Update</button>
            </form>
            <button onclick="closePanel('update_panel')">hide</button>
        </div>
    </div>
    <script src="admin_side.js"></script>
</body>
</html>