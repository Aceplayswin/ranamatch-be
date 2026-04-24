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
            if($resp_data['tbl_service_name']=="APP_STATUS"){
              $this->resArr['status_code'] = $resp_data['tbl_service_value'];
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