<?php
session_start();
require '../db_manager.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    
    header("Location: ../index.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

$db = new DBManager();
$read_conn = $db->getReadConn();

$users_query = "SELECT u.user_id, u.username, u.email, u.createdAt, COALESCE(c.totalCapygrass, 0) AS Capygrass 
                FROM USERS u
                LEFT JOIN CAPYGRASSWALLET c ON u.user_id = c.user_id
                WHERE u.isAdmin = 0 
                ORDER BY c.totalCapygrass DESC";

$users_result = $read_conn->query($users_query);

$query = "SELECT * FROM USERS
            WHERE user_id = ?";
$user_stmt = $read_conn->prepare($query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_row = $user_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UpGrade Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_home.css">
</head>
<body>

<div class="admin-container">
    <div class="header-bar">
        <h2>Admin Control Panel</h2>
        <div>
            <span style="margin-right: 15px;">Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    <hr style="margin: 20px 0; border: 1px solid #eee;">

    <h3>User Leaderboard and Management</h3>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Capygrass</th>
                <th>Joined Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($users_result->num_rows > 0) {
                while($row = $users_result->fetch_assoc()) {
                    $date = date("M j, Y", strtotime($row['createdAt']));
                    echo "<tr>";
                    echo "<td>" . $row['user_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td><strong>" . $row['Capygrass'] . "</strong></td>";
                    echo "<td>" . $date . "</td>";
                    echo "<td>
                        <form action='userProfile.php' method='POST'>
                            <input type='hidden' name='user_id' name='user_id' value={$row['user_id']}>
                            <button type='submit' name='userProfile' class='action-btn'>View Activity</button>
                        </form>
                    </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='text-align:center;'>No regular users found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
    <div>
        <button onclick="openPanel('update_panel')">Update</button>
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
</div>
<script src="admin_side.js"></script>
</body>
</html>