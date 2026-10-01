function configurarMenu() {
    const boton = document.getElementById("btnMenu");
    const menu = document.getElementById("menuPrincipal");

    if (!boton || !menu) return;

    function cerrarMenu() {
        menu.classList.remove("abierto");
        boton.setAttribute("aria-expanded", "false");
        boton.textContent = "☰";
    }

    boton.addEventListener("click", function () {
        const abierto = menu.classList.toggle("abierto");
        boton.setAttribute("aria-expanded", abierto ? "true" : "false");
        boton.textContent = abierto ? "✕" : "☰";
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

document.addEventListener("DOMContentLoaded", function () {
    configurarMenu();
    configurarBuscadorHeader();
});