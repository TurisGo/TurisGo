<?php
// Barra de navegación superior. La usan todas las páginas (vía includes/cabecera.php,
// o directamente en index.php, registro.php y carrito.php, que tienen su propio <head>).
// $carritoDrawer = true hace que el ícono del carrito abra el panel con JS en vez de
// enlazar a carrito.php; lo usan index.php y registro.php, que tienen ese panel en la página.
$carritoDrawer = $carritoDrawer ?? false;
$rolSesion = $_SESSION["rol"] ?? "";
?>
<header class="header">
    <div class="contenedor nav">

        <button class="btn-menu" id="btnMenu" type="button" aria-label="Abrir menú" aria-expanded="false"
            aria-controls="menuPrincipal"><i class="fa-solid fa-bars"></i></button>

        <a href="index.php#inicio" class="logo">
            <div class="logo-icono"><i class="fa-solid fa-plane"></i></div>
            <div class="logo-texto">
                <h2>TurisGo <span>Viajes</span></h2>
            </div>
        </a>

        <form class="buscador-header" id="formBuscadorHeader" action="index.php" method="get" role="search">
            <span class="lupa"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="search" id="buscarHeader" name="buscar" placeholder="Buscar destinos..." autocomplete="off"
                aria-label="Buscar destinos">
        </form>

        <nav class="menu" id="menuPrincipal">
            <a href="index.php#inicio">Inicio</a>
            <a href="index.php#destinos">Destinos</a>
            <a href="productos.php">Productos</a>
            <a href="index.php#contacto">Contacto</a>
        </nav>

        <div class="acciones">

            <button class="carrito" id="btnTema" onclick="cambiarTema()" aria-label="Cambiar tema"><i
                    class="fa-solid fa-moon"></i></button>

            <?php if ($carritoDrawer) { ?>
            <button class="carrito" onclick="abrirCarrito()">
                <i class="fa-solid fa-cart-shopping"></i> <span>Carrito</span>
                <b id="contadorCarrito">0</b>
            </button>
            <?php } else { ?>
            <a href="carrito.php" class="carrito" aria-label="Carrito">
                <i class="fa-solid fa-cart-shopping"></i> <span>Carrito</span>
                <b id="contadorCarrito">0</b>
            </a>
            <?php } ?>

            <?php if (isset($_SESSION["usuario"])) { ?>
            <div class="usuario-menu">
                <span>Hola, <?php echo limpiar($_SESSION["usuario"]); ?></span>
                <a href="<?php echo $rolSesion == "ventas" ? "ventas.php" : ($rolSesion == "jefe_ventas" ? "jefe_ventas.php" : "pedidos.php"); ?>"
                    class="btn-logout"><?php echo $rolSesion == "ventas" ? "Panel de ventas" : ($rolSesion == "jefe_ventas" ? "Panel de jefe de ventas" : "Mis pedidos"); ?></a>
                <a href="index.php?logout=1" class="btn-logout">Cerrar sesión</a>
            </div>
            <?php } else { ?>
            <a href="index.php?login=1" class="btn-login btn-enlace">Iniciar sesión</a>
            <?php } ?>

        </div>
    </div>
</header>
