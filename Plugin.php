<?php
<<<<<<< HEAD
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


=======
/**
 * AI摘要工具
 *
 * 一个为您的Typecho博客文章生成AI摘要的插件，支持自定义GPT API。
 *
 * @package AIAbstractor
 * @author Your Name Here (请自行替换为您的名字)
 * @version 1.0.1 // 版本号更新，以反映安全修复和功能改进
 * @link https://github.com/YourGitHub/AIAbstractor (请自行替换为您的GitHub仓库地址，可选)
 */
class AIAbstractor_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法
     */
    public static function activate()
    {
        // 注册在页面头部输出CSS和配置的钩子
        Typecho_Plugin::factory('Widget_Archive')->header = array('AIAbstractor_Plugin', 'header');
        // 注册在页面底部输出JS的钩子
        Typecho_Plugin::factory('Widget_Archive')->footer = array('AIAbstractor_Plugin', 'footer');
        // 注册Action Hook来处理API请求
        Typecho_Plugin::factory('Widget_Service')->onService = array('AIAbstractor_Plugin', 'action');
        return 'AI摘要工具已激活！';
    }

    /**
     * 禁用插件方法
     */
    public static function deactivate()
    {
        return 'AI摘要工具已禁用！';
    }

    /**
     * 获取插件配置面板
     *
     * @param Typecho_Widget_Helper_Form $form 配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $appName = new Typecho_Widget_Helper_Form_Element_Text(
            'appName',
            NULL,
            'AI摘要工具',
            _t('应用名称'),
            _t('在博客页面上显示的AI摘要工具的名称。')
        );
        $form->addInput($appName);

        $classNamePrefix = new Typecho_Widget_Helper_Form_Element_Text(
            'classNamePrefix',
            NULL,
            'ai-abstractor',
            _t('类名/ID前缀'),
            _t('用于HTML元素的类名和ID前缀。请确保此值与JS和CSS文件中的前缀一致。')
        );
        $form->addInput($classNamePrefix);

        $apiEndpoint = new Typecho_Widget_Helper_Form_Element_Text(
            'apiEndpoint',
            NULL,
            'https://hub.onmicrosoft.cn/chat/stream', // 默认API端点
            _t('GPT API 端点'),
            _t('您的个人GPT API的流式输出端点。')
        );
        $form->addInput($apiEndpoint);

        $apiKey = new Typecho_Widget_Helper_Form_Element_Text(
            'apiKey',
            NULL,
            '', // 默认API Key为空
            _t('GPT API Key'),
            _t('您的个人GPT API密钥 (如果您的API需要)。')
        );
        $form->addInput($apiKey);

        $postSelector = new Typecho_Widget_Helper_Form_Element_Text(
            'postSelector',
            NULL,
            '.post-content', // 默认文章内容选择器
            _t('文章内容选择器'),
            _t('您的博客文章内容的CSS选择器，例如 ".post-body", ".entry-content" 等。')
        );
        $form->addInput($postSelector);

        $wordLimit = new Typecho_Widget_Helper_Form_Element_Text(
            'wordLimit',
            NULL,
            '100', // 默认摘要字数限制
            _t('摘要字数限制'),
            _t('生成的摘要的最大字数。')
        );
        $form->addInput($wordLimit);

        $postURL = new Typecho_Widget_Helper_Form_Element_Text(
            'postURL',
            NULL,
            '', // 默认URL匹配规则为空
            _t('URL匹配规则'),
            _t('可选：匹配文章URL的规则，例如 "/posts/*" 。留空表示所有页面都启用。')
        );
        $form->addInput($postURL);
    }

    /**
     * 个人用户的配置面板 (本插件不需要，留空)
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 在页面头部输出CSS和AIAbstractorConfig配置
     */
    public static function header()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('AIAbstractor');

        // 检查是否需要加载CSS和JS
        if (self::shouldLoadResources($options->postURL)) {
			// 输出CSS文件
			$pluginUrl = Helper::options()->pluginUrl . '/AIAbstractor';
			echo '<link rel="stylesheet" href="' . Typecho_Common::url('assets/css/ai_abstractor.css', $pluginUrl) . '">';

            // 输出AIAbstractorConfig配置 (必须在JS文件之前)
            echo '<script type="text/javascript">';
            echo 'const AIAbstractorConfig = ' . json_encode([
                'appName' => $options->appName,
                'classNamePrefix' => $options->classNamePrefix,
                'apiEndpoint' => $options->apiEndpoint,
                'postSelector' => $options->postSelector,
                'wordLimit' => intval($options->wordLimit),
                'postURL' => empty($options->postURL) ? null : $options->postURL,
                'apiProxyUrl' => Typecho_Common::url('action/AIAbstractor', Typecho_Router::url('index', [], Typecho_Widget::widget('Widget_Options')->index)),
            ]) . ';';
            echo '</script>';
        }
    }

    /**
     * 在页面底部输出JS文件
     */
    public static function footer()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('AIAbstractor');
        // 检查是否需要加载CSS和JS
        if (self::shouldLoadResources($options->postURL)) {
			// 输出JS文件
			$pluginUrl = Helper::options()->pluginUrl . '/AIAbstractor';
			echo '<script type="text/javascript" src="' . Typecho_Common::url('assets/js/ai_abstractor.js', $pluginUrl) . '"></script>';
        }
    }

    /**
     * 判断是否应该加载CSS和JS资源
     *
     * @param string $postURL 配置的URL匹配规则
     * @return bool
     */
    private static function shouldLoadResources($postURL)
    {
        if (empty($postURL)) {
            return true; // 如果没有设置URL规则，则所有页面都加载
        }

        $currentURL = Typecho_Router::url('index', [], Typecho_Widget::widget('Widget_Options')->index);
        
        // 简单通配符匹配 (如 /posts/*)
        $pattern = str_replace(['*', '/'], ['.*', '\/'], $postURL);
        return preg_match('/^' . $pattern . '$/', $currentURL);
    }

    /**
     * 处理后端API请求
     */
    public static function action()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('AIAbstractor');
        $request = Typecho_Request::getInstance();

        // 检查请求是否是我们的AI摘要API请求
        if ($request->isPost() && $request->get('action') == 'AIAbstractor') {
            // 获取前端发送的文章内容和字数限制
            $postContent = $request->get('postContent');
            $wordLimit = $request->get('wordLimit');

            // 验证输入
            if (empty($postContent) || empty($wordLimit)) {
                Typecho_Response::alert('Missing required parameters.', 500);
                exit;
            }

            $apiKey = $options->apiKey;
            $apiEndpoint = $options->apiEndpoint;

            if (empty($apiKey) || empty($apiEndpoint)) {
                Typecho_Response::alert('AIAbstractor plugin not configured. Missing API Key or Endpoint.', 500);
                exit;
            }

            // 构建GPT API请求体 (这里只是一个示例，具体取决于您的GPT API接口)
            // 通常会包括消息数组，模型等
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant. Summarize the following text within ' . $wordLimit . ' words.'],
                ['role' => 'user', 'content' => $postContent]
            ];

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ];

            $payload = json_encode([
                'model' => 'gpt-3.5-turbo', // 可以从插件配置中获取或根据需要设置
                'messages' => $messages,
                'max_tokens' => $wordLimit * 2, // 粗略估算，允许的响应token数
                'stream' => true // 确保流式输出
            ]);

            // 使用cURL发送请求到GPT API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 根据需要设置，生产环境建议开启SSL验证

            // 设置为流式输出
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
                echo $data;
                ob_flush();
                flush();
                return strlen($data);
            });

            curl_exec($ch);

            if (curl_errno($ch)) {
                Typecho_Response::alert('GPT API Request Error: ' . curl_error($ch), 500);
            }

            curl_close($ch);
            exit; // 确保不输出其他Typecho内容
        }
    }
}
>>>>>>> 7ee9f6c3da8f1a653a5950304bb709c35c4025fb
