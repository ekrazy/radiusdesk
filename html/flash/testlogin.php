<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    
    <title>Amathuba Topup</title>

    <link href="css/style.css" rel="stylesheet" type="text/css">
        <style>
            .button {
                font: bold 11px Arial;
                text-decoration: none;
                /* background-color: #EEEEEE; */
                color: #ffffff;
                padding: 10px 28px 10px 28px;
                border-top: 1px solid #CCCCCC;
                border-right: 1px solid #333333;
                border-bottom: 1px solid #333333;
                border-left: 1px solid #CCCCCC;
                border-radius: 8px;
                background-color: #FE4C02;
                width: 100%;
                margin-top: 10px;
            }
            .strong-header {
              display: block;
            }

            .container_message {
                margin: auto;
                width: 50%;
                padding: 10px;
            }
        </style>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script>
          $(document).ready(function(){ 
           $("button").click(function(){
                console.log("data");
                $.post("http://10.5.50.1/login",
                {
                    username: "kulani",
                    password: "eKrazy911"
                },
            function(data, status){
                alert("Data: " + data + "\nStatus: " + status);
            });
            });
        });
    </script>
    </head>
<body>
    <button class="button" id="button">Submit</button>
</body>
</html>

