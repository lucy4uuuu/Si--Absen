// ══════════════════════════════════════════
// COMMON HELPERS - SiAbsen
// ══════════════════════════════════════════

function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'toast show ' + type;
  setTimeout(() => t.className = 'toast', 3200);
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}

// Close modal on overlay click
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function (e) {
      if (e.target === this) this.classList.remove('open');
    });
  });

  // Live clock + date
  const clockEl = document.getElementById('live-clock');
  const dateEl = document.getElementById('live-date');
  const topbarDate = document.getElementById('topbar-date');

  function tick() {
    const now = new Date();
    if (clockEl) clockEl.textContent = now.toLocaleTimeString('id-ID');
    if (dateEl) dateEl.textContent = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    if (topbarDate) topbarDate.textContent = now.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }
  tick();
  setInterval(tick, 1000);
});

// ══════════════════════════════════════════
// CSV Export helper
// ══════════════════════════════════════════
function exportCSV(rows, filename) {
  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
  const el = document.createElement('a');
  el.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  el.download = filename;
  el.click();
  showToast('CSV diunduh!', 'success');
}
