console.log("\n %c Cat-Abstract-AI (Forked from Post-Abstract-AI) 开源博客文章摘要AI生成工具 %c https://github.com/zkeq/Cat-Abstract-AI \n", "color: #fadfa3; background: #030307; padding:5px 0;", "background: #fadfa3; padding:5px 0;")

// 默认配置
const AIAbstractorConfig = {
  appName: "AI摘要工具", // 应用名称，用于UI显示和控制台输出
  classNamePrefix: "ai-abstractor", // 用于生成HTML元素的类名和ID前缀
  apiEndpoint: "/action/ai-abstractor", // 默认API端点（Typecho 插件 Action）
  apiKey: "", // 默认API Key为空，需要用户自行配置（前端不再直接使用）
  postSelector: "#article-container", // 文章内容的CSS选择器（默认按照需求修改）
  wordLimit: 720, // 摘要的字数限制
  postURL: undefined, // 匹配文章URL的规则，undefined表示不限制
  model: "gpt-4o-mini" // 供后端参考，前端只作透传
};

// 允许由后台（Typecho 插件）注入覆盖配置
if (typeof window !== 'undefined' && window.AIAbstractorConfigOverrides) {
  try {
    Object.assign(AIAbstractorConfig, window.AIAbstractorConfigOverrides);
  } catch (e) {
    console.warn("AI摘要工具：合并后台配置失败", e);
  }
}

// 1. 读取文章已有的描述
// 2. 增加按钮 AI 描述

let StreamAIAbstractorFetchWait = false;

function insertAIDiv(selector) {
  // 首先移除现有的 "post-TianliGPT" 类元素（如果有的话）
  removeExistingAIDiv();
  
  // 获取目标元素
  const targetElement = document.querySelector(selector);

  // 如果没有找到目标元素，不执行任何操作
  if (!targetElement) {
    return;
  }

  // 创建要插入的HTML元素
  const aiDiv = document.createElement('div');
  aiDiv.className = `post-${AIAbstractorConfig.classNamePrefix}`;

  const aiTitleDiv = document.createElement('div');
  aiTitleDiv.className = `${AIAbstractorConfig.classNamePrefix}-title`;
  aiDiv.appendChild(aiTitleDiv);

  const aiIcon = document.createElement('i');
  aiIcon.className = `${AIAbstractorConfig.classNamePrefix}-title-icon`;
  aiTitleDiv.appendChild(aiIcon);

  // 插入 SVG 图标
  aiIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="48px" height="48px" viewBox="0 0 48 48">
  <title>机器人</title>
  <g id="&#x673A;&#x5668;&#x4EBA;" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
    <path d="M34.717885,5.03561087 C36.12744,5.27055371 37.079755,6.60373651 36.84481,8.0132786 L35.7944,14.3153359 L38.375,14.3153359 C43.138415,14.3153359 47,18.1768855 47,22.9402569 L47,34.4401516 C47,39.203523 43.138415,43.0650727 38.375,43.0650727 L9.625,43.0650727 C4.861585,43.0650727 1,39.203523 1,34.4401516 L1,22.9402569 C1,18.1768855 4.861585,14.3153359 9.625,14.3153359 L12.2056,14.3153359 L11.15519,8.0132786 C10.920245,6.60373651 11.87256,5.27055371 13.282115,5.03561087 C14.69167,4.80066802 16.024865,5.7529743 16.25981,7.16251639 L17.40981,14.0624532 C17.423955,14.1470924 17.43373,14.2315017 17.43948,14.3153359 L30.56052,14.3153359 C30.56627,14.2313867 30.576045,14.1470924 30.59019,14.0624532 L31.74019,7.16251639 C31.975135,5.7529743 33.30833,4.80066802 34.717885,5.03561087 Z M38.375,19.4902885 L9.625,19.4902885 C7.719565,19.4902885 6.175,21.0348394 6.175,22.9402569 L6.175,34.4401516 C6.175,36.3455692 7.719565,37.89012 9.625,37.89012 L38.375,37.89012 C40.280435,37.89012 41.825,36.3455692 41.825,34.4401516 L41.825,22.9402569 C41.825,21.0348394 40.280435,19.4902885 38.375,19.4902885 Z M14.8575,23.802749 C16.28649,23.802749 17.445,24.9612484 17.445,26.3902253 L17.445,28.6902043 C17.445,30.1191812 16.28649,31.2776806 14.8575,31.2776806 C13.42851,31.2776806 12.27,30.1191812 12.27,28.6902043 L12.27,26.3902253 C12.27,24.9612484 13.42851,23.802749 14.8575,23.802749 Z M33.1425,23.802749 C34.57149,23.802749 35.73,24.9612484 35.73,26.3902253 L35.73,28.6902043 C35.73,30.1191812 34.57149,31.2776806 33.1425,31.2776806 C31.71351,31.2776806 30.555,30.1191812 30.555,28.6902043 L30.555,26.3902253 C30.555,24.9612484 31.71351,23.802749 33.1425,23.802749 Z" id="&#x5F62;&#x72B6;&#x7ED3;&#x5408;" fill="#444444" fill-rule="nonzero"></path>
  </g>
  </svg>`

  const aiTitleTextDiv = document.createElement('div');
  aiTitleTextDiv.className = `${AIAbstractorConfig.classNamePrefix}-title-text`;
  aiTitleTextDiv.textContent = AIAbstractorConfig.appName;
  aiTitleDiv.appendChild(aiTitleTextDiv);

  const aiToggleDiv = document.createElement('div');
  aiToggleDiv.id = `${AIAbstractorConfig.classNamePrefix}-Toggle`;
  aiToggleDiv.textContent = '切换';
  // 点击时触发 runAIAbstractor 函数
  aiToggleDiv.addEventListener('click', runAIAbstractor);
  aiTitleDiv.appendChild(aiToggleDiv);

  const aiTagDiv = document.createElement('div');
  aiTagDiv.className = `${AIAbstractorConfig.classNamePrefix}-tag`;
  aiTagDiv.id = `${AIAbstractorConfig.classNamePrefix}-tag`;
  aiTagDiv.textContent = `CatGPT - ${AIAbstractorConfig.appName}(1)`;
  aiTagDiv.addEventListener('click', () => {
    window.open('https://catgpt.miaorun.dev/', '_blank');
  });
  aiTitleDiv.appendChild(aiTagDiv);

  const aiExplanationDiv = document.createElement('div');
  aiExplanationDiv.className = `${AIAbstractorConfig.classNamePrefix}-explanation`;
  aiExplanationDiv.innerHTML = '生成中...' + '<span class="blinking-cursor"></span>';
  aiDiv.appendChild(aiExplanationDiv); // 将 AI摘要工具-explanation 插入到 aiDiv，而不是 aiTitleDiv

  // 将创建的元素插入到目标元素的顶部
  targetElement.insertBefore(aiDiv, targetElement.firstChild);
}

function removeExistingAIDiv() {
  // 查找具有 "post-TianliGPT" 类的元素
  const existingAIDiv = document.querySelector(`.post-${AIAbstractorConfig.classNamePrefix}`);

  // 如果找到了这个元素，就从其父元素中删除它
  if (existingAIDiv) {
    existingAIDiv.parentElement.removeChild(existingAIDiv);
  }
}

var AIAbstractor = {
  //读取文章中的所有文本
  getTitleAndContent: function() {
    try {
      const title = document.title;
      const container = document.querySelector(AIAbstractorConfig.postSelector);
      if (!container) {
        console.warn(`${AIAbstractorConfig.appName}：找不到文章容器。请尝试将引入的代码放入到文章容器之后。如果本身没有打算使用摘要功能可以忽略此提示。`);
        return '';
      }
      const paragraphs = container.getElementsByTagName('p');
      const headings = container.querySelectorAll('h1, h2, h3, h4, h5');
      let content = '';
  
      for (let h of headings) {
        content += h.innerText + '|&|';
      }
  
      for (let p of paragraphs) {
        // 移除包含'http'的链接
        const filteredText = p.innerText.replace(/https?:\/\/[^\s]+/g, '');
        content += filteredText + '|&|';
      }
  
      const combinedText = title + ' ' + content;
      let wordLimit = AIAbstractorConfig.wordLimit;
      // 旧的全局变量兼容代码已移除
      const truncatedText = combinedText.substring(0, wordLimit).split('|&|').slice(0, -1).join(' ');
      return truncatedText;
    } catch (e) {
      console.error(`${AIAbstractorConfig.appName}错误：可能由于一个或多个错误导致没有正常运行，原因出在获取文章容器中的内容失败，或者可能是在文章转换过程中失败。`, e);
      return '';
    }
  },
  
  fetchAPI: async function(content) {

    const prompt = "生成30字以内的摘要供读者阅读,不要带“生成的摘要如下”这样的问候信息,我给你的文章内容是:" + content
    const apiUrl = AIAbstractorConfig.apiEndpoint; // 使用配置的API端点

    if (!apiUrl) {
      console.error(`${AIAbstractorConfig.appName}错误：API端点未配置。`);
      document.querySelector(`.${AIAbstractorConfig.classNamePrefix}-explanation`).innerHTML = '获取文章摘要失败：API端点未配置。';
      return;
    }
    
    document.querySelector(`.${AIAbstractorConfig.classNamePrefix}-explanation`).innerHTML = '生成中...' + '<span class="blinking-cursor"></span>';

    try {
      StreamAIAbstractorFetchWait = true;
      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ q: prompt, model: AIAbstractorConfig.model })
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      const text = (data && (data.text || data.message || data.content)) ? (data.text || data.message || data.content) : '';

      if (text) {
        const cursor = document.querySelector('.blinking-cursor');
        if (cursor) cursor.remove();
        document.querySelector(`.${AIAbstractorConfig.classNamePrefix}-explanation`).innerText = text;
      } else {
        throw new Error('Empty response');
      }

    } catch (error) {
      if (error.name === 'AbortError') {
        if (window.location.hostname === 'localhost') {
          console.warn('警告：请勿在本地主机上测试 API 密钥。');
          document.querySelector(`.${AIAbstractorConfig.classNamePrefix}-explanation`).innerHTML = '获取文章摘要超时。请勿在本地主机上测试 API 密钥。';
        } else {
          console.error('请求超时');
          document.querySelector(`.${AIAbstractorConfig.classNamePrefix}-explanation`).innerHTML = '获取文章摘要超时。当你出现这个问题时，可能是key或者绑定的域名不正确。也可能是因为文章过长导致的 AI 运算量过大，您可以稍等一下然后刷新页面重试。';
        }
      } else {
        console.error('请求失败：', error);
        document.querySelector(`.${AIAbstractorConfig.classNamePrefix}-explanation`).innerHTML = '获取文章摘要失败，请稍后再试。';
      }
    } finally {
      StreamAIAbstractorFetchWait = false;
    }
  }
}

function runAIAbstractor() {
  if (StreamAIAbstractorFetchWait){
    console.log(`${AIAbstractorConfig.appName}：正在等待上一次请求的返回结果，本次请求将被忽略。`);
    return;
  }
  const content = AIAbstractor.getTitleAndContent();
  if (content && content !== '') {
    console.log(`${AIAbstractorConfig.appName}本次提交的内容为：` + content);
  }else{
    return;
  }
  AIAbstractor.fetchAPI(content);
}

function checkURLAndRun() {
  if (typeof AIAbstractorConfig.postURL === "undefined") { // 使用配置的URL规则
    initRun(); // 如果没有设置自定义 URL，则直接执行 initRun() 函数
    return;
  }

  try {
    const wildcardToRegExp = (s) => {
      return new RegExp('^' + s.split(/\*+/).map(regExpEscape).join('.*') + '$');
    };

    const regExpEscape = (s) => {
      return s.replace(/[|\\{}()[\]^$+*?.]/g, '\\$&');
    };

    const urlPattern = wildcardToRegExp(AIAbstractorConfig.postURL);
    const currentURL = window.location.href;

    if (urlPattern.test(currentURL)) {
      initRun(); // 如果当前 URL 符合用户设置的 URL，则执行 initRun() 函数
    } else {
      console.log(`${AIAbstractorConfig.appName}：因为不符合自定义的链接规则，我决定不执行摘要功能。`);
    }
  } catch (error) {
    console.error(`${AIAbstractorConfig.appName}：我没有看懂你编写的自定义链接规则，所以我决定不执行摘要功能`, error);
  }
}

function fillDescriptionContent() {
  let descriptionElement = document.querySelector('meta[name="description"]');
  if (descriptionElement) {
    document.querySelector(`.${AIAbstractorConfig.classNamePrefix}-explanation`).innerHTML = descriptionElement.content;
  }
}

function initRun() {
  insertAIDiv(AIAbstractorConfig.postSelector); // 使用配置的文章选择器
  fillDescriptionContent();
}

checkURLAndRun();
