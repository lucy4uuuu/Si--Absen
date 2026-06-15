<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$db = getDB();
$user = currentUser();
$role = $user['role'];
$pageTitle = 'Absensi';
$today = date('Y-m-d');

// untuk admin: tabs mahasiswa/dosen
include __DIR__ . '/includes/header.php';
?>

<div class="ci-wrap">
  <div class="ci-card">
    <div style="font-size:13px;font-weight:600;color:var(--gray-500)">Waktu Sekarang</div>
    <div class="clock-big" id="live-clock">00:00:00</div>
    <div class="clock-sub" id="live-date">–</div>

    <!-- COUNTDOWN -->
    <div id="countdown-box" class="countdown-box" style="display:none">
      <div class="cd-label" id="cd-label">–</div>
      <div class="cd-time" id="cd-time">--:--:--</div>
      <div class="cd-sub" id="cd-sub"></div>
    </div>

    <!-- LATE WARNING -->
    <div class="late-warn" id="late-warn"><span id="late-msg"></span></div>

    <?php if ($role === 'admin'): ?>
    <div id="ci-type-tabs" class="role-tabs">
      <div class="role-tab active" onclick="setCiType('mahasiswa',this)">👤 Mahasiswa</div>
      <div class="role-tab" onclick="setCiType('dosen',this)">👨‍🏫 Dosen</div>
    </div>
    <div class="field" id="ci-nim-field">
      <label id="ci-nim-label">NIM Mahasiswa</label>
      <input id="ci-nim" type="text" placeholder="Masukkan NIM">
    </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <!-- Admin: search MK dengan autocomplete -->
    <div class="field ac-wrap">
      <label>Cari Mata Kuliah</label>
      <input id="mk-search" type="text" placeholder="Ketik nama mata kuliah..." autocomplete="off">
      <div class="ac-results" id="ac-results"></div>
    </div>
    <div class="field" id="ci-jadwal-info" style="display:none">
      <div class="info-row">🕐 <span id="ci-jadwal-detail">–</span></div>
    </div>
    <input type="hidden" id="ci-jadwal-id" value="">
    <?php else: ?>
    <!-- Mahasiswa/Dosen: jadwal hari ini, tinggal pilih -->
    <div class="field">
      <label>Jadwal Hari Ini — Pilih Kelas</label>
      <div id="today-schedule-list">
        <div class="empty"><div class="eico">⏳</div>Memuat jadwal...</div>
      </div>
    </div>
    <input type="hidden" id="ci-jadwal-id" value="">
    <?php endif; ?>

    <!-- GPS STATUS -->
    <?php if ($role !== 'admin'): ?>
    <div class="gps-status checking" id="gps-status">
      <span class="gps-ico">📍</span>
      <span id="gps-text">Memeriksa lokasi GPS...</span>
    </div>
    <?php endif; ?>

    <button class="btn-ci btn-ci-in" id="btn-ci" onclick="doCheckin()" <?= $role!=='admin'?'disabled':'' ?>>✅ Check-in Sekarang</button>
    <button class="btn-ci btn-ci-out" id="btn-co" onclick="doCheckout()" style="display:none;margin-top:8px">🔴 Check-out</button>
  </div>

  <div class="ci-card">
    <div style="font-size:14px;font-weight:700;margin-bottom:14px">Status Absensi Hari Ini</div>
    <div id="ci-status-list"><div class="empty"><div class="eico">📭</div>Memuat...</div></div>
  </div>
</div>

<script>
const ROLE = '<?= $role ?>';
let ciType = '<?= $role === 'dosen' ? 'dosen' : 'mahasiswa' ?>';
let scheduleData = [];
let selectedJadwal = null;
let userLat = null, userLng = null, gpsValid = (ROLE === 'admin');
let acTimer = null;

// ══════════════════════════════════════════
// LOAD JADWAL HARI INI (mahasiswa/dosen)
// ══════════════════════════════════════════
async function loadTodaySchedule() {
  try {
    const res = await fetch('api/jadwal_hari_ini.php');
    const data = await res.json();
    scheduleData = data.jadwal;
    renderTodaySchedule();
  } catch (e) {
    console.error(e);
  }
}

function renderTodaySchedule() {
  const el = document.getElementById('today-schedule-list');
  if (!el) return;
  if (!scheduleData.length) {
    el.innerHTML = '<div class="no-schedule-today">📭 Tidak ada jadwal kelas hari ini</div>';
    document.getElementById('btn-ci').disabled = true;
    return;
  }
  el.innerHTML = scheduleData.map((j, i) => {
    const sudah = j.sudahAbsen;
    return `<div class="today-schedule ${sudah ? 'selected' : ''}" data-idx="${i}" onclick="${sudah ? '' : `selectSchedule(${i})`}" style="${sudah?'opacity:.6;cursor:default':''}">
      <div class="ts-mk">${escapeHtml(j.mk)} ${sudah?'✅':''}</div>
      <div class="ts-meta">🕐 ${j.mulai}–${j.selesai} · 🏫 ${escapeHtml(j.ruang||'-')} · ⏱ Toleransi ${j.toleransi} menit</div>
      ${sudah ? `<div class="ts-meta" style="color:var(--success);font-weight:600">Sudah check-in pukul ${j.absen.checkin ? j.absen.checkin.slice(0,5) : '-'}</div>` : ''}
    </div>`;
  }).join('');

  // Auto-select first not-yet-attended schedule that's relevant to current time
  const idx = scheduleData.findIndex(j => !j.sudahAbsen);
  if (idx >= 0) selectSchedule(idx);
  else { document.getElementById('btn-ci').disabled = true; updateCountdown(); }
}

function selectSchedule(idx) {
  selectedJadwal = scheduleData[idx];
  document.getElementById('ci-jadwal-id').value = selectedJadwal.id;
  document.querySelectorAll('.today-schedule').forEach((el,i) => el.classList.toggle('selected', i===idx));
  updateCheckinButtonState();
  updateCountdown();
  updateLateWarn();
}

function escapeHtml(s) { return (s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

// ══════════════════════════════════════════
// AUTOCOMPLETE MATA KULIAH (admin)
// ══════════════════════════════════════════
const mkSearchInput = document.getElementById('mk-search');
if (mkSearchInput) {
  mkSearchInput.addEventListener('input', function() {
    clearTimeout(acTimer);
    const q = this.value.trim();
    acTimer = setTimeout(() => searchMk(q), 200);
  });
  mkSearchInput.addEventListener('focus', function() { searchMk(this.value.trim()); });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.ac-wrap')) document.getElementById('ac-results').classList.remove('show');
  });
}

async function searchMk(q) {
  const res = await fetch('api/search_mk.php?q=' + encodeURIComponent(q));
  const data = await res.json();
  const box = document.getElementById('ac-results');
  if (!data.length) {
    box.innerHTML = '<div class="ac-empty">Tidak ditemukan</div>';
  } else {
    box.innerHTML = data.map(j => `
      <div class="ac-item" onclick='pickMk(${JSON.stringify(j).replace(/'/g,"&#39;")})'>
        <div class="ac-mk">${escapeHtml(j.mk)}</div>
        <div class="ac-meta">${j.hari}, ${j.mulai}–${j.selesai} · ${escapeHtml(j.ruang||'-')} · Toleransi ${j.toleransi} menit</div>
      </div>`).join('');
  }
  box.classList.add('show');
}

function pickMk(j) {
  document.getElementById('mk-search').value = j.mk;
  document.getElementById('ci-jadwal-id').value = j.id;
  document.getElementById('ac-results').classList.remove('show');
  selectedJadwal = {
    id: j.id, mk: j.mk, mulai: j.mulai, selesai: j.selesai, toleransi: j.toleransi, ruang: j.ruang,
    mulaiMin: timeToMin(j.mulai), selesaiMin: timeToMin(j.selesai), batasMin: timeToMin(j.mulai)+j.toleransi
  };
  const info = document.getElementById('ci-jadwal-info');
  document.getElementById('ci-jadwal-detail').textContent = `${j.hari}, ${j.mulai}–${j.selesai} | Ruang: ${j.ruang} | Toleransi: ${j.toleransi} menit`;
  info.style.display = 'block';
}

function timeToMin(t) { const [h,m] = t.split(':').map(Number); return h*60+m; }
function minToTime(m) {
  m = Math.max(0, m);
  const h = Math.floor(m/60), mm = m%60;
  return String(h).padStart(2,'0')+':'+String(mm).padStart(2,'0');
}

// ══════════════════════════════════════════
// COUNTDOWN
// ══════════════════════════════════════════
function updateCountdown() {
  const box = document.getElementById('countdown-box');
  if (!selectedJadwal) { box.style.display='none'; return; }

  const now = new Date();
  const nowSec = now.getHours()*3600 + now.getMinutes()*60 + now.getSeconds();
  const mulaiSec = selectedJadwal.mulaiMin*60;
  const batasSec = selectedJadwal.batasMin*60;
  const selesaiSec = selectedJadwal.selesaiMin*60;

  box.style.display = 'block';
  box.className = 'countdown-box';

  if (nowSec < mulaiSec) {
    const diff = mulaiSec - nowSec;
    box.classList.add('cd-upcoming');
    document.getElementById('cd-label').textContent = '⏳ Kelas Akan Dimulai';
    document.getElementById('cd-time').textContent = formatDuration(diff);
    document.getElementById('cd-sub').textContent = `${selectedJadwal.mk} dimulai pukul ${selectedJadwal.mulai}`;
  } else if (nowSec <= batasSec) {
    const diff = batasSec - nowSec;
    box.classList.add('cd-ontime');
    document.getElementById('cd-label').textContent = '✅ Waktu Check-in (Tepat Waktu)';
    document.getElementById('cd-time').textContent = formatDuration(diff);
    document.getElementById('cd-sub').textContent = 'Sisa waktu sebelum dianggap terlambat';
  } else if (nowSec <= selesaiSec) {
    const diff = selesaiSec - nowSec;
    box.classList.add('cd-late');
    document.getElementById('cd-label').textContent = '⚠️ Terlambat — Kelas Berlangsung';
    document.getElementById('cd-time').textContent = formatDuration(diff);
    document.getElementById('cd-sub').textContent = 'Check-in akan dicatat sebagai TERLAMBAT';
  } else {
    box.classList.add('cd-closed');
    document.getElementById('cd-label').textContent = '⏹ Kelas Telah Selesai';
    document.getElementById('cd-time').textContent = '00:00:00';
    document.getElementById('cd-sub').textContent = `Kelas berakhir pukul ${selectedJadwal.selesai}`;
  }
}

function formatDuration(sec) {
  sec = Math.max(0, sec);
  const h = Math.floor(sec/3600), m = Math.floor((sec%3600)/60), s = sec%60;
  return [h,m,s].map(v=>String(v).padStart(2,'0')).join(':');
}

// ══════════════════════════════════════════
// LATE WARNING + REMINDER
// ══════════════════════════════════════════
let reminderShown = {};
function updateLateWarn() {
  const warn = document.getElementById('late-warn');
  if (ROLE === 'admin' || !selectedJadwal) { warn.classList.remove('show'); return; }

  const now = new Date();
  const nowMin = now.getHours()*60 + now.getMinutes();
  const mulai = selectedJadwal.mulaiMin;
  const batas = selectedJadwal.batasMin;

  // Reminder 10 menit sebelum kelas dimulai
  if (nowMin >= mulai - 10 && nowMin < mulai) {
    const sisa = mulai - nowMin;
    document.getElementById('late-msg').textContent = `🔔 Pengingat: kelas "${selectedJadwal.mk}" akan dimulai dalam ${sisa} menit (pukul ${selectedJadwal.mulai}). Segera siapkan check-in!`;
    warn.classList.add('show');
    if (!reminderShown[selectedJadwal.id]) {
      reminderShown[selectedJadwal.id] = true;
      showToast(`🔔 Kelas "${selectedJadwal.mk}" dimulai dalam ${sisa} menit!`, 'warn');
    }
  } else if (nowMin > mulai && nowMin <= batas) {
    document.getElementById('late-msg').textContent = `Kelas dimulai ${selectedJadwal.mulai}, toleransi keterlambatan ${selectedJadwal.toleransi} menit. Segera check-in!`;
    warn.classList.add('show');
  } else if (nowMin > batas) {
    document.getElementById('late-msg').textContent = `Batas toleransi keterlambatan terlampaui. Check-in akan dicatat sebagai TERLAMBAT.`;
    warn.classList.add('show');
  } else {
    warn.classList.remove('show');
  }
}

// ══════════════════════════════════════════
// GPS / LOKASI
// ══════════════════════════════════════════
function initGps() {
  if (ROLE === 'admin') return;
  const statusEl = document.getElementById('gps-status');
  const textEl = document.getElementById('gps-text');
  if (!navigator.geolocation) {
    statusEl.className = 'gps-status fail';
    textEl.textContent = '❌ Browser tidak mendukung GPS. Absensi tidak dapat dilakukan.';
    gpsValid = false;
    updateCheckinButtonState();
    return;
  }
  navigator.geolocation.getCurrentPosition(async (pos) => {
    userLat = pos.coords.latitude;
    userLng = pos.coords.longitude;
    try {
      const res = await fetch('api/check_location.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({lat: userLat, lng: userLng})
      });
      const data = await res.json();
      gpsValid = data.valid;
      statusEl.className = 'gps-status ' + (data.valid ? 'ok' : 'fail');
      textEl.textContent = (data.valid ? '✅ ' : '❌ ') + data.message;
    } catch(e) {
      gpsValid = false;
      statusEl.className = 'gps-status fail';
      textEl.textContent = '❌ Gagal memeriksa lokasi.';
    }
    updateCheckinButtonState();
  }, (err) => {
    gpsValid = false;
    statusEl.className = 'gps-status fail';
    textEl.textContent = '❌ Izin lokasi ditolak. Aktifkan GPS untuk dapat absen.';
    updateCheckinButtonState();
  }, { enableHighAccuracy: true, timeout: 10000 });
}

function updateCheckinButtonState() {
  const btn = document.getElementById('btn-ci');
  if (ROLE === 'admin') { btn.disabled = false; return; }
  const hasSchedule = !!selectedJadwal;
  btn.disabled = !hasSchedule || !gpsValid;
}

// ══════════════════════════════════════════
// ADMIN: tipe check-in tab
// ══════════════════════════════════════════
function setCiType(type, el) {
  ciType = type;
  document.querySelectorAll('.role-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('ci-nim-label').textContent = type==='mahasiswa'?'NIM Mahasiswa':'NIP Dosen';
}

// ══════════════════════════════════════════
// CHECK-IN / CHECK-OUT
// ══════════════════════════════════════════
async function doCheckin() {
  const jadwalId = document.getElementById('ci-jadwal-id').value;
  if (!jadwalId) { showToast('Pilih mata kuliah!','error'); return; }

  const payload = { jadwal_id: jadwalId, lat: userLat, lng: userLng };
  if (ROLE === 'admin') {
    payload.raw_id = document.getElementById('ci-nim').value.trim();
    payload.ci_type = ciType;
    if (!payload.raw_id) { showToast('Masukkan NIM/NIP!','error'); return; }
  }

  const res = await fetch('api/checkin.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
  const data = await res.json();
  if (!data.ok) { showToast(data.message, 'error'); return; }
  showToast(data.message, data.status==='terlambat'?'warn':'success');

  if (ROLE === 'admin') document.getElementById('ci-nim').value='';
  else {
    document.getElementById('btn-ci').style.display='none';
    document.getElementById('btn-co').style.display='block';
  }
  loadCiStatus();
  if (ROLE !== 'admin') loadTodaySchedule();
}

async function doCheckout() {
  const jadwalId = document.getElementById('ci-jadwal-id').value;
  const res = await fetch('api/checkout.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({jadwal_id: jadwalId}) });
  const data = await res.json();
  if (!data.ok) { showToast(data.message, 'error'); return; }
  showToast(data.message, 'success');
  document.getElementById('btn-co').style.display='none';
  document.getElementById('btn-ci').style.display='block';
  document.getElementById('btn-ci').disabled = true;
  loadCiStatus();
}

// ══════════════════════════════════════════
// STATUS LIST
// ══════════════════════════════════════════
async function loadCiStatus() {
  const res = await fetch('api/jadwal_hari_ini.php');
  const data = await res.json();
  const el = document.getElementById('ci-status-list');
  const attended = data.jadwal.filter(j => j.sudahAbsen);

  if (ROLE === 'admin') {
    // For admin, fetch a broader list via rekap-like endpoint isn't built; show today schedule attendance summary
  }

  if (!attended.length) {
    el.innerHTML = '<div class="empty"><div class="eico">📭</div>Belum ada absensi hari ini</div>';
    return;
  }
  el.innerHTML = attended.map(j => `
    <div class="ci-status-item">
      <span><b>${escapeHtml(j.mk)}</b><br><span style="font-size:11px;color:var(--gray-500)">${j.hari} · ${j.mulai}-${j.selesai}</span></span>
      <span>${statusBadgeJs(j.absen.status)} <span style="font-size:11px;color:var(--gray-400)">${j.absen.checkin ? j.absen.checkin.slice(0,5) : ''}</span></span>
    </div>`).join('');

  // If checked in but not out for currently selected
  const cur = attended.find(j => selectedJadwal && j.id === selectedJadwal.id);
  if (cur && cur.absen.checkin && !cur.absen.checkout && ROLE !== 'admin') {
    document.getElementById('btn-ci').style.display = 'none';
    document.getElementById('btn-co').style.display = 'block';
  }
}

function statusBadgeJs(s) {
  const map = { hadir:['bg-green','Hadir'], terlambat:['bg-purple','Terlambat'], alpha:['bg-red','Alpha'] };
  const izinMap = { 'izin-sakit':'Izin Sakit','izin-keluarga':'Izin Keluarga','izin-akademik':'Izin Akademik','izin-lain':'Izin Lainnya','alpha':'Alpha' };
  if (s.startsWith('izin')) return `<span class="badge bg-yellow">${izinMap[s]}</span>`;
  const v = map[s] || ['bg-gray', s];
  return `<span class="badge ${v[0]}">${v[1]}</span>`;
}

// ══════════════════════════════════════════
// INIT
// ══════════════════════════════════════════
if (ROLE !== 'admin') {
  loadTodaySchedule();
  initGps();
}
loadCiStatus();
setInterval(() => { updateCountdown(); updateLateWarn(); }, 1000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
