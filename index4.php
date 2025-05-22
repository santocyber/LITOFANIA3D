<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Editor de Litofania 3D</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<script type="importmap">
{
  "imports": {
    "three": "./js/three.module.js",
    "three/addons/": "./js/"
  }
}
</script>







  <style>
    body { margin: 0; background: #121212; color: #fff; font-family: sans-serif; overflow: hidden; }
    .side-panel {
      position: fixed; top: 20px; right: 20px;
      background: #1f1f1f; padding: 20px; border-radius: 8px; 
      border: 1px solid #444; width: 350px; z-index: 10; 
      max-height: 95vh; overflow-y: auto;
    }
    canvas { display: block; }
  </style>
</head>
<body>

<div class="side-panel">
  <h3>Litofania 3D</h3>
  
  <label>Imagens:</label>
  <input type="file" id="imageUpload" multiple accept="image/*" class="form-control form-control-sm mb-2">

  <label>Resolução:</label>
  <input type="number" id="resolution" value="0.25" step="0.01" min="0.05" max="5" class="form-control form-control-sm mb-2">

  <label>Espessura Mín (mm):</label>
  <input type="number" id="thicknessMin" value="0.6" step="0.1" class="form-control form-control-sm mb-2">

  <label>Espessura Máx (mm):</label>
  <input type="number" id="thicknessMax" value="2.7" step="0.1" class="form-control form-control-sm mb-2">

  <label>Diâmetro Esfera (mm):</label>
  <input type="number" id="sphereDiameter" value="140" step="1" class="form-control form-control-sm mb-2">

  <label>Largura Angular (°):</label>
  <input type="number" id="angularWidth" value="114.2" step="0.1" class="form-control form-control-sm mb-2">

  <label>Altura Angular (°):</label>
  <input type="number" id="angularHeight" value="90" step="0.1" class="form-control form-control-sm mb-2">

  <label>Furo Inferior (mm):</label>
  <input type="number" id="baseHoleDiameter" value="20" step="1" class="form-control form-control-sm mb-2">

  <label>Furo Superior (mm):</label>
  <input type="number" id="topHoleDiameter" value="0" step="1" class="form-control form-control-sm mb-2">

<label>Altura do Anel (%):</label>
<input type="range" id="baseSupportHeightSlider" value="0" min="0" max="100" step="1">
<input type="number" id="baseSupportHeightNumber" value="0" min="0" max="100" step="1" class="form-control form-control-sm mb-2">

  <label>Expessura Base (mm):</label>
  <input type="number" id="baseSupportThickness" value="2" step="0.1" class="form-control form-control-sm mb-2">

  <label>Largura Base (mm):</label>
  <input type="number" id="baseSupportWidth" value="5" step="0.1" class="form-control form-control-sm mb-2">

  <label>Espessura Casca (mm):</label>
  <input type="number" id="shellThickness" value="1" step="0.1" class="form-control form-control-sm mb-2">

  <label>Largura Imagem (px):</label>
  <input type="number" id="imgWidth" value="200" min="1" class="form-control form-control-sm mb-2">

  <label>Altura Imagem (px):</label>
  <input type="number" id="imgHeight" value="200" min="1" class="form-control form-control-sm mb-2">

  <label>Exposição:</label>
  <input type="range" id="exposureLevel" min="0.5" max="2" step="0.1" value="1" class="form-range mb-2">

  <label>Saturação:</label>
  <input type="range" id="saturationLevel" min="0.5" max="2" step="0.1" value="1" class="form-range mb-2">

<label>Contraste:</label>
<input type="range" id="contrastLevel" min="0" max="3" step="0.1" value="1" class="form-range mb-2">

<label for="rotationAngle">Rotação</label>
  <input type="range" id="rotationAngle" min="0" max="360" step="1" value="0">
  <input type="number" id="rotationAngleNumber" min="0" max="360" step="1" value="0" style="width: 60px;">

  <label>Offset φ (°):</label>
  <input type="number" id="offsetPhi" value="0" step="0.1" class="form-control form-control-sm mb-2">

  <label>Offset θ (°):</label>
  <input type="number" id="offsetTheta" value="0" step="0.1" class="form-control form-control-sm mb-2">

  <hr>

  <label>Cor do Material:</label>
  <input type="color" id="materialColor" value="#ffffff" class="form-control form-control-sm mb-2">

  <label>Opacidade:</label>
  <input type="range" id="materialOpacity" min="0.1" max="1" step="0.1" value="1" class="form-range mb-2">

  <div class="form-check mb-2">
    <input type="checkbox" class="form-check-input" id="showWireframe">
    <label class="form-check-label" for="showWireframe">Visualizar casca interna</label>
  </div>

  <div class="form-check mb-3">
    <input type="checkbox" class="form-check-input" id="toggleLight" checked>
    <label class="form-check-label" for="toggleLight">Ligar/desligar luz interna</label>
  </div>

  <button id="generateBtn" class="btn btn-primary btn-sm mb-2">Gerar Litofania</button>
  <button id="exportBtn" class="btn btn-secondary btn-sm">Exportar STL</button>
</div>

<div style="position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,0.7); padding: 5px; border-radius: 5px; z-index: 1000;">
  
  <div style="display: flex; flex-direction: column; gap: 5px;">
    
    <!-- Canvas imagem em tons de cinza -->
    <canvas id="grayCanvas" width="200" height="200" style="border: 1px solid white; width: 200px; height: 200px;"></canvas>

    <!-- Canvas imagem de relevo -->
    <canvas id="reliefCanvas" width="200" height="200" style="border: 1px solid white; width: 200px; height: 200px;"></canvas>
  
  </div>

  <!-- Canvas histograma -->
  <canvas id="histogram" width="256" height="100" style="margin-top: 5px; border: 1px solid white;"></canvas>

</div>




<script type="module">
import * as THREE from './js/three.module.js';
import { OrbitControls } from './js/OrbitControls.js';
import { STLExporter } from './js/STLExporter.js';
import CSG from './js/three-csg.js';

let scene, camera, renderer, controls, lightInternal, currentMaterial;
let geometries = [];
const segments = 16;

init();

function init() {
  scene = new THREE.Scene();

  camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
  camera.position.set(0, 0, 200);

  renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setSize(window.innerWidth, window.innerHeight);
  document.body.appendChild(renderer.domElement);

  controls = new OrbitControls(camera, renderer.domElement);

  const ambientLight = new THREE.AmbientLight(0xffffff, 0.3);
  scene.add(ambientLight);

  const dirLight1 = new THREE.DirectionalLight(0xffffff, 0.8);
  dirLight1.position.set(50, 50, 50);
  scene.add(dirLight1);

  const dirLight2 = new THREE.DirectionalLight(0xffffff, 0.8);
  dirLight2.position.set(-50, 50, 50);
  scene.add(dirLight2);

  const dirLight3 = new THREE.DirectionalLight(0xffffff, 0.8);
  dirLight3.position.set(0, -50, 50);
  scene.add(dirLight3);

  lightInternal = new THREE.PointLight(0xffffff, 1, 500);
  scene.add(lightInternal);

  createInitialCSG();

  animate();
}

function createInitialCSG() {
  const material = new THREE.MeshStandardMaterial({ color: 0xffffff });
  const meshA = new THREE.Mesh(new THREE.BoxGeometry(2, 2, 2), material);
  const meshB = new THREE.Mesh(new THREE.SphereGeometry(1.3, 32, 32), material);

  meshA.updateMatrix();
  meshB.updateMatrix();

  const bspA = CSG.fromMesh(meshA);
  const bspB = CSG.fromMesh(meshB);
  const bspResult = bspA.subtract(bspB);

  const meshResult = CSG.toMesh(bspResult, meshA.matrix, meshA.material);

  scene.add(meshResult);
  geometries.push(meshResult.geometry);
}

function animate() {
  requestAnimationFrame(animate);
  renderer.render(scene, camera);
}

function clearScene() {
  geometries.forEach(g => g.dispose());
  geometries = [];
  scene.children = scene.children.filter(obj => obj.type !== 'Mesh');
}















async function loadImageAsGrayScale(file, width, height) {
  const img = new Image();
  const reader = new FileReader();

  return new Promise(resolve => {
    reader.onload = e => img.src = e.target.result;

    img.onload = () => {
      const contrast = parseFloat(document.getElementById('contrastLevel').value);
      const exposure = parseFloat(document.getElementById('exposureLevel').value);
      const saturation = parseFloat(document.getElementById('saturationLevel').value);
      const rotation = parseFloat(document.getElementById('rotationAngle').value);  // em graus

      const grayCanvas = document.getElementById('grayCanvas');
      grayCanvas.width = width;
      grayCanvas.height = height;

      const ctx = grayCanvas.getContext('2d');

      // Rotacionar
      ctx.save();
      ctx.translate(width / 2, height / 2);
      ctx.rotate(rotation * Math.PI / 180);
      ctx.translate(-width / 2, -height / 2);
      ctx.drawImage(img, 0, 0, width, height);
      ctx.restore();

      const imageData = ctx.getImageData(0, 0, width, height);
      const data = imageData.data;

      const gray = new Uint8Array(width * height);
      const histogram = new Array(256).fill(0);

      for (let i = 0; i < gray.length; i++) {
        let r = data[i * 4];
        let g = data[i * 4 + 1];
        let b = data[i * 4 + 2];

        // Ajuste de saturação
        const avg = (r + g + b) / 3;
        r = avg + (r - avg) * saturation;
        g = avg + (g - avg) * saturation;
        b = avg + (b - avg) * saturation;

        // Ajuste de exposição
        r = r * exposure;
        g = g * exposure;
        b = b * exposure;

        r = Math.max(0, Math.min(255, r));
        g = Math.max(0, Math.min(255, g));
        b = Math.max(0, Math.min(255, b));

        let val = Math.round((r + g + b) / 3);

        // Ajuste de contraste
        val = ((val - 128) * contrast) + 128;
        val = Math.max(0, Math.min(255, val));

        gray[i] = val;
        histogram[val]++;
      }

      // Mostrar imagem em tons de cinza
      for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
          const i = y * width + x;
          const val = gray[i];
          ctx.fillStyle = `rgb(${val},${val},${val})`;
          ctx.fillRect(x, y, 1, 1);
        }
      }

      drawHistogram(histogram);

      resolve(gray);
    };

    reader.readAsDataURL(file);
  });
}





function drawHistogram(histogram) {
  const canvas = document.getElementById('histogram');
  const ctx = canvas.getContext('2d');
  
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  
  const max = Math.max(...histogram);
  
  for (let i = 0; i < 256; i++) {
    const value = histogram[i];
    const barHeight = (value / max) * canvas.height;
    
    ctx.fillStyle = '#0f0';
    ctx.fillRect(i, canvas.height - barHeight, 1, barHeight);
  }
}


const resolution = parseFloat(document.getElementById('resolution').value);


function drawLithophanePreview(reliefMap, width, height, tMin, tMax, resolution) {
  const canvas = document.getElementById('reliefCanvas');
  if (!canvas) {
    console.error("Canvas #reliefCanvas não encontrado!");
    return;
  }

  canvas.width = width;
  canvas.height = height;

  const ctx = canvas.getContext('2d');
  const imageData = ctx.createImageData(width, height);

  for (let i = 0; i < reliefMap.length; i++) {
    const thickness = reliefMap[i];

    // Aplicar resolução como amplificador de relevo
    const adjusted = thickness * resolution;

    // Normaliza ajustado para 0-255
    const normalized = Math.round((adjusted - tMin) / (tMax - tMin) * 255);
    const color = Math.max(0, Math.min(255, normalized));

    const idx = i * 4;
    imageData.data[idx] = color;             // R
    imageData.data[idx + 1] = 0;             // G
    imageData.data[idx + 2] = 255 - color;   // B
    imageData.data[idx + 3] = 255;           // A
  }

  ctx.putImageData(imageData, 0, 0);
}






function generateReliefMap(grayData, width, height, tMin, tMax) {
  const reliefMap = new Float32Array(grayData.length);
  
  for (let i = 0; i < grayData.length; i++) {
    const gray = grayData[i];
    const thickness = tMin + (1 - gray / 255) * (tMax - tMin);
    reliefMap[i] = thickness;
  }

  return reliefMap;
}




function createMaterial(params) {
  return new THREE.MeshStandardMaterial({
    color: params.materialColor,
    opacity: params.materialOpacity,
    transparent: params.materialOpacity < 1,
    wireframe: params.showWireframe
  });
}



function createSphereWithHoles(radius, shell, topHole, bottomHole) {
  const outer = new THREE.Mesh(new THREE.SphereGeometry(radius + shell, segments, segments));
  const inner = new THREE.Mesh(new THREE.SphereGeometry(radius, segments, segments));

  outer.updateMatrix();
  inner.updateMatrix();

  let result = CSG.fromMesh(outer).subtract(CSG.fromMesh(inner));

  const cutLength = (radius + shell) * 3;  // Maior que o diâmetro para garantir corte total

  if (topHole > 0) {
    const topCut = new THREE.Mesh(new THREE.CylinderGeometry(topHole / 2, topHole / 2, cutLength, 32));
    topCut.rotation.x = Math.PI / 2;  // Orienta cilindro no eixo Y
    topCut.position.set(0, radius, 0);
    topCut.updateMatrix();
    result = result.subtract(CSG.fromMesh(topCut));
  }

  if (bottomHole > 0) {
    const bottomCut = new THREE.Mesh(new THREE.CylinderGeometry(bottomHole / 2, bottomHole / 2, cutLength, 32));
    bottomCut.rotation.x = Math.PI / 2;
    bottomCut.position.set(0, -radius, 0);
    bottomCut.updateMatrix();
    result = result.subtract(CSG.fromMesh(bottomCut));
  }

  const mesh = CSG.toMesh(result, outer.matrix, currentMaterial);
  scene.add(mesh);
  geometries.push(mesh.geometry);
}

function createSphereWithPolarHoles(radius, shell, polarHoleAngleDegrees) {
  const openAngle = THREE.MathUtils.degToRad(polarHoleAngleDegrees);

  const thetaStart = openAngle;
  const thetaLength = Math.PI - 2 * openAngle;  // Corta simetricamente no topo e base

  const geometry = new THREE.SphereGeometry(
    radius + shell,
    segments,
    segments,
    0,              // phiStart → começo horizontal (longitude)
    Math.PI * 2,    // phiLength → cobre toda a volta
    thetaStart,     // thetaStart → vertical, corta no topo
    thetaLength     // thetaLength → altura restante
  );

  const mesh = new THREE.Mesh(geometry, currentMaterial);
  scene.add(mesh);
  geometries.push(mesh.geometry);
}



function createBaseRing(radius, baseWidth, baseThickness, baseHeightPercent) {
  if (baseThickness <= 0) {
    alert("Espessura inválida para a base (baseThickness). Deve ser maior que 0.");
    return;
  }
  if (baseWidth <= 0) {
    alert("Largura inválida para a base (baseWidth). Deve ser maior que 0.");
    return;
  }

  const innerRadius = radius;
  const outerRadius = radius + baseWidth;

  // Ajuste: limita entre [0, 100]
  const safeHeightPercent = Math.min(100, Math.max(0, baseHeightPercent));

  // Calcula posição Y, compensando a metade da espessura
  const rawY = -radius + (safeHeightPercent / 100) * (2 * radius);
  const positionY = Math.max(-radius, Math.min(radius, rawY));  // clamp

  // Cria cilindro externo
  const outer = new THREE.Mesh(
    new THREE.CylinderGeometry(outerRadius, outerRadius, baseThickness, 64)
  );

  // Cria cilindro interno para subtrair
  const inner = new THREE.Mesh(
    new THREE.CylinderGeometry(innerRadius, innerRadius, baseThickness + 2, 64)
  );

  // Ambos na mesma posição
  outer.position.y = positionY;
  inner.position.y = positionY;

  outer.updateMatrix();
  inner.updateMatrix();

  // Subtrai para formar o anel
  const bspOuter = CSG.fromMesh(outer);
  const bspInner = CSG.fromMesh(inner);
  const bspResult = bspOuter.subtract(bspInner);

  const mesh = CSG.toMesh(bspResult, outer.matrix, currentMaterial);
  scene.add(mesh);
  geometries.push(mesh.geometry);
}






async function createLithophaneShell(textureData, params) {
  const { radius, shellThickness, imgWidth, imgHeight, tMin, tMax, topHole, bottomHole } = params;

  // Criar geometria da casca externa
  const geometry = new THREE.SphereGeometry(radius + shellThickness, segments, segments);
  const position = geometry.attributes.position;
  const uv = geometry.attributes.uv;

  // Modificar os vértices com base na imagem em tons de cinza
  for (let i = 0; i < position.count; i++) {
    const vertex = new THREE.Vector3().fromBufferAttribute(position, i);
    const u = Math.floor(uv.getX(i) * (imgWidth - 1));
    const v = Math.floor(uv.getY(i) * (imgHeight - 1));
    const gray = textureData[v * imgWidth + u] / 255;
    const displacement = tMin + (1 - gray) * (tMax - tMin);
    vertex.normalize().multiplyScalar(radius + shellThickness + displacement);
    position.setXYZ(i, vertex.x, vertex.y, vertex.z);
  }

  geometry.computeVertexNormals();

  // Criar malha da casca externa com relevo
  const outerMesh = new THREE.Mesh(geometry, currentMaterial);

  // Criar malha da esfera interna
  const innerGeometry = new THREE.SphereGeometry(radius, segments, segments);
  const innerMesh = new THREE.Mesh(innerGeometry, currentMaterial);

  // Atualizar matrizes
  outerMesh.updateMatrix();
  innerMesh.updateMatrix();

  // Subtrair esfera interna da casca externa para obter a casca com relevo
  let bspResult = CSG.fromMesh(outerMesh).subtract(CSG.fromMesh(innerMesh));

  const cutLength = (radius + shellThickness) * 2 + 20;  // comprimento extra para garantir corte total

  // Furo superior
  if (topHole > 0) {
    const topCut = new THREE.Mesh(new THREE.CylinderGeometry(topHole / 2, topHole / 2, cutLength, 16));
    topCut.position.set(0, radius, 0);
    topCut.updateMatrix();
    bspResult = bspResult.subtract(CSG.fromMesh(topCut));
  }

  // Furo inferior
  if (bottomHole > 0) {
    const bottomCut = new THREE.Mesh(new THREE.CylinderGeometry(bottomHole / 2, bottomHole / 2, cutLength, 16));
    bottomCut.position.set(0, -radius, 0);
    bottomCut.updateMatrix();
    bspResult = bspResult.subtract(CSG.fromMesh(bottomCut));
  }

  // Converter resultado para malha e adicionar à cena
  const lithoMesh = CSG.toMesh(bspResult, outerMesh.matrix, currentMaterial);
  scene.add(lithoMesh);
  geometries.push(lithoMesh.geometry);
}



document.getElementById('generateBtn').onclick = async () => {
  clearScene();
  const files = document.getElementById('imageUpload').files;

  const params = {
    radius: +document.getElementById('sphereDiameter').value / 2,
    shellThickness: +document.getElementById('shellThickness').value,
    topHole: +document.getElementById('topHoleDiameter').value,
    bottomHole: +document.getElementById('baseHoleDiameter').value,
    baseHeight: +document.getElementById('baseSupportThickness').value,
    baseWidth: +document.getElementById('baseSupportWidth').value,
  baseHeightPercent: +document.getElementById('baseSupportHeightNumber').value,
    imgWidth: +document.getElementById('imgWidth').value,
    imgHeight: +document.getElementById('imgHeight').value,
    tMin: +document.getElementById('thicknessMin').value,
    tMax: +document.getElementById('thicknessMax').value,
    materialColor: document.getElementById('materialColor').value,
    materialOpacity: +document.getElementById('materialOpacity').value,
    showWireframe: document.getElementById('showWireframe').checked,
    toggleLight: document.getElementById('toggleLight').checked
  };

  currentMaterial = createMaterial(params);
  lightInternal.visible = params.toggleLight;

  const resolution = parseFloat(document.getElementById('resolution').value);

  if (files.length > 0) {
    const grayData = await loadImageAsGrayScale(files[0], params.imgWidth, params.imgHeight);
    const reliefMap = generateReliefMap(grayData, params.imgWidth, params.imgHeight, params.tMin, params.tMax);

    drawHistogram(grayData);

    drawLithophanePreview(reliefMap, params.imgWidth, params.imgHeight, params.tMin, params.tMax, resolution);

    await createLithophaneShell(grayData, params);
  }


createBaseRing(
  params.radius,
  params.baseWidth,
  params.baseThickness,
  params.baseHeightPercent
);


};

document.getElementById('exportBtn').onclick = () => {
  const exporter = new STLExporter();
  const blob = new Blob([exporter.parse(scene)], { type: 'text/plain' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'litofania.stl';
  a.click();
};



const baseHeightSlider = document.getElementById('baseSupportHeightSlider');
const baseHeightNumber = document.getElementById('baseSupportHeightNumber');

baseHeightSlider.addEventListener('input', () => {
  baseHeightNumber.value = baseHeightSlider.value;
});
baseHeightNumber.addEventListener('input', () => {
  baseHeightSlider.value = baseHeightNumber.value;
});


</script>


</body>
</html>



