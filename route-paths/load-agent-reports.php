<?php
include "../components/helper-functions.php";

class AgentReports {
    private $conn;
    private $helperFunctions;
    private $currDate = "";
    private $constUserId = "";
    
    private $totalAgentIncome = 0;
    private $totalBettingCommission = 0;
    private $totalRechargeCommission = 0;
    
    private $totalRecharge = 0;
    private $totalWithdrawal = 0;
    
    private $totalMembers = 0;
    private $totalTeamBalance = 0;
    private $totalTodayActive = 0;
    private $totalTodayJoined = 0;
    
    private $resArr = [
        "data" => [],
        "total_income" => 0,
        "total_bet_income" => 0,
        "total_recharge_income" => 0,
        "total_invited" => 0,
        "team_balance" => 0,
        "total_recharge" => 0,
        "total_withdraw" => 0,
        "today_invited" => 0,
        "status_code" => "failed"
    ];

    public function __construct($conn, HelperFunctions $helperFunctions, $currDate) {
        $this->conn = $conn;
        $this->currDate = $currDate;
        $this->helperFunctions = $helperFunctions;
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

        if (is_object($data) && property_exists($data, 'USER_ID')) {
            $this->constUserId = $data->USER_ID;
        } else {
            $this->resArr['status_code'] = "invalid_params";
            $this->returnRequest();
        }
    }
    
    private function fetchRechargeWithdrawalData($parentId) {
        try {
            $stmt = $this->conn->prepare("SELECT tbl_recharge_amount FROM tblusersrecharge WHERE tbl_user_id = ?");
            $stmt->bind_param("s", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $this->totalRecharge += $row['tbl_recharge_amount'];
            }
            
            $stmt = $this->conn->prepare("SELECT tbl_withdraw_amount FROM tbluserswithdraw WHERE tbl_user_id = ?");
            $stmt->bind_param("s", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $this->totalWithdrawal += $row['tbl_withdraw_amount'];
            }
        } catch (Exception $e) {
            error_log("Error in fetchRechargeWithdrawalData: " . $e->getMessage());
        }
    }
    
    private function fetchLevelData($parentId) {
        try {
            $stmt = $this->conn->prepare("SELECT tbl_uniq_id, tbl_last_active_date, tbl_user_joined, tbl_balance FROM tblusersdata WHERE tbl_joined_under = ?");
            $stmt->bind_param("s", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $userUniqId = $row['tbl_uniq_id'];
                $userLastActive = $row['tbl_last_active_date'];
                $userJoined = $row['tbl_user_joined'];
                
                if($this->currDate == $userLastActive){
                    $this->totalTodayActive += 1;
                }
                
                if($this->currDate == substr($userJoined, 0, 10)){
                    $this->totalTodayJoined += 1;
                }
                
                $data[] = $userUniqId;
                $this->totalTeamBalance += $row['tbl_balance'];
                $this->fetchRechargeWithdrawalData($userUniqId);
            }
            
            $this->totalMembers += $result->num_rows;
            return $data;
        } catch (Exception $e) {
            error_log("Error in fetchLevelData: " . $e->getMessage());
            return [];
        }
    }
    
    private function processAllMembers(){
        $level1Users = $this->fetchLevelData($this->constUserId);
            
        foreach ($level1Users as $level1ID) {
            $level2Users = $this->fetchLevelData($level1ID);
                
            foreach ($level2Users as $level2ID) {
                $this->fetchLevelData($level2ID);
            }
        }
        
        $this->resArr["total_invited"] = $this->totalMembers;
        $this->resArr["today_invited"] = $this->totalTodayJoined;
        $this->resArr["today_active"] = $this->totalTodayActive;
        $this->resArr["team_balance"] = number_format($this->totalTeamBalance, 2);
        $this->resArr["total_recharge"] = number_format($this->totalRecharge, 2);
        $this->resArr["total_withdraw"] = number_format($this->totalWithdrawal, 2);
    }
    
    private function processAllTransactions() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tblotherstransactions WHERE tbl_user_id = ? AND tbl_received_from != 'app'");
            $stmt->bind_param("s", $this->constUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $transactionAmount = $row['tbl_transaction_amount'];
                $transactionType = $row['tbl_transaction_type'];
                $transactionNote = $row['tbl_transaction_note'];
                
                if($transactionType == "commision"){
                    if(in_array($transactionNote, ["Level 1", "Level 2", "Level 3"])){
                        $this->totalBettingCommission += $transactionAmount;
                    } else if($transactionNote == "Recharge Bonus"){
                        $this->totalRechargeCommission += $transactionAmount;
                    }
                }
                
                $this->totalAgentIncome += $transactionAmount;
            }
            
            $this->resArr["total_income"] = number_format($this->totalAgentIncome, 2);
            $this->resArr["total_bet_income"] = number_format($this->totalBettingCommission, 2);
            $this->resArr["total_recharge_income"] = number_format($this->totalRechargeCommission, 2);
        } catch (Exception $e) {
            error_log("Error in processAllTransactions: " . $e->getMessage());
        }
    }
    
    private function processRequest(){
        if($this->constUserId != ""){
            $this->processAllTransactions();
            $this->processAllMembers();
            $this->resArr["status_code"] = "success";
        } else {
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

// initializing new required objects
$helperFunctions = new HelperFunctions();

// initializing new object & calling function
$agentReportsObj = new AgentReports($conn, $helperFunctions, date('Y-m-d'));
$agentReportsObj->process();
?>