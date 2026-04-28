const sidebar = document.getElementById('sidebar');
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sideLinks = Array.from(document.querySelectorAll('#sidebar a[data-page]'));
const pages = Array.from(document.querySelectorAll('main .page[data-page]'));

menuBar?.addEventListener('click', () => {
  sidebar?.classList.toggle('hide');
});

function setActivePage(pageId) {
  pages.forEach((p) => p.classList.toggle('active', p.dataset.page === pageId));
  const items = Array.from(document.querySelectorAll('#sidebar .side-menu.top li'));
  items.forEach((li) => li.classList.remove('active'));
  const activeLink = sideLinks.find((a) => a.dataset.page === pageId);
  activeLink?.closest('li')?.classList.add('active');
}

const HISTORIQUE_API = 'api/historique_plaques.php';
const TRAITER_PARKING_API = 'api/traiter_parking_auto.php';
const DASHBOARD_API = 'api/dashboard_complete.php';
const RECENT_RESERVATIONS_API = 'api/recent_reservations.php';

let occupationChart = null;
let revenueChart = null;
let zoneChart = null;

function escapeHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function statusClassForStatut(statut) {
  const s = (statut || '').toLowerCase();
  if (s.includes('en_attente')) return 'pending';
  if (s.includes('confirmee')) return 'completed';
  if (s.includes('sorti')) return 'completed';
  if (s.includes('parking') || s.includes('présent') || s.includes('present')) return 'pending';
  return 'process';
}

// =================== DASHBOARD ===================
async function loadDashboardStats() {
  try {
    const res = await fetch(DASHBOARD_API, { cache: 'no-store' });
    const data = await res.json();
    
    if (!data.success || !data.stats) return;
    
    const stats = data.stats;
    
    // Update stats cards
    const boxInfo = document.querySelectorAll('.box-info h3');
    if (boxInfo.length >= 3) {
      boxInfo[0].textContent = stats.active_reservations || 0;
      boxInfo[1].textContent = stats.total_users || 0;
      boxInfo[2].textContent = (stats.total_revenue || 0).toFixed(2) + ' DH';
    }
    
    // Create occupation chart
    const occCtx = document.getElementById('occupationChart');
    if (occCtx && stats.daily_occupation) {
      if (occupationChart) occupationChart.destroy();
      
      const dailyData = stats.daily_occupation.slice(0, 7).reverse();
      occupationChart = new Chart(occCtx, {
        type: 'line',
        data: {
          labels: dailyData.map(d => d.jour),
          datasets: [{
            label: 'Entrées',
            data: dailyData.map(d => d.entrees),
            borderColor: '#2EAAC1',
            backgroundColor: 'rgba(46,170,193,0.1)',
            fill: true,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            title: { display: true, text: 'Entrées des 7 derniers jours', color: '#fff' }
          },
          scales: {
            y: { 
              beginAtZero: true,
              grid: { color: 'rgba(255,255,255,0.1)' },
              ticks: { color: '#fff' }
            },
            x: {
              grid: { color: 'rgba(255,255,255,0.1)' },
              ticks: { color: '#fff' }
            }
          }
        }
      });
    }
    
    // Create revenue chart
    const revCtx = document.getElementById('revenueChart');
    if (revCtx && stats.revenue_daily) {
      if (revenueChart) revenueChart.destroy();
      
      const revenueData = stats.revenue_daily.slice(0, 10).reverse();
      revenueChart = new Chart(revCtx, {
        type: 'bar',
        data: {
          labels: revenueData.map(d => d.date),
          datasets: [{
            label: 'Revenus (DH)',
            data: revenueData.map(d => d.total),
            backgroundColor: '#22c55e'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            title: { display: true, text: 'Revenus des 10 derniers jours', color: '#fff' }
          },
          scales: {
            y: { 
              beginAtZero: true,
              grid: { color: 'rgba(255,255,255,0.1)' },
              ticks: { color: '#fff' }
            },
            x: {
              grid: { color: 'rgba(255,255,255,0.1)' },
              ticks: { color: '#fff' }
            }
          }
        }
      });
    }
    
    // Create zone chart
    const zoneCtx = document.getElementById('zoneChart');
    if (zoneCtx && stats.zone_stats) {
      if (zoneChart) zoneChart.destroy();
      
      zoneChart = new Chart(zoneCtx, {
        type: 'doughnut',
        data: {
          labels: stats.zone_stats.map(z => z.zone),
          datasets: [{
            data: stats.zone_stats.map(z => z.total_entrees),
            backgroundColor: ['#2EAAC1', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            title: { display: true, text: 'Entrées par Zone', color: '#fff' },
            legend: { 
              display: true,
              position: 'bottom',
              labels: { color: '#fff' }
            }
          }
        }
      });
    }
  } catch (e) {
    console.error('Failed to load dashboard stats:', e);
  }
}

async function loadRecentReservations() {
  const tbody = document.getElementById('recent-reservations-body');
  if (!tbody) return;
  
  try {
    const res = await fetch(RECENT_RESERVATIONS_API, { cache: 'no-store' });
    const data = await res.json();
    
    if (data.success && data.reservations && data.reservations.length > 0) {
      tbody.innerHTML = data.reservations.map((r) => {
        const userName = escapeHtml(`${r.user.prenom} ${r.user.nom}`);
        const vehicleInfo = r.vehicle.marque ? `${r.vehicle.marque} (${r.vehicle.matricule})` : 'N/A';
        const cls = statusClassForStatut(r.statut);
        return `<tr>
          <td>
            <img src="https://placehold.co/40x40/png?text=${r.user.prenom?.charAt(0) || 'U'}">
            <p>${userName}</p>
          </td>
          <td>${r.date}</td>
          <td><span class="status ${cls}">${r.statut}</span></td>
          <td>${r.montant} DH</td>
        </tr>`;
      }).join('');
    } else {
      tbody.innerHTML = '<tr><td colspan="4">Aucune réservation récente.</td></tr>';
    }
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="4">Erreur de chargement.</td></tr>';
  }
}

// =================== HISTORIQUE ===================
/** Simule les caméras : lit parking/entree et parking/sortie côté serveur, puis enregistre en MySQL. */
async function traiterDossiersParking() {
  try {
    const res = await fetch(TRAITER_PARKING_API, { cache: 'no-store' });
    if (!res.ok) return null;
    return await res.json();
  } catch (e) {
    console.warn('traiterDossiersParking:', e);
    return null;
  }
}

function renderHistorique(rows) {
  const tbody = document.getElementById('historique-plaques-body');
  if (!tbody) return;

  if (!rows || !rows.length) {
    tbody.innerHTML = '<tr><td colspan="6">Aucune visite enregistrée.</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map((r) => {
    const mat = escapeHtml(String(r.matricule ?? '—'));
    const ent = escapeHtml(String(r.date_entree ?? r.date ?? '—'));
    const sor = escapeHtml(r.date_sortie && String(r.date_sortie).trim() !== '' ? String(r.date_sortie) : '—');
    const duree =
      r.duree_minutes != null && r.duree_minutes !== ''
        ? escapeHtml(String(r.duree_minutes))
        : '—';
    const st = String(r.statut ?? r.action ?? '—');
    const cls = statusClassForStatut(st);

    const entUrl = r.image_entree ? escapeHtml(String(r.image_entree)) : '';
    const sorUrl = r.image_sortie ? escapeHtml(String(r.image_sortie)) : '';
    let photoCell = '<span style="opacity:.45">—</span>';
    if (entUrl || sorUrl) {
      const blockEnt = entUrl
        ? `<span>Entrée</span><img class="historique-capture-thumb" src="${entUrl}" alt="Capture entrée" loading="lazy" width="88" height="56"/>`
        : '';
      const blockSor = sorUrl
        ? `<span>Sortie</span><img class="historique-capture-thumb" src="${sorUrl}" alt="Capture sortie" loading="lazy" width="88" height="56"/>`
        : '';
      photoCell = `<div class="historique-thumb-stack">${blockEnt}${blockSor}</div>`;
    }

    const ocrLine =
      r.ocr_method === 'easyocr'
        ? '<span class="historique-ocr-badge">Détecté sur la plaque (caméra / OCR)</span>'
        : '';
    const warnOld =
      r.ocr_method !== 'easyocr' && /^AUTO-/i.test(String(r.matricule ?? ''))
        ? '<span class="historique-ocr-missing">Ce texte n’est pas la plaque : ancienne entrée sans OCR. Supprime la ligne en base ou refais une entrée avec Python installé.</span>'
        : '';

    return `<tr>
      <td>${photoCell}</td>
      <td><div class="historique-plaque-mat">${mat}</div>${ocrLine}${warnOld}</td>
      <td>${ent}</td>
      <td>${sor}</td>
      <td>${duree}</td>
      <td><span class="status ${cls}">${escapeHtml(st)}</span></td>
    </tr>`;
  }).join('');
}

async function loadHistorique(options = {}) {
  const { skipFolderSync = false } = options;
  const tbody = document.getElementById('historique-plaques-body');
  if (!tbody) return;

  if (!skipFolderSync) {
    tbody.innerHTML =
      '<tr><td colspan="6" style="opacity:.75;">Lecture des dossiers entrée/sortie…</td></tr>';
    await traiterDossiersParking();
  }

  try {
    const res = await fetch(HISTORIQUE_API, { cache: 'no-store' });
    const data = await res.json();
    if (data && typeof data === 'object' && data.error && !Array.isArray(data)) {
      tbody.innerHTML = `<tr><td colspan="6">Erreur : ${escapeHtml(String(data.error))}</td></tr>`;
      return;
    }
    renderHistorique(Array.isArray(data) ? data : []);
  } catch (_) {
    tbody.innerHTML = '<tr><td colspan="6">Impossible de charger l\'historique.</td></tr>';
  }
}

// =================== NAVIGATION ===================
sideLinks.forEach((a) => {
  a.addEventListener('click', (e) => {
    e.preventDefault();
    const id = a.dataset.page;
    if (!id) return;
    
    setActivePage(id);
    
    if (id === 'dashboard') {
      loadDashboardStats();
      loadRecentReservations();
    }
    if (id === 'historique') void loadHistorique();
    
    try { history.replaceState({}, '', `#${id}`); } catch (_) {}
  });
});

// Auto-refresh dashboard every 30 seconds
setInterval(() => {
  const activePage = document.querySelector('.page.active');
  if (activePage && activePage.dataset.page === 'dashboard') {
    loadDashboardStats();
  }
}, 30000);

// Initial load
const initial = (location.hash || '').replace('#', '');
if (initial && pages.some((p) => p.dataset.page === initial)) {
  setActivePage(initial);
  if (initial === 'dashboard') {
    loadDashboardStats();
    loadRecentReservations();
  }
  if (initial === 'historique') void loadHistorique();
} else {
  setActivePage('dashboard');
  loadDashboardStats();
  loadRecentReservations();
}

// Pas de traitement OCR au chargement du dashboard (trop lent) : uniquement si tu ouvres Historique ou l’intervalle ci‑dessous.

// Traitement rapide après ouverture de l’admin (sans bloquer le premier rendu)
setTimeout(() => {
  void traiterDossiersParking();
}, 800);

// Toutes les 20 s : lit parking/entree et parking/sortie comme des flux « caméra » (même sur une autre page)
setInterval(() => {
  void traiterDossiersParking();
}, 20000);

// Sur l’onglet Historique : rafraîchir le tableau depuis MySQL (le traitement des dossiers est déjà fait au‑dessus)
setInterval(() => {
  const activePage = document.querySelector('.page.active');
  if (activePage && activePage.dataset.page === 'historique') {
    void loadHistorique({ skipFolderSync: true });
  }
}, 15000);
