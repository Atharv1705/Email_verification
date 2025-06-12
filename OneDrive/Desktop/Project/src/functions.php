<?php

function generateVerificationCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function registerEmail($email) {
    // Input validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $file = __DIR__ . '/registered_emails.txt';
    
    // Create file if it doesn't exist
    if (!file_exists($file)) {
        touch($file);
    }
    
    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $emails = $emails ? array_map('trim', $emails) : [];
    
    if (!in_array(trim($email), $emails)) {
        return file_put_contents($file, trim($email) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }
    
    return true; // Email already registered
}

function unsubscribeEmail($email) {
    // Input validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $file = __DIR__ . '/registered_emails.txt';
    
    if (!file_exists($file)) {
        return false;
    }
    
    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $emails = array_filter($emails, fn($e) => trim($e) !== trim($email));
    
    $result = file_put_contents($file, implode(PHP_EOL, $emails) . PHP_EOL, LOCK_EX);
    return $result !== false;
}

function storeVerificationCode($email, $code, $type = 'subscribe') {
    $codesFile = __DIR__ . '/verification_codes.json';
    $codes = [];
    
    if (file_exists($codesFile)) {
        $content = file_get_contents($codesFile);
        $codes = json_decode($content, true) ?: [];
    }
    
    $codes[$email] = [
        'code' => $code,
        'type' => $type,
        'timestamp' => time()
    ];
    
    return file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

function verifyCode($email, $inputCode, $type = 'subscribe') {
    $codesFile = __DIR__ . '/verification_codes.json';
    
    if (!file_exists($codesFile)) {
        return false;
    }
    
    $codes = json_decode(file_get_contents($codesFile), true) ?: [];
    
    if (!isset($codes[$email])) {
        return false;
    }
    
    $data = $codes[$email];
    
    // Check if code matches and type matches
    if ($data['code'] !== $inputCode || $data['type'] !== $type) {
        return false;
    }
    
    // Check if code is expired (15 minutes)
    if ((time() - $data['timestamp']) > 900) {
        // Remove expired code
        unset($codes[$email]);
        file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT), LOCK_EX);
        return false;
    }
    
    // Remove used code
    unset($codes[$email]);
    file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT), LOCK_EX);
    
    return true;
}

function sendVerificationEmail($email, $code) {
    // Input validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Store the verification code
    if (!storeVerificationCode($email, $code, 'subscribe')) {
        return false;
    }
    
    $subject = "Your GitHub Updates Verification Code";
    
    // Create both HTML and plain text versions
    $htmlMessage = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0a74da; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .code { font-size: 28px; font-weight: bold; color: #0a74da; text-align: center; 
                    background: white; padding: 20px; margin: 20px 0; border: 3px dashed #0a74da; 
                    border-radius: 8px; letter-spacing: 3px; }
            .footer { text-align: center; color: #666; font-size: 12px; padding: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>📧 GitHub Updates Verification</h2>
            </div>
            <div class="content">
                <p>Hello,</p>
                <p>Thank you for subscribing to GitHub timeline updates! Please use the verification code below to complete your subscription:</p>
                <div class="code">' . htmlspecialchars($code) . '</div>
                <p><strong>Your verification code is: ' . htmlspecialchars($code) . '</strong></p>
                <p>This code will expire in 15 minutes for security reasons.</p>
                <p>If you didn\'t request this, please ignore this email.</p>
            </div>
            <div class="footer">
                <p>This is an automated message from GitHub Updates Service</p>
            </div>
        </div>
    </body>
    </html>';
    
    $plainMessage = "GitHub Updates Verification\n\n";
    $plainMessage .= "Hello,\n\n";
    $plainMessage .= "Thank you for subscribing to GitHub timeline updates!\n";
    $plainMessage .= "Please use the verification code below to complete your subscription:\n\n";
    $plainMessage .= "VERIFICATION CODE: " . $code . "\n\n";
    $plainMessage .= "This code will expire in 15 minutes for security reasons.\n";
    $plainMessage .= "If you didn't request this, please ignore this email.\n\n";
    $plainMessage .= "This is an automated message from GitHub Updates Service";
    
    // Log both versions for debugging
    $logEntry = "=== EMAIL SENT ===\n";
    $logEntry .= "To: $email\n";
    $logEntry .= "Subject: $subject\n";
    $logEntry .= "Verification Code: $code\n";
    $logEntry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "--- Plain Text Version ---\n";
    $logEntry .= $plainMessage . "\n";
    $logEntry .= "--- HTML Version (stripped) ---\n";
    $logEntry .= strip_tags($htmlMessage) . "\n\n";
    
    // Write to log file
    if (!file_put_contents(__DIR__ . '/sent_emails.log', $logEntry, FILE_APPEND | LOCK_EX)) {
        return false;
    }

    // Display simulation message with the actual code (only if not in CLI mode)
    if (!defined('STDIN')) {
        echo '<div style="border:2px solid #0a74da; padding:15px; margin-top:10px; border-radius: 8px; background: #f0f8ff;">
            <p><strong>📧 [Simulated Email Sent]</strong></p>
            <p>To: <code>' . htmlspecialchars($email) . '</code></p>
            <p>Subject: ' . htmlspecialchars($subject) . '</p>
            <div style="background: white; border: 2px dashed #0a74da; padding: 15px; margin: 10px 0; text-align: center;">
                <p style="margin: 0; font-size: 18px;"><strong>Verification Code:</strong></p>
                <p style="margin: 5px 0; font-size: 24px; color: #0a74da; font-weight: bold; letter-spacing: 2px;">' . htmlspecialchars($code) . '</p>
            </div>
            <p style="font-size: 12px; color: #666;">✓ Email content logged to sent_emails.log</p>
            <p style="font-size: 12px; color: #666;">✓ Code expires in 15 minutes</p>
        </div>';
    }
    
    // For production, uncomment these lines and remove the echo above:
    /*
    $headers = "From: GitHub Updates <no-reply@example.com>\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    $result = mail($email, $subject, $htmlMessage, $headers);
    
    return $result;
    */
    
    return true; // Return true for simulation mode
}

function isEmailRegistered($email) {
    // Input validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $file = __DIR__ . '/registered_emails.txt';
    
    if (!file_exists($file)) {
        return false;
    }
    
    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$emails) {
        return false;
    }
    
    $emails = array_map('trim', $emails);
    return in_array(trim($email), $emails);
}

function cleanExpiredCodes() {
    $codesFile = __DIR__ . '/verification_codes.json';
    
    if (!file_exists($codesFile)) {
        return;
    }
    
    $content = file_get_contents($codesFile);
    $codes = json_decode($content, true);
    
    if (!$codes) {
        return;
    }
    
    $currentTime = time();
    $cleaned = false;
    
    foreach ($codes as $email => $data) {
        // Remove codes older than 15 minutes
        if (isset($data['timestamp']) && ($currentTime - $data['timestamp']) > 900) {
            unset($codes[$email]);
            $cleaned = true;
        }
    }
    
    if ($cleaned) {
        file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

function sendUnsubscribeCode($email, $code) {
    // Input validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Store the verification code
    if (!storeVerificationCode($email, $code, 'unsubscribe')) {
        return false;
    }
    
    $subject = "Confirm Unsubscription - GitHub Updates";
    
    $htmlMessage = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .code { font-size: 28px; font-weight: bold; color: #dc3545; text-align: center; 
                    background: white; padding: 20px; margin: 20px 0; border: 3px dashed #dc3545; 
                    border-radius: 8px; letter-spacing: 3px; }
            .footer { text-align: center; color: #666; font-size: 12px; padding: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>🚫 Confirm Unsubscription</h2>
            </div>
            <div class="content">
                <p>Hello,</p>
                <p>We received a request to unsubscribe your email from GitHub timeline updates.</p>
                <p>To confirm this action, please use the code below:</p>
                <div class="code">' . htmlspecialchars($code) . '</div>
                <p><strong>Your unsubscribe code is: ' . htmlspecialchars($code) . '</strong></p>
                <p>This code will expire in 15 minutes.</p>
                <p>If you didn\'t request this, please ignore this email and your subscription will remain active.</p>
            </div>
            <div class="footer">
                <p>This is an automated message from GitHub Updates Service</p>
            </div>
        </div>
    </body>
    </html>';

    $plainMessage = "GitHub Updates - Confirm Unsubscription\n\n";
    $plainMessage .= "Hello,\n\n";
    $plainMessage .= "We received a request to unsubscribe your email from GitHub timeline updates.\n";
    $plainMessage .= "To confirm this action, please use the code below:\n\n";
    $plainMessage .= "UNSUBSCRIBE CODE: " . $code . "\n\n";
    $plainMessage .= "This code will expire in 15 minutes.\n";
    $plainMessage .= "If you didn't request this, please ignore this email and your subscription will remain active.\n\n";
    $plainMessage .= "This is an automated message from GitHub Updates Service";

    // Log entry with clear code display
    $logEntry = "=== UNSUBSCRIBE EMAIL SENT ===\n";
    $logEntry .= "To: $email\n";
    $logEntry .= "Subject: $subject\n";
    $logEntry .= "Unsubscribe Code: $code\n";
    $logEntry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "--- Plain Text Version ---\n";
    $logEntry .= $plainMessage . "\n";
    $logEntry .= "--- HTML Version (stripped) ---\n";
    $logEntry .= strip_tags($htmlMessage) . "\n\n";
    
    // Write to log file
    if (!file_put_contents(__DIR__ . '/sent_emails.log', $logEntry, FILE_APPEND | LOCK_EX)) {
        return false;
    }
    
    // Display simulation with clear code visibility (only if not in CLI mode)
    if (!defined('STDIN')) {
        echo '<div style="border:2px dashed #dc3545; padding:15px; margin-top:10px; border-radius: 8px; background: #fff5f5;">
            <p><strong>🚫 [Simulated Unsubscribe Email]</strong></p>
            <p>To: <code>' . htmlspecialchars($email) . '</code></p>
            <p>Subject: ' . htmlspecialchars($subject) . '</p>
            <div style="background: white; border: 2px dashed #dc3545; padding: 15px; margin: 10px 0; text-align: center;">
                <p style="margin: 0; font-size: 18px;"><strong>Unsubscribe Code:</strong></p>
                <p style="margin: 5px 0; font-size: 24px; color: #dc3545; font-weight: bold; letter-spacing: 2px;">' . htmlspecialchars($code) . '</p>
            </div>
            <p style="font-size: 12px; color: #666;">✓ Email content logged to sent_emails.log</p>
            <p style="font-size: 12px; color: #666;">✓ Code expires in 15 minutes</p>
        </div>';
    }
    
    // For production:
    /*
    $headers = "From: GitHub Updates <no-reply@example.com>\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    $result = mail($email, $subject, $htmlMessage, $headers);
    
    return $result;
    */
    
    return true; // Return true for simulation mode
}

function fetchGitHubTimeline() {
    $url = "https://api.github.com/events";
    $ch = curl_init($url);
    
    if (!$ch) {
        error_log("Failed to initialize cURL");
        return false;
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "PHP-GitHub-Updates-Bot/1.0");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL verification for production
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        error_log("GitHub API Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("GitHub API returned HTTP $httpCode");
        return false;
    }
    
    $data = json_decode($response, true);
    return $data ?: false;
}

function formatGitHubData($data) {
    if (!$data || empty($data)) {
        return "<p>No GitHub updates available at this time.</p>";
    }
    
    $html = "
    <div style='font-family: Arial, sans-serif;'>
        <h2 style='color: #333; border-bottom: 2px solid #0a74da; padding-bottom: 10px;'>
            🚀 Latest GitHub Timeline Updates
        </h2>
        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
            <tr style='background: #0a74da; color: white;'>
                <th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>Event Type</th>
                <th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>User</th>
                <th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>Repository</th>
                <th style='padding: 12px; text-align: left; border: 1px solid #ddd;'>Time</th>
            </tr>";
    
    $eventEmojis = [
        'PushEvent' => '📝',
        'CreateEvent' => '🆕',
        'WatchEvent' => '⭐',
        'IssuesEvent' => '🐛',
        'PullRequestEvent' => '🔄',
        'ForkEvent' => '🍴',
        'ReleaseEvent' => '🎉',
        'DeleteEvent' => '🗑️'
    ];
    
    foreach (array_slice($data, 0, 10) as $index => $event) {
        $type = htmlspecialchars($event['type'] ?? 'Unknown');
        $user = htmlspecialchars($event['actor']['login'] ?? 'Unknown');
        $repo = htmlspecialchars($event['repo']['name'] ?? 'Unknown');
        $time = isset($event['created_at']) ? date('M j, H:i', strtotime($event['created_at'])) : 'Unknown';
        $emoji = $eventEmojis[$type] ?? '📌';
        
        $bgColor = $index % 2 === 0 ? '#f8f9fa' : '#ffffff';
        
        $html .= "<tr style='background: $bgColor;'>
            <td style='padding: 10px; border: 1px solid #ddd;'>$emoji $type</td>
            <td style='padding: 10px; border: 1px solid #ddd;'>$user</td>
            <td style='padding: 10px; border: 1px solid #ddd;'>$repo</td>
            <td style='padding: 10px; border: 1px solid #ddd;'>$time</td>
        </tr>";
    }
    
    $html .= "</table>
        <p style='color: #666; font-size: 12px; text-align: center;'>
            Updates fetched from GitHub Public Timeline
        </p>
    </div>";
    
    return $html;
}

function sendGitHubUpdatesToSubscribers() {
    $emailsFile = __DIR__ . '/registered_emails.txt';
    
    if (!file_exists($emailsFile)) {
        if (!defined('STDIN')) {
            echo "<p style='color: orange;'>No subscribers found.</p>";
        }
        return false;
    }
    
    $emails = file($emailsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if (empty($emails)) {
        if (!defined('STDIN')) {
            echo "<p style='color: orange;'>No subscribers found.</p>";
        }
        return false;
    }
    
    if (!defined('STDIN')) {
        echo "<p>Fetching GitHub updates...</p>";
    }
    
    $data = fetchGitHubTimeline();
    
    if (!$data) {
        if (!defined('STDIN')) {
            echo "<p style='color: red;'>Failed to fetch GitHub updates.</p>";
        }
        return false;
    }
    
    $htmlContent = formatGitHubData($data);
    $updateCount = 0;
    $baseUrl = isset($_SERVER['HTTP_HOST']) ? 
        (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : 
        'http://localhost:8000';
    
    foreach ($emails as $email) {
        $email = trim($email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        
        $unsubscribeLink = $baseUrl . "/unsubscribe.php?email=" . urlencode($email);
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 800px; margin: 0 auto; }
                .header { background: #0a74da; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; border-top: 1px solid #ddd; }
                .unsubscribe { color: #666; font-size: 12px; }
                .unsubscribe a { color: #dc3545; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📊 GitHub Timeline Updates</h1>
                    <p>Your daily dose of GitHub activity</p>
                </div>
                <div class='content'>
                    $htmlContent
                </div>
                <div class='footer'>
                    <div class='unsubscribe'>
                        <p>Don't want these updates? <a href='$unsubscribeLink'>Unsubscribe here</a></p>
                        <p>This email was sent to: " . htmlspecialchars($email) . "</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = "From: GitHub Updates <no-reply@example.com>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Simulate email log instead of real mail()
        $logEntry = "=== GITHUB UPDATE EMAIL ===\n";
        $logEntry .= "To: $email\n";
        $logEntry .= "Subject: Latest GitHub Updates - " . date('Y-m-d H:i') . "\n";
        $logEntry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $logEntry .= "Content: " . strip_tags($htmlContent) . "\n\n";
        
        if (file_put_contents(__DIR__ . '/sent_emails.log', $logEntry, FILE_APPEND | LOCK_EX)) {
            if (!defined('STDIN')) {
                echo "<p style='color: green;'>✅ [Simulated GitHub Update] Sent to " . htmlspecialchars($email) . "</p>";
            }
            $updateCount++;
        }
        
        // For production:
        // mail($email, "Latest GitHub Updates - " . date('Y-m-d H:i'), $message, $headers);
        
        // Small delay to prevent overwhelming (remove in production with real mail server)
        usleep(100000); // 0.1 second delay
    }
    
    if (!defined('STDIN')) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 20px 0; border-radius: 8px;'>
            <strong>✅ Update Complete!</strong><br>
            Successfully sent GitHub updates to $updateCount subscribers.
        </div>";
    }
    
    return $updateCount > 0;
}

// Utility function to get subscriber count
function getSubscriberCount() {
    $file = __DIR__ . '/registered_emails.txt';
    
    if (!file_exists($file)) {
        return 0;
    }
    
    $emails = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$emails) {
        return 0;
    }
    
    return count(array_filter($emails, function($email) {
        return !empty(trim($email)) && filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    }));
}

// Utility function to sanitize user input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to handle AJAX requests properly
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Clean up expired verification codes on each function load
cleanExpiredCodes();

?>