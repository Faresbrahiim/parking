/**
 * ParkIQ — Smart Parking  |  app.js
 */

const BASE_URL = 'http://localhost:8000';
/* ══════════════════════════════════════════════
   STATE
══════════════════════════════════════════════ */
const state = {
  token: localStorage.getItem('parkiq_token') || null,
  user:  JSON.parse(localStorage.getItem('parkiq_user') || 'null'),
  places: [],
  filter: 'all',
  currentPlace: null,
  duration: 1,
};

/* ══════════════════════════════════════════════
   API HELPERS
══════════════════════════════════════════════ */
async function api(path, method = 'GET', body = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
  };
  if (state.token) opts.headers['Authorization'] = `Bearer ${state.token}`;
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(BASE_URL + path, opts);
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}

/* ══════════════════════════════════════════════
   VIEWS
══════════════════════════════════════════════ */
function showView(id) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('view-' + id)?.classList.add('active');
  document.querySelector(`[data-view="${id}"]`)?.classList.add('active');

  if (id === 'places') loadPlaces();
}

// Nav buttons
document.querySelectorAll('[data-view]').forEach(btn => {
  btn.addEventListener('click', () => showView(btn.dataset.view));
});

/* ══════════════════════════════════════════════
   AUTH UI
══════════════════════════════════════════════ */
const authModal    = document.getElementById('authModal');
const authToggle   = document.getElementById('authToggle');
const closeModal   = document.getElementById('closeModal');

function openAuthModal(tab = 'login') {
  authModal.classList.add('open');
  switchTab(tab);
}
function closeAuthModal() { authModal.classList.remove('open'); }

authToggle.addEventListener('click', () => {
  if (state.token) logout();
  else openAuthModal('login');
});
closeModal.addEventListener('click', closeAuthModal);
authModal.addEventListener('click', e => { if (e.target === authModal) closeAuthModal(); });

// Tabs
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

  if (ok && data.token) {
    state.token = data.token;
    state.user  = data.user || { name: email.split('@')[0] };
    localStorage.setItem('parkiq_token', state.token);
    localStorage.setItem('parkiq_user', JSON.stringify(state.user));
    updateAuthUI();
    closeAuthModal();
    showToast('Welcome back! 👋', 'success');
  } else {
    errEl.textContent = data.message || data.error || 'Login failed. Check your credentials.';
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

  if (ok) {
    sucEl.textContent = 'Account created! Please sign in.';
    setTimeout(() => switchTab('login'), 1500);
  } else {
    errEl.textContent = data.message || data.error || 'Registration failed. Try again.';
  }
});

/* ─── LOGOUT ─────────────────────────────────── */
function logout() {
  state.token = null; state.user = null;
  localStorage.removeItem('parkiq_token');
  localStorage.removeItem('parkiq_user');
  updateAuthUI();
  showToast('Signed out.', 'success');
}

function updateAuthUI() {
  const navUser   = document.getElementById('navUser');
  const authToggle = document.getElementById('authToggle');
  if (state.user) {
    navUser.textContent = state.user.name || 'User';
    authToggle.textContent = 'Sign Out';
  } else {
    navUser.textContent = 'Guest';
    authToggle.textContent = 'Sign In';
  }
}
updateAuthUI();

/* ══════════════════════════════════════════════
   PLACES
══════════════════════════════════════════════ */
async function loadPlaces() {
  const grid = document.getElementById('placesGrid');
  grid.innerHTML = '<div class="loader-wrap"><div class="loader"></div></div>';

  const { ok, data } = await api('/places');

  if (!ok || !Array.isArray(data)) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">⚠️</div>
        <h3>Could not load parking lots</h3>
        <p>Check your API connection and try again.</p>
      </div>`;
    updateStats([]);
    return;
  }

  state.places = data;
  updateStats(data);
  renderPlaces();
}

function renderPlaces() {
  const grid   = document.getElementById('placesGrid');
  const filter = state.filter;

  let places = state.places;
  if (filter === 'available') places = places.filter(p => availableSlots(p) > 0);
  if (filter === 'full')      places = places.filter(p => availableSlots(p) === 0);

  if (!places.length) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🅿️</div>
        <h3>No parking lots found</h3>
        <p>Try a different filter.</p>
      </div>`;
    return;
  }

  grid.innerHTML = places.map((p, i) => placeCardHTML(p, i)).join('');

  // Bind reserve buttons
  grid.querySelectorAll('.btn-reserve').forEach(btn => {
    btn.addEventListener('click', () => {
      const place = state.places.find(p => String(p.id) === btn.dataset.id);
      if (place) openPayModal(place);
    });
  });
}

function availableSlots(place) {
  if (place.available_slots !== undefined) return Number(place.available_slots);
  if (place.total_slots !== undefined && place.occupied_slots !== undefined)
    return Number(place.total_slots) - Number(place.occupied_slots);
  return 0;
}
function totalSlots(place) {
  return Number(place.total_slots || place.capacity || 0);
}
function pricePerHour(place) {
  return Number(place.price_per_hour || place.price || place.hourly_rate || 0);
}

function placeCardHTML(place, idx) {
  const avail  = availableSlots(place);
  const total  = totalSlots(place);
  const isFull = avail === 0;
  const pct    = total ? ((total - avail) / total * 100) : 0;
  const price  = pricePerHour(place);
  const name   = place.name || place.location || `Lot #${place.id}`;

  // Build mini slot map (max 30 dots shown)
  const dotsMax  = Math.min(total, 30);
  const occDots  = total ? Math.round((total - avail) / total * dotsMax) : dotsMax;
  const slotDots = Array.from({ length: dotsMax }, (_, i) =>
    `<div class="slot-dot ${i < occDots ? 'occupied' : ''}"></div>`
  ).join('');

  return `
    <div class="place-card ${isFull ? 'is-full' : ''}" style="animation-delay:${idx * 60}ms">
      <div class="card-top">
        <div>
          <div class="card-name">${escHtml(name)}</div>
          <div class="card-id">ID: ${place.id}${place.address ? ' · ' + escHtml(place.address) : ''}</div>
        </div>
        <span class="card-badge ${isFull ? 'badge-full' : 'badge-available'}">
          ${isFull ? 'Full' : 'Open'}
        </span>
      </div>
      <div class="card-body">
        ${total > 0 ? `<div class="slot-map">${slotDots}</div>` : ''}
        <div class="card-meta">
          <div class="card-avail ${isFull ? 'is-full' : ''}">
            <span class="avail-num">${avail}</span> / ${total} slots free
          </div>
          ${price ? `<div>${formatPrice(price)}/hr</div>` : ''}
        </div>
        <div class="progress-bar">
          <div class="progress-fill ${pct > 80 ? 'is-high' : ''}" style="width:${pct}%"></div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn-reserve" data-id="${place.id}" ${isFull ? 'disabled' : ''}>
          ${isFull ? '🚫 No slots available' : '⚡ Reserve a Slot'}
        </button>
      </div>
    </div>`;
}

function updateStats(places) {
  const total    = places.reduce((s, p) => s + totalSlots(p), 0);
  const occupied = places.reduce((s, p) => s + (totalSlots(p) - availableSlots(p)), 0);
  animateNum('statTotal',    total);
  animateNum('statFree',     total - occupied);
  animateNum('statOccupied', occupied);
  animateNum('statLots',     places.length);
}

function animateNum(id, target) {
  const el = document.getElementById(id);
  if (!el) return;
  const start = parseInt(el.textContent) || 0;
  const dur   = 600; const t0 = performance.now();
  function frame(t) {
    const p = Math.min((t - t0) / dur, 1);
    el.textContent = Math.round(start + (target - start) * easeOut(p));
    if (p < 1) requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}
function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

// Filter buttons
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.filter = btn.dataset.filter;
    renderPlaces();
  });
});

// Refresh
document.getElementById('refreshPlaces').addEventListener('click', loadPlaces);

// Auto-refresh every 30s
setInterval(() => {
  if (document.getElementById('view-places').classList.contains('active')) loadPlaces();
}, 30000);

/* ══════════════════════════════════════════════
   PAY MODAL
══════════════════════════════════════════════ */
const payModal     = document.getElementById('payModal');
const closePayModal = document.getElementById('closePayModal');

function openPayModal(place) {
  if (!state.token) {
    openAuthModal('login');
    showToast('Please sign in to reserve a slot.', 'error');
    return;
  }

  state.currentPlace = place;
  state.duration     = 1;

  document.getElementById('payPlaceName').textContent = place.name || `Lot #${place.id}`;
  document.getElementById('payPlaceInfo').textContent = place.address || 'Confirm your reservation';
  document.getElementById('payPlate').value = '';
  document.getElementById('payError').textContent   = '';
  document.getElementById('paySuccess').textContent = '';
  document.getElementById('durValue').textContent   = '1';

  updatePayDetails(place);
  payModal.classList.add('open');
}

function updatePayDetails(place) {
  const avail = availableSlots(place);
  const price = pricePerHour(place);
  document.getElementById('payDetails').innerHTML = `
    <div class="pay-detail-item">
      <span class="pay-detail-label">Available</span>
      <span class="pay-detail-val">${avail} slots</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Rate</span>
      <span class="pay-detail-val">${price ? formatPrice(price) + '/hr' : 'Free'}</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Lot ID</span>
      <span class="pay-detail-val">#${place.id}</span>
    </div>
    <div class="pay-detail-item">
      <span class="pay-detail-label">Status</span>
      <span class="pay-detail-val" style="color:var(--accent)">Open</span>
    </div>`;
  updateTotal();
}

function updateTotal() {
  const price = pricePerHour(state.currentPlace);
  const total = price * state.duration;
  document.getElementById('payTotal').textContent = total ? formatPrice(total) : 'Free';
}

// Duration picker
document.getElementById('durMinus').addEventListener('click', () => {
  if (state.duration > 1) { state.duration--; document.getElementById('durValue').textContent = state.duration; updateTotal(); }
});
document.getElementById('durPlus').addEventListener('click', () => {
  if (state.duration < 24) { state.duration++; document.getElementById('durValue').textContent = state.duration; updateTotal(); }
});

closePayModal.addEventListener('click', () => payModal.classList.remove('open'));
payModal.addEventListener('click', e => { if (e.target === payModal) payModal.classList.remove('open'); });

// Confirm pay
document.getElementById('confirmPayBtn').addEventListener('click', async () => {
  const plate  = document.getElementById('payPlate').value.trim().toUpperCase();
  const errEl  = document.getElementById('payError');
  const sucEl  = document.getElementById('paySuccess');
  errEl.textContent = ''; sucEl.textContent = '';

  if (!plate) { errEl.textContent = 'Please enter your vehicle plate.'; return; }

  const btn = document.getElementById('confirmPayBtn');
  btn.textContent = 'Processing…'; btn.disabled = true;

  const { ok, data } = await api('/pay', 'POST', {
    place_id:  state.currentPlace.id,
    plate,
    duration:  state.duration,
  });

  btn.textContent = 'Confirm & Reserve'; btn.disabled = false;

  if (ok) {
    sucEl.textContent = data.message || '✅ Slot reserved successfully!';
    showToast('Parking reserved! 🎉', 'success');
    setTimeout(() => {
      payModal.classList.remove('open');
      loadPlaces();
    }, 1800);
  } else {
    errEl.textContent = data.message || data.error || 'Payment failed. Please try again.';
  }
});

/* ══════════════════════════════════════════════
   MISC
══════════════════════════════════════════════ */
function showToast(msg, type = '') {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className   = 'toast show ' + type;
  clearTimeout(toast._t);
  toast._t = setTimeout(() => { toast.className = 'toast'; }, 3500);
}

function formatPrice(n) {
  if (!n) return 'Free';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'MAD', minimumFractionDigits: 0, maximumFractionDigits: 2 })
    .format(n).replace('MAD', 'MAD ');
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Learn more scroll
document.getElementById('learnMore')?.addEventListener('click', () => {
  document.getElementById('howItWorks')?.scrollIntoView({ behavior: 'smooth' });
});

// Load stats on boot by silently fetching places for home view
(async () => {
  const { ok, data } = await api('/places');
  if (ok && Array.isArray(data)) {
    state.places = data;
    updateStats(data);
  }
})();