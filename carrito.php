<?php
session_start();
require_once __DIR__ . "/datos.php";

function limpiar($texto) {
    return htmlspecialchars($texto, ENT_QUOTES, "UTF-8");
}

$info = [];

foreach ($destinos as $d) {
    $archivo = "img/" . $d["img"] . ".jpg";

    if (file_exists(__DIR__ . "/" . $archivo)) {
        $src = $archivo;
    } else {
        $src = "https://images.unsplash.com/" . $d["img"] . "?auto=format&fit=crop&q=70&w=160&h=110";
    }

    $info[$d["nombre"]] = [
        "img" => $src,
        "pais" => $d["pais"],
        "cod" => $d["cod"],
        "dias" => $d["dias"],
    ];
}

$sesionIniciada = isset($_SESSION["id_cliente"]);
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu carrito | TurisGo</title>

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
                <input type="search" id="buscarHeader" name="buscar" placeholder="Buscar destinos..." autocomplete="off"
                    aria-label="Buscar destinos">
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

                <a href="carrito.php" class="carrito" aria-label="Carrito">
                    🛒 <span>Carrito</span>
                    <b id="contadorCarrito">0</b>
                </a>

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

    <main class="carrito-pagina">
        <div class="contenedor">

            <h2 class="titulo-carrito">Tu carrito</h2>

            <div class="tabla-carrito-caja" id="cajaTabla" hidden>
                <table class="tabla-carrito">
                    <thead>
                        <tr>
                            <th scope="col">Imagen</th>
                            <th scope="col">Destino</th>
                            <th scope="col">Precio</th>
                            <th scope="col">Cantidad</th>
                            <th scope="col">Subtotal</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoCarrito"></tbody>
                </table>
            </div>

            <div class="carrito-vacio-pagina" id="carritoVacio" hidden>
                <strong>Tu carrito está vacío</strong>
                <p>Explorá nuestros destinos y agregá tu próxima aventura.</p>
                <a href="index.php#destinos" class="btn-explorar">Explorar destinos</a>
            </div>

            <div class="carrito-total" id="carritoTotalCaja" hidden>
                <h3 id="totalCarrito">Total: $0</h3>

                <div class="carrito-botones">
                    <button type="button" class="btn-vaciar" id="btnVaciar">Vaciar carrito</button>
                    <button type="button" class="btn-reservar" id="btnReservar">Continuar con la reserva</button>
                </div>
            </div>

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

    const destinosInfo = <?php echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const sesionIniciada = <?php echo $sesionIniciada ? "true" : "false"; ?>;
    </script>

    <script src="js/carrito.js"></script>
    <script src="js/header.js"></script>

</body>

</html>