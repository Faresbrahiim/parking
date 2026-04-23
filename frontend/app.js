/**
 * ParkIQ — Smart Parking  |  app.js
 * Backend: PHP session-based auth (no JWT)
 * Fields: id_place, statut ('libre'/'occupe'), nom, prenom, id_utilisateur
 */

const BASE_URL = 'http://localhost:8000';

/* ══════════════════════════════════════════════
   STATE — persisted in localStorage
══════════════════════════════════════════════ */
const state = {
  user:         JSON.parse(localStorage.getItem('parkiq_user') || 'null'),
  places:       [],
  filter:       'all',
  currentPlace: null,
  duration:     1,
};

/* ══════════════════════════════════════════════
   API HELPER
   credentials:'include' sends PHP session cookie
══════════════════════════════════════════════ */
async function api(path, method = 'GET', body = null) {
  const opts = {
    method,
    credentials: 'include',                      // sends session cookie
    headers: { 'Content-Type': 'application/json' },
  };
  if (body) opts.body = JSON.stringify(body);
  try {
    const res  = await fetch(BASE_URL + path, opts);
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data };
  } catch (e) {
    return { ok: false, status: 0, data: { error: 'Network error — is the server running?' } };
  }
}

/* ══════════════════════════════════════════════
   VIEWS
══════════════════════════════════════════════ */
function showView(id) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('view-' + id)?.classList.add('active');
  document.querySelector(`.nav-btn[data-view="${id}"]`)?.classList.add('active');
  if (id === 'places') loadPlaces();
}

document.querySelectorAll('[data-view]').forEach(btn => {
  btn.addEventListener('click', () => showView(btn.dataset.view));
});

/* ══════════════════════════════════════════════
   AUTH UI
══════════════════════════════════════════════ */
const authModal  = document.getElementById('authModal');
const closeModal = document.getElementById('closeModal');

function openAuthModal(tab = 'login') {
  authModal.classList.add('open');
  switchTab(tab);
}
function closeAuthModal() { authModal.classList.remove('open'); }

document.getElementById('authToggle').addEventListener('click', () => {
  if (state.user) logout();
  else openAuthModal('login');
});
closeModal.addEventListener('click', closeAuthModal);
authModal.addEventListener('click', e => { if (e.target === authModal) closeAuthModal(); });

document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', () => switchTab(tab.dataset.tab));
});
function switchTab(name) {
  document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
  document.querySelectorAll('.tab-content').forEach(c => c.classList.toggle('active', c.id === `tab-${name}`));
}

/* ─── LOGIN ──────────────────────────────────── */
document.getElementById('loginBtn').addEventListener('click', async () => {
  const email    = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;
  const errEl    = document.getElementById('loginError');
  errEl.textContent = '';

  if (!email || !password) { errEl.textContent = 'Please fill all fields.'; return; }

  const btn = document.getElementById('loginBtn');
  btn.textContent = 'Signing in…'; btn.disabled = true;

  const { ok, data } = await api('/login', 'POST', { email, password });
  btn.textContent = 'Sign In'; btn.disabled = false;

  if (ok && data.status === 'success') {
    // API returns: { user: { id, nom, prenom, email } }
    state.user = data.user;
    localStorage.setItem('parkiq_user', JSON.stringify(data.user));
    updateAuthUI();
    closeAuthModal();
    showToast(`Welcome back, ${data.user.prenom || data.user.nom}! 👋`, 'success');
    // ── Redirect to parking lots after login ──
    showView('places');
  } else {
    errEl.textContent = data.error || data.message || 'Invalid credentials.';
  }
});

/* ─── REGISTER ───────────────────────────────── */
document.getElementById('registerBtn').addEventListener('click', async () => {
  const name     = document.getElementById('regName').value.trim();
  const email    = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value;
  const errEl    = document.getElementById('registerError');
  const sucEl    = document.getElementById('registerSuccess');
  errEl.textContent = ''; sucEl.textContent = '';

  if (!name || !email || !password) { errEl.textContent = 'Please fill all fields.'; return; }
  if (password.length < 8) { errEl.textContent = 'Password must be at least 8 characters.'; return; }

  const btn = document.getElementById('registerBtn');
  btn.textContent = 'Creating…'; btn.disabled = true;

  const { ok, data } = await api('/register', 'POST', { name, email, password });
  btn.textContent = 'Create Account'; btn.disabled = false;

  if (ok && data.status === 'success') {
    sucEl.textContent = '✅ Account created! Please sign in.';
    setTimeout(() => switchTab('login'), 1600);
  } else {
    errEl.textContent = data.message || data.error || 'Registration failed. Try again.';
  }
});

/* ─── LOGOUT ─────────────────────────────────── */
function logout() {
  state.user = null;
  localStorage.removeItem('parkiq_user');
  updateAuthUI();
  showView('home');
  showToast('Signed out successfully.', 'success');
}

/* ─── UPDATE NAV ─────────────────────────────── */
function updateAuthUI() {
  const navUser    = document.getElementById('navUser');
  const authToggle = document.getElementById('authToggle');
  const logoutBtn  = document.getElementById('logoutBtn');

  if (state.user) {
    const fullName = `${state.user.prenom || ''} ${state.user.nom || ''}`.trim() || state.user.email;
    navUser.textContent      = fullName;
    authToggle.style.display = 'none';        // hide Sign In
    logoutBtn.style.display  = 'flex';        // show Logout
  } else {
    navUser.textContent      = '';
    authToggle.style.display = 'flex';
    logoutBtn.style.display  = 'none';
  }
}

document.getElementById('logoutBtn').addEventListener('click', logout);
updateAuthUI();

/* ══════════════════════════════════════════════
   PLACES
   API: GET /places → { status, data: [ {id_place, statut, ...} ] }
══════════════════════════════════════════════ */
async function loadPlaces() {
  const grid = document.getElementById('placesGrid');
  grid.innerHTML = '<div class="loader-wrap"><div class="loader"></div></div>';

  const { ok, data } = await api('/places');

  // API wraps array in data.data
  const places = Array.isArray(data?.data) ? data.data
               : Array.isArray(data)        ? data
               : null;

  if (!ok || !places) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">⚠️</div>
        <h3>Could not load parking lots</h3>
        <p>${data?.error || 'Check your API connection and try again.'}</p>
      </div>`;
    updateStats([]);
    return;
  }

  state.places = places;
  updateStats(places);
  renderPlaces();
}

function renderPlaces() {
  const grid = document.getElementById('placesGrid');
  let places  = state.places;

  if (state.filter === 'available') places = places.filter(p => isLibre(p));
  if (state.filter === 'full')      places = places.filter(p => !isLibre(p));

  if (!places.length) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🅿️</div>
        <h3>No parking slots found</h3>
        <p>Try a different filter.</p>
      </div>`;
    return;
  }

  grid.innerHTML = places.map((p, i) => placeCardHTML(p, i)).join('');

  grid.querySelectorAll('.btn-reserve').forEach(btn => {
    btn.addEventListener('click', () => {
      const place = state.places.find(p => String(p.id_place) === btn.dataset.id);
      if (place) openPayModal(place);
    });
  });
}

/* ══════════════════════════════════════════════
   SLOT HELPERS — matches your DB schema exactly
   statut values: 'libre' | 'occupe' | 'handicapLibre' | 'handicapOccupe'
══════════════════════════════════════════════ */
function isLibre(place) {
  return place.statut === 'libre' || place.statut === 'handicapLibre';
}
function isHandicap(place) {
  return place.statut === 'handicapLibre' || place.statut === 'handicapOccupe';
}
function placeLabel(place) {
  // numero = 'C5', zone = 'C'  →  "Zone C · C5"
  if (place.numero) return place.numero;
  return place.nom || place.name || `Slot #${place.id_place}`;
}
function pricePerHour(place) {
  return Number(place.price_per_hour || place.price || place.tarif || 5);
}

function updateStats(places) {
  const total    = places.length;
  const free     = places.filter(isLibre).length;
  animateNum('statTotal',    total);
  animateNum('statFree',     free);
  animateNum('statOccupied', total - free);
  animateNum('statLots',     [...new Set(places.map(p => p.zone).filter(Boolean))].length || total);
}

/* SVG car icon — inline, scales perfectly */
function carSVG(color) {
  return `<svg viewBox="0 0 64 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="car-svg">
    <rect x="6" y="12" width="52" height="14" rx="4" fill="${color}" opacity="0.9"/>
    <path d="M14 12 L20 4 H44 L50 12Z" fill="${color}"/>
    <circle cx="16" cy="27" r="5" fill="#1a1d24" stroke="${color}" stroke-width="2"/>
    <circle cx="48" cy="27" r="5" fill="#1a1d24" stroke="${color}" stroke-width="2"/>
    <rect x="22" y="6" width="20" height="6" rx="1.5" fill="rgba(255,255,255,0.2)"/>
    <rect x="8" y="15" width="10" height="4" rx="1" fill="rgba(255,255,255,0.25)"/>
    <rect x="46" y="15" width="10" height="4" rx="1" fill="rgba(255,255,255,0.25)"/>
  </svg>`;
}

/* Wheelchair / handicap SVG */
function handicapSVG(color) {
  return `<svg viewBox="0 0 40 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="handicap-svg">
    <circle cx="20" cy="5" r="4.5" fill="${color}"/>
    <path d="M17 11 L14 26 L22 26 L26 36" stroke="${color}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M14 17 L28 17" stroke="${color}" stroke-width="2.5" stroke-linecap="round"/>
    <circle cx="20" cy="39" r="7" stroke="${color}" stroke-width="3" fill="none"/>
    <path d="M26 43 L30 47" stroke="${color}" stroke-width="2.5" stroke-linecap="round"/>
  </svg>`;
}

function placeCardHTML(place, idx) {
  const libre    = isLibre(place);
  const handicap = isHandicap(place);
  const price    = pricePerHour(place);
  const numero   = place.numero  || `#${place.id_place}`;
  const zone     = place.zone    || '—';

  const carColor       = libre ? '#00e5a0' : '#ff4d6d';
  const handicapColor  = libre ? '#60a5fa' : '#f87171';
  const cardClass      = `place-card ${!libre ? 'is-full' : ''} ${handicap ? 'is-handicap' : ''}`;

  const slotIcon = handicap
    ? `<div class="slot-icon-wrap handicap-wrap">${handicapSVG(handicapColor)}</div>`
    : `<div class="slot-icon-wrap car-wrap">${carSVG(carColor)}</div>`;

  const statusLabel = handicap
    ? (libre ? '♿ Handicap — Libre' : '♿ Handicap — Occupé')
    : (libre ? 'Libre' : 'Occupé');

  return `
    <div class="${cardClass}" style="animation-delay:${idx * 50}ms">
      <div class="card-top">
        <div class="card-title-group">
          <div class="card-name">
            ${handicap ? '<span class="handi-tag">♿</span>' : ''}
            Zone <strong>${escHtml(zone)}</strong> · ${escHtml(numero)}
          </div>
          <div class="card-id">Slot ID: ${place.id_place}</div>
        </div>
        <span class="card-badge ${libre ? (handicap ? 'badge-handicap' : 'badge-available') : 'badge-full'}">
          ${libre ? (handicap ? '♿ Free' : 'Libre') : 'Occupé'}
        </span>
      </div>

      <div class="card-body">
        <div class="slot-visual-box ${libre ? 'box-libre' : 'box-occupe'} ${handicap ? 'box-handicap' : ''}">
          <div class="slot-road-lines">
            <div class="road-line"></div>
            <div class="road-line"></div>
          </div>
          ${slotIcon}
          <div class="slot-label-row">
            <span class="slot-num-label">${escHtml(numero)}</span>
            <span class="slot-status-pill ${libre ? 'pill-libre' : 'pill-occupe'}">${statusLabel}</span>
          </div>
        </div>

        <div class="card-meta">
          <div>Zone: <strong>${escHtml(zone)}</strong></div>
          <div>${formatPrice(price)}/hr</div>
        </div>
      </div>

      <div class="card-footer">
        <button class="btn-reserve ${handicap ? 'btn-reserve-handicap' : ''}"
                data-id="${place.id_place}" ${!libre ? 'disabled' : ''}>
          ${!libre
            ? (handicap ? '♿ Slot Occupied' : '🚫 Slot Occupied')
            : (handicap ? '♿ Reserve Handicap Slot' : '⚡ Reserve This Slot')}
        </button>
      </div>
    </div>`;
}

function animateNum(id, target) {
  const el = document.getElementById(id);
  if (!el) return;
  const start = parseInt(el.textContent) || 0;
  const dur = 600; const t0 = performance.now();
  function frame(t) {
    const p = Math.min((t - t0) / dur, 1);
    el.textContent = Math.round(start + (target - start) * easeOut(p));
    if (p < 1) requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}
function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.filter = btn.dataset.filter;
    renderPlaces();
  });
});

document.getElementById('refreshPlaces').addEventListener('click', loadPlaces);

setInterval(() => {
  if (document.getElementById('view-places').classList.contains('active')) loadPlaces();
}, 30000);

/* ══════════════════════════════════════════════
   PAY MODAL
   API: POST /pay → { place_id, user_id, amount }
══════════════════════════════════════════════ */
const payModal      = document.getElementById('payModal');
const closePayModal = document.getElementById('closePayModal');

function openPayModal(place) {
  if (!state.user) {
    openAuthModal('login');
    showToast('Please sign in to reserve a slot.', 'error');
    return;
  }

  state.currentPlace = place;
  state.duration     = 1;

  const numero = place.numero || `#${place.id_place}`;
  const zone   = place.zone   || '—';
  const handicap = isHandicap(place);

  document.getElementById('payPlaceName').textContent = `Zone ${zone} · ${numero}`;
  document.getElementById('payPlaceInfo').textContent = handicap
    ? '♿ Handicap accessible slot'
    : 'Standard parking slot';
  document.getElementById('payError').textContent   = '';
  document.getElementById('paySuccess').textContent = '';
  document.getElementById('durValue').textContent   = '1';

  renderPayDetails(place);
  payModal.classList.add('open');
}

function renderPayDetails(place) {
  const price    = pricePerHour(place);
  const handicap = isHandicap(place);
  const zone     = place.zone   || '—';
  const numero   = place.numero || `#${place.id_place}`;

  document.getElementById('payDetails').innerHTML = `
    <div class="pay-detail-item">
      <span class="pay-detail-label">Slot</span>
      <span class="pay-detail-val">${escHtml(numero)}</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Zone</span>
      <span class="pay-detail-val">${escHtml(zone)}</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Type</span>
      <span class="pay-detail-val">${handicap ? '♿ Handicap' : '🚗 Standard'}</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Rate</span>
      <span class="pay-detail-val">${formatPrice(price)}/hr</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Booked by</span>
      <span class="pay-detail-val">${escHtml((state.user.prenom || '') + ' ' + (state.user.nom || ''))}</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Status</span>
      <span class="pay-detail-val" style="color:var(--accent)">Libre ✓</span>
    </div>`;
  updateTotal();
}

function updateTotal() {
  const price = pricePerHour(state.currentPlace);
  const total = price * state.duration;
  document.getElementById('payTotal').textContent = formatPrice(total);
}

document.getElementById('durMinus').addEventListener('click', () => {
  if (state.duration > 1) {
    state.duration--;
    document.getElementById('durValue').textContent = state.duration;
    updateTotal();
  }
});
document.getElementById('durPlus').addEventListener('click', () => {
  if (state.duration < 24) {
    state.duration++;
    document.getElementById('durValue').textContent = state.duration;
    updateTotal();
  }
});

closePayModal.addEventListener('click', () => payModal.classList.remove('open'));
payModal.addEventListener('click', e => { if (e.target === payModal) payModal.classList.remove('open'); });

document.getElementById('confirmPayBtn').addEventListener('click', async () => {
  const errEl = document.getElementById('payError');
  const sucEl = document.getElementById('paySuccess');
  errEl.textContent = ''; sucEl.textContent = '';

  const btn = document.getElementById('confirmPayBtn');
  btn.textContent = 'Processing…'; btn.disabled = true;

  const price  = pricePerHour(state.currentPlace);
  const amount = price * state.duration;

  const { ok, data } = await api('/pay', 'POST', {
    place_id: state.currentPlace.id_place,
    user_id:  state.user.id,
    amount,
    duration: state.duration,
  });

  btn.textContent = 'Confirm & Reserve'; btn.disabled = false;

  if (ok && data.status === 'success') {
    sucEl.textContent = '✅ ' + (data.message || 'Slot reserved successfully!');
    showToast('Parking reserved! 🎉', 'success');
    const idx = state.places.findIndex(p => p.id_place === state.currentPlace.id_place);
    if (idx !== -1) {
      // preserve handicap type when marking occupied
      state.places[idx].statut = isHandicap(state.currentPlace) ? 'handicapOccupe' : 'occupe';
    }
    setTimeout(() => {
      payModal.classList.remove('open');
      loadPlaces();
    }, 2000);
  } else {
    errEl.textContent = data.message || data.error || 'Payment failed. Please try again.';
  }
});

/* ══════════════════════════════════════════════
   UTILITIES
══════════════════════════════════════════════ */
function showToast(msg, type = '') {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className   = `toast show ${type}`;
  clearTimeout(toast._t);
  toast._t = setTimeout(() => { toast.className = 'toast'; }, 3500);
}

function formatPrice(n) {
  if (!n && n !== 0) return 'Free';
  return n.toFixed(2) + ' MAD';
}

function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('learnMore')?.addEventListener('click', () => {
  document.getElementById('howItWorks')?.scrollIntoView({ behavior: 'smooth' });
});

// Boot: silently load stats for home view
(async () => {
  const { ok, data } = await api('/places');
  const places = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
  if (ok && places.length) { state.places = places; updateStats(places); }
})();