<?php
session_start();
require_once('radius.class.php');
include "global.php";

// Change this to your connection info.
$salt = "DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9miAA";
$DATABASE_HOST = 'localhost';
$DATABASE_USER = 'rd';
$DATABASE_PASS = 'rd';
$DATABASE_NAME = 'rd';

print $header;

$radius = new Radius('127.0.0.1', 'eKrazy911');
// Try and connect using the info above.
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if ( mysqli_connect_errno() ) {
	// If there is an error with the connection, stop the script and display the error.
	exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}


// Now we check if the data from the login form was submitted, isset() will check if the data exists.
if ( !isset($_POST['username'], $_POST['password']) ) {
	// Could not get the data that should have been sent.
	
	print '<div role="heading" aria-level="1" data-bind="text: title" class="title">Sign in</div>';
	print "username and password not entered please login..";
	print "<a href='login.html' class='button secondary login_button'>Login</a>";
	print "</div>";

	print $footer;
	exit();
}

// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id, name, surname, time_cap, time_used, phone, password, to_date FROM permanent_users WHERE username = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
    $username = $_POST['username'];
	$raw_password = $_POST['password'];

	$stmt->bind_param('s', $username);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
	$stmt->bind_result($id, $name, $surname, $time_cap, $time_used, $phone, $password, $to_date);
	$stmt->fetch();
	// Account exists, now we verify the password.
	// Note: remember to use password_hash in your registration file to store the hashed passwords.
	$pass_salt = sha1($salt.$_POST['password']);

	if ($pass_salt == $password) {
		// Verification success! User has logged-in!
		// Create sessions, so we know the user is logged in, they basically act like cookies but remember the data on the server.
		session_regenerate_id();
		$_SESSION['loggedin'] = TRUE;
		$_SESSION['name'] = $_POST['username'];
		$_SESSION['id'] = $id;
		
		if ($name == "" ){
			$title = $_SESSION['name'];
		}else { $title = $name." ".$surname; }
		
		echo '<div role="heading" aria-level="1" data-bind="text: title" class="title">' . $title . '</div>';
		echo "<br> Service Expires ".$to_date." <br> please click <a href='../flash/redeem.php'>here</a> to recharge";
		
		echo '<div class="col-xs-24 button-container no-padding-left-right" style="margin-top:20px;">
				<form method="post" action="http://10.5.50.1/login">
					<input type="hidden" name="username" value="'.$username.'">
					<input type="hidden" name="password" value="'.$raw_password.'">
					<div class="inline-block"><button id="login" class="button primary" type="submit">Connect</button></div>
					<div class="inline-block"><button id="logout" class="button secondary" type="button">Logout</button></div>
			    </form>
			  </div>';
		
		echo '<script>
				
				$("#logout").click(function(){
            		window.location = "http://10.5.50.1/logout";
          		});
			 </script>';

	} else {
		// Incorrect password
		echo 'Incorrect username and/or password!';
	}
} else {
	// Incorrect username
	echo 'Incorrect username and/or password!';
}

	$stmt->close();
}
?>
