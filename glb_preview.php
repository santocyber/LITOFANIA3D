<?php
// glb_preview.php — Chamando Python com posicional + flags nomeadas

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function respond(array $d) {
    if (ob_get_length()) ob_clean();
    echo json_encode($d);
    exit;
}
function sanitize_id($i){ return preg_replace('/[^a-zA-Z0-9]/','',$i); }
function post_float($k,$d,$min=null,$max=null){
    if(!isset($_POST[$k])) return $d;
    $v = filter_var($_POST[$k],FILTER_VALIDATE_FLOAT);
    if($v===false) respond(['success'=>false,'message'=>"Parâmetro {$k} inválido."]);
    if($min!==null && $v<$min) respond(['success'=>false,'message'=>"{$k} mínimo: {$min}."]);
    if($max!==null && $v>$max) respond(['success'=>false,'message'=>"{$k} máximo: {$max}."]);
    return $v;
}
function post_int($k,$d,$min=null,$max=null){
    if(!isset($_POST[$k])) return $d;
    $v = filter_var($_POST[$k],FILTER_VALIDATE_INT);
    if($v===false) respond(['success'=>false,'message'=>"Parâmetro {$k} deve ser inteiro."]);
    if($min!==null && $v<$min) respond(['success'=>false,'message'=>"{$k} mínimo: {$min}."]);
    if($max!==null && $v>$max) respond(['success'=>false,'message'=>"{$k} máximo: {$max}."]);
    return $v;
}
function post_bool($k){ return (isset($_POST[$k]) && $_POST[$k]==='on'); }

$DIR        = __DIR__.'/preview/';
$MAX_AGE    = 2*3600;
$MAX_SIZE   = 10*1024*1024;
$PY_SCRIPT  = __DIR__.'/script/generate_lithophane.py';
$TIMEOUT    = 60;
$EXTS       = ['jpg','jpeg','png','gif'];

// 1) POST only
if($_SERVER['REQUEST_METHOD']!=='POST') respond(['success'=>false,'message'=>'Use POST.']);

// 2) CSRF
if(empty($_POST['csrf_token'])||$_POST['csrf_token']!==($_SESSION['csrf_token']??'')) {
    respond(['success'=>false,'message'=>'Token CSRF inválido.']);
}

// 3) Clean old previews
if(!is_dir($DIR)&&!mkdir($DIR,0755,true)) {
    respond(['success'=>false,'message'=>'Não foi possível criar preview/.']);
}
foreach(glob($DIR.'*') as $f){
    if(is_file($f)&&filemtime($f)<time()-$MAX_AGE) @unlink($f);
}

// 4) Upload or reuse
$id = $image = '';
if(!empty($_POST['image_id']) && empty($_FILES['image']['tmp_name'])) {
    $id = sanitize_id($_POST['image_id']);
    $found = glob($DIR.$id.'.*');
    if(!$found) respond(['success'=>false,'message'=>'Preview anterior não encontrado.']);
    $image = $found[0];
} else {
    if(empty($_FILES['image'])||$_FILES['image']['error']!==UPLOAD_ERR_OK)
        respond(['success'=>false,'message'=>'Erro no upload da imagem.']);
    if($_FILES['image']['size']>$MAX_SIZE)
        respond(['success'=>false,'message'=>'Imagem maior que 10 MB.']);
    $info = @getimagesize($_FILES['image']['tmp_name']);
    if(!$info||empty($info[2]))
        respond(['success'=>false,'message'=>'Arquivo não é imagem válida.']);
    $ext = strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,$EXTS,true))
        respond(['success'=>false,'message'=>'Use JPG/PNG/GIF.']);
    $id = bin2hex(random_bytes(16));
    $image = "{$DIR}{$id}.{$ext}";
    if(!move_uploaded_file($_FILES['image']['tmp_name'],$image))
        respond(['success'=>false,'message'=>'Falha ao salvar imagem.']);
}

// 5) Define saídas
$stl = "{$DIR}{$id}.stl";
$glb = "{$DIR}{$id}.glb";

// 6) Coleta parâmetros
$p = [];
// posicionais
$p[] = $image; 
$p[] = $stl;   
$p[] = $glb;   
// numéricos com flag+valor
foreach([
    'resolution', 'sphere_diameter','angular_width','angular_height',
    'thickness_max','thickness_min','base_hole_diameter','top_hole_diameter',
    'base_support_thickness','base_support_width','shell_thickness',
    'img_width','img_height','exposure_level','saturation_level',
    'offset_phi','offset_theta'
] as $k){
    $val = in_array($k,['img_width','img_height'])
           ? post_int($k, $_POST[$k]??0, 1, 5000)
           : post_float($k, $_POST[$k]??0, null, null);
    $p[] = "--{$k}";
    $p[] = (string)$val;
}
// flags booleanas
foreach(['flip_image','mirror_image','crop_image','moon_background','fit_sphere'] as $k){
    if(post_bool($k)) $p[] = "--{$k}";
}
// modo (sempre existe)
$mode = $_POST['mode']??'planar';
if(!in_array($mode,['planar','semi','full'],true))
    respond(['success'=>false,'message'=>'Modo inválido.']);
$p[] = "--mode"; $p[] = $mode;

// 7) Monta comando
$parts = array_map('escapeshellarg',$p);
$cmd = sprintf(
  'timeout %d python3 %s %s 2>&1',
  $TIMEOUT,
  escapeshellarg($PY_SCRIPT),
  implode(' ',$parts)
);

// 8) Executa
exec($cmd,$out,$st);

// 9) Resposta
if($st===0 && file_exists($glb)){
  respond(['success'=>true,'glb_url'=>'preview/'.$id.'.glb','image_id'=>$id]);
} else {
  respond([
    'success'=>false,
    'message'=>'Falha ao gerar modelo 3D.',
    'command'=>$cmd,
    'status'=>$st,
    'output'=>array_slice($out,-10)
  ]);
}
