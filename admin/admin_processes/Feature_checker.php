<?php
require_once(__DIR__ . '/../../modal.php');
require_once '../db_manager.php';

class Feature_checker{
    private $db;
    private $write_conn;
    private $read_conn;
    private $user_id;

    public function __construct(int $user_id)
    {
        $this->db = new DBManager();
        $this->write_conn = $this->db->getWriteConn();
        $this->read_conn = $this->db->getReadConn();
        $this->user_id = $user_id;
    }

    public function check_features(int $capygrass) : ?Exception
    {
        $query = "SELECT * FROM USERFEATURES u
                    JOIN FEATURES f ON f.feature_id = u.feature_id
                        WHERE user_id = ?
                        ORDER BY unlockedAt desc
                        limit 1";
        $feat_stmt = $this->read_conn->prepare($query);
        $feat_stmt->bind_param("i", $this->user_id);
        $feat_stmt->execute();
        $feat_results = $feat_stmt->get_result();
        if($feat_results->num_rows > 0)
        {
            $row = $feat_results->fetch_assoc();
            $feature_id = $row['feature_id'];
            $user_id = $row['user_id'];
            if($capygrass < $row['minPoints'])
            {
                $this->write_conn->begin_transaction();
                try{
                    $query = "INSERT INTO USERFEATURES(user_id, feature_id)
                            VALUES(?, ?)";
                    $user_feat_stmt = $this->write_conn->prepare($query);
                    $user_feat_stmt->bind_param("ii", $user_id, $feature_id == 1? 
                                                    $feature_id : $feature_id - 1);
                    $user_feat_stmt->execute();
                    $this->write_conn->commit();
                    // ang mga amo ni para ni sa alert. i change lang 
                    echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'warning',
                            title: 'Notification',
                            message: 'You have downgraded accessible features',
                            icon: 'assets/logo.png',
                            redirect: 'index.php'
                        });
                    };
                </script>";
                } catch(Exception $e)
                {
                    echo "<script>alert('{$e->getMessage()}')</script>";
                    $this->write_conn->rollback();
                    return $e;
                }
            }
            elseif($capygrass > $row['maxPoints'])
            {
                $new_feat_id = $feature_id == 6? 6 : $feature_id + 1;
                $this->write_conn->begin_transaction();
                try{
                    $query = "INSERT INTO USERFEATURES(user_id, feature_id)
                            VALUES(?, ?)";
                    $user_feat_stmt = $this->write_conn->prepare($query);
                    $user_feat_stmt->bind_param("ii", $user_id, $new_feat_id);
                    $user_feat_stmt->execute();
                    $this->write_conn->commit();
                    echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'success',
                            title: 'Congrats!',
                            message: 'You have upgraded accessible features',
                            icon: 'assets/logo.png',
                        });
                    };
                </script>";
                    
                } catch(Exception $e)
                {
                    echo "<script>alert('{$e->getMessage()}')</script>";
                    $this->write_conn->rollback();
                    return $e;
                }
            }
            else {
                echo "<script>
                    window.onload = function() {
                        triggerModal({
                            theme: 'notification',
                            title: 'Notification',
                            message: 'There are no changes in accessible features',
                            icon: 'assets/logo.png',
                        });
                    };
                </script>";
            }
        }
        return null;
    }
}

?>