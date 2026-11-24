
document.addEventListener('click', async (evento) => {
    const enlace = evento.target.closest('a[href*="?editar="]');

    if (!enlace || !enlace.closest('table')) return;

    evento.preventDefault();

    try {
        const respuesta = await fetch(enlace.href);
        if (!respuesta.ok) throw new Error();

        const html = await respuesta.text();
        const documento = new DOMParser().parseFromString(html, 'text/html');

        const campo = documento.querySelector(
            'main input[name="accion"][value="editar"]'
        );

        if (!campo) throw new Error();

        const formulario = campo.closest('form');
        const fila = enlace.closest('tr');

        document.querySelector('.fila-edicion')?.remove();

        const nuevaFila = document.createElement('tr');
        nuevaFila.className = 'fila-edicion';

        const celda = document.createElement('td');
        celda.colSpan = fila.cells.length;

        const contenedor = document.createElement('div');
        contenedor.className = 'p-3 border rounded bg-light';

        const titulo = document.createElement('h5');
        titulo.textContent = 'Editar registro';

        contenedor.append(titulo, formulario);
        celda.appendChild(contenedor);
        nuevaFila.appendChild(celda);
        fila.after(nuevaFila);

        const cancelar = formulario.querySelector('a.btn-secondary');

        if (cancelar) {
            cancelar.addEventListener('click', (e) => {
                e.preventDefault();
                nuevaFila.remove();
            });
        }

    } catch (error) {
        alert('No se pudo abrir el formulario de edición.');
    }
});

document.addEventListener('submit', async (evento) => {
    const formulario = evento.target;

    if (!(formulario instanceof HTMLFormElement)) return;
    if (!formulario.closest('main')) return;
    if (formulario.method.toUpperCase() !== 'POST') return;
    if (evento.defaultPrevented) return;

    evento.preventDefault();

    const boton = evento.submitter;
    if (boton) boton.disabled = true;

    try {
        const respuesta = await fetch(formulario.action, {
            method: 'POST',
            body: new FormData(formulario),
            credentials: 'same-origin'
        });

        if (!respuesta.ok) throw new Error();

        const contenido = await respuesta.text();
        const documento = new DOMParser().parseFromString(
            contenido, 'text/html'
        );

        const nuevoMain = documento.querySelector('main');
        const destino = new URL(respuesta.url);

        if (!nuevoMain || destino.pathname !== location.pathname) {
            throw new Error();
        }

        if (
            formulario.closest('.fila-edicion') &&
            !destino.searchParams.has('ok')
        ) {
            const error = documento.querySelector('.alert-danger');
            const celda = formulario.closest('td');

            celda.querySelectorAll('.alert-danger').forEach(
                aviso => aviso.remove()
            );

            const aviso = document.createElement('div');
            aviso.className = 'alert alert-danger';
            aviso.textContent = error
                ? error.textContent.trim()
                : 'No se pudieron guardar los cambios.';

            celda.prepend(aviso);

            setTimeout(() => aviso.remove(), 4000);
            return;
        }

        const posicion = window.scrollY;

        document.querySelector('main').replaceWith(nuevoMain);

        destino.searchParams.delete('ok');
        history.replaceState(
            null,
            '',
            destino.pathname + destino.search + destino.hash
        );

        requestAnimationFrame(() => {
            window.scrollTo(0, posicion);
        });

        setTimeout(() => {
            document.querySelectorAll('main .alert').forEach(
                aviso => aviso.remove()
            );
        }, 4000);

    } catch (error) {
        alert('No se pudo completar la operación.');
    } finally {
        if (boton && boton.isConnected) {
            boton.disabled = false;
        }
    }
});
