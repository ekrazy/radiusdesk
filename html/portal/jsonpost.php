<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>jQuery AJAX Submit Form</title>
<script src="js/jquery-3.5.1.min.js"></script>
<script>
  $(document).ready(function(){
    $("form").on("submit", function(event){
        event.preventDefault();
 
        var formValues= $(this).serializeArray();
        alert("hello");
        console.log(formValues);
        $.post("https://10.5.50.1/login?", formValues, function(data){
            // Display the returned data in browser
            $("#result").html(data);
        });
    });
});

</script>
</head>
<body>
    <div id="result"></div>
    <form>
        <p>
            <label>Name:</label>
            <input type="text" name="username">
        </p>

        <p>
            <label>Password:</label>
            <input type="password" name="password">
        </p>
        
        <input type="submit" value="submit">
    </form>
   
</body>
</html>