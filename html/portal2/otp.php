<?php
session_start();
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Login</title>
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
		<link href="style.css" rel="stylesheet" type="text/css">
	</head>
	<body>
		<div class="login">
			<h1>Login</h1>
			<form action="register.php" method="post">
				<label for="otpvalue">
					<i class="fas fa-user"></i>
				</label>
				<input type="text" name="otpvalue" placeholder="OTP" id="otpvalue" required>
				<input type="submit" value="Submit">
			</form>
		</div>
	</body>
</html>