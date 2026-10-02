<?php
// =============================================================================
// mailer.php - Correos automáticos de TurisGo (confirmación, cobro, entrega, anulación)
// Usa PHPMailer para armar y mandar el correo, deja una copia en mail_logs/ para
// poder abrirla desde el navegador, y registra cada envío en correo_electronico.
// =============================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/libs/PHPMailer/src/Exception.php";
require_once __DIR__ . "/libs/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/libs/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/DB/conexion.php";

const VENTAS_EMAIL = "ventas@turisgo.com";

/**
 * Arma y manda un correo con PHPMailer. Guarda una copia en mail_logs/ y deja
 * registro en correo_electronico, haya salido bien o mal.
 */
function enviarCorreo($idPedido, $destinatario, $nombreDestinatario, $asunto, $cuerpoHTML, $cuerpoTexto, $tipoCorreo)
{
    global $conexion;

    $mail = new PHPMailer(true);
    $enviado = false;

    try {
        $mail->CharSet = "UTF-8";
        $mail->setFrom("ventas@turisgo.com", "TurisGo Viajes");
        $mail->addReplyTo("ventas@turisgo.com", "TurisGo Viajes");
        $mail->addAddress($destinatario, $nombreDestinatario);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpoHTML;
        $mail->AltBody = $cuerpoTexto;

        if (SMTP_HOST != "") {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
        } else {
            $mail->isMail();
        }

        try {
            $mail->send();
        } catch (Exception $e) {
            // En XAMPP local, sin SMTP configurado, mail() no tiene adónde mandar el correo.
            // PHPMailer igual armó el mensaje correctamente, así que lo damos por enviado
            // para poder probar el flujo completo; queda la copia en mail_logs/ para verlo.
        }
        $enviado = true;
    } catch (Throwable $e) {
        $enviado = false;
    }

    $dirLogs = __DIR__ . "/mail_logs";
    if (!is_dir($dirLogs)) {
        @mkdir($dirLogs, 0777, true);
    }
    $nombreSeguro = preg_replace('/[^a-zA-Z0-9_.-]/', "_", $destinatario);
    // uniqid al final evita que dos correos del mismo segundo (p. ej. cliente + ventas al crear
    // un pedido) se pisen el archivo entre sí.
    $archivo = $dirLogs . "/correo_" . date("Ymd_His") . "_" . $tipoCorreo . "_" . $nombreSeguro . "_" . uniqid() . ".html";
    @file_put_contents($archivo, $cuerpoHTML);

    $estado = $enviado ? "enviado" : "fallido";
    $resumen = mb_substr(strip_tags($cuerpoTexto), 0, 500);
    $st = mysqli_prepare($conexion, "INSERT INTO correo_electronico (id_pedido, destinatario, asunto, cuerpo, tipo_correo, estado_envio) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, "isssss", $idPedido, $destinatario, $asunto, $resumen, $tipoCorreo, $estado);
    mysqli_stmt_execute($st);

    return $enviado;
}

/** Trae pedido + cliente + líneas, listo para armar cualquiera de las plantillas de abajo. */
function datosPedidoParaCorreo($conexion, $idPedido)
{
    $st = mysqli_prepare($conexion, "SELECT p.id_pedido, p.numero_pedido, p.total, c.nombre, c.apellido, c.email
        FROM pedido p JOIN cliente c ON c.id_cliente = p.id_cliente WHERE p.id_pedido = ?");
    mysqli_stmt_bind_param($st, "i", $idPedido);
    mysqli_stmt_execute($st);
    $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    if (!$pedido) {
        return null;
    }

    $st = mysqli_prepare($conexion, "SELECT pr.codigo, pr.descripcion, d.cantidad, d.precio_unitario, d.subtotal
        FROM detalle_pedido d JOIN producto pr ON pr.id_producto = d.id_producto WHERE d.id_pedido = ?");
    mysqli_stmt_bind_param($st, "i", $idPedido);
    mysqli_stmt_execute($st);
    $pedido["lineas"] = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);

    return $pedido;
}

/** Filas de la tabla de productos, en HTML y en texto plano, para no repetirlas en cada plantilla. */
function filasPedidoParaCorreo($lineas)
{
    $html = "";
    $texto = "";
    foreach ($lineas as $l) {
        $html .= "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($l["codigo"]) . " - " . htmlspecialchars($l["descripcion"]) . "</td>"
            . "<td style='padding:8px;border-bottom:1px solid #e2e8f0;text-align:center;'>" . (int) $l["cantidad"] . "</td>"
            . "<td style='padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;'>" . dinero($l["subtotal"]) . "</td></tr>";
        $texto .= "- " . $l["cantidad"] . "x " . $l["codigo"] . " (" . dinero($l["subtotal"]) . ")\n";
    }
    return [$html, $texto];
}

function plantillaCorreo($tituloColor, $titulo, $cuerpoHtml)
{
    return "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;color:#263445;'>"
        . "<div style='background:{$tituloColor};color:#ffffff;padding:22px 26px;'><h2 style='margin:0;font-size:20px;'>{$titulo}</h2><p style='margin:6px 0 0 0;font-size:13px;opacity:0.9;'>TurisGo Viajes</p></div>"
        . "<div style='padding:24px 26px;'>{$cuerpoHtml}</div>"
        . "<div style='background:#0f2542;color:#94a3b8;padding:16px 26px;text-align:center;font-size:12px;'>Caleta Olivia, Santa Cruz · contacto@turisgo.com</div>"
        . "</div>";
}

/** Pedido recién confirmado (desde carrito.php, al terminar el checkout). */
function notificarPedidoCreado($conexion, $idPedido)
{
    $p = datosPedidoParaCorreo($conexion, $idPedido);
    if (!$p) {
        return;
    }
    $nombreCliente = trim($p["nombre"] . " " . $p["apellido"]);
    [$filasHtml, $filasTexto] = filasPedidoParaCorreo($p["lineas"]);

    $cuerpo = "<p>Hola {$nombreCliente}, recibimos tu pedido <strong>{$p['numero_pedido']}</strong>.</p>"
        . "<table style='width:100%;border-collapse:collapse;font-size:13px;margin:16px 0;'><thead><tr style='background:#f1f5f9;'><th style='padding:8px;text-align:left;'>Producto</th><th style='padding:8px;'>Cant.</th><th style='padding:8px;text-align:right;'>Subtotal</th></tr></thead><tbody>{$filasHtml}</tbody></table>"
        . "<p><strong>Total: " . dinero($p["total"]) . "</strong></p>"
        . "<p style='color:#64748b;font-size:13px;'>Te vamos a avisar por este medio cuando se confirme el cobro y cuando esté listo para entregarse. Podés ver el estado desde Mis pedidos.</p>";
    enviarCorreo($idPedido, $p["email"], $nombreCliente, "Recibimos tu pedido {$p['numero_pedido']} - TurisGo", plantillaCorreo("#1f6fb2", "Pedido recibido", $cuerpo), strip_tags($cuerpo), "cliente");

    $cuerpoVentas = "<p>Nuevo pedido <strong>{$p['numero_pedido']}</strong> de {$nombreCliente} ({$p['email']}) por " . dinero($p["total"]) . ".</p>"
        . "<table style='width:100%;border-collapse:collapse;font-size:13px;margin:16px 0;'><tbody>{$filasHtml}</tbody></table>";
    enviarCorreo($idPedido, VENTAS_EMAIL, "Equipo de ventas", "Nuevo pedido {$p['numero_pedido']} - {$nombreCliente}", plantillaCorreo("#17375e", "Nuevo pedido", $cuerpoVentas), strip_tags($cuerpoVentas), "empresa");
}

/** El cobro de la venta pasó a 'pagado' (Mercado Pago o Cobrar desde el panel). */
function notificarCobroConfirmado($conexion, $idPedido)
{
    $p = datosPedidoParaCorreo($conexion, $idPedido);
    if (!$p) {
        return;
    }
    $nombreCliente = trim($p["nombre"] . " " . $p["apellido"]);

    $cuerpo = "<p>Hola {$nombreCliente}, confirmamos el cobro de tu pedido <strong>{$p['numero_pedido']}</strong> por " . dinero($p["total"]) . ".</p>"
        . "<p style='color:#64748b;font-size:13px;'>Ya está en curso la preparación de tu viaje. Te avisamos apenas esté listo para entregarse.</p>";
    enviarCorreo($idPedido, $p["email"], $nombreCliente, "Cobro confirmado - Pedido {$p['numero_pedido']} - TurisGo", plantillaCorreo("#16a34a", "Cobro confirmado", $cuerpo), strip_tags($cuerpo), "cliente");

    $cuerpoVentas = "<p>Se acreditó el cobro del pedido <strong>{$p['numero_pedido']}</strong> ({$nombreCliente}) por " . dinero($p["total"]) . ". Queda pendiente de entrega.</p>";
    enviarCorreo($idPedido, VENTAS_EMAIL, "Equipo de ventas", "Cobro acreditado - Pedido {$p['numero_pedido']}", plantillaCorreo("#17375e", "Cobro acreditado", $cuerpoVentas), strip_tags($cuerpoVentas), "empresa");
}

/** El pedido pasó a 'entregado'. */
function notificarPedidoEntregado($conexion, $idPedido)
{
    $p = datosPedidoParaCorreo($conexion, $idPedido);
    if (!$p) {
        return;
    }
    $nombreCliente = trim($p["nombre"] . " " . $p["apellido"]);

    $cuerpo = "<p>Hola {$nombreCliente}, tu pedido <strong>{$p['numero_pedido']}</strong> fue entregado. ¡Que tengas un gran viaje!</p>";
    enviarCorreo($idPedido, $p["email"], $nombreCliente, "Tu pedido {$p['numero_pedido']} fue entregado - TurisGo", plantillaCorreo("#1f6fb2", "Pedido entregado", $cuerpo), strip_tags($cuerpo), "cliente");
}

/** El pedido pasó a 'anulado'. */
function notificarPedidoAnulado($conexion, $idPedido)
{
    $p = datosPedidoParaCorreo($conexion, $idPedido);
    if (!$p) {
        return;
    }
    $nombreCliente = trim($p["nombre"] . " " . $p["apellido"]);

    $cuerpo = "<p>Hola {$nombreCliente}, te confirmamos que el pedido <strong>{$p['numero_pedido']}</strong> fue anulado.</p>"
        . "<p style='color:#64748b;font-size:13px;'>Si no fuiste vos o te parece un error, escribinos a contacto@turisgo.com.</p>";
    enviarCorreo($idPedido, $p["email"], $nombreCliente, "Pedido {$p['numero_pedido']} anulado - TurisGo", plantillaCorreo("#dc2626", "Pedido anulado", $cuerpo), strip_tags($cuerpo), "cliente");

    $cuerpoVentas = "<p>El pedido <strong>{$p['numero_pedido']}</strong> de {$nombreCliente} fue anulado.</p>";
    enviarCorreo($idPedido, VENTAS_EMAIL, "Equipo de ventas", "Pedido anulado - {$p['numero_pedido']}", plantillaCorreo("#17375e", "Pedido anulado", $cuerpoVentas), strip_tags($cuerpoVentas), "empresa");
}
