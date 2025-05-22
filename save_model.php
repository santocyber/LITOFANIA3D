<?php
// save_model.php
header('Content-Type: application/json; charset=utf-8');

// 1) timer
$start = microtime(true);

// 2) lê o corpo cru (blob STL)
$data = file_get_contents('php://input');
if ($data === false || strlen($data) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum dado recebido']);
    exit;
}

// 3) garante diretório de saída
$modelsDir = __DIR__ . '/models';
if (!is_dir($modelsDir)) {
    if (!mkdir($modelsDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao criar diretório models/']);
        exit;
    }
}

// 4) nomes únicos
$baseName   = 'model_' . uniqid();
$stlName    = $baseName . '.stl';
$zipName    = $baseName . '.zip';
$stlPath    = "$modelsDir/$stlName";
$zipPath    = "$modelsDir/$zipName";

// 5) grava temporariamente o STL
if (file_put_contents($stlPath, $data) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao salvar STL']);
    exit;
}

// 6) cria o ZIP usando ZipArchive
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao criar arquivo ZIP']);
    exit;
}
$zip->addFile($stlPath, $stlName);
$zip->close();

// 7) opcional: remove o .stl original se quiser poupar espaço
// unlink($stlPath);

// 8) tempo total
$end = microtime(true);
$saveTime = $end - $start;

// 9) responde com JSON informando o ZIP
echo json_encode([
    'save_time' => $saveTime,
    'filename'  => 'models/' . $zipName
]);
