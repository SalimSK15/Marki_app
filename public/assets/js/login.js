(function () {
  'use strict';

  function message(text, type = 'error') {
    const element = document.getElementById('auth-message');
    if (!element) return;

    element.textContent = text;
    element.className = 'auth-message';

    if (text && type) {
      element.classList.add(`is-${type}`);
    }
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

  function normalizeClinicSlug(value) {
    return String(value || '')
      .trim()
      .toLocaleLowerCase('fr')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function clinicLoginUrl(slug) {
    const url = new URL('login.php', window.location.href);
    url.searchParams.set('clinic', slug);
    return url.toString();
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

  function bindClinicLookupForm(form) {
    if (!form) {
      return;
    }

    form.addEventListener('submit', event => {
      event.preventDefault();

      const input = form.querySelector('[name="clinic_code"]');
      const slug = normalizeClinicSlug(input?.value);

      if (!slug) {
        message('Le code de la structure est obligatoire.');
        input?.focus();
        return;
      }

      if (input) {
        input.value = slug;
      }

      localStorage.setItem('marki.clinicSlug', slug);
      location.href = clinicLoginUrl(slug);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const clinicLookupForm = document.getElementById('clinic-lookup-form');
    const forgotForm = document.getElementById('forgot-password-form');
    const resetForm = document.getElementById('reset-password-form');
    const changeForm = document.getElementById('change-password-form');

    const clinicInput = document.querySelector('[name="clinic_slug"]');
    const rememberedSlug = localStorage.getItem('marki.clinicSlug') || '';
    const clinicWasRequested = document.body.dataset.clinicRequested === '1';
    const query = new URLSearchParams(window.location.search);
    const forceStructureLookup = query.get('change') === '1';

    if (forceStructureLookup) {
      localStorage.removeItem('marki.clinicSlug');
    }

    /*
    |--------------------------------------------------------------------------
    | Connexion ouverte sans code dans l'URL
    |--------------------------------------------------------------------------
    | Sur un appareil déjà utilisé, on reprend automatiquement le dernier code.
    | En navigation privée, le formulaire de secours reste visible.
    |--------------------------------------------------------------------------
    */
    if (
      clinicLookupForm
      && !clinicWasRequested
      && !forceStructureLookup
      && rememberedSlug
    ) {
      location.replace(clinicLoginUrl(rememberedSlug));
      return;
    }

    if (clinicInput?.value) {
      localStorage.setItem('marki.clinicSlug', clinicInput.value);
    }

    const activeClinicSlug = clinicInput?.value || rememberedSlug || '';

    const forgotLink = document.querySelector('a[href^="forgot-password.php"]');

    if (forgotLink && activeClinicSlug) {
      forgotLink.href = `forgot-password.php?clinic=${encodeURIComponent(activeClinicSlug)}`;
    }

    bindClinicLookupForm(clinicLookupForm);

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
