<?php
    
    session_start();
        
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        // create curl resource
                
        $otp = rand(100000,999999);

        $phone = $_POST['mobilenumber'];

        $word = explode(" ", $_POST['fullname']);

        $message = urlencode("Enter confirmation OTP: ".$otp." to complete Registration");
        $ch = curl_init();
        $url = "https://platform.clickatell.com/messages/http/send?apiKey=_MSTxeJsS1euj7zwX_xoIA==&to=".$phone."&content=".$message;
        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $output = curl_exec($ch);
        // close curl resource to free up system resources
        curl_close($ch);

        $_SESSION['firstname'] = $word[0];
        $_SESSION['phone'] = $phone;
        $_SESSION['surname'] = $word[1];
        $_SESSION['username'] = $phone;
        $_SESSION['password'] = $_POST['password'];
        $_SESSION['otp'] = $otp;

        header( "location: otp.php" );
    } else {
        header( "location: login.html");
    }

?> 
