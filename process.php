<?php
session_start();

$target_dir = "upload/";
if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

$image_name = basename($_FILES["image"]["name"]);
$target_file = $target_dir . uniqid() . "_" . $image_name;

if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
    $stl_dir = "stl/";
    if (!file_exists($stl_dir)) mkdir($stl_dir, 0777, true);

    // ✅ Nome único para STL
    $base_filename = uniqid();
    $stl_file = "stl/{$base_filename}.stl";
    $glb_file = "stl/{$base_filename}.glb";

    // ✅ Parâmetros
    $mode = $_POST["mode"] ?? "planar";
    $params = [
        $_POST["resolution"] ?? 0.25,
        $_POST["sphere_diameter"] ?? 140,
        $_POST["angular_height"] ?? 90,
        $_POST["angular_width"] ?? 114.21,
        $_POST["thickness_max"] ?? 2.7,
        $_POST["thickness_min"] ?? 0.6,
        $_POST["cyl_diameter"] ?? 70,
        $_POST["cyl_height"] ?? 25,
        $_POST["cyl_thickness"] ?? 5,
        $_POST["ledge_diameter"] ?? 75,
        $_POST["top_hole_diameter"] ?? 0,
        $mode
    ];

    // ✅ Segurança e comando
    $safe_target = escapeshellarg($target_file);
    $safe_stl = escapeshellarg($stl_file);
    $param_str = implode(" ", array_map("escapeshellarg", $params));

    $command = "python3 script/generate_lithophane.py $safe_target $safe_stl $param_str 2>&1";
    exec($command, $output, $return_var);

    if ($return_var === 0 && file_exists($glb_file)) {
        $_SESSION['stl_file'] = $stl_file;
        $_SESSION['glb_file'] = $glb_file;
        header("Location: index.php");
        exit();
    } else {
        echo "<pre>Erro ao gerar STL:\n";
        echo implode("\n", $output);
        echo "\n\nComando:\n$command";
        echo "</pre>";
    }
} else {
    echo "Falha ao mover a imagem.";
}
