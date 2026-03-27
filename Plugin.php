<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * AIAbstractor - 基于 OpenAI 标准接口的文章摘要插件
 *
 * @package AIAbstractor
 * @author ipan233
 * @version 2.0.0
 * @link https://github.com/ipan233/AIAbstractor
 */
class AIAbstractor_Plugin implements Typecho_Plugin_Interface
{
    /** @var bool 防止 CSS 被 header/footer 双重注入 */
    private static $cssInjected = false;

    /** 允许前端覆盖的模型白名单 */
    const ALLOWED_MODELS = [
        'gpt-4o-mini',
        'gpt-4o',
        'o4-mini',
        'gpt-3.5-turbo',
    ];

    // -------------------------------------------------------------------------
    // 插件生命周期
    // -------------------------------------------------------------------------

    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->header = ['AIAbstractor_Plugin', 'header'];
        Typecho_Plugin::factory('Widget_Archive')->footer = ['AIAbstractor_Plugin', 'footer'];
        Helper::addAction('ai-abstractor', 'AIAbstractor_Action');
        return _t('AIAbstractor 插件已启用');
    }

    public static function deactivate()
    {
        Helper::removeAction('ai-abstractor');
        return _t('AIAbstractor 插件已停用');
    }

    // -------------------------------------------------------------------------
    // 插件配置表单
    // -------------------------------------------------------------------------

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $fields = [
            new Typecho_Widget_Helper_Form_Element_Text(
                'apiBase', null, 'https://api.openai.com/v1',
                _t('OpenAI API Base'),
                _t('例如：https://api.openai.com/v1 或企业/代理网关 v1 路径')
            ),
            new Typecho_Widget_Helper_Form_Element_Password(
                'apiKey', null, '',
                _t('OpenAI API Key'),
                _t('不会在前端暴露，后端代为请求')
            ),
            new Typecho_Widget_Helper_Form_Element_Text(
                'model', null, 'gpt-5.3',
                _t('默认模型'),
                _t('例如：gpt-4o-mini, gpt-4o, o4-mini 等')
            ),
            new Typecho_Widget_Helper_Form_Element_Text(
                'temperature', null, '0.3',
                _t('Temperature'),
                _t('0-2 浮点数，控制生成随机性')
            ),
            new Typecho_Widget_Helper_Form_Element_Text(
                'maxTokens', null, '256',
                _t('最大 Tokens'),
                _t('响应上限，建议 64-512')
            ),
            new Typecho_Widget_Helper_Form_Element_Text(
                'wordLimit', null, '720',
                _t('提取源文本长度上限'),
                _t('从页面采集的最大字符数')
            ),
            new Typecho_Widget_Helper_Form_Element_Text(
                'postSelector', null, '#article-container',
                _t('文章容器选择器'),
                _t('默认 #article-container')
            ),
            new Typecho_Widget_Helper_Form_Element_Text(
                'rateLimit', null, '5',
                _t('频率限制（次/分钟）'),
                _t('每个 IP 每分钟最多调用次数，0 表示不限制')
            ),
            (new Typecho_Widget_Helper_Form_Element_Radio(
                'autoInject',
                ['1' => _t('开启'), '0' => _t('关闭')],
                '1',
                _t('自动插入前端资源')
            )),
        ];

        foreach ($fields as $field) {
            // required 验证仅对关键字段
            if (in_array($field->name, ['apiBase', 'apiKey', 'model'])) {
                $field->addRule('required', _t($field->name . ' 不能为空'));
            }
            $form->addInput($field);
        }
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    // -------------------------------------------------------------------------
    // 前端资源注入
    // -------------------------------------------------------------------------

    /**
     * 判断当前请求是否应跳过资源注入。
     */
    private static function shouldSkipInjection()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        foreach (['/admin/', '/feed', '/rss'] as $path) {
            if (strpos($uri, $path) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取插件设置，autoInject 未开启时返回 null。
     */
    private static function getActiveSettings()
    {
        $settings = Helper::options()->plugin('AIAbstractor');
        if (!$settings || !isset($settings->autoInject) || $settings->autoInject !== '1') {
            return null;
        }
        return $settings;
    }

    public static function header()
    {
        if (self::shouldSkipInjection()) return;
        $settings = self::getActiveSettings();
        if (!$settings) return;

        $css = self::assetUrl('css/ai_abstractor.css');
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css) . '">' . "\n";
        self::$cssInjected = true;
    }

    public static function footer()
    {
        if (self::shouldSkipInjection()) return;
        $settings = self::getActiveSettings();
        if (!$settings) return;

        // 若主题没有调用 header()，在 footer 补注入 CSS（兜底，不重复）
        if (!self::$cssInjected) {
            $css = self::assetUrl('css/ai_abstractor.css');
            echo '<link rel="stylesheet" href="' . htmlspecialchars($css) . '">' . "\n";
        }

        $js = self::assetUrl('js/ai_abstractor.js');

        // 构建传给前端的配置（不含 apiKey，安全）
        $config = [
            'apiEndpoint' => rtrim(Helper::options()->pluginUrl, '/') . '/AIAbstractor/api.php',
            'model'        => isset($settings->model)       ? $settings->model       : 'gpt-5.3',
            'postSelector' => isset($settings->postSelector) ? $settings->postSelector : '#article-container',
            'wordLimit'    => intval(isset($settings->wordLimit) ? $settings->wordLimit : 720),
        ];

        echo '<script>window.AIAbstractorConfigOverrides=' .
            json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
            ';</script>' . "\n";
        echo '<script src="' . htmlspecialchars($js) . '"></script>' . "\n";
    }

    // -------------------------------------------------------------------------
    // 工具方法
    // -------------------------------------------------------------------------

    private static function assetUrl($relativePath)
    {
        $pluginUrl = rtrim(Helper::options()->pluginUrl, '/') . '/AIAbstractor';
        return $pluginUrl . '/assets/' . $relativePath;
    }
}

// =============================================================================
// Action：处理前端 AJAX 请求
// =============================================================================

class AIAbstractor_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /** 简单内存级请求频率表（同进程内有效，适合大多数部署） */
    private static $rateBucket = [];

    public function action()
    {
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');

        if ($this->request->isPost()) {
            $this->serve();
        } else {
            $this->response->setStatus(405);
            $this->response->throwJson(['error' => 'Method Not Allowed', 'allowed' => 'POST']);
        }
    }

    // -------------------------------------------------------------------------
    // 核心处理逻辑
    // -------------------------------------------------------------------------

    private function serve()
    {
        // 1. 读取并验证插件配置
        $settings    = Helper::options()->plugin('AIAbstractor');
        $apiBase     = isset($settings->apiBase)     ? rtrim($settings->apiBase, '/')       : '';
        $apiKey      = isset($settings->apiKey)      ? trim($settings->apiKey)               : '';
        $model       = isset($settings->model)       ? trim($settings->model)                : 'gpt-5.3';
        $temperature = isset($settings->temperature) ? floatval($settings->temperature)     : 0.3;
        $maxTokens   = isset($settings->maxTokens)   ? intval($settings->maxTokens)         : 256;
        $rateLimit   = isset($settings->rateLimit)   ? intval($settings->rateLimit)         : 5;

        // 参数边界限制
        $temperature = max(0.0, min(2.0, $temperature));
        $maxTokens   = max(1, min(4096, $maxTokens));

        if (!$apiBase || !$apiKey) {
            $this->jsonError(500, 'Server not configured');
            return;
        }

        // 2. 频率限制
        if ($rateLimit > 0 && !$this->checkRateLimit($rateLimit)) {
            $this->jsonError(429, 'Too Many Requests');
            return;
        }

        // 3. 解析请求体
        $raw  = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonError(400, 'Invalid JSON');
            return;
        }

        $q           = isset($json['q'])     ? trim($json['q'])     : '';
        $clientModel = isset($json['model']) ? trim($json['model']) : null;

        if (!$q) {
            $this->jsonError(400, 'Missing q');
            return;
        }

        // 4. model 白名单校验（前端传入必须在允许列表内，否则降级为配置值）
        $useModel = ($clientModel && in_array($clientModel, AIAbstractor_Plugin::ALLOWED_MODELS, true))
            ? $clientModel
            : $model;

        // 5. 调用 AI 接口
        [$text, $errMsg] = $this->callOpenAI($apiBase, $apiKey, $useModel, $temperature, $maxTokens, $q);

        if ($errMsg !== null) {
            $this->jsonError(500, $errMsg);
            return;
        }

        $this->response->throwJson(['text' => $text]);
    }

    // -------------------------------------------------------------------------
    // OpenAI 请求封装
    // -------------------------------------------------------------------------

    /**
     * 向 OpenAI 兼容接口发送请求。
     *
     * @return array{0: string|null, 1: string|null} [text, errorMessage]
     */
    private function callOpenAI(
        $apiBase,
        $apiKey,
        $model,
        $temperature,
        $maxTokens,
        $userContent
    ) {
        $payload = [
            'model'      => $model,
            'messages'   => [
                ['role' => 'system', 'content' => '你是一个擅长中文写作的助手。'],
                ['role' => 'user',   'content' => $userContent],
            ],
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'stream'      => false,
        ];

        $ch = curl_init($apiBase . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST          => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT       => 30,
            CURLOPT_HTTPHEADER    => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return [null, 'Curl error: ' . $err];
        }

        $data = json_decode($resp, true);

        if ($code >= 400 || !is_array($data)) {
            return [null, 'Bad response from AI provider (HTTP ' . $code . ')'];
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        return [$text, null];
    }

    // -------------------------------------------------------------------------
    // 频率限制（基于 IP，会话级内存桶）
    // -------------------------------------------------------------------------

    private function checkRateLimit($maxPerMinute)
    {
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $now = time();
        $key = $ip;

        if (!isset(self::$rateBucket[$key])) {
            self::$rateBucket[$key] = ['count' => 0, 'reset' => $now + 60];
        }

        // 如果时间窗口已过，重置计数
        if ($now >= self::$rateBucket[$key]['reset']) {
            self::$rateBucket[$key] = ['count' => 0, 'reset' => $now + 60];
        }

        self::$rateBucket[$key]['count']++;
        return self::$rateBucket[$key]['count'] <= $maxPerMinute;
    }

    // -------------------------------------------------------------------------
    // 工具方法
    // -------------------------------------------------------------------------

    private function jsonError($status, $message)
    {
        $this->response->setStatus($status);
        $this->response->throwJson(['error' => $message]);
    }
}
