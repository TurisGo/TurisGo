<?php
session_start();

if (($_SESSION["rol"] ?? "") != "jefe_ventas") {
    header("Location: index.php?login=1");
    exit;
}

require_once __DIR__ . "/DB/conexion.php";
require_once __DIR__ . "/mailer.php";
/** @var mysqli $conexion */

$titulo = "Panel de jefe de ventas | TurisGo";
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
    } elseif (isset($_POST["cobrar"])) {
        $vista = $_POST["desde"] ?? "cuenta";
        $st = mysqli_prepare($conexion, "UPDATE venta SET estado_cobro = 'pagado' WHERE id_pedido = ? AND estado_cobro = 'pendiente'");
        mysqli_stmt_bind_param($st, "i", $idPedido);
        mysqli_stmt_execute($st);

        if (mysqli_stmt_affected_rows($st) == 1) {
            notificarCobroConfirmado($conexion, $idPedido);
            $mensaje = "Cobro registrado.";
        } else {
            $error = "Esa venta ya no estaba pendiente de cobro.";
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
    } elseif (isset($_POST["editar_producto"])) {
        $vista = "productos";
        $idProducto = (int) ($_POST["id_producto"] ?? 0);
        $codigo = trim($_POST["codigo"] ?? "");
        $descripcion = trim($_POST["descripcion"] ?? "");
        $tipo = $_POST["tipo"] ?? "";
        $precio = (float) str_replace(",", ".", $_POST["precio"] ?? "0");

        if ($codigo == "" || strlen($codigo) > 50 || $descripcion == "" || !isset($tipos[$tipo]) || $precio <= 0) {
            $error = "Completá código, descripción, tipo y un precio mayor a cero.";
        } else {
            try {
                $st = mysqli_prepare($conexion, "UPDATE producto SET codigo = ?, descripcion = ?, tipo_producto = ?, precio_unitario = ? WHERE id_producto = ?");
                mysqli_stmt_bind_param($st, "sssdi", $codigo, $descripcion, $tipo, $precio, $idProducto);
                mysqli_stmt_execute($st);
                $mensaje = "Producto actualizado.";
            } catch (Throwable $e) {
                $error = "Ya existe otro producto con ese código.";
            }
        }
    } elseif (isset($_POST["eliminar_producto"])) {
        $vista = "productos";
        $idProducto = (int) ($_POST["id_producto"] ?? 0);

        try {
            $st = mysqli_prepare($conexion, "DELETE FROM producto WHERE id_producto = ?");
            mysqli_stmt_bind_param($st, "i", $idProducto);
            mysqli_stmt_execute($st);

            if (mysqli_stmt_affected_rows($st) == 1) {
                $mensaje = "Producto eliminado.";
            } else {
                $error = "Ese producto ya no existe.";
            }
        } catch (Throwable $e) {
            $error = "No se puede eliminar: el producto ya figura en pedidos anteriores.";
        }
    }
}

require __DIR__ . "/includes/cabecera.php";
?>
<h2 class="titulo-carrito">Panel de jefe de ventas</h2>

<nav class="pestanas">
    <a href="?v=pendientes" class="<?php echo $vista == "pendientes" ? "activa" : ""; ?>"><i
            class="fa-solid fa-box"></i> Pedidos pendientes</a>
    <a href="?v=productos" class="<?php echo in_array($vista, ["productos", "editar"]) ? "activa" : ""; ?>"><i
            class="fa-solid fa-list"></i> Productos</a>
    <a href="?v=cuenta" class="<?php echo $vista == "cuenta" ? "activa" : ""; ?>"><i
            class="fa-solid fa-file-invoice-dollar"></i> Estado de cuenta</a>
    <a href="?v=historial" class="<?php echo $vista == "historial" ? "activa" : ""; ?>"><i
            class="fa-solid fa-clock-rotate-left"></i> Historial de ventas</a>
</nav>

<?php if ($mensaje != "") { ?><div class="mensaje-exito"><?php echo limpiar($mensaje); ?></div><?php } ?>
<?php if ($error != "") { ?><div class="error-login"><?php echo limpiar($error); ?></div><?php } ?>

<?php if ($vista == "editar") {
    $idProducto = (int) ($_GET["id"] ?? 0);
    $st = mysqli_prepare($conexion, "SELECT id_producto, codigo, descripcion, tipo_producto, precio_unitario FROM producto WHERE id_producto = ?");
    mysqli_stmt_bind_param($st, "i", $idProducto);
    mysqli_stmt_execute($st);
    $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

    if (!$producto) { ?>
<p class="nota-lista">Ese producto no existe. <a href="?v=productos">Volver a la lista</a>.</p>
<?php } else { ?>
<form method="POST" class="form-linea bloque-pedido">
    <input type="hidden" name="token" value="<?php echo $_SESSION["token"]; ?>">
    <input type="hidden" name="id_producto" value="<?php echo $producto["id_producto"]; ?>">
    <input type="text" name="codigo" value="<?php echo limpiar($producto["codigo"]); ?>" placeholder="Código"
        maxlength="50" required>
    <input type="text" name="descripcion" value="<?php echo limpiar($producto["descripcion"]); ?>"
        placeholder="Descripción" required>
    <select name="tipo" required>
        <?php foreach ($tipos as $valor => $texto) { ?>
        <option value="<?php echo $valor; ?>" <?php echo $producto["tipo_producto"] == $valor ? "selected" : ""; ?>>
            <?php echo $texto; ?></option>
        <?php } ?>
    </select>
    <input type="number" name="precio" value="<?php echo $producto["precio_unitario"]; ?>"
        placeholder="Precio unitario" min="1" step="0.01" required>
    <button type="submit" name="editar_producto" value="1" class="btn-reservar"><i
            class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
</form>
<p class="nota-lista"><a href="?v=productos">Cancelar y volver a la lista</a></p>
<?php } ?>

<?php } elseif ($vista == "productos") {
    $productos = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_producto, codigo, descripcion, tipo_producto, precio_unitario FROM producto ORDER BY codigo"), MYSQLI_ASSOC); ?>

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
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $p) { ?>
            <tr>
                <td data-label="Código"><?php echo limpiar($p["codigo"]); ?></td>
                <td data-label="Descripción" class="nombre"><?php echo limpiar($p["descripcion"]); ?></td>
                <td data-label="Tipo"><?php echo $tipos[$p["tipo_producto"]]; ?></td>
                <td data-label="Precio" class="subtotal"><?php echo dinero($p["precio_unitario"]); ?></td>
                <td data-label="Acciones">
                    <div class="carrito-botones">
                        <a href="?v=editar&id=<?php echo $p["id_producto"]; ?>" class="btn-reservar"><i
                                class="fa-solid fa-pen"></i> Editar</a>
                        <form method="POST">
                            <input type="hidden" name="token" value="<?php echo $_SESSION["token"]; ?>">
                            <input type="hidden" name="id_producto" value="<?php echo $p["id_producto"]; ?>">
                            <button type="submit" name="eliminar_producto" value="1" class="btn-vaciar"
                                onclick="return confirm('¿Eliminar el producto <?php echo limpiar($p["codigo"]); ?>?')">
                                <i class="fa-solid fa-trash"></i> Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php } elseif ($vista == "cuenta") {
    $estadoFiltro = $_GET["estado"] ?? "pendiente";
    if (!in_array($estadoFiltro, ["pendiente", "pagado", "rechazado", "todos"])) {
        $estadoFiltro = "pendiente";
    }
    $porCliente = ($_GET["orden"] ?? "fecha") == "cliente";
    $orden = $porCliente ? "cliente ASC, v.fecha_venta DESC" : "v.fecha_venta DESC";
    $condicion = $estadoFiltro == "todos" ? "1=1" : "v.estado_cobro = '" . $estadoFiltro . "'";

    $totales = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT
            COALESCE(SUM(CASE WHEN estado_cobro = 'pendiente' THEN monto_total END), 0) AS pendiente,
            COALESCE(SUM(CASE WHEN estado_cobro = 'pagado' THEN monto_total END), 0) AS cobrado
        FROM venta"));

    $cuentas = mysqli_fetch_all(mysqli_query($conexion, "SELECT v.id_pedido, v.fecha_venta, v.monto_total, v.metodo_pago, v.estado_cobro,
            COALESCE(p.numero_pedido, CONCAT('TG-', LPAD(v.id_pedido, 5, '0'))) AS numero,
            CONCAT(c.nombre, ' ', c.apellido) AS cliente
        FROM venta v
        LEFT JOIN pedido p ON p.id_pedido = v.id_pedido
        LEFT JOIN venta_historial h ON h.id_pedido = v.id_pedido
        LEFT JOIN cliente c ON c.id_cliente = COALESCE(p.id_cliente, h.id_cliente)
        WHERE " . $condicion . "
        ORDER BY " . $orden), MYSQLI_ASSOC);

    $estados = ["pendiente" => "Pendientes de cobro", "pagado" => "Cobradas", "rechazado" => "Rechazadas", "todos" => "Todas"]; ?>

<div class="carrito-total">
    <h3>Total pendiente de cobro: <?php echo dinero($totales["pendiente"]); ?></h3>
    <h3>Total ya cobrado: <?php echo dinero($totales["cobrado"]); ?></h3>
</div>

<nav class="pestanas">
    <?php foreach ($estados as $valor => $texto) { ?>
    <a href="?v=cuenta&estado=<?php echo $valor; ?>&orden=<?php echo $porCliente ? "cliente" : "fecha"; ?>"
        class="<?php echo $estadoFiltro == $valor ? "activa" : ""; ?>"><?php echo $texto; ?></a>
    <?php } ?>
</nav>
<nav class="pestanas">
    <a href="?v=cuenta&estado=<?php echo $estadoFiltro; ?>&orden=fecha"
        class="<?php echo $porCliente ? "" : "activa"; ?>">Ordenar por fecha</a>
    <a href="?v=cuenta&estado=<?php echo $estadoFiltro; ?>&orden=cliente"
        class="<?php echo $porCliente ? "activa" : ""; ?>">Ordenar por cliente</a>
</nav>

<?php if (count($cuentas) == 0) { ?>
<div class="carrito-vacio-pagina">
    <p>No hay ventas en este filtro.</p>
</div>
<?php } else { ?>
<div class="tabla-carrito-caja">
    <table class="tabla-carrito">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Pedido</th>
                <th>Método de pago</th>
                <th>Importe</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cuentas as $c) { ?>
            <tr>
                <td data-label="Cliente" class="nombre"><?php echo limpiar($c["cliente"]); ?></td>
                <td data-label="Fecha"><?php echo date("d/m/Y", strtotime($c["fecha_venta"])); ?></td>
                <td data-label="Pedido"><?php echo limpiar($c["numero"]); ?></td>
                <td data-label="Método"><?php echo limpiar(ucfirst($c["metodo_pago"])); ?></td>
                <td data-label="Importe" class="subtotal"><?php echo dinero($c["monto_total"]); ?></td>
                <td data-label="Estado">
                    <?php echo $c["estado_cobro"] == "pagado" ? "Pagado" : ($c["estado_cobro"] == "rechazado" ? "Rechazado" : "A cobrar"); ?>
                </td>
                <td data-label="Acción">
                    <?php if ($c["estado_cobro"] == "pendiente") { ?>
                    <form method="POST" class="form-linea">
                        <input type="hidden" name="token" value="<?php echo $_SESSION["token"]; ?>">
                        <input type="hidden" name="id_pedido" value="<?php echo $c["id_pedido"]; ?>">
                        <input type="hidden" name="desde" value="cuenta">
                        <button type="submit" name="cobrar" value="1" class="btn-reservar"
                            onclick="return confirm('¿Registrar el cobro de <?php echo limpiar($c["numero"]); ?>?')">Cobrar</button>
                    </form>
                    <?php } else { ?>
                    —
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>

<?php } elseif ($vista == "historial") {
    $historial = mysqli_fetch_all(mysqli_query($conexion, "SELECT vh.id_pedido, vh.fecha_venta, vh.fecha_entrega, vh.monto_total, vh.productos_detalle,
            COALESCE(p.numero_pedido, CONCAT('TG-', LPAD(vh.id_pedido, 5, '0'))) AS numero,
            CONCAT(c.nombre, ' ', c.apellido) AS cliente,
            CONCAT(e.nombre, ' ', e.apellido) AS responsable
        FROM venta_historial vh
        JOIN cliente c ON c.id_cliente = vh.id_cliente
        LEFT JOIN pedido p ON p.id_pedido = vh.id_pedido
        LEFT JOIN empleado e ON e.id_empleado = p.id_usuario_responsable
        ORDER BY vh.fecha_venta DESC"), MYSQLI_ASSOC);
    $totalHistorial = array_sum(array_column($historial, "monto_total")); ?>

<?php if (count($historial) == 0) { ?>
<div class="carrito-vacio-pagina">
    <p>Todavía no hay ventas entregadas.</p>
</div>
<?php } else { ?>
<p class="nota-lista"><?php echo count($historial); ?> ventas entregadas por un total de
    <?php echo dinero($totalHistorial); ?>.</p>
<div class="tabla-carrito-caja">
    <table class="tabla-carrito">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Venta</th>
                <th>Entrega</th>
                <th>Productos</th>
                <th>Total</th>
                <th>Responsable</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historial as $h) { ?>
            <tr>
                <td data-label="Pedido"><?php echo limpiar($h["numero"]); ?></td>
                <td data-label="Cliente" class="nombre"><?php echo limpiar($h["cliente"]); ?></td>
                <td data-label="Venta"><?php echo date("d/m/Y", strtotime($h["fecha_venta"])); ?></td>
                <td data-label="Entrega">
                    <?php echo $h["fecha_entrega"] ? date("d/m/Y", strtotime($h["fecha_entrega"])) : "—"; ?></td>
                <td data-label="Productos"><?php echo limpiar($h["productos_detalle"]); ?></td>
                <td data-label="Total" class="subtotal"><?php echo dinero($h["monto_total"]); ?></td>
                <td data-label="Responsable">
                    <?php echo $h["responsable"] ? limpiar($h["responsable"]) : "—"; ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>

<?php } else {
    $pendientes = mysqli_fetch_all(mysqli_query($conexion, "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, CONCAT(c.nombre, ' ', c.apellido) AS cliente,
            GROUP_CONCAT(DISTINCT CONCAT(pr.codigo, ' x', d.cantidad) SEPARATOR ', ') AS productos,
            v.estado_cobro
        FROM pedido p
        JOIN cliente c ON c.id_cliente = p.id_cliente
        LEFT JOIN detalle_pedido d ON d.id_pedido = p.id_pedido
        LEFT JOIN producto pr ON pr.id_producto = d.id_producto
        LEFT JOIN venta v ON v.id_pedido = p.id_pedido
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
                <th>Cobro</th>
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
                <td data-label="Cobro">
                    <?php echo $p["estado_cobro"] == "pagado" ? "Pagado" : "Pendiente"; ?>
                </td>
                <td>
                    <form method="POST" class="form-linea">
                        <input type="hidden" name="token" value="<?php echo $_SESSION["token"]; ?>">
                        <input type="hidden" name="id_pedido" value="<?php echo $p["id_pedido"]; ?>">
                        <input type="hidden" name="desde" value="pendientes">
                        <?php if ($p["estado_cobro"] != "pagado") { ?>
                        <button type="submit" name="cobrar" value="1" class="btn-reservar"
                            onclick="return confirm('¿Registrar el cobro de <?php echo limpiar($p["numero_pedido"]); ?>?')">Cobrar</button>
                        <?php } ?>
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
