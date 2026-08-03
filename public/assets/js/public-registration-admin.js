(function () {
  'use strict';

  const API = {
    get: 'api/public_registration_get.php',
    save: 'api/public_registration_save.php',
    toggle: 'api/public_registration_toggle.php',
    revoke: 'api/public_registration_revoke.php'
  };

  const state = {
    initializedForDoctor: null,
    loading: false,
    data: null,
    confirmAction: null,
    qrInstance: null
  };

  function byId(id) {
    return document.getElementById(id);
  }

  function setMessage(message = '', type = '') {
    const element = byId('qr-admin-message');
    if (!element) return;

    element.textContent = message;
    element.className = 'v1-message';
    if (message && type) {
      element.classList.add(`is-${type}`);
    }
  }

  function notify(message, type = 'info') {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type);
      return;
    }

    setMessage(message, type);
  }

  async function readJson(response) {
    const raw = await response.text();

    try {
      return JSON.parse(raw);
    } catch (error) {
      console.error('[MARKI QR] Réponse non JSON :', raw);
      throw new Error('Le serveur a renvoyé une réponse invalide.');
    }
  }

  async function requestJson(url, options = {}, retries = 1) {
    const requestOptions = {
      cache: 'no-store',
      ...options
    };

    try {
      const response = await fetch(url, requestOptions);
      const data = await readJson(response);

      if (!response.ok || !data?.ok) {
        const error = new Error(
          data?.message || 'Une erreur est survenue.'
        );
        error.status = response.status;
        error.data = data;
        error.serverError = data?.error || '';
        error.errorId = data?.error_id || '';
        throw error;
      }

      return data;
    } catch (error) {
      const method = String(requestOptions.method || 'GET').toUpperCase();
      const retryable = method === 'GET'
        && retries > 0
        && (!error?.status || error.status >= 500);

      if (!retryable) throw error;

      await new Promise(resolve => window.setTimeout(resolve, 300));
      return requestJson(url, options, retries - 1);
    }
  }

  function formatDateTime(value) {
    if (!value) return '—';

    const match = String(value).match(
      /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/
    );

    if (!match) return String(value);

    return `${match[3]}/${match[2]}/${match[1]} à ${match[4]}:${match[5]}`;
  }

  function clearFieldErrors() {
    document.querySelectorAll('[data-qr-error]').forEach(element => {
      element.textContent = '';
    });

    document.querySelectorAll('#qr-settings-form .v1-field.is-invalid')
      .forEach(element => element.classList.remove('is-invalid'));
  }

  function showFieldErrors(errors = {}) {
    Object.entries(errors).forEach(([key, value]) => {
      const errorElement = document.querySelector(
        `[data-qr-error="${CSS.escape(key)}"]`
      );
      if (errorElement) {
        errorElement.textContent = String(value || '');
      }

      const field = document.querySelector(
        `#qr-settings-form [name="${CSS.escape(key)}"]`
      );
      field?.closest('.v1-field')?.classList.add('is-invalid');
    });
  }

  function renderStatus(active) {
    const badge = byId('qr-admin-status');
    const button = byId('qr-toggle-button');

    if (badge) {
      badge.textContent = active ? 'QR actif' : 'QR désactivé';
      badge.className = `qr-admin-status ${active ? 'is-active' : 'is-inactive'}`;
    }

    if (button) {
      button.textContent = active
        ? 'Désactiver temporairement'
        : 'Activer le QR';
      button.classList.toggle('v1-button--primary', !active);
      button.classList.toggle('v1-button--danger-outline', active);
      button.dataset.active = active ? '1' : '0';
    }
  }

  function renderQrCode(url) {
    const container = byId('qr-code-canvas');
    if (!container) return;

    container.innerHTML = '';
    state.qrInstance = null;

    if (!url) {
      container.innerHTML = '<p class="qr-code-canvas__error">Lien public indisponible.</p>';
      return;
    }

    if (typeof window.QRCode !== 'function') {
      container.innerHTML = `
        <div class="qr-code-canvas__error">
          <strong>Le générateur QR n’a pas pu être chargé.</strong>
          <span>Le lien public reste disponible et peut être copié.</span>
        </div>
      `;
      return;
    }

    state.qrInstance = new window.QRCode(container, {
      text: url,
      width: 236,
      height: 236,
      colorDark: '#17132f',
      colorLight: '#ffffff',
      correctLevel: window.QRCode.CorrectLevel.H
    });
  }

  function renderOverview(data) {
    state.data = data;

    const section = byId('public-registration-section');
    if (section) section.hidden = false;

    renderStatus(Boolean(data.link?.is_active));

    if (byId('qr-public-url')) {
      byId('qr-public-url').value = data.link?.public_url || '';
    }
    if (byId('qr-admin-doctor-name')) {
      byId('qr-admin-doctor-name').textContent = data.doctor?.name || '—';
    }
    if (byId('qr-admin-clinic-name')) {
      byId('qr-admin-clinic-name').textContent = data.clinic?.name || '—';
    }
    if (byId('qr-token-version')) {
      byId('qr-token-version').textContent =
        `Version ${Number(data.link?.token_version || 1)}`;
    }

    if (byId('qr-scans-today')) {
      byId('qr-scans-today').textContent =
        String(Number(data.metrics?.scans_today || 0));
    }
    if (byId('qr-registrations-today')) {
      byId('qr-registrations-today').textContent =
        String(Number(data.metrics?.registrations_today || 0));
    }
    if (byId('qr-last-scan')) {
      byId('qr-last-scan').textContent =
        formatDateTime(data.link?.last_scanned_at);
    }

    if (byId('qr-birth-date-required')) {
      byId('qr-birth-date-required').checked =
        Boolean(data.settings?.birth_date_required);
    }
    if (byId('qr-max-registrations')) {
      byId('qr-max-registrations').value =
        data.settings?.max_public_registrations_per_day ?? '';
    }
    if (byId('qr-session-duration')) {
      byId('qr-session-duration').value = String(
        Number(data.settings?.public_session_duration_minutes || 720)
      );
    }

    const messageBindings = {
      'qr-message-day-not-open': 'day_not_open',
      'qr-message-open': 'registration_open',
      'qr-message-closed': 'registration_closed',
      'qr-message-paused': 'queue_paused',
      'qr-message-completed': 'day_completed',
      'qr-message-disabled': 'qr_disabled',
      'qr-message-outside-schedule': 'outside_schedule',
      'qr-message-success': 'registration_success'
    };

    Object.entries(messageBindings).forEach(([id, code]) => {
      const field = byId(id);
      if (field) field.value = data.messages?.[code] || '';
    });

    renderQrCode(data.link?.public_url || '');
    renderNetworkWarning(data.link?.public_url || '');
  }

  function renderNetworkWarning(url) {
    const warning = byId('qr-network-warning');
    if (!warning) return;

    try {
      const parsed = new URL(url, window.location.href);
      warning.hidden = !['localhost', '127.0.0.1', '::1'].includes(
        parsed.hostname
      );
    } catch (_) {
      warning.hidden = true;
    }
  }

  async function loadOverview({ silent = false } = {}) {
    if (state.loading) return;
    state.loading = true;

    if (!silent) setMessage('Chargement de la configuration du QR…', 'info');

    try {
      const response = await requestJson(API.get);
      renderOverview(response.data || {});
      setMessage();
    } catch (error) {
      if (error.status === 403) {
        const section = byId('public-registration-section');
        if (section) section.hidden = true;
        return;
      }

      console.error('[MARKI QR] Chargement :', error);
      const detail = error.serverError ? ` Détail : ${error.serverError}` : '';
      const reference = error.errorId ? ` Référence : ${error.errorId}.` : '';
      setMessage(`${error.message}${detail}${reference}`, 'error');
    } finally {
      state.loading = false;
    }
  }

  async function saveSettings(event) {
    event.preventDefault();
    clearFieldErrors();
    setMessage();

    const button = byId('qr-save-settings');
    if (button) {
      button.disabled = true;
      button.textContent = 'Enregistrement…';
    }

    const payload = {
      birth_date_required: Boolean(byId('qr-birth-date-required')?.checked),
      max_public_registrations_per_day:
        byId('qr-max-registrations')?.value.trim() || '',
      public_session_duration_minutes:
        Number(byId('qr-session-duration')?.value || 720),
      messages: {
        day_not_open: byId('qr-message-day-not-open')?.value.trim() || '',
        registration_open: byId('qr-message-open')?.value.trim() || '',
        registration_closed: byId('qr-message-closed')?.value.trim() || '',
        queue_paused: byId('qr-message-paused')?.value.trim() || '',
        day_completed: byId('qr-message-completed')?.value.trim() || '',
        qr_disabled: byId('qr-message-disabled')?.value.trim() || '',
        outside_schedule:
          byId('qr-message-outside-schedule')?.value.trim() || '',
        registration_success: byId('qr-message-success')?.value.trim() || ''
      }
    };

    try {
      const response = await requestJson(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      notify(response.message, 'success');
      await loadOverview({ silent: true });
    } catch (error) {
      console.error('[MARKI QR] Enregistrement :', error);
      showFieldErrors(error.data?.errors || {});
      setMessage(error.message, 'error');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Enregistrer les réglages';
      }
    }
  }

  function openConfirm({ title, message, confirmLabel, danger = false, action }) {
    const modal = byId('qr-confirm-modal');
    if (!modal) return;

    state.confirmAction = action;
    byId('qr-confirm-title').textContent = title;
    byId('qr-confirm-message').textContent = message;

    const confirmButton = byId('qr-confirm-action');
    confirmButton.textContent = confirmLabel;
    confirmButton.classList.toggle('v1-button--primary', !danger);
    confirmButton.classList.toggle('v1-button--danger', danger);

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    window.setTimeout(() => confirmButton.focus(), 0);
  }

  function closeConfirm() {
    const modal = byId('qr-confirm-modal');
    if (!modal) return;

    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    state.confirmAction = null;
  }

  async function runConfirmedAction() {
    const action = state.confirmAction;
    if (typeof action !== 'function') return;

    const button = byId('qr-confirm-action');
    const previous = button.textContent;
    button.disabled = true;
    button.textContent = 'Traitement…';

    try {
      await action();
      closeConfirm();
    } catch (error) {
      console.error('[MARKI QR] Action confirmée :', error);
      setMessage(error.message, 'error');
      button.disabled = false;
      button.textContent = previous;
    }
  }

  function requestToggle() {
    const active = byId('qr-toggle-button')?.dataset.active === '1';

    openConfirm({
      title: active ? 'Désactiver le QR ?' : 'Activer le QR ?',
      message: active
        ? 'Le QR imprimé restera valide, mais les patients verront que l’inscription publique est indisponible.'
        : 'Les patients pourront de nouveau utiliser le même QR pour rejoindre la liste lorsque la journée est ouverte.',
      confirmLabel: active ? 'Désactiver' : 'Activer',
      danger: active,
      action: async () => {
        const response = await requestJson(API.toggle, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ active: !active })
        });
        notify(response.message, 'success');
        await loadOverview({ silent: true });
      }
    });
  }

  function requestRegeneration() {
    openConfirm({
      title: 'Régénérer le QR pour sécurité ?',
      message: 'L’ancien QR et l’ancien lien cesseront immédiatement de fonctionner. Les affiches déjà imprimées devront être remplacées.',
      confirmLabel: 'Régénérer le QR',
      danger: true,
      action: async () => {
        const response = await requestJson(API.revoke, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        });
        notify(response.message, 'success');
        await loadOverview({ silent: true });
      }
    });
  }

  async function copyPublicLink() {
    const url = byId('qr-public-url')?.value || '';
    if (!url) return;

    try {
      await navigator.clipboard.writeText(url);
      notify('Lien public copié.', 'success');
    } catch (_) {
      const field = byId('qr-public-url');
      field?.select();
      document.execCommand('copy');
      notify('Lien public copié.', 'success');
    }
  }

  function openPublicLink() {
    const url = byId('qr-public-url')?.value || '';
    if (url) window.open(url, '_blank', 'noopener,noreferrer');
  }

  function qrImageDataUrl() {
    const container = byId('qr-code-canvas');
    const canvas = container?.querySelector('canvas');
    if (canvas) return canvas.toDataURL('image/png');

    return container?.querySelector('img')?.src || '';
  }

  function downloadQr() {
    const imageUrl = qrImageDataUrl();
    if (!imageUrl) {
      setMessage('Le QR n’est pas encore disponible au téléchargement.', 'error');
      return;
    }

    const doctor = state.data?.doctor?.name || 'medecin';
    const filename = `marki-qr-${doctor}`
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');

    const link = document.createElement('a');
    link.href = imageUrl;
    link.download = `${filename || 'marki-qr'}.png`;
    document.body.append(link);
    link.click();
    link.remove();
  }

  function printQr() {
    const imageUrl = qrImageDataUrl();
    const url = byId('qr-public-url')?.value || '';
    if (!imageUrl || !url) {
      setMessage('Le QR n’est pas encore prêt à être imprimé.', 'error');
      return;
    }

    const doctor = state.data?.doctor?.name || 'Votre médecin';
    const specialty = state.data?.doctor?.specialty || '';
    const clinic = state.data?.clinic?.name || 'Cabinet médical';
    const popup = window.open('', '_blank');

    if (!popup) {
      setMessage('Autorisez les fenêtres contextuelles pour imprimer le QR.', 'error');
      return;
    }

    try { popup.opener = null; } catch (_) {}

    popup.document.write(`<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>QR MARKI — ${escapeHtml(doctor)}</title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: Arial, sans-serif; color: #17132f; }
  .poster { min-height: 260mm; border: 2px solid #e5e0ff; border-radius: 28px; padding: 24mm 18mm; display: flex; flex-direction: column; align-items: center; text-align: center; }
  .brand { font-size: 22px; font-weight: 800; color: #6d4aff; letter-spacing: .12em; }
  h1 { font-size: 34px; margin: 26px 0 8px; }
  .clinic { color: #5f5a73; font-size: 18px; margin: 0 0 28px; }
  .qr { width: 290px; height: 290px; padding: 18px; border: 1px solid #ece9f7; border-radius: 24px; box-shadow: 0 12px 40px rgba(60, 43, 130, .12); }
  .doctor { font-size: 25px; font-weight: 800; margin: 30px 0 6px; }
  .specialty { color: #6d4aff; font-size: 17px; margin: 0; }
  .steps { margin-top: 32px; max-width: 520px; font-size: 18px; line-height: 1.55; }
  .url { margin-top: auto; padding-top: 32px; color: #777188; font-size: 10px; word-break: break-all; }
</style>
</head>
<body>
  <main class="poster">
    <div class="brand">MARKI</div>
    <h1>Rejoignez la liste d’attente</h1>
    <p class="clinic">${escapeHtml(clinic)}</p>
    <img class="qr" src="${imageUrl}" alt="QR code d’inscription">
    <p class="doctor">${escapeHtml(doctor)}</p>
    <p class="specialty">${escapeHtml(specialty)}</p>
    <p class="steps">Ouvrez l’appareil photo de votre téléphone, scannez le QR code puis remplissez le formulaire d’inscription.</p>
    <p class="url">${escapeHtml(url)}</p>
  </main>
  <script>window.addEventListener('load', function(){ window.print(); });<\/script>
</body>
</html>`);
    popup.document.close();
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function bindEvents(section) {
    if (section.dataset.qrBound === '1') return;
    section.dataset.qrBound = '1';

    byId('qr-settings-form')?.addEventListener('submit', saveSettings);
    byId('qr-toggle-button')?.addEventListener('click', requestToggle);
    byId('qr-regenerate')?.addEventListener('click', requestRegeneration);
    byId('qr-copy-link')?.addEventListener('click', copyPublicLink);
    byId('qr-open-link')?.addEventListener('click', openPublicLink);
    byId('qr-download')?.addEventListener('click', downloadQr);
    byId('qr-print')?.addEventListener('click', printQr);
    byId('qr-confirm-action')?.addEventListener('click', runConfirmedAction);

    document.querySelectorAll('[data-close-qr-confirm]').forEach(button => {
      button.addEventListener('click', closeConfirm);
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !byId('qr-confirm-modal')?.hidden) {
        closeConfirm();
      }
    });
  }

  window.initMarkiPublicRegistration = async function () {
    const section = byId('public-registration-section');
    if (!section) return false;

    const canManageQr = Boolean(
      window.MARKI_CONTEXT?.capabilities?.['settings.manage_doctor']
    );

    if (!canManageQr) {
      section.remove();
      return false;
    }

    bindEvents(section);
    const doctorId = String(
      window.MARKI_CONTEXT?.doctor_id
      || document.querySelector('[data-selected-doctor-id]')?.dataset.selectedDoctorId
      || 'current'
    );

    if (state.initializedForDoctor !== doctorId) {
      state.initializedForDoctor = doctorId;
      state.data = null;
    }

    await loadOverview();
    return true;
  };
})();
