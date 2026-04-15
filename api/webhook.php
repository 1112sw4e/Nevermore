<?php
// Telegram Bot для 2FA Nevermore - Vercel Webhook версия

define('BOT_TOKEN', '8782060933:AAHoMPQ0ctRQ3NHs03_rSL3CcSdAmD8h9uA');

// Подключение к БД
$db_host = 'sql306.infinityfree.com';
$db_name = 'if0_40983697_starbase';
$db_user = 'if0_40983697';
$db_pass = 'b663zrtJVWQFpc';

function apiRequest($method, $data) {
    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
} catch(PDOException $e) {
    exit;
}

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$message = $update['message'] ?? null;
if (!$message) exit;

$chat_id = $message['chat']['id'];
$text = trim($message['text'] ?? '');
$tg_user_id = $message['from']['id'] ?? 0;

if ($text === '/start') {
    apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "👋 Привет!\n\n📝 Привяжи: /link НИКНЕЙМ\n🔐 Код: /auth"]);
}
elseif ($text === '/help') {
    apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "📋 Команды:\n/link НИКНЕЙМ\n/auth\n/unlink"]);
}
elseif (strpos($text, '/link') === 0) {
    $username = trim(str_replace('/link', '', $text));
    if (empty($username)) {
        apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ /link НИКНЕЙМ"]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE users SET tg_user_id = ? WHERE username = ?");
            $stmt->execute([$tg_user_id, $username]);
            apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ @{$username} привязан!"]);
        } else {
            apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Не найден"]);
        }
    }
}
elseif ($text === '/auth') {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE tg_user_id = ?");
    $stmt->execute([$tg_user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Сначала /link НИКНЕЙМ"]);
    } else {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("UPDATE users SET tg_2fa_code = ? WHERE tg_user_id = ?");
        $stmt->execute([$code, $tg_user_id]);
        apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "🔐 Код для @{$user['username']}:\n\n📌 $code", 'parse_mode' => 'HTML']);
    }
}
elseif ($text === '/unlink') {
    $stmt = $pdo->prepare("UPDATE users SET tg_user_id = NULL WHERE tg_user_id = ?");
    $stmt->execute([$tg_user_id]);
    apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Отвязано"]);
}
