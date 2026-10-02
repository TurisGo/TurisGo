<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(16));
}

$rol = $_SESSION["rol"] ?? "";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo limpiar($titulo ?? "TurisGo"); ?></title>
    <script>
    var guardado = null;
    try {
        guardado = localStorage.getItem("tema");
    } catch (e) {}
    document.documentElement.dataset.theme = guardado || (window.matchMedia("(prefers-color-scheme: dark)").matches ?
        "dark" : "light");
    </script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(dirname(__DIR__) . "/css/style.css"); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="pagina-registro">

    <?php require __DIR__ . "/header_nav.php"; ?>

    <main class="carrito-pagina">
        <div class="contenedor">