<?php
require 'functions.php';
session_start();

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$email = trim($_POST['email'] ?? '');
$code = trim($_POST['verification_code'] ?? '');
$action = $_POST['action'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

$codesFile = __DIR__ . '/verification_codes.json';
$codes = file_exists($codesFile) ? json_decode(file_get_contents($codesFile), true) ?? [] : [];

$message = '';
$showCodeInput = false;

// CSRF check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    die('Invalid CSRF token');
}

// Reset session
if (isset($_GET['reset'])) {
    unset($_SESSION['pending_unsub_email']);
    header('Location: unsubscribe.php');
    exit;
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'send_code' && $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "❌ Invalid email format.";
        } elseif (!isEmailRegistered($email)) {
            $message = "⚠️ This email is not subscribed.";
        } elseif (isset($codes[$email]) && (time() - $codes[$email]['timestamp']) < 60 && $codes[$email]['type'] === 'unsubscribe') {
            $wait = 60 - (time() - $codes[$email]['timestamp']);
            $message = "⏳ Please wait {$wait}s before requesting another code.";
        } else {
            $codeGenerated = generateVerificationCode();
            $codes[$email] = [
                'code' => $codeGenerated,
                'timestamp' => time(),
                'attempts' => 0,
                'type' => 'unsubscribe'
            ];
            file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
            sendUnsubscribeCode($email, $codeGenerated);
            $_SESSION['pending_unsub_email'] = $email;
            $message = "📩 Code sent to <strong>$email</strong>. Please enter it below.";
            $showCodeInput = true;
        }
    } elseif ($action === 'verify_code' && $email && $code) {
        if (isset($codes[$email])) {
            $data = $codes[$email];
            if ($data['type'] !== 'unsubscribe') {
                $message = "❌ Code not valid for unsubscription.";
            } elseif (time() - $data['timestamp'] > 900) {
                unset($codes[$email]);
                $message = "⏰ Code expired. Please request a new one.";
            } elseif ($data['attempts'] >= 5) {
                unset($codes[$email]);
                $message = "❌ Too many incorrect attempts.";
            } elseif ($data['code'] === $code) {
                unsubscribeEmail($email);
                unset($codes[$email]);
                unset($_SESSION['pending_unsub_email']);
                $message = "✅ You've been unsubscribed successfully.";
            } else {
                $codes[$email]['attempts']++;
                $remaining = 5 - $codes[$email]['attempts'];
                $message = "❌ Incorrect code. {$remaining} attempts left.";
                $showCodeInput = true;
            }
            file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
        } else {
            $message = "❌ No code found for this email.";
        }
    }
}

if (isset($_SESSION['pending_unsub_email']) && !$showCodeInput) {
    $email = $_SESSION['pending_unsub_email'];
    $showCodeInput = true;
}

$typeClass = '';
if (str_contains($message, '✅')) $typeClass = 'message-success';
elseif (str_contains($message, '❌')) $typeClass = 'message-error';
elseif (str_contains($message, '⚠️') || str_contains($message, '⏰') || str_contains($message, '⏳')) $typeClass = 'message-warning';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Unsubscribe - GitHub Updates</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
        font-family: sans-serif;
        background: linear-gradient(135deg, #dc2626, #f87171);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
    }
    .container {
        background: white;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 420px;
    }
    h2 {
        margin-top: 0;
        font-size: 24px;
        color: #b91c1c;
        text-align: center;
    }
    .form-group {
        margin-bottom: 20px;
    }
    input[type="email"], input[type="text"] {
        width: 100%;
        padding: 14px;
        border: 2px solid #ddd;
        border-radius: 10px;
        font-size: 16px;
    }
    input:focus {
        border-color: #ef4444;
        outline: none;
    }
    button {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #ef4444, #b91c1c);
        border: none;
        border-radius: 10px;
        color: white;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
    }
    button:hover {
        opacity: 0.95;
    }
    .message-success { background: #f0fdf4; color: #14532d; border-left: 5px solid #22c55e; padding: 12px; margin-bottom: 20px; border-radius: 8px; }
    .message-error { background: #fef2f2; color: #991b1b; border-left: 5px solid #ef4444; padding: 12px; margin-bottom: 20px; border-radius: 8px; }
    .message-warning { background: #fefce8; color: #854d0e; border-left: 5px solid #facc15; padding: 12px; margin-bottom: 20px; border-radius: 8px; }
    .info { font-size: 14px; color: #666; text-align: center; margin-top: 12px; }
  </style>
</head>
<body>
<div class="container">
    <h2>🚫 Unsubscribe from GitHub Updates</h2>

    <?php if (!empty($message)): ?>
      <div class="<?= $typeClass ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!$showCodeInput): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group">
          <input type="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($email) ?>" required>
        </div>
        <button type="submit" name="action" value="send_code">📨 Send Unsubscribe Code</button>
        <p class="info">We'll send a confirmation code to verify your unsubscription.</p>
      </form>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <div class="form-group">
          <input type="text" name="verification_code" placeholder="Enter Code" maxlength="6" required pattern="[0-9]{6}">
        </div>
        <button type="submit" name="action" value="verify_code">✅ Confirm Unsubscribe</button>
      </form>
    <?php endif; ?>
</div>
<script>
const input = document.querySelector('input[name="verification_code"]');
if (input) {
    input.addEventListener('input', e => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });
}
</script>
</body>
</html>
