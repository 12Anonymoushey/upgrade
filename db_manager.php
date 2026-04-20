<?php

class DBManager{
private $slave_conn;
private $master_conn;
private $db = "UpGrade";

public function __construct()
{
    $this->master_conn = new mysqli("localhost", "root", "", $this->db, 3306);
    $this->slave_conn = new mysqli("localhost", "root", "", $this->db, 3307);

    if($this->slave_conn->connect_error)
    {
        die("Error on Here: " . $this->slave_conn->connect_error);
    }
    if($this->master_conn->connect_error)
    {
        die("Error on Here: " . $this->master_conn->connect_error);
    }
}

public function getReadConn()
{
    return $this->slave_conn;
}

public function getWriteConn()
{
    return $this->master_conn;
}

};

?>