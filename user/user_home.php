<?php
session_start();
require '../db_manager.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert({$_SESSION["user_id"]});</script>";
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$db = new DBManager();
$read_conn = $db->getReadConn();
$write_conn = $db->getWriteConn();

$wallet_query = "SELECT totalCapygrass FROM CAPYGRASSWALLET WHERE User_id = ?";
$wallet_stmt = $read_conn->prepare($wallet_query);
$wallet_stmt->bind_param("i", $user_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$capygrass = 0;

if ($row = $wallet_result->fetch_assoc()) {
    $capygrass = $row['totalCapygrass'];
}

$rank_query = "SELECT r.name FROM USERRANKS ur 
               JOIN RANKS r ON ur.rank_id = r.rank_id
               WHERE ur.user_id = ? ORDER BY ur.assignedAt DESC LIMIT 1";
$rank_stmt = $read_conn->prepare($rank_query);
$rank_stmt->bind_param("i", $user_id);
$rank_stmt->execute();
$rank_result = $rank_stmt->get_result();

$current_rank = "Unranked";
if ($rank_row = $rank_result->fetch_assoc()) {
    $current_rank = $rank_row['name'];
}

$notes_query = "SELECT title, content, pointsEarned, updatedAt FROM notes
                WHERE user_id = ?";
$notes_stmt = $read_conn->prepare($notes_query);
$notes_stmt->bind_param("s", $user_id);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();

$query = "SELECT * FROM TASKS
            WHERE user_id = ?";
$task_stmt = $read_conn->prepare($query);
$task_stmt->bind_param("i", $user_id);
$task_stmt->execute();
$task_results = $task_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My UpGrade Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/user_home.css">
</head>
<body>
<div clas="left-options">
    <div class="btn-carousel">
        <button class="btn-toggle" id="btnNotes">Notes</button>
        <button class="btn-toggle" id="btnTasks">Tasks</button>
        <button>Features</button>
    </div>
</div>
<div class="panel hidden" id="NotesPanel">
    <div>
        <h3>Notes</h3>
        <form method="POST" action="../processes/note.php">
            <input type="text" name="title" placeholder="Note Title..." required>
            <textarea name="content" rows="4" placeholder="Type your study notes here..." required></textarea>
            <button type="submit" name="create_note" class="action-btn">Save Note & Earn Points</button>
        </form>
    </div>
    <div>
        <h3>Your Recent Notes</h3>
        <?php if ($notes_result->num_rows > 0): ?>
            <?php while($note = $notes_result->fetch_assoc()): ?>
                <div class="note-card">
                    <h4><?php echo htmlspecialchars($note['title']); ?></h4>
                    <p><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                    <small style="color: #aaa;"><?php echo date("M j, g:i A", strtotime($note['updatedAt'])); ?></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #7f8c8d;">No notes yet. Create one to earn Capygrass!</p>
        <?php endif; ?>
    </div>
</div>
<div>
    <div class="panel hidden" id="tasksPanel" style="margin-top: 20px;">
        <h3>Task Manager</h3>
        <form action="../processes/task.php" method="POST">
            <textarea name="description" rows="4" placeholder="Type your Task Description here..." required></textarea>
            <input type="hidden" name="user_id" value=<?php echo $user_id; ?>>
            <input type="hidden" name="tool_id" value=2>
            <input type="hidden" name="action" value="create">
            <button type="submit" name="task">Submit Task</button>
        </form>
        <div>
            <h3>Task List</h3>
            <table>
                <thead>
                    <th>Description</th>
                    <th>Date Created</th>
                    <th>Date Finished</th>
                    <th>Finish</th>
                    <th>Delete Task</th>
                    <th>Update Task</th>
                </thead>
                <tbody>
                    <?php
                        if($task_results->num_rows > 0)
                        {
                            while($task_row = $task_results->fetch_assoc())
                            {
                                echo "<tr>
                                        <td>{$task_row['description']}</td>
                                        <td>{$task_row['createdAt']}</td>"
                                       . (($task_row['isFinished'] == 1 && $task_row['isApproved'] == 1) ?
                                            "<td>{$task_row['finishedAt']}<br>
                                            You can no longer cancel</td>" :
                                             (($task_row['isFinished'] == 1 && $task_row['isApproved'] == 0) ?
                                             "<td>
                                             {$task_row['finishedAt']} <br>
                                                <form action='../processes/task.php' method='POST'>
                                                    <input type='hidden' name='user_id' value={$user_id}>
                                                    <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                                    <input type='hidden' name='action' value='not_finish'>
                                                    <button type='submit' name='task'>Cancel</button>
                                                </form>
                                             </td>" :
                                            "<td>
                                                <form action='../processes/task.php' method='POST'>
                                                    <input type='hidden' name='user_id' value={$user_id}>
                                                    <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                                    <input type='hidden' name='action' value='finish'>
                                                    <button type='submit' name='task'>Finish</button>
                                                </form>
                                             </td>")) .                    
                                    (!empty($task_row['finsihedAt']) ?
                                            "<td>
                                                <form action='delete_task.php' method='POST'
                                                 onsubmit=\"return confirm('Are you sure you want to delete this task?')\">
                                                    <input type='hidden' name='user_id' value={$user_id}>
                                                    <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                                    <input type='hidden' name='action' value='user_delete'>
                                                    <button type='submit' name='task'>Delete</button>
                                                </form>
                                            </td>" :
                                            "<td>Task Already Finished</td>") .
                                            (!empty($task_row['finsihedAt']) ?
                                            "<td>
                                                <button onclick=\"openPanel('update_task')\">Update</button>
                                                <div id='update_task' class='panel hide'>
                                                    <form action='update_task.php' method='POST'
                                                    onsubmit=\"return confirm('Are you sure you want to delete this task?')\">
                                                        <input type='hidden' name='user_id' value={$user_id}>
                                                        <input type='hidden' name='task_id' value={$task_row['task_id']}>
                                                        <input type='hidden' name='action' value='user_update'>
                                                        <button type='submit' name='task'></button>
                                                    </form>
                                                    <button onclick=\"closePanel('update_task')\">Close</button>
                                                </div>
                                            </td>" :
                                            "<td>Task Already Finished</td>"); 
                            }
                        }
                        else{
                            echo "<td>No Tasks Yet</td>";
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>        
    <div class="panel hidden" id="study-sessions" style="margin-top: 20px;">
        <h3>Study Sessions</h3> 
        <p style="color: #7f8c8d;">Track your study time to level up your Rank.</p>
        <button class="action-btn" style="background: #e67e22;" onclick="alert('Study Timer coming soon!');">Start Session</button>
    </div>
</div>
<div class="dashboard-container">
    <div class="header-bar">
        <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Capygrass</h3>
            <p><?php echo $capygrass; ?></p>
        </div>
        <div class="stat-card">
            <h3>Current Rank</h3>
            <p><?php echo htmlspecialchars($current_rank); ?></p>
        </div>
    </div>

    <hr style="margin: 30px 0; border: 1px solid #eee;">

    <h3>Your Learning Hub</h3>
    <div class="grid-layout">
    </div>
</div>
<script src="user_home.js">
</script>
</body>
</html>