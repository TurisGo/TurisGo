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
    document.getElementById("btnTema").innerHTML = document.documentElement.dataset.theme == "dark" ?
        '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
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
</script>
<script src="js/header.js?v=<?php echo filemtime(dirname(__DIR__) . "/js/header.js"); ?>"></script>
</body>

</html>