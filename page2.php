<?php
// page2.php

// 1) Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['file'])) {
    echo 'Nenhum modelo especificado.';
    exit;
}

// 2) Sanitiza o nome do arquivo
$filename = basename($_POST['file']);
if (!preg_match('/^[a-zA-Z0-9_\-]+\.stl$/', $filename)) {
    echo 'Nome de arquivo inválido.';
    exit;
}

// 3) Verifica existência
$modelsDir = realpath(__DIR__ . '/models');
$filePath  = realpath($modelsDir . '/' . $filename);
if (!$filePath || strpos($filePath, $modelsDir) !== 0) {
    echo 'Arquivo não encontrado.';
    exit;
}

// 4) URL relativa para o loader
$stlUrl = 'models/' . $filename;
?>



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
      "three": "./js/three.module.js",
      "three/addons/": "./js/"
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

  import * as THREE        from 'three';
  import { OrbitControls } from 'three/addons/OrbitControls.js';
  import { STLLoader }     from 'three/addons/STLLoader.js';
  import CSG               from './js/three-csg.js';

  // 1) URL do STL injetado pelo PHP
  const stlUrl = "<?= addslashes($stlUrl) ?>";

  // ─── setup básico: cena, câmera, renderer, controls ───
  const sidebarW = 260;
  const scene    = new THREE.Scene();
  scene.background = new THREE.Color(0xf0f0f0);

  const camera   = new THREE.PerspectiveCamera(
    75,
    (window.innerWidth - sidebarW) / window.innerHeight,
    0.1, 1000
  );
  camera.position.set(0,0,3);

  const renderer = new THREE.WebGLRenderer({ antialias:true });
  renderer.setSize(window.innerWidth - sidebarW, window.innerHeight);
  document.getElementById('threejs').appendChild(renderer.domElement);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;

// Cria e posiciona as luzes, guardando em variáveis
const ambLight = new THREE.AmbientLight(0xffffff, 0.6);
const dirLight = new THREE.DirectionalLight(0xffffff, 1);
dirLight.position.set(5, 5, 5);

// Só agora adiciona objetos válidos ao scene
scene.add(ambLight, dirLight);

  // ─── Canvas off-screen e material da esfera ───
  const canvas = document.createElement('canvas');
  canvas.width  = 2048; canvas.height = 1024;
  const ctx = canvas.getContext('2d');
  const tex = new THREE.CanvasTexture(canvas);
  tex.wrapS = tex.wrapT = THREE.ClampToEdgeWrapping;

  const baseMat = new THREE.MeshStandardMaterial({
    map:        tex,
    metalness:  0.1,
    roughness:  0.8,
    side:       THREE.DoubleSide,
    transparent:true,
    opacity:    1
  });

  let sphereMesh = new THREE.Mesh(
    new THREE.SphereGeometry(1,64,64),
    baseMat.clone()
  );
  scene.add(sphereMesh);

  // ─── variável para geometria STL ───
  let loadedGeom = null;

  // ─── função que atualiza apenas o setor de relevo ───
  function updateReliefInSector() {
    if (!loadedGeom) return;
    const aw   = THREE.MathUtils.degToRad(+document.getElementById('angW').value);
    const ah   = THREE.MathUtils.degToRad(+document.getElementById('angH').value);
    const offH = THREE.MathUtils.degToRad(+document.getElementById('offH').value);
    const offV = THREE.MathUtils.degToRad(+document.getElementById('offV').value);

    // fatia esférica (radius um pouco >1 pra garantir interseção)
    const wedgeGeo = new THREE.SphereGeometry(
      1.001, 32, 32,
      offH, aw,
      offV, ah
    );
    const wedge = new THREE.Mesh(wedgeGeo);
    wedge.updateMatrix();

    // prepara relevo
    const geom = loadedGeom.clone();
    geom.computeBoundingBox();
    const c = geom.boundingBox.getCenter(new THREE.Vector3());
    geom.translate(-c.x,-c.y,-c.z);
    geom.computeBoundingSphere();
    const s = 1/geom.boundingSphere.radius;
    geom.scale(s,s,s);
    const relief = new THREE.Mesh(geom, baseMat.clone());
    relief.updateMatrix();

    // CSG: intersect relevo ∩ wedge, depois union com esfera
    const bspWedge  = CSG.fromMesh(wedge);
    const bspRelief = CSG.fromMesh(relief);
    const sectorBSP = bspRelief.intersect(bspWedge);
    const unionBSP  = CSG.fromMesh(sphereMesh).union(sectorBSP);

    const merged = CSG.toMesh(unionBSP, sphereMesh.matrix, baseMat.clone());
    merged.material.transparent = baseMat.transparent;
    merged.material.opacity     = baseMat.opacity;

    scene.remove(sphereMesh);
    sphereMesh = merged;
    scene.add(sphereMesh);
  }

  // ─── 2) Carrega o STL via URL do PHP ───
  new STLLoader().load(stlUrl,
    geom => {
      loadedGeom = geom;
      updateReliefInSector();
    },
    undefined,
    err => {
      console.error('Erro ao carregar STL:', err);
      alert('Falha ao carregar o modelo.');
    }
  );

  // ─── 3) UI bindings ───
  // sliders do setor
  ['angW','angH','offH','offV'].forEach(id=>{
    document.getElementById(id)
      .addEventListener('input', updateReliefInSector);
  });
  // luz, wireframe, transparência (mesmo do protótipo)
  document.getElementById('btnLight')
    .addEventListener('click', () => dirLight.visible = !dirLight.visible);
  document.getElementById('btnWire')
    .addEventListener('click', () => baseMat.wireframe = !baseMat.wireframe);
  document.getElementById('transp')
    .addEventListener('input', function(){
      baseMat.opacity     = 1 - (+this.value)/100;
      baseMat.transparent = baseMat.opacity < 1;
    });

  // ─── 4) Animação & resize ───
  function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
  }
  window.addEventListener('resize', () => {
    camera.aspect = (window.innerWidth - sidebarW)/window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth - sidebarW, window.innerHeight);
  });
  animate();

  </script>
</body>
</html>