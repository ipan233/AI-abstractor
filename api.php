<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * 定位 Typecho 根目录（插件位于 /usr/plugins/AIAbstractor/）
 */
define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 3));
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');
define('__TYPECHO_THEME_DIR__', '/usr/themes');
define('__TYPECHO_ADMIN_DIR__', '/admin/');

// 加载数据库配置与 Typecho 核心
if (!file_exists(__TYPECHO_ROOT_DIR__ . '/config.inc.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Typecho config not found']);
    exit;
}
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
Typecho\Common::init();
Typecho_Widget::widget('Widget_Init');

// 获取插件配置
$settings = Helper::options()->plugin('AIAbstractor');
if (!$settings) {
    http_response_code(500);
    echo json_encode(['error' => 'Plugin not configured']);
    exit;
}

$apiBase     = rtrim($settings->apiBase, '/');
$apiKey      = trim($settings->apiKey);
$model       = trim($settings->model);
$temperature = floatval($settings->temperature);
$maxTokens   = intval($settings->maxTokens);
$rateLimit   = isset($settings->rateLimit) ? intval($settings->rateLimit) : 5;

// 参数边界
$temperature = max(0, min(2, $temperature));
$maxTokens   = max(1, min(4096, $maxTokens));

// 只允许 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 频率限制（基于 session）
if ($rateLimit > 0) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $now = time();
    if (!isset($_SESSION['ai_rate'])) {
        $_SESSION['ai_rate'] = ['count' => 0, 'reset' => $now + 60];
    }
    if ($now >= $_SESSION['ai_rate']['reset']) {
        $_SESSION['ai_rate'] = ['count' => 0, 'reset' => $now + 60];
    }
    $_SESSION['ai_rate']['count']++;
    if ($_SESSION['ai_rate']['count'] > $rateLimit) {
        http_response_code(429);
        echo json_encode(['error' => 'Too Many Requests']);
        exit;
    }
}

// 读取并验证 JSON 输入
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$q = isset($data['q']) ? trim($data['q']) : '';
if (!$q) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing q']);
    exit;
}

// model 白名单（前端可指定，但必须在允许列表内）
$allowedModels = ['gpt-4o-mini', 'gpt-4o', 'o4-mini', 'gpt-3.5-turbo', 'gpt-5.2', 'gpt-5.3'];
$clientModel   = isset($data['model']) ? trim($data['model']) : null;
$useModel      = ($clientModel && in_array($clientModel, $allowedModels, true)) ? $clientModel : $model;

// 调用 AI 接口
$payload = [
    'model'       => $useModel,
    'messages'    => [
        ['role' => 'system', 'content' => '你是一个擅长中文写作的助手。'],
        ['role' => 'user',   'content' => $q],
    ],
    'temperature' => $temperature,
    'max_tokens'  => $maxTokens,
    'stream'      => false,
];

$ch = curl_init($apiBase . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_SSL_VERIFYPEER => true,
]);

$resp  = curl_exec($ch);
$err   = curl_error($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl error: ' . $err]);
    exit;
}

$result = json_decode($resp, true);
if ($code >= 400 || !is_array($result)) {
    http_response_code(500);
    echo json_encode(['error' => 'Bad response from AI provider (HTTP ' . $code . ')']);
    exit;
}

$text = isset($result['choices'][0]['message']['content'])
    ? $result['choices'][0]['message']['content']
    : '';

echo json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
exit;
