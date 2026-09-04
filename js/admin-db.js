(() => {
    const api = 'php/admin_api.php';
    const productApi = 'php/admin_products.php';
    let productos = [], clientes = [], interacciones = [], usuarios = [], ventas = [];
    let miGrafica = null;

    const $ = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[c]));

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
            ...options
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'No se pudo completar la operación.');
        return data;
    }

    function error(error) {
        console.error(error);
        alert(error.message || 'Ocurrió un error.');
    }

    function renderProductos() {
        $('tabla-productos-body').innerHTML = productos.map(p => `
            <tr><td>${p.id}</td><td>${esc(p.nombre)}</td>
            <td>$${Number(p.precio).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
            <td class="acciones">
              <button class="btn-accion btn-editar" onclick="editarProducto(${p.id})">Editar</button>
              <button class="btn-accion btn-eliminar" onclick="eliminarProducto(${p.id})">Eliminar</button>
            </td></tr>`).join('');
        $('total-productos').textContent = productos.length;
    }

    function renderClientes() {
        $('tabla-clientes-body').innerHTML = clientes.map(c => `
            <tr><td>${c.id}</td><td><a href="#" onclick="verPerfilCliente(${c.id});return false">${esc(c.nombre)}</a></td>
            <td>${esc(c.correo)}</td><td>${esc(c.telefono)}</td>
            <td><span class="badge ${c.estado === 'Activo' ? 'badge-activo' : 'badge-inactivo'}">${c.estado}</span></td>
            <td><button class="btn-accion btn-editar" onclick="verPerfilCliente(${c.id})">Perfil</button></td></tr>`).join('');
        $('total-clientes-dash').textContent = clientes.length;
    }

    function renderInteracciones() {
        $('tabla-interacciones-body').innerHTML = interacciones.map(i => `
            <tr><td>${esc(i.clienteNombre)}</td><td><strong>${esc(i.tipo)}</strong></td>
            <td>${esc(i.detalle)}</td><td>${esc(i.fechaHora)}</td></tr>`).join('');
    }

    function renderUsuarios() {
        $('tabla-usuarios-body').innerHTML = usuarios.map(u => `
            <tr><td>${u.id}</td><td>${esc(u.nombre)}</td><td>${esc(u.correo)}</td><td>${esc(u.telefono)}</td>
            <td><span class="badge ${u.tipo === 'Administrador' ? 'badge-admin' : 'badge-user'}">${u.tipo}</span></td>
            <td><button class="btn-accion btn-eliminar" onclick="eliminarUsuario(${u.id})">Eliminar</button></td></tr>`).join('');
    }

    function renderVentas() {
        $('tabla-ventas-body').innerHTML = ventas.map(v => `
            <tr><td>${esc(v.folio)}</td><td>${esc(v.cliente)}</td><td>${esc(v.producto)}</td>
            <td>$${Number(v.total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td></tr>`).join('');
        $('total-ventas').textContent = '$' + ventas.reduce((sum, v) => sum + Number(v.total), 0)
            .toLocaleString('es-MX', { minimumFractionDigits: 2 });
    }

    function renderResumen() {
        const activos = clientes.filter(c => c.estado === 'Activo').length;
        $('crm-total-clientes').textContent = clientes.length;
        $('crm-clientes-activos').textContent = activos;
        $('crm-clientes-inactivos').textContent = clientes.length - activos;
        $('crm-total-interacciones').textContent = interacciones.length;
        if (miGrafica) miGrafica.destroy();
        miGrafica = new Chart($('graficaClientes'), {
            type: 'doughnut',
            data: { labels: ['Clientes Activos', 'Clientes Inactivos / Baja'],
                datasets: [{ data: [activos, clientes.length - activos], backgroundColor: ['#28a745', '#dc3545'] }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }

    window.verPerfilCliente = id => {
        const cliente = clientes.find(c => c.id === id);
        if (!cliente) return;
        $('datos-perfil-cliente').innerHTML = `<p><strong>Nombre:</strong> ${esc(cliente.nombre)}</p>
            <p><strong>Correo:</strong> ${esc(cliente.correo)}</p><p><strong>Teléfono:</strong> ${esc(cliente.telefono)}</p>
            <p><strong>Fecha de Ingreso:</strong> ${esc(cliente.ingreso)}</p><p><strong>Estado:</strong> ${esc(cliente.estado)}</p>`;
        const history = interacciones.filter(i => i.clienteId === id);
        $('historial-cliente-lista').innerHTML = history.length ? history.map(h =>
            `<div class="historial-item"><strong>${esc(h.tipo)}</strong> - ${esc(h.fechaHora)}<p>${esc(h.detalle)}</p></div>`).join('')
            : '<p style="font-size:13px;color:#777">Sin interacciones registradas.</p>';
        $('modal-perfil-cliente').classList.add('activo');
    };

    window.editarProducto = id => {
        const p = productos.find(item => item.id === id);
        if (!p) return;
        $('modal-titulo').textContent = 'Editar Producto'; $('producto-id').value = p.id;
        $('producto-nombre').value = p.nombre; $('producto-precio').value = p.precio;
        $('modal-producto').classList.add('activo');
    };
    window.eliminarProducto = id => confirm('¿Seguro que deseas eliminar este producto?') &&
        request(productApi, { method: 'DELETE', body: JSON.stringify({ id }) }).then(load).catch(error);
    window.eliminarUsuario = id => confirm('¿Seguro que deseas eliminar este usuario?') &&
        request(`${api}?action=usuario`, { method: 'DELETE', body: JSON.stringify({ id }) }).then(load).catch(error);

    async function load() {
        const data = await request(api);
        ({ productos, clientes, interacciones, usuarios, ventas } = data);
        renderProductos(); renderClientes(); renderInteracciones(); renderUsuarios(); renderVentas(); renderResumen();
    }

    function show(view) {
        document.querySelectorAll('.vista').forEach(v => v.classList.remove('activa'));
        const target = $(view);
        if (target) target.classList.add('activa');
        if (view === 'crm-resumen') renderResumen();
    }

    document.querySelectorAll('.sidebar-link:not(#crm-menu-toggle)').forEach(link =>
        link.addEventListener('click', () => show(link.dataset.vista)));
    document.querySelectorAll('.submenu-link').forEach(link =>
        link.addEventListener('click', () => show(link.dataset.vista)));
    $('crm-menu-toggle').addEventListener('click', () => $('crm-submenu').classList.toggle('activo'));
    $('btn-agregar-producto').addEventListener('click', () => {
        $('modal-titulo').textContent = 'Agregar Producto'; $('producto-id').value = '';
        $('producto-nombre').value = ''; $('producto-precio').value = '';
        $('modal-producto').classList.add('activo');
    });
    $('form-producto').addEventListener('submit', async e => {
        e.preventDefault();
        const id = $('producto-id').value;
        const body = { name: $('producto-nombre').value.trim(), price: Number($('producto-precio').value),
            category: 'general', description: '' };
        try { await request(productApi, { method: id ? 'PUT' : 'POST', body: JSON.stringify(id ? { ...body, id } : body) });
            cerrarModales(); await load();
        } catch (e) { error(e); }
    });
    $('btn-agregar-cliente').addEventListener('click', () => $('modal-cliente').classList.add('activo'));
    $('form-cliente').addEventListener('submit', async e => {
        e.preventDefault();
        try { await request(`${api}?action=cliente`, { method: 'POST', body: JSON.stringify({
            nombre: $('cliente-nombre').value, correo: $('cliente-correo').value,
            telefono: $('cliente-telefono').value, estado: $('cliente-estado').value }) });
            e.target.reset(); cerrarModales(); await load();
        } catch (err) { error(err); }
    });
    $('btn-nueva-interaccion').addEventListener('click', () => {
        $('interaccion-cliente').innerHTML = clientes.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
        $('modal-interaccion').classList.add('activo');
    });
    $('form-interaccion').addEventListener('submit', async e => {
        e.preventDefault();
        try { await request(`${api}?action=interaccion`, { method: 'POST', body: JSON.stringify({
            clienteId: $('interaccion-cliente').value, tipo: $('interaccion-tipo').value, detalle: $('interaccion-detalle').value }) });
            e.target.reset(); cerrarModales(); await load();
        } catch (err) { error(err); }
    });
    $('btn-agregar-usuario').addEventListener('click', () => $('modal-usuario').classList.add('activo'));
    $('form-usuario').addEventListener('submit', async e => {
        e.preventDefault();
        try { await request(`${api}?action=usuario`, { method: 'POST', body: JSON.stringify({
            nombre: $('usuario-nombre').value, correo: $('usuario-correo').value,
            telefono: $('usuario-telefono').value, password: $('usuario-password').value, tipo: $('usuario-tipo').value }) });
            e.target.reset(); cerrarModales(); await load();
        } catch (err) { error(err); }
    });
    function cerrarModales() { document.querySelectorAll('.modal').forEach(m => m.classList.remove('activo')); }
    document.querySelectorAll('.btn-cerrar-modal').forEach(btn => btn.addEventListener('click', cerrarModales));
    window.addEventListener('click', e => { if (e.target.classList.contains('modal')) cerrarModales(); });
    load().catch(error);
})();
