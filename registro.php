<?php
session_start();
$mensajeRegistro = "";
$registroExitoso = false;
$nombre = "";
$apellido = "";
$email = "";
$telefono = "";

if (isset($_POST["registrar"])) {
    $nombre = trim($_POST["nombre"]);
    $apellido = trim($_POST["apellido"]);
    $email = trim($_POST["email"]);
    $telefono = trim($_POST["telefono"]);
    $pass = $_POST["password"];
    $confirmar = $_POST["confirm_password"];

    if ($nombre == "" || $apellido == "" || $email == "" || $pass == "" || $confirmar == "") {
        $mensajeRegistro = "Por favor, completá todos los campos obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensajeRegistro = "El correo electrónico no es válido.";
    } elseif (strlen($pass) < 6) {
        $mensajeRegistro = "La contraseña tiene que tener al menos 6 caracteres.";
    } elseif ($pass !== $confirmar) {
        $mensajeRegistro = "Las contraseñas no coinciden.";
    } elseif (!isset($_POST["terminos"])) {
        $mensajeRegistro = "Tenés que aceptar los términos y condiciones.";
    } else {
        require_once __DIR__ . "/DB/conexion.php";
        /** @var mysqli $conexion */

        $stmt_check = mysqli_prepare($conexion, "SELECT id_cliente FROM cliente WHERE email = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $email);
        mysqli_stmt_execute($stmt_check);
        $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

        if ($existente) {
            $mensajeRegistro = "Ese correo ya está registrado.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $tel = ($telefono == "") ? null : $telefono;

            $stmt_insert = mysqli_prepare($conexion, "INSERT INTO cliente (nombre, apellido, email, `contraseña`, telefono) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_insert, "sssss", $nombre, $apellido, $email, $hash, $tel);

            if (mysqli_stmt_execute($stmt_insert)) {
                $registroExitoso = true;
            } else {
                $mensajeRegistro = "Hubo un error al registrar el usuario.";
            }
        }
    }
}

function limpiar($texto) {
    return htmlspecialchars($texto, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | TurisGo</title>

    <script>
    var guardado = null;
    try {
        guardado = localStorage.getItem("tema");
    } catch (e) {}
    var sistema = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    document.documentElement.dataset.theme = guardado || sistema;
    </script>

    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . "/css/style.css"); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;family=Playfair+Display:wght@600;700&amp;display=swap"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;family=Playfair+Display:wght@600;700&amp;display=swap">
    </noscript>
</head>

<body class="pagina-registro">

    <header class="header">
        <div class="contenedor nav">

            <button class="btn-menu" id="btnMenu" type="button" aria-label="Abrir menú" aria-expanded="false"
                aria-controls="menuPrincipal">☰</button>

            <a href="index.php#inicio" class="logo">
                <div class="logo-icono">✈</div>
                <div class="logo-texto">
                    <h2>TurisGo <span>Viajes</span></h2>
                    <small>PORTAL TURÍSTICO</small>
                </div>
            </a>

            <form class="buscador-header" id="formBuscadorHeader" action="index.php" method="get" role="search">
                <span class="lupa">🔎</span>
                <input type="search" id="buscarHeader" name="buscar" placeholder="Buscar destinos..."
                    list="listaDestinos" autocomplete="off" aria-label="Buscar destinos">
            </form>

            <nav class="menu" id="menuPrincipal">
                <a href="index.php#inicio">Inicio</a>
                <a href="index.php#destinos">Destinos</a>
                <a href="index.php#ofertas">Ofertas</a>
                <a href="index.php#empresas">Empresas</a>
                <a href="index.php#contacto">Contacto</a>
            </nav>

            <div class="acciones">

                <button class="carrito" id="btnTema" onclick="cambiarTema()" aria-label="Cambiar tema">🌙</button>

                <?php if (isset($_SESSION["usuario"])) { ?>
                <div class="usuario-menu">
                    <span>Hola, <?php echo limpiar($_SESSION["usuario"]); ?></span>
                    <a href="index.php?logout=1" class="btn-logout">Cerrar sesión</a>
                </div>
                <?php } else { ?>
                <a href="index.php?login=1" class="btn-login btn-enlace">Iniciar sesión</a>
                <?php } ?>

            </div>
        </div>
    </header>

    <main class="registro-main">

        <div class="caja-registro login-contenido">

            <h2>Crear cuenta</h2>
            <p>Unite a TurisGo y accedé a los mejores destinos.</p>

            <?php if ($registroExitoso) { ?>

            <div class="mensaje-exito">¡Cuenta creada con éxito! Ya podés iniciar sesión.</div>

            <a href="index.php?login=1" class="btn-login-form btn-volver">Iniciar sesión</a>

            <?php } else { ?>

            <?php if ($mensajeRegistro != "") { ?>
            <div class="error-login"><?php echo limpiar($mensajeRegistro); ?></div>
            <?php } ?>

            <form method="POST" action="registro.php">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" placeholder="Tu nombre"
                    value="<?php echo limpiar($nombre); ?>" autocomplete="given-name" required>

                <label for="apellido">Apellido *</label>
                <input type="text" id="apellido" name="apellido" placeholder="Tu apellido"
                    value="<?php echo limpiar($apellido); ?>" autocomplete="family-name" required>

                <label for="email">Correo electrónico *</label>
                <input type="email" id="email" name="email" placeholder="Dirección de correo electrónico"
                    value="<?php echo limpiar($email); ?>" autocomplete="email" required>

                <label for="telefono">Teléfono (opcional)</label>
                <input type="tel" id="telefono" name="telefono" placeholder="Ej: 297 000 0000"
                    value="<?php echo limpiar($telefono); ?>" autocomplete="tel">

                <label for="password">Contraseña *</label>
                <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6"
                    autocomplete="new-password" required>

                <label for="confirm_password">Confirmar contraseña *</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repetí la contraseña"
                    minlength="6" autocomplete="new-password" required>

                <label class="check-terminos">
                    <input type="checkbox" name="terminos" required>
                    <span>Acepto los términos y condiciones</span>
                </label>

                <button type="submit" name="registrar" class="btn-login-form">Registrarme</button>
            </form>

            <div class="enlace-cuenta">
                ¿Ya tenés cuenta? <a href="index.php?login=1">Iniciá sesión</a>
            </div>

            <?php } ?>

        </div>

    </main>

    <footer>
        <div class="contenedor footer">

            <div>
                <h3>TurisGo <span>Viajes</span></h3>
                <p>Tu próxima aventura comienza aquí.</p>
            </div>

            <div>
                <h4>Navegación</h4>
                <a href="index.php#inicio">Inicio</a>
                <a href="index.php#destinos">Destinos</a>
                <a href="index.php#ofertas">Ofertas</a>
            </div>

            <div>
                <h4>Contacto</h4>
                <p>Caleta Olivia, Santa Cruz</p>
                <p>contacto@turisgo.com</p>
            </div>

        </div>

        <div class="copyright">© 2026 TurisGo. Todos los derechos reservados.</div>
    </footer>

    <script>
    function iconoTema() {
        var btn = document.getElementById("btnTema");
        btn.textContent = document.documentElement.dataset.theme == "dark" ? "☀️" : "🌙";
    }

    function cambiarTema() {
        var root = document.documentElement;
        root.dataset.theme = root.dataset.theme == "dark" ? "light" : "dark";
        try {
            localStorage.setItem("tema", root.dataset.theme);
        } catch (e) {}
        iconoTema();
    }

    iconoTema();

    var clave = document.getElementById("password");
    var confirmar = document.getElementById("confirm_password");

    if (clave && confirmar) {
        function revisarClaves() {
            confirmar.setCustomValidity(confirmar.value !== clave.value ? "Las contraseñas no coinciden." : "");
        }

        clave.addEventListener("input", revisarClaves);
        confirmar.addEventListener("input", revisarClaves);
    }
    </script>

    <script src="js/header.js"></script>

</body>

</html>