<?php

    session_start();

    $username = $_POST['username'];
    $mac = $_POST['mac'];
    $ip = $_POST['ip'];

    $bytes_in = $_POST['bytes_in'];
    $bytes_out = $_POST['bytes_out'];
    $uptime = $_POST['uptime'];
    $session_time_left = $_POST['session_timeout'];

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
		
		<title>Status</title>
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
		<link href="style.css" rel="stylesheet" type="text/css">

        <script>
	        function openLogout() {
		        if (window.name != 'hotspot_status') return true;
                    open('http://10.5.50.1/logout', 'hotspot_logout', 'toolbar=0,location=0,directories=0,status=0,menubars=0,resizable=1,width=280,height=250');
		            window.close();
	                return false;
            }
	    </script>
	</head>
	<body>
		<div class="sign-in-box">
			<img src="img/logo.png" class="logo">
			<div role="heading" aria-level="1" data-bind="text: title" class="title">Login Status</div>
            <div class="row">	
					<div class="col-md-24">
                    <table class="table table-striped">
                  <tbody>
                    <tr>
                      <td>IP Address</td>
                      <td><i class="fas fa-caret-right"></i></td>
                      <td><?php echo $ip;?></td>
                    </tr>
                    <tr>
                      <td>MAC Address</td>
                      <td><i class="fas fa-caret-right"></i></td>
                      <td><?php echo $mac; ?></td>
                    </tr>
                    <tr>
                      <td>Upload / Download</td>
                      <td><i class="fas fa-caret-right"></i></td>
                      <td><?php echo $bytes_in. " / ". $bytes_out ?></td>
                    </tr>
                    <tr>
                      <td>Uptime</td>
                      <td><i class="fas fa-caret-right"></i></td>
                      <td><?php echo $uptime; ?></td>
                    </tr>
				
					<tr>
                      <td>Session Time Left</td>
                      <td><i class="fas fa-caret-right"></i></td>
                      <td><?php echo $session_time_left; ?></td>
                    </tr>
					
					<tr>
					  <td>Status Refresh</td>
					  <td><i class="fas fa-caret-right"></i></td>
					  <td><?php echo $refresh_timeout; ?></td>
                  </tbody>
                </table>
					</div>
					<div class="col-md-24">
						
					</div>	
            </div>
            <div class="col-xs-24 button-container no-padding-left-right">
						<div class="inline-block">
                        <form action="http://10.5.50.1/logout" name="logout" onSubmit="return openLogout()">    
                            <button type="submit" class="button primary" value="log off"><i class="pr-2 fas fa-sign-out-alt"></i> Disconnect</button>
                        </form>
                        </div>
						<div class="inline-block"><a href="https://www.google.com" class="button secondary">Go Browsing</a></div>
			</div>
        </div>
    </body>
</html>
