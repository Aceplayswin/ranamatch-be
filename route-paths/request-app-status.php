<?php
class AppStatusManager {
    private $conn;
    
    private $resArr = [
        "status_code" => "true"
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
    
    public function process(){
        $sql = "SELECT * FROM tblservices WHERE tbl_service_value!='' ";
        $sql_query = mysqli_query($this->conn, $sql);
    
        if (mysqli_num_rows($sql_query) > 0) {
          while($resp_data = mysqli_fetch_array($sql_query)){
            $name = $resp_data['tbl_service_name'];
            $val = $resp_data['tbl_service_value'];
            if($name == "APP_STATUS"){
              $this->resArr['status_code'] = $val;
            } else if ($name == "TELEGRAM_URL") {
              $this->resArr['telegram_url'] = $val;
            } else if ($name == "CONTACT_WHATSAPP") {
              $this->resArr['whatsapp_num'] = $val;
            } else if ($name == "CONTACT_SUPPORT_URL") {
              $this->resArr['support_url'] = $val;
            } else if ($name == "SITE_SOCIAL_LINKS") {
              $this->resArr['site_social_links'] = json_decode($val, true) ?: [];
            }
          }
        }

        
        $this->returnRequest();
    }
}

// Initializing database connection
if ($conn->connect_error) {
    die("db_conn_error");
}

// initializing new object & calling function
$appStatusManager = new AppStatusManager($conn);
$appStatusManager->process();
?>