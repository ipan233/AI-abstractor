<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * AIAbstractor - 基于 OpenAI 标准接口的文章摘要插件
 * 
 * @package AIAbstractor
 * @author Your Name
 * @version 1.0.0
 * @link https://github.com/your/repo
 */

class AIAbstractor_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->header = array('AIAbstractor_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('AIAbstractor_Plugin', 'footer');

        // 注册 Action（Typecho 标准方式）
        Helper::addAction('ai-abstractor', 'AIAbstractor_Action');
        return _t('AIAbstractor 插件已启用');
    }

    public static function deactivate()
    {
        Helper::removeAction('ai-abstractor');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $apiBase = new Typecho_Widget_Helper_Form_Element_Text(
            'apiBase', NULL, 'https://api.openai.com/v1', _t('OpenAI API Base'),
            _t('例如：https://api.openai.com/v1 或企业/代理网关 v1 路径')
        );
        $form->addInput($apiBase->addRule('required', _t('API Base 不能为空')));

        $apiKey = new Typecho_Widget_Helper_Form_Element_Password(
            'apiKey', NULL, '', _t('OpenAI API Key'),
            _t('不会在前端暴露，后端代为请求')
        );
        $form->addInput($apiKey->addRule('required', _t('API Key 不能为空')));

        $model = new Typecho_Widget_Helper_Form_Element_Text(
            'model', NULL, 'gpt-4o-mini', _t('模型名'), _t('例如：gpt-4o-mini, gpt-4o, o4-mini 等')
        );
        $form->addInput($model->addRule('required', _t('模型名不能为空')));

        $temperature = new Typecho_Widget_Helper_Form_Element_Text(
            'temperature', NULL, '0.3', _t('Temperature'), _t('0-2 浮点数')
        );
        $form->addInput($temperature);

        $maxTokens = new Typecho_Widget_Helper_Form_Element_Text(
            'maxTokens', NULL, '256', _t('最大 Tokens'), _t('响应上限，建议 64-512')
        );
        $form->addInput($maxTokens);

        $wordLimit = new Typecho_Widget_Helper_Form_Element_Text(
            'wordLimit', NULL, '720', _t('提取源文本长度上限'), _t('从页面采集的最大文本长度')
        );
        $form->addInput($wordLimit);

        $postSelector = new Typecho_Widget_Helper_Form_Element_Text(
            'postSelector', NULL, '#article-container', _t('文章容器选择器'), _t('默认 #article-container')
        );
        $form->addInput($postSelector);

        $autoInject = new Typecho_Widget_Helper_Form_Element_Radio(
            'autoInject', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('自动插入前端资源'))
        ;
        $form->addInput($autoInject);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    public static function header()
    {
        $options = Helper::options();
        $pluginUrl = rtrim($options->pluginUrl, '/') . '/AIAbstractor';
        $css = $pluginUrl . '/assets/css/ai_abstractor.css';
        $settings = Helper::options()->plugin('AIAbstractor');
        if (!isset($settings->autoInject) || $settings->autoInject !== '1') {
            return;
        }
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css) . '">';
    }

    public static function footer()
    {
        $options = Helper::options();
        $pluginUrl = rtrim($options->pluginUrl, '/') . '/AIAbstractor';
        $css = $pluginUrl . '/assets/css/ai_abstractor.css';
        $js = $pluginUrl . '/assets/js/ai_abstractor.js';
        $settings = Helper::options()->plugin('AIAbstractor');
        if (!isset($settings->autoInject) || $settings->autoInject !== '1') {
            return;
        }

        $inject = array(
            'apiEndpoint' => rtrim($options->index, '/') . '/action/ai-abstractor',
            'model' => isset($settings->model) ? $settings->model : 'gpt-4o-mini',
            'postSelector' => isset($settings->postSelector) ? $settings->postSelector : '#article-container',
            'wordLimit' => isset($settings->wordLimit) ? intval($settings->wordLimit) : 720
        );

        // 兜底：若主题未调用 $this->header()，在 footer 也注入 CSS，避免无样式
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css) . '">';
        echo '<script>window.AIAbstractorConfigOverrides=' . json_encode($inject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
        echo '<script src="' . htmlspecialchars($js) . '"></script>';
    }
}

class AIAbstractor_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $this->on($this->request);
    }

    public function on($request)
    {
        // 仅允许 POST
        if ($this->request->isPost()) {
            $this->serve();
        } else {
            $this->response->setStatus(405);
            $this->response->throwJson(array('error' => 'Method Not Allowed'));
        }
    }

    private function serve()
    {
        $settings = Helper::options()->plugin('AIAbstractor');
        $apiBase = isset($settings->apiBase) ? rtrim($settings->apiBase, '/') : '';
        $apiKey = isset($settings->apiKey) ? trim($settings->apiKey) : '';
        $model = isset($settings->model) ? trim($settings->model) : 'gpt-4o-mini';
        $temperature = isset($settings->temperature) ? floatval($settings->temperature) : 0.3;
        $maxTokens = isset($settings->maxTokens) ? intval($settings->maxTokens) : 256;

        if (!$apiBase || !$apiKey) {
            $this->response->setStatus(500);
            $this->response->throwJson(array('error' => 'Server not configured'));
        }

        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $q = isset($json['q']) ? trim($json['q']) : '';
        $clientModel = isset($json['model']) ? trim($json['model']) : null;
        if (!$q) {
            $this->response->setStatus(400);
            $this->response->throwJson(array('error' => 'Missing q'));
        }

        $useModel = $clientModel ?: $model;

        $endpoint = $apiBase . '/chat/completions';
        $payload = array(
            'model' => $useModel,
            'messages' => array(
                array('role' => 'system', 'content' => '你是一个擅长中文写作的助手。'),
                array('role' => 'user', 'content' => $q)
            ),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false
        );

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $this->response->setStatus(500);
            $this->response->throwJson(array('error' => 'Curl error: ' . $err));
        }

        $data = json_decode($resp, true);
        if ($code >= 400 || !is_array($data)) {
            $this->response->setStatus(500);
            $this->response->throwJson(array('error' => 'Bad response from OpenAI', 'code' => $code));
        }

        $text = '';
        if (isset($data['choices'][0]['message']['content'])) {
            $text = $data['choices'][0]['message']['content'];
        }

        $this->response->throwJson(array('text' => $text));
    }
}
