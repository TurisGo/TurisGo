<?php
session_start();
require_once __DIR__ . "/includes/datos.php";
require_once __DIR__ . "/mailer.php";

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

$metodosPago = [
    "mercadopago" => "Mercado Pago",
    "transferencia" => "Transferencia bancaria",
    "agencia" => "Pago en la agencia",
];

$titular = null;
if ($sesionIniciada) {
    require_once __DIR__ . "/DB/conexion.php";
    /** @var mysqli $conexion */
    $st = mysqli_prepare($conexion, "SELECT nombre, apellido, email FROM cliente WHERE id_cliente = ?");
    mysqli_stmt_bind_param($st, "i", $_SESSION["id_cliente"]);
    mysqli_stmt_execute($st);
    $titular = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
}

if (empty($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(16));
}

function crearPagoMercadoPago($numero, $lineas) {
    if (MP_ACCESS_TOKEN == "") {
        return null;
    }

    $volver = (empty($_SERVER["HTTPS"]) ? "http" : "https") . "://" . $_SERVER["HTTP_HOST"] . strtok($_SERVER["REQUEST_URI"], "?");

    $items = [];
    foreach ($lineas as $l) {
        $items[] = ["title" => $l["nombre"], "quantity" => $l["cantidad"], "unit_price" => $l["precio"], "currency_id" => "ARS"];
    }

    $ch = curl_init("https://api.mercadopago.com/checkout/preferences");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . MP_ACCESS_TOKEN],
        CURLOPT_POSTFIELDS => json_encode([
            "items" => $items,
            "external_reference" => $numero,
            "back_urls" => ["success" => $volver, "failure" => $volver, "pending" => $volver],
        ]),
    ]);
    $respuesta = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $respuesta["init_point"] ?? null;
}

$resultadoPago = "";

if (isset($_GET["payment_id"]) && $sesionIniciada) {
    require_once __DIR__ . "/DB/conexion.php";
    /** @var mysqli $conexion */

    if (MP_ACCESS_TOKEN != "") {
        $ch = curl_init("https://api.mercadopago.com/v1/payments/" . urlencode($_GET["payment_id"]));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: Bearer " . MP_ACCESS_TOKEN]]);
        $pago = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $numeroPagado = $pago["external_reference"] ?? "";
        $st = mysqli_prepare($conexion, "SELECT v.id_venta, v.id_pedido, v.monto_total FROM venta v JOIN pedido p ON p.id_pedido = v.id_pedido WHERE p.numero_pedido = ? AND p.id_cliente = ?");
        mysqli_stmt_bind_param($st, "si", $numeroPagado, $_SESSION["id_cliente"]);
        mysqli_stmt_execute($st);
        $venta = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        $estadoPago = $pago["status"] ?? "";

        if ($venta && $estadoPago == "approved" && abs($pago["transaction_amount"] - $venta["monto_total"]) < 0.01) {
            $st = mysqli_prepare($conexion, "UPDATE venta SET estado_cobro = 'pagado', metodo_pago = 'mercadopago' WHERE id_venta = ?");
            mysqli_stmt_bind_param($st, "i", $venta["id_venta"]);
            mysqli_stmt_execute($st);
            notificarCobroConfirmado($conexion, $venta["id_pedido"]);
            $resultadoPago = "aprobado";
        } elseif ($venta && $estadoPago == "rejected") {
            $resultadoPago = "rechazado";
        } elseif ($venta) {
            $resultadoPago = "pendiente";
        }
    }
}

$pedido = null;
$errorPedido = "";

if (isset($_POST["confirmar"])) {
    if (!$sesionIniciada) {
        $errorPedido = "Iniciá sesión para confirmar tu reserva.";
    } elseif (!hash_equals($_SESSION["token"], $_POST["token"] ?? "")) {
        $errorPedido = "La sesión venció. Volvé a intentarlo.";
    } else {
        require_once __DIR__ . "/DB/conexion.php";
        /** @var mysqli $conexion */

        $codigos = [];
        foreach ($destinos as $d) {
            $codigos[$d["nombre"]] = $d["cod"];
        }

        $enviados = json_decode($_POST["items"] ?? "[]", true);
        $metodo = $_POST["metodo_pago"] ?? "";
        if (!isset($metodosPago[$metodo])) {
            $errorPedido = "Elegí un medio de pago válido.";
        }
        $lineas = [];
        $total = 0;
        $stmt = mysqli_prepare($conexion, "SELECT id_producto, precio_unitario FROM producto WHERE codigo = ?");

        foreach ((array) $enviados as $item) {
            $nombre = $item["nombre"] ?? "";
            $cantidad = (int) ($item["cantidad"] ?? 0);

            if ($nombre == "" || $cantidad < 1 || $cantidad > 20) {
                $errorPedido = "Hay un producto inválido en el carrito.";
                break;
            }

            $codigo = $item["codigo"] ?? ($codigos[$nombre] ?? $nombre);
            mysqli_stmt_bind_param($stmt, "s", $codigo);
            mysqli_stmt_execute($stmt);
            $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$producto) {
                $errorPedido = "El producto " . $nombre . " no está disponible.";
                break;
            }

            $precio = (float) $producto["precio_unitario"];
            $lineas[] = ["id" => $producto["id_producto"], "nombre" => $nombre, "cantidad" => $cantidad, "precio" => $precio, "subtotal" => $precio * $cantidad];
            $total += $precio * $cantidad;
        }

        if ($errorPedido == "" && count($lineas) == 0) {
            $errorPedido = "Tu carrito está vacío.";
        }

        if ($errorPedido == "") {
            mysqli_begin_transaction($conexion);

            try {
                $provisorio = uniqid("T");
                $st = mysqli_prepare($conexion, "INSERT INTO pedido (numero_pedido, id_cliente, estado, total) VALUES (?, ?, 'pendiente', ?)");
                mysqli_stmt_bind_param($st, "sid", $provisorio, $_SESSION["id_cliente"], $total);
                mysqli_stmt_execute($st);
                $idPedido = mysqli_insert_id($conexion);

                $numero = "TG-" . str_pad($idPedido, 5, "0", STR_PAD_LEFT);
                $st = mysqli_prepare($conexion, "UPDATE pedido SET numero_pedido = ? WHERE id_pedido = ?");
                mysqli_stmt_bind_param($st, "si", $numero, $idPedido);
                mysqli_stmt_execute($st);

                $st = mysqli_prepare($conexion, "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                foreach ($lineas as $l) {
                    mysqli_stmt_bind_param($st, "iiidd", $idPedido, $l["id"], $l["cantidad"], $l["precio"], $l["subtotal"]);
                    mysqli_stmt_execute($st);
                }

                $st = mysqli_prepare($conexion, "INSERT INTO venta (id_pedido, monto_total, metodo_pago, estado_cobro) VALUES (?, ?, ?, 'pendiente')");
                mysqli_stmt_bind_param($st, "ids", $idPedido, $total, $metodo);
                mysqli_stmt_execute($st);

                mysqli_commit($conexion);
                notificarPedidoCreado($conexion, $idPedido);
                $pedido = ["numero" => $numero, "total" => $total, "metodo" => $metodo, "lineas" => $lineas, "pago" => $metodo == "mercadopago" ? crearPagoMercadoPago($numero, $lineas) : null];
            } catch (Throwable $e) {
                mysqli_rollback($conexion);
                $errorPedido = "No pudimos registrar el pedido. Intentá de nuevo en unos minutos.";
            }
        }
    }
}
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

    <main class="carrito-pagina">
        <div class="contenedor">

            <h2 class="titulo-carrito">Tu carrito</h2>

            <?php $paso = ($pedido || $resultadoPago != "") ? 3 : 1; ?>
            <ol class="pasos-compra">
                <li id="pasoCarrito" class="<?php echo $paso == 1 ? "actual" : "hecho"; ?>"><span>1</span> Carrito</li>
                <li id="pasoMedio" class="<?php echo $paso == 3 ? "hecho" : ""; ?>"><span>2</span> Medio de pago</li>
                <li class="<?php echo $paso == 3 ? "actual" : ""; ?>"><span>3</span> Confirmación</li>
            </ol>

            <?php if ($resultadoPago == "aprobado") { ?>
            <div class="mensaje-exito"><i class="fa-solid fa-circle-check"></i> Pago aprobado. Tu compra quedó
                registrada como pagada y pendiente de entrega. Podés seguirla en <a href="pedidos.php">Mis pedidos</a>.
            </div>
            <?php } elseif ($resultadoPago == "rechazado") { ?>
            <div class="error-login">El pago fue rechazado. Tu pedido sigue pendiente de pago; podés intentarlo de nuevo
                desde <a href="pedidos.php">Mis pedidos</a>.</div>
            <?php } elseif ($resultadoPago == "pendiente") { ?>
            <div class="error-login">El pago todavía está en proceso. Lo vas a ver actualizado en <a
                    href="pedidos.php">Mis pedidos</a>.</div>
            <?php } ?>

            <?php if ($errorPedido != "") { ?>
            <div class="error-login"><?php echo limpiar($errorPedido); ?></div>
            <?php } ?>

            <?php if ($pedido) { ?>
            <div class="confirmacion">
                <p class="confirmacion-estado"><i class="fa-solid fa-circle-check"></i> Reserva registrada</p>
                <h3>Pedido <?php echo limpiar($pedido["numero"]); ?></h3>

                <table class="tabla-carrito">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedido["lineas"] as $l) { ?>
                        <tr>
                            <td data-label="Producto" class="nombre"><?php echo limpiar($l["nombre"]); ?></td>
                            <td data-label="Cantidad"><?php echo $l["cantidad"]; ?></td>
                            <td data-label="Precio">$<?php echo number_format($l["precio"], 0, ",", "."); ?></td>
                            <td data-label="Subtotal" class="subtotal">
                                $<?php echo number_format($l["subtotal"], 0, ",", "."); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <p class="confirmacion-total">Total
                    <strong>$<?php echo number_format($pedido["total"], 0, ",", "."); ?></strong>
                </p>
                <p>Medio de pago: <strong><?php echo $metodosPago[$pedido["metodo"]]; ?></strong> · Pago pendiente</p>

                <?php if ($pedido["pago"]) { ?>
                <a href="<?php echo limpiar($pedido["pago"]); ?>" class="btn-reservar"><i
                        class="fa-solid fa-credit-card"></i> Pagar con Mercado Pago</a>
                <?php } elseif ($pedido["metodo"] == "mercadopago") { ?>
                <p class="resumen-nota">El cobro online todavía no está habilitado. El pedido queda pendiente de pago.
                </p>
                <?php } else { ?>
                <p class="resumen-nota">El pedido queda pendiente hasta que la agencia registre el pago.</p>
                <?php } ?>

                <div class="confirmacion-acciones">
                    <a href="pedidos.php" class="btn-vaciar">Ver mis pedidos</a>
                    <a href="index.php#destinos" class="btn-vaciar">Seguir comprando</a>
                </div>
            </div>
            <?php } else { ?>

            <div class="checkout-grid">
                <div>
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

                    <?php if ($titular) { ?>
                    <?php
            $infoPago = [
                "mercadopago" => ["fa-solid fa-credit-card", "Pagá con tarjeta de crédito, débito o dinero en cuenta."],
                "transferencia" => ["fa-solid fa-building-columns", "La reserva se confirma cuando registramos la transferencia."],
                "agencia" => ["fa-solid fa-store", "Aboná en la oficina de TurisGo en Caleta Olivia."],
            ];
            ?>
                    <section class="panel-pago" id="panelPago" hidden>
                        <h3>Elegí cómo pagar</h3>
                        <p>El pedido queda reservado a tu nombre hasta que se registre el pago.</p>

                        <?php $primero = true; foreach ($metodosPago as $clave => $texto) { ?>
                        <label class="opcion-pago">
                            <input type="radio" name="metodoPago" value="<?php echo $clave; ?>"
                                <?php echo $primero ? "checked" : ""; ?>>
                            <i class="<?php echo $infoPago[$clave][0] ?? "fa-solid fa-wallet"; ?>"></i>
                            <span>
                                <strong><?php echo $texto; ?></strong>
                                <small><?php echo $infoPago[$clave][1] ?? ""; ?></small>
                            </span>
                        </label>
                        <?php $primero = false; } ?>
                    </section>
                    <?php } ?>

                </div>

                <aside class="resumen-compra" id="carritoTotalCaja" hidden>
                    <h3>Resumen de compra</h3>
                    <p class="resumen-linea">Total <strong id="totalCarrito">$0</strong></p>
                    <p class="resumen-nota">Los precios se verifican al confirmar la reserva.</p>

                    <?php if ($titular) { ?>
                    <div class="resumen-titular">
                        <span>Titular de la reserva</span>
                        <strong><?php echo limpiar($titular["nombre"] . " " . $titular["apellido"]); ?></strong>
                        <small><?php echo limpiar($titular["email"]); ?></small>
                    </div>

                    <button type="button" class="btn-reservar" id="btnIrPago">Continuar con el pago <i
                            class="fa-solid fa-arrow-right"></i></button>

                    <div class="paso-pago" id="pasoPago" hidden>
                        <ul class="resumen-items" id="resumenItems"></ul>
                        <label class="check-terminos">
                            <input type="checkbox" id="aceptoCondiciones">
                            <span>Acepto las condiciones de la reserva</span>
                        </label>
                        <button type="button" class="btn-reservar" id="btnReservar"><i class="fa-solid fa-lock"></i>
                            Confirmar reserva</button>
                        <button type="button" class="btn-volver-carrito" id="btnVolverCarrito"><i
                                class="fa-solid fa-arrow-left"></i> Volver al carrito</button>
                    </div>
                    <?php } else { ?>
                    <button type="button" class="btn-reservar" id="btnReservar">Iniciá sesión para comprar</button>
                    <?php } ?>

                    <button type="button" class="btn-vaciar" id="btnVaciar">Vaciar carrito</button>
                </aside>
            </div>

            <?php } ?>
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

    const destinosInfo = <?php echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const sesionIniciada = <?php echo $sesionIniciada ? "true" : "false"; ?>;
    const tokenPedido = "<?php echo $_SESSION["token"]; ?>";
    <?php if ($pedido) { ?>try {
        localStorage.removeItem("carritoTurisgo");
    } catch (e) {}
    <?php } ?>
    </script>

    <script src="js/carrito.js?v=<?php echo filemtime(__DIR__ . "/js/carrito.js"); ?>"></script>
    <script src="js/header.js?v=<?php echo filemtime(__DIR__ . "/js/header.js"); ?>"></script>

</body>

</html>