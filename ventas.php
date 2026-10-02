<?php
session_start();

if (($_SESSION["rol"] ?? "") != "ventas") {
    header("Location: index.php?login=1");
    exit;
}

require_once __DIR__ . "/DB/conexion.php";
require_once __DIR__ . "/mailer.php";
/** @var mysqli $conexion */

$titulo = "Panel de ventas | TurisGo";
$tipos = ["pasaje" => "Pasaje", "estadia" => "Estadía", "alquiler_auto" => "Alquiler de auto", "paquete_integral" => "Paquete integral"];
$vista = $_GET["v"] ?? "pendientes";
$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idPedido = (int) ($_POST["id_pedido"] ?? 0);

    if (!tokenValido()) {
        $error = "La sesión venció. Volvé a intentarlo.";
    } elseif (isset($_POST["entregar"])) {
        if (entregarPedido($conexion, $idPedido)) {
            $mensaje = "Pedido entregado y pasado al histórico.";
        } else {
            $error = "No se pudo entregar el pedido (¿ya no está pendiente?).";
        }
    } elseif (isset($_POST["anular"])) {
        if (anularPedido($conexion, $idPedido)) {
            $mensaje = "Pedido anulado.";
        } else {
            $error = "Solo se pueden anular pedidos pendientes.";
        }
    } elseif (isset($_POST["nuevo_producto"])) {
        $vista = "productos";
        $codigo = trim($_POST["codigo"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");
        $tipo = $_POST["tipo"] ?? "";
        $precio = (float) str_replace(",", ".", $_POST["precio"] ?? "0");

        if ($codigo == "" || strlen($codigo) > 50 || $descripcion == "" || !isset($tipos[$tipo]) || $precio <= 0) {
            $error = "Completá código, descripción, tipo y un precio mayor a cero.";
        } else {
            try {
                $st = mysqli_prepare($conexion, "INSERT INTO producto (codigo, descripcion, tipo_producto, precio_unitario, stock_disponible, fecha_alta) VALUES (?, ?, ?, ?, 100, CURDATE())");
                mysqli_stmt_bind_param($st, "sssd", $codigo, $descripcion, $tipo, $precio);
                mysqli_stmt_execute($st);
                $mensaje = "Producto cargado.";
            } catch (Throwable $e) {
                $error = "Ya existe un producto con ese código.";
            }
        }
    }
}

require __DIR__ . "/includes/cabecera.php";
?>
<h2 class="titulo-carrito">Panel de ventas</h2>

<nav class="pestanas">
    <a href="?v=pendientes" class="<?php echo $vista == "pendientes" ? "activa" : ""; ?>"><i
            class="fa-solid fa-box"></i> Pedidos pendientes</a>
    <a href="?v=productos" class="<?php echo $vista == "productos" ? "activa" : ""; ?>"><i class="fa-solid fa-list"></i>
        Productos</a>
    <a href="?v=cuenta" class="<?php echo $vista == "cuenta" ? "activa" : ""; ?>"><i
            class="fa-solid fa-file-invoice-dollar"></i> Estado de cuenta</a>
</nav>

<?php if ($mensaje != "") { ?><div class="mensaje-exito"><?php echo limpiar($mensaje); ?></div><?php } ?>
<?php if ($error != "") { ?><div class="error-login"><?php echo limpiar($error); ?></div><?php } ?>

<?php if ($vista == "productos") {
    $productos = mysqli_fetch_all(mysqli_query($conexion, "SELECT codigo, descripcion, tipo_producto, precio_unitario FROM producto ORDER BY codigo"), MYSQLI_ASSOC); ?>

<form method="POST" class="form-linea bloque-pedido">
    <input type="hidden" name="token" value="<?php echo $_SESSION["token"]; ?>">
    <input type="text" name="codigo" placeholder="Código" maxlength="50" required>
    <input type="text" name="descripcion" placeholder="Descripción" required>
    <select name="tipo" required>
        <?php foreach ($tipos as $valor => $texto) { ?><option value="<?php echo $valor; ?>"><?php echo $texto; ?>
        </option><?php } ?>
    </select>
    <input type="number" name="precio" placeholder="Precio unitario" min="1" step="0.01" required>
    <button type="submit" name="nuevo_producto" value="1" class="btn-reservar"><i class="fa-solid fa-plus"></i> Cargar
        producto</button>
</form>

<div class="tabla-carrito-caja">
    <table class="tabla-carrito">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Tipo</th>
                <th>Precio unitario</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $p) { ?>
            <tr>
                <td data-label="Código"><?php echo limpiar($p["codigo"]); ?></td>
                <td data-label="Descripción" class="nombre"><?php echo limpiar($p["descripcion"]); ?></td>
                <td data-label="Tipo"><?php echo $tipos[$p["tipo_producto"]]; ?></td>
                <td data-label="Precio" class="subtotal"><?php echo dinero($p["precio_unitario"]); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php } elseif ($vista == "cuenta") {
    $porCliente = ($_GET["orden"] ?? "fecha") == "cliente";
    $orden = $porCliente ? "cliente ASC, v.fecha_venta DESC" : "v.fecha_venta DESC";
    $cuentas = mysqli_fetch_all(mysqli_query($conexion, "SELECT v.id_venta, v.fecha_venta, v.monto_total,
            COALESCE(p.numero_pedido, CONCAT('TG-', LPAD(v.id_pedido, 5, '0'))) AS numero,
            CONCAT(c.nombre, ' ', c.apellido) AS cliente
        FROM venta v
        LEFT JOIN pedido p ON p.id_pedido = v.id_pedido
        LEFT JOIN venta_historial h ON h.id_pedido = v.id_pedido
        LEFT JOIN cliente c ON c.id_cliente = COALESCE(p.id_cliente, h.id_cliente)
        WHERE v.estado_cobro = 'pendiente'
        ORDER BY " . $orden), MYSQLI_ASSOC);
    $totalCobrar = array_sum(array_column($cuentas, "monto_total")); ?>

<nav class="pestanas">
    <a href="?v=cuenta&orden=fecha" class="<?php echo $porCliente ? "" : "activa"; ?>">Ordenar por fecha</a>
    <a href="?v=cuenta&orden=cliente" class="<?php echo $porCliente ? "activa" : ""; ?>">Ordenar por cliente</a>
</nav>

<div class="tabla-carrito-caja">
    <table class="tabla-carrito">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Pedido</th>
                <th>Importe</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cuentas as $c) { ?>
            <tr>
                <td data-label="Cliente" class="nombre"><?php echo limpiar($c["cliente"]); ?></td>
                <td data-label="Fecha"><?php echo date("d/m/Y", strtotime($c["fecha_venta"])); ?></td>
                <td data-label="Pedido"><?php echo limpiar($c["numero"]); ?></td>
                <td data-label="Importe" class="subtotal"><?php echo dinero($c["monto_total"]); ?></td>
                <td data-label="Estado">A cobrar</td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<div class="carrito-total">
    <h3>Total a cobrar: <?php echo dinero($totalCobrar); ?></h3>
</div>

<?php } else {
    $pendientes = mysqli_fetch_all(mysqli_query($conexion, "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, CONCAT(c.nombre, ' ', c.apellido) AS cliente,
            GROUP_CONCAT(CONCAT(pr.codigo, ' x', d.cantidad) SEPARATOR ', ') AS productos
        FROM pedido p
        JOIN cliente c ON c.id_cliente = p.id_cliente
        LEFT JOIN detalle_pedido d ON d.id_pedido = p.id_pedido
        LEFT JOIN producto pr ON pr.id_producto = d.id_producto
        WHERE p.estado = 'pendiente'
        GROUP BY p.id_pedido
        ORDER BY p.fecha_pedido ASC"), MYSQLI_ASSOC); ?>

<?php if (count($pendientes) == 0) { ?>
<div class="carrito-vacio-pagina">
    <p>No hay pedidos pendientes.</p>
</div>
<?php } else { ?>
<div class="tabla-carrito-caja">
    <table class="tabla-carrito">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Productos</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendientes as $p) { ?>
            <tr>
                <td data-label="Pedido"><?php echo limpiar($p["numero_pedido"]); ?></td>
                <td data-label="Cliente" class="nombre"><?php echo limpiar($p["cliente"]); ?></td>
                <td data-label="Fecha"><?php echo date("d/m/Y H:i", strtotime($p["fecha_pedido"])); ?></td>
                <td data-label="Productos"><?php echo limpiar($p["productos"]); ?></td>
                <td data-label="Total" class="subtotal"><?php echo dinero($p["total"]); ?></td>
                <td data-label="Estado">Pendiente</td>
                <td>
                    <form method="POST" class="form-linea">
                        <input type="hidden" name="token" value="<?php echo $_SESSION["token"]; ?>">
                        <input type="hidden" name="id_pedido" value="<?php echo $p["id_pedido"]; ?>">
                        <button type="submit" name="entregar" value="1" class="btn-reservar"
                            onclick="return confirm('¿Marcar como entregado?')">Entregar</button>
                        <button type="submit" name="anular" value="1" class="btn-vaciar"
                            onclick="return confirm('¿Anular este pedido?')">Anular</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>
<?php } ?>
<?php require __DIR__ . "/includes/pie.php"; ?>