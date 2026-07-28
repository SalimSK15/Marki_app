/* =========================================================
   MARKI V1 — Mes Patients, Toutes les listes, Paramètres
   Ce fichier complète app.js sans remplacer le dashboard.
   Il doit être chargé APRÈS app.js.
   ========================================================= */

(function () {
  'use strict';

  const API = {
    patients: 'api/patients_index.php',
    patientDetails: 'api/patient_details.php',
    patientUpdate: 'api/patient_update_profile.php',
    patientAddToToday: 'api/patient_add_to_today.php',
    queues: 'api/queues_history.php',
    queueDetails: 'api/queue_history_details.php',
    settings: 'api/settings_get.php',
    settingsUpdate: 'api/settings_update.php'
  };

  const patientsState = {
    query: '',
    page: 1,
    perPage: 12,
    selectedId: null,
    requestId: 0
  };

  const queuesState = {
    dateFrom: '',
    dateTo: '',
    dayStatus: 'all',
    page: 1,
    perPage: 12,
    selectedId: null,
    requestId: 0
  };

  let patientSearchTimer = null;

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  async function readJson(response) {
    const raw = await response.text();

    try {
      return JSON.parse(raw);
    } catch (error) {
      console.error('Réponse non JSON :', raw);
      throw new Error('Le serveur a renvoyé une réponse invalide.');
    }
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const data = await readJson(response);

    if (!response.ok || !data?.ok) {
      throw new Error(data?.message || 'Une erreur est survenue.');
    }

    return data;
  }

  function setMessage(element, message = '', type = '') {
    if (!element) return;

    element.textContent = message;
    element.className = 'v1-message';

    if (message && type) {
      element.classList.add(`is-${type}`);
    }
  }

  function formatDate(value) {
    if (!value) return '-';

    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return escapeHtml(value);

    return `${match[3]}/${match[2]}/${match[1]}`;
  }

  function formatDateTime(value) {
    if (!value) return '-';

    const match = String(value).match(
      /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/
    );

    if (!match) return escapeHtml(value);

    return `${match[3]}/${match[2]}/${match[1]} à ${match[4]}:${match[5]}`;
  }

  /*
  |--------------------------------------------------------------------------
  | Afficher un téléphone algérien au format local
  |--------------------------------------------------------------------------
  | La base conserve +213. L'interface affiche 05, 06 ou 07.
  |--------------------------------------------------------------------------
  */
  function formatPhoneForDisplay(value) {
    const original = String(value ?? '').trim();

    if (original === '') {
      return '';
    }

    const digits = original.replace(/\D+/g, '');

    if (/^0[567]\d{8}$/.test(digits)) {
      return digits;
    }

    if (/^213[567]\d{8}$/.test(digits)) {
      return `0${digits.slice(3)}`;
    }

    if (/^00213[567]\d{8}$/.test(digits)) {
      return `0${digits.slice(5)}`;
    }

    return original;
  }

  function statusLabel(status) {
    const labels = {
      waiting: 'En attente',
      called: 'Appelé',
      done: 'Terminé',
      no_show: 'Absent',
      canceled: 'Annulé',
      active: 'Active',
      paused: 'En pause',
      completed: 'Clôturée',
      open: 'Ouverte',
      closed: 'Fermée',
      archived: 'Archivée',
      in_progress: 'En cours'
    };

    return labels[status] || status || '-';
  }

  function statusClass(status) {
    const normalized = String(status || '')
      .toLowerCase()
      .replace(/_/g, '-');

    return `v1-status v1-status--${normalized}`;
  }

  function renderStatus(status) {
    return `<span class="${statusClass(status)}">${escapeHtml(statusLabel(status))}</span>`;
  }

  function paginationPages(current, total) {
    if (total <= 7) {
      return Array.from({ length: total }, (_, index) => index + 1);
    }

    const pages = new Set([1, total, current - 1, current, current + 1]);
    const valid = [...pages]
      .filter(page => page >= 1 && page <= total)
      .sort((a, b) => a - b);

    const result = [];
    let previous = 0;

    valid.forEach(page => {
      if (previous && page - previous > 1) {
        result.push('…');
      }

      result.push(page);
      previous = page;
    });

    return result;
  }

  function renderPagination(container, pagination, onPage) {
    if (!container) return;

    const current = Number(pagination?.page || 1);
    const total = Math.max(1, Number(pagination?.total_pages || 1));

    const pageButtons = paginationPages(current, total).map(page => {
      if (page === '…') {
        return '<span class="v1-pagination-summary" aria-hidden="true">…</span>';
      }

      return `
        <button
          type="button"
          data-page="${page}"
          class="${Number(page) === current ? 'is-active' : ''}"
          aria-label="Page ${page}"
          ${Number(page) === current ? 'aria-current="page"' : ''}
        >${page}</button>
      `;
    }).join('');

    container.innerHTML = `
      <button type="button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''} aria-label="Page précédente">‹</button>
      ${pageButtons}
      <button type="button" data-page="${current + 1}" ${current >= total ? 'disabled' : ''} aria-label="Page suivante">›</button>
    `;

    container.querySelectorAll('button[data-page]').forEach(button => {
      button.addEventListener('click', () => {
        const page = Number(button.dataset.page);
        if (page >= 1 && page <= total && page !== current) {
          onPage(page);
        }
      });
    });
  }

  function pageSummary(pagination) {
    const total = Number(pagination?.total || 0);
    const page = Number(pagination?.page || 1);
    const perPage = Number(pagination?.per_page || 12);

    if (total === 0) return 'Aucun résultat';

    const first = ((page - 1) * perPage) + 1;
    const last = Math.min(total, page * perPage);
    return `${first} à ${last} sur ${total}`;
  }

  /* =======================================================
     MES PATIENTS
     ======================================================= */

  function initPatientsPage() {
    const searchInput = document.getElementById('patients-search');
    const pageSize = document.getElementById('patients-page-size');
    const form = document.getElementById('patient-profile-form');
    const addButton = document.getElementById('patient-add-to-today-button');

    if (!searchInput || !pageSize) return;

    const requestedPatientId = Number(
      sessionStorage.getItem('marki.openPatientId') || 0
    );

    if (requestedPatientId > 0) {
      patientsState.selectedId = requestedPatientId;
      sessionStorage.removeItem('marki.openPatientId');
    }

    searchInput.value = patientsState.query;
    pageSize.value = String(patientsState.perPage);

    searchInput.addEventListener('input', () => {
      clearTimeout(patientSearchTimer);
      patientSearchTimer = setTimeout(() => {
        patientsState.query = searchInput.value.trim();
        patientsState.page = 1;
        loadPatients();
      }, 280);
    });

    pageSize.addEventListener('change', () => {
      patientsState.perPage = Number(pageSize.value) || 12;
      patientsState.page = 1;
      loadPatients();
    });

    form?.addEventListener('submit', savePatientProfile);
    addButton?.addEventListener('click', addPatientToTodayQueue);

    loadPatients();
  }

  async function loadPatients() {
    const body = document.getElementById('patients-table-body');
    const message = document.getElementById('patients-page-message');
    const summary = document.getElementById('patients-results-summary');
    const paginationSummary = document.getElementById('patients-pagination-summary');
    const paginationContainer = document.getElementById('patients-pagination');

    if (!body) return;

    const requestId = ++patientsState.requestId;
    setMessage(message);
    body.innerHTML = '<tr><td colspan="6" class="v1-empty-cell">Chargement des patients…</td></tr>';

    const params = new URLSearchParams({
      q: patientsState.query,
      page: String(patientsState.page),
      per_page: String(patientsState.perPage)
    });

    try {
      const response = await requestJson(`${API.patients}?${params.toString()}`);
      if (requestId !== patientsState.requestId) return;

      const items = response.data?.items || [];
      const pagination = response.data?.pagination || {};

      if (summary) {
        summary.textContent = `${Number(pagination.total || 0)} patient(s) trouvé(s)`;
      }

      if (paginationSummary) {
        paginationSummary.textContent = pageSummary(pagination);
      }

      renderPagination(paginationContainer, pagination, page => {
        patientsState.page = page;
        loadPatients();
      });

      if (items.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="v1-empty-cell">Aucun patient ne correspond à la recherche.</td></tr>';
        clearPatientProfile();
        return;
      }

      body.innerHTML = items.map(patient => `
        <tr
          data-selectable="true"
          data-patient-id="${patient.id}"
          class="${Number(patient.id) === Number(patientsState.selectedId) ? 'is-selected' : ''}"
          tabindex="0"
        >
          <td>
            <span class="v1-table__patient-name">${escapeHtml(patient.full_name)}</span>
            <span class="v1-table__subtext">Ajouté le ${formatDate(patient.created_at)}</span>
          </td>
          <td>${escapeHtml(formatPhoneForDisplay(patient.phone) || '-')}</td>
          <td>${formatDate(patient.birth_date)}</td>
          <td>${patient.last_visit_at ? formatDateTime(patient.last_visit_at) : '-'}</td>
          <td>${Number(patient.visit_count || 0)}</td>
          <td class="v1-table__action-heading">
            <button type="button" class="v1-icon-button" data-open-patient="${patient.id}" title="Ouvrir la fiche" aria-label="Ouvrir la fiche de ${escapeHtml(patient.full_name)}">›</button>
          </td>
        </tr>
      `).join('');

      body.querySelectorAll('tr[data-patient-id]').forEach(row => {
        const open = () => selectPatient(Number(row.dataset.patientId));
        row.addEventListener('click', open);
        row.addEventListener('keydown', event => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
          }
        });
      });

      if (
        patientsState.selectedId
        && items.some(item => Number(item.id) === Number(patientsState.selectedId))
      ) {
        await loadPatientProfile(patientsState.selectedId);
      } else if (!patientsState.selectedId) {
        await selectPatient(items[0].id);
      } else {
        await loadPatientProfile(patientsState.selectedId);
      }
    } catch (error) {
      console.error('Mes Patients :', error);
      body.innerHTML = '<tr><td colspan="6" class="v1-empty-cell">Impossible de charger les patients.</td></tr>';
      setMessage(message, error.message, 'error');
    }
  }

  async function selectPatient(patientId) {
    patientsState.selectedId = Number(patientId);

    document.querySelectorAll('#patients-table-body tr[data-patient-id]').forEach(row => {
      row.classList.toggle(
        'is-selected',
        Number(row.dataset.patientId) === patientsState.selectedId
      );
    });

    await loadPatientProfile(patientId);
  }

  async function loadPatientProfile(patientId) {
    const empty = document.getElementById('patient-profile-empty');
    const content = document.getElementById('patient-profile-content');
    const title = document.getElementById('patient-profile-title');
    const formMessage = document.getElementById('patient-profile-form-message');

    if (!content || !empty) return;

    empty.hidden = false;
    empty.textContent = 'Chargement de la fiche…';
    content.hidden = true;
    setMessage(formMessage);

    try {
      const response = await requestJson(
        `${API.patientDetails}?patient_id=${encodeURIComponent(patientId)}`
      );
      const patient = response.data?.patient;

      if (!patient) {
        throw new Error('Fiche patient introuvable.');
      }

      patientsState.selectedId = Number(patient.id);

      if (title) title.textContent = patient.full_name;
      setInputValue('patient-profile-id', patient.id);
      setInputValue('patient-profile-full-name', patient.full_name);
      setInputValue('patient-profile-phone', formatPhoneForDisplay(patient.phone));
      setInputValue('patient-profile-birth-date', patient.birth_date);
      setInputValue('patient-profile-email', patient.email);
      setInputValue('patient-profile-address', patient.address);
      setInputValue('patient-profile-notes', patient.notes_non_medical);

      renderPatientVisits(patient.recent_visits || []);
      renderPatientEntries(patient.recent_entries || []);

      empty.hidden = true;
      content.hidden = false;
    } catch (error) {
      console.error('Fiche patient :', error);
      empty.hidden = false;
      empty.textContent = error.message;
      content.hidden = true;
    }
  }

  function setInputValue(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value ?? '';
  }

  function clearPatientProfile() {
    patientsState.selectedId = null;
    const empty = document.getElementById('patient-profile-empty');
    const content = document.getElementById('patient-profile-content');
    const title = document.getElementById('patient-profile-title');

    if (title) title.textContent = 'Sélectionnez un patient';
    if (empty) {
      empty.hidden = false;
      empty.textContent = 'Cliquez sur un patient pour afficher sa fiche et son historique.';
    }
    if (content) content.hidden = true;
  }

  function renderPatientVisits(visits) {
    const container = document.getElementById('patient-visits-list');
    const count = document.getElementById('patient-visits-count');
    if (!container) return;

    if (count) count.textContent = String(visits.length);

    if (!visits.length) {
      container.innerHTML = '<div class="v1-empty-panel">Aucune visite enregistrée pour ce médecin.</div>';
      return;
    }

    container.innerHTML = visits.map(visit => {
      const date = visit.ended_at || visit.started_at || visit.created_at;
      return `
        <article class="v1-timeline__item">
          <div>
            <strong>Visite du ${formatDateTime(date)}</strong>
            <p>${visit.queue_entry_id ? `Reliée à l’entrée n° ${visit.queue_entry_id}` : 'Visite sans entrée de file'}</p>
          </div>
          ${renderStatus(visit.status)}
        </article>
      `;
    }).join('');
  }

  function renderPatientEntries(entries) {
    const container = document.getElementById('patient-queues-list');
    const count = document.getElementById('patient-queues-count');
    if (!container) return;

    if (count) count.textContent = String(entries.length);

    if (!entries.length) {
      container.innerHTML = '<div class="v1-empty-panel">Aucun passage dans une liste.</div>';
      return;
    }

    container.innerHTML = entries.map(entry => `
      <article class="v1-timeline__item">
        <div>
          <strong>${formatDate(entry.queue_date)}${entry.position_number ? ` — N° d’arrivée ${entry.position_number}` : ''}</strong>
          <p>Inscription : ${formatDateTime(entry.created_at)}</p>
        </div>
        ${renderStatus(entry.status)}
      </article>
    `).join('');
  }

  async function savePatientProfile(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const message = document.getElementById('patient-profile-form-message');
    const button = document.getElementById('patient-profile-save-button');
    const patientId = Number(document.getElementById('patient-profile-id')?.value || 0);

    if (patientId <= 0) return;

    const payload = Object.fromEntries(new FormData(form).entries());
    payload.patient_id = patientId;

    setMessage(message);
    if (button) {
      button.disabled = true;
      button.textContent = 'Enregistrement…';
    }

    try {
      const response = await requestJson(API.patientUpdate, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      setMessage(message, response.message || 'Fiche patient mise à jour.', 'success');
      await loadPatients();
      await loadPatientProfile(patientId);
    } catch (error) {
      console.error('Mise à jour patient :', error);
      setMessage(message, error.message, 'error');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Enregistrer les modifications';
      }
    }
  }

  async function addPatientToTodayQueue() {
    const button = document.getElementById('patient-add-to-today-button');
    const message = document.getElementById('patient-profile-form-message');
    const patientId = Number(document.getElementById('patient-profile-id')?.value || 0);

    if (patientId <= 0) return;

    if (button) {
      button.disabled = true;
      button.textContent = 'Ajout en cours…';
    }
    setMessage(message);

    try {
      const response = await requestJson(API.patientAddToToday, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ patient_id: patientId })
      });

      setMessage(message, response.message || 'Patient ajouté à la Liste du jour.', 'success');
    } catch (error) {
      console.error('Ajout à la liste du jour :', error);
      setMessage(message, error.message, 'error');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Ajouter à la liste du jour';
      }
    }
  }

  window.openPatientProfile = function (patientId) {
    const id = Number(patientId);
    if (id <= 0) return;

    sessionStorage.setItem('marki.openPatientId', String(id));
    patientsState.selectedId = id;

    if (typeof window.setActiveMenuItem === 'function') {
      window.setActiveMenuItem('patients');
    }

    if (typeof window.loadPage === 'function') {
      window.loadPage('patients');
    }
  };

  /* =======================================================
     TOUTES LES LISTES
     ======================================================= */

  function initListsPage() {
    const form = document.getElementById('queues-history-filters');
    const reset = document.getElementById('queues-reset-filters');
    const pageSize = document.getElementById('queues-page-size');

    if (!form || !pageSize) return;

    pageSize.value = String(queuesState.perPage);
    setInputValue('queues-date-from', queuesState.dateFrom);
    setInputValue('queues-date-to', queuesState.dateTo);
    setInputValue('queues-day-status', queuesState.dayStatus);

    form.addEventListener('submit', event => {
      event.preventDefault();
      queuesState.dateFrom = document.getElementById('queues-date-from')?.value || '';
      queuesState.dateTo = document.getElementById('queues-date-to')?.value || '';
      queuesState.dayStatus = document.getElementById('queues-day-status')?.value || 'all';
      queuesState.perPage = Number(pageSize.value) || 12;
      queuesState.page = 1;
      loadQueuesHistory();
    });

    pageSize.addEventListener('change', () => {
      queuesState.perPage = Number(pageSize.value) || 12;
    });

    reset?.addEventListener('click', () => {
      queuesState.dateFrom = '';
      queuesState.dateTo = '';
      queuesState.dayStatus = 'all';
      queuesState.page = 1;
      setInputValue('queues-date-from', '');
      setInputValue('queues-date-to', '');
      setInputValue('queues-day-status', 'all');
      loadQueuesHistory();
    });

    loadQueuesHistory();
  }

  async function loadQueuesHistory() {
    const body = document.getElementById('queues-history-table-body');
    const message = document.getElementById('queues-history-message');
    const summary = document.getElementById('queues-results-summary');
    const paginationSummary = document.getElementById('queues-pagination-summary');
    const paginationContainer = document.getElementById('queues-pagination');

    if (!body) return;

    const requestId = ++queuesState.requestId;
    setMessage(message);
    body.innerHTML = '<tr><td colspan="8" class="v1-empty-cell">Chargement des listes…</td></tr>';

    const params = new URLSearchParams({
      day_status: queuesState.dayStatus,
      page: String(queuesState.page),
      per_page: String(queuesState.perPage)
    });

    if (queuesState.dateFrom) params.set('date_from', queuesState.dateFrom);
    if (queuesState.dateTo) params.set('date_to', queuesState.dateTo);

    try {
      const response = await requestJson(`${API.queues}?${params.toString()}`);
      if (requestId !== queuesState.requestId) return;

      const items = response.data?.items || [];
      const pagination = response.data?.pagination || {};
      const filters = response.data?.filters || {};

      if (!queuesState.dateFrom && filters.date_from) {
        queuesState.dateFrom = filters.date_from;
        setInputValue('queues-date-from', filters.date_from);
      }
      if (!queuesState.dateTo && filters.date_to) {
        queuesState.dateTo = filters.date_to;
        setInputValue('queues-date-to', filters.date_to);
      }

      if (summary) summary.textContent = `${Number(pagination.total || 0)} journée(s) trouvée(s)`;
      if (paginationSummary) paginationSummary.textContent = pageSummary(pagination);

      renderPagination(paginationContainer, pagination, page => {
        queuesState.page = page;
        loadQueuesHistory();
      });

      if (!items.length) {
        body.innerHTML = '<tr><td colspan="8" class="v1-empty-cell">Aucune liste dans cette période.</td></tr>';
        clearQueueDetails();
        return;
      }

      body.innerHTML = items.map(queue => `
        <tr
          data-selectable="true"
          data-queue-id="${queue.id}"
          class="${Number(queue.id) === Number(queuesState.selectedId) ? 'is-selected' : ''}"
          tabindex="0"
        >
          <td><strong>${formatDate(queue.queue_date)}</strong></td>
          <td>${renderStatus(queue.day_status)}</td>
          <td>${queue.total_entries}</td>
          <td>${Number(queue.waiting_count || 0) + Number(queue.called_count || 0)}</td>
          <td>${queue.done_count}</td>
          <td>${queue.no_show_count}</td>
          <td>${queue.canceled_count}</td>
          <td class="v1-table__action-heading">
            <button type="button" class="v1-icon-button" data-open-queue="${queue.id}" title="Voir la journée" aria-label="Voir la journée du ${formatDate(queue.queue_date)}">›</button>
          </td>
        </tr>
      `).join('');

      body.querySelectorAll('tr[data-queue-id]').forEach(row => {
        const open = () => selectQueue(Number(row.dataset.queueId));
        row.addEventListener('click', open);
        row.addEventListener('keydown', event => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
          }
        });
      });

      if (
        queuesState.selectedId
        && items.some(item => Number(item.id) === Number(queuesState.selectedId))
      ) {
        await loadQueueDetails(queuesState.selectedId);
      } else {
        await selectQueue(items[0].id);
      }
    } catch (error) {
      console.error('Toutes les listes :', error);
      body.innerHTML = '<tr><td colspan="8" class="v1-empty-cell">Impossible de charger les listes.</td></tr>';
      setMessage(message, error.message, 'error');
    }
  }

  async function selectQueue(queueId) {
    queuesState.selectedId = Number(queueId);

    document.querySelectorAll('#queues-history-table-body tr[data-queue-id]').forEach(row => {
      row.classList.toggle(
        'is-selected',
        Number(row.dataset.queueId) === queuesState.selectedId
      );
    });

    await loadQueueDetails(queueId);
  }

  async function loadQueueDetails(queueId) {
    const empty = document.getElementById('queue-detail-empty');
    const content = document.getElementById('queue-detail-content');
    const title = document.getElementById('queue-detail-title');
    const summary = document.getElementById('queue-detail-summary');
    const body = document.getElementById('queue-detail-table-body');
    const count = document.getElementById('queue-detail-count');

    if (!empty || !content || !summary || !body) return;

    empty.hidden = false;
    empty.textContent = 'Chargement de la journée…';
    content.hidden = true;

    try {
      const response = await requestJson(
        `${API.queueDetails}?queue_id=${encodeURIComponent(queueId)}`
      );
      const queue = response.data?.queue;
      const entries = response.data?.entries || [];

      if (!queue) throw new Error('Liste introuvable.');

      queuesState.selectedId = Number(queue.id);
      if (title) title.textContent = `Liste du ${formatDate(queue.queue_date)}`;
      if (count) count.textContent = String(entries.length);

      summary.innerHTML = [
        ['État de la journée', renderStatus(queue.day_status), true],
        ['Inscriptions', statusLabel(queue.registration_status)],
        ['Ouverture', formatDateTime(queue.opened_at || queue.created_at)],
        ['Fermeture', formatDateTime(queue.completed_at || queue.closed_at)],
        ['Ouverte par', queue.opened_by_name || '-'],
        ['Clôturée par', queue.completed_by_name || queue.closed_by_name || '-']
      ].map(([label, value, raw]) => `
        <div>
          <dt>${escapeHtml(label)}</dt>
          <dd>${raw ? value : escapeHtml(value)}</dd>
        </div>
      `).join('');

      if (!entries.length) {
        body.innerHTML = '<tr><td colspan="6" class="v1-empty-cell">Aucun patient pour cette journée.</td></tr>';
      } else {
        body.innerHTML = entries.map(entry => `
          <tr>
            <td>${entry.position_number ?? '-'}</td>
            <td><strong>${escapeHtml(entry.display_name)}</strong></td>
            <td>${escapeHtml(formatPhoneForDisplay(entry.phone) || '-')}</td>
            <td>${renderStatus(entry.status)}</td>
            <td>${escapeHtml(entry.created_by_name || '-')}</td>
            <td>${escapeHtml(entry.updated_by_name || '-')}</td>
          </tr>
        `).join('');
      }

      empty.hidden = true;
      content.hidden = false;
    } catch (error) {
      console.error('Détail liste :', error);
      empty.hidden = false;
      empty.textContent = error.message;
      content.hidden = true;
    }
  }

  function clearQueueDetails() {
    queuesState.selectedId = null;
    const empty = document.getElementById('queue-detail-empty');
    const content = document.getElementById('queue-detail-content');
    const title = document.getElementById('queue-detail-title');

    if (title) title.textContent = 'Sélectionnez une journée';
    if (empty) {
      empty.hidden = false;
      empty.textContent = 'Cliquez sur une journée pour consulter son résumé et ses patients.';
    }
    if (content) content.hidden = true;
  }

  /* =======================================================
     PARAMÈTRES
     ======================================================= */

  function initSettingsPage() {
    const form = document.getElementById('settings-form');
    if (!form) return;

    form.addEventListener('submit', saveSettings);
    loadSettings();
  }

  async function loadSettings() {
    const message = document.getElementById('settings-page-message');
    const form = document.getElementById('settings-form');
    const button = document.getElementById('settings-save-button');

    if (!form) return;

    setMessage(message, 'Chargement des paramètres…', 'info');
    if (button) button.disabled = true;

    try {
      const response = await requestJson(API.settings);
      fillSettingsForm(response.data?.clinic || {}, response.data?.doctor || {});
      setMessage(message);
    } catch (error) {
      console.error('Paramètres :', error);
      setMessage(message, error.message, 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  function fillSettingsForm(clinic, doctor) {
    setInputValue('settings-clinic-name', clinic.name);
    setInputValue('settings-clinic-type', clinic.type || 'solo');
    setInputValue('settings-clinic-phone', formatPhoneForDisplay(clinic.phone));
    setInputValue('settings-clinic-address', clinic.address);
    setInputValue('settings-clinic-city', clinic.city);
    setInputValue('settings-clinic-wilaya', clinic.wilaya);
    setInputValue('settings-clinic-timezone', clinic.timezone || 'Africa/Algiers');

    setInputValue('settings-doctor-name', doctor.display_name);
    setInputValue('settings-doctor-specialty', doctor.specialty);
    setInputValue('settings-doctor-license', doctor.license_number);
    setInputValue('settings-doctor-address', doctor.address);
  }

  async function saveSettings(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const message = document.getElementById('settings-page-message');
    const button = document.getElementById('settings-save-button');
    const payload = Object.fromEntries(new FormData(form).entries());

    setMessage(message);
    if (button) {
      button.disabled = true;
      button.textContent = 'Enregistrement…';
    }

    try {
      const response = await requestJson(API.settingsUpdate, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      fillSettingsForm(response.data?.clinic || {}, response.data?.doctor || {});
      setMessage(message, response.message || 'Paramètres enregistrés.', 'success');
    } catch (error) {
      console.error('Enregistrement paramètres :', error);
      setMessage(message, error.message, 'error');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Enregistrer les paramètres';
      }
    }
  }

  /* =======================================================
     BRANCHEMENT AVEC LE ROUTEUR ACTUEL DE app.js
     ======================================================= */

  const previousInitPage = typeof window.initPage === 'function'
    ? window.initPage
    : null;

  window.initPage = function (page) {
    if (previousInitPage) {
      previousInitPage(page);
    }

    if (page === 'patients') {
      initPatientsPage();
      return;
    }

    if (page === 'lists') {
      initListsPage();
      return;
    }

    if (page === 'settings') {
      initSettingsPage();
    }
  };
})();
