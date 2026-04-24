<?php
set_time_limit(2000);
define("ACCESS_SECURITY", "true");
include "../security/config.php";
include "../security/constants.php";

// getting current date & times
date_default_timezone_set("Asia/Kolkata");

class InitiateSallary
{
    private $conn = "";
    private $rewardName = "Sallary";
    private $rewardType = "sallary";
    private $rewardReceivedFrom = "app";
    private $totalRecords = 0;

    private $currDate = "";
    private $currDateTime = "";

    private $level1Bonus = 0;
    private $level2Bonus = 0;
    private $level3Bonus = 0;

    function __construct($conn)
    {
        $this->conn = $conn;
        $this->currDate = date("d-m-Y");
        $this->currDateTime = date("d-m-Y h:i a");
    }

    function process()
    {
        $returnVal = "false";

        $select_services_sql =
            "SELECT * FROM tblservices WHERE tbl_service_name='SALLARY_PERCENT' ";
        $select_services_query = mysqli_query(
            $this->conn,
            $select_services_sql
        );

        if (mysqli_num_rows($select_services_query) > 0) {
            $services_data = mysqli_fetch_assoc($select_services_query);
            $sallaryPercent = $services_data["tbl_service_value"];
            $resArray = explode(",", $sallaryPercent);

            if (count($resArray) == 3 || count($resArray) == 1) {
                if (count($resArray) == 3) {
                    $this->level1Bonus = $resArray[0];
                    $this->level2Bonus = $resArray[1];
                    $this->level3Bonus = $resArray[2];
                } else {
                    $this->level1Bonus = $sallaryPercent;
                    $this->level2Bonus = $sallaryPercent;
                    $this->level3Bonus = $sallaryPercent;
                }

                $returnVal = $this->getEligibleList();
            }
        }

        return $returnVal;
    }

    // total users
    function getEligibleList()
    {
        $returnVal = "false";

        $search_sql =
            "SELECT * FROM tblusersdata WHERE tbl_account_level >= 2 AND tbl_account_status='true' ";
        $search_query = mysqli_query($this->conn, $search_sql);

        if (mysqli_num_rows($search_query) > 0) {
            while ($resp_data = mysqli_fetch_assoc($search_query)) {
                $userMainID = $resp_data["tbl_uniq_id"];
                $search_transaction_sql = "SELECT * FROM tblotherstransactions WHERE tbl_user_id='{$userMainID}' AND tbl_transaction_type='{$this->rewardType}' AND tbl_time_stamp LIKE '%$this->currDate%' ";
                $search_transaction_query = mysqli_query($this->conn, $search_transaction_sql);
                
                if (mysqli_num_rows($search_transaction_query) <= 0) {
                    $this->sendSallary($userMainID);
                }
            }
        }

        if ($this->totalRecords > 0) {
            $returnVal = "true";
        }

        return $returnVal;
    }

    // main referal
    function sendSallary($userMainID)
    {
        $sallaryAmount = 0;

        // level: 1
        $search_sql = "SELECT * FROM tblusersdata WHERE tbl_joined_under='{$userMainID}' AND tbl_account_level >= 2 ";
        $search_query = mysqli_query($this->conn, $search_sql);

        if (mysqli_num_rows($search_query) > 0) {
            
            while ($resp_data = mysqli_fetch_assoc($search_query)) {
                $Level1UserID = $resp_data["tbl_uniq_id"];

                $search_recharge_sql = "SELECT * FROM tblusersrecharge WHERE tbl_user_id='{$Level1UserID}' AND tbl_request_status='success' AND tbl_time_stamp like '%$this->currDate%' ";
                $search_recharge_query = mysqli_query(
                    $this->conn,
                    $search_recharge_sql
                );

                while (
                    $resp_recharge_data = mysqli_fetch_assoc(
                        $search_recharge_query
                    )
                ) {
                    $sallaryAmount +=
                        $resp_recharge_data["tbl_recharge_amount"] *
                        ($this->level1Bonus / 100);
                }

                // level: 2
                $search_sql = "SELECT * FROM tblusersdata WHERE tbl_joined_under='{$Level1UserID}' AND tbl_account_level >= 2 ";
                $search_query = mysqli_query($this->conn, $search_sql);

                if (mysqli_num_rows($search_query) > 0) {
                    while ($resp_data = mysqli_fetch_assoc($search_query)) {
                        $Level2UserID = $resp_data["tbl_uniq_id"];

                        $search_recharge_sql = "SELECT * FROM tblusersrecharge WHERE tbl_user_id='{$Level2UserID}' AND tbl_time_stamp LIKE '%$this->currDate%' AND tbl_request_status='success' ";
                        $search_recharge_query = mysqli_query(
                            $this->conn,
                            $search_recharge_sql
                        );

                        while (
                            $resp_recharge_data = mysqli_fetch_assoc(
                                $search_recharge_query
                            )
                        ) {
                            $sallaryAmount +=
                                $resp_recharge_data["tbl_recharge_amount"] *
                                ($this->level2Bonus / 100);
                        }

                        // level: 3
                        $search_sql = "SELECT * FROM tblusersdata WHERE tbl_joined_under='{$Level2UserID}' AND tbl_account_level >= 2 ";
                        $search_query = mysqli_query($this->conn, $search_sql);

                        if (mysqli_num_rows($search_query) > 0) {
                            while (
                                $resp_data = mysqli_fetch_assoc($search_query)
                            ) {
                                $Level3UserID = $resp_data["tbl_uniq_id"];

                                $search_recharge_sql = "SELECT * FROM tblusersrecharge WHERE tbl_user_id='{$Level3UserID}' AND tbl_time_stamp LIKE '%$this->currDate%' AND tbl_request_status='success' ";
                                $search_recharge_query = mysqli_query(
                                    $this->conn,
                                    $search_recharge_sql
                                );

                                while (
                                    $resp_recharge_data = mysqli_fetch_assoc(
                                        $search_recharge_query
                                    )
                                ) {
                                    $sallaryAmount +=
                                        $resp_recharge_data[
                                            "tbl_recharge_amount"
                                        ] *
                                        ($this->level3Bonus / 100);
                                }
                            }
                        }
                    }
                }
            }

            $sallaryAmount = number_format($sallaryAmount, 2, ".", "");

            if ($sallaryAmount > 0) {
                $update_balance_sql = $this->conn->prepare(
                    "UPDATE tblusersdata SET tbl_balance = tbl_balance + ? WHERE tbl_uniq_id = ?"
                );
                $update_balance_sql->bind_param(
                    "ss",
                    $sallaryAmount,
                    $userMainID
                );
                $update_balance_sql->execute();

                $insert_sql = $this->conn->prepare(
                    "INSERT INTO tblotherstransactions(tbl_user_id,tbl_received_from,tbl_transaction_type,tbl_transaction_amount,tbl_transaction_note,tbl_time_stamp) VALUES(?,?,?,?,?,?)"
                );
                $insert_sql->bind_param(
                    "ssssss",
                    $userMainID,
                    $this->rewardReceivedFrom,
                    $this->rewardType,
                    $sallaryAmount,
                    $this->rewardName,
                    $this->currDateTime
                );
                $insert_sql->execute();

                $this->totalRecords++;
            }
        }
    }

    function getTotalRecords()
    {
        return $this->totalRecords;
    }

    function __destruct()
    {
        $this->conn->close();
    }
}

$sallaryHandler = new InitiateSallary($conn);
$sallaryHandler->process();
if($sallaryHandler->getTotalRecords() > 0){
   echo "<h2>Sallary Sended! (Total: " . $sallaryHandler->getTotalRecords()." Users)</h2>"; 
}else{
   echo "<h2>No Eligible users found!</h2>";
}
?>