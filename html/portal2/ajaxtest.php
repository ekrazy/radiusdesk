<?php 
 header('Access-Control-Allow-Origin: *');
?>
<html>
    <head>
    <script src="js/jquery.min.js"></script>
        <script>
            $.ajax({
                url: "https://10.5.50.1/status",
                method: 'get', 
                dataType: 'json', //if you're sure its returning json you can set this
                success: function(data) {
                    console.log(data.message);
                },
                error: function(error) {
                    //handle error json here
                }
            });
        </script>   
    </head>
<body>
</body>  
</html>