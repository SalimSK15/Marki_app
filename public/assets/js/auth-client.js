(function () {
  'use strict';

  const originalFetch = window.fetch.bind(window);

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  function getBasePath() {
    return document.querySelector('meta[name="marki-base-path"]')?.content || '';
  }

  window.markiAuth = {
    csrfToken: getCsrfToken,
    basePath: getBasePath
  };

  window.fetch = async function (input, init = {}) {
    const method = String(init.method || 'GET').toUpperCase();
    const headers = new Headers(init.headers || {});

    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      const token = getCsrfToken();
      if (token && !headers.has('X-CSRF-Token')) {
        headers.set('X-CSRF-Token', token);
      }
    }

    const response = await originalFetch(input, {
      ...init,
      headers,
      credentials: init.credentials || 'same-origin'
    });

    if (response.status === 401) {
      const basePath = getBasePath();
      if (!location.pathname.endsWith('/login.php')) {
        location.href = `${basePath}/login.php`;
      }
    }

    if (response.status === 403) {
      const clone = response.clone();
      try {
        const data = await clone.json();
        if (data?.error_code === 'PASSWORD_CHANGE_REQUIRED') {
          location.href = `${getBasePath()}/change-password.php`;
        }
      } catch (_) {
        // La réponse originale reste disponible pour le code appelant.
      }
    }

    return response;
  };
})();
