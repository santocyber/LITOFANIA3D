

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

<label>
  Repetições: <span id="repVal">1</span>
  <input type="range" id="repSlider" min="1" max="8" step="1" value="1">
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
      res: 200,
      ctr: 1,
      gray: 0.1,
      minH: 0,
      maxH: 25,
       reps: 8
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

const repSlider = document.getElementById('repSlider');
const repVal    = document.getElementById('repVal');





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

  // 1) parâmetros + UI
  const res   = +resSlider.value;
  const ctr   = +ctrSlider.value;
  const gray  = +graySlider.value;
  const minH  = +minHSlider.value;
  const maxH  = +maxHSlider.value;
  const reps  = +repSlider.value;

  resVal.textContent  = res;
  ctrVal.textContent  = ctr.toFixed(1);
  grayVal.textContent = gray.toFixed(1);
  minHVal.textContent = minH;
  maxHVal.textContent = maxH;
  repVal.textContent  = reps;

  // 2) processa a imagem
  const pixels = processImage(res, ctr, gray);

  // 3) limpa mesh anterior
  if (meshGroup) {
    scene.remove(meshGroup);
    // dispose de geometria e material de cada filho
    meshGroup.children.forEach(m => {
      m.geometry.dispose();
      m.material.dispose();
    });
  }

  // 4) cria um novo group
  meshGroup = new THREE.Group();

  // 5) para cada repetição, gera um setor esférico
  const R_base     = 50;
  const phiLength  = Math.PI/2;
  const thetaLength= Math.PI/2;
  const mat = new THREE.MeshStandardMaterial({
    color: 0xffffff,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.9
  });

  for (let i = 0; i < reps; i++) {
    const off = SECTOR_OFFSETS[i];
    const geo = new THREE.SphereGeometry(
      R_base,
      res - 1,
      res - 1,
      off.phiStart, phiLength,
      off.thetaStart, thetaLength
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

      const x = pos[j],   y = pos[j+1], z = pos[j+2];
      const r = Math.sqrt(x*x + y*y + z*z);
      const newR = r + h;

      pos[j]   = (x/r)*newR;
      pos[j+1] = (y/r)*newR;
      pos[j+2] = (z/r)*newR;
    }
    geo.computeVertexNormals();

    const sectorMesh = new THREE.Mesh(geo, mat);
    meshGroup.add(sectorMesh);
  }

  // 6) adiciona ao scene
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
  updateMesh();
});



repSlider.addEventListener('input', updateMesh);

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





    // — start —
    initThree();
  </script>

</body>
</html>
