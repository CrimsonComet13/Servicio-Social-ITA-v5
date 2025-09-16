// Dashboard JS - maneja la consulta al endpoint de estadísticas y render de widgets
document.addEventListener('DOMContentLoaded', function() {
  const statsContainer = document.getElementById('dashboard-stats');
  const reportsTable = document.getElementById('reports-table');

  async function fetchStats() {
    try {
      const res = await fetch('/servicio_social_ita/api/dashboard.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) throw new Error('Error fetching stats: ' + res.status);
      const data = await res.json();
      renderStats(data);
      renderRecentReports(data.recent_reports || []);
    } catch (err) {
      console.error(err);
      if (statsContainer) statsContainer.innerHTML = '<p class="error">No se pudieron cargar las estadísticas.</p>';
      if (reportsTable) reportsTable.innerHTML = '<tr><td colspan="5">Error al cargar reportes.</td></tr>';
    }
  }

  function renderStats(data) {
    if (!statsContainer) return;
    statsContainer.innerHTML = `
      <div class="stat-grid">
        <div class="stat-card"><h3>${data.total_estudiantes || 0}</h3><p>Estudiantes</p></div>
        <div class="stat-card"><h3>${data.total_proyectos || 0}</h3><p>Proyectos</p></div>
        <div class="stat-card"><h3>${data.total_reportes || 0}</h3><p>Reportes</p></div>
        <div class="stat-card"><h3>${data.horas_totales || 0}</h3><p>Horas Reportadas</p></div>
      </div>
    `;
  }

  function renderRecentReports(reports) {
    if (!reportsTable) return;
    if (!Array.isArray(reports) || reports.length === 0) {
      reportsTable.innerHTML = '<tr><td colspan="5">No hay reportes recientes.</td></tr>';
      return;
    }
    reportsTable.innerHTML = reports.map(r => `
      <tr>
        <td>${escapeHtml(r.id)}</td>
        <td>${escapeHtml(r.estudiante_nombre || r.estudiante || '')}</td>
        <td>${escapeHtml(r.proyecto || '')}</td>
        <td>${escapeHtml(r.horas_reportadas || 0)}</td>
        <td>${escapeHtml(r.estado || '')}</td>
      </tr>
    `).join('');
  }

  // pequeño helper para evitar inyección al insertar texto
  function escapeHtml(str) {
    if (typeof str === 'undefined' || str === null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Iniciar y refrescar periódicamente
  fetchStats();
  setInterval(fetchStats, 60000); // cada 60s
});
