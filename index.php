<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Map Snapshot Service</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      color-scheme: light;
      --bg: #f5f7fa;
      --surface: #ffffff;
      --surface-soft: #eef3f7;
      --line: #d6dee8;
      --text: #182434;
      --muted: #66778b;
      --accent: #d43c3c;
      --accent-dark: #a92b2b;
      --blue: #276fbf;
      --green: #34875f;
      --shadow: 0 16px 42px rgba(24, 36, 52, 0.12);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans TC", "Microsoft JhengHei", sans-serif;
      color: var(--text);
      background: var(--bg);
    }

    a {
      color: inherit;
    }

    .shell {
      width: min(1180px, calc(100vw - 32px));
      margin: 0 auto;
    }

    header {
      position: sticky;
      top: 0;
      z-index: 5;
      background: rgba(245, 247, 250, 0.92);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(214, 222, 232, 0.8);
    }

    .nav {
      min-height: 68px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 800;
      letter-spacing: 0;
    }

    .brand-mark {
      width: 34px;
      height: 34px;
      display: grid;
      place-items: center;
      border-radius: 8px;
      color: #fff;
      background: var(--accent);
      box-shadow: 0 8px 18px rgba(212, 60, 60, 0.24);
    }

    nav {
      display: flex;
      align-items: center;
      gap: 18px;
      color: var(--muted);
      font-size: 14px;
    }

    nav a {
      text-decoration: none;
    }

    .hero {
      padding: 46px 0 28px;
      display: grid;
      grid-template-columns: minmax(320px, 0.92fr) minmax(420px, 1.08fr);
      gap: 36px;
      align-items: center;
    }

    h1 {
      margin: 0;
      max-width: 760px;
      font-size: clamp(36px, 5vw, 62px);
      line-height: 1.03;
      letter-spacing: 0;
    }

    .hero p {
      margin: 18px 0 0;
      max-width: 640px;
      color: var(--muted);
      font-size: 18px;
      line-height: 1.75;
    }

    .actions {
      margin-top: 26px;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .button {
      min-height: 42px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border-radius: 7px;
      padding: 0 16px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      border: 1px solid transparent;
      cursor: pointer;
      font-family: inherit;
    }

    .button.primary {
      color: #fff;
      background: var(--accent);
    }

    .button[disabled] {
      opacity: .72;
      cursor: wait;
    }

    .button.secondary {
      color: var(--text);
      background: #fff;
      border-color: var(--line);
    }

    .preview-panel {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 8px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .panel-top {
      min-height: 46px;
      padding: 0 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--line);
      color: var(--muted);
      font-size: 13px;
    }

    .map-preview {
      aspect-ratio: 1.35 / 1;
      position: relative;
      overflow: hidden;
      background:
        linear-gradient(32deg, transparent 0 32%, rgba(160, 176, 192, .42) 32% 38%, transparent 38% 100%),
        linear-gradient(145deg, transparent 0 52%, rgba(160, 176, 192, .52) 52% 59%, transparent 59% 100%),
        linear-gradient(90deg, #e8eef4 0 22%, #f8fafc 22% 43%, #e7edf4 43% 58%, #f8fafc 58% 100%);
    }

    .snapshot-image {
      display: block;
      width: min(100%, 416px);
      height: auto;
      margin: 0 auto;
      aspect-ratio: 1 / 1;
      object-fit: contain;
      background: #eef3f7;
    }

    .road-label {
      position: absolute;
      color: rgba(34, 45, 58, .44);
      font-size: 18px;
      font-weight: 800;
      transform: rotate(-33deg);
      left: 53%;
      top: 52%;
    }

    .pin {
      position: absolute;
      width: 30px;
      height: 46px;
      left: 49%;
      top: 39%;
      transform: translate(-50%, -100%);
      background: url("assets/images/map/pin.png") center / contain no-repeat;
      filter: drop-shadow(0 4px 5px rgba(0, 0, 0, .16));
    }

    .pin.second {
      left: 55%;
    }

    .label {
      position: absolute;
      max-width: 180px;
      padding: 8px 10px;
      border: 1px solid rgba(212, 60, 60, .52);
      border-radius: 7px;
      background: rgba(255, 255, 255, .9);
      color: #d43c3c;
      font-size: 14px;
      font-weight: 700;
      line-height: 1.35;
    }

    .label.start {
      right: 52%;
      top: 42%;
    }

    .label.end {
      left: 57%;
      top: 42%;
    }

    .coord {
      position: absolute;
      right: 12px;
      bottom: 10px;
      padding: 4px 6px;
      background: rgba(255, 255, 255, .72);
      color: #27313d;
      font-size: 12px;
    }

    .section {
      padding: 34px 0;
    }

    .section-head {
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 18px;
      margin-bottom: 16px;
    }

    h2 {
      margin: 0;
      font-size: 28px;
      line-height: 1.25;
    }

    .section-head p {
      margin: 0;
      max-width: 520px;
      color: var(--muted);
      line-height: 1.7;
    }

    .recipes {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }

    .recipe-card {
      min-height: 260px;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .recipe-thumb {
      height: 128px;
      background: var(--surface-soft);
      border-bottom: 1px solid var(--line);
      position: relative;
      overflow: hidden;
    }

    .mini-pin {
      position: absolute;
      width: 22px;
      height: 36px;
      background: url("assets/images/map/pin.png") center / contain no-repeat;
      left: 45%;
      top: 54%;
      transform: translate(-50%, -100%);
    }

    .mini-pin.two {
      left: 57%;
    }

    .mini-pin.three {
      left: 66%;
      top: 42%;
    }

    .mini-line {
      position: absolute;
      left: 45%;
      top: 52%;
      width: 68px;
      height: 3px;
      background: var(--accent);
      transform: rotate(-12deg);
      transform-origin: left center;
    }

    .shape {
      position: absolute;
      inset: 30px 62px 28px;
      border: 3px solid rgba(39, 111, 191, .75);
      background: rgba(39, 111, 191, .12);
      clip-path: polygon(10% 30%, 58% 8%, 88% 42%, 70% 88%, 18% 72%);
    }

    .recipe-body {
      padding: 16px;
      display: grid;
      gap: 10px;
      flex: 1;
    }

    .recipe-body h3 {
      margin: 0;
      font-size: 18px;
    }

    .recipe-body p {
      margin: 0;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.65;
    }

    .tags {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .recipe-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      align-self: end;
    }

    .recipe-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--accent);
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
    }

    .tag {
      padding: 4px 8px;
      border-radius: 999px;
      background: #eef3f8;
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
    }

    .tag.ready {
      color: #fff;
      background: var(--green);
    }

    .demo {
      display: grid;
      grid-template-columns: 380px minmax(0, 1fr);
      gap: 18px;
      align-items: start;
    }

    .form-card,
    .code-card,
    .result-card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 18px;
    }

    .demo-output {
      display: grid;
      gap: 14px;
    }

    .field {
      display: grid;
      gap: 6px;
      margin-bottom: 12px;
      color: var(--muted);
      font-size: 13px;
      font-weight: 700;
    }

    input,
    select {
      width: 100%;
      min-height: 39px;
      border: 1px solid var(--line);
      border-radius: 6px;
      padding: 8px 10px;
      color: var(--text);
      background: #fff;
      font: inherit;
      font-size: 14px;
    }

    pre {
      margin: 0;
      overflow: auto;
      border-radius: 8px;
      padding: 16px;
      color: #dce7f5;
      background: #152131;
      font-size: 13px;
      line-height: 1.6;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .result-frame {
      display: grid;
      place-items: center;
      min-height: 360px;
      background: var(--surface-soft);
      border-radius: 8px;
      overflow: hidden;
      position: relative;
    }

    .result-frame img {
      width: min(100%, 416px);
      height: auto;
      display: block;
    }

    .result-frame.loading img {
      opacity: .42;
    }

    .loading-mask {
      position: absolute;
      inset: 0;
      display: none;
      place-items: center;
      color: var(--text);
      background: rgba(238, 243, 247, .62);
      font-size: 14px;
      font-weight: 800;
    }

    .result-frame.loading .loading-mask {
      display: grid;
    }

    .spinner {
      width: 18px;
      height: 18px;
      border: 2px solid rgba(24, 36, 52, .18);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin .75s linear infinite;
    }

    .loading-content {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      padding: 9px 12px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .88);
      box-shadow: 0 10px 24px rgba(24, 36, 52, .12);
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    footer {
      margin-top: 32px;
      padding: 24px 0 38px;
      color: var(--muted);
      border-top: 1px solid var(--line);
      font-size: 13px;
    }

    .footer-line {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px 18px;
      line-height: 1.7;
    }

    @media (max-width: 900px) {
      .hero,
      .demo {
        grid-template-columns: 1fr;
      }

      .recipes {
        grid-template-columns: 1fr;
      }

      nav {
        display: none;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="shell nav">
      <div class="brand">
        <span class="brand-mark"><i class="bi bi-map"></i></span>
        <span>Map Snapshot Service</span>
      </div>
      <nav aria-label="primary navigation">
        <a href="#recipes">Recipes</a>
        <a href="#demo">Demo</a>
        <a href="#api">API</a>
        <a href="https://github.com/shadowjohn/map-snapshot-service">GitHub</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="shell hero">
      <div>
        <h1>把地圖變成可以保存與傳遞的圖片</h1>
        <p>第一版提供單點、雙點、線段與 polygon 快照：輸入座標與名稱，選擇 OSM、Google 或 EMAP5 底圖，直接產生可放進報表、通知、工單或 README 的 PNG 地圖快照。</p>
        <div class="actions">
          <a class="button primary" href="#demo"><i class="bi bi-play-fill"></i>試用 Two Point</a>
          <a class="button secondary" href="#recipes"><i class="bi bi-grid-3x3-gap"></i>查看型錄</a>
        </div>
      </div>

      <div class="preview-panel" aria-label="two point map preview mockup">
        <div class="panel-top">
          <span>recipes/two-point</span>
          <span>PNG 416 x 416</span>
        </div>
        <img class="snapshot-image" src="assets/images/examples/two-point-fengchia-icc.png?v=20260610c" alt="Two Point Snapshot example from Feng Chia University to ICC office building">
      </div>
    </section>

    <section id="recipes" class="shell section">
      <div class="section-head">
        <h2>Snapshot Recipes</h2>
        <p>先用產品型錄讓使用者看見能做什麼；每個 recipe 都有 demo、API URL、參數表與可複製範例。</p>
      </div>

      <div class="recipes">
        <article class="recipe-card">
          <div class="recipe-thumb">
            <span class="mini-pin"></span>
            <span class="mini-pin two"></span>
          </div>
          <div class="recipe-body">
            <h3>Two Point Snapshot</h3>
            <p>兩點座標、兩個標籤，產出一張乾淨的地圖快照。</p>
            <div class="recipe-actions">
              <div class="tags">
                <span class="tag ready">MVP</span>
                <span class="tag">PNG</span>
                <span class="tag">PHP</span>
              </div>
              <a class="recipe-link" href="recipes/two-point/demo.html"><i class="bi bi-box-arrow-up-right"></i>Demo</a>
            </div>
          </div>
        </article>

        <article class="recipe-card">
          <div class="recipe-thumb">
            <span class="mini-pin"></span>
          </div>
          <div class="recipe-body">
            <h3>Single Point Snapshot</h3>
            <p>單一地點標記與文字，適合門牌、設備、案件位置。</p>
            <div class="recipe-actions">
              <div class="tags">
                <span class="tag ready">MVP</span>
                <span class="tag">Point</span>
              </div>
              <a class="recipe-link" href="recipes/single-point/demo.html"><i class="bi bi-box-arrow-up-right"></i>Demo</a>
            </div>
          </div>
        </article>

        <article class="recipe-card">
          <div class="recipe-thumb">
            <span class="mini-pin"></span>
            <span class="mini-pin two"></span>
            <span class="mini-pin three"></span>
          </div>
          <div class="recipe-body">
            <h3>Multi Point Snapshot</h3>
            <p>多點標記、編號或群組名稱，適合巡檢與案件清單。</p>
            <div class="tags">
              <span class="tag">Next</span>
              <span class="tag">Points</span>
            </div>
          </div>
        </article>

        <article class="recipe-card">
          <div class="recipe-thumb">
            <span class="mini-line"></span>
            <span class="mini-pin"></span>
            <span class="mini-pin two"></span>
          </div>
          <div class="recipe-body">
            <h3>Line Snapshot</h3>
            <p>線段、路線或多段線，可加方向、距離與線色。</p>
            <div class="recipe-actions">
              <div class="tags">
                <span class="tag ready">MVP</span>
                <span class="tag">Line</span>
              </div>
              <a class="recipe-link" href="recipes/line/demo.html"><i class="bi bi-box-arrow-up-right"></i>Demo</a>
            </div>
          </div>
        </article>

        <article class="recipe-card">
          <div class="recipe-thumb">
            <span class="shape"></span>
          </div>
          <div class="recipe-body">
            <h3>Polygon Snapshot</h3>
            <p>範圍、工區、災害區塊的圖片輸出。</p>
            <div class="recipe-actions">
              <div class="tags">
                <span class="tag ready">MVP</span>
                <span class="tag">Area</span>
              </div>
              <a class="recipe-link" href="recipes/polygon/demo.html"><i class="bi bi-box-arrow-up-right"></i>Demo</a>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section id="demo" class="shell section">
      <div class="section-head">
        <h2>Two Point Demo</h2>
        <p>這個入口直接呼叫 <code>api/two-point.php</code>。GET 適合短參數，POST 則保留給後續多點、線、面這類較長資料。</p>
      </div>

      <div class="demo">
        <form class="form-card" id="snapshotForm">
          <label class="field">sLatLon <input id="sLatLon" name="sLatLon" value="24.1782252,120.6484168"></label>
          <label class="field">eLatLon <input id="eLatLon" name="eLatLon" value="24.1111272,120.6100528"></label>
          <label class="field">sName <input id="sName" name="sName" value="起點: 逢甲大學"></label>
          <label class="field">eName <input id="eName" name="eName" value="目的地: ICC 辦公大樓"></label>
          <label class="field">basemap
            <select id="basemap" name="basemap">
              <option value="osm" selected>OpenStreetMap</option>
              <option value="google">Google Roadmap</option>
              <option value="emap5">國土測繪 EMAP5</option>
            </select>
          </label>
          <label class="field">request
            <select id="requestMethod" name="requestMethod">
              <option value="GET" selected>GET</option>
              <option value="POST">POST</option>
            </select>
          </label>
          <button class="button primary" id="generateButton" type="submit"><i class="bi bi-image"></i><span>產生截圖</span></button>
        </form>

        <div class="demo-output">
          <div class="result-card">
            <div class="result-frame" id="resultFrame">
              <img id="snapshotResult" src="api/two-point.php?sLatLon=24.1782252%2C120.6484168&amp;eLatLon=24.1111272%2C120.6100528&amp;sName=%E8%B5%B7%E9%BB%9E%3A%20%E9%80%A2%E7%94%B2%E5%A4%A7%E5%AD%B8&amp;eName=%E7%9B%AE%E7%9A%84%E5%9C%B0%3A%20ICC%20%E8%BE%A6%E5%85%AC%E5%A4%A7%E6%A8%93&amp;basemap=osm&amp;width=416&amp;height=416" alt="Generated two point map snapshot">
              <div class="loading-mask" aria-live="polite">
                <span class="loading-content"><span class="spinner"></span><span>產生中</span></span>
              </div>
            </div>
          </div>

          <div class="code-card" id="api">
            <pre><code id="apiSnippet"></code></pre>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="shell footer-line">
      <span>Map Snapshot Service Preview. Recipes: Single Point, Two Point, Line, Polygon.</span>
      <span>作者：羽山秋人(<a href="https://3wa.tw">https://3wa.tw</a>)；Codex 協作開發。</span>
    </div>
  </footer>
  <script>
    const form = document.getElementById('snapshotForm');
    const image = document.getElementById('snapshotResult');
    const snippet = document.getElementById('apiSnippet');
    const resultFrame = document.getElementById('resultFrame');
    const generateButton = document.getElementById('generateButton');
    let objectUrl = '';
    let renderToken = 0;

    function paramsFromForm() {
      const params = new URLSearchParams();
      params.set('sLatLon', document.getElementById('sLatLon').value.trim());
      params.set('eLatLon', document.getElementById('eLatLon').value.trim());
      params.set('sName', document.getElementById('sName').value.trim());
      params.set('eName', document.getElementById('eName').value.trim());
      params.set('basemap', document.getElementById('basemap').value);
      params.set('width', '416');
      params.set('height', '416');
      return params;
    }

    function showSnippet(method, params) {
      if (method === 'POST') {
        snippet.textContent = [
          "curl -X POST 'api/two-point.php' \\",
          ...Array.from(params.entries()).map(([key, value]) => `  --data-urlencode '${key}=${value}'`)
        ].join("\n");
        return;
      }

      snippet.textContent = Array.from(params.entries())
        .map(([key, value], index) => `  ${index === 0 ? '?' : '&'}${key}=${encodeURIComponent(value)}`)
        .reduce((lines, line) => lines.concat(line), ['GET api/two-point.php'])
        .join("\n");
    }

    async function renderSnapshot(event) {
      if (event) {
        event.preventDefault();
      }

      const token = ++renderToken;
      const params = paramsFromForm();
      const method = document.getElementById('requestMethod').value;
      showSnippet(method, params);
      resultFrame.classList.add('loading');
      generateButton.disabled = true;
      generateButton.innerHTML = '<span class="spinner"></span><span>產生中</span>';

      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = '';
      }

      if (method === 'POST') {
        const response = await fetch('api/two-point.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params
        });
        const blob = await response.blob();
        objectUrl = URL.createObjectURL(blob);
        image.src = objectUrl;
        if (token === renderToken) {
          resultFrame.classList.remove('loading');
          generateButton.disabled = false;
          generateButton.innerHTML = '<i class="bi bi-image"></i><span>產生截圖</span>';
        }
        return;
      }

      image.onload = () => {
        if (token !== renderToken) {
          return;
        }
        resultFrame.classList.remove('loading');
        generateButton.disabled = false;
        generateButton.innerHTML = '<i class="bi bi-image"></i><span>產生截圖</span>';
      };
      image.onerror = image.onload;
      image.src = `api/two-point.php?${params.toString()}`;
    }

    form.addEventListener('submit', renderSnapshot);
    form.addEventListener('change', renderSnapshot);
    showSnippet('GET', paramsFromForm());
  </script>
</body>
</html>
