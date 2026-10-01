const CLAVE_CARRITO = "carritoTurisgo";
let carrito = [];

try {
    carrito = JSON.parse(localStorage.getItem(CLAVE_CARRITO)) || [];
} catch (e) {
    carrito = [];
}

function dinero(numero) {
    return "$" + numero.toLocaleString("es-AR");
}

function guardarCarrito() {
    try {
        localStorage.setItem(CLAVE_CARRITO, JSON.stringify(carrito));
    } catch (e) { }
}

function actualizarContador() {
    const contador = document.getElementById("contadorCarrito");
    let cantidad = 0;

    carrito.forEach(function (p) { cantidad += p.cantidad; });

    if (contador) contador.textContent = cantidad;
}

function celda(clase, etiqueta, contenido) {
    const td = document.createElement("td");

    if (clase) td.className = clase;
    if (etiqueta) td.dataset.label = etiqueta;

    if (typeof contenido === "string") {
        td.textContent = contenido;
    } else if (contenido) {
        td.appendChild(contenido);
    }

    return td;
}

function crearImagen(info, nombre) {
    const img = document.createElement("img");

    img.className = "miniatura";
    img.src = info.img;
    img.alt = nombre;
    img.width = 84;
    img.height = 58;
    img.loading = "lazy";

    return img;
}

function crearNombre(nombre, info) {
    const caja = document.createElement("div");
    const titulo = document.createElement("strong");

    titulo.textContent = nombre;
    caja.appendChild(titulo);

    if (info.pais) {
        const detalle = document.createElement("small");
        detalle.textContent = info.pais + " · " + info.cod + " · " + info.dias + " días";
        caja.appendChild(detalle);
    }

    return caja;
}

function crearControlCantidad(indice, cantidad) {
    const control = document.createElement("div");
    const menos = document.createElement("button");
    const numero = document.createElement("span");
    const mas = document.createElement("button");

    control.className = "cantidad-control";

    menos.type = "button";
    menos.textContent = "−";
    menos.setAttribute("aria-label", "Restar una unidad");
    menos.disabled = cantidad <= 1;
    menos.addEventListener("click", function () { cambiarCantidad(indice, -1); });

    numero.textContent = cantidad;

    mas.type = "button";
    mas.textContent = "+";
    mas.setAttribute("aria-label", "Sumar una unidad");
    mas.addEventListener("click", function () { cambiarCantidad(indice, 1); });

    control.appendChild(menos);
    control.appendChild(numero);
    control.appendChild(mas);

    return control;
}

function crearBotonEliminar(indice) {
    const boton = document.createElement("button");

    boton.type = "button";
    boton.className = "btn-eliminar";
    boton.textContent = "Eliminar";
    boton.addEventListener("click", function () { eliminarProducto(indice); });

    return boton;
}

function mostrarCarrito() {
    const cuerpo = document.getElementById("cuerpoCarrito");
    const cajaTabla = document.getElementById("cajaTabla");
    const vacio = document.getElementById("carritoVacio");
    const cajaTotal = document.getElementById("carritoTotalCaja");

    cuerpo.innerHTML = "";
    actualizarContador();

    if (carrito.length === 0) {
        cajaTabla.hidden = true;
        cajaTotal.hidden = true;
        vacio.hidden = false;
        return;
    }

    cajaTabla.hidden = false;
    cajaTotal.hidden = false;
    vacio.hidden = true;

    let total = 0;

    carrito.forEach(function (producto, indice) {
        const info = destinosInfo[producto.nombre] || {};
        const subtotal = producto.precio * producto.cantidad;
        const fila = document.createElement("tr");

        total += subtotal;

        fila.appendChild(celda("imagen", "", info.img ? crearImagen(info, producto.nombre) : ""));
        fila.appendChild(celda("nombre", "Destino", crearNombre(producto.nombre, info)));
        fila.appendChild(celda("", "Precio", dinero(producto.precio)));
        fila.appendChild(celda("", "Cantidad", crearControlCantidad(indice, producto.cantidad)));
        fila.appendChild(celda("subtotal", "Subtotal", dinero(subtotal)));
        fila.appendChild(celda("", "", crearBotonEliminar(indice)));

        cuerpo.appendChild(fila);
    });

    document.getElementById("totalCarrito").textContent = "Total: " + dinero(total);
}

function cambiarCantidad(indice, cambio) {
    const nueva = carrito[indice].cantidad + cambio;

    if (nueva < 1) return;

    carrito[indice].cantidad = nueva;
    guardarCarrito();
    mostrarCarrito();
}

function eliminarProducto(indice) {
    carrito.splice(indice, 1);
    guardarCarrito();
    mostrarCarrito();
}

function vaciarCarrito() {
    if (!confirm("¿Querés vaciar todo el carrito?")) return;

    carrito = [];
    guardarCarrito();
    mostrarCarrito();
}

function continuarReserva() {
    if (carrito.length === 0) return;

    if (!sesionIniciada) {
        alert("Iniciá sesión para continuar con la reserva.");
        location.href = "index.php?login=1";
        return;
    }

    alert(
        "¡Perfecto!\n\n" +
        "Tu reserva fue preparada.\n" +
        "En la siguiente etapa podemos conectar esto con una base de datos."
    );
}

document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("btnVaciar").addEventListener("click", vaciarCarrito);
    document.getElementById("btnReservar").addEventListener("click", continuarReserva);

    mostrarCarrito();
});