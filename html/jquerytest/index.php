<!DOCTYPE html>
<html>
  <head>
    <title>jQuery Form Example</title>
    <link
      rel="stylesheet"
      href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css"
    />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
    <script src="js/form.js"></script>
</head>
  <body>
    <div class="col-sm-6 col-sm-offset-3">
      <h1>Processing an AJAX Form</h1>

      <form action="" method="POST">
        <div id="name-group" class="form-group">
          <label for="username">Username</label>
          <input
            type="text"
            class="form-control"
            id="username"
            name="username"
            placeholder="username"
          />
        </div>

        <div id="email-group" class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            placeholder="password"
          />
        </div>

        <div id="superhero-group" class="form-group">
          
        </div>

        <button type="submit" class="btn btn-success">
          Submit
        </button>
      </form>
    </div>
  </body>
</html>