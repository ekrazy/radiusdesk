<?php
    
    session_start();
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        echo "Current OTP ".$_SESSION['otp'];
        if($_POST['otp'] == $_SESSION['otp']) {
            echo "Succesfully verified";
        } else {
            echo "Current OTP ".$otp;
            echo "OTP Incorrect";
        }
    } else {
        
        // create curl resource
                
        $otp = rand(1000,9999);
        $_SESSION['otp']=$otp;
        $message = "'OTP 1234'";
        $ch = curl_init();
        $url = "https://platform.clickatell.com/messages/http/send?apiKey=_MSTxeJsS1euj7zwX_xoIA==&to=27832000498&content=".$message;
        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $output = curl_exec($ch);
        echo $otp;
        // close curl resource to free up system resources
        curl_close($ch);
?> 
<form method="post" action""><q></q>
    <input type="text" name="otp">
    <input type="submit">
</form>

<?php } ?>
