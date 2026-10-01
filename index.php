<?php
if (extension_loaded("zlib") && !ini_get("zlib.output_compression")) {
    ob_start("ob_gzhandler");
}

session_start();
$mensajeLogin = "";

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_POST["accion"]) && $_POST["accion"] == "login") {
    $email = trim($_POST["email"]);
    $pass = $_POST["password"];

    if ($email == "" || $pass == "") {
        $mensajeLogin = "Por favor, completá todos los datos.";
    } else {
        require_once __DIR__ . "/DB/conexion.php";
        /** @var mysqli $conexion */

        $stmt = mysqli_prepare($conexion, "SELECT id_cliente, nombre, `contraseña` AS clave FROM cliente WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $ok = false;

        if ($fila) {
            if (password_verify($pass, $fila["clave"])) {
                $ok = true;
            } elseif ($pass == $fila["clave"]) {
                $ok = true;
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $up = mysqli_prepare($conexion, "UPDATE cliente SET `contraseña` = ? WHERE id_cliente = ?");
                mysqli_stmt_bind_param($up, "si", $hash, $fila["id_cliente"]);
                mysqli_stmt_execute($up);
            }
        }

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION["usuario"] = $fila["nombre"];
            $_SESSION["id_cliente"] = $fila["id_cliente"];
            header("Location: index.php");
            exit;
        } else {
            $mensajeLogin = "Correo o contraseña incorrectos.";
        }
    }
}

require_once __DIR__ . "/datos.php";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TurisGo | Portal Turístico</title>
    <meta name="description"
        content="TurisGo Viajes: paquetes nacionales e internacionales, vuelos, estadías y autos. Elegí tu destino y reservá tu próxima aventura.">

    <script>
    var guardado = null;
    try {
        guardado = localStorage.getItem("tema");
    } catch (e) {}
    var sistema = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    document.documentElement.dataset.theme = guardado || sistema;
    </script>

    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . "/css/style.css"); ?>">
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <?php $heroLocal = file_exists("img/hero.jpg") && file_exists("img/hero-m.jpg"); ?>
    <?php if ($heroLocal) { ?>
    <link rel="preload" as="image" href="img/hero.jpg" media="(min-width: 751px)" fetchpriority="high">
    <link rel="preload" as="image" href="img/hero-m.jpg" media="(max-width: 750px)" fetchpriority="high">
    <style>
    .hero {
        background-image: url("img/hero.jpg");
    }

    @media (max-width: 750px) {
        .hero {
            background-image: url("img/hero-m.jpg");
        }
    }
    </style>
    <?php } else { ?>
    <link rel="preload" as="image"
        href="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&amp;fit=crop&amp;w=1400&amp;q=70"
        media="(min-width: 751px)" fetchpriority="high">
    <link rel="preload" as="image"
        href="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&amp;fit=crop&amp;w=800&amp;q=70"
        media="(max-width: 750px)" fetchpriority="high">
    <?php } ?>

    <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;family=Playfair+Display:wght@600;700&amp;display=swap"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;family=Playfair+Display:wght@600;700&amp;display=swap">
    </noscript>
</head>

<body>

    <header class="header">
        <div class="contenedor nav">

            <button class="btn-menu" id="btnMenu" type="button" aria-label="Abrir menú" aria-expanded="false"
                aria-controls="menuPrincipal">☰</button>

            <a href="#inicio" class="logo">
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
                <a href="#inicio">Inicio</a>
                <a href="#destinos">Destinos</a>
                <a href="#ofertas">Ofertas</a>
                <a href="#empresas">Empresas</a>
                <a href="#contacto">Contacto</a>
            </nav>

            <div class="acciones">

                <button class="carrito" id="btnTema" onclick="cambiarTema()">🌙</button>

                <button class="carrito" onclick="abrirCarrito()">
                    🛒 <span>Carrito</span>
                    <b id="contadorCarrito">0</b>
                </button>

                <?php if (isset($_SESSION["usuario"])) { ?>
                <div class="usuario-menu">
                    <span>Hola, <?php echo htmlspecialchars($_SESSION["usuario"]); ?></span>
                    <a href="?logout=1" class="btn-logout">Cerrar sesión</a>
                </div>
                <?php } else { ?>
                <button class="btn-login" onclick="abrirLogin()">Iniciar sesión</button>
                <?php } ?>

            </div>
        </div>
    </header>

    <main id="inicio">

        <section class="hero">
            <div class="hero-overlay"></div>

            <div class="contenedor hero-contenido">

                <div class="hero-texto">
                    <p class="mini-titulo">DESCUBRE EL MUNDO CON NOSOTROS</p>
                    <h1>Tu próxima <span>aventura</span> comienza aquí</h1>
                    <p class="descripcion">
                        Paquetes nacionales e internacionales para individuos, familias y grupos.
                        Vuelos, estadías, autos y paquetes integrales.
                    </p>

                    <div class="datos">
                        <div><strong>🌐</strong><span>80+ destinos</span></div>
                        <div><strong>★</strong><span>15 años de experiencia</span></div>
                        <div><strong>✈</strong><span>+50.000 viajeros</span></div>
                    </div>
                </div>

                <div class="buscador">

                    <div class="campo">
                        <label for="destino">DESTINO / AEROPUERTO</label>
                        <div class="campo-input">
                            <input type="text" id="destino" list="listaDestinos"
                                placeholder="Ej: Bariloche, Madrid, EZE..." autocomplete="off" enterkeyhint="search">
                            <button type="button" class="btn-limpiar" id="btnLimpiar" aria-label="Borrar búsqueda"
                                hidden>×</button>
                        </div>
                        <datalist id="listaDestinos">
                            <?php foreach ($aeropuertos as $a) { ?>
                            <option value="<?php echo $a; ?>">
                                <?php } ?>
                        </datalist>
                    </div>

                    <div class="campo">
                        <label for="fecha">CHECK-IN</label>
                        <input type="date" id="fecha">
                    </div>

                    <div class="campo">
                        <label for="pasajeros">PASAJEROS</label>
                        <select id="pasajeros">
                            <option value="1">1 pasajero</option>
                            <?php for ($i = 2; $i <= 6; $i++) { ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> pasajeros</option>
                            <?php } ?>
                        </select>
                    </div>

                    <button class="btn-buscar" onclick="buscarDestino()">🔎 Buscar</button>

                    <p class="buscador-aviso" id="avisoBuscador" hidden></p>

                </div>
            </div>
        </section>

        <section class="estadisticas">
            <div class="estadistica"><strong>$680</strong><span>Precio desde</span></div>
            <div class="estadistica"><strong>80+</strong><span>Destinos disponibles</span></div>
            <div class="estadistica"><strong>24/7</strong><span>Soporte al viajero</span></div>
            <div class="estadistica"><strong>100%</strong><span>Pagos seguros</span></div>
        </section>

        <section class="destinos" id="destinos">
            <div class="contenedor">

                <div class="destinos-header">
                    <div>
                        <p class="seccion-titulo">NUESTROS DESTINOS</p>
                        <h2>Elegí tu destino ideal</h2>
                    </div>

                    <div class="filtros-principales">
                        <button class="filtro-principal activo" data-region="todos">Todos</button>
                        <button class="filtro-principal" data-region="nacional">Argentina</button>
                        <button class="filtro-principal" data-region="internacional">Internacional</button>
                    </div>
                </div>

                <div class="filtros-servicio">
                    <button class="servicio activo" data-tipo="todos">Todos</button>
                    <button class="servicio" data-tipo="integral">🌐 Integral</button>
                    <button class="servicio" data-tipo="estadia">🏨 Estadía</button>
                    <button class="servicio" data-tipo="vuelo">✈ Vuelo</button>
                    <button class="servicio" data-tipo="auto">🚗 Auto</button>
                </div>

                <div class="resultado-busqueda" id="resultadoBusqueda" hidden>
                    <span id="resultadoTexto"></span>
                    <button type="button" onclick="limpiarBusqueda()">✕ Ver todos</button>
                </div>

                <div class="sin-resultados" id="sinResultados" hidden>
                    <strong>No encontramos ese destino</strong>
                    <p>Probá con alguno de estos:</p>
                    <div class="sugerencias">
                        <?php foreach ($destinos as $d) { ?>
                        <button type="button"
                            onclick="sugerirDestino('<?php echo $d["nombre"]; ?>')"><?php echo $d["nombre"]; ?></button>
                        <?php } ?>
                        <button type="button" class="todos" onclick="limpiarBusqueda()">Ver todos</button>
                    </div>
                </div>

                <div class="tarjetas">
                    <?php foreach ($destinos as $d) { ?>
                    <article class="tarjeta" data-region="<?php echo $d["region"]; ?>"
                        data-tipo="<?php echo $d["tipo"]; ?>" data-destino="<?php echo $d["busca"]; ?>">

                        <div class="imagen-tarjeta">
                            <?php $archivo = "img/" . $d["img"] . ".jpg"; ?>
                            <?php if (file_exists($archivo)) { ?>
                            <img src="<?php echo $archivo; ?>" width="720" height="492" loading="lazy" decoding="async"
                                alt="<?php echo $d["nombre"]; ?>">
                            <?php } else { ?>
                            <?php $base = "https://images.unsplash.com/" . $d["img"] . "?auto=format&amp;fit=crop&amp;q=70"; ?>
                            <img src="<?php echo $base; ?>&amp;w=600&amp;h=410"
                                srcset="<?php echo $base; ?>&amp;w=400&amp;h=273 400w, <?php echo $base; ?>&amp;w=600&amp;h=410 600w, <?php echo $base; ?>&amp;w=720&amp;h=492 720w"
                                sizes="(max-width: 750px) 90vw, (max-width: 1000px) 45vw, 400px" width="600"
                                height="410" loading="lazy" decoding="async" alt="<?php echo $d["nombre"]; ?>">
                            <?php } ?>
                            <?php if ($d["etiqueta"] != "") { ?>
                            <span class="etiqueta"><?php echo $d["etiqueta"]; ?></span>
                            <?php } ?>
                            <span class="tipo"><?php echo $tiposTxt[$d["tipo"]]; ?></span>
                        </div>

                        <div class="contenido-tarjeta">
                            <span class="ubicacion"><?php echo $d["pais"]; ?> · <?php echo $d["cod"]; ?></span>
                            <h3><?php echo $d["nombre"]; ?></h3>
                            <p><?php echo $d["desc"]; ?></p>

                            <div class="info-tarjeta">
                                <span><?php echo $d["dias"]; ?> días</span>
                                <strong>Desde $<?php echo number_format($d["precio"], 0, ",", "."); ?></strong>
                            </div>

                            <button class="btn-ver"
                                onclick="agregarCarrito('<?php echo $d["nombre"]; ?>', <?php echo $d["precio"]; ?>)">
                                Agregar al carrito
                            </button>
                        </div>

                    </article>
                    <?php } ?>
                </div>

            </div>
        </section>

        <section class="ofertas" id="ofertas">
            <div class="contenedor">
                <p class="seccion-titulo">OFERTAS ESPECIALES</p>
                <h2>Viajá más, pagá menos</h2>
                <p>Encontrá promociones y descuentos para tus próximas vacaciones.</p>
                <button class="btn-ofertas">Ver ofertas</button>
            </div>
        </section>

        <section class="empresas" id="empresas">
            <div class="contenedor">
                <div>
                    <p class="seccion-titulo">PARA EMPRESAS</p>
                    <h2>Soluciones para viajes corporativos</h2>
                    <p>
                        Organizamos viajes empresariales, reservas, vuelos y alojamiento
                        para equipos de trabajo.
                    </p>
                </div>
                <button class="btn-empresa">Más información</button>
            </div>
        </section>

        <section class="contacto" id="contacto">
            <div class="contenedor">
                <p class="seccion-titulo">CONTACTO</p>
                <h2>¿Necesitás ayuda?</h2>
                <p>Nuestro equipo está disponible para ayudarte a planificar tu viaje.</p>
                <button class="btn-contacto" onclick="contactar()">Contactanos</button>
            </div>
        </section>

    </main>

    <footer>
        <div class="contenedor footer">

            <div>
                <h3>TurisGo <span>Viajes</span></h3>
                <p>Tu próxima aventura comienza aquí.</p>
            </div>

            <div>
                <h4>Navegación</h4>
                <a href="#inicio">Inicio</a>
                <a href="#destinos">Destinos</a>
                <a href="#ofertas">Ofertas</a>
            </div>

            <div>
                <h4>Contacto</h4>
                <p>Caleta Olivia, Santa Cruz</p>
                <p>contacto@turisgo.com</p>
            </div>

        </div>

        <div class="copyright">© 2026 TurisGo. Todos los derechos reservados.</div>
    </footer>

    <div class="modal" id="modalLogin">
        <div class="modal-contenido login-contenido">

            <button class="cerrar" onclick="cerrarLogin()">×</button>

            <h2>Iniciar sesión</h2>
            <p>Ingresá a tu cuenta de TurisGo.</p>

            <?php if ($mensajeLogin != "") { ?>
            <div class="error-login"><?php echo htmlspecialchars($mensajeLogin); ?></div>
            <?php } ?>

            <form method="POST" action="index.php">
                <input type="hidden" name="accion" value="login">

                <label>Correo electrónico</label>
                <input type="email" name="email" placeholder="Ingresá tu correo electrónico" autocomplete="email"
                    required>

                <label>Contraseña</label>
                <input type="password" name="password" placeholder="Ingresá tu contraseña" required>

                <button type="submit" class="btn-login-form">Iniciar sesión</button>
            </form>

            <div class="enlace-cuenta">
                ¿No tenés cuenta? <a href="registro.php">Registrate acá</a>
            </div>

            <div class="datos-demo">
                <strong>Cuenta de prueba</strong>
                <span>Correo: demo@turisgo.com</span>
                <span>Contraseña: 1234</span>
            </div>

        </div>
    </div>

    <div class="modal" id="modalCarrito">
        <div class="modal-contenido carrito-contenido">

            <button class="cerrar" onclick="cerrarCarrito()">×</button>

            <h2>Tu carrito</h2>

            <div id="listaCarrito"></div>

            <div class="total-carrito">
                <span>Total:</span>
                <strong id="totalCarrito">$0</strong>
            </div>

            <a href="carrito.php" class="enlace-carrito-completo">Ver carrito completo →</a>

            <button class="btn-finalizar" onclick="finalizarCompra()">Continuar con la reserva</button>

        </div>
    </div>

    <?php if ($mensajeLogin != "") { ?>

    <script>
    document.getElementById("modalLogin").classList.add("mostrar");
    </script>
    <?php } ?>

    <script>
    function cambiarTema() {
        var root = document.documentElement;
        if (root.dataset.theme == "dark") {
            root.dataset.theme = "light";
        } else {
            root.dataset.theme = "dark";
        }
        try {
            localStorage.setItem("tema", root.dataset.theme);
        } catch (e) {}
        iconoTema();
    }

    function iconoTema() {
        var btn = document.getElementById("btnTema");
        btn.textContent = document.documentElement.dataset.theme == "dark" ? "☀️" : "🌙";
    }

    iconoTema();
    </script>

    <script src="js/script.js"></script>
    <script src="js/header.js"></script>

    <script>
    if (location.search.indexOf("login=1") !== -1) {
        abrirLogin();
    }
    </script>

</body>

</html>