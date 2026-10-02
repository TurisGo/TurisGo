<?php
session_start();

if (isset($_SESSION["usuario"])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . "/DB/conexion.php";
/** @var mysqli $conexion */

$titulo = "Crear cuenta | TurisGo";
$error = "";
$nombre = "";
$apellido = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST["accion"] ?? "") == "registro") {
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $pass = $_POST["password"] ?? "";
    $pass2 = $_POST["password2"] ?? "";
    $terminos = isset($_POST["terminos"]);

    if ($nombre == "" || $apellido == "" || $email == "" || $pass == "" || $pass2 == "") {
        $error = "Completá todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no es válido.";
    } elseif (strlen($pass) < 4) {
        $error = "La contraseña tiene que tener al menos 4 caracteres.";
    } elseif ($pass !== $pass2) {
        $error = "Las contraseñas no coinciden.";
    } elseif (!$terminos) {
        $error = "Tenés que aceptar los términos y condiciones.";
    } else {
        $st = mysqli_prepare($conexion, "SELECT id_cliente FROM cliente WHERE email = ?");
        mysqli_stmt_bind_param($st, "s", $email);
        mysqli_stmt_execute($st);

        if (mysqli_fetch_assoc(mysqli_stmt_get_result($st))) {
            $error = "Ya existe una cuenta con ese correo electrónico.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = mysqli_prepare($conexion, "INSERT INTO cliente (nombre, apellido, email, `contraseña`) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($st, "ssss", $nombre, $apellido, $email, $hash);
            mysqli_stmt_execute($st);

            session_regenerate_id(true);
            $_SESSION["usuario"] = $nombre;
            $_SESSION["rol"] = "cliente";
            $_SESSION["id_cliente"] = mysqli_insert_id($conexion);
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo limpiar($titulo); ?></title>

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;display=swap"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;display=swap">
    </noscript>
</head>

<body class="pagina-registro">

    <?php require __DIR__ . "/includes/header_nav.php"; ?>

    <main class="registro-main">
        <div class="caja-registro login-contenido">

            <h2>Creá tu cuenta</h2>
            <p>Registrate para armar tu pedido y hacerle seguimiento.</p>

            <?php if ($error != "") { ?>
            <div class="error-login"><?php echo limpiar($error); ?></div>
            <?php } ?>

            <form method="POST" action="registro.php">
                <input type="hidden" name="accion" value="registro">

                <label>Nombre</label>
                <input type="text" name="nombre" placeholder="Ingresá tu nombre" value="<?php echo limpiar($nombre); ?>"
                    required>

                <label>Apellido</label>
                <input type="text" name="apellido" placeholder="Ingresá tu apellido"
                    value="<?php echo limpiar($apellido); ?>" required>

                <label>Correo electrónico</label>
                <input type="email" name="email" placeholder="Ingresá tu correo electrónico" autocomplete="email"
                    value="<?php echo limpiar($email); ?>" required>

                <label>Contraseña</label>
                <input type="password" name="password" placeholder="Elegí una contraseña" autocomplete="new-password"
                    required>

                <label>Confirmar contraseña</label>
                <input type="password" name="password2" placeholder="Repetí la contraseña"
                    autocomplete="new-password" required>

                <label class="check-terminos">
                    <input type="checkbox" name="terminos" value="1">
                    Acepto los términos y condiciones
                </label>

                <button type="submit" class="btn-login-form">Crear cuenta</button>
            </form>

            <div class="enlace-cuenta">
                ¿Ya tenés cuenta? <a href="index.php?login=1">Iniciar sesión</a>
            </div>

            <a href="index.php" class="btn-volver">← Volver al inicio</a>

        </div>
    </main>

    <footer>
        <div class="contenedor footer">

            <div>
                <h3>TurisGo <span>Viajes</span></h3>
                <p>Viajes nacionales e internacionales desde la Patagonia.</p>
                <div class="redes">
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>

            <div>
                <h4>Navegación</h4>
                <a href="index.php#inicio">Inicio</a>
                <a href="index.php#destinos">Destinos</a>
                <a href="index.php#productos">Productos</a>
            </div>

            <div>
                <h4>Contacto</h4>
                <p><i class="fa-solid fa-location-dot"></i>Caleta Olivia, Santa Cruz</p>
                <p><i class="fa-regular fa-envelope"></i>contacto@turisgo.com</p>
            </div>

        </div>

        <div class="copyright">© 2026 TurisGo. Todos los derechos reservados.</div>
    </footer>

    <script>
    function iconoTema() {
        var btn = document.getElementById("btnTema");
        btn.innerHTML = document.documentElement.dataset.theme == "dark" ? '<i class="fa-solid fa-sun"></i>' :
            '<i class="fa-solid fa-moon"></i>';
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
    </script>

    <script src="js/header.js?v=<?php echo filemtime(__DIR__ . "/js/header.js"); ?>"></script>

</body>

</html>
