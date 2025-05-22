<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Litofania 3D – Processamento de Imagem</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- 1) Importmap para módulos ES6 locais -->
  <script type="importmap">
  {
    "imports": {
      "three": "./js/three.module.js",
      "three/addons/": "./js/"
    }
  }
  </script>

  <!-- 2) Bootstrap e estilos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin:0; overflow:hidden; }
    .sidebar {
      background:#f8f9fa; width:250px; height:100vh;
      padding:10px; box-sizing:border-box; overflow-y:auto;
      border-right:1px solid #dee2e6;
    }
    #threejs-container { flex:1; }
    .form-range, .form-select, .form-control, .btn { margin-bottom:8px; }
    /* overlay dos histogramas */
    #histogram-overlay {
      position:absolute; top:10px; right:10px;
      background:rgba(255,255,255,0.8);
      padding:8px; border:1px solid #ccc;
      display:flex; gap:8px;
    }
    #histogram-overlay canvas { background:#fff; border:1px solid #999; }
    #histogram-overlay .label { text-align:center; font-size:12px; }
  </style>
</head>
<body>

  <!-- 3) Overlay de histogramas -->
  <div id="histogram-overlay">
    <div>
      <div class="label">Original</div>
      <canvas id="histOrig" width="128" height="64"></canvas>
    </div>
    <div>
      <div class="label">Alterado</div>
      <canvas id="histProc" width="128" height="64"></canvas>
    </div>
  </div>

  <div style="display:flex; height:100vh;">
    <!-- 4) Sidebar com TODOS os controles -->
    <div class="sidebar">
<h5>Grey scaling</h5>

<label for="gsMethod">Método</label>
<select id="gsMethod" class="form-select mb-2">
  <option value="lum">Luminance</option>
  <option value="bw">Black &amp; White</option>
</select>

<label for="gsThresh">
  Threshold: <span id="gsThreshVal">0.50</span>
</label>
<input
  id="gsThresh"
  type="range"
  class="form-range mb-2"
  min="0"
  max="1"
  step="0.01"
  value="0.5"
/>

<label for="gsBlack">
  Black Level: <span id="gsBlackVal">0.20</span>
</label>
<input
  id="gsBlack"
  type="range"
  class="form-range mb-2"
  min="0"
  max="1"
  step="0.01"
  value="0.2"
/>

<label for="gsWhite">
  White Level: <span id="gsWhiteVal">0.80</span>
</label>
<input
  id="gsWhite"
  type="range"
  class="form-range mb-2"
  min="0"
  max="1"
  step="0.01"
  value="0.8"
/>

<label for="gsBg">
  Bg Intensity: <span id="gsBgVal">1.00</span>
</label>
<input
  id="gsBg"
  type="range"
  class="form-range mb-2"
  min="0"
  max="1"
  step="0.01"
  value="1"
/>

      <hr>
      <h5>Image editor</h5>
      <label>Brightness (-100 a 100)</label>
      <input id="editBright" type="range" class="form-range" min="-100" max="100" value="0">
      <label>Contrast (-100 a 100)</label>
      <input id="editContrast" type="range" class="form-range" min="-100" max="100" value="0">
      <label>Exposure (-100 a 100)</label>
      <input id="editExposure" type="range" class="form-range" min="-100" max="100" value="0">
      <label>Blur (px)</label>
      <input id="editBlur" type="range" class="form-range" min="0" max="20" value="0">

      <hr>
      <h5>Transformations</h5>
      <label>Rotate (°)</label>
      <input id="trRotate" type="number" class="form-control" min="0" max="360" value="0">
      <label>Crop Width (0–100%)</label>
      <input id="trCropW" type="range" class="form-range" min="0" max="100" value="100">
      <label>Crop Height (0–100%)</label>
      <input id="trCropH" type="range" class="form-range" min="0" max="100" value="100">
      <div class="form-check">
        <input id="trMirror" class="form-check-input" type="checkbox">
        <label class="form-check-label">Mirror</label>
      </div>
      <div class="form-check">
        <input id="trFlip" class="form-check-input" type="checkbox">
        <label class="form-check-label">Flip</label>
      </div>

      <hr>
      <h5>Upload & Sphere</h5>
      <input id="imageInput" type="file" class="form-control" accept="image/*">
      <hr>
      <button id="toggleLight"     class="btn btn-primary w-100">Luz On/Off</button>
      <button id="toggleWireframe" class="btn btn-secondary w-100">Wireframe</button>
      <label>Transparency</label>
      <input id="transparencySlider" type="range" class="form-range" min="0" max="100" value="0">
      <hr>
      <button id="exportImg" class="btn btn-success w-100">Exportar Imagem</button>
    </div>

    <!-- 5) Container para Three.js -->
    <div id="threejs-container"></div>
  </div>

  <!-- 6) Script principal -->
  <script type="module">
  import * as THREE        from 'three';
  import { OrbitControls } from 'three/addons/OrbitControls.js';

  // ——————————————————————————————————————————————————————
  // Setup Three.js
  const scene    = new THREE.Scene();
  scene.background = new THREE.Color(0xf0f0f0);
  const camera   = new THREE.PerspectiveCamera(75,(window.innerWidth-250)/window.innerHeight,0.1,1000);
  camera.position.set(0,0,3);
  const renderer = new THREE.WebGLRenderer({antialias:true});
  renderer.setSize(window.innerWidth-250, window.innerHeight);
  document.getElementById('threejs-container').appendChild(renderer.domElement);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping    = true;
  controls.dampingFactor    = 0.05;
  controls.minDistance      = 1;
  controls.maxDistance      = 10;

  // Luzes
  const ambientLight     = new THREE.AmbientLight(0xffffff,0.6);
  const directionalLight = new THREE.DirectionalLight(0xffffff,1);
  directionalLight.position.set(5,5,5);
  scene.add(ambientLight,directionalLight);

  // Esfera
  const sphereGeo = new THREE.SphereGeometry(1,64,64);
  let procCanvas = document.createElement('canvas');
      procCanvas.width  = 1024;
      procCanvas.height = 512;
  let procCtx    = procCanvas.getContext('2d');
  let baseMaterial = new THREE.MeshStandardMaterial({
    map        : new THREE.CanvasTexture(procCanvas),
    side       : THREE.DoubleSide,
    metalness  : 0.1,
    roughness  : 0.8,
    transparent: true,
    opacity    : 1
  });
  const sphere = new THREE.Mesh(sphereGeo, baseMaterial);
  scene.add(sphere);

  // ——————————————————————————————————————————————————————
  // Histogramas
  const hOrig = document.getElementById('histOrig'),  ctxO = hOrig.getContext('2d');
  const hProc = document.getElementById('histProc'), ctxP = hProc.getContext('2d');

  function drawHistogram(data,ctx){
    const w = ctx.canvas.width, h = ctx.canvas.height;
    ctx.clearRect(0,0,w,h);
    const max = Math.max(...data);
    for(let i=0;i<256;i++){
      const bar = data[i]/max * h;
      ctx.fillStyle = 'black';
      ctx.fillRect(i*(w/256), h-bar, w/256, bar);
    }
  }

  function computeHistogram(imgData){
    const hist = new Array(256).fill(0);
    const d = imgData.data;
    for(let i=0;i<d.length;i+=4){
      // luminance
      const l = Math.round(0.2126*d[i] + 0.7152*d[i+1] + 0.0722*d[i+2]);
      hist[l]++;
    }
    return hist;
  }

  // ——————————————————————————————————————————————————————
  // Processamento de imagem
  let img = new Image();
  function applyProcessing(){
    // 1) Draw original image offscreen
    procCtx.save();
    procCtx.clearRect(0,0,procCanvas.width,procCanvas.height);

    // transformations
    procCtx.translate(procCanvas.width/2,procCanvas.height/2);
    const rot = +trRotate.value * Math.PI/180;
    procCtx.rotate(rot);
    const mirror = trMirror.checked? -1:1;
    const flip   = trFlip.checked?   -1:1;
    procCtx.scale(mirror,flip);

    // crop
    const cw = procCanvas.width  * (trCropW.value/100);
    const ch = procCanvas.height * (trCropH.value/100);
    procCtx.drawImage(
      img,
      (procCanvas.width - cw)/-2, (procCanvas.height - ch)/-2, cw, ch,
      -procCanvas.width/2, -procCanvas.height/2,
      procCanvas.width, procCanvas.height
    );
    procCtx.restore();

    // get pixel data
    let imageData = procCtx.getImageData(0,0,procCanvas.width,procCanvas.height);
    let d = imageData.data;

    // 2) Ajustes Brightness / Contrast / Exposure
    const b = +editBright.value, c = +editContrast.value, e = +editExposure.value;
    const cb = (259*(c+255))/(255*(259-c));
    const ef = Math.pow(2,e/100);
    for(let i=0;i<d.length;i+=4){
      // brightness
      d[i]   = d[i]*ef + b; // R
      d[i+1] = d[i+1]*ef + b;
      d[i+2] = d[i+2]*ef + b;
      // contrast
      d[i]   = cb*(d[i]-128)+128;
      d[i+1] = cb*(d[i+1]-128)+128;
      d[i+2] = cb*(d[i+2]-128)+128;
    }

    // 3) Grey scaling
    const method = gsMethod.value,
          thr    = +gsThresh.value*255,
          bl     = +gsBlack.value*255,
          wh     = +gsWhite.value*255,
          bgI    = +gsBg.value;
    for(let i=0;i<d.length;i+=4){
      let lum = 0.2126*d[i] + 0.7152*d[i+1] + 0.0722*d[i+2];
      if(method==='bw'){
        if(lum < thr) lum = bl;
        else          lum = wh;
        d[i]=d[i+1]=d[i+2]=lum;
      } else {
        d[i]=d[i+1]=d[i+2]=lum; 
      }
      // bg intensity via alpha
      d[i+3] = d[i+3]*bgI;
    }

    // 4) Aplicar blur via filter se necessário
    if(+editBlur.value>0){
      // draw processed to temp, then blur with canvas filter
      const tmpC = document.createElement('canvas');
      tmpC.width=procCanvas.width; tmpC.height=procCanvas.height;
      const tmpCtx = tmpC.getContext('2d');
      tmpCtx.putImageData(imageData,0,0);
      procCtx.clearRect(0,0,procCanvas.width,procCanvas.height);
      procCtx.filter = `blur(${editBlur.value}px)`;
      procCtx.drawImage(tmpC,0,0);
      procCtx.filter = 'none';
    } else {
      procCtx.putImageData(imageData,0,0);
    }

    // 5) Atualiza textura da esfera
    baseMaterial.map = new THREE.CanvasTexture(procCanvas);
    baseMaterial.needsUpdate = true;

    // 6) Atualiza histogramas
    // original
    const tmpOrig = document.createElement('canvas');
    tmpOrig.width=procCanvas.width; tmpOrig.height=procCanvas.height;
    tmpOrig.getContext('2d').drawImage(img,0,0,procCanvas.width,procCanvas.height);
    drawHistogram(computeHistogram(tmpOrig.getContext('2d').getImageData(0,0,procCanvas.width,procCanvas.height)), ctxO);
    // processado
    drawHistogram(computeHistogram(procCtx.getImageData(0,0,procCanvas.width,procCanvas.height)), ctxP);
  }

  // ——————————————————————————————————————————————————————
  // Listeners
  const controlsList = [
    'gsMethod','gsThresh','gsBlack','gsWhite','gsBg',
    'editBright','editContrast','editExposure','editBlur',
    'trRotate','trCropW','trCropH','trMirror','trFlip'
  ];
  const getEl = id=>document.getElementById(id);
  controlsList.forEach(id=> getEl(id).addEventListener('input',applyProcessing));

  // upload
  document.getElementById('imageInput').addEventListener('change', e=>{
    const file = e.target.files[0];
    if(!file) return;
    const reader = new FileReader();
    reader.onload = ev=>{
      img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  });

  // botões esfera
  document.getElementById('toggleLight').addEventListener('click', ()=> {
    directionalLight.visible = !directionalLight.visible;
  });
  document.getElementById('toggleWireframe').addEventListener('click', ()=>{
    baseMaterial.wireframe = !baseMaterial.wireframe;
  });
  document.getElementById('transparencySlider').addEventListener('input', function(){
    const v = +this.value;
    baseMaterial.opacity     = 1 - v/100;
    baseMaterial.transparent = baseMaterial.opacity < 1;
  });

  // exportar imagem tratada
  document.getElementById('exportImg').addEventListener('click', ()=>{
    const a = document.createElement('a');
    a.href = procCanvas.toDataURL('image/png');
    a.download = 'litofania-processada.png';
    a.click();
  });

  // loop Three.js
  (function animate(){
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene,camera);
  })();

  // responsive
  window.addEventListener('resize', ()=>{
    camera.aspect = (window.innerWidth-250)/window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth-250, window.innerHeight);
  });
  </script>
</body>
</html>
