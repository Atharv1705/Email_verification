<?php
require 'functions.php';
session_start();

// CSRF token generation
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

// Handle reset
if (isset($_GET['reset'])) {
    unset($_SESSION['pending_email']);
    header('Location: index.php');
    exit;
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'send_code' && $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "❌ Invalid email format.";
        } elseif (isEmailRegistered($email)) {
            $message = "⚠️ This email is already subscribed.";
        } elseif (isset($codes[$email]) && (time() - $codes[$email]['timestamp']) < 60) {
            $wait = 60 - (time() - $codes[$email]['timestamp']);
            $message = "⏳ Please wait {$wait}s before requesting a new code.";
        } else {
            $codeGenerated = generateVerificationCode();
            $codes[$email] = [
                'code' => $codeGenerated,
                'timestamp' => time(),
                'attempts' => 0,
                'type' => 'subscribe'
            ];
            file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
            sendVerificationEmail($email, $codeGenerated);
            $_SESSION['pending_email'] = $email;
            $message = "✅ Code sent to <strong>$email</strong>. Please enter it below.";
            $showCodeInput = true;
        }
    } elseif ($action === 'verify_code' && $email && $code) {
        if (isset($codes[$email])) {
            $data = $codes[$email];
            $data['attempts'] = $data['attempts'] ?? 0;

            if ($data['type'] !== 'subscribe') {
                $message = "❌ This code is not valid for subscription.";
            } elseif (time() - $data['timestamp'] > 900) {
                unset($codes[$email]);
                $message = "⏰ Code expired. Please request a new one.";
            } elseif ($data['attempts'] >= 3) {
                unset($codes[$email]);
                $message = "❌ Too many failed attempts.";
            } elseif ($data['code'] === $code) {
                registerEmail($email);
                unset($codes[$email]);
                unset($_SESSION['pending_email']);
                $message = "✅ Email verified and subscribed!";
                $showCodeInput = false;
                $email = '';
            } else {
                $codes[$email]['attempts'] = $data['attempts'] + 1;
                $remaining = 3 - $codes[$email]['attempts'];
                $message = "❌ Incorrect code. $remaining attempts left.";
                $showCodeInput = true;
            }

            file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
        } else {
            $message = "❌ No verification code found. Please request one.";
        }
    } elseif ($action === 'request_new_code') {
        unset($_SESSION['pending_email']);
        unset($codes[$email]);
        file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
        $message = "🔄 Session reset. Enter your email again.";
        $showCodeInput = false;
    }
}

// Autofill if session active
if (isset($_SESSION['pending_email'])) {
    $email = $_SESSION['pending_email'];

    // Only show code input if a valid send/verify just occurred
    if (in_array($action, ['verify_code', 'send_code'])) {
        $showCodeInput = true;
    }
}

// Message styling
$typeClass = '';
if (str_contains($message, '✅')) $typeClass = 'message-success';
elseif (str_contains($message, '❌')) $typeClass = 'message-error';
elseif (str_contains($message, '⚠️') || str_contains($message, '⏰') || str_contains($message, '🔄') || str_contains($message, '⏳')) $typeClass = 'message-warning';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GitHub Email Subscription</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
        font-family: sans-serif;
        background: linear-gradient(135deg, #2f855a, #68d391);
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
        color: #2f855a;
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
        border-color: #38a169;
        outline: none;
    }
    button {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #38a169, #2f855a);
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
    .message-success {
        background: #f0fff4;
        color: #22543d;
        border-left: 5px solid #38a169;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    .message-error {
        background: #fff5f5;
        color: #742a2a;
        border-left: 5px solid #e53e3e;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    .message-warning {
        background: #fffff0;
        color: #744210;
        border-left: 5px solid #dd6b20;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    .info {
        font-size: 14px;
        color: #666;
        text-align: center;
        margin-top: 12px;
    }
    .reset-link {
        text-align: center;
        margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>📬 GitHub Email Subscription</h2>

    <?php if (!empty($message)): ?>
      <div class="<?= $typeClass ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!$showCodeInput): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group">
          <input type="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($email) ?>" required>
        </div>
        <button type="submit" name="action" value="send_code">📨 Send Verification Code</button>
        <p class="info">You'll receive a 6-digit code. It expires in 15 minutes.</p>
      </form>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <div class="form-group">
          <input type="text" name="verification_code" id="verification_code" placeholder="123456" maxlength="6" required pattern="[0-9]{6}">
        </div>
        <button type="submit" name="action" value="verify_code">✅ Verify</button>
        <div class="reset-link">
          <button type="submit" name="action" value="request_new_code" style="margin-top: 12px; background: #ddd; color: #333;">🔁 Request New Code</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <script>
    const input = document.getElementById('verification_code');
    if (input) {
        input.addEventListener('input', e => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        input.focus();
    }
  </script>
</body>
</html>
