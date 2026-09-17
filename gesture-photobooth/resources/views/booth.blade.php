<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name') }}</title>
<style>
  :root {
    --bg: #f4f1ea; --panel: #ffffff; --ink: #201d1a; --ink-soft: #6b655c;
    --accent: #e8623d; --accent-ink: #ffffff; --line: #e3ddd0; --good: #3f8f5f;
    --shadow: 0 10px 30px rgba(32,29,26,0.12);
  }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--ink); font-family:'Segoe UI',system-ui,sans-serif; padding:24px 16px 60px; }
  .wrap { max-width:900px; margin:0 auto; }
  header { text-align:center; margin-bottom:22px; }
  h1 { font-size:clamp(1.5rem,4vw,2.1rem); margin:0 0 6px; }
  header p { color:var(--ink-soft); margin:0; font-size:.95rem; }
  .stage { position:relative; background:var(--panel); border-radius:20px; box-shadow:var(--shadow); overflow:hidden; border:1px solid var(--line); aspect-ratio:4/3; max-width:640px; margin:0 auto; }
  video, canvas.overlay { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transform:scaleX(-1); }
  video { background:#000; }
  canvas.overlay { pointer-events:none; }
  .hud { position:absolute; top:12px; left:12px; right:12px; display:flex; justify-content:space-between; pointer-events:none; }
  .status-pill { background:rgba(20,18,16,.55); color:#fff; padding:6px 14px; border-radius:999px; font-size:.82rem; font-weight:600; display:flex; align-items:center; gap:8px; }
  .dot { width:8px; height:8px; border-radius:50%; background:var(--ink-soft); }
  .dot.live { background:var(--good); box-shadow:0 0 8px var(--good); }
  .dot.hold { background:var(--accent); box-shadow:0 0 8px var(--accent); }
  .countdown { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:7rem; font-weight:800; color:#fff; text-shadow:0 4px 20px rgba(0,0,0,.5); opacity:0; pointer-events:none; transition:opacity .15s; }
  .countdown.show { opacity:1; }
  .flash { position:absolute; inset:0; background:#fff; opacity:0; pointer-events:none; }
  .flash.go { animation:flashpop .4s ease-out; }
  @keyframes flashpop { 0%{opacity:.9} 100%{opacity:0} }
  .controls { max-width:640px; margin:18px auto 0; display:flex; flex-wrap:wrap; gap:10px; justify-content:center; }
  button { font-family:inherit; font-size:.9rem; font-weight:600; padding:10px 18px; border-radius:12px; border:1px solid var(--line); background:var(--panel); color:var(--ink); cursor:pointer; }
  button:hover { border-color:var(--accent); }
  button.primary { background:var(--accent); color:var(--accent-ink); border-color:var(--accent); }
  button:disabled { opacity:.5; cursor:not-allowed; }
  .hint { text-align:center; color:var(--ink-soft); font-size:.85rem; margin-top:12px; max-width:640px; margin-left:auto; margin-right:auto; }
  .hint b { color:var(--ink); }
  .error-box { max-width:640px; margin:16px auto 0; background:var(--panel); border:1px solid var(--line); border-radius:12px; padding:14px 16px; color:var(--ink-soft); font-size:.85rem; display:none; }
  .gallery { max-width:900px; margin:32px auto 0; }
  .gallery h2 { font-size:1.05rem; margin:0 0 12px; text-align:center; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:12px; }
  .shot { position:relative; border-radius:12px; overflow:hidden; border:1px solid var(--line); background:var(--panel); box-shadow:var(--shadow); }
  .shot img { display:block; width:100%; height:auto; }
  .shot .dl, .shot .del { position:absolute; bottom:6px; background:rgba(20,18,16,.65); color:#fff; border:none; border-radius:8px; padding:4px 8px; font-size:.72rem; cursor:pointer; }
  .shot .dl { right:6px; text-decoration:none; }
  .shot .del { left:6px; }
  .empty { text-align:center; color:var(--ink-soft); font-size:.85rem; padding:20px; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>🖐️ {{ config('app.name') }}</h1>
    <p>Hold up an open palm to the camera and let it count you down. Every shot is saved to the database.</p>
  </header>

  <div class="stage" id="stage">
    <video id="video" autoplay playsinline muted></video>
    <canvas class="overlay" id="overlay"></canvas>
    <div class="hud"><div class="status-pill"><span class="dot" id="statusDot"></span><span id="statusText">Starting camera…</span></div></div>
    <div class="countdown" id="countdown"></div>
    <div class="flash" id="flash"></div>
  </div>

  <div class="controls">
    <button class="primary" id="captureBtn" disabled>📸 Capture now</button>
    <button id="toggleBtn">⏸ Pause gesture detection</button>
  </div>

  <p class="hint">Show an <b>open palm</b> (all five fingers spread) and hold it steady for about a second to trigger a 3-second countdown.</p>

  <div class="error-box" id="errorBox"></div>

  <div class="gallery">
    <h2>Saved shots</h2>
    <div class="grid" id="grid">
      @foreach ($photos as $photo)
        <div class="shot" data-id="{{ $photo->id }}">
          <img src="{{ $photo->url }}" alt="Photobooth shot">
          <a class="dl" href="{{ $photo->url }}" download>⬇</a>
          <button class="del" data-id="{{ $photo->id }}">🗑</button>
        </div>
      @endforeach
    </div>
    <div class="empty" id="emptyMsg" style="{{ $photos->count() ? 'display:none' : '' }}">No photos yet — strike a pose!</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
<script>
(function() {
  const video = document.getElementById('video');
  const overlay = document.getElementById('overlay');
  const octx = overlay.getContext('2d');
  const statusDot = document.getElementById('statusDot');
  const statusText = document.getElementById('statusText');
  const countdownEl = document.getElementById('countdown');
  const flashEl = document.getElementById('flash');
  const captureBtn = document.getElementById('captureBtn');
  const toggleBtn = document.getElementById('toggleBtn');
  const grid = document.getElementById('grid');
  const emptyMsg = document.getElementById('emptyMsg');
  const errorBox = document.getElementById('errorBox');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  let detectionActive = true;
  let counting = false;
  let holdFrames = 0;
  const HOLD_NEEDED = 12;
  let handsReady = false;

  function showError(msg) { errorBox.style.display = 'block'; errorBox.textContent = msg; }
  function setStatus(text, mode) { statusText.textContent = text; statusDot.className = 'dot' + (mode ? ' ' + mode : ''); }

  function isOpenPalm(landmarks) {
    const tips = [8, 12, 16, 20], pips = [6, 10, 14, 18];
    let extended = 0;
    for (let i = 0; i < tips.length; i++) {
      if (landmarks[tips[i]].y < landmarks[pips[i]].y - 0.02) extended++;
    }
    const thumbTip = landmarks[4], thumbIp = landmarks[3], wrist = landmarks[0];
    const thumbExtended = Math.hypot(thumbTip.x - wrist.x, thumbTip.y - wrist.y) >
                           Math.hypot(thumbIp.x - wrist.x, thumbIp.y - wrist.y);
    if (thumbExtended) extended++;
    return extended >= 4;
  }

  function drawLandmarks(landmarks) {
    octx.clearRect(0, 0, overlay.width, overlay.height);
    if (!landmarks) return;
    octx.fillStyle = '#e8623d'; octx.strokeStyle = octx.fillStyle; octx.lineWidth = 2;
    const conns = [[0,1],[1,2],[2,3],[3,4],[0,5],[5,6],[6,7],[7,8],[5,9],[9,10],[10,11],[11,12],[9,13],[13,14],[14,15],[15,16],[13,17],[17,18],[18,19],[19,20],[0,17]];
    conns.forEach(([a,b]) => {
      const pa = landmarks[a], pb = landmarks[b];
      octx.beginPath(); octx.moveTo(pa.x*overlay.width, pa.y*overlay.height); octx.lineTo(pb.x*overlay.width, pb.y*overlay.height); octx.stroke();
    });
    landmarks.forEach(p => { octx.beginPath(); octx.arc(p.x*overlay.width, p.y*overlay.height, 3, 0, Math.PI*2); octx.fill(); });
  }

  let lastW = 0, lastH = 0;
  function resizeOverlayIfNeeded() {
    if (overlay.clientWidth !== lastW || overlay.clientHeight !== lastH) {
      overlay.width = overlay.clientWidth; overlay.height = overlay.clientHeight;
      lastW = overlay.clientWidth; lastH = overlay.clientHeight;
    }
  }

  function onResults(results) {
    resizeOverlayIfNeeded();
    const hasHand = results.multiHandLandmarks && results.multiHandLandmarks.length > 0;
    if (hasHand) drawLandmarks(results.multiHandLandmarks[0]); else octx.clearRect(0,0,overlay.width,overlay.height);
    if (!detectionActive || counting) return;
    if (hasHand && isOpenPalm(results.multiHandLandmarks[0])) {
      holdFrames++;
      setStatus('Palm detected — hold steady…', 'hold');
      if (holdFrames >= HOLD_NEEDED) { holdFrames = 0; startCountdown(); }
    } else {
      holdFrames = Math.max(0, holdFrames - 2);
      if (holdFrames === 0) setStatus(handsReady ? 'Show an open palm to trigger' : 'Loading gesture model…', handsReady ? 'live' : '');
    }
  }

  function startCountdown() {
    counting = true; captureBtn.disabled = true;
    let n = 3; countdownEl.textContent = n; countdownEl.classList.add('show');
    setStatus('Get ready!', 'hold');
    const tick = setInterval(() => {
      n--;
      if (n > 0) { countdownEl.textContent = n; }
      else {
        clearInterval(tick);
        countdownEl.textContent = '📸';
        setTimeout(() => {
          takePhoto();
          countdownEl.classList.remove('show');
          counting = false; captureBtn.disabled = false;
          setStatus('Show an open palm to trigger', 'live');
        }, 250);
      }
    }, 700);
  }

  function takePhoto() {
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.translate(canvas.width, 0); ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/png');

    flashEl.classList.remove('go'); void flashEl.offsetWidth; flashEl.classList.add('go');

    fetch('{{ route('photos.store') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify({ image: dataUrl })
    })
      .then(r => r.json())
      .then(data => { if (data.url) prependShot(data.id, data.url); })
      .catch(() => showError('Could not save the photo to the server. It was still captured — try again in a moment.'));
  }

  function prependShot(id, url) {
    emptyMsg.style.display = 'none';
    const shot = document.createElement('div');
    shot.className = 'shot'; shot.dataset.id = id;
    shot.innerHTML = `<img src="${url}" alt="Photobooth shot"><a class="dl" href="${url}" download>⬇</a><button class="del" data-id="${id}">🗑</button>`;
    grid.prepend(shot);
  }

  grid.addEventListener('click', (e) => {
    if (!e.target.classList.contains('del')) return;
    const id = e.target.dataset.id;
    fetch(`/photos/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(() => {
        e.target.closest('.shot').remove();
        if (!grid.children.length) emptyMsg.style.display = 'block';
      });
  });

  captureBtn.addEventListener('click', () => { if (!counting) startCountdown(); });
  toggleBtn.addEventListener('click', () => {
    detectionActive = !detectionActive;
    toggleBtn.textContent = detectionActive ? '⏸ Pause gesture detection' : '▶ Resume gesture detection';
    holdFrames = 0;
    setStatus(detectionActive ? 'Show an open palm to trigger' : 'Gesture detection paused', detectionActive ? 'live' : '');
  });

  async function init() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 }, audio: false });
      video.srcObject = stream;
      await new Promise(res => { video.onloadedmetadata = res; });
      overlay.width = overlay.clientWidth; overlay.height = overlay.clientHeight;
      lastW = overlay.clientWidth; lastH = overlay.clientHeight;
      captureBtn.disabled = false;
      setStatus('Loading gesture model…', '');
    } catch (err) {
      showError('Could not access your camera. Check permissions and reload. You can still use "Capture now" once enabled.');
      setStatus('Camera unavailable', '');
      return;
    }

    try {
      if (typeof Hands === 'undefined') throw new Error('Hands library did not load');
      const hands = new Hands({ locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}` });
      hands.setOptions({ maxNumHands: 1, modelComplexity: 1, minDetectionConfidence: 0.6, minTrackingConfidence: 0.5 });
      hands.onResults(onResults);

      if (typeof Camera !== 'undefined') {
        new Camera(video, { onFrame: async () => { await hands.send({ image: video }); }, width: 640, height: 480 }).start();
      } else {
        const loop = async () => { await hands.send({ image: video }); requestAnimationFrame(loop); };
        loop();
      }
      handsReady = true;
      setStatus('Show an open palm to trigger', 'live');
    } catch (err) {
      handsReady = false;
      setStatus('Gesture detection unavailable — use the button below', '');
      showError('Gesture detection could not load. You can still take photos with the "Capture now" button.');
    }
  }

  init();
})();
</script>
</body>
</html>
