<?php
session_start();
require '../db_manager.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../index.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

$db = new DBManager();
$read_conn = $db->getReadConn();

// Fetch users for leaderboard (excluding admins)
$users_query = "SELECT u.user_id, u.username, u.email, u.createdAt, u.profilePhoto, u.profile_banner, COALESCE(c.totalCapygrass, 0) AS Capygrass 
                FROM USERS u
                LEFT JOIN CAPYGRASSWALLET c ON u.user_id = c.user_id
                WHERE u.isAdmin = 0 
                ORDER BY c.totalCapygrass DESC";

$users_result = $read_conn->query($users_query);

// Fetch current admin's details for the profile view and update form
$query = "SELECT * FROM USERS WHERE user_id = ?";
$user_stmt = $read_conn->prepare($query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_row = $user_result->fetch_assoc();

// ==========================================
// GLOBAL PLATFORM STATISTICS QUERIES
// ==========================================

// 1. Total Active Users (Excluding Admins)
$res = $read_conn->query("SELECT COUNT(user_id) AS total FROM USERS WHERE isAdmin = 0");
$global_total_users = $res->fetch_assoc()['total'] ?? 0;

// 2. Global Capygrass Economy (Total currency in circulation)
$res = $read_conn->query("SELECT SUM(totalCapygrass) AS total FROM CAPYGRASSWALLET");
$global_capygrass = $res->fetch_assoc()['total'] ?? 0;

// 3. Total Focus Time Globally (Converted from minutes to hours)
$res = $read_conn->query("SELECT SUM(durationMinutes) AS total FROM studysessions");
$global_study_mins = $res->fetch_assoc()['total'] ?? 0;
$global_study_hours = round($global_study_mins / 60, 1);

// 4. Total Tasks Completed Globally
$res = $read_conn->query("SELECT COUNT(task_id) AS total FROM tasks WHERE isFinished = 1");
$global_tasks_done = $res->fetch_assoc()['total'] ?? 0;

// 5. Total Notes Created
$res = $read_conn->query("SELECT COUNT(note_id) AS total FROM notes");
$global_notes = $res->fetch_assoc()['total'] ?? 0;

// 6. Total Flashcards Created
$res = $read_conn->query("SELECT COUNT(card_id) AS total FROM flashcards");
$global_flashcards = $res->fetch_assoc()['total'] ?? 0;

// 7. Total Files Uploaded to the Platform
$res = $read_conn->query("SELECT COUNT(file_id) AS total FROM userfiles");
$global_files = $res->fetch_assoc()['total'] ?? 0;

// 8. Total Features Unlocked/Purchased
$res = $read_conn->query("SELECT COUNT(user_feature_id) AS total FROM userfeatures");
$global_features_unlocked = $res->fetch_assoc()['total'] ?? 0;

// ==========================================
// AUDIT LOGS QUERY
// ==========================================
$logs_query = "SELECT al.activity, al.createdAt, u.username AS actor 
               FROM adminlogs al
               LEFT JOIN admins a ON al.admin_id = a.admin_id
               LEFT JOIN users u ON a.user_id = u.user_id
               ORDER BY al.createdAt DESC LIMIT 100";
$logs_result = $read_conn->query($logs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UpGrade Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Ensures the body takes up full screen and removes gaps */
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
            align-items: stretch;
        }

        .left-container, .right-container {
            height: auto;
            min-height: 100vh;
        }

        /* Modal styling for the profile panel to center it on screen */
        .modal-panel {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1001;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 500px;
            max-height: 90vh; /* Prevents it from being taller than the screen */
            overflow-y: auto; /* Allows scrolling inside the modal if it's too tall */
            overflow-x: hidden;
            display: none;
            padding: 0; 
        }

        .modal-panel.show {
            display: block;
        }

        #overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }

        /* Styling for the new profile menu button */
        .menu-action-btn {
            display: block;
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px 15px;
            font-size: 1rem;
            color: #333;
            transition: background 0.2s;
        }

        .menu-action-btn:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="left-container">
        <div class="left-btn">
           <button class="btn-toggle" id="btnAuditLogs" onclick="openModal('auditLogsPanel')">
                <img src="../assets/auditlog.png" alt="auditlog">
                <span class="btn-label">Audit Logs</span>
            </button>
            <button class="btn-toggle" id="btnFeatures" onclick="openModal('profilePanel')">
                <img src="../assets/profile.png" alt="profile">
                <span class="btn-label">Profile</span>
            </button>
        </div>
    </div>

    <div class="right-container">
        <div class="admin-container">
            <div class="header-bar">
                <h2>Admin Control Panel</h2>
                <div>
                    <span style="margin-right: 15px;">Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong></span>
                </div>
                
                <div class="profile-container">
                    <div class="profile-trigger" id="profileTrigger">
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                    </div>

                    <div class="profile-menu" id="profileMenu">
                        <p><strong><?php echo htmlspecialchars($username); ?></strong></p>
                        <p class="position-text">Admin</p>
                        <hr>
                        <button onclick="openModal('profilePanel')" class="menu-action-btn">Profile Settings</button>
                        <a href="../logout.php" class="logout-btn" style="margin-top: 10px;">Logout</a>
                    </div>
                </div>
            </div>
            
            <hr style="margin: 20px 0; border: 1px solid #eee;">

            <h3>User Leaderboard</h3>
            
            <table style="border-collapse: separate; border-spacing: 0 10px; width: 100%;">
                <thead>
                    <tr style="text-align: left; background: #f8f9fa;">
                        <th style="padding: 10px;">Rank</th>
                        <th style="padding: 10px;">Profile</th>
                        <th style="padding: 10px;">Username</th>
                        <th style="padding: 10px;">Email</th>
                        <th style="padding: 10px;">Capygrass</th>
                        <th style="padding: 10px;">Joined Date</th>
                        <th style="padding: 10px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($users_result->num_rows > 0) {
                        $rank = 1; 
                        
                        while($row = $users_result->fetch_assoc()) {
                            $date = date("M j, Y", strtotime($row['createdAt']));
                            $banner = !empty($row['profile_banner']) ? $row['profile_banner'] : 'default_banner.jpg';
                            
                            echo "<tr style='background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05);'>";
                            echo "<td style='padding: 15px; font-weight: bold; font-size: 1.2rem; color: var(--primary); text-align: center;'>" . $rank . "</td>";
                            
                            // Profile Visual
                            echo "<td style='padding: 15px; width: 140px;'>
                                    <div style='position: relative; width: 120px; height: 50px; background-image: url(\"../uploads/" . htmlspecialchars($banner) . "\"); background-color: #ddd; background-size: cover; background-position: center; border-radius: 4px; border: 1px solid #ccc;'>
                                        <div style='position: absolute; bottom: -10px; left: 10px; width: 35px; height: 35px; border-radius: 50%; background-color: #fff; border: 2px solid var(--primary); background-image: url(\"../uploads/" . htmlspecialchars($row['profilePhoto'] ?? '') . "\"); background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: bold; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.2);'>";
                                        if(empty($row['profilePhoto'])) {
                                            echo strtoupper(substr($row['username'], 0, 1));
                                        }
                            echo "      </div>
                                    </div>
                                  </td>";
                            
                            echo "<td style='padding: 15px;'>" . htmlspecialchars($row['username']) . "<br><small style='color: gray;'>ID: " . $row['user_id'] . "</small></td>";
                            echo "<td style='padding: 15px;'>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td style='padding: 15px; color: #2ecc71;'><strong>" . $row['Capygrass'] . "</strong></td>";
                            echo "<td style='padding: 15px;'>" . $date . "</td>";
                            echo "<td style='padding: 15px;'>
                                <form action='userProfile.php' method='POST'>
                                    <input type='hidden' name='user_id' value='" . $row['user_id'] . "'>
                                    <button type='submit' name='userProfile' class='action-btn' style='width: auto; font-size: 0.9rem;'>View Activity</button>
                                </form>
                            </td>";
                            echo "</tr>";
                            
                            $rank++; 
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center; padding: 20px;'>No regular users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <hr style="margin: 40px 0 20px; border: 1px solid #eee;">
            <h3>Global Platform Statistics</h3>
            
            <div class="stats-grid">
                
                <div class="stat-card">
                    <img src="../assets/rank.png" alt="users" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Total Users</h3>
                    <p><?php echo number_format($global_total_users); ?></p>
                    <div class="stat-subtext">Registered Students</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/capygrass.png" alt="economy" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Economy</h3>
                    <p><?php echo number_format($global_capygrass); ?></p>
                    <div class="stat-subtext">Total Capygrass Mined</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/pomodoro_icon.png" alt="focus" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Global Focus Time</h3>
                    <p><?php echo number_format($global_study_hours); ?>h</p>
                    <div class="stat-subtext">Total hours studied</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/task.png" alt="tasks" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Tasks Finished</h3>
                    <p><?php echo number_format($global_tasks_done); ?></p>
                    <div class="stat-subtext">Across all users</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/notes.png" alt="notes" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Notes Written</h3>
                    <p><?php echo number_format($global_notes); ?></p>
                    <div class="stat-subtext">Knowledge base size</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/flashcard_icon.png" alt="flashcards" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Flashcards Created</h3>
                    <p><?php echo number_format($global_flashcards); ?></p>
                    <div class="stat-subtext">For active recall</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/features.png" alt="files" class="stat-icon" onerror="this.style.display='none'">
                    <h3>Files Uploaded</h3>
                    <p><?php echo number_format($global_files); ?></p>
                    <div class="stat-subtext">Total attachments</div>
                </div>

                <div class="stat-card">
                    <img src="../assets/quiz_icon.png" alt="features" class="stat-icon" onerror="this.style.display='none'">
                    <h3>UpGrades Purchased</h3>
                    <p><?php echo number_format($global_features_unlocked); ?></p>
                    <div class="stat-subtext">Items bought from shop</div>
                </div>

            </div>
        </div>
    </div>
</div>
<div class="modal-panel" id="auditLogsPanel" style="max-width: 700px;">
    <button type="button" onclick="closeModal('auditLogsPanel')" style="position: absolute; top: 15px; right: 20px; background: #eee; border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">&times;</button>
    
    <div style="padding: 25px 30px; background: #2c3e50; color: white; border-radius: 12px 12px 0 0;">
        <h2 style="margin: 0; font-size: 1.5rem;"><i class="fas fa-clipboard-list" style="margin-right: 10px;"></i> System Audit Logs</h2>
        <p style="margin: 5px 0 0 0; color: #bdc3c7; font-size: 0.9rem;">Real-time tracking of platform activity and administrative actions.</p>
    </div>

    <div style="padding: 20px 30px;">
        <div style="max-height: 450px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #f8f9fa; z-index: 1;">
                    <tr>
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd; font-size: 0.9rem; color: #555;">Timestamp</th>
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd; font-size: 0.9rem; color: #555;">Triggered By</th>
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd; font-size: 0.9rem; color: #555;">Activity Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result->num_rows > 0): ?>
                        <?php while($log = $logs_result->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px 15px; font-size: 0.85rem; color: gray; white-space: nowrap;">
                                    <?php echo date("M j, g:i A", strtotime($log['createdAt'])); ?>
                                </td>
                                <td style="padding: 12px 15px; font-size: 0.9rem; font-weight: bold; color: #3498db;">
                                    <?php echo htmlspecialchars($log['actor'] ?? 'System'); ?>
                                </td>
                                <td style="padding: 12px 15px; font-size: 0.9rem; color: #333;">
                                    <?php echo htmlspecialchars($log['activity']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding: 30px; text-align: center; color: gray;">No system logs recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="overlay" onclick="closeModal('profilePanel')"></div>

<div class="modal-panel" id="profilePanel">
    <button type="button" onclick="closeModal('profilePanel')" style="position: absolute; top: 10px; right: 15px; background: rgba(255,255,255,0.7); border: none; font-size: 1.5rem; cursor: pointer; color: #333; z-index: 10; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
    
    <div class="profile-header" style="position: relative; margin-bottom: 50px;">
        <div style="width: 100%; height: 150px; background-image: url('../uploads/<?php echo htmlspecialchars($user_row['profile_banner'] ?? 'default_banner.jpg'); ?>'); background-color: #ddd; background-size: cover; background-position: center;"></div>
        
        <div style="position: absolute; bottom: -40px; left: 30px; width: 90px; height: 90px; border-radius: 50%; background-color: #fff; border: 4px solid #3498db; background-image: url('../uploads/<?php echo htmlspecialchars($user_row['profilePhoto'] ?? ''); ?>'); background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <?php if(empty($user_row['profilePhoto'])): ?>
                <?php echo strtoupper(substr($user_row['username'], 0, 1)); ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="adminProfileView" style="padding: 20px 30px 30px; text-align: center;">
        <h2 style="margin-top: 0; margin-bottom: 5px;"><?php echo htmlspecialchars($user_row['fName'] . ' ' . $user_row['lName']); ?></h2>
        <p style="color: gray; margin-top: 0; margin-bottom: 20px;">@<?php echo htmlspecialchars($user_row['username']); ?></p>
        
        <div style="line-height: 1.8; text-align: left; background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
            <p style="margin: 5px 0;"><strong>Email Address:</strong> <?php echo htmlspecialchars($user_row['email']); ?></p>
            <p style="margin: 5px 0;"><strong>Account Role:</strong> Administrator</p>
            <p style="margin: 5px 0;"><strong>Joined Since:</strong> <?php echo date("F j, Y", strtotime($user_row['createdAt'])); ?></p>
        </div>
        
        <button onclick="toggleAdminEdit()" class="action-btn" style="margin-top: 25px; width: 100%; font-size: 1.1rem; padding: 12px; background-color: #3498db; color: #fff; border: none; border-radius: 5px; cursor: pointer;">Update Profile Details</button>
    </div>

    <div id="adminProfileEdit" style="display: none; padding: 20px 30px 30px;">
        <h3 style="margin-top: 0;">Update Admin Details</h3>
        <form action="update_user.php" method="POST">
            <div class="input-group">
                <label>First Name</label>
                <input type="text" name="fname" value="<?php echo htmlspecialchars($user_row['fName']); ?>" required style="width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;">
            </div>
            <div class="input-group" style="margin-top: 10px;">
                <label>Middle Name (Optional)</label>
                <input type="text" name="mname" value="<?php echo htmlspecialchars($user_row['mName'] ?? ''); ?>" style="width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;">
            </div>
            <div class="input-group" style="margin-top: 10px;">
                <label>Last Name</label>
                <input type="text" name="lname" value="<?php echo htmlspecialchars($user_row['lName']); ?>" required style="width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;">
            </div>
            <div class="input-group" style="margin-top: 10px;">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user_row['username']); ?>" required style="width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;">
            </div>
            <div class="input-group" style="margin-top: 10px;">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user_row['email']); ?>" required style="width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;">
            </div>
            <div class="input-group" style="margin-top: 10px;">
                <label>Password</label>
                <input type="password" name="password" value="<?php echo htmlspecialchars($user_row['password']); ?>" required style="width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box;">
            </div>
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="action-btn" type="submit" name="confirm_update" style="background-color: #2ecc71; color: #fff; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; flex: 1;">Save Changes</button>
                <button class="btn-toggle" type="button" onclick="toggleAdminEdit()" style="padding: 10px 15px; width: auto; cursor: pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script src="admin_side.js"></script>
<script>
    // Toggle between View and Edit mode inside the Admin Profile Modal
    function toggleAdminEdit() {
        const viewMode = document.getElementById('adminProfileView');
        const editMode = document.getElementById('adminProfileEdit');
        
        if (viewMode.style.display === 'none') {
            viewMode.style.display = 'block';
            editMode.style.display = 'none';
        } else {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
        }
    }

    // Modal Logic
    function openModal(id) {
        document.getElementById('profileMenu').classList.remove('active');
        document.getElementById('overlay').style.display = 'block';
        let panel = document.getElementById(id);
        if (panel) {
            panel.classList.add('show');
        }
    }

    function closeModal(id) {
        document.getElementById('overlay').style.display = 'none';
        let panel = document.getElementById(id);
        if (panel) {
            panel.classList.remove('show');
            // Reset modal back to view mode upon closing
            document.getElementById('adminProfileView').style.display = 'block';
            document.getElementById('adminProfileEdit').style.display = 'none';
        }
    }

    // Dropdown script
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
</script>
</body>
</html>