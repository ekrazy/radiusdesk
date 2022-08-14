<?php

 function topup($user,$amount){
 
    $token = "b4c6ac81-8c7c-4802-b50a-0a6380555b50";
    $username = $user;
    $values = $amount;
    
    $comment = "payment via 1foryou";

    $postData = array (
        'comment'           => $comment,
        'data_unit'         => 'days',
        'permanent_user'    => $username,
        'token'             => $token,
        'type'              => 'days_to_use',
        'value'             => $values
    );

    $url = "http://13.244.150.54/cake3/rd_cake/top-ups/add.json";

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
 
    // Decode the response
    $responseData = json_decode($response, TRUE);
    return $responseData;
}

?>