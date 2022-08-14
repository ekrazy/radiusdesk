<?php
    
    function save_redeem($data, $pin, $username, $amount, $phone){

        include "config.php";

        $json = json_decode($data);
        $changeVoucher = $json->changeVoucher;
        
        $sql = "INSERT INTO voucher_redeem values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp())";

        $stmt = mysqli_prepare($link, $sql);

        if(false===$stmt){
            print("Prepaired failed: ".htmlspecialchars($mysqli->error));
            die('prepare failed: '.htmlspecialchars($mysqli->error));
        }
        

        $rc = $stmt->bind_param("isssisssssssis", $id, $requestId, $username, $pin, $amount, $transactionID, $transactionDate, $responseMessage, $comments, $phone, $changePin, $changeSerial, $changeAmount, $expiryDate);

        if(false===$rc){
            die('bind_param() failed: '. htmlspecialchars($stmt->error));
        }

        $id = "";
        $requestId = $json->requestId;
        $transactionID = $json->transactionId;
        $transactionDate = $json->transactionDate;
        $responseMessage = $json->responseMessage;
        $comments = $data;
        $changePin = $changeVoucher->pin;
        $changeSerial = $changeVoucher->serialNumber;
        $changeAmount = $changeVoucher->amount;
        $expiryDate = $changeVoucher->expiryDate;

        //Executing the statement
        $rc = $stmt->execute();
        if ( false===$rc ) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        //Closing the statement
        $stmt->close();
    }
?>