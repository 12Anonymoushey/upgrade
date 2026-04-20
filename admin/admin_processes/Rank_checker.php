<?php

require_once '../db_manager.php';

class Rank_checker{
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

    public function check_rank(int $capygrass) :  ?Exception
    {
        $query = "SELECT * FROM USERRANKS u
                    JOIN RANKS r ON r.rank_id = u.rank_id
                        WHERE user_id = ?
                        ORDER BY assignedAt desc
                        limit 1";
        $rank_stmt = $this->read_conn->prepare($query);
        $rank_stmt->bind_param("i", $this->user_id);
        $rank_stmt->execute();
        $rank_results = $rank_stmt->get_result();
        if($rank_results->num_rows > 0)
        {
            $row = $rank_results->fetch_assoc();
            $rank_id = $row['rank_id'];
            $user_id = $row['user_id'];
            if($capygrass < $row['minCapygrass'])
            {
                $this->write_conn->begin_transaction();
                try{
                    $new_rank_id = $rank_id == 1 ? 1 : $rank_id - 1;
                    $query = "INSERT INTO USERRANKS(user_id, rank_id)
                            VALUES(?, ?)";
                    $user_rank_stmt = $this->write_conn->prepare($query);
                    $user_rank_stmt->bind_param("ii", $user_id, $new_rank_id);
                    $user_rank_stmt->execute();
                    $this->write_conn->commit();
                    echo "<script>alert('you have ranked down')</script>";
                } catch(Exception $e)
                {
                    echo "<script>alert($e->getMessage())</script>";
                    $this->write_conn->rollback();
                }
            }
            elseif($capygrass > $row['maxCapygrass'])
            {
                 $new_rank_id = $rank_id == 6 ? 6 : $rank_id + 1;
                $this->write_conn->begin_transaction();
                try{
                    $query = "INSERT INTO USERRANKS(user_id, rank_id)
                            VALUES(?, ?)";
                    $user_feat_stmt = $this->write_conn->prepare($query);
                    $user_feat_stmt->bind_param("ii", $user_id, $new_rank_id);
                    $user_feat_stmt->execute();
                    $this->write_conn->commit();
                    echo "<script>alert('you have ranked up')</script>";
                } catch(Exception $e)
                {
                    echo "<script>alert($e->getMessage())</script>";
                    $this->write_conn->rollback();
                }
            }
            echo "<script>alert('There are no changes in rank')</script>";
        }
        return null;
    }
}

?>