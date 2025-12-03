// admin_live.js
(function(){
  // Helpers
  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString()
    });
  }

  // --------- Overrides (sin reload, sin confirm en selects) ---------
  window.asignarUsuario = function(id, usuario) {
    if (!usuario) return;
    post('update_ticket_field.php', { id, field: 'usuario_asignado', value: usuario })
      .then(r => r.json())
      .then(d => { if (!d.ok) alert('No se pudo asignar el usuario'); })
      .catch(() => alert('Error de red al asignar usuario'));
  };

  window.asignarCategoria = function(id, categoria) {
    if (!categoria) return;
    post('update_ticket_field.php', { id, field: 'categoria', value: categoria })
      .then(r => r.json())
      .then(d => { if (!d.ok) alert('No se pudo actualizar la categoría'); })
      .catch(() => alert('Error de red al cambiar categoría'));
  };

  // Cambia SOLO el estado. (Columna: 'estado_ticket'; cámbiala a 'estado' si corresponde)
  window.cambiarEstadoManual = function(id, estado) {
    post('update_ticket_field.php', { id, field: 'estado_ticket', value: estado })
      .then(r => r.json())
      .then(d => { if (!d.ok) alert('No se pudo cambiar el estado'); })
      .catch(() => alert('Error de red al cambiar estado'));
  };

  // --------- Confirmación SOLO para Cerrar / Reabrir ----------
  window.cerrarTicket = function(id) {
    if (!confirm('¿Estás seguro de que deseas cerrar este ticket?')) return;
    // Decide a qué estado envías. Si usas 'Gestionado' como categoría de cierre:
    post('update_ticket_field.php', { id, field: 'estado_ticket', value: 'Gestionado' })
      .then(r => r.json())
      .then(d => {
        if (!d.ok) { alert('No se pudo cerrar el ticket'); return; }
        // reflejar al tiro: oculta la fila; los demás lo verán por polling
        const tr = document.getElementById('row_' + id);
        if (tr) tr.style.display = 'none';
      })
      .catch(() => alert('Error de red al cerrar el ticket'));
  };

  window.reabrirTicket = function(id) {
    if (!confirm('¿Reabrir este ticket?')) return;
    // Elige el estado al reabrir (p.ej., 'En curso' o 'Asignado')
    post('update_ticket_field.php', { id, field: 'estado_ticket', value: 'En curso' })
      .then(r => r.json())
      .then(d => { if (!d.ok) alert('No se pudo reabrir el ticket'); })
      .catch(() => alert('Error de red al reabrir'));
  };

  // --------- Live sync (polling suave cada 2s) ----------
  const cache = {}; // id -> {usuario_asignado, categoria, estado_ticket}

  function isEditing(el){
    return el && (document.activeElement === el);
  }

  function applyTicketState(id, t) {
    const selUser = document.getElementById('usuario_asignado_' + id);
    const selCat  = document.getElementById('categoria_' + id);
    const selEst  = document.getElementById('estado_' + id);
    const row     = document.getElementById('row_' + id);

    // Usuario
    if (selUser && !isEditing(selUser) && selUser.value !== t.usuario_asignado) {
      selUser.value = t.usuario_asignado || '';
    }

    // Categoría
    if (selCat && !isEditing(selCat) && selCat.value !== t.categoria) {
      selCat.value = t.categoria || '';
    }

    // Estado
    if (selEst && !isEditing(selEst) && selEst.value !== t.estado_ticket) {
      selEst.value = t.estado_ticket || '';
    }

    // Si quedó Gestionado, ocultamos la fila (el server lo mostrará en su sección al refrescar)
    if (t.estado_ticket === 'Gestionado' && row) {
      row.style.display = 'none';
    }
  }

  function tick() {
    fetch('get_tickets_state.php')
      .then(r => r.json())
      .then(data => {
        if (!data.ok || !data.tickets) return;
        const tickets = data.tickets;
        for (const id in tickets) {
          const t = tickets[id];
          const prev = cache[id];
          // Si cambió algo, aplica en DOM
          if (!prev ||
              prev.usuario_asignado !== t.usuario_asignado ||
              prev.categoria        !== t.categoria ||
              prev.estado_ticket    !== t.estado_ticket) {
            applyTicketState(id, t);
            cache[id] = t;
          }
        }
      })
      .catch(() => { /* silencioso */ });
  }

  // Primera sincronización + intervalo
  tick();
  setInterval(tick, 2000);
})();
