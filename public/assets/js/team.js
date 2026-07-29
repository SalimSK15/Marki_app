(function () {
  'use strict';

  const API = {
    list: 'api/team_list.php',
    save: 'api/team_save.php',
    toggle: 'api/team_toggle_status.php'
  };

  const state = {
    members: [],
    doctors: []
  };

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function setMessage(text = '', type = '') {
    const element = document.getElementById('team-message');
    if (!element) return;

    element.textContent = text;
    element.className = 'v1-message';
    if (text && type) element.classList.add(`is-${type}`);
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json();

    if (!response.ok || !data?.ok) {
      throw new Error(data?.message || 'Une erreur est survenue.');
    }

    return data;
  }

  function formatDateTime(value) {
    if (!value) return 'Jamais';
    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return match
      ? `${match[3]}/${match[2]}/${match[1]} à ${match[4]}:${match[5]}`
      : escapeHtml(value);
  }

  function accessLabel(member) {
    if (member.roles?.includes('clinic_admin')) return 'Administration complète';
    if (member.account_type === 'doctor') return 'Médecin';

    const levels = [...new Set(
      (member.doctor_accesses || []).map(access => access.access_level)
    )];

    const labels = {
      queue_only: 'Liste du jour',
      queue_and_patients: 'Liste, patients et historique',
      full: 'Accès opérationnel complet'
    };

    return levels.map(level => labels[level] || level).join(', ') || 'Aucun médecin';
  }

  function renderDoctorsOptions(selectedIds = []) {
    const container = document.getElementById('team-doctors-options');
    if (!container) return;

    container.innerHTML = state.doctors
      .filter(doctor => doctor.is_active)
      .map(doctor => `
        <label class="team-doctor-option">
          <input
            type="checkbox"
            name="doctor_ids[]"
            value="${doctor.id}"
            ${selectedIds.includes(Number(doctor.id)) ? 'checked' : ''}
          >
          <span>
            <strong>${escapeHtml(doctor.display_name)}</strong>
            <small>${escapeHtml(doctor.specialty || 'Médecin')}</small>
          </span>
        </label>
      `)
      .join('');
  }

  function renderTable() {
    const body = document.getElementById('team-table-body');
    if (!body) return;

    if (state.members.length === 0) {
      body.innerHTML = '<tr><td colspan="6" class="v1-empty-cell">Aucun compte.</td></tr>';
      return;
    }

    body.innerHTML = state.members.map(member => {
      const typeLabel = member.account_type === 'doctor'
        ? 'Médecin'
        : member.roles?.includes('clinic_admin')
          ? 'Administrateur'
          : 'Secrétariat';
      const protectedAccount = member.is_protected_admin;
      const actionLabel = member.status === 'active' ? 'Désactiver' : 'Réactiver';

      return `
        <tr data-team-user-id="${member.id}">
          <td>
            <strong>${escapeHtml(member.full_name)}</strong>
            <span class="v1-table__subtext">${escapeHtml(member.email || member.phone || '-')}</span>
          </td>
          <td>${escapeHtml(typeLabel)}</td>
          <td>${escapeHtml(accessLabel(member))}</td>
          <td>
            <span class="v1-status v1-status--${member.status === 'active' ? 'active' : 'canceled'}">
              ${member.status === 'active' ? 'Actif' : 'Désactivé'}
            </span>
          </td>
          <td>${formatDateTime(member.last_login_at)}</td>
          <td>
            <div class="team-actions">
              <button
                type="button"
                class="v1-icon-button"
                data-team-edit="${member.id}"
                ${protectedAccount ? 'disabled title="Compte administrateur protégé"' : 'title="Modifier"'}
              >Modifier</button>
              <button
                type="button"
                class="v1-icon-button"
                data-team-toggle="${member.id}"
                ${protectedAccount || member.is_current_user ? 'disabled' : ''}
              >${actionLabel}</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    body.querySelectorAll('[data-team-edit]').forEach(button => {
      button.addEventListener('click', () => {
        const member = state.members.find(
          item => Number(item.id) === Number(button.dataset.teamEdit)
        );
        if (member) openForm(member);
      });
    });

    body.querySelectorAll('[data-team-toggle]').forEach(button => {
      button.addEventListener('click', () => toggleStatus(Number(button.dataset.teamToggle)));
    });
  }

  function updateConditionalFields() {
    const accountType = document.getElementById('team-account-type')?.value || 'secretary';
    const isDoctor = accountType === 'doctor';

    document.getElementById('team-job-title-field')?.toggleAttribute('hidden', isDoctor);
    document.getElementById('team-specialty-field')?.toggleAttribute('hidden', !isDoctor);
    document.getElementById('team-license-field')?.toggleAttribute('hidden', !isDoctor);
    document.getElementById('team-access-level-field')?.toggleAttribute('hidden', isDoctor);
    document.getElementById('team-doctors-field')?.toggleAttribute('hidden', isDoctor);
  }

  function setInput(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value ?? '';
  }

  function openForm(member = null) {
    const form = document.getElementById('team-account-form');
    if (!form) return;

    form.hidden = false;
    form.reset();
    setInput('team-user-id', member?.id || '');
    setInput('team-full-name', member?.full_name || '');
    setInput('team-email', member?.email || '');
    setInput('team-phone', member?.phone || '');
    setInput('team-account-type', member?.account_type || 'secretary');
    setInput('team-job-title', member?.job_title || '');
    setInput('team-specialty', member?.doctor_specialty || '');
    setInput('team-license-number', member?.doctor_license_number || '');
    setInput(
      'team-access-level',
      member?.doctor_accesses?.[0]?.access_level || 'queue_only'
    );
    setInput('team-temporary-password', '');

    const accountType = document.getElementById('team-account-type');
    if (accountType) accountType.disabled = Boolean(member);

    const title = document.getElementById('team-form-title');
    if (title) title.textContent = member ? 'Modifier le compte' : 'Nouveau compte';

    const passwordInput = document.getElementById('team-temporary-password');
    if (passwordInput) {
      passwordInput.required = !member;
      passwordInput.placeholder = member
        ? 'Laisser vide pour conserver le mot de passe'
        : 'Mot de passe temporaire';
    }

    renderDoctorsOptions(
      (member?.doctor_accesses || []).map(access => Number(access.doctor_id))
    );
    updateConditionalFields();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function closeForm() {
    const form = document.getElementById('team-account-form');
    if (!form) return;
    form.hidden = true;
    form.reset();
    const accountType = document.getElementById('team-account-type');
    if (accountType) accountType.disabled = false;
    setMessage('');
  }

  async function loadTeam() {
    const section = document.getElementById('team-settings-section');
    const body = document.getElementById('team-table-body');
    if (!section || !body) return;

    try {
      const response = await requestJson(API.list);
      state.members = response.data?.members || [];
      state.doctors = response.data?.doctors || [];
      section.hidden = false;
      renderTable();
      renderDoctorsOptions();
    } catch (error) {
      if (String(error.message).includes('accès')) {
        section.hidden = true;
        return;
      }
      section.hidden = false;
      body.innerHTML = '<tr><td colspan="6" class="v1-empty-cell">Impossible de charger l’équipe.</td></tr>';
      setMessage(error.message, 'error');
    }
  }

  async function saveAccount(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = document.getElementById('team-form-save-button');
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.doctor_ids = [...form.querySelectorAll('[name="doctor_ids[]"]:checked')]
      .map(input => Number(input.value));

    const accountType = document.getElementById('team-account-type');
    payload.account_type = accountType?.value || 'secretary';

    if (!payload.email && !payload.phone) {
      setMessage('Un courriel ou un téléphone est obligatoire.', 'error');
      return;
    }

    if (button) button.disabled = true;
    setMessage('');

    try {
      const response = await requestJson(API.save, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      state.members = response.data?.members || [];
      state.doctors = response.data?.doctors || [];
      renderTable();
      closeForm();
      setMessage(response.message || 'Compte enregistré.', 'success');
    } catch (error) {
      setMessage(error.message, 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  async function toggleStatus(userId) {
    const member = state.members.find(item => Number(item.id) === userId);
    if (!member) return;

    const action = member.status === 'active' ? 'désactiver' : 'réactiver';
    if (!window.confirm(`Confirmer : ${action} le compte de ${member.full_name} ?`)) {
      return;
    }

    try {
      const response = await requestJson(API.toggle, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
      });
      state.members = response.data?.members || [];
      state.doctors = response.data?.doctors || [];
      renderTable();
      setMessage(response.message || 'Compte modifié.', 'success');
    } catch (error) {
      setMessage(error.message, 'error');
    }
  }

  function initTeamPage() {
    const section = document.getElementById('team-settings-section');
    if (!section) return;

    document
      .getElementById('team-new-account-button')
      ?.addEventListener('click', () => openForm());
    document
      .getElementById('team-form-close-button')
      ?.addEventListener('click', closeForm);
    document
      .getElementById('team-form-cancel-button')
      ?.addEventListener('click', closeForm);
    document
      .getElementById('team-account-type')
      ?.addEventListener('change', updateConditionalFields);
    document
      .getElementById('team-account-form')
      ?.addEventListener('submit', saveAccount);

    loadTeam();
  }

  const previousInitializer = window.initMarkiV1Page;
  window.initMarkiV1Page = function (page) {
    if (typeof previousInitializer === 'function') {
      previousInitializer(page);
    }

    if (page === 'settings') {
      initTeamPage();
    }
  };
})();
