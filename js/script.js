let carrito = JSON.parse(localStorage.getItem("carritoTurisgo")) || [];
let regionActiva = "todos";
let tipoActivo = "todos";

document.addEventListener("DOMContentLoaded", function () {
    actualizarCarrito();
    configurarFiltros();
    configurarBuscador();
    configurarFecha();
});


function normalizar(texto) {
    return texto
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9\s]/g, " ")
        .replace(/\s+/g, " ")
        .trim();
}

function mostrarAviso(mensaje) {
    const aviso = document.getElementById("avisoBuscador");
    aviso.textContent = mensaje;
    aviso.hidden = false;
}

function buscarDestino() {
    const destinoInput = document.getElementById("destino");
    const fecha = document.getElementById("fecha").value;
    const pasajeros = document.getElementById("pasajeros").value;
    const palabras = normalizar(destinoInput.value).split(" ").filter(function (p) { return p !== ""; });

    if (palabras.length === 0) {
        mostrarAviso("Escribí un destino o aeropuerto para buscar.");
        destinoInput.classList.add("error");
        destinoInput.focus();
        return;
    }

    document.getElementById("avisoBuscador").hidden = true;
    destinoInput.classList.remove("error");
    resetearFiltros();

    let encontrados = 0;

    document.querySelectorAll(".tarjeta").forEach(function (tarjeta) {
        const texto = normalizar(
            tarjeta.dataset.destino + " " +
            tarjeta.querySelector(".ubicacion").textContent + " " +
            tarjeta.querySelector("h3").textContent
        );

        const coincide = palabras.every(function (p) { return texto.includes(p); });

        tarjeta.style.display = coincide ? "block" : "none";
        if (coincide) encontrados++;
    });

    const barra = document.getElementById("resultadoBusqueda");
    const sinResultados = document.getElementById("sinResultados");

    if (encontrados === 0) {
        barra.hidden = true;
        sinResultados.hidden = false;
    } else {
        let detalle = encontrados + (encontrados === 1 ? " destino" : " destinos") + ' para "' + destinoInput.value.trim() + '"';

        if (fecha !== "") detalle += "  ·  Check-in: " + fecha.split("-").reverse().join("/");
        detalle += "  ·  " + pasajeros + (pasajeros === "1" ? " pasajero" : " pasajeros");

        document.getElementById("resultadoTexto").textContent = detalle;
        sinResultados.hidden = true;
        barra.hidden = false;
    }

    document.getElementById("destinos").scrollIntoView({ behavior: "smooth" });
}

function sugerirDestino(nombre) {
    document.getElementById("destino").value = nombre;
    document.getElementById("btnLimpiar").hidden = false;
    buscarDestino();
}

function ocultarResultado() {
    document.getElementById("resultadoBusqueda").hidden = true;
    document.getElementById("sinResultados").hidden = true;
}

function limpiarBusqueda() {
    const input = document.getElementById("destino");

    input.value = "";
    input.classList.remove("error");
    document.getElementById("btnLimpiar").hidden = true;
    document.getElementById("avisoBuscador").hidden = true;

    const headerInput = document.getElementById("buscarHeader");
    if (headerInput) headerInput.value = "";

    ocultarResultado();
    resetearFiltros();
    aplicarFiltros();
}

function resetearFiltros() {
    regionActiva = "todos";
    tipoActivo = "todos";

    document.querySelectorAll(".filtro-principal").forEach(function (b) {
        b.classList.toggle("activo", b.dataset.region === "todos");
    });

    document.querySelectorAll(".servicio").forEach(function (b) {
        b.classList.toggle("activo", b.dataset.tipo === "todos");
    });
}

function configurarBuscador() {
    const input = document.getElementById("destino");
    const limpiar = document.getElementById("btnLimpiar");

    input.addEventListener("input", function (e) {
        limpiar.hidden = input.value === "";
        input.classList.remove("error");
        document.getElementById("avisoBuscador").hidden = true;

        if (e.inputType === "insertReplacementText") buscarDestino();
    });

    limpiar.addEventListener("click", function () {
        limpiarBusqueda();
        input.focus();
    });

    ["destino", "fecha", "pasajeros"].forEach(function (id) {
        document.getElementById(id).addEventListener("keydown", function (e) {
            if (e.key === "Enter") buscarDestino();
        });
    });
}


function aplicarFiltros() {
    document.querySelectorAll(".tarjeta").forEach(function (tarjeta) {
        const coincideRegion = regionActiva === "todos" || tarjeta.dataset.region === regionActiva;
        const coincideTipo = tipoActivo === "todos" || tarjeta.dataset.tipo === tipoActivo;

        tarjeta.style.display = (coincideRegion && coincideTipo) ? "block" : "none";
    });
}

function configurarFiltros() {
    const botonesRegion = document.querySelectorAll(".filtro-principal");
    const botonesServicio = document.querySelectorAll(".servicio");

    botonesRegion.forEach(function (boton) {
        boton.addEventListener("click", function () {
            botonesRegion.forEach(function (b) { b.classList.remove("activo"); });
            boton.classList.add("activo");

            regionActiva = boton.dataset.region;
            ocultarResultado();
            aplicarFiltros();
        });
    });

    botonesServicio.forEach(function (boton) {
        boton.addEventListener("click", function () {
            botonesServicio.forEach(function (b) { b.classList.remove("activo"); });
            boton.classList.add("activo");

            tipoActivo = boton.dataset.tipo;
            ocultarResultado();
            aplicarFiltros();
        });
    });
}


function agregarCarrito(nombre, precio, codigo) {
    const existente = carrito.find(function (p) { return p.nombre === nombre; });

    if (existente) {
        existente.cantidad++;
    } else {
        carrito.push({ nombre: nombre, precio: precio, cantidad: 1, codigo: codigo });
    }

    guardarCarrito();
    actualizarCarrito();
    mostrarAvisoCarrito(nombre);
}

function guardarCarrito() {
    localStorage.setItem("carritoTurisgo", JSON.stringify(carrito));
}

function actualizarCarrito() {
    const contador = document.getElementById("contadorCarrito");
    let cantidadTotal = 0;

    carrito.forEach(function (p) { cantidadTotal += p.cantidad; });

    if (contador) contador.textContent = cantidadTotal;

    mostrarProductosCarrito();
}

function mostrarProductosCarrito() {
    const lista = document.getElementById("listaCarrito");
    const totalElemento = document.getElementById("totalCarrito");

    if (!lista) return;

    lista.innerHTML = "";

    if (carrito.length === 0) {
        lista.innerHTML = '<div class="carrito-vacio">Tu carrito está vacío.</div>';
        totalElemento.textContent = "$0";
        return;
    }

    let total = 0;

    carrito.forEach(function (producto, indice) {
        const subtotal = producto.precio * producto.cantidad;
        total += subtotal;

        const item = document.createElement("div");
        item.className = "item-carrito";

        item.innerHTML = `
            <div class="item-carrito-info">
                <strong>${producto.nombre}</strong>
                <span>${producto.cantidad} x $${producto.precio.toLocaleString("es-AR")}</span>
            </div>
            <strong>$${subtotal.toLocaleString("es-AR")}</strong>
            <button class="btn-eliminar" onclick="eliminarCarrito(${indice})">Eliminar</button>
        `;

        lista.appendChild(item);
    });

    totalElemento.textContent = "$" + total.toLocaleString("es-AR");
}

function eliminarCarrito(indice) {
    carrito.splice(indice, 1);
    guardarCarrito();
    actualizarCarrito();
}

function abrirCarrito() {
    actualizarCarrito();
    document.getElementById("modalCarrito").classList.add("mostrar");
}

function cerrarCarrito() {
    document.getElementById("modalCarrito").classList.remove("mostrar");
}

function finalizarCompra() {
    if (carrito.length === 0) {
        alert("Tu carrito está vacío.");
        return;
    }

    location.href = "carrito.php";
}


function abrirLogin() {
    document.getElementById("modalLogin").classList.add("mostrar");
}

function cerrarLogin() {
    document.getElementById("modalLogin").classList.remove("mostrar");
}


function contactar() {
    alert(
        "TurisGo Viajes\n\n" +
        "Email: contacto@turisgo.com\n" +
        "Teléfono: +54 297 000 0000"
    );
}


function configurarFecha() {
    const fecha = document.getElementById("fecha");
    if (!fecha) return;

    const hoy = new Date();
    const año = hoy.getFullYear();
    const mes = String(hoy.getMonth() + 1).padStart(2, "0");
    const dia = String(hoy.getDate()).padStart(2, "0");

    fecha.min = `${año}-${mes}-${dia}`;
}


window.addEventListener("click", function (event) {
    if (event.target === document.getElementById("modalLogin")) cerrarLogin();
    if (event.target === document.getElementById("modalCarrito")) cerrarCarrito();
});


let temporizadorAviso;

function mostrarAvisoCarrito(nombre) {
    const aviso = document.getElementById("avisoCarrito");
    const contador = document.getElementById("contadorCarrito");

    aviso.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
    aviso.append(nombre + " se agregó al carrito");
    aviso.classList.add("visible");

    if (contador) {
        contador.classList.remove("salto");
        void contador.offsetWidth;
        contador.classList.add("salto");
    }

    clearTimeout(temporizadorAviso);
    temporizadorAviso = setTimeout(function () { aviso.classList.remove("visible"); }, 2500);
}