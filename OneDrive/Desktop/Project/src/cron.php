<?php
require 'functions.php';

// Run only from CLI or CRON (optional safety)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Forbidden: This script must be run via CRON or CLI.\n");
}

// Log start
echo "📦 [".date('Y-m-d H:i:s')."] Starting GitHub update broadcast...\n";

// Clean expired codes (optional, good hygiene)
cleanExpiredCodes();

// Fetch timeline
$events = fetchGitHubTimeline();

if (!$events) {
    echo "❌ Failed to fetch GitHub events.\n";
    exit;
}

// Format HTML content
$html = formatGitHubData($events);

// Load subscribers
$subscribersFile = __DIR__ . '/registered_emails.txt';
if (!file_exists($subscribersFile)) {
    echo "⚠️ No subscribers file found.\n";
    exit;
}

$subscribers = array_filter(array_map('trim', file($subscribersFile)));

if (empty($subscribers)) {
    echo "⚠️ No subscribers to email.\n";
    exit;
}

$count = 0;
foreach ($subscribers as $email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

    $baseUrl = 'https://yourdomain.com'; // 🔁 Update to your actual domain
    $unsubscribeLink = $baseUrl . '/unsubscribe.php?email=' . urlencode($email);

    $subject = "📊 GitHub Timeline Updates - " . date("M d, H:i");
    $body = "
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: sans-serif;'>
        <div style='background:#0a74da;color:#fff;padding:16px;text-align:center;'>
            <h2>GitHub Timeline Updates</h2>
        </div>
        <div style='padding:20px;'>$html</div>
        <div style='background:#f8f8f8;padding:12px;text-align:center;font-size:12px;color:#555;'>
            Don’t want these emails? <a href='$unsubscribeLink'>Unsubscribe</a><br>
            Sent to: $email
        </div>
    </body>
    </html>";

    // Simulated mail log (for production, use mail())
    $log = "=== EMAIL TO $email ===\nSubject: $subject\n--- HTML STRIPPED ---\n" . strip_tags($html) . "\n\n";
    file_put_contents(__DIR__ . '/sent_emails.log', $log, FILE_APPEND | LOCK_EX);

    // Uncomment for real sending:
    /*
    $headers = "From: GitHub Updates <no-reply@yourdomain.com>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    mail($email, $subject, $body, $headers);
    */

    echo "✅ Sent to $email\n";
    $count++;
    usleep(100000); // 0.1s delay to prevent throttling
}

echo "✅ Finished. $count emails sent.\n";
