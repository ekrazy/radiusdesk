<?php
//require_once "config.php";

function getPhone($username) {
    
 include "config.php";
 $sql = "SELECT id, username, phone FROM permanent_users WHERE username = ?";
   if($stmt = mysqli_prepare($link, $sql)){     
    mysqli_stmt_bind_param($stmt, "s", $param_username);
    $param_username = $username;
    if(mysqli_stmt_execute($stmt)){
     mysqli_stmt_store_result($stmt);
     if(mysqli_stmt_num_rows($stmt) == 1){
      mysqli_stmt_bind_result($stmt, $id, $username, $phone);
       if(mysqli_stmt_fetch($stmt)){
        return $phone;
       }else {
        echo "Username not found";
       }
     }
    }
 }
}

function checkaccount($username) {
    
    include "config.php";

    $sql = "SELECT id, username FROM permanent_users WHERE username = ?";
    if($stmt = mysqli_prepare($link, $sql)){
      mysqli_stmt_bind_param($stmt, "s", $param_username);
      $param_username = $username;
      if(mysqli_stmt_execute($stmt)){
        mysqli_stmt_store_result($stmt);
        if(mysqli_stmt_num_rows($stmt) == 1){
          return true;
        }else {
         $_SESSION['error'] = 'Account incorect or does not exit, please correct the account or register <a href="/portal2/signup.php">here</a>';
         $_SESSION['usernane'] = $username;
         header("location:redeem.php");
         exit;
        }
      }
    }
}

function checkvoucher($pin) {
    
   include "config.php";
   $sql = "SELECT * FROM voucher_redeem WHERE pin = ?";
   if($stmt = mysqli_prepare($link, $sql)){     
    mysqli_stmt_bind_param($stmt, "s", $param_pin);
    $param_pin = $pin;
    if(mysqli_stmt_execute($stmt)){
     mysqli_stmt_store_result($stmt);
     if(mysqli_stmt_num_rows($stmt) >= 1){
      mysqli_stmt_bind_result($stmt, $pin);
       if(mysqli_stmt_fetch($stmt)){
        $_SESSION['error'] = "This 1Voucher has already been redeemed. Please use a new code!!";
        header("location:redeem.php");
        exit;
       } 
     } else {
       return false;
     }
    }
 }
}

function deleteAttr($username) {
    
    include "config.php";
    
    $sql = "DELETE FROM radcheck WHERE `username`= ? and `attribute`='Rd-Total-Time'";

    $stmt = mysqli_prepare($link, $sql);

    if(false===$stmt){
        print("Prepaired failed: ".htmlspecialchars($mysqli->error));
        die('prepare failed: '.htmlspecialchars($mysqli->error));
    }

    $rc = $stmt->bind_param("s", $username);
    if(false===$rc){
        die('bind_param() failed: '. htmlspecialchars($stmt->error));
    }

    $rc = $stmt->execute();
    if ( false===$rc ) {
        die('execute() failed: ' . htmlspecialchars($stmt->error));
    }
    //Closing the statement
    $stmt->close();
}

?>