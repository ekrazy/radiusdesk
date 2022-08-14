<html>
    <head>
        <style>
            .button {
                font: bold 11px Arial;
                text-decoration: none;
                /* background-color: #EEEEEE; */
                color: #333333;
                padding: 10px 28px 10px 28px;
                border-top: 1px solid #CCCCCC;
                border-right: 1px solid #333333;
                border-bottom: 1px solid #333333;
                border-left: 1px solid #CCCCCC;
                border-radius: 8px;
            }
            .container_message {
                margin: auto;
                width: 50%;
                padding: 10px;
            }
        </style>
    </head>

<?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        include "topups.php";
        $username = $_POST['username'];
        $phone = $_POST['phone'];
        $value = $_POST['value'];
        $results = topup($username,$value);
        
        echo "<div class='container_message'><h3>Thank You</h3>";
        echo "<p class='message'>Your topup has been succesfully loaded please click the button below to login</p>";
        echo "<a class='button' href='http://10.5.50.1/login'>Connect</a></div>";

    } else {
?>
    <body>
        <form method="POST" action="">
            <select class="Recharge Options" name="value">
                <option value="1">1 Day - Uncapped Internet -R4.50</option>
                <option value="7">7 Days - Uncapped Internet - R14.50</option>
                <option value="30">30 Days - Uncapped Internet - R50.00</option>
            </select>

            <input class="username" type="text" placeholder="username" name="username">
            <input class="phonenumber" type="text" placeholder="27832000498" name="phone">

            <button type="submit" value="submit">Submit</button>
        </form>
    </body>
</html>

<?php } ?>