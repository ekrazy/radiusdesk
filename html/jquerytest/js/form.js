$(document).ready(function () {
    $("form").submit(function (event) {
      var formData = {
        username: $("#username").val(),
        password: $("#password").val(),
      };
  
      $.ajax({
        type: "POST",
        url: "http://10.5.50.1/login",
        data: formData,
        dataType: "json",
        encode: true,
      }).done(function (data) {
        console.log(data);

        if (!data.success) {
            if (data.errors.username) {
              $("#name-group").addClass("has-error");
              $("#name-group").append(
                '<div class="help-block">' + data.errors.username + "</div>"
              );
            }
    
            if (data.errors.password) {
              $("#email-group").addClass("has-error");
              $("#email-group").append(
                '<div class="help-block">' + data.errors.password + "</div>"
              );
            }
    
          } else {
            $("form").html(
              '<div class="alert alert-success">' + data.message + "</div>"
            );
          }
      });
  
      event.preventDefault();
    });
  });