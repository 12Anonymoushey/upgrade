<?php
require_once(__DIR__ . '/../modal.php');
session_start();
require '../db_manager.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('" . $_SESSION["user_id"] . "');</script>";
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$db = new DBManager();
$read_conn = $db->getReadConn();
$write_conn = $db->getWriteConn();

// FETCH CURRENT USER'S SAVED SETTINGS (Added profilePhoto, bio, email)
$user_settings_query = "SELECT fName, mName, lName, username, bg_color, btn_color, theme_color, profile_banner, profilePhoto, bio, email FROM USERS WHERE user_id = ?";
$stmt = $read_conn->prepare($user_settings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// FETCH ALL CUSTOMIZATION FEATURES
$features_query = "SELECT feature_id, Name, minPoints FROM FEATURES ORDER BY minPoints ASC";
$all_features = $read_conn->query($features_query)->fetch_all(MYSQLI_ASSOC);

// FETCH CAPYGRASS WALLET
$wallet_query = "SELECT totalCapygrass FROM CAPYGRASSWALLET WHERE User_id = ?";
$wallet_stmt = $read_conn->prepare($wallet_query);
$wallet_stmt->bind_param("i", $user_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$capygrass = 0;

if ($row = $wallet_result->fetch_assoc()) {
    $capygrass = $row['totalCapygrass'];
}

// FETCH RANK
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

// FETCH NOTES
$notes_query = "SELECT * FROM notes WHERE user_id = ?";
$notes_stmt = $read_conn->prepare($notes_query);
$notes_stmt->bind_param("i", $user_id);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();

// FETCH TASKS
$query = "SELECT * FROM TASKS WHERE user_id = ?";
$task_stmt = $read_conn->prepare($query);
$task_stmt->bind_param("i", $user_id);
$task_stmt->execute();
$task_results = $task_stmt->get_result();

// FETCH COMMUNITY DECKS
$deck_query = "SELECT d.deck_id, d.title, u.username 
               FROM DECK d 
               JOIN USERS u ON d.user_id = u.user_id 
               WHERE d.user_id != ?";
$deck_stmt = $read_conn->prepare($deck_query);
$deck_stmt->bind_param("i", $user_id);
$deck_stmt->execute();
$community_decks = $deck_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// FETCH MyDECKS
$myDeck_query = "SELECT d.deck_id, d.title, u.username 
               FROM DECK d 
               JOIN USERS u ON d.user_id = u.user_id
               JOIN FLASHCARDS c ON d.deck_id = c.deck_id 
               WHERE d.user_id = ?";
$myDeck_stmt = $read_conn->prepare($myDeck_query);
$myDeck_stmt->bind_param("i", $user_id);
$myDeck_stmt->execute();
$my_decks = $myDeck_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// FETCH CURRENT FEATURE LEVEL
$feature_query = "SELECT f.feature_id, f.name FROM USERFEATURES uf 
                  JOIN FEATURES f ON uf.feature_id = f.feature_id
                  WHERE uf.user_id = ? ORDER BY uf.unlockedAt DESC LIMIT 1";
$feat_stmt = $read_conn->prepare($feature_query);
$feat_stmt->bind_param("i", $user_id);
$feat_stmt->execute();
$feat_result = $feat_stmt->get_result();

$current_feature_id = 1; // Default to Level 1
if ($feat_row = $feat_result->fetch_assoc()) {
    $current_feature_id = $feat_row['feature_id'];
}

// 1. Get Capygrass Wallet
$stmt = $read_conn->prepare("SELECT totalCapygrass FROM capygrasswallet WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$capygrass = $stmt->get_result()->fetch_assoc()['totalCapygrass'] ?? 0;
$stmt->close();

// 2. Get Current Rank
$stmt = $read_conn->prepare("
    SELECT r.name 
    FROM userranks ur 
    JOIN ranks r ON ur.rank_id = r.rank_id 
    WHERE ur.user_id = ? 
    ORDER BY ur.assignedAt DESC LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_rank = $stmt->get_result()->fetch_assoc()['name'] ?? 'Wood';
$stmt->close();

// 3. Get Focus Time (Pomodoro from studysessions)
$stmt = $read_conn->prepare("SELECT SUM(durationMinutes) AS total_mins FROM studysessions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_mins = $stmt->get_result()->fetch_assoc()['total_mins'] ?? 0;
$total_study_hours = round($total_mins / 60, 1);
$stmt->close();

// 4. Get Tasks Completed & Calculate Average
$stmt = $read_conn->prepare("SELECT COUNT(task_id) AS total_tasks FROM tasks WHERE user_id = ? AND isFinished = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_tasks_completed = $stmt->get_result()->fetch_assoc()['total_tasks'] ?? 0;
$stmt->close();

// Get account age to calculate tasks per day
$stmt = $read_conn->prepare("SELECT DATEDIFF(NOW(), createdAt) AS days_active FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$days_active = $stmt->get_result()->fetch_assoc()['days_active'] ?? 1;
$days_active = $days_active > 0 ? $days_active : 1; // Prevent division by zero
$avg_tasks_per_day = round($total_tasks_completed / $days_active, 1);
$stmt->close();

// 5. Decks Created (Replacing Quizzes since it's not in the DB)
$stmt = $read_conn->prepare("SELECT COUNT(deck_id) AS total_decks FROM deck WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_decks_created = $stmt->get_result()->fetch_assoc()['total_decks'] ?? 0;
$stmt->close();

// 6. Flashcards Mastered (Box level 5)
$stmt = $read_conn->prepare("
    SELECT COUNT(f.card_id) AS mastered 
    FROM flashcards f 
    JOIN deck d ON f.deck_id = d.deck_id 
    WHERE d.user_id = ? AND f.box_level >= 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$leitner_cards_mastered = $stmt->get_result()->fetch_assoc()['mastered'] ?? 0;
$stmt->close();

// 7. Notes Created
$stmt = $read_conn->prepare("SELECT COUNT(note_id) AS total_notes FROM notes WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_notes = $stmt->get_result()->fetch_assoc()['total_notes'] ?? 0;
$stmt->close();

// 8. Streak Placeholder (Requires a new table/column to track daily logins)
$current_streak = 0; 
$longest_streak = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My UpGrade Dashboard</title>
    <link rel="stylesheet" href="../css/user_home.css">
    <style>
        :root {
            --background: <?php echo htmlspecialchars($user_data['bg_color']); ?> !important;
            --primary: <?php echo htmlspecialchars($user_data['btn_color']); ?> !important;
            --secondary: <?php echo htmlspecialchars($user_data['theme_color']); ?> !important;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="left-container">
        <div class="left-btn btn-carousel">
            <button class="btn-toggle" id="btnProfile" onclick="openPanel('profilePanel')">
                <img src="../assets/profile.png" alt="profile" onerror="this.style.display='none'">
                <span class="btn-label">Profile</span>
            </button>
            <button class="btn-toggle" id="btnNotes" onclick="openPanel('notesPanel')">
                <img src="../assets/notes.png" alt="notes" onerror="this.style.display='none'">
                <span class="btn-label">Notes</span>
            </button>
            <button class="btn-toggle" id="btnTasks" onclick="openPanel('tasksPanel')">
                <img src="../assets/task.png" alt="task" onerror="this.style.display='none'">
                <span class="btn-label">Tasks</span>
            </button>
            <button class="btn-toggle" id="btnPomodoro" onclick="openPanel('pomodoroPanel')">
                <img src="../assets/pomodoro.png" alt="pomodoro" onerror="this.style.display='none'">
                <span class="btn-label">Pomodoro</span>
            </button>
            <button class="btn-toggle" id="btnLeitner" onclick="openPanel('leitnerPanel')">
                <img src="../assets/leitner.png" alt="leitner" onerror="this.style.display='none'">
                <span class="btn-label">Leitner</span>
            </button>
            <button class="btn-toggle" id="btnMyQuizzes" onclick="openPanel('myQuizzesPanel')">
                <img src="../assets/myquizzes.png" alt="myquizzes" onerror="this.style.display='none'">
                <span class="btn-label">My Quizzes</span>
            </button>
            <button class="btn-toggle" id="btnCustomize" onclick="openPanel('customizePanel')">
                <img src="../assets/features.png" alt="features" onerror="this.style.display='none'">
                <span class="btn-label">Customize</span>
            </button>
        </div>
    </div>
    <div class="right-container">
        <div class="panel hidden" id="profilePanel" style="margin-top: 20px;">
    <button type="button" onclick="closePanel('profilePanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
    <h2>My Profile</h2>

    <div class="profile-header" style="position: relative; margin-bottom: 60px;">
        <div style="width: 100%; height: 180px; background-image: url('../uploads/<?php echo htmlspecialchars($user_data['profile_banner'] ?? 'default_banner.jpg'); ?>'); background-color: #ddd; background-size: cover; background-position: center; border-radius: 8px; border: 2px solid var(--primary);"></div>
        
        <div style="position: absolute; bottom: -40px; left: 30px; width: 100px; height: 100px; border-radius: 50%; background-color: #fff; border: 4px solid var(--primary); background-image: url('../uploads/<?php echo htmlspecialchars($user_data['profilePhoto'] ?? ''); ?>'); background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <?php if(empty($user_data['profilePhoto'])): ?>
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="profileViewMode" style="margin-top: 20px; padding: 0 10px;">
        <div style="margin-bottom: 15px;">
            <p><strong>Email Address:</strong><br> <?php echo htmlspecialchars($user_data['email'] ?? 'No email provided'); ?></p>
        </div>
        <div style="margin-bottom: 20px;">
            <p><strong>Bio / About Me:</strong><br> <?php echo nl2br(htmlspecialchars($user_data['bio'] ?? 'No bio yet.')); ?></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="toggleProfileEdit()" class="action-btn" style="padding: 10px 20px; font-size: 1rem; width: auto;">Edit Profile</button>
            <button onclick="togglePasswordEdit()" class="action-btn" style="padding: 10px 20px; font-size: 1rem; width: auto; background-color: #f39c12;">Change Password</button>
        </div>
    </div>

    <div class="profile-info-form" id="profileEditMode" style="display: none;">
        <h3>Update Information</h3>
        <form action="../processes/update_profile.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 15px; max-width: 500px; margin-top: 15px;">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
            
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label><strong>Profile Photo:</strong></label>
                <input type="file" name="profilePhoto" accept="image/*" style="padding: 5px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label><strong>Email Address:</strong></label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="your.email@example.com" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px;" required>
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label><strong>Bio / About Me:</strong></label>
                <textarea name="bio" rows="4" placeholder="Tell the community about yourself..." style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; resize: vertical;"><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button type="submit" name="update_profile" class="action-btn" style="padding: 10px 20px; font-size: 1rem;">Save Changes</button>
                <button type="button" onclick="toggleProfileEdit()" class="btn-toggle" style="padding: 10px 20px; font-size: 1rem; background-color: #02084B; color: white;">Cancel</button>
            </div>
        </form>
    </div>

    <div class="profile-info-form" id="passwordEditMode" style="display: none; margin-top: 20px;">
        <h3>Change Password</h3>
        <form action="../processes/change_password.php" method="POST" style="display: flex; flex-direction: column; gap: 15px; max-width: 500px; margin-top: 15px;">
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label><strong>Current Password:</strong></label>
                <input type="password" name="current_password" required style="padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label><strong>New Password:</strong></label>
                <input type="password" name="new_password" id="new-password-input" 
                       pattern="(?=.*[a-zA-Z])(?=.*\d)(?=.*[\W_]).+" 
                       title="Password must contain at least one letter, one number, and one special character." required style="padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label><strong>Confirm New Password:</strong></label>
                <input type="password" name="confirm_new_password" id="confirm-new-password-input" required style="padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button type="submit" name="change_password" class="action-btn" style="padding: 10px 20px; font-size: 1rem; background-color: #f39c12;">Update Password</button>
                <button type="button" onclick="togglePasswordEdit()" class="btn-toggle" style="padding: 10px 20px; font-size: 1rem; background-color: #02084B; color: white;">Cancel</button>
            </div>
        </form>
    </div>
</div>
        <div class="panel hidden" id="notesPanel">
             <button type="button" onclick="closePanel('notesPanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
            <div class="panel-content">
                <div class="panel-header">
                    <h3>Notes</h3>
                </div>
                <form action="../processes/note.php" method="POST">
                   <div class="input-group">
                    <label for="title">Title</label>
                     <input type="text" name="title" placeholder="Note Title" required>
                   </div>

                   <div class="input-group">
                    
                    <textarea name="content" rows="4" placeholder="Type your Note content here..." required></textarea>
                   </div>
                   
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="action" value="create">
                    <button type="submit" name="note" class="action-btn">Submit Note</button>
                </form>
            </div>
            <div style="margin-top: 20px;">
                <h3>Note List</h3>
                <table style="width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Date Updated</th>
                            <th>Points</th>
                            <th>Status</th>
                            <th>Delete</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            if($notes_result->num_rows > 0) {
                                while($note_row = $notes_result->fetch_assoc()) {
                                    $isApproved = ($note_row['isApproved'] == 1);
                                    echo "<tr>
                                            <td>" . htmlspecialchars($note_row['title']) . "</td>
                                            <td>" . nl2br(htmlspecialchars($note_row['content'])) . "</td>
                                            <td>" . date("M j, g:i A", strtotime($note_row['updatedAt'])) . "</td>
                                            <td>{$note_row['pointsEarned']}</td>";

                                    if($isApproved) {
                                        echo "<td><span style='color:#2ecc71;'>Approved</span></td>
                                              <td>Locked</td>
                                              <td>Locked</td>";
                                    } else {
                                        echo "<td><span style='color:#f39c12;'>Pending</span></td>
                                            <td>
                                                <form class='delete-form' data-type='note' action='../processes/note.php' method='POST'>
                                                    <input type='hidden' name='user_id' value='{$user_id}'>
                                                    <input type='hidden' name='note_id' value='{$note_row['note_id']}'>
                                                    <input type='hidden' name='action' value='user_delete'>
                                                    <button type='submit' name='note'>Delete</button>
                                                </form>
                                            </td>
                                            <td>
                                                <button onclick=\"openPanel('update_note_{$note_row['note_id']}')\">Update</button>
                                                <div id='update_note_{$note_row['note_id']}' class='panel hidden'>
                                                    <form action='../processes/note.php' method='POST'>
                                                        <input type='text' name='title' value='" . htmlspecialchars($note_row['title']) . "'>
                                                        <textarea name='content' rows='4'>" . htmlspecialchars($note_row['content']) . "</textarea>
                                                        <input type='hidden' name='user_id' value='{$user_id}'>
                                                        <input type='hidden' name='note_id' value='{$note_row['note_id']}'>
                                                        <input type='hidden' name='action' value='user_update'>
                                                        <button type='submit' name='note'>Save Changes</button>
                                                    </form>
                                                    <button onclick=\"closePanel('update_note_{$note_row['note_id']}')\" style='margin-top:10px;'>Close</button>
                                                </div>
                                            </td>";
                                    }
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7'>No Notes Yet. Create one to earn Capygrass!</td></tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel hidden" id="tasksPanel" style="margin-top: 20px;">
             <button type="button" onclick="closePanel('tasksPanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
            <h3>Task Manager</h3>
            <form action="../processes/task.php" method="POST">
                <textarea name="description" rows="4" placeholder="Type your Task Description here..." required></textarea>
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="tool_id" value="2">
                <input type="hidden" name="action" value="create">
                <button type="submit" name="task" class="action-btn">Submit Task</button>
            </form>
            <div style="margin-top: 20px;">
                <h3>Task List</h3>
                <table style="width: 100%; text-align: left;">
                    <thead>
                        <th>Description</th>
                        <th>Date Created</th>
                        <th>Finish</th>
                        <th>Delete Task</th>
                        <th>Update Task</th>
                    </thead>
                    <tbody>
                        <?php
                            if($task_results->num_rows > 0) {
                                while($task_row = $task_results->fetch_assoc()) {
                                    echo "<tr>
                                            <td>{$task_row['description']}</td>
                                            <td>{$task_row['createdAt']}</td>"
                                           . (($task_row['isFinished'] == 1 && $task_row['isApproved'] == 1) ?
                                                "<td>{$task_row['finishedAt']}<br><small>You can no longer cancel</small></td>" :
                                                (($task_row['isFinished'] == 1 && $task_row['isApproved'] == 0) ?
                                                "<td>
                                                    {$task_row['finishedAt']} <br>
                                                    <form action='../processes/task.php' method='POST'>
                                                        <input type='hidden' name='user_id' value='{$user_id}'>
                                                        <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                        <input type='hidden' name='action' value='not_finish'>
                                                        <button type='submit' name='task'>Cancel</button>
                                                    </form>
                                                 </td>" :
                                                "<td>
                                                    <form action='../processes/task.php' method='POST'>
                                                        <input type='hidden' name='user_id' value='{$user_id}'>
                                                        <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                        <input type='hidden' name='action' value='finish'>
                                                        <button type='submit' name='task'>Finish</button>
                                                    </form>
                                                 </td>")) .                    
                                        (empty($task_row['finishedAt']) ?
                                            "<td>
                                                <form class='delete-form' data-type='task' action='../processes/task.php' method='POST'>
                                                    <input type='hidden' name='user_id' value='{$user_id}'>
                                                    <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                    <input type='hidden' name='action' value='user_delete'>
                                                    <button type='submit' name='task'>Delete</button>
                                                </form>
                                            </td>" :
                                            "<td>Task Already Finished</td>") .
                                        (empty($task_row['finishedAt']) ?
                                            "<td>
                                                <button onclick=\"openPanel('update_task_{$task_row['task_id']}')\">Update</button>
                                                <div id='update_task_{$task_row['task_id']}' class='panel hidden'>
                                                    <form class='update-task-form' action='../processes/task.php' method='POST'>
                                                        <input type='hidden' name='user_id' value='{$user_id}'>
                                                        <input type='hidden' name='task_id' value='{$task_row['task_id']}'>
                                                        <input type='hidden' name='action' value='user_update'>
                                                        <textarea name='description' rows='4'>" . htmlspecialchars($task_row['description']) . "</textarea>
                                                        <button type='submit' name='task'>Update</button>
                                                    </form>
                                                    <button onclick=\"closePanel('update_task_{$task_row['task_id']}')\" style='margin-top:10px;'>Close</button>
                                                </div>
                                            </td>" :
                                            "<td>Task Already Finished</td>"); 
                                }
                            } else {
                                echo "<tr><td colspan='5'>No Tasks Yet</td></tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel hidden" id="pomodoroPanel" style="margin-top: 20px;">
            <button type="button" onclick="closePanel('pomodoroPanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
            <h1>Pomodoro</h1>
            <div class="pomodoro-settings">
                <div class="pomodoro-input-group">
                    <label for="study-input">Study (mins):</label>
                    <input type="number" id="study-input" value="25" min="1">
                </div>
                <div class="pomodoro-input-group">
                    <label for="break-input">Break (mins):</label>
                    <input type="number" id="break-input" value="5" min="1">
                </div>
                <button id="apply-btn" class="action-btn">Apply</button>
            </div>

            <h2 id="mode-indicator" style="margin-top: 20px;">Study Time</h2>
            <div class="timer-display" id="time" style="font-size: 3rem; font-weight: bold; margin: 20px 0;">25:00</div>
            <div class="timer-display" id="accumulated-time">Total Focus: 0 mins</div>
            
            <div class="pomodoro-controls" style="display: flex; gap: 10px; margin-top: 20px;">
                <button id="start-btn" class="action-btn">Start</button>
                <button id="pause-btn" class="action-btn" style="background:#f39c12;">Pause</button>
                <button id="reset-btn" class="action-btn" style="background:#e74c3c;">Stop / Reset</button>
            </div>
        </div>

        <div class="panel hidden" id="leitnerPanel" style="margin-top: 20px;">
            <button type="button" onclick="closePanel('leitnerPanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
            <h2>Community Leitner Quizzes</h2>
            <p>Study flashcards created by other students to earn Capygrass!</p>

            <ul style="list-style: none; padding: 0;">
                <?php foreach($community_decks as $deck): ?>
                    <li style="background: #fff; padding: 15px; margin-bottom: 10px; border-radius: 8px; border-left: 4px solid var(--primary); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo htmlspecialchars($deck['title']); ?></strong><br>
                            <small>Created by: <?php echo htmlspecialchars($deck['username']); ?></small>
                        </div>
                        <button class="action-btn" onclick="startCommunityQuiz(<?php echo $deck['deck_id']; ?>)">Study Deck</button>
                    </li>
                <?php endforeach; ?>
                <?php if(empty($community_decks)) echo "<li>No community decks available right now.</li>"; ?>
            </ul>

            <div id="quiz-area" class="hidden" style="margin-top: 20px; text-align: center; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <h3 id="quiz-front" style="font-size: 1.5rem; margin-bottom: 20px;">Question</h3>
                <p id="quiz-back" class="hidden" style="font-size: 1.2rem; color: var(--primary); font-weight: bold; margin-bottom: 20px;">Answer</p>
                
                <button id="quiz-show-answer" class="action-btn">Show Answer</button>
                
                <div id="quiz-controls" class="hidden" style="display: flex; gap: 10px; justify-content: center;">
                    <button id="quiz-wrong" class="action-btn" style="background: #e74c3c;">Needs Review</button>
                    <button id="quiz-correct" class="action-btn" style="background: #2ecc71;">Got It</button>
                </div>

                <form id="quiz-reward-form" action="quiz_process.php" method="POST" class="hidden" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="reward">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <button type="submit" class="action-btn" style="background: #f39c12; font-size: 1.1rem; padding: 10px 20px;">Claim Quiz Reward (20 Capygrass)</button>
                </form>
            </div>
        </div>

        <div class="panel hidden" id="myQuizzesPanel" style="margin-top: 20px;">
     <button type="button" onclick="closePanel('myQuizzesPanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
    <h2>My Leitner Quizzes</h2>
    <p>Create and manage your own flashcard decks.</p>

    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <input type="text" id="new-deck-title" placeholder="Enter new quiz title..." style="flex: 1;">
        <button class="action-btn" onclick="createDeck()" style="width: auto;">Create Deck</button>
    </div>

    <ul id="my-decks-list" style="list-style: none; padding: 0;">
        <?php 
        // Track displayed decks to prevent duplicates caused by the FLASHCARDS JOIN in your query
        $displayed_decks = [];
        foreach ($my_decks as $deck): 
            if (in_array($deck['deck_id'], $displayed_decks)) continue;
            $displayed_decks[] = $deck['deck_id'];
        ?>
            <li style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee;">
                <span>
                    <strong><?php echo htmlspecialchars($deck['title']); ?></strong> 
                    <small style="color: #7f8c8d;">(by <?php echo htmlspecialchars($deck['username']); ?>)</small>
                </span>
                <div>
                    <button class="action-btn" onclick="openEditDeck(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars(addslashes($deck['title'])); ?>')" style="width: auto; background: #3498db; margin-right: 5px;">Edit Cards</button>
                    <button class="action-btn" onclick="deleteDeck(<?php echo $deck['deck_id']; ?>)" style="width: auto; background: #e74c3c;">Delete</button>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (empty($my_decks)): ?>
            <li>No decks found. Create one above!</li>
        <?php endif; ?>
    </ul>

    <div id="edit-deck-area" class="hidden" style="margin-top: 30px; border-top: 2px solid #eee; padding-top: 20px;">
        <button class="action-btn" onclick="closeEditDeck()" style="background: #7f8c8d; width: auto; margin-bottom: 15px;">&larr; Back to Decks</button>
        
        <h3 id="editing-deck-title">Editing Deck</h3>

        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <input type="hidden" id="edit-deck-id">
            <input type="text" id="new-card-front" placeholder="Question (Front)" style="flex: 1;">
            <input type="text" id="new-card-back" placeholder="Answer (Back)" style="flex: 1;">
            <button class="action-btn" onclick="addCard()" style="width: auto;">Add Card</button>
        </div>

        <ul id="my-cards-list" style="list-style: none; padding: 0;"></ul>
    </div>
</div>

        <div class="panel scrollablePanel hidden" id="customizePanel" style="margin-top: 20px;">
            <button type="button" onclick="closePanel('customizePanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
            <div style="width: 100%; height: 150px; background-image: url('../uploads/<?php echo htmlspecialchars($user_data['profile_banner'] ?? ''); ?>'); background-color: #ddd; background-size: cover; background-position: center; border-radius: 8px; margin-bottom: 20px; border: 2px solid var(--primary);"></div>
            
            <h2>Profile Customization</h2>
            <p>Spend time studying to earn Capygrass and unlock new ways to customize your dashboard!</p>

            <div class="customization-grid" style="display: grid; gap: 15px; margin-top: 20px;">
                <?php foreach($all_features as $feat): ?>
                    <div style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid var(--primary);">
                        <div>
                            <strong><?php echo htmlspecialchars($feat['Name']); ?></strong><br>
                            <?php if($capygrass >= $feat['minPoints']): ?>
                                <small style="color: #2ecc71;">Unlocked!</small>
                            <?php else: ?>
                                <small style="color: #e74c3c;">Unlocks at <?php echo $feat['minPoints']; ?> Capygrass</small>
                            <?php endif; ?>
                        </div>

                        <div>
                            <?php if($capygrass >= $feat['minPoints']): ?>
                                <?php if($feat['Name'] == 'customizePersonalInfo'): ?>
                                    <input type="text" id="cust-fname" value="<?php echo htmlspecialchars($user_data['fName']); ?>" placeholder="First Name" style="width: 120px; margin-right:5px;">
                                    <input type="text" id="cust-mname" value="<?php echo htmlspecialchars($user_data['mName']); ?>" placeholder="Middle" style="width: 80px; margin-right:5px;">
                                    <input type="text" id="cust-lname" value="<?php echo htmlspecialchars($user_data['lName']); ?>" placeholder="Last Name" style="width: 120px; margin-right:5px;">
                                    <button class="action-btn" onclick="saveCustomization('personal')" style="width: auto;">Save Info</button>

                                <?php elseif($feat['Name'] == 'customizeName'): ?>
                                    <input type="text" id="cust-username" value="<?php echo htmlspecialchars($user_data['username']); ?>" placeholder="Username" style="width: 150px; margin-right:5px;">
                                    <button class="action-btn" onclick="saveCustomization('username')" style="width: auto;">Save Name</button>

                                <?php elseif($feat['Name'] == 'customizeBgColor'): ?>
                                    <input type="color" id="cust-bg" value="<?php echo htmlspecialchars($user_data['bg_color']); ?>" onchange="saveCustomization('bg_color', this.value)" style="cursor: pointer; height: 35px;">

                                <?php elseif($feat['Name'] == 'customizeBtn'): ?>
                                    <input type="color" id="cust-btn" value="<?php echo htmlspecialchars($user_data['btn_color']); ?>" onchange="saveCustomization('btn_color', this.value)" style="cursor: pointer; height: 35px;">

                                <?php elseif($feat['Name'] == 'customizeProfileBanner'): ?>
                                    <form id="banner-form" onsubmit="saveBanner(event)" enctype="multipart/form-data" style="display:flex; gap: 5px; align-items:center;">
                                        <input type="file" id="cust-banner" name="banner" accept="image/*" style="width: 200px;">
                                        <button type="submit" class="action-btn" style="width: auto;">Upload</button>
                                    </form>

                                <?php elseif($feat['Name'] == 'customizeAllColor'): ?>
                                    <input type="color" id="cust-theme" value="<?php echo htmlspecialchars($user_data['theme_color']); ?>" onchange="saveCustomization('theme_color', this.value)" style="cursor: pointer; height: 35px;">
                                <?php endif; ?>

                            <?php else: ?>
                                <button class="action-btn" style="background: #ccc; cursor: not-allowed; width: auto;" disabled>Locked</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="top-profile">
            <div class="profile-header" style="position: relative; margin-bottom: 60px;">
                <div style="width: 100%; height: 180px; background-image: url('../uploads/<?php echo htmlspecialchars($user_data['profile_banner'] ?? 'default_banner.jpg'); ?>'); background-color: #ddd; background-size: cover; background-position: center; border-radius: 8px; border: 2px solid var(--primary);"></div>
                
                <div style="position: absolute; bottom: -40px; left: 30px; width: 100px; height: 100px; border-radius: 50%; background-color: #fff; border: 4px solid var(--primary); background-image: url('../uploads/<?php echo htmlspecialchars($user_data['profilePhoto'] ?? ''); ?>'); background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <?php if(empty($user_data['profilePhoto'])): ?>
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
       <div class="dashboard-container">
            <div class="header-bar">
                <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
                <div class="profile-container">
                    <div class="profile-trigger" id="profileTrigger">
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($username, 0, 1)); ?> 
                        </div>
                    </div>

                    <div class="profile-menu" id="profileMenu">
                        <p><strong><?php echo htmlspecialchars($username); ?></strong></p>
                        <p class="rank-text">Rank: <?php echo htmlspecialchars($current_rank); ?></p>
                        <hr>
                        <a href="../logout.php" class="logout-btn">Logout</a>
                    </div>
                </div>
            </div>

            <!-- Expanded Dynamic Statistics Grid -->
            <div class="stats-grid">
                
                <!-- Main Currency & Rank -->
                <div class="stat-card">
                    <img src="../assets/capygrass.png" alt="capygrass" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Capygrass</h3>
                    <p><?php echo htmlspecialchars($capygrass); ?></p>
                    <div class="stat-subtext">Total Currency</div>
                </div>
                
                <div class="stat-card">
                    <img src="../assets/rank.png" alt="rank" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Current Rank</h3>
                    <p><?php echo htmlspecialchars($current_rank); ?></p>
                    <div class="stat-subtext">Keep Grinding!</div>
                </div>

                <!-- Productivity Stats -->
                <div class="stat-card">
                    <img src="../assets/pomodoro_icon.png" alt="focus" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Focus Time</h3>
                    <p><?php echo htmlspecialchars($total_study_hours); ?>h</p>
                    <div class="stat-subtext">Total recorded time</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/task.png" alt="tasks" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Tasks Done</h3>
                    <p><?php echo htmlspecialchars($total_tasks_completed); ?></p>
                    <div class="stat-subtext">Avg: <?php echo htmlspecialchars($avg_tasks_per_day); ?>/day</div>
                </div>

                <!-- Learning Stats (Adapted for Decks) -->
                <div class="stat-card">
                    <img src="../assets/quiz_icon.png" alt="decks" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Decks Built</h3>
                    <p><?php echo htmlspecialchars($total_decks_created); ?></p>
                    <div class="stat-subtext">Total study decks</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/flashcard_icon.png" alt="leitner" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Cards Mastered</h3>
                    <p><?php echo htmlspecialchars($leitner_cards_mastered); ?></p>
                    <div class="stat-subtext">In Box 5 (Long-term)</div>
                </div>

                <!-- Engagement Stats -->
                <div class="stat-card">
                    <img src="../assets/streak_icon.png" alt="streak" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Daily Streak</h3>
                    <p><?php echo htmlspecialchars($current_streak); ?></p>
                    <div class="stat-subtext">Best: <?php echo htmlspecialchars($longest_streak); ?> days</div>
                </div>
                
                <div class="stat-card">
                    <img src="../assets/notes.png" alt="notes" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Notes Created</h3>
                    <p><?php echo htmlspecialchars($total_notes); ?></p>
                    <div class="stat-subtext">Knowledge base</div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="user_home.js"></script>
<script>
(function() {
    // Profile Dropdown Logic
    document.addEventListener('DOMContentLoaded', function() {
        const trigger = document.getElementById('profileTrigger');
        const menu = document.getElementById('profileMenu');

        if (trigger && menu) {
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('active');
            });

            document.addEventListener('click', function() {
                menu.classList.remove('active');
            });

            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
})();

    // Toggles between viewing the profile info and the edit form
function toggleProfileEdit() {
    const viewMode = document.getElementById('profileViewMode');
    const editMode = document.getElementById('profileEditMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}


function toggleProfileEdit() {
        const viewMode = document.getElementById('profileViewMode');
        const editMode = document.getElementById('profileEditMode');
        const passMode = document.getElementById('passwordEditMode');
        
        if (editMode.style.display === 'none') {
            viewMode.style.display = 'none';
            passMode.style.display = 'none'; // Close password form if open
            editMode.style.display = 'block';
        } else {
            viewMode.style.display = 'block';
            editMode.style.display = 'none';
        }
    }

    // Toggle Change Password Form
    function togglePasswordEdit() {
        const viewMode = document.getElementById('profileViewMode');
        const editMode = document.getElementById('profileEditMode');
        const passMode = document.getElementById('passwordEditMode');
        
        if (passMode.style.display === 'none') {
            viewMode.style.display = 'none';
            editMode.style.display = 'none'; // Close profile edit form if open
            passMode.style.display = 'block';
        } else {
            viewMode.style.display = 'block';
            passMode.style.display = 'none';
        }
    }

    // Modern HTML5 password matching validation for the change password form
    const newPassword = document.getElementById('new-password-input');
    const confirmNewPassword = document.getElementById('confirm-new-password-input');

    function validateNewPasswordMatch() {
        if (newPassword.value !== confirmNewPassword.value) {
            confirmNewPassword.setCustomValidity("Passwords do not match!");
        } else {
            confirmNewPassword.setCustomValidity('');
        }
    }

    // Check for matching values dynamically as the user interacts with the fields
    if(newPassword && confirmNewPassword) {
        newPassword.addEventListener('change', validateNewPasswordMatch);
        confirmNewPassword.addEventListener('keyup', validateNewPasswordMatch);
    }
</script>

<script src="user_home.js"></script>

<!-- <script>
document.addEventListener("submit", function (e) {

    const form = e.target;

    if (!form.classList.contains("delete-form")) return;

    e.preventDefault();

    const type = form.dataset.type || "item";

    triggerModal({
        theme: "warning",
        title: `Delete ${type.charAt(0).toUpperCase() + type.slice(1)}`,
        message: `Are you sure you want to delete this ${type}?`,
        type: "confirm",
        buttonText: "Yes, Delete",
        onConfirm: () => form.submit()
    });

});
</script>

<script>
    document.addEventListener("submit", function (e) {

    const form = e.target;

    if (!form.classList.contains("update-task-form")) return;

    e.preventDefault();

    triggerModal({
        theme: "warning",
        title: "Update Task",
        message: "Are you sure you want to update this task?",
        type: "confirm",
        buttonText: "Yes, Update",
        onConfirm: () => form.submit()
    });

});
</script> -->

<?php include '../modal.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Listen for any click on the entire page
    document.body.addEventListener("click", function(e) {
        
        // 2. Check if what was clicked is a Delete Button inside one of your forms
        // This looks for buttons inside forms with class 'delete-form'
        const btn = e.target.closest(".delete-form button");
        
        if (btn) {
            // 3. Stop the form from submitting immediately
            e.preventDefault();
            
            // 4. Find the specific form this button belongs to
            const form = btn.closest("form");
            const type = form.getAttribute("data-type") || "item";

            // 5. Trigger your modal
            triggerModal({
                type: 'confirm',
                theme: 'warning',
                title: `Delete ${type.charAt(0).toUpperCase() + type.slice(1)}?`,
                message: `Are you sure you want to delete this ${type}? This cannot be undone.`,
                icon: '../assets/logo.png'
                buttonText: 'Yes, Delete',
                onConfirm: function() {
                    // 6. If they confirm, manually submit THIS specific form
                    form.submit();
                }
            });
        }
    });
});
</script>

</body>
</html>