<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * 定位 Typecho 根目录
 */
define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 3));
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');
define('__TYPECHO_THEME_DIR__', '/usr/themes');
define('__TYPECHO_ADMIN_DIR__', '/admin/');

// ✅ 必须加载数据库配置（你之前缺了这个！）
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

// ✅ 加载 Typecho 核心
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

// ✅ 初始化 Typecho
Typecho\Common::init();

// ✅ 加载全部 Widget（含数据库、Options、插件配置）
Typecho_Widget::widget('Widget_Init');

// ✅ 获取插件配置
$settings = Helper::options()->plugin('AIAbstractor');

if (!$settings) {
    http_response_code(500);
    echo json_encode(['error' => 'Plugin not configured']);
    exit;
}

$apiBase = rtrim($settings->apiBase, '/');
$apiKey = trim($settings->apiKey);
$model = trim($settings->model);
$temperature = floatval($settings->temperature);
$maxTokens = intval($settings->maxTokens);

// 只允许 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 读取 JSON 输入
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$q = trim($data['q'] ?? '');
if (!$q) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing q']);
    exit;
}

// 使用前端或默认模型
$useModel = trim($data['model'] ?? $model);

// 构建 OpenAI 请求
$payload = [
    'model' => $useModel,
    'messages' => [
        ['role' => 'system', 'content' => '你是一个擅长中文写作的助手。'],
        ['role' => 'user', 'content' => $q]
    ],
    'temperature' => max(0, min(2, $temperature)),
    'max_tokens' => max(1, $maxTokens),
];

$ch = curl_init($apiBase . '/chat/completions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$error = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => $error]);
    exit;
}

$result = json_decode($response, true);

if ($code >= 400 || !is_array($result)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Bad response from OpenAI',
        'status' => $code,
        'body' => $response
    ]);
    exit;
}

echo json_encode(['text' => $result['choices'][0]['message']['content']], JSON_UNESCAPED_UNICODE);
exit;
