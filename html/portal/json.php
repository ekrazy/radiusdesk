<?php
$return = json_decode('{ "success": true, "errors": { "username": " The username you provided is already taken. Please provide another one." }, "message": "Could not create item\u003Cbr\u003E The username you provided is already taken. Please provide another one." }',true);

//var_export($return);
$success = $return['success'];
echo $success;

if($success){
	print " Successfully created a account";
}
?>