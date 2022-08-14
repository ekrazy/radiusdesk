<?php 
session_start(); 
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta name="description" content="Responsive Admin &amp; Dashboard Template based on Bootstrap 5">
		<meta name="author" content="Kayweb">
		<meta name="keywords" content="Amathuba, bootstrap, bootstrap 5, admin, dashboard, template, responsive, css, sass, html, theme, front-end, ui kit, web">
		<link rel="preconnect" href="https://fonts.gstatic.com">
		
		<title>Register Account</title>
		<link rel="stylesheet" href="css/fontawesome.css">
		<link href="style.css" rel="stylesheet" type="text/css">
		<script src="js/jquery.min.js"></script>
		<script>
			$(document).ready(function(){

				$("#phoneswitch").click(function(){
					document.getElementById('emailbox').classList.remove("hidden");
					document.getElementById('emailaddress').required = true;
					document.getElementById('mobilebox').classList.add("hidden");
					document.getElementById('mobilenumber').removeAttribute( "required" );
					
				});
				$("#mailswitch").click(function(){

					document.getElementById('emailbox').classList.add("hidden");
					document.getElementById('emailaddress').removeAttribute( "required" );
					document.getElementById('mobilebox').classList.remove("hidden");
					document.getElementById('mobilenumber').required = true;
					
				});
				  $("#loginback").click(function(){
					window.location = "login.php";
				  });
				  
				  $("#register").submit(function(){
					if (document.getElementById('password').value != document.getElementById('passwordconf').value){
						alert("Password do not match, please retype");
						return false;
					}
				});
			});
		</script>
	</head>
	<body>
		<div class="sign-in-box">
			<img src="img/logo.png" class="logo">
			<div role="heading" aria-level="1" data-bind="text: title" class="title">Register Account</div>
			<div id="error" class="hidden"></div>
			<form id="register" action="register.php" method="post">
				<div class="row">
					<div id="mobilebox" class="col-md-24 mobilebox">
						<input type="text" name="mobilenumber" class="text-box" placeholder="Mobile Number.(0831234567)" id="mobilenumber" required>
						<button id="phoneswitch" class="smallhref" type="button">use email address instead</button>
					</div>

					<div id="emailbox" class="col-md-24 hidden">
						<input type="email" name="emailaddress" class="text-box" placeholder="email address" id="emailaddress">
						<button id="mailswitch" class="smallhref" type="button">use phone number instead</button>
					</div>
				
					<div class="col-md-24">
						<input type="text" name="fullname" class="text-box" placeholder="Name Surname" id="fullname" required>
					</div>

					<div class="col-md-24">
						<input type="password" class="text-box" name="password" placeholder="Password" id="password" required>
					</div>

					<div class="col-md-24">
						<input type="password" class="text-box" name="passwordconf" placeholder="Confirm Password" id="passwordconf" required>
					</div>
				</div>
					
					<div class="col-xs-24 button-container no-padding-left-right" style="margin-top:20px;">
						<div class="inline-block"><input type="submit" class="button primary" value="Register"></div>
						<div class="inline-block"><button id="loginback" type="button" class="button secondary">Cancel</button></div>
					</div>

				</div>
			</form>
		</div>
	</body>
</html>