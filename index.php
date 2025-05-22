

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
<link
  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
  rel="stylesheet"
/>

 <style>
    body { margin:0; overflow:hidden; font-family:sans-serif; }
    /* seu estilo original ficou dentro do aside */
    #canvasContainer canvas { display:block; } /* remove scrollbars do canvas */
  </style>
</head>
<body>
    
    
<body class="vh-100 d-flex flex-column p-0 m-0">
  <div class="container-fluid h-100 p-0">
    <div class="row h-100 g-0">
      <!-- painel de controles: 12 cols em xs, 3 cols em md+ -->
      <aside
        id="ui"
        class="col-12 col-md-3 bg-light p-3 overflow-auto"
        style="max-height:100vh;"
      >




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

<label>
  Repetições: <span id="repVal">1</span>
  <input type="range" id="repSlider" min="1" max="8" step="1" value="1">
</label>

<label>
  Offset Inicial (°): <span id="phiStartVal">0</span>
  <input type="range" id="phiStartSlider" min="0" max="90" step="1" value="0">
</label>

<label>
  Offset Final (°): <span id="phiEndVal">180</span>
  <input type="range" id="phiEndSlider" min="90" max="180" step="1" value="180">
</label>


    <button id="resetBtn">Reset Padrões</button>
    
    <button id="sendModelBtn">Mirako me leve para o proximo passo...</button>

  </div>
  
  <!-- logo antes do </body> -->
<div id="modalOverlay" style="
  position:fixed; top:0; left:0; width:100%; height:100%;
  background:rgba(0,0,0,0.5); display:none; align-items:center; justify-content:center;
  z-index:1000;
">
  <div style="
    background:#fff; padding:20px; border-radius:4px; width:300px; text-align:center;
  ">
    <p id="modalMessage">…</p>
    <button id="closeModalBtn" style="margin-top:10px;">Fechar</button>
  </div>
</div>












  </div>
</div>


      </aside>

      <!-- área de renderização: 12 cols em xs, 9 cols em md+ -->
      <main id="canvasContainer" class="col-12 col-md-9 p-0">
        <!-- o Three.js vai injetar o <canvas> aqui -->
      </main>
    </div>
  </div>






<!-- logo antes de </body> -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>



  <script type="module">
    import * as THREE from 'three';
    import { OrbitControls } from 'three/addons/OrbitControls.js';
import { STLExporter } from 'three/addons/STLExporter.js';


// --- modal helpers ---
const modalOverlay   = document.getElementById('modalOverlay');
const modalMessage   = document.getElementById('modalMessage');
const closeModalBtn  = document.getElementById('closeModalBtn');

function showModal(msg) {
  modalMessage.textContent = msg;
  modalOverlay.style.display = 'flex';
}
function hideModal() {
  modalOverlay.style.display = 'none';
}
closeModalBtn.addEventListener('click', hideModal);

// — parâmetros iniciais —
const defaults = {
  res: 100, ctr: 1, gray: 1, minH: 0, maxH: 25,
  reps: 1,
  phiStartDeg: 0,
  phiEndDeg: 180
};



    let img, canvas2d, ctx2d, scene, camera, renderer, controls, mesh;
let meshGroup = null;      // <-- aqui

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

const repSlider      = document.getElementById('repSlider');
const repVal         = document.getElementById('repVal');
const phiStartSlider = document.getElementById('phiStartSlider');
const phiEndSlider   = document.getElementById('phiEndSlider');
const phiStartVal    = document.getElementById('phiStartVal');
const phiEndVal      = document.getElementById('phiEndVal');



    // — Init Three.js —
function initThree() {
  scene    = new THREE.Scene();

  // ângulo de visão, aspect, near, far
  camera   = new THREE.PerspectiveCamera(45, window.innerWidth/window.innerHeight, 0.1, 1000);
  // 1) aumenta o Z para “afastar” (zoom out) e sobe o Y para ver a esfera por cima
  camera.position.set(0, 200, 900);

  renderer = new THREE.WebGLRenderer({antialias:true});
  renderer.setSize(window.innerWidth, window.innerHeight);
  document.getElementById('canvasContainer').appendChild(renderer.domElement);

  controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;

  // 2) desloca o ponto que a câmera olha (o “centro” da esfera) para cima
  controls.target.set(0, -50, 0);
  controls.update();  // importante para que o novo target seja aplicado

  // (opcional) limite de zoom
  controls.minDistance = 100;
  controls.maxDistance = 600;

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





// fora do updateMesh(), no topo do arquivo:
const SECTOR_OFFSETS = [];
for (let t = 0; t < 2; t++) {
  for (let p = 0; p < 4; p++) {
    SECTOR_OFFSETS.push({
      phiStart: p * Math.PI/2,
      thetaStart: t * Math.PI/2
    });
  }
}
// ficamos com 8 combinações: [ {0,0}, {π/2,0}, {π,0}, {3π/2,0}, {0,π/2}, … ]

function updateMesh() {
  if (!img) return;

  // — 1) lê valores e atualiza UI —
  const res        = +resSlider.value;
  const ctr        = +ctrSlider.value;
  const gray       = +graySlider.value;
  const minH       = +minHSlider.value;
  const maxH       = +maxHSlider.value;
  const reps       = +repSlider.value;
  const phiStartD  = +phiStartSlider.value;
  const phiEndD    = +phiEndSlider.value;

  resVal.textContent       = res;
  ctrVal.textContent       = ctr.toFixed(1);
  grayVal.textContent      = gray.toFixed(1);
  minHVal.textContent      = minH;
  maxHVal.textContent      = maxH;
  repVal.textContent       = reps;
  phiStartVal.textContent  = phiStartD;
  phiEndVal.textContent    = phiEndD;

  // garante pelo menos 1° de banda
  const bandDeg   = Math.max(1, phiEndD - phiStartD);
  const phiStart  = THREE.MathUtils.degToRad(phiStartD);
  const phiLength = THREE.MathUtils.degToRad(bandDeg);

  // — 2) processa a imagem —
  const pixels = processImage(res, ctr, gray);

  // — 3) limpa mesh antigo —
  if (meshGroup) {
    scene.remove(meshGroup);
    meshGroup.children.forEach(m => {
      m.geometry.dispose();
      m.material.dispose();
    });
    meshGroup = null;
  }

  // — 4) preenche novo grupo —
  meshGroup = new THREE.Group();
  const R_base      = 50;
  const thetaLength = (2 * Math.PI) / 8;  // cada setor: 360°/8
  const mat = new THREE.MeshStandardMaterial({
    color: 0xffffff,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.9
  });

  for (let i = 0; i < reps; i++) {
    const thetaStart = i * thetaLength;
    const geo = new THREE.SphereGeometry(
      R_base,
      res - 1,
      res - 1,
      thetaStart,        // thetaStart
      thetaLength,       // thetaLength
      phiStart,          // phiStart
      phiLength          // phiLength
    );

    // aplica o height-map radial
    const pos = geo.attributes.position.array;
    for (let j = 0; j < pos.length; j += 3) {
      const vidx = (j/3)|0;
      const xi   = vidx % res;
      const yi   = Math.floor(vidx / res);
      const pidx = (yi * res + xi) * 4;
      const v    = pixels[pidx];
      const h    = minH + (v/255)*(maxH - minH);

      const x = pos[j], y = pos[j+1], z = pos[j+2];
      const r = Math.sqrt(x*x + y*y + z*z);
      const newR = r + h;

      pos[j]   = (x/r)*newR;
      pos[j+1] = (y/r)*newR;
      pos[j+2] = (z/r)*newR;
    }
    geo.computeVertexNormals();

    meshGroup.add(new THREE.Mesh(geo, mat));
  }

  // — 5) adiciona ao scene —
  scene.add(meshGroup);
}




// 1) dispara a geração assim que a imagem carrega
imgInput.addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  img = new Image();
  img.onload = updateMesh;
  img.src = URL.createObjectURL(file);
});

// 2) refaz o mesh sempre que um slider muda
[resSlider, ctrSlider, graySlider, minHSlider, maxHSlider]
  .forEach(el => el.addEventListener('input', updateMesh));

// 3) reset dos padrões
resetBtn.addEventListener('click', () => {
  resSlider.value  = defaults.res;
  ctrSlider.value  = defaults.ctr;
  graySlider.value = defaults.gray;
  minHSlider.value = defaults.minH;
  maxHSlider.value = defaults.maxH;
  repSlider.value      = defaults.reps;
  phiStartSlider.value = defaults.phiStartDeg;
  phiEndSlider.value   = defaults.phiEndDeg;

  
  
  updateMesh();
});



[repSlider, phiStartSlider, phiEndSlider]
  .forEach(el => el.addEventListener('input', updateMesh));
  
  
sendBtn.addEventListener('click', async () => {
  // 1) checa se o grupo de meshes existe
  if (!meshGroup || meshGroup.children.length === 0) {
    alert('Gere o modelo primeiro!');
    return;
  }

  // 2) pede confirmação visual e bloqueia interface
  showModal('Salvando arquivo…');

  // 3) exporta STL de todo o grupo
  const exporter  = new STLExporter();
  // parse aceita um Object3D (Group), não só Mesh
  const stlString = exporter.parse(meshGroup);

  // 4) cria e envia o blob
  const blob = new Blob([stlString], { type: 'application/octet-stream' });

  try {
      
      const res = await fetch('save_model.php', {
  method: 'POST',
  body: blob
});
const data = await res.json();
console.log(`Gravação levou ${data.save_time.toFixed(3)}s — arquivo: ${data.filename}`);

    if (!data.filename) {
      throw new Error(data.error || 'Nome de arquivo não retornado');
    }

    // 5) sucesso: informa e, opcionalmente, redireciona
    showModal(`Arquivo salvo: ${data.filename}`);
    // Se quiser ir para page2 imediatamente:
    // window.location = `page2.php?file=${encodeURIComponent(data.filename)}`;
    // Ou, se usar form POST:
    // const form = document.createElement('form');
    // form.method = 'POST';
    // form.action = 'page2.php';
    // form.innerHTML = `<input type="hidden" name="file" value="${data.filename}">`;
    // document.body.appendChild(form);
    // form.submit();

  } catch (err) {
    console.error(err);
    showModal(`Erro ao salvar: ${err.message}`);
  }
});


    // ajuste dinâmico ao redimensionar:
    window.addEventListener('resize', () => {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(innerWidth, innerHeight);
    });



    // — start —
    initThree();
  </script>








</body>
</html>
