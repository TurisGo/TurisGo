function configurarMenu() {
    const boton = document.getElementById("btnMenu");
    const menu = document.getElementById("menuPrincipal");

    if (!boton || !menu) return;

    function cerrarMenu() {
        menu.classList.remove("abierto");
        boton.setAttribute("aria-expanded", "false");
        boton.innerHTML = '<i class="fa-solid fa-bars"></i>';
    }

    boton.addEventListener("click", function () {
        const abierto = menu.classList.toggle("abierto");
        boton.setAttribute("aria-expanded", abierto ? "true" : "false");
        boton.innerHTML = abierto ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>';
    });

    menu.querySelectorAll("a").forEach(function (enlace) {
        enlace.addEventListener("click", cerrarMenu);
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth > 1250) cerrarMenu();
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") cerrarMenu();
    });
}

function buscarDesdeHeader(texto) {
    const destinoHero = document.getElementById("destino");
    const limpiar = document.getElementById("btnLimpiar");

    destinoHero.value = texto;
    if (limpiar) limpiar.hidden = false;

    buscarDestino();
}

function configurarBuscadorHeader() {
    const form = document.getElementById("formBuscadorHeader");
    const input = document.getElementById("buscarHeader");

    if (!form || !input) return;

    const hayCatalogo = document.getElementById("destino") && typeof buscarDestino === "function";

    if (!hayCatalogo) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        if (input.value.trim() === "") {
            input.focus();
            return;
        }

        buscarDesdeHeader(input.value);
    });

    const consulta = new URLSearchParams(location.search).get("buscar");

    if (consulta && consulta.trim() !== "") {
        input.value = consulta;
        buscarDesdeHeader(consulta);
    }
}

function contarCarrito() {
    const contador = document.getElementById("contadorCarrito");
    if (!contador) return;

    try {
        const items = JSON.parse(localStorage.getItem("carritoTurisgo")) || [];
        contador.textContent = items.reduce(function (total, p) { return total + p.cantidad; }, 0);
    } catch (e) { }
}

document.addEventListener("DOMContentLoaded", function () {
    contarCarrito();
    configurarMenu();
    configurarBuscadorHeader();
});