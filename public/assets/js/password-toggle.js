(function () {
  'use strict';

  const eyeOpen = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>';
  const eyeClosed = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.1A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3 3.7M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.8-.4 4-1M9.8 9.8a3.1 3.1 0 0 0 4.4 4.4"/></svg>';

  function enhance(input) {
    if (!input || input.dataset.passwordToggle === '1') return;
    input.dataset.passwordToggle = '1';

    const wrapper = document.createElement('span');
    wrapper.className = 'password-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.append(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-label', 'Afficher le mot de passe');
    button.setAttribute('aria-pressed', 'false');
    button.innerHTML = eyeOpen;
    wrapper.append(button);

    button.addEventListener('click', () => {
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
      button.setAttribute('aria-pressed', visible ? 'false' : 'true');
      button.innerHTML = visible ? eyeOpen : eyeClosed;
      input.focus({ preventScroll: true });
      input.setSelectionRange?.(input.value.length, input.value.length);
    });
  }

  function bind(root = document) {
    root.querySelectorAll('input[type="password"]').forEach(enhance);
  }

  window.MarkiPasswordToggle = { bind };

  document.addEventListener('DOMContentLoaded', () => bind(document));
  new MutationObserver(records => {
    records.forEach(record => record.addedNodes.forEach(node => {
      if (node.nodeType !== Node.ELEMENT_NODE) return;
      if (node.matches?.('input[type="password"]')) enhance(node);
      bind(node);
    }));
  }).observe(document.documentElement, { childList: true, subtree: true });
})();
