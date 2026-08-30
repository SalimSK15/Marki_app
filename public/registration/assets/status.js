(function () {
  'use strict';

  const app = document.getElementById('public-status-app');
  if (!app) return;

  const state = {
    session: app.dataset.session || '',
    csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
    loading: false,
    timer: null
  };

  const STORAGE_PREFIX = 'marki:public-registration:';

  const elements = {
    loading: document.getElementById('status-loading'),
    error: document.getElementById('status-error'),
    errorMessage: document.getElementById('status-error-message'),
    content: document.getElementById('status-content'),
    patientName: document.getElementById('status-patient-name'),
    doctorLine: document.getElementById('status-doctor-line'),
    position: document.getElementById('status-position'),
    createdAt: document.getElementById('status-created-at'),
    label: document.getElementById('status-label'),
    ahead: document.getElementById('status-ahead'),
    aheadNote: document.getElementById('status-ahead-note'),
    guidance: document.getElementById('status-guidance'),
    clinicName: document.getElementById('status-clinic-name'),
    phone: document.getElementById('status-phone'),
    refreshedAt: document.getElementById('status-refreshed-at'),
    refresh: document.getElementById('status-refresh'),
    cancel: document.getElementById('status-cancel'),
    message: document.getElementById('status-message'),
    modal: document.getElementById('status-cancel-modal'),
    confirmCancel: document.getElementById('status-confirm-cancel')
  };

  function clearSavedSessionForCurrentToken() {
    try {
      for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
        const key = window.localStorage.key(index);
        if (!key?.startsWith(STORAGE_PREFIX)) continue;

        const raw = window.localStorage.getItem(key);
        const saved = raw ? JSON.parse(raw) : null;

        if (saved?.session === state.session) {
          window.localStorage.removeItem(key);
        }
      }
    } catch (error) {
      console.warn('Impossible de nettoyer le suivi local :', error);
    }
  }

  async function readJson(response) {
    const raw = await response.text();
    try {
      return JSON.parse(raw);
    } catch (error) {
      console.error('Réponse suivi non JSON :', raw);
      throw new Error('Le serveur a renvoyé une réponse invalide.');
    }
  }

  function setMessage(message = '', type = '') {
    if (!elements.message) return;
    elements.message.textContent = message;
    elements.message.className = 'public-registration-message';
    if (message && type) elements.message.classList.add(`is-${type}`);
  }

  function formatDateTime(value) {
    if (!value) return '—';
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('fr-DZ', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date);
  }

  function guidanceFor(registration) {
    const status = registration.status;
    if (status === 'waiting') {
      const ahead = Number(registration.patients_ahead ?? 0);
      if (ahead === 1) {
        return 'Il reste 1 patient devant vous. Gardez cette page pour suivre votre position.';
      }
      if (ahead > 1) {
        return `Il reste ${ahead} patients devant vous. Gardez cette page pour suivre votre position.`;
      }
      return 'Vous êtes le prochain patient en attente. Restez disponible.';
    }
    if (status === 'called') return 'Le cabinet vous appelle. Présentez-vous auprès du secrétariat.';
    if (status === 'no_show') return 'Vous avez été marqué absent. Contactez le secrétariat pour savoir si vous pouvez être réintégré.';
    if (status === 'done') return 'Votre passage est terminé. Merci d’avoir utilisé MARKI.';
    if (status === 'canceled') return 'Cette inscription a été annulée.';
    return 'Votre inscription est enregistrée.';
  }

  function render(data) {
    const patient = data.patient || {};
    const doctor = data.doctor || {};
    const clinic = data.clinic || {};
    const registration = data.registration || {};

    elements.loading.hidden = true;
    elements.error.hidden = true;
    elements.content.hidden = false;

    if (elements.patientName) elements.patientName.textContent = patient.name || '';
    if (elements.doctorLine) {
      elements.doctorLine.textContent = [doctor.name, doctor.specialty]
        .filter(Boolean)
        .join(' — ');
    }
    if (elements.position) elements.position.textContent = registration.position_number ?? '—';
    if (elements.createdAt) {
      elements.createdAt.textContent = `Inscrit le ${formatDateTime(registration.created_at)}`;
    }
    if (elements.label) {
      elements.label.textContent = registration.status_label || '—';
      elements.label.dataset.status = registration.status || '';
    }
    const patientsAhead = Number(registration.patients_ahead ?? 0);
    if (elements.ahead) elements.ahead.textContent = String(patientsAhead);
    if (elements.aheadNote) {
      elements.aheadNote.textContent = patientsAhead === 0
        ? 'Vous êtes le prochain patient.'
        : patientsAhead === 1
          ? '1 patient doit passer avant vous.'
          : `${patientsAhead} patients doivent passer avant vous.`;
    }
    if (elements.guidance) {
      elements.guidance.textContent = guidanceFor(registration);
      elements.guidance.className = `public-registration-notice is-${registration.status || 'waiting'}`;
    }
    if (elements.clinicName) elements.clinicName.textContent = clinic.name || '—';
    if (elements.phone) elements.phone.textContent = patient.phone || '—';
    if (elements.refreshedAt) {
      elements.refreshedAt.textContent = new Intl.DateTimeFormat('fr-DZ', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      }).format(new Date());
    }
    if (elements.cancel) elements.cancel.hidden = !registration.can_cancel;

    if (['done', 'canceled', 'no_show'].includes(registration.status)) {
      clearSavedSessionForCurrentToken();
    }
  }

  async function loadStatus(options = {}) {
    if (state.loading) return;
    state.loading = true;
    if (!options.silent) {
      setMessage();
      if (elements.refresh) elements.refresh.disabled = true;
    }

    try {
      const params = new URLSearchParams({ session: state.session });
      const response = await fetch(`api/status.php?${params.toString()}`, {
        cache: 'no-store',
        credentials: 'same-origin'
      });
      const data = await readJson(response);

      if (!response.ok || !data?.ok) {
        throw new Error(data?.message || 'Impossible de charger votre inscription.');
      }

      state.csrf = data.csrf_token || state.csrf;
      render(data.data);
    } catch (error) {
      elements.loading.hidden = true;
      elements.content.hidden = true;
      elements.error.hidden = false;
      if (elements.errorMessage) elements.errorMessage.textContent = error.message;
      clearSavedSessionForCurrentToken();
      if (state.timer) clearInterval(state.timer);
    } finally {
      state.loading = false;
      if (elements.refresh) elements.refresh.disabled = false;
    }
  }

  function openModal() {
    if (!elements.modal) return;
    elements.modal.hidden = false;
    elements.modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-modal');
    elements.confirmCancel?.focus();
  }

  function closeModal() {
    if (!elements.modal) return;
    elements.modal.hidden = true;
    elements.modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('has-modal');
  }

  async function cancelRegistration() {
    if (!elements.confirmCancel) return;
    elements.confirmCancel.disabled = true;
    elements.confirmCancel.textContent = 'Annulation…';

    try {
      const response = await fetch('api/cancel.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf
        },
        body: JSON.stringify({ session: state.session })
      });
      const data = await readJson(response);
      if (!response.ok || !data?.ok) {
        throw new Error(data?.message || 'Impossible d’annuler votre inscription.');
      }

      closeModal();
      setMessage(data.message, 'success');
      await loadStatus({ silent: true });
    } catch (error) {
      closeModal();
      setMessage(error.message, 'error');
    } finally {
      elements.confirmCancel.disabled = false;
      elements.confirmCancel.textContent = 'Annuler mon inscription';
    }
  }

  elements.refresh?.addEventListener('click', () => loadStatus());
  elements.cancel?.addEventListener('click', openModal);
  elements.confirmCancel?.addEventListener('click', cancelRegistration);
  document.querySelectorAll('[data-close-status-modal]').forEach(button => {
    button.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && elements.modal && !elements.modal.hidden) {
      closeModal();
    }
  });

  loadStatus();
  state.timer = window.setInterval(() => loadStatus({ silent: true }), 5000);
})();
