<?php 
  
  function ($username, )
  /*
   
      * = required
   
      == Adding a 1Gb Data top-up ==
   
      comment	            One Gb data (optional)
      *data_unit	            gb (options 'mb' or 'gb')
      *permanent_user_id	187 (alternatively you can use 'permanent_user = <permanent user's username>' to avoid lookup for ID)
      *token	            52190fff-a800-48eb-b1f2-478bc0a80167 (root user's token)
      *type	            data (other options are 'time' or 'days_to_use')
      *value	            1 (the amount of data/time or days_to_use)
      *user_id                0 (keep zero to make the owner the owner of the token)
   
      == Adding a 50 minutes Time top-up ==
   
      comment	            50 minutes Time (optional)
      *time_unit	            minutes (options 'minutes' or 'hours' or 'days')
      *permanent_user_id	    187 (alternatively you can use 'permanent_user = <permanent user's username>' to avoid lookup for ID)
      *sel_language	    4_4
      *token	            52190fff-a800-48eb-b1f2-478bc0a80167 (root user's token)
      *type	            time (other options are 'time' or 'days_to_use')
      *value	            1 (the amount of data/time or days_to_use)
      *user_id                0 (keep zero to make the owner the owner of the token)
   
  */
   
  $comment	            = '15 Minutes Time';
  $time_unit	        = 'minutes'; 
  //$permanent_user_id   = 187; 
  $permanent_user        = $username;
  $token	                    = 'b4c6ac81-8c7c-4802-b50a-0a6380555b50';
  $user_id                      = 0; //The owner is the owner of the token
  $type                           = 'time'; 
  $value	                    = 900;
   
  $url                        = 'http://127.0.0.1/cake2/rd_cake/top_ups/add.json';
   
  // The data to send to the API
  $postData = array(
      'comment'               => $comment,
      //'permanent_user_id'     => $permanent_user_id,
      'permanent_user'        => $permanent_user,
      'token'                 => $token,
      'user_id'               => $user_id,
      'type'                  => $type,
      'value'                 => $value
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
   
  // Decode the response
  $responseData = json_decode($response, TRUE);
  print_r($responseData);
  ?>