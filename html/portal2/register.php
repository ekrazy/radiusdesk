<?php

include "global.php";

session_start();

$topup = $_SESSION['topup'];
$fullname = explode(" ", $_POST['fullname']);
//$otp = $_SESSION['otp'];
//$uotp = $_POST['otpvalue'];

if($_POST['emailaddress']) { $username = $_POST['emailaddress'];}
if($_POST['mobilenumber']) { $username = $_POST['mobilenumber'];}


 $active         = 'active';
 $cap_time       = 'hard';
 $language       = '4_4';
 $parent_id      = 0;
 $profile_id     = 13;
 $realm_id       = 36;
 $token          = 'b4c6ac81-8c7c-4802-b50a-0a6380555b50'; 
 $name           = $fullname[0];
 $surname        = $fullname[1];
 $password       = $_POST['password'];
 $mac            = $_SESSION['mac'];
 
 $url            = 'http://localhost/cake3/rd_cake/register-users/new-permanent-user.json';
 
 // The data to send to the API
 $postData = array(
    'mac'            => $mac, 
    'active'         => $active,
    'login_page_id'  => "2",
    'name'           => $name,
    'surname'        => $surname,
    'cap_time'       => $cap_data,
    'language'       => $language,
    'parent_id'      => $parent_id,
    'profile_id'     => $profile_id,
    'realm_id'       => $realm_id,
    'token'          => $token,
    'username'       => $username,
    'password'       => $password,
    'phone'          => $mobilenumber
 );
 
 // Setup cURL
 $ch = curl_init($url);
 curl_setopt_array($ch, array(
 
    CURLOPT_POST            => TRUE,
    CURLOPT_RETURNTRANSFER  => TRUE,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
    ),
    CURLOPT_POSTFIELDS => json_encode($postData)
 ));
 
 // Send the request
 $response = curl_exec($ch);
 
 // Check for errors
 if($response === FALSE){
    die(curl_error($ch));
 }
 
 var_dump($response);
 // Decode the response
 $responseData = json_decode($response);

 //echo $responseData->success;
 
 if($responseData->success != true) {
   print $header;
    //var_dump($responseData->errors);
    echo '<div role="heading" aria-level="1" data-bind="text: title" class="title">Error Creating Account</div>';
    if($responseData->message){
      echo $responseData->message;
   } else var_dump($responseData);
?>
<button id="regback" class="button primary" type="button">Go Back</button>
<script>
    $(document).ready(function(){
          $("#regback").click(function(){
            window.location = "signup.php";
          });	
    });
</script>
<?php } print $footer; ?>