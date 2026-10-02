<?php
$servidor = "sql211.byethost10.com";
$usuario_bd = "b10_43064962";
$password_bd = "turisgo58abc6767";
$nombre_bd = "b12_34567890_turisgo";

try {
    $conexion = mysqli_connect($servidor, $usuario_bd, $password_bd, $nombre_bd);
} catch (mysqli_sql_exception $e) {
    if ($servidor == "localhost" && $usuario_bd == "root" && $password_bd == "") {
        die("No se pudo conectar con la base de datos. Si estás en tu computadora, verificá que MySQL esté iniciado en XAMPP y que exista la base " . $nombre_bd . ". Si subiste el sitio a un hosting, todavía tenés que poner los datos del hosting en DB/conexion.php (servidor, usuario, contraseña y nombre de la base).");
    }
    die("No se pudo conectar con la base de datos del hosting. Revisá que el servidor, el usuario, la contraseña y el nombre de la base en DB/conexion.php sean exactamente los que figuran en el panel de tu hosting, y que la base ya esté creada e importada.");
}

if (!$conexion) {
    die("La conexión ha fallado: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8mb4");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Credencial de Mercado Pago (https://www.mercadopago.com.ar/developers). Dejar vacía hasta tener una real.
define("MP_ACCESS_TOKEN", "");

// Configuración de correo saliente (PHPMailer). Con SMTP_HOST vacío se manda con mail() de PHP,
// que en XAMPP local no llega a ningún lado pero sirve para probar: el correo queda guardado en
// mail_logs/ y registrado en la tabla correo_electronico igual. Completar estos datos con un SMTP
// real (Gmail, Mailtrap, el que sea) para que los correos salgan de verdad.
define("SMTP_HOST", "");
define("SMTP_PORT", 587);
define("SMTP_USER", "");
define("SMTP_PASS", "");
define("SMTP_SECURE", "tls");

if (!function_exists("limpiar")) {
    function limpiar($t)
    {
        return htmlspecialchars((string) $t, ENT_QUOTES, "UTF-8");
    }
}

function dinero($n)
{
    return "$" . number_format($n, 0, ",", ".");
}

function tokenValido()
{
    return hash_equals($_SESSION["token"] ?? "", $_POST["token"] ?? "");
}

function entregarPedido($conexion, $id_pedido)
{
    mysqli_begin_transaction($conexion);

    try {
        $st = mysqli_prepare($conexion, "SELECT id_cliente, total, fecha_pedido FROM pedido WHERE id_pedido = ? AND estado = 'pendiente'");
        mysqli_stmt_bind_param($st, "i", $id_pedido);
        mysqli_stmt_execute($st);
        $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

        if (!$pedido) {
            throw new Exception("Pedido inexistente o ya entregado");
        }

        $st = mysqli_prepare($conexion, "SELECT p.codigo, d.cantidad, d.subtotal FROM detalle_pedido d JOIN producto p ON p.id_producto = d.id_producto WHERE d.id_pedido = ?");
        mysqli_stmt_bind_param($st, "i", $id_pedido);
        mysqli_stmt_execute($st);
        $lineas = [];
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $l) {
            $lineas[] = $l["codigo"] . " x" . $l["cantidad"] . " ($" . $l["subtotal"] . ")";
        }
        $detalle = implode(" | ", $lineas);

        $st = mysqli_prepare($conexion, "INSERT INTO venta_historial (id_pedido, id_cliente, fecha_venta, monto_total, productos_detalle, fecha_entrega) VALUES (?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($st, "iisds", $id_pedido, $pedido["id_cliente"], $pedido["fecha_pedido"], $pedido["total"], $detalle);
        mysqli_stmt_execute($st);

        if (mysqli_stmt_affected_rows($st) !== 1) {
            throw new Exception("No se pudo guardar en el historial");
        }

        $responsable = $_SESSION["id_empleado"] ?? null;
        $st = mysqli_prepare($conexion, "UPDATE pedido SET estado = 'entregado', fecha_entrega = NOW(), id_usuario_responsable = ? WHERE id_pedido = ? AND estado = 'pendiente'");
        mysqli_stmt_bind_param($st, "ii", $responsable, $id_pedido);
        mysqli_stmt_execute($st);

        if (mysqli_stmt_affected_rows($st) !== 1) {
            throw new Exception("El pedido ya no estaba pendiente");
        }

        mysqli_commit($conexion);

        if (function_exists("notificarPedidoEntregado")) {
            notificarPedidoEntregado($conexion, $id_pedido);
        }

        return true;
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return false;
    }
}

/**
 * Anula un pedido pendiente: lo pasa a 'anulado', rechaza el cobro y avisa por correo.
 * La usan ventas.php, jefe_ventas.php y pedidos.php (cancelación del propio cliente).
 */
function anularPedido($conexion, $id_pedido)
{
    $st = mysqli_prepare($conexion, "UPDATE pedido SET estado = 'anulado' WHERE id_pedido = ? AND estado = 'pendiente'");
    mysqli_stmt_bind_param($st, "i", $id_pedido);
    mysqli_stmt_execute($st);

    if (mysqli_stmt_affected_rows($st) !== 1) {
        return false;
    }

    $st = mysqli_prepare($conexion, "UPDATE venta SET estado_cobro = 'rechazado' WHERE id_pedido = ?");
    mysqli_stmt_bind_param($st, "i", $id_pedido);
    mysqli_stmt_execute($st);

    if (function_exists("notificarPedidoAnulado")) {
        notificarPedidoAnulado($conexion, $id_pedido);
    }

    return true;
}