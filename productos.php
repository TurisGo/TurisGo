<?php
session_start();
require_once __DIR__ . "/includes/datos.php";
require_once __DIR__ . "/DB/conexion.php";
/** @var mysqli $conexion */

$titulo = "Lista de productos | TurisGo";
$tipos = ["pasaje" => "Pasaje", "estadia" => "Estadía", "alquiler_auto" => "Alquiler de auto", "paquete_integral" => "Paquete integral"];
$nombres = [];

foreach ($destinos as $d) {
    $nombres[$d["cod"]] = $d["nombre"];
}

$productos = mysqli_fetch_all(mysqli_query($conexion, "SELECT codigo, descripcion, tipo_producto, precio_unitario FROM producto ORDER BY codigo"), MYSQLI_ASSOC);

require __DIR__ . "/includes/cabecera.php";
?>
<h2 class="titulo-carrito">Lista de productos</h2>

<div class="tabla-carrito-caja">
    <table class="tabla-carrito">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th>Tipo</th>
                <th>Precio unitario</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $p) {
            $nombre = $nombres[$p["codigo"]] ?? $p["codigo"]; ?>
            <tr>
                <td data-label="Código"><?php echo limpiar($p["codigo"]); ?></td>
                <td data-label="Descripción" class="nombre">
                    <strong><?php echo limpiar($nombre); ?></strong><small><?php echo limpiar($p["descripcion"]); ?></small>
                </td>
                <td data-label="Tipo"><?php echo $tipos[$p["tipo_producto"]]; ?></td>
                <td data-label="Precio" class="subtotal"><?php echo dinero($p["precio_unitario"]); ?></td>
                <td><button type="button" class="btn-reservar"
                        onclick='agregar(<?php echo json_encode($p["codigo"]); ?>, <?php echo json_encode($nombre); ?>, <?php echo (float) $p["precio_unitario"]; ?>)'><i
                            class="fa-solid fa-cart-plus"></i> Agregar</button></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<p class="nota-lista" id="avisoLista" role="status"></p>

<script>
function agregar(codigo, nombre, precio) {
    var carrito = [];
    try {
        carrito = JSON.parse(localStorage.getItem("carritoTurisgo")) || [];
    } catch (e) {}

    var item = carrito.find(function(p) {
        return p.codigo === codigo || p.nombre === nombre;
    });
    if (item) {
        item.cantidad++;
        item.codigo = codigo;
    } else {
        carrito.push({
            nombre: nombre,
            codigo: codigo,
            precio: precio,
            cantidad: 1
        });
    }

    localStorage.setItem("carritoTurisgo", JSON.stringify(carrito));
    document.getElementById("avisoLista").textContent = nombre + " se agregó al carrito. Revisalo en Carrito.";
}
</script>
<?php require __DIR__ . "/includes/pie.php"; ?>