<?php
 
$active         = 'active';
$cap_data       = 'soft';
$parent_id      = 1;
$profile_id     = 13;
$realm_id       = 36;
$token          = 'b4c6ac81-8c7c-4802-b50a-0a6380555b50';
$mac            = $_POST['mac'];
$username       = $_POST['username'];
$name           = $_POST['name'];
$surname        = $_POST['surname'];
$phone          = $username;
$password       = $_POST['password'];

$url            = 'http://localhost/cake3/rd_cake/register-users/new-permanent-user.json';

if($mac==""){
    $mac = implode(':', str_split(str_pad(base_convert(mt_rand(0, 0xffffff), 10, 16) . base_convert(mt_rand(0, 0xffffff), 10, 16), 12), 2));
}

// The data to send to the API
$postData = array(
    'active'        => $active,
    'name'	        => $name,
    'surname'       => $surname,
    'cap_data'      => $cap_data,
    'login_page_id' => $parent_id,
    'mac'	        => $mac,
    'phone'         => $phone,
    'profile_id'    => $profile_id,
    'realm_id'      => $realm_id,
    'token'         => $token,
    'username'      => $username,
    'password'      => $password
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
echo $response;
 
// Check for errors
if($response === FALSE){
    die(curl_error($ch));
}
 
// Decode the response
$responseData = json_decode($response,true);
//var_export($responseData);
if($responseData['errors']){
    echo $responseData['message'];
} else
    {
        echo "User successfully created";
        echo "<a href='login.php?username=".$username."'>Click here to login</a>";
    }
?>
