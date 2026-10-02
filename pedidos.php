<?php
session_start();

if (!isset($_SESSION["id_cliente"])) {
    header("Location: index.php?login=1");
    exit;
}

require_once __DIR__ . "/DB/conexion.php";
require_once __DIR__ . "/mailer.php";
/** @var mysqli $conexion */

$titulo = "Mis pedidos | TurisGo";
$idCliente = $_SESSION["id_cliente"];
$mensaje = "";
$error = "";

function pedidoPendienteDelCliente($conexion, $idPedido, $idCliente) {
    $st = mysqli_prepare($conexion, "SELECT id_pedido FROM pedido WHERE id_pedido = ? AND id_cliente = ? AND estado = 'pendiente'");
    mysqli_stmt_bind_param($st, "ii", $idPedido, $idCliente);
    mysqli_stmt_execute($st);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($st)) !== null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idPedido = (int) ($_POST["id_pedido"] ?? 0);

    if (!tokenValido()) {
        $error = "La sesión venció. Volvé a intentarlo.";
    } elseif (!pedidoPendienteDelCliente($conexion, $idPedido, $idCliente)) {
        $error = "Ese pedido no existe o ya no está pendiente.";
    } elseif (isset($_POST["cancelar"])) {
        anularPedido($conexion, $idPedido);
        $mensaje = "El pedido fue cancelado.";
    } elseif (isset($_POST["guardar"])) {
        mysqli_begin_transaction($conexion);

        try {
            foreach ((array) ($_POST["cantidad"] ?? []) as $idDetalle => $cantidad) {
                $cantidad = min(20, max(0, (int) $cantidad));
                $idDetalle = (int) $idDetalle;

                if ($cantidad == 0) {
                    $st = mysqli_prepare($conexion, "DELETE FROM detalle_pedido WHERE id_detalle = ? AND id_pedido = ?");
                    mysqli_stmt_bind_param($st, "ii", $idDetalle, $idPedido);
                } else {
                    $st = mysqli_prepare($conexion, "UPDATE detalle_pedido SET cantidad = ?, subtotal = precio_unitario * ? WHERE id_detalle = ? AND id_pedido = ?");
                    mysqli_stmt_bind_param($st, "iiii", $cantidad, $cantidad, $idDetalle, $idPedido);
                }
                mysqli_stmt_execute($st);
            }

            $st = mysqli_prepare($conexion, "SELECT COALESCE(SUM(subtotal), 0) AS total FROM detalle_pedido WHERE id_pedido = ?");
            mysqli_stmt_bind_param($st, "i", $idPedido);
            mysqli_stmt_execute($st);
            $total = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($st))["total"];

            $st = mysqli_prepare($conexion, "UPDATE pedido SET total = ? WHERE id_pedido = ?");
            mysqli_stmt_bind_param($st, "di", $total, $idPedido);
            mysqli_stmt_execute($st);

            $st = mysqli_prepare($conexion, "UPDATE venta SET monto_total = ? WHERE id_pedido = ?");
            mysqli_stmt_bind_param($st, "di", $total, $idPedido);
            mysqli_stmt_execute($st);

            if ($total == 0) {
                anularPedido($conexion, $idPedido);
            }

            mysqli_commit($conexion);
            $mensaje = "El pedido fue actualizado.";
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            $error = "No pudimos actualizar el pedido.";
        }
    }
}

$st = mysqli_prepare($conexion, "SELECT id_pedido, numero_pedido, fecha_pedido, total FROM pedido WHERE id_cliente = ? AND estado = 'pendiente' ORDER BY fecha_pedido DESC");
mysqli_stmt_bind_param($st, "i", $idCliente);
mysqli_stmt_execute($st);
$pendientes = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);

$st = mysqli_prepare($conexion, "SELECT id_pedido, fecha_venta, fecha_entrega, monto_total, productos_detalle FROM venta_historial WHERE id_cliente = ? ORDER BY fecha_entrega DESC");
mysqli_stmt_bind_param($st, "i", $idCliente);
mysqli_stmt_execute($st);
$historial = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);

require __DIR__ . "/includes/cabecera.php";
?>
<h2 class="titulo-carrito">Mis pedidos</h2>

<?php if ($mensaje != "") { ?><div class="mensaje-exito"><?php echo limpiar($mensaje); ?></div><?php } ?>
<?php if ($error != "") { ?><div class="error-login"><?php echo limpiar($error); ?></div><?php } ?>

<h3 class="subtitulo-panel">Pendientes</h3>

<?php if (count($pendientes) == 0) { ?>
<div class="carrito-vacio-pagina">
    <p>No tenés pedidos pendientes.</p><a href="productos.php" class="btn-explorar">Ver productos</a>
</div>
<?php } ?>

<?php foreach ($pendientes as $ped) {
    $st = mysqli_prepare($conexion, "SELECT d.id_detalle, p.codigo, d.cantidad, d.precio_unitario, d.subtotal FROM detalle_pedido d JOIN producto p ON p.id_producto = d.id_producto WHERE d.id_pedido = ?");
    mysqli_stmt_bind_param($st, "i", $ped["id_pedido"]);
    mysqli_stmt_execute($st);
    $lineas = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC); ?>
<form method="POST" class="bloque-pedido">
    <input type="hidden" name="token" value="<?php echo $_SESSION["token"]; ?>">
    <input type="hidden" name="id_pedido" value="<?php echo $ped["id_pedido"]; ?>">

    <h4>Pedido <?php echo limpiar($ped["numero_pedido"]); ?> <span class="estado-pedido">Pendiente</span></h4>
    <p class="nota-lista"><?php echo date("d/m/Y H:i", strtotime($ped["fecha_pedido"])); ?></p>

    <div class="tabla-carrito-caja">
        <table class="tabla-carrito">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineas as $l) { ?>
                <tr>
                    <td data-label="Código"><?php echo limpiar($l["codigo"]); ?></td>
                    <td data-label="Cantidad"><input type="number" class="campo-cantidad"
                            name="cantidad[<?php echo $l["id_detalle"]; ?>]" min="0" max="20"
                            value="<?php echo $l["cantidad"]; ?>"></td>
                    <td data-label="Precio"><?php echo dinero($l["precio_unitario"]); ?></td>
                    <td data-label="Subtotal" class="subtotal"><?php echo dinero($l["subtotal"]); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="carrito-total">
        <h3>Total: <?php echo dinero($ped["total"]); ?></h3>
        <div class="carrito-botones">
            <button type="submit" name="cancelar" value="1" class="btn-vaciar"
                onclick="return confirm('¿Cancelar este pedido?')">Cancelar pedido</button>
            <button type="submit" name="guardar" value="1" class="btn-reservar">Guardar cambios</button>
        </div>
    </div>
    <p class="nota-lista">Poné la cantidad en 0 para quitar un producto del pedido.</p>
</form>
<?php } ?>

<h3 class="subtitulo-panel">Historial de pedidos entregados</h3>

<?php if (count($historial) == 0) { ?>
<p class="nota-lista">Todavía no tenés pedidos entregados.</p>
<?php } else { ?>
<div class="tabla-carrito-caja">
    <table class="tabla-carrito">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Fecha de compra</th>
                <th>Entrega</th>
                <th>Productos</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historial as $h) { ?>
            <tr>
                <td data-label="Pedido">TG-<?php echo str_pad($h["id_pedido"], 5, "0", STR_PAD_LEFT); ?></td>
                <td data-label="Compra"><?php echo date("d/m/Y", strtotime($h["fecha_venta"])); ?></td>
                <td data-label="Entrega"><?php echo date("d/m/Y", strtotime($h["fecha_entrega"])); ?></td>
                <td data-label="Productos"><?php echo limpiar($h["productos_detalle"]); ?></td>
                <td data-label="Total" class="subtotal"><?php echo dinero($h["monto_total"]); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>
<?php require __DIR__ . "/includes/pie.php"; ?>