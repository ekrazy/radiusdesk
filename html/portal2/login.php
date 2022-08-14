<?php 
session_start();

$_SESSION['mac'] = $_POST['mac'];
$username = $_SESSION['username'];
$message = $_SESSION['error'];

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
		
		<title>Login</title>
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
		<link href="style.css" rel="stylesheet" type="text/css">
		<script src="/flash/assets/scripts/jquery-3.6.0.min.js"></script>
		<script>
        $(document).ready(function () {
        
        <?php if($message=="success") { ?>
        document.getElementById('error').classList.remove("hidden");
        <?php } ?>

        });
    </script>
	</head>
	<body>
		<div class="sign-in-box">
			<img src="img/logo.png" class="logo">
			<div role="heading" aria-level="1" data-bind="text: title" class="title">Sign in</div>
			<div id="error" class="error hidden">
			 <strong>Vocher loaded succesfully, Please login <br /></strong>
			 <?php print $_SSSION['trans_ID']; ?>
		   </div>
			<form action="login2.php" method="post">
				<div class="row">	
					<div class="col-md-24">
						<input type="text" name="username" class="text-box" value="<?php echo $username; ?>" placeholder="Username" id="username" required>
					</div>
					<div class="col-md-24">
						<input type="password" class="text-box" name="password" placeholder="Password" id="password" required>
					</div>
				</div>
					<div class="no-account"><a href="signup.php">Don't have an account? Sign up now.</a></div>
					
					<div class="col-xs-24 button-container no-padding-left-right">
						<div class="inline-block"><input type="submit" class="button primary" value="Sign In"></div>
						<div class="inline-block"><input type="submit" class="button secondary" value="Recharge"></div>
					</div>
					<div class="row tile">
						<div class="table" role="button" tabindex="0">
						 <div class="table-row">
							<div class="table-cell tile-img medium">
								<img class="tile-img medium" role="presentation" data-bind="attr: { src: $data.darkIconUrl }" src="img/signin-options_4e48046ce74f4b89d45037c90576bfac.svg">
							</div>
							<div class="table-cell text-left content">
								Forgot Password?
							</div>
						 </div>
						</div>
					</div>

				</div>
			</form>
		</div>
	</body>
</html>