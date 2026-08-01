(function () {
  'use strict';

  function digits(value) {
    return String(value ?? '').replace(/\D+/g, '');
  }

  function canonicalMobileDigits(value) {
    let clean = digits(value);

    if (clean.startsWith('00213')) clean = clean.slice(5);
    else if (clean.startsWith('213') && clean.length >= 12) clean = clean.slice(3);

    if (clean.length === 9 && /^[567]/.test(clean)) clean = `0${clean}`;

    return clean;
  }

  function localMobileDigits(value) {
    return canonicalMobileDigits(value).slice(0, 10);
  }

  function formatMobile(value) {
    const clean = localMobileDigits(value);
    const groups = [clean.slice(0, 4), clean.slice(4, 6), clean.slice(6, 8), clean.slice(8, 10)]
      .filter(Boolean);
    return groups.join(' ');
  }

  function isValidMobile(value) {
    return /^0[567]\d{8}$/.test(canonicalMobileDigits(value));
  }

  function formatAdaptivePhone(value) {
    const raw = String(value ?? '').trim();
    const clean = localMobileDigits(raw);

    if (/^0[567]/.test(clean)) return formatMobile(clean);
    return raw.replace(/[^0-9+()\-\s]/g, '').slice(0, 24);
  }

  function bindMobile(input) {
    if (!input || input.dataset.markiPhoneBound === '1') return;
    input.dataset.markiPhoneBound = '1';
    input.inputMode = 'numeric';
    input.maxLength = 13;
    input.placeholder ||= '0550 80 30 90';

    const apply = () => {
      input.value = formatMobile(input.value);
    };

    input.addEventListener('input', apply);
    input.addEventListener('blur', apply);
    apply();
  }

  function bindAdaptive(input) {
    if (!input || input.dataset.markiPhoneBound === '1') return;
    input.dataset.markiPhoneBound = '1';
    input.maxLength = 24;
    input.addEventListener('input', () => {
      input.value = formatAdaptivePhone(input.value);
    });
    input.addEventListener('blur', () => {
      input.value = formatAdaptivePhone(input.value);
    });
    input.value = formatAdaptivePhone(input.value);
  }

  function bind(root = document) {
    root.querySelectorAll('[data-dz-mobile]').forEach(bindMobile);
    root.querySelectorAll('[data-dz-phone-auto]').forEach(bindAdaptive);
  }

  window.MarkiPhone = {
    digits,
    canonicalMobileDigits,
    localMobileDigits,
    formatMobile,
    isValidMobile,
    formatAdaptivePhone,
    bind
  };

  document.addEventListener('DOMContentLoaded', () => bind(document));
  new MutationObserver(records => {
    records.forEach(record => record.addedNodes.forEach(node => {
      if (node.nodeType !== Node.ELEMENT_NODE) return;
      if (node.matches?.('[data-dz-mobile]')) bindMobile(node);
      if (node.matches?.('[data-dz-phone-auto]')) bindAdaptive(node);
      bind(node);
    }));
  }).observe(document.documentElement, { childList: true, subtree: true });
})();
