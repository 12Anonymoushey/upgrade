<?php
require_once(__DIR__ . '/modal.php');
require_once '../db_manager.php';
require_once '../admin/admin_processes/Rank_checker.php';
session_start();

// Security: Make sure we know exactly who is making these requests
if (!isset($_SESSION['user_id']) && !isset($_POST['user_id'])) {
    exit("Unauthorized");
}
// Prioritize session user_id for security, fallback to POST if needed for the reward form
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_POST['user_id'];

$db = new DBManager();
$read_conn = $db->getReadConn();
$write_conn = $db->getWriteConn();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ==========================================
    // MODULE 1: COMMUNITY QUIZZES & REWARDS
    // ==========================================

    // 1. Fetch Flashcards for JavaScript
    if ($action == 'fetch_cards') {
        $deck_id = $_POST['deck_id'];
        $query = "SELECT front_text, back_text FROM FLASHCARDS WHERE deck_id = ?";
        $stmt = $read_conn->prepare($query);
        $stmt->bind_param("i", $deck_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode($result);
        exit();
    }

    // 2. Reward User for Completing the Quiz
    if ($action == 'reward') {
        $write_conn->begin_transaction();
        try {
            // Reward Capygrass
            $query = "UPDATE CAPYGRASSWALLET SET totalCapygrass = totalCapygrass + 20 WHERE user_id = ?";
            $wallet_stmt = $write_conn->prepare($query);
            $wallet_stmt->bind_param("i", $user_id);
            $wallet_stmt->execute();
            $write_conn->commit();

            // Check if they earned enough to rank up (and consequently unlock a new feature)
            $capy_query = "SELECT totalCapygrass FROM CAPYGRASSWALLET WHERE user_id = ?";
            $capy_stmt = $read_conn->prepare($capy_query);
            $capy_stmt->bind_param("i", $user_id);
            $capy_stmt->execute();
            $new_capygrass = $capy_stmt->get_result()->fetch_assoc()['totalCapygrass'];

            $rank = new Rank_checker($user_id);
            $rank->check_rank($new_capygrass);

           echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Deck completed!',
                            message: 'You earned 20 Capygrass.',
                            icon: 'assets/logo.png',
                            redirect: '../user/user_home.php'
                        });
                    };
                </script>";
            
        } catch(Exception $e) {
            $write_conn->rollback();
            echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='../user/user_home.php';</script>";
        }
        exit();
    }

    // ==========================================
    // MODULE 2: "MY QUIZZES" MANAGEMENT (CRUD)
    // ==========================================

    // 3. Fetch User's Decks
    if ($action == 'get_my_decks') {
        $query = "SELECT * FROM DECK WHERE user_id = ? ORDER BY createdAt DESC";
        $stmt = $read_conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($result);
        exit();
    }

    // 4. Create a New Deck
    if ($action == 'create_deck') {
        $title = $_POST['title'];
        $query = "INSERT INTO DECK (user_id, title) VALUES (?, ?)";
        $stmt = $write_conn->prepare($query);
        $stmt->bind_param("is", $user_id, $title);
        $stmt->execute();
        echo "Success";
        exit();
    }

    // 5. Delete a Deck
    if ($action == 'delete_deck') {
        $deck_id = $_POST['deck_id'];
        // Ensure they only delete THEIR OWN deck
        $query = "DELETE FROM DECK WHERE deck_id = ? AND user_id = ?";
        $stmt = $write_conn->prepare($query);
        $stmt->bind_param("ii", $deck_id, $user_id);
        $stmt->execute();
        echo "Success";
        exit();
    }

    // 6. Fetch Cards for a specific Deck
    if ($action == 'get_my_cards') {
        $deck_id = $_POST['deck_id'];
        
        // Verify ownership first so people can't edit decks that aren't theirs
        $auth_query = "SELECT user_id FROM DECK WHERE deck_id = ?";
        $auth_stmt = $read_conn->prepare($auth_query);
        $auth_stmt->bind_param("i", $deck_id);
        $auth_stmt->execute();
        $deck_owner = $auth_stmt->get_result()->fetch_assoc()['user_id'];
        
        if ($deck_owner != $user_id) exit("Unauthorized");

        $query = "SELECT * FROM FLASHCARDS WHERE deck_id = ?";
        $stmt = $read_conn->prepare($query);
        $stmt->bind_param("i", $deck_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($result);
        exit();
    }

    // 7. Add a Card to a Deck
    if ($action == 'add_card') {
        $deck_id = $_POST['deck_id'];
        $front = $_POST['front'];
        $back = $_POST['back']; // Fixed completion
        
        $query = "INSERT INTO FLASHCARDS (deck_id, front_text, back_text) VALUES (?, ?, ?)";
        $stmt = $write_conn->prepare($query);
        $stmt->bind_param("iss", $deck_id, $front, $back);
        if ($stmt->execute()) {
            echo "Success";
        } else {
            echo "Error";
        }
        exit();
    }

    // 8. Delete a Card
    if ($action == 'delete_card') {
        $card_id = $_POST['card_id'];
        
        // NOTE: Ensure your primary key column is named 'card_id'. If it is 'id', change it below.
        $query = "DELETE FROM FLASHCARDS WHERE card_id = ?"; 
        $stmt = $write_conn->prepare($query);
        $stmt->bind_param("i", $card_id);
        if ($stmt->execute()) {
            echo "Success";
        } else {
            echo "Error";
        }
        exit();
    }

    // 9. Edit a Card
    if ($action == 'edit_card') {
        $card_id = $_POST['card_id'];
        $front = $_POST['front'];
        $back = $_POST['back'];
        
        // NOTE: Ensure your primary key column is named 'card_id'. If it is 'id', change it below.
        $query = "UPDATE FLASHCARDS SET front_text = ?, back_text = ? WHERE card_id = ?";
        $stmt = $write_conn->prepare($query);
        $stmt->bind_param("ssi", $front, $back, $card_id);
        if ($stmt->execute()) {
            echo "Success";
        } else {
            echo "Error";
        }
        exit();
    }
}
?>