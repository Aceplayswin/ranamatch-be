<?php

class AgentReports {
    private $conn;
    private $const_user_id = "";
    private $const_page_num = "";
    private $content = 30;
    
    private $resArr = [
        "data" => [],
        "status_code" => "failed"
    ];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function __destruct() {
        $this->conn->close();
    }
    
    private function returnRequest() {
        echo json_encode($this->resArr);
        exit();
    }
    
    private function getParams() {
        $json = file_get_contents('php://input');
        $data = json_decode($json);

        if (isset($_GET['USER_ID']) && isset($_GET['PAGE_NUM'])) {
            $this->const_user_id = mysqli_real_escape_string($this->conn, $_GET['USER_ID']);
            $this->const_page_num = mysqli_real_escape_string($this->conn, $_GET['PAGE_NUM']);
        } else {
            $this->resArr['status_code'] = "invalid_params";
            $this->returnRequest();
        }
    }
    
    
    private function fetchLevelData($parent_id, $order) {
        $offset = ($this->const_page_num-1)*$this->content;
        
        if($order=="DESC"){
            $select_sql = "SELECT * FROM tblusersdata WHERE tbl_joined_under='{$parent_id}' ORDER BY id DESC LIMIT {$offset},{$this->content} ";
        }else{
            $select_sql = "SELECT * FROM tblusersdata WHERE tbl_joined_under='{$parent_id}' ORDER BY id $order ";
        }
        
        $result = $this->conn->query($select_sql);

        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
    
    private function processLevelData($row, $level) {
        $account_type = $row['tbl_account_level'] >= 2 ? "Vip" : "Normal";
        
        $index = [
            'm_account_level' => $level,
            'm_mobile' => "******" . substr($row['tbl_mobile_num'], 6),
            'm_id' => $row['tbl_uniq_id'],
            'm_account_type' => $account_type,
            'm_status' => $row['tbl_account_status'],
            'm_joined' => $row['tbl_user_joined']
        ];
        array_push($this->resArr['data'], $index);
    }
    
    private function processRequest(){
        if($this->const_user_id!="" && $this->const_page_num!=""){
            // level: 1
            $level1_users = $this->fetchLevelData($this->const_user_id, 'DESC');
            
            foreach ($level1_users as $level1Row) {
                $this->processLevelData($level1Row, 1);
                $level1_id = $level1Row['tbl_uniq_id'];
                
                
                // level 2
                $level2_users = $this->fetchLevelData($level1_id, 'ASC');
                
                foreach ($level2_users as $level2Row) {
                  $this->processLevelData($level2Row, 2);
                  $level2_id = $level2Row['tbl_uniq_id'];
                  
                  
                  // level 3
                  $level3_users = $this->fetchLevelData($level2_id, 'ASC');
                  foreach ($level3_users as $level3Row) {
                    $this->processLevelData($level3Row, 2);
                    $level3_id = $level3Row['tbl_uniq_id'];
                  }
                }
            }
            
            $this->resArr["status_code"] = "success";
        }else{
            $this->resArr["status_code"] = "invalid_params";
        }
        
        $this->returnRequest();
    }
    
    public function process() {
        $this->getParams();
        $this->processRequest();
    }
}

// Initializing database connection
if ($conn->connect_error) {
    die("db_conn_error");
}


// initializing new object & calling function
$agentReportsObj = new AgentReports($conn);
$agentReportsObj->process();
?>