<?php
class AgentReports {
    private $conn;
    private $const_user_id = "";
    private $const_page_num = "";
    private $content = 30;
    private $const_date = "";
    
    private $resArr = [
        "data" => [],  // This will only contain aggregated totals
        "list" => [],  // This will contain user-specific details
        "status_code" => "failed"
    ];

    // Initialize variables to accumulate totals
    private $totalBetAmount = 0.00;
    private $totalRechargeAmount = 0.00;
    private $totalFirstRechargeAmount = 0.00;
    private $betCountSum = 0;
    private $rechargeCount = 0;
    private $firstRechargeCount = 0;

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
            $this->const_date = mysqli_real_escape_string($this->conn, $_GET['DATE']);
            
        } else {
            $this->resArr['status_code'] = "invalid_params";
            $this->returnRequest();
        }
    }
    
    private function fetchLevelData($parent_id, $order) {
        $offset = ($this->const_page_num-1)*$this->content;
        
        if($order == "DESC") {
            $select_sql = "SELECT * FROM tblusersdata WHERE tbl_joined_under='{$parent_id}' ORDER BY id DESC LIMIT {$offset},{$this->content} ";
        } else {
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

    private function fetchTotalBettedAmount($user_id,$date) {
        $select_sql = "SELECT SUM(tbl_match_invested) AS total_betted_amount FROM tblmatchplayed WHERE tbl_user_id='{$user_id}' AND DATE(STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p')) = '$date'";
        $result = $this->conn->query($select_sql);

        if ($result) {
            $row = $result->fetch_assoc();
            return $row['total_betted_amount'] ? (float)$row['total_betted_amount'] : 0.00;
        }
        return 0.00;
    }

    private function fetchTotalRechargeAmount($user_id,$date) {
        $select_sql = "SELECT SUM(tbl_recharge_amount) AS total_recharge_amount FROM tblusersrecharge WHERE tbl_user_id='{$user_id}' AND tbl_request_status='success' AND DATE(STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p')) = '$date'";
        $result = $this->conn->query($select_sql);

        if ($result) {
            $row = $result->fetch_assoc();
            return $row['total_recharge_amount'] ? (float)$row['total_recharge_amount'] : 0.00;
        }
        return 0.00;
    }

    private function fetchTotalComissionAmount($user_id,$date) {
        $select_sql = "SELECT SUM(tbl_transaction_amount) AS total_comission_amount FROM tblotherstransactions WHERE tbl_user_id='{$user_id}' AND tbl_transaction_type='comission' AND DATE(STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p')) = '$date'";
        $result = $this->conn->query($select_sql);

        if ($result) {
            $row = $result->fetch_assoc();
            return $row['total_comission_amount'] ? (float)$row['total_comission_amount'] : 0.00;
        }
        return 0.00;
    }

    private function fetchFirstRechargeAmount($user_id,$date) {
        $select_sql = "SELECT tbl_recharge_amount FROM tblusersrecharge WHERE tbl_user_id='{$user_id}' AND DATE(STR_TO_DATE(tbl_time_stamp, '%d-%m-%Y %h:%i %p')) = '$date' ORDER BY id ASC LIMIT 1";
        $result = $this->conn->query($select_sql);
    
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return isset($row['tbl_recharge_amount']) ? (float)$row['tbl_recharge_amount'] : 0.00;
        }
    
        return 0.00;
    }
    
    private function processLevelData($row, $level,$date) {
        $account_type = $row['tbl_account_level'] >= 2 ? "Vip" : "Normal";
        
        $user_id = $row['tbl_uniq_id'];

        // Fetching bet and recharge details
        $total_betted = $this->fetchTotalBettedAmount($user_id,$date);
        $total_recharge = $this->fetchTotalRechargeAmount($user_id,$date);
        $total_comission = $this->fetchTotalComissionAmount($user_id,$date);
        $firstRechargeAmount = $this->fetchFirstRechargeAmount($user_id,$date);

        // Add recharge data (successful recharges and total amount recharged)
        $rechargeCount = $total_recharge > 0 ? 1 : 0;
        $rechargeAmountSum = number_format($total_recharge, 2);

        // Increment totals for summary
        $this->totalBetAmount += $total_betted;
        $this->totalRechargeAmount += $total_recharge;
        $this->totalFirstRechargeAmount += $firstRechargeAmount;
        $this->betCountSum += $total_betted > 0 ? 1 : 0;
        $this->rechargeCount += $rechargeCount;
        $this->firstRechargeCount += $firstRechargeAmount > 0 ? 1 : 0;
        $joinedDate = $row['tbl_user_joined'];
        $dateTime = DateTime::createFromFormat('d-m-Y h:i A', $joinedDate);
        // Adding individual user details to the 'list' array
        $index2 = [
            'userID' => $row['tbl_uniq_id'],
            'lv' => $level,
            'lotteryAmount' => number_format($total_betted,2),
            'rechargeAmount' => number_format($total_recharge,2),
            'userComission' => number_format($total_comission,2),
            'userState' => $row['tbl_account_status'],
            'userJoined' => $dateTime->format('Y-m-d')
        ];
        array_push($this->resArr['list'], $index2);
    }

    public function processRequest() {
        if($this->const_user_id != "" && $this->const_page_num != "") {
            // level: 1
            $level1_users = $this->fetchLevelData($this->const_user_id, 'DESC');
            
            foreach ($level1_users as $level1Row) {
                $this->processLevelData($level1Row, 1,$this->const_date);
                $level1_id = $level1Row['tbl_uniq_id'];
                
                // level 2
                $level2_users = $this->fetchLevelData($level1_id, 'ASC');
                
                foreach ($level2_users as $level2Row) {
                    $this->processLevelData($level2Row, 2,$this->const_date);
                    $level2_id = $level2Row['tbl_uniq_id'];
                    
                    // level 3
                    $level3_users = $this->fetchLevelData($level2_id, 'ASC');
                    foreach ($level3_users as $level3Row) {
                        $this->processLevelData($level3Row, 3,$this->const_date);
                    }
                }
            }
    
            // Add total summary to the data array after all the users have been processed
            $summary = [
                'betCountSum' => $this->betCountSum,
                'betAmountSum' => number_format($this->totalBetAmount, 2),
                'rechargeCount' => $this->rechargeCount,
                'rechargeAmountSum' => number_format($this->totalRechargeAmount, 2),
                'firstRechargeCount' => $this->firstRechargeCount,
                'firstRechargeAmount' => number_format($this->totalFirstRechargeAmount, 2)
            ];
    
            // Only push the summary once to 'data'
            array_push($this->resArr['data'], $summary);
            $this->resArr["status_code"] = "success";
        } else {
            $this->resArr["status_code"] = "invalid_params";
        }
    
        // Now the list array will contain the individual users
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
