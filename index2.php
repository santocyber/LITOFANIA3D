<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Litofania 3D – Configurador</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- ───── importmap: indica onde estão os módulos ───── -->
<script type="importmap">
{
  "imports": {
    "three"          : "./js/three.module.js",
    "three/addons/"  : "./js/"
  }
}
</script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
  body        { overflow:hidden; }
  .sidebar    { background:#f8f9fa; height:100vh; padding:10px; border-right:1px solid #dee2e6; width:260px }
  #threejs    { width:calc(100vw - 260px); height:100vh; }
  .form-range, .btn { margin-bottom:10px }
</style>
</head>
<body>

<div class="d-flex">
  <!-- ───── Painel Lateral ───── -->
  <div class="sidebar">
    <h6 class="fw-bold">Setor angular da imagem</h6>

    <label>Largura (° 1–360)</label>
    <input type="range" id="angW" class="form-range" min="1" max="360" step="1" value="360">

    <label>Altura (° 1–180)</label>
    <input type="range" id="angH" class="form-range" min="1" max="180" step="1" value="180">

    <label>Offset H (° 0–360)</label>
    <input type="range" id="offH" class="form-range" min="0" max="360" step="1" value="0">

    <label>Offset V (° 0–180)</label>
    <input type="range" id="offV" class="form-range" min="0" max="180" step="1" value="0">

    <hr>
    <h6 class="fw-bold">Upload de textura</h6>
    <input type="file" id="imgInput" class="form-control" accept="image/*">

    <hr>
    <button id="btnLight"     class="btn btn-primary  w-100">Luz on/off</button>
    <button id="btnWire"      class="btn btn-secondary w-100">Wireframe</button>

    <label class="mt-2">Transparência (0-100 %)</label>
    <input type="range" id="transp" class="form-range" min="0" max="100" step="1" value="0">
  </div>

  <!-- ───── Área Three.js ───── -->
  <div id="threejs"></div>
</div>

<!-- ───── Código Three.js ───── -->
<script type="module">
import * as THREE from './js/three.module.js';
import { OrbitControls } from './js/OrbitControls.js';
import { STLExporter } from './js/STLExporter.js';
import CSG from './js/three-csg.js';
/* ─ Scene, camera, renderer ─ */
const sidebarW = 260;                                  // mesma largura do CSS
const scene    = new THREE.Scene();
scene.background = new THREE.Color(0xf0f0f0);

const camera   = new THREE.PerspectiveCamera(75, (window.innerWidth-sidebarW)/window.innerHeight, 0.1, 1000);
camera.position.set(0,0,3);

const renderer = new THREE.WebGLRenderer({antialias:true});
renderer.setSize(window.innerWidth-sidebarW, window.innerHeight);
document.getElementById('threejs').appendChild(renderer.domElement);

/* ─ Orbit controls ─ */
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = .05;
controls.minDistance = 1;
controls.maxDistance = 10;

/* ─ Lights ─ */
const ambLight = new THREE.AmbientLight(0xffffff, .6);
const dirLight = new THREE.DirectionalLight(0xffffff, 1);
dirLight.position.set(5,5,5);
scene.add(ambLight, dirLight);

/* ─ Base geometry ─ */
const geo   = new THREE.SphereGeometry(1, 64, 64);

/* Canvas que servirá de textura */
const canvas = document.createElement('canvas');
canvas.width  = 2048;             // 2:1 → bom p/ esfera (equiretângulo)
canvas.height = 1024;
const ctx = canvas.getContext('2d');
ctx.clearRect(0,0,canvas.width,canvas.height); // inicial transparente

let tex  = new THREE.CanvasTexture(canvas);
tex.wrapS = tex.wrapT = THREE.ClampToEdgeWrapping;

const mat = new THREE.MeshStandardMaterial({
  map        : tex,
  metalness  : .1,
  roughness  : .8,
  side       : THREE.DoubleSide,
  transparent: true,
  opacity    : 1
});
const sphere = new THREE.Mesh(geo, mat);
scene.add(sphere);

/* ─ Função de desenho do setor ─ */
const img = new Image();
img.onload = drawSector;          // sempre que a imagem carregar

function drawSector(){
  const aw   = +document.getElementById('angW').value;   // 1–360
  const ah   = +document.getElementById('angH').value;   // 1–180
  const offH = +document.getElementById('offH').value;   // 0–360
  const offV = +document.getElementById('offV').value;   // 0–180

  // fração ocupada no canvas
  const fracW = aw / 360;
  const fracH = ah / 180;

  // tamanho do retângulo em px
  const rectW = canvas.width  * fracW;
  const rectH = canvas.height * fracH;

  // offset em px: 0° → topo/esquerda
  const rectX = (canvas.width  - rectW) * (offH / 360);
  const rectY = (canvas.height - rectH) * (offV / 180);

  // limpa e desenha
  ctx.clearRect(0,0,canvas.width,canvas.height);
  ctx.drawImage(img, rectX, rectY, rectW, rectH);

  tex.needsUpdate = true;
}

/* ─ Sliders atualizam setor ─ */
['angW','angH','offH','offV'].forEach(id=>{
  document.getElementById(id).addEventListener('input', drawSector);
});

/* ─ Upload de imagem ─ */
document.getElementById('imgInput').addEventListener('change', e=>{
  const f = e.target.files[0];
  if(!f) return;
  const r = new FileReader();
  r.onload = ev => { img.src = ev.target.result; };
  r.readAsDataURL(f);
});

/* ─ Botões ─ */
document.getElementById('btnLight').addEventListener('click', ()=> {
  dirLight.visible = !dirLight.visible;
});
document.getElementById('btnWire').addEventListener('click', ()=> {
  mat.wireframe = !mat.wireframe;
});
document.getElementById('transp').addEventListener('input', function(){
  const v = +this.value;
  mat.opacity     = 1 - v/100;       // 0 = opaco, 100 = invisível
  mat.transparent = mat.opacity < 1;
});

/* ─ Loop de animação ─ */
function animate(){
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
}
animate();

/* ─ Responsivo ─ */
window.addEventListener('resize', ()=>{
  camera.aspect = (window.innerWidth-sidebarW)/window.innerHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(window.innerWidth-sidebarW, window.innerHeight);
});
</script>
</body>
</html>
