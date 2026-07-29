(function () {
  'use strict';

  function message(text, type = 'error') {
    const element = document.getElementById('auth-message');
    if (!element) return;

    element.textContent = text;
    element.className = `auth-message is-${type}`;
  }

  async function submitJson(form, endpoint) {
    const button = form.querySelector('button[type="submit"]');
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.remember = form.querySelector('[name="remember"]')?.checked || false;

    message('');
    if (button) button.disabled = true;

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await response.json();

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
        }, 250);
      }
    } catch (error) {
      message(error.message || 'Une erreur est survenue.');
    } finally {
      if (button) button.disabled = false;
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
