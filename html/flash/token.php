<?php

function get_token() {

  $url = "https://api.flashswitch.flash-group.com/token";

  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

  $headers = array(
     "Authorization: Basic QlhyYUlLeG9yX19BNTBPT3lmU1c0ek9DeDUwYTpKN2dTZnNIanpfZmc4eWhveHVOWTNCU0ZhM0Vh",
     "Content-Type: application/x-www-form-urlencoded",
   );
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

  $data = "grant_type=client_credentials";

  curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

  //for debug only!
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

  $resp = curl_exec($curl);
  curl_close($curl);

  $results = json_decode($resp);
  return $results->access_token;
 }

?>

