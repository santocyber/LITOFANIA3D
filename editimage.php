

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <title>Editor Litofania 3D</title>

  <script type="importmap">
  {
    "imports": {
      "three": "./js/three.module.js",
      "three/addons/": "./js/"
    }
  }
  </script>

  <style>
    body { margin:0; overflow:hidden; font-family:sans-serif; }
    #ui {
      position:absolute; top:10px; left:10px;
      background:rgba(255,255,255,0.9); padding:10px;
      border-radius:4px; z-index:10;
      width:200px;
    }
    #ui label, #ui button { display:block; margin:8px 0; }
    #ui input, #ui button { width:100%; }
    #ui span { font-weight:bold; }
  </style>
</head>
<body>
  <!-- 2) Formulário oculto para envio do STL -->
  <form id="saveForm" method="post" style="display:none;">
    <input type="hidden" name="stl" id="stlInput">
  </form>
  
  <div id="ui">
    <input type="file" id="imgInput" accept="image/*">

    <label>
      Resolução: <span id="resVal">100</span>
      <input type="range" id="resSlider" min="20" max="1000" value="100">
    </label>

    <label>
      Contraste: <span id="ctrVal">1.0</span>
      <input type="range" id="ctrSlider" min="0.5" max="3" step="0.1" value="1">
    </label>

    <label>
      Escala Cinza: <span id="grayVal">1.0</span>
      <input type="range" id="graySlider" min="0.1" max="2" step="0.1" value="1">
    </label>

    <label>
      Altura Mínima (mm): <span id="minHVal">0</span>
      <input type="range" id="minHSlider" min="0" max="50" step="1" value="0">
    </label>

    <label>
      Altura Máxima (mm): <span id="maxHVal">25</span>
      <input type="range" id="maxHSlider" min="1" max="100" step="1" value="25">
    </label>

    <button id="resetBtn">Reset Padrões</button>
    
    <button id="sendModelBtn">Mirako me leve para o proximo passo...</button>

  </div>

  <script type="module">
    import * as THREE from 'three';
    import { OrbitControls } from 'three/addons/OrbitControls.js';
import { STLExporter } from 'three/addons/STLExporter.js';


    // — parâmetros iniciais —
    const defaults = {
      res: 100,
      ctr: 1,
      gray: 1,
      minH: 0,
      maxH: 25
    };

    let img, canvas2d, ctx2d, scene, camera, renderer, controls, mesh;

    // — referências UI —
    const imgInput    = document.getElementById('imgInput');
    const resSlider   = document.getElementById('resSlider');
    const ctrSlider   = document.getElementById('ctrSlider');
    const graySlider  = document.getElementById('graySlider');
    const minHSlider  = document.getElementById('minHSlider');
    const maxHSlider  = document.getElementById('maxHSlider');
    const resetBtn    = document.getElementById('resetBtn');
    const resVal      = document.getElementById('resVal');
    const ctrVal      = document.getElementById('ctrVal');
    const grayVal     = document.getElementById('grayVal');
    const minHVal     = document.getElementById('minHVal');
    const maxHVal     = document.getElementById('maxHVal');
    const sendBtn    = document.getElementById('sendModelBtn');
    const stlInput   = document.getElementById('stlInput');


    // — Init Three.js —
    function initThree() {
      scene    = new THREE.Scene();
      camera   = new THREE.PerspectiveCamera(45, innerWidth/innerHeight, 0.1, 1000);
      camera.position.set(0, 0, 150);

      renderer = new THREE.WebGLRenderer({antialias:true});
      renderer.setSize(innerWidth, innerHeight);
      document.body.appendChild(renderer.domElement);

      controls = new OrbitControls(camera, renderer.domElement);
      controls.enableDamping = true;

      scene.add(new THREE.AmbientLight(0x888888));
      const dir = new THREE.DirectionalLight(0xffffff, 1);
      dir.position.set(0, 1, 1).normalize();
      scene.add(dir);

      animate();
    }

    function animate() {
      requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    }

    // — Canvas 2D off-screen —
    canvas2d = document.createElement('canvas');
    ctx2d    = canvas2d.getContext('2d');

    // — Processa imagem: grayscale, escala e contraste —
    function processImage(res, contrast, grayScale) {
      canvas2d.width = canvas2d.height = res;
      ctx2d.drawImage(img, 0, 0, res, res);
      const data = ctx2d.getImageData(0, 0, res, res);
      for (let i = 0; i < data.data.length; i += 4) {
        let gray = 0.2126*data.data[i] + 0.7152*data.data[i+1] + 0.0722*data.data[i+2];
        gray = gray * grayScale;
        const g2 = ((gray - 128) * contrast) + 128;
        const g  = Math.max(0, Math.min(255, g2));
        data.data[i] = data.data[i+1] = data.data[i+2] = g;
      }
      ctx2d.putImageData(data, 0, 0);
      return data.data;
    }

    // — (Re)gera a malha 3D usando min/max heights —
    function updateMesh() {
      const res     = +resSlider.value;
      const ctr     = +ctrSlider.value;
      const gray    = +graySlider.value;
      const minH    = +minHSlider.value;
      const maxH    = +maxHSlider.value;
      resVal.textContent  = res;
      ctrVal.textContent  = ctr.toFixed(1);
      grayVal.textContent = gray.toFixed(1);
      minHVal.textContent = minH;
      maxHVal.textContent = maxH;

      if (!img) return;
      const pixels = processImage(res, ctr, gray);

      if (mesh) scene.remove(mesh);

      // grid res×res → PlaneGeometry(100×100) subdividido
      const geo = new THREE.PlaneGeometry(100, 100, res-1, res-1);
      const pos = geo.attributes.position.array;

      for (let i = 0; i < pos.length; i += 3) {
        const xi  = ((i/3) % res) | 0;
        const yi  = Math.floor((i/3) / res);
        const idx = (yi*res + xi)*4;
        const v   = pixels[idx];  // 0–255
        // mapeia para altura entre minH e maxH
        pos[i+2] = minH + (v/255)*(maxH - minH);
      }

      geo.computeVertexNormals();
      mesh = new THREE.Mesh(
        geo,
        new THREE.MeshStandardMaterial({
          color: 0xffffff,
          side: THREE.DoubleSide,
          transparent: true,
          opacity: 0.9
        })
      );
      mesh.rotation.x = -Math.PI/2;
      scene.add(mesh);
    }

    // — Handlers UI —
    imgInput.addEventListener('change', e => {
      const file = e.target.files[0];
      if (!file) return;
      img = new Image();
      img.onload = () => updateMesh();
      img.src = URL.createObjectURL(file);
    });
    [resSlider, ctrSlider, graySlider, minHSlider, maxHSlider]
      .forEach(el => el.addEventListener('input', updateMesh));
    resetBtn.addEventListener('click', () => {
      resSlider.value  = defaults.res;
      ctrSlider.value  = defaults.ctr;
      graySlider.value = defaults.gray;
      minHSlider.value = defaults.minH;
      maxHSlider.value = defaults.maxH;
      updateMesh();
    });



sendBtn.addEventListener('click', async () => {
  if (!mesh) {
    alert('Gere o modelo primeiro!');
    return;
  }

  // 1) exporta STL para string
  const exporter  = new STLExporter();
  const stlString = exporter.parse(mesh);

  // 2) cria um Blob e envia o body cru
  const blob = new Blob([stlString], { type: 'application/octet-stream' });

  try {
    // 3) envia via fetch para save_model.php
    const res = await fetch('save_model.php', {
      method: 'POST',
      body: blob
    });

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();
    console.log(`Gravação levou ${data.save_time.toFixed(3)} segundos`);

    if (!data.filename) {
      alert('Erro ao salvar modelo: ' + (data.error || 'desconhecido'));
      return;
    }

    // 4) form POST leve só com o nome do arquivo
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'page2.php';

    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'file';
    input.value = data.filename;
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();

  } catch (err) {
    console.error(err);
    alert('Falha na requisição: ' + err.message);
  }
});





    // — start —
    initThree();
  </script>

</body>
</html>
