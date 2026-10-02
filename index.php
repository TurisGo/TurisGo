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

        $cuentas = [
            ["cliente", "SELECT id_cliente AS id, nombre, `contraseña` AS clave FROM cliente WHERE email = ?", "UPDATE cliente SET `contraseña` = ? WHERE id_cliente = ?"],
            ["empleado", "SELECT id_empleado AS id, nombre, rol, `contraseña` AS clave FROM empleado WHERE email = ?", "UPDATE empleado SET `contraseña` = ? WHERE id_empleado = ?"],
        ];
        $cuenta = null;

        foreach ($cuentas as $c) {
            $stmt = mysqli_prepare($conexion, $c[1]);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$fila) {
                continue;
            }

            $ok = password_verify($pass, $fila["clave"]);

            if (!$ok && $pass == $fila["clave"]) {
                $ok = true;
                $up = mysqli_prepare($conexion, $c[2]);
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($up, "si", $hash, $fila["id"]);
                mysqli_stmt_execute($up);
            }

            if ($ok) {
                $cuenta = $fila;
                $cuenta["rol"] = $c[0] == "cliente" ? "cliente" : $fila["rol"];
                break;
            }
        }

        if ($cuenta) {
            session_regenerate_id(true);
            $_SESSION["usuario"] = $cuenta["nombre"];
            $_SESSION["rol"] = $cuenta["rol"];
            $_SESSION[$cuenta["rol"] == "cliente" ? "id_cliente" : "id_empleado"] = $cuenta["id"];
            header("Location: " . ($cuenta["rol"] == "ventas" ? "ventas.php" : ($cuenta["rol"] == "jefe_ventas" ? "jefe_ventas.php" : "index.php")));
            exit;
        }

        $mensajeLogin = "Correo o contraseña incorrectos.";
    }
}

require_once __DIR__ . "/includes/datos.php";
require_once __DIR__ . "/DB/conexion.php";
/** @var mysqli $conexion */

$nombrePorCodigo = [];
foreach ($destinos as $d) {
    $nombrePorCodigo[$d["cod"]] = $d["nombre"];
}
$listaProductos = mysqli_fetch_all(mysqli_query($conexion, "SELECT codigo, descripcion, tipo_producto, precio_unitario FROM producto ORDER BY codigo"), MYSQLI_ASSOC);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

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
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;display=swap"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&amp;display=swap">
    </noscript>
</head>

<body>

    <?php $carritoDrawer = true;
    require __DIR__ . "/includes/header_nav.php"; ?>

    <main id="inicio">

        <section class="hero">
            <div class="hero-overlay"></div>

            <div class="contenedor hero-contenido">

                <div class="hero-texto">
                    <h1>Paquetes, vuelos y estadías desde Caleta Olivia</h1>
                    <p class="descripcion">Buscá por destino o código de aeropuerto, elegí la fecha y la cantidad de
                        pasajeros.</p>

                </div>

                <div class="buscador">

                    <div class="campo">
                        <label for="destino"><i class="fa-solid fa-location-dot"></i> DESTINO / AEROPUERTO</label>
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
                        <label for="fecha"><i class="fa-regular fa-calendar"></i> CHECK-IN</label>
                        <input type="date" id="fecha">
                    </div>

                    <div class="campo">
                        <label for="pasajeros"><i class="fa-solid fa-user-group"></i> PASAJEROS</label>
                        <select id="pasajeros">
                            <option value="1">1 pasajero</option>
                            <?php for ($i = 2; $i <= 6; $i++) { ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> pasajeros</option>
                            <?php } ?>
                        </select>
                    </div>

                    <button class="btn-buscar" onclick="buscarDestino()"><i class="fa-solid fa-magnifying-glass"></i>
                        Buscar</button>

                    <p class="buscador-aviso" id="avisoBuscador" hidden></p>

                </div>
            </div>
        </section>

        <section class="destinos" id="destinos">
            <div class="contenedor">

                <div class="destinos-header">
                    <div>
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
                    <button class="servicio" data-tipo="integral"><i class="fa-solid fa-earth-americas"></i>
                        Integral</button>
                    <button class="servicio" data-tipo="estadia"><i class="fa-solid fa-hotel"></i> Estadía</button>
                    <button class="servicio" data-tipo="vuelo"><i class="fa-solid fa-plane"></i> Vuelo</button>
                    <button class="servicio" data-tipo="auto"><i class="fa-solid fa-car"></i> Auto</button>
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
                                <span class="ubicacion"><i class="fa-solid fa-location-dot"></i> <?php echo $d["pais"]; ?> ·
                                    <?php echo $d["cod"]; ?></span>
                                <h3><?php echo $d["nombre"]; ?></h3>
                                <p><?php echo $d["desc"]; ?></p>

                                <div class="info-tarjeta">
                                    <span><i class="fa-regular fa-clock"></i> <?php echo $d["dias"]; ?> días</span>
                                    <strong>Desde $<?php echo number_format($d["precio"], 0, ",", "."); ?></strong>
                                </div>

                                <button class="btn-ver"
                                    onclick="agregarCarrito('<?php echo $d["nombre"]; ?>', <?php echo $d["precio"]; ?>, '<?php echo $d["cod"]; ?>')">
                                    Agregar al carrito
                                </button>
                            </div>

                        </article>
                    <?php } ?>
                </div>

            </div>
        </section>

        <section class="lista-productos" id="productos">
            <div class="contenedor">
                <h2 class="titulo-lista">Precios y códigos</h2>

                <div class="tabla-carrito-caja">
                    <table class="tabla-carrito">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Tipo</th>
                                <th>Precio</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaProductos as $prod) { ?>
                                <?php $nombreCarrito = $nombrePorCodigo[$prod["codigo"]] ?? $prod["codigo"]; ?>
                                <tr>
                                    <td data-label="Código"><?php echo htmlspecialchars($prod["codigo"]); ?></td>
                                    <td data-label="Descripción" class="izq">
                                        <?php echo htmlspecialchars($prod["descripcion"]); ?></td>
                                    <td data-label="Tipo">
                                        <?php echo htmlspecialchars(str_replace("_", " ", $prod["tipo_producto"])); ?></td>
                                    <td data-label="Precio">
                                        $<?php echo number_format($prod["precio_unitario"], 0, ",", "."); ?></td>
                                    <td>
                                        <button type="button" class="btn-ver btn-chico"
                                            onclick="agregarCarrito(<?php echo htmlspecialchars(json_encode($nombreCarrito), ENT_QUOTES); ?>, <?php echo (float) $prod["precio_unitario"]; ?>)">
                                            <i class="fa-solid fa-cart-plus"></i> Agregar
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="beneficios" id="nosotros">
            <div class="contenedor">

                <div class="beneficios-lista">
                    <div class="beneficio"><i class="fa-solid fa-suitcase-rolling"></i>
                        <h3>Todo en un solo lugar</h3>
                        <p>Vuelos, estadías, autos y paquetes integrales sin tener que armar el viaje por separado.</p>
                    </div>
                    <div class="beneficio"><i class="fa-solid fa-headset"></i>
                        <h3>Atención personalizada</h3>
                        <p>Un asesor real responde tus dudas antes, durante y después del viaje.</p>
                    </div>
                    <div class="beneficio"><i class="fa-solid fa-shield-halved"></i>
                        <h3>Reservas seguras</h3>
                        <p>Tus datos y tus pagos están protegidos en cada paso de la reserva.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="ofertas" id="reservar">
            <div class="contenedor">
                <h2>Armá tu pedido y seguilo desde tu cuenta</h2>
                <p>Elegí los productos, revisá el carrito y consultá el estado de tu pedido en "Mis pedidos".</p>
                <a href="#productos" class="btn-ofertas">Ver lista de productos</a>
            </div>
        </section>

        <section class="contacto" id="contacto">
            <div class="contenedor">
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
                <p>Viajes nacionales e internacionales desde la Patagonia.</p>
                <div class="redes">
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>

            <div>
                <h4>Navegación</h4>
                <a href="#inicio">Inicio</a>
                <a href="#destinos">Destinos</a>
            </div>

            <div>
                <h4>Contacto</h4>
                <p><i class="fa-solid fa-location-dot"></i>Caleta Olivia, Santa Cruz</p>
                <p><i class="fa-regular fa-envelope"></i>contacto@turisgo.com</p>
            </div>

        </div>

        <div class="copyright">© 2026 TurisGo. Todos los derechos reservados.</div>
    </footer>

    <div class="aviso-carrito" id="avisoCarrito" role="status" aria-live="polite"></div>

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
            btn.innerHTML = document.documentElement.dataset.theme == "dark" ? '<i class="fa-solid fa-sun"></i>' :
                '<i class="fa-solid fa-moon"></i>';
        }

        iconoTema();
    </script>

    <script src="js/script.js?v=<?php echo filemtime(__DIR__ . "/js/script.js"); ?>"></script>
    <script src="js/header.js?v=<?php echo filemtime(__DIR__ . "/js/header.js"); ?>"></script>

    <script>
        if (location.search.indexOf("login=1") !== -1) {
            abrirLogin();
        }
    </script>

</body>

</html>