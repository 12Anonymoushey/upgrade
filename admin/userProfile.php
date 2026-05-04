<?php
include '../alert.php';
require_once '../db_manager.php';
session_start();
include '../confirm.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['userProfile']))
{
    $user_id = $_POST['user_id'];

    $db = new DBManager();
    $read_conn = $db->getReadConn();

    //get USERS with CAPYGRASSWALLET
    $query = "SELECT * FROM USERS u
                LEFT JOIN CAPYGRASSWALLET c ON u.user_id = c.user_id
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
    $query = "SELECT * FROM NOTES WHERE user_id = ?";
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
    $query = "SELECT * FROM TASKS WHERE user_id = ?";
    $task_stmt = $read_conn->prepare($query);
    $task_stmt->bind_param("i", $user_id);
    $task_stmt->execute();
    $task_results = $task_stmt->get_result();
}

if(!empty($_SESSION['alert']))
{
    echo "<script> alert('{$_SESSION['alert']}'); </script>";
    unset($_SESSION['alert']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Admin Control</title>
    <link rel="stylesheet" href="../css/userProfile.css?v=<?php echo filemtime('../css/userProfile.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Extra styles to ensure the banner looks clean */
        .profile-header-visual {
            position: relative;
            margin-bottom: 60px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .user-details {
            padding: 0 30px 20px;
        }
        .color-picker-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .color-picker-group input[type="color"] {
            width: 50px;
            height: 40px;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../alert.php'; ?>
    <?php include __DIR__ . '/../confirm.php'; ?>
    
    <div class="userprofile-container">
        
        <header class="profile-header" style="background: transparent; padding: 0;">
            <button class="back-btn" onclick="window.location.href='admin_home.php'" style="margin-bottom: 15px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </button>
            
            <div class="profile-header-visual">
                <div style="width: 100%; height: 200px; background-image: url('../uploads/<?php echo htmlspecialchars($user_row['profile_banner'] ?? 'default_banner.jpg'); ?>'); background-color: #ddd; background-size: cover; background-position: center; border-radius: 8px 8px 0 0;"></div>
                
                <div style="position: absolute; bottom: -40px; left: 30px; width: 110px; height: 110px; border-radius: 50%; background-color: #fff; border: 4px solid #3498db; background-image: url('../uploads/<?php echo htmlspecialchars($user_row['profilePhoto'] ?? ''); ?>'); background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: bold; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <?php if(empty($user_row['profilePhoto'])): ?>
                        <?php echo strtoupper(substr($user_row['username'], 0, 1)); ?>
                    <?php endif; ?>
                </div>

                <div class="user-details" style="margin-top: 50px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h1 style="margin: 0; font-size: 2rem;"><?php echo htmlspecialchars($user_row['fName'] . ' ' . $user_row['lName']); ?> <span style="font-size: 1.2rem; color: gray; font-weight: normal;">(@<?php echo htmlspecialchars($user_row['username'])?>)</span></h1>
                            <p style="margin: 10px 0; color: #555; font-style: italic; max-width: 600px;"><?php echo nl2br(htmlspecialchars($user_row['bio'] ?? 'No bio provided.')); ?></p>
                            <p style="margin: 5px 0; color: #777;"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user_row['email']); ?></p>
                        </div>
                        <div class="balance-card" style="text-align: right; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                            <small style="color: gray; font-weight: bold; text-transform: uppercase;">Current Balance</small>
                            <p style="margin: 5px 0 0; font-size: 1.5rem; color: #2ecc71; font-weight: bold;"><?php echo htmlspecialchars($user_row['totalCapygrass'] ?? 0) ?> CapyGrass</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-actions" style="margin-bottom: 20px; display: flex; gap: 10px;">
            <button class="update-btn" onclick="openPanel('update_panel')" style="padding: 10px 20px; background: #f39c12; color: #fff; border: none; border-radius: 5px; cursor: pointer;">Edit User Information</button>
            <form action="delete_user.php" method="POST" onsubmit="return confirm('Are you absolutely sure you want to completely delete this user? This cannot be undone.')" style="margin: 0;">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <button type="submit" name="delete_user" class="delete-btn" style="padding: 10px 20px; background: #e74c3c; color: #fff; border: none; border-radius: 5px; cursor: pointer;">Delete User</button>
            </form>
        </div>

        <div class="grid-layout">
            <div class="card full-width">
                <h3>CURRENT AND PREVIOUS RANKS</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank Name</th>
                                <th>Assigned At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                if($user_ranks_results->num_rows > 0) {
                                    while($rank_rows = $user_ranks_results->fetch_assoc()) {
                                        echo "<tr>
                                            <td>{$rank_rows['name']}</td>
                                            <td>{$rank_rows['assignedAt']}</td>    
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='2' style='text-align:center;'>No Rank Yet</td></tr>";
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card full-width">
            <h3>NOTES</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Date and Time Created</th>
                            <th>Points Earned</th>
                            <th>Approval Status</th>
                            <th>Approve / Disapprove</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            if($notes_results->num_rows > 0) {
                                while($notes_rows = $notes_results->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$notes_rows['title']}</td>
                                        <td>{$notes_rows['content']}</td>
                                        <td>{$notes_rows['updatedAt']}</td>
                                        <td>{$notes_rows['pointsEarned']}</td>" . 
                                        ($notes_rows['isApproved'] == 1 ? "<td style='color:green;'>Approved</td>" : "<td style='color:orange;'>Not Yet Approved</td>") .
                                        
                                        "<td>" . 
                                        ($notes_rows['isApproved'] == 1 ? "
                                            <form action='note.php' method='POST' onsubmit=\"return confirm('Are you sure you wanna un-approve this note?')\">
                                                <input type='hidden' name='user_id' value='{$user_id}'>
                                                <input type='hidden' name='note_id' value='{$notes_rows['note_id']}'>
                                                <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                <input type='hidden' name='action' value='disapprove'>
                                                <button type='submit' name='note' style='background:#e74c3c; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Disapprove</button>
                                            </form>" :
                                            "<form action='note.php' method='POST' onsubmit=\"return confirm('Are you sure you wanna Approve this note?')\">
                                                <input type='hidden' name='user_id' value='{$user_id}'>
                                                <input type='hidden' name='note_id' value='{$notes_rows['note_id']}'>
                                                <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                <input type='hidden' name='action' value='approve'>
                                                <button type='submit' name='note' style='background:#2ecc71; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Approve</button>
                                            </form>"
                                        ) . 
                                        "</td>
                                        
                                        <td style='display:flex; gap:5px;'>
                                            <button onclick=\"openPanel('update_note_{$notes_rows['note_id']}')\" style='background:#3498db; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Edit</button>
                                            <form action='note.php' method='POST' onsubmit=\"return confirm('Are you sure you wanna delete this note?')\">
                                                <input type='hidden' name='user_id' value='{$user_id}'>
                                                <input type='hidden' name='note_id' value='{$notes_rows['note_id']}'>
                                                <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                <input type='hidden' name='action' value='delete'>
                                                <button type='submit' name='note' style='background:#c0392b; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Delete</button>
                                            </form>
                                            
                                            <div id='update_note_{$notes_rows['note_id']}' class='panel hide' style='position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.2); z-index:1000;'>
                                                <h3>Edit Note</h3>
                                                <form action='note.php' method='POST' style='display:flex; flex-direction:column; gap:10px;'>
                                                    <input type='hidden' name='user_id' value='{$user_id}'>
                                                    <input type='hidden' name='note_id' value='{$notes_rows['note_id']}'>
                                                    <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                    <input type='hidden' name='action' value='update'>
                                                    <label>Title: <input type='text' name='title' value='{$notes_rows['title']}' style='width:100%;'></label>
                                                    <label>Content: <textarea name='content' rows='4' style='width:100%;'>{$notes_rows['content']}</textarea></label>    
                                                    <div style='display:flex; gap:10px;'>
                                                        <button type='submit' name='note' style='background:#2ecc71; color:#fff; border:none; padding:8px; border-radius:4px;'>Save Update</button>
                                                        <button type='button' onclick=\"closePanel('update_note_{$notes_rows['note_id']}')\" style='background:#95a5a6; color:#fff; border:none; padding:8px; border-radius:4px;'>Close</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center;'>No Notes Yet</td></tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card full-width">
            <h3>TASKS</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Date Finished</th>
                            <th>Points Earned</th>
                            <th>Approval Status</th>
                            <th>Approve</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            if($task_results->num_rows > 0) {
                                while($task_row = $task_results->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$task_row['description']}</td>"
                                        . ($task_row['isFinished'] == 1 ? "<td style='color:#3498db;'>Finished</td>" : "<td style='color:gray;'>Pending</td>") .
                                        "<td>{$task_row['createdAt']}</td> 
                                        <td>{$task_row['finishedAt']}</td>
                                        <td>{$task_row['pointsEarned']}</td> "
                                        . ($task_row['isApproved'] == 1 ? "<td style='color:green;'>Approved</td>" : "<td style='color:orange;'>Not Yet Approved</td>") .
                                        
                                        "<td>" . 
                                        ($task_row['isApproved'] == 1 ?
                                            "<form action='task.php' method='POST' onsubmit=\"return confirm('Are you sure you wanna Cancel the approval of this task?')\">
                                                <input type='hidden' name='user_id' value='{$user_id}'>
                                                <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                <input type='hidden' name='action' value='disapprove'>
                                                <button type='submit' name='task' style='background:#e74c3c; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Revoke</button>
                                            </form>" :
                                            "<form action='task.php' method='POST' onsubmit=\"return confirm('Are you sure you wanna Approve this task?')\">
                                                <input type='hidden' name='user_id' value='{$user_id}'>
                                                <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                <input type='hidden' name='action' value='approve'>
                                                <button type='submit' name='task' style='background:#2ecc71; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Approve</button>
                                            </form>"
                                        ) . 
                                        "</td>
                                        <td style='display:flex; gap:5px;'>
                                            <button onclick=\"openPanel('update_task_{$task_row['task_id']}')\" style='background:#3498db; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Edit</button>
                                            <form action='task.php' method='POST' onsubmit=\"return confirm('Are you sure you wanna delete this task?')\">
                                                <input type='hidden' name='user_id' value='{$user_id}'>
                                                <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                <input type='hidden' name='action' value='delete'>
                                                <button type='submit' name='task' style='background:#c0392b; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;'>Delete</button>
                                            </form>

                                            <div id='update_task_{$task_row['task_id']}' class='panel hide' style='position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.2); z-index:1000;'>
                                                <h3>Edit Task</h3>
                                                <form action='task.php' method='POST' style='display:flex; flex-direction:column; gap:10px;'>
                                                    <input type='hidden' name='user_id' value='{$user_id}'>
                                                    <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                    <input type='hidden' name='totalCapygrass' value='{$user_row['totalCapygrass']}'>
                                                    <input type='hidden' name='action' value='update'>
                                                    <label>Description: <textarea name='description' rows='3' style='width:100%;'>{$task_row['description']}</textarea></label>
                                                    <div style='display:flex; gap:10px;'>
                                                        <button type='submit' name='task' style='background:#2ecc71; color:#fff; border:none; padding:8px; border-radius:4px;'>Update</button>
                                                        <button type='button' onclick=\"closePanel('update_task_{$task_row['task_id']}')\" style='background:#95a5a6; color:#fff; border:none; padding:8px; border-radius:4px;'>Close</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' style='text-align:center;'>No Tasks</td></tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>UNLOCKED FEATURES</h3>
            <?php
                if($user_features_results->num_rows > 0) {
                    echo "
                        <div class='table-container'>
                        <table>
                            <thead>
                                <tr>
                                    <th>Feature</th>
                                    <th>Date Unlocked</th>
                                </tr>
                            </thead>
                            <tbody>";
                    while($user_feature = $user_features_results->fetch_assoc()) {
                        echo "<tr>
                                <td>{$user_feature['name']}</td>
                                <td>{$user_feature['unlockedAt']}</td>
                              </tr>";
                    }
                    echo "  </tbody>
                        </table>
                        </div>";
                } else {
                    echo "<p style='color:gray; padding:10px;'>No Features Unlocked Yet</p>";
                }
            ?>
        </div>

        <div class="card">
            <h3>STUDY SESSIONS</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Study Technique</th>
                            <th>Start Time</th>
                            <th>End time</th>
                            <th>Duration (Mins)</th>
                            <th>Points Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            if($sessions_results->num_rows > 0) {
                                while($session_row = $sessions_results->fetch_assoc()) {
                                    echo "
                                        <tr>
                                            <td>{$session_row['techniqueName']}</td>
                                            <td>{$session_row['startTime']}</td>
                                            <td>{$session_row['endTime']}</td>
                                            <td>{$session_row['durationMinutes']}</td>
                                            <td><strong style='color:#2ecc71;'>{$session_row['pointsEarned']}</strong></td>
                                        </tr>
                                    ";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align:center;'>No Study Sessions Yet</td></tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel hide" id="update_panel" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 25px rgba(0,0,0,0.3); z-index:1001; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
            <button type="button" class="btn-close" onclick="closePanel('update_panel')" style="float:right; border:none; background:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            <h2 style="margin-top:0;">Update User Information</h2>
            
            <form action="update_user.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                
                <h4 style="border-bottom:1px solid #eee; padding-bottom:5px;">Media & Aesthetics</h4>
                <div class="input-group" style="margin-bottom:10px;">
                    <label>Profile Photo</label>
                    <input type="file" name="profilePhoto" accept="image/*" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div class="input-group" style="margin-bottom:10px;">
                    <label>Cover Banner</label>
                    <input type="file" name="profile_banner" accept="image/*" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                
                <div class="color-picker-group">
                    <label>Background Color:</label>
                    <input type="color" name="bg_color" value="<?php echo htmlspecialchars($user_row['bg_color'] ?? '#ffffff'); ?>">
                    
                    <label style="margin-left:15px;">Button Color:</label>
                    <input type="color" name="btn_color" value="<?php echo htmlspecialchars($user_row['btn_color'] ?? '#3498db'); ?>">
                    
                    <label style="margin-left:15px;">Theme Color:</label>
                    <input type="color" name="theme_color" value="<?php echo htmlspecialchars($user_row['theme_color'] ?? '#ffffff'); ?>">
                </div>

                <h4 style="border-bottom:1px solid #eee; padding-bottom:5px;">Personal Details</h4>
                <div class="input-group" style="margin-bottom:10px;">
                    <label>Bio</label>
                    <textarea name="bio" rows="3" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?php echo htmlspecialchars($user_row['bio'] ?? ''); ?></textarea>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <div class="input-group" style="flex:1;">
                        <label>First Name</label>
                        <input type="text" name="fname" value="<?php echo htmlspecialchars($user_row['fName']); ?>" required style="width:100%; padding:8px;">
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label>Middle Name</label>
                        <input type="text" name="mname" value="<?php echo htmlspecialchars($user_row['mName'] ?? ''); ?>" style="width:100%; padding:8px;">
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label>Last Name</label>
                        <input type="text" name="lname" value="<?php echo htmlspecialchars($user_row['lName']); ?>" required style="width:100%; padding:8px;">
                    </div>
                </div>

                <div class="input-group" style="margin-top:10px;">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user_row['username']); ?>" required style="width:100%; padding:8px;">
                </div>
                <div class="input-group" style="margin-top:10px;">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_row['email']); ?>" required style="width:100%; padding:8px;">
                </div>
                <div class="input-group" style="margin-top:10px; margin-bottom: 20px;">
                    <label>Password</label>
                    <input type="password" name="password" value="<?php echo htmlspecialchars($user_row['password']); ?>" required style="width:100%; padding:8px;">
                </div>
                
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="confirm_update" style="background:#2ecc71; color:#fff; border:none; padding:12px; border-radius:5px; cursor:pointer; flex:1; font-weight:bold;">Save All Changes</button>
                    <button type="button" onclick="closePanel('update_panel')" style="background:#95a5a6; color:#fff; border:none; padding:12px; border-radius:5px; cursor:pointer;">Cancel</button>
                </div>
            </form>
    </div>
    
    <div id="overlay" class="hide" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;" onclick="closeAllPanels()"></div>

    <script>
        function openPanel(id) {
            document.getElementById(id).classList.remove('hide');
            document.getElementById('overlay').classList.remove('hide');
        }

        function closePanel(id) {
            document.getElementById(id).classList.add('hide');
            document.getElementById('overlay').classList.add('hide');
        }
        
        function closeAllPanels() {
            const panels = document.querySelectorAll('.panel');
            panels.forEach(p => p.classList.add('hide'));
            document.getElementById('overlay').classList.add('hide');
        }
    </script>
    <script src="admin_side.js"></script>
</body>
</html>