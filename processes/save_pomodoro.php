<?php
session_start();
require_once '../db_manager.php';
require_once '../admin/admin_processes/Feature_checker.php';
require_once '../admin/admin_processes/Rank_checker.php';

// Tell the browser to expect JSON back
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Grab data from the frontend fetch request
    $user_id = $data['user_id'] ?? (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
    $durationMinutes = isset($data['durationMinutes']) ? (int)$data['durationMinutes'] : 0;
    $startTime = $data['startTime'] ?? null;
    $endTime = $data['endTime'] ?? null;
    
    if (!$user_id || $durationMinutes <= 0 || !$startTime || !$endTime) {
        echo json_encode(["success" => false, "message" => "Invalid session data."]);
        exit();
    }

    $db = new DBManager();
    $write_conn = $db->getWriteConn();
    
    $write_conn->begin_transaction();
    try {
        // 1. Fetch Pomodoro technique details from DB
        $tech_query = "SELECT study_tech_id, basePoints FROM studytechniques WHERE techniqueName = 'pomodoro' LIMIT 1";
        $tech_result = $write_conn->query($tech_query);
        $tech_row = $tech_result->fetch_assoc();
        
        $study_tech_id = $tech_row['study_tech_id'] ?? 1;
        $basePoints = $tech_row['basePoints'] ?? 2; // Default to 2 if missing

        // 2. Calculate Points Earned
        // Gives basePoints (2) for every 25 mins completed. 
        // If they did at least 5 mins but less than 25, give 1 point. Less than 5 mins = 0 points.
        $pointsEarned = floor($durationMinutes / 25) * $basePoints;
        if ($pointsEarned < 1 && $durationMinutes >= 5) {
             $pointsEarned = 1; 
        } elseif ($durationMinutes < 5) {
             $pointsEarned = 0; 
        }

        // 3. Insert the record into `studysessions`
        $insert_session = "INSERT INTO studysessions (user_id, study_tech_id, startTime, endTime, durationMinutes, pointsEarned) 
                           VALUES (?, ?, ?, ?, ?, ?)";
        $session_stmt = $write_conn->prepare($insert_session);
        $session_stmt->bind_param("iissii", $user_id, $study_tech_id, $startTime, $endTime, $durationMinutes, $pointsEarned);
        $session_stmt->execute();

        // 4. Reward the Capygrass Wallet (if they earned points)
        if ($pointsEarned > 0) {
            $wallet_query = "UPDATE capygrasswallet SET totalCapygrass = totalCapygrass + ? WHERE user_id = ?";
            $wallet_stmt = $write_conn->prepare($wallet_query);
            $wallet_stmt->bind_param("ii", $pointsEarned, $user_id);
            $wallet_stmt->execute();
        }

        // 5. Fetch new total to check if they rank up
        $query_total = "SELECT totalCapygrass FROM capygrasswallet WHERE user_id = ?";
        $total_stmt = $write_conn->prepare($query_total);
        $total_stmt->bind_param("i", $user_id);
        $total_stmt->execute();
        $total_row = $total_stmt->get_result()->fetch_assoc();
        $new_total = $total_row['totalCapygrass'] ?? 0;

        // 6. Check for Rank / Feature upgrades
        $feature = new Feature_checker($user_id);
        $rank = new Rank_checker($user_id);
        $e_rank = $rank->check_rank($new_total);
        $e_feature = $feature->check_features($new_total);

        $rank_message = (!empty($e_rank)) ? $e_rank->getMessage() : "";
        $feature_message = (!empty($e_feature)) ? $e_feature->getMessage() : "";

        $write_conn->commit();
        
        // Return response
        echo json_encode([
            "success" => true, 
            "message" => "Session logged! You focused for $durationMinutes mins and earned $pointsEarned Capygrass.",
            "rank_updates" => trim("$rank_message $feature_message")
        ]);
        
    } catch(Exception $e) {
        $write_conn->rollback();
        echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>