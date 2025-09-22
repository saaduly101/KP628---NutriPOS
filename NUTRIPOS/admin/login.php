<?php
require_once __DIR__.'/../backend/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $ok = auth_login($_POST['email'] ?? '', $_POST['password'] ?? '');
  if ($ok){
    header(header: 'Location: ../public/products.php');
    exit;
    
  }
  $error = "Invalid credentials";

}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8"/>
    <title>Admin Login</title>
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>" />
  </head>
  <body class="login-page">
    <div class="login-container">
      <div class="login-logo">
        <h1>Nutri<span>POS</span></h1>
        <p class="login-subtitle">Admin Login</p>
      </div>
      <?php if (!empty($error)) echo "<p style='color:red'>".htmlspecialchars($error)."</p>"; ?>
      <form method="post" id="loginForm" autocomplete="username">

        <div class="form-group">
          <label for="email">Username</label>
          <input name="email" placeholder="admin@example.com" value="admin@nutripos.local"/><br/>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-input-container">
            <input name="password" id="password" type="password" placeholder="Password" value="admin123" autocomplete="current-password"/>
            <span class="password-toggle" id="passwordToggle">
              <span class="eye-icon" id="eyeIcon">🧐</span>
              <span class="eye-icon" id="eyeOffIcon" style="display:none">🫣</span>
            </span>
          </div>
        </div>

        <button type="submit" class="login-button" id="loginBtn">Login</button>
      </form>
    </div>
    <script>
        // request login again after go back to previous page
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
          // submit form
          const loginForm = document.getElementById('loginForm');
          if (loginForm) {
            loginForm.addEventListener('submit', function() {
              const button = document.getElementById('loginBtn');
              if (button) {
                button.classList.add('loading');
                button.innerHTML = '<span class="spinner-text"><span class="loading-spinner"></span>Logging in...</span>';
              }
            });
          }
          
          // show password toggle
          const passwordToggle = document.getElementById('passwordToggle');
          if (passwordToggle) {
            passwordToggle.addEventListener('click', function() {
              const passwordInput = document.getElementById('password');
              const eyeIcon = document.getElementById('eyeIcon');
              const eyeOffIcon = document.getElementById('eyeOffIcon');
              
              if (passwordInput && eyeIcon && eyeOffIcon) {
                if (passwordInput.type === 'password') {
                  passwordInput.type = 'text';
                  eyeIcon.style.display = 'none';
                  eyeOffIcon.style.display = 'inline-block';
                } else {
                  passwordInput.type = 'password';
                  eyeIcon.style.display = 'inline-block';
                  eyeOffIcon.style.display = 'none';
                }
              }
            });
          }       
        });
    </script>
  </body>
</html>
