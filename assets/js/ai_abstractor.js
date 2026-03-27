/**
 * AIAbstractor - 基于 OpenAI 标准接口的文章摘要插件
 * @link https://github.com/ipan233/AIAbstractor
 */
(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // 配置初始化
  // ---------------------------------------------------------------------------

  /** 默认配置，所有字段均可被后台注入的 AIAbstractorConfigOverrides 覆盖 */
  var DEFAULT_CONFIG = {
    appName:         'AI摘要',
    classNamePrefix: 'ai-abstractor',
    apiEndpoint:     '/action/ai-abstractor',
    postSelector:    '#article-container',
    wordLimit:       720,
    postURL:         undefined,
    model:           'gpt-4o-mini',
    /** localStorage 缓存有效期（毫秒），默认 7 天 */
    cacheMaxAge:     7 * 24 * 60 * 60 * 1000,
    /** 调试模式：true 时才输出 console.log */
    debug:           false,
  };

  if (!window.AIAbstractorConfig) {
    window.AIAbstractorConfig = Object.assign({}, DEFAULT_CONFIG);
  }

  if (window.AIAbstractorConfigOverrides) {
    try {
      Object.assign(window.AIAbstractorConfig, window.AIAbstractorConfigOverrides);
    } catch (e) {
      console.warn('[AIAbstractor] 合并后台配置失败', e);
    }
  }

  var cfg = window.AIAbstractorConfig;

  // ---------------------------------------------------------------------------
  // 工具函数
  // ---------------------------------------------------------------------------

  function log() {
    if (cfg.debug) {
      console.log.apply(console, ['[' + cfg.appName + ']'].concat(Array.prototype.slice.call(arguments)));
    }
  }

  // ---------------------------------------------------------------------------
  // LocalStorage 缓存（带过期时间）
  // ---------------------------------------------------------------------------

  /**
   * 用文章 pathname+hash 作为唯一 key，忽略查询参数。
   */
  function getArticleKey() {
    var url = new URL(window.location.href);
    return 'ai_abstractor_v2_' + url.pathname + url.hash;
  }

  function loadCachedAbstract() {
    try {
      var raw = localStorage.getItem(getArticleKey());
      if (!raw) return null;
      var data = JSON.parse(raw);
      if (!data || !data.text || !data.ts) return null;
      if (Date.now() - data.ts > cfg.cacheMaxAge) {
        localStorage.removeItem(getArticleKey());
        return null;
      }
      return data.text;
    } catch (e) {
      return null;
    }
  }

  function saveAbstract(text) {
    try {
      localStorage.setItem(getArticleKey(), JSON.stringify({ text: text, ts: Date.now() }));
      log('摘要已缓存到本地');
    } catch (e) {
      console.warn('[' + cfg.appName + '] 缓存写入失败', e);
    }
  }

  // ---------------------------------------------------------------------------
  // 文章文本提取
  // ---------------------------------------------------------------------------

  function getArticleText() {
    try {
      var container = document.querySelector(cfg.postSelector);
      if (!container) {
        console.warn('[' + cfg.appName + '] 找不到文章容器：' + cfg.postSelector);
        return '';
      }
      var title   = document.title || '';
      // 直接用 innerText 获取纯文本，比逐标签拼接更准确、效率更高
      var rawText = container.innerText || '';
      // 过滤 URL
      rawText = rawText.replace(/https?:\/\/[^\s]+/g, '');
      // 合并标题 + 正文，按字符数截断
      var combined = (title + ' ' + rawText).replace(/\s+/g, ' ').trim();
      return combined.substring(0, cfg.wordLimit);
    } catch (e) {
      console.error('[' + cfg.appName + '] 文章文本提取失败', e);
      return '';
    }
  }

  // ---------------------------------------------------------------------------
  // UI 构建
  // ---------------------------------------------------------------------------

  var ROBOT_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20" height="20"><path fill="currentColor" d="M34.717885,5.03561087 C36.12744,5.27055371 37.079755,6.60373651 36.84481,8.0132786 L35.7944,14.3153359 L38.375,14.3153359 C43.138415,14.3153359 47,18.1768855 47,22.9402569 L47,34.4401516 C47,39.203523 43.138415,43.0650727 38.375,43.0650727 L9.625,43.0650727 C4.861585,43.0650727 1,39.203523 1,34.4401516 L1,22.9402569 C1,18.1768855 4.861585,14.3153359 9.625,14.3153359 L12.2056,14.3153359 L11.15519,8.0132786 C10.920245,6.60373651 11.87256,5.27055371 13.282115,5.03561087 C14.69167,4.80066802 16.024865,5.7529743 16.25981,7.16251639 L17.40981,14.0624532 C17.423955,14.1470924 17.43373,14.2315017 17.43948,14.3153359 L30.56052,14.3153359 C30.56627,14.2313867 30.576045,14.1470924 30.59019,14.0624532 L31.74019,7.16251639 C31.975135,5.7529743 33.30833,4.80066802 34.717885,5.03561087 Z M38.375,19.4902885 L9.625,19.4902885 C7.719565,19.4902885 6.175,21.0348394 6.175,22.9402569 L6.175,34.4401516 C6.175,36.3455692 7.719565,37.89012 9.625,37.89012 L38.375,37.89012 C40.280435,37.89012 41.825,36.3455692 41.825,34.4401516 L41.825,22.9402569 C41.825,21.0348394 40.280435,19.4902885 38.375,19.4902885 Z M14.8575,23.802749 C16.28649,23.802749 17.445,24.9612484 17.445,26.3902253 L17.445,28.6902043 C17.445,30.1191812 16.28649,31.2776806 14.8575,31.2776806 C13.42851,31.2776806 12.27,30.1191812 12.27,28.6902043 L12.27,26.3902253 C12.27,24.9612484 13.42851,23.802749 14.8575,23.802749 Z M33.1425,23.802749 C34.57149,23.802749 35.73,24.9612484 35.73,26.3902253 L35.73,28.6902043 C35.73,30.1191812 34.57149,31.2776806 33.1425,31.2776806 C31.71351,31.2776806 30.555,30.1191812 30.555,28.6902043 L30.555,26.3902253 C30.555,24.9612484 31.71351,23.802749 33.1425,23.802749 Z"/></svg>';

  function buildUI() {
    var prefix = cfg.classNamePrefix;

    var wrap = document.createElement('div');
    wrap.className = 'post-' + prefix;

    // 标题行
    var titleRow = document.createElement('div');
    titleRow.className = prefix + '-title';

    var icon = document.createElement('i');
    icon.className = prefix + '-title-icon';
    icon.innerHTML = ROBOT_SVG;

    var titleText = document.createElement('div');
    titleText.className = prefix + '-title-text';
    titleText.textContent = cfg.appName;

    var regenBtn = document.createElement('div');
    regenBtn.id = prefix + '-Toggle';
    regenBtn.textContent = '生成';
    regenBtn.setAttribute('role', 'button');
    regenBtn.setAttribute('tabindex', '0');
    regenBtn.addEventListener('click', triggerGenerate);
    regenBtn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') triggerGenerate();
    });

    var tagLink = document.createElement('div');
    tagLink.className = prefix + '-tag';
    tagLink.textContent = '项目地址';
    tagLink.setAttribute('role', 'link');
    tagLink.setAttribute('tabindex', '0');
    tagLink.addEventListener('click', function () {
      window.open('https://github.com/ipan233/AIAbstractor', '_blank', 'noopener');
    });

    titleRow.appendChild(icon);
    titleRow.appendChild(titleText);
    titleRow.appendChild(regenBtn);
    titleRow.appendChild(tagLink);

    // 摘要内容区
    var content = document.createElement('div');
    content.className = prefix + '-explanation';
    content.id        = prefix + '-explanation';
    content.textContent = '加载中...';

    wrap.appendChild(titleRow);
    wrap.appendChild(content);
    return wrap;
  }

  function getContentEl() {
    return document.getElementById(cfg.classNamePrefix + '-explanation');
  }

  function removeExistingUI() {
    var el = document.querySelector('.post-' + cfg.classNamePrefix);
    if (el && el.parentElement) el.parentElement.removeChild(el);
  }

  function insertUI(selector) {
    removeExistingUI();
    var target = document.querySelector(selector);
    if (!target) return false;
    target.insertBefore(buildUI(), target.firstChild);
    return true;
  }

  // ---------------------------------------------------------------------------
  // 摘要生成（API 请求）
  // ---------------------------------------------------------------------------

  var _fetching = false;

  function setContentText(text) {
    var el = getContentEl();
    if (el) el.textContent = text;
  }

  function setContentHTML(html) {
    var el = getContentEl();
    if (el) el.innerHTML = html;
  }

  function showLoading() {
    setContentHTML('生成中...<span class="blinking-cursor"></span>');
  }

  function showError(msg) {
    setContentText('生成失败：' + msg + '。请稍后重试。');
  }

  function generate(content) {
    if (_fetching) {
      log('上一次请求尚未完成，本次忽略');
      return;
    }
    if (!cfg.apiEndpoint) {
      showError('API 端点未配置');
      return;
    }

    var prompt = '生成30字以内的摘要供读者阅读，不要带开场白，文章内容：' + content;

    _fetching = true;
    showLoading();

    fetch(cfg.apiEndpoint, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ q: prompt, model: cfg.model }),
    })
      .then(function (resp) {
        if (!resp.ok) {
          return resp.text().then(function (body) {
            throw new Error('HTTP ' + resp.status + (body ? ': ' + body.substring(0, 100) : ''));
          });
        }
        return resp.json();
      })
      .then(function (data) {
        var text = (data && data.text) ? data.text.trim() : '';
        if (!text) throw new Error('Empty response');
        setContentText(text);
        saveAbstract(text);
      })
      .catch(function (err) {
        console.error('[' + cfg.appName + '] 请求失败', err);
        showError(err.message || '未知错误');
      })
      .finally(function () {
        _fetching = false;
      });
  }

  function triggerGenerate() {
    var text = getArticleText();
    if (!text) return;
    log('提交文本长度：' + text.length);
    generate(text);
  }

  // ---------------------------------------------------------------------------
  // 初始化流程
  // ---------------------------------------------------------------------------

  function fillOrGenerate() {
    var cached = loadCachedAbstract();
    if (cached) {
      log('命中缓存，直接显示');
      setContentText(cached);
    } else {
      triggerGenerate();
    }
  }

  function initRun(retryCount) {
    retryCount = retryCount || 0;
    var inserted = insertUI(cfg.postSelector);
    if (!inserted) {
      if (retryCount < 3) {
        setTimeout(function () { initRun(retryCount + 1); }, 500);
      } else {
        console.warn('[' + cfg.appName + '] 找不到文章容器 "' + cfg.postSelector + '"，已停止重试');
      }
      return;
    }
    // 稍微延迟，确保 DOM 稳定后再读取内容
    setTimeout(fillOrGenerate, 100);
  }

  function checkURLAndRun() {
    if (typeof cfg.postURL === 'undefined') {
      initRun();
      return;
    }
    try {
      var pattern = new RegExp(
        '^' + cfg.postURL.split(/\*+/).map(function (s) {
          return s.replace(/[|\\{}()[\]^$+*?.]/g, '\\$&');
        }).join('.*') + '$'
      );
      if (pattern.test(window.location.href)) {
        initRun();
      } else {
        log('URL 不匹配自定义规则，跳过摘要功能');
      }
    } catch (e) {
      console.error('[' + cfg.appName + '] 自定义链接规则解析失败', e);
    }
  }

  // ---------------------------------------------------------------------------
  // 入口
  // ---------------------------------------------------------------------------

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkURLAndRun);
  } else {
    checkURLAndRun();
  }

})();
