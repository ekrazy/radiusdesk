<?php 
    session_start(); 
    $error = $_SESSION['error'];
    echo "testing Git";
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    
    <title>Amathuba Topup</title>
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <script src="assets/scripts/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function () {
        
        <?php if($error) { ?>
        document.getElementById('error').innerHTML = '<?php echo $error; ?>';
        document.getElementById('error').classList.remove("hidden");
        <?php } ?>
            $('#myform').submit(function() {
                
                document.getElementById('error').classList.add("hidden");
                if (document.getElementById('pin').value.length != 16) {
                document.getElementById('error').textContent = 'Invalid PIN.';
                document.getElementById('error').classList.remove("hidden");
                return false;
                }
                return true;
            });
        });
    </script>
</head>

<body>
    <div class="row">
    <div class="col" style="margin: Auto; font-weight:bold; font-size:25px;"><center>Amathuba Connect</center></div>
    </div>
    <div class="container-fluid">
        <br>
        <div id="error" class="hidden error div3"></div>
        <form id="myform" name="purchase" method="POST" action="flashpost.php">
            
            <div class="amount_input div3">
               <select name="topup" id="topup" class="voucher_type">
                  <option value="daily">1 Day access for R4.50</option>
                  <option value="weekly">7 Days access for R14.50</option>
                  <option value="monthly">30 Days access for R50.00</option>
               </select>
            </div>

            <div class="username div3">
                <input name="username" class="tel" type="text" placeholder="Username" value="<?php echo $_SESSION['username']; ?>">
            </div>

            <div class="voucher_pin div3">
                <input name="phone" class="pin" value="<?php echo $_SESSION['phone']; ?>" placeholder="Mobile No.">
            </div>

            <div class="voucher_pin div3">
                <input id="pin" name="pin" class="pin" value="<?php echo $_SESSION['pin']; ?>" placeholder="Voucher Pin">
            </div>
            <div class="row">
                <div class="col-sm-6"><button type="submit" id="submit_button" class="submit_payment">Submit</button></div>
                <div class="col-sm-6"><button type="button" class="cancel_payment_button" onclick="location.href='http://10.5.50.1/login'">Cancel</button></div>
            </div>
        </form>
        <div class="footer" id="footer"><img src="assets/img/oneforyou_logo.jpeg" style="height: 100px;"></div>
    </div>
    <script class="iti-load-utils" async="" src="assets/scripts/utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>

</html>
