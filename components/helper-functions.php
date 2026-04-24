<?php
class HelperFunctions {
    public function getJsonDecode($arrString){
        return json_decode($arrString, true);
    }
    
    public function getNumberFormat($number = 0, $decimalPoint=2){
        $multiplier = pow(10, $decimalPoint);
          
        // Truncate without rounding
        $truncated = floor($number * $multiplier) / $multiplier;
        return number_format($truncated, $decimalPoint, '.', '');
    }
    
    public function getSecondsBetDates($time1,$time2){
        $timeFirst  = strtotime($time1);
        $timeSecond = strtotime($time2);
        return $timeSecond - $timeFirst;
    }
    
    public function generateRandID($prefix="RB0", $length = 15) {
        $characters = "0123456789AGPR";
        $charactersLength = strlen($characters);
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $prefix . $randomString;
    }
    
    public function generateRandString($length = 10, $capital=false, $prefix="") {
        $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $charactersLength = strlen($characters);
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        if(!$capital){
           $randomString = strtolower($randomString);
        }
        return $prefix . $randomString;
    }
    
    public function generateRandNumber($length = 10, $prefix="") {
        $characters = "0123456789";
        $charactersLength = strlen($characters);
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $prefix . $randomString;
    }
    
    public function stringToBoolean($string){
        if($string=="true" || $string=="1"){
            return true;
        }else{
            return false;
        }
    }
    
    public function generateRandInt($min,$max){
      return mt_rand($min,$max);
    }
}