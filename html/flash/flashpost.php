<?php

session_start();

include "config.php";
include "token.php";
include "dbconnect.php";
include "topups.php";
include "test_voucher.php";

$token = get_token();

$reqID = "AMA_".time()."_".bin2hex(random_bytes(2))."_".bin2hex(random_bytes(2))."_".bin2hex(random_bytes(2));
echo $reqID;
$username = $_POST['username'];
$topup = $_POST['topup'];
$pin = $_POST['pin'];
$_SESSION['username'] = $username;

checkaccount($username);

$phone = "27".ltrim($_POST['phone'], '0');
$_SESSION['pin'] = $pin;
$_SESSION['phone'] = $_POST['phone'];

if($_POST['phone'] == "" ){
$phone = "27".ltrim(getPhone($username),'0');
}

if($topup=="daily"){
  $amount=450;
  $value = 1;

}

if($topup=="weekly"){
  $amount=1450;
  $value = 7;
  
}

if($topup=="monthly"){
  $amount=5000;
  $value = 30;
  
}

$url = "https://api.flashswitch.flash-group.com/1foryou/1.0.0/redeem";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$headers = array(
   "Content-Type: application/json",
   "Accept: application/json",
   "Authorization: Bearer ".$token,
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

$data = <<<DATA
{  
   "requestId": "$reqID",  
   "voucherPin": $pin,  
   "amount": $amount,
   "customerContact": {
    "mechanism": "SMS",
    "address": $phone
  },   
   "acquirer": {  
     "account": {  
       "accountNumber": "0310-6795-4489-9324"  
     },  
     "entityTag": "AMA-9678-01"  
   }  
 }
DATA;

curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);

$results =  json_decode($resp);

save_redeem($resp, $pin, $username, $amount, $phone);

if ($results->responseMessage == "Success") {

      $result = topup($username,$value);
      $attribResults = deleteAttr($username);
      $_SESSION['error'] = 'success';
      $_SSSION['trans_ID'] = $results->requestId;
      header("location:/portal2/login.php");
      exit;

} 
    
if($results->responseMessage == "OneVoucher_VoucherUsed") {
    $_SESSION['error'] = 'This 1Voucher has already been redeemed. Please use a new code!!, or contact support@amathubaconnect.co.za or WhatsApp <a href="https://api.whatsapp.com/send/?phone=27683706422">068 370 6422</a>';
    header("location:redeem.php");
    exit;
}

if($results->responseMessage == "OneVoucher_InsufficientValue"){
  $_SESSION['error'] = "Insufficient Funds";
  header("location:redeem.php");
  exit;
}

if($results->responseMessage == "OneVoucher_VoucherNotFound" ) {
  $_SESSION['error'] = "Voucher not found please try another voucher";
  header("location:redeem.php");
  exit;
}
?>