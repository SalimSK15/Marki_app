(function () {
  'use strict';

  const app = document.getElementById('public-registration-app');
  if (!app) return;

  const state = {
    link: app.dataset.link || '',
    token: app.dataset.token || '',
    csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
    allowSharedPhone: false,
    requirements: null,
    submitting: false
  };

  const elements = {
    loading: document.getElementById('registration-loading'),
    unavailable: document.getElementById('registration-unavailable'),
    unavailableMessage: document.getElementById('registration-unavailable-message'),
    contact: document.getElementById('registration-contact'),
    content: document.getElementById('registration-content'),
    introduction: document.getElementById('registration-introduction'),
    doctorName: document.getElementById('registration-doctor-name'),
    doctorSpecialty: document.getElementById('registration-doctor-specialty'),
    clinicName: document.getElementById('registration-clinic-name'),
    openMessage: document.getElementById('registration-open-message'),
    form: document.getElementById('public-registration-form'),
    fullName: document.getElementById('registration-full-name'),
    phone: document.getElementById('registration-phone'),
    birthDate: document.getElementById('registration-birth-date'),
    birthRequired: document.getElementById('registration-birth-date-required'),
    consent: document.getElementById('registration-privacy-consent'),
    sharedBox: document.getElementById('registration-shared-phone'),
    sharedMessage: document.getElementById('registration-shared-phone-message'),
    confirmShared: document.getElementById('registration-confirm-shared-phone'),
    message: document.getElementById('registration-form-message'),
    submit: document.getElementById('registration-submit')
  };

  function setHidden(element, hidden) {
    if (element) element.hidden = hidden;
  }

  function setMessage(message = '', type = '') {
    if (!elements.message) return;
    elements.message.textContent = message;
    elements.message.className = 'public-registration-message';
    if (message && type) {
      elements.message.classList.add(`is-${type}`);
    }
  }

  function clearErrors() {
    document.querySelectorAll('[data-error-for]').forEach(error => {
      error.textContent = '';
    });
    document.querySelectorAll('.public-registration-field.is-invalid').forEach(field => {
      field.classList.remove('is-invalid');
    });
  }

  function showErrors(errors = {}) {
    Object.entries(errors).forEach(([name, message]) => {
      const error = document.querySelector(`[data-error-for="${CSS.escape(name)}"]`);
      if (error) error.textContent = String(message || '');

      const field = document.querySelector(`[name="${CSS.escape(name)}"]`);
      field?.closest('.public-registration-field')?.classList.add('is-invalid');
    });

    const firstFieldName = Object.keys(errors)[0];
    if (firstFieldName) {
      document.querySelector(`[name="${CSS.escape(firstFieldName)}"]`)?.focus();
    }
  }

  async function readJson(response) {
    const raw = await response.text();
    try {
      return JSON.parse(raw);
    } catch (error) {
      console.error('Réponse publique non JSON :', raw);
      throw new Error('Le serveur a renvoyé une réponse invalide.');
    }
  }

  function contextUrl() {
    const params = new URLSearchParams({
      link: state.link,
      token: state.token
    });
    return `api/context.php?${params.toString()}`;
  }

  async function loadContext() {
    setHidden(elements.loading, false);
    setHidden(elements.unavailable, true);
    setHidden(elements.content, true);

    try {
      const response = await fetch(contextUrl(), {
        cache: 'no-store',
        credentials: 'same-origin'
      });
      const data = await readJson(response);

      if (!response.ok || !data?.ok) {
        throw new Error(data?.message || 'Ce lien ne peut pas être utilisé.');
      }

      state.csrf = data.csrf_token || state.csrf;
      state.requirements = data.data.requirements || {};
      renderContext(data.data);
    } catch (error) {
      setHidden(elements.loading, true);
      setHidden(elements.content, true);
      setHidden(elements.unavailable, false);
      if (elements.unavailableMessage) {
        elements.unavailableMessage.textContent = error.message;
      }
    }
  }

  function renderContext(data) {
    const clinic = data.clinic || {};
    const doctor = data.doctor || {};
    const availability = data.availability || {};

    if (elements.introduction) {
      elements.introduction.textContent =
        `Inscription à la liste d’attente de ${doctor.name || 'votre médecin'}.`;
    }
    if (elements.doctorName) elements.doctorName.textContent = doctor.name || 'Médecin';
    if (elements.doctorSpecialty) {
      elements.doctorSpecialty.textContent = doctor.specialty || 'Consultation médicale';
    }
    if (elements.clinicName) elements.clinicName.textContent = clinic.name || 'Cabinet médical';

    if (elements.contact && clinic.phone) {
      elements.contact.textContent = `Cabinet : ${clinic.phone}`;
      elements.contact.hidden = false;
    }

    setHidden(elements.loading, true);

    if (!availability.can_register) {
      setHidden(elements.content, true);
      setHidden(elements.unavailable, false);
      if (elements.unavailableMessage) {
        elements.unavailableMessage.textContent = availability.message || 'Inscription indisponible.';
      }
      return;
    }

    setHidden(elements.unavailable, true);
    setHidden(elements.content, false);
    if (elements.openMessage) {
      elements.openMessage.textContent = availability.message || '';
    }

    const birthRequired = Boolean(state.requirements?.birth_date_required);
    if (elements.birthDate) elements.birthDate.required = birthRequired;
    if (elements.birthRequired) elements.birthRequired.hidden = !birthRequired;

    if (elements.birthDate && data.today) {
      elements.birthDate.max = data.today;
    }

    window.MarkiPhone?.bind(document);
  }

  function currentPayload() {
    return {
      link: state.link,
      token: state.token,
      full_name: elements.fullName?.value.trim() || '',
      phone: elements.phone?.value.trim() || '',
      birth_date: elements.birthDate?.value || '',
      privacy_consent: Boolean(elements.consent?.checked),
      allow_shared_phone: state.allowSharedPhone
    };
  }

  function resetSharedPhoneConfirmation() {
    state.allowSharedPhone = false;
    setHidden(elements.sharedBox, true);
    if (elements.sharedMessage) elements.sharedMessage.textContent = '';
  }

  async function submitRegistration(event) {
    event?.preventDefault();
    if (state.submitting) return;

    clearErrors();
    setMessage();
    state.submitting = true;

    if (elements.submit) {
      elements.submit.disabled = true;
      elements.submit.textContent = 'Inscription en cours…';
    }

    try {
      const response = await fetch('api/submit.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf
        },
        body: JSON.stringify(currentPayload())
      });
      const data = await readJson(response);

      if (!response.ok || !data?.ok) {
        if (data?.error_code === 'PHONE_SHARED_CONFIRMATION_REQUIRED') {
          setHidden(elements.sharedBox, false);
          if (elements.sharedMessage) elements.sharedMessage.textContent = data.message;
          state.allowSharedPhone = true;
          elements.confirmShared?.focus();
          return;
        }

        showErrors(data?.errors || {});
        throw new Error(data?.message || 'Impossible de vous inscrire.');
      }

      resetSharedPhoneConfirmation();
      setMessage(data.message || 'Inscription enregistrée.', 'success');
      window.location.assign(data.data.status_path);
    } catch (error) {
      setMessage(error.message, 'error');
    } finally {
      state.submitting = false;
      if (elements.submit) {
        elements.submit.disabled = false;
        elements.submit.textContent = 'Rejoindre la liste d’attente';
      }
    }
  }

  elements.form?.addEventListener('submit', submitRegistration);
  elements.confirmShared?.addEventListener('click', () => {
    state.allowSharedPhone = true;
    submitRegistration();
  });

  [elements.fullName, elements.phone, elements.birthDate, elements.consent]
    .forEach(element => {
      element?.addEventListener('input', () => {
        setMessage();
        clearErrors();
        if (elements.sharedBox && !elements.sharedBox.hidden) {
          resetSharedPhoneConfirmation();
        }
      });
    });

  loadContext();
})();
