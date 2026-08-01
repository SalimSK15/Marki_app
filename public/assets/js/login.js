(function () {
  'use strict';

  function message(text, type = 'error') {
    const element = document.getElementById('auth-message');
    if (!element) return;

    element.textContent = text;
    element.className = `auth-message is-${type}`;
  }

  async function readJson(response) {
    const raw = await response.text();

    try {
      return JSON.parse(raw);
    } catch (error) {
      console.error('Réponse non JSON :', raw);
      throw new Error(
        'Le serveur a renvoyé une réponse inattendue. Rechargez la page puis réessayez.'
      );
    }
  }

  function csrfToken(form) {
    return document.querySelector('meta[name="csrf-token"]')?.content
      || form.querySelector('[name="csrf_token"]')?.value
      || '';
  }

  async function submitJson(form, endpoint) {
    const button = form.querySelector('button[type="submit"]');
    const payload = Object.fromEntries(new FormData(form).entries());
    const rememberInput = form.querySelector('[name="remember"]');

    if (rememberInput) {
      payload.remember = rememberInput.checked;
    }

    const token = csrfToken(form);
    const headers = {
      'Content-Type': 'application/json'
    };

    if (token) {
      headers['X-CSRF-Token'] = token;
    }

    message('');

    if (button) {
      button.disabled = true;
      button.dataset.defaultLabel = button.dataset.defaultLabel
        || button.textContent;
      button.textContent = 'Traitement…';
    }

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });

      const data = await readJson(response);

      if (!response.ok || !data?.ok) {
        throw new Error(data?.message || 'Impossible de traiter la demande.');
      }

      message(data.message || 'Opération réussie.', 'success');

      if (payload.clinic_slug) {
        localStorage.setItem('marki.clinicSlug', payload.clinic_slug);
      }

      if (data.data?.redirect) {
        window.setTimeout(() => {
          location.href = data.data.redirect;
        }, 180);
      }
    } catch (error) {
      message(error.message || 'Une erreur est survenue.');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = button.dataset.defaultLabel || 'Valider';
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const forgotForm = document.getElementById('forgot-password-form');
    const resetForm = document.getElementById('reset-password-form');
    const changeForm = document.getElementById('change-password-form');

    const clinicInput = document.querySelector('[name="clinic_slug"]');
    const rememberedSlug = localStorage.getItem('marki.clinicSlug');

    if (clinicInput && !clinicInput.value && rememberedSlug) {
      clinicInput.value = rememberedSlug;
    }

    const activeClinicSlug = clinicInput?.value || rememberedSlug || '';

    if (loginForm && !activeClinicSlug) {
      message(
        'Lien de connexion incomplet. Ouvrez le lien fourni par votre cabinet ou votre clinique.'
      );
      const submitButton = loginForm.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;
    }

    const forgotLink = document.querySelector('a[href^="forgot-password.php"]');

    if (forgotLink && activeClinicSlug) {
      forgotLink.href = `forgot-password.php?clinic=${encodeURIComponent(activeClinicSlug)}`;
    }

    const loginLink = document.querySelector('a[href^="login.php"]');

    if (loginLink && activeClinicSlug) {
      loginLink.href = `login.php?clinic=${encodeURIComponent(activeClinicSlug)}`;
    }

    loginForm?.addEventListener('submit', event => {
      event.preventDefault();
      submitJson(loginForm, 'api/auth_login.php');
    });

    forgotForm?.addEventListener('submit', event => {
      event.preventDefault();
      submitJson(forgotForm, 'api/auth_forgot_password.php');
    });

    resetForm?.addEventListener('submit', event => {
      event.preventDefault();
      submitJson(resetForm, 'api/auth_reset_password.php');
    });

    changeForm?.addEventListener('submit', event => {
      event.preventDefault();
      submitJson(changeForm, 'api/auth_change_password.php');
    });
  });
})();
