(function () {
  'use strict';

  function updateClock() {
    const header = document.querySelector('.header[data-timezone]');
    if (!header) return;

    const timezone = header.dataset.timezone || 'Africa/Algiers';
    const now = new Date();
    const dateElement = document.getElementById('header-current-date');
    const timeElement = document.getElementById('header-current-time');

    if (dateElement) {
      dateElement.textContent = new Intl.DateTimeFormat('fr-DZ', {
        timeZone: timezone,
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
      }).format(now);
    }

    if (timeElement) {
      timeElement.textContent = new Intl.DateTimeFormat('fr-DZ', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      }).format(now);
    }
  }

  async function changeDoctor(event) {
    const select = event.currentTarget;
    const doctorId = Number(select.value);
    if (doctorId <= 0) return;

    select.disabled = true;

    try {
      const response = await fetch('api/auth_select_doctor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ doctor_id: doctorId })
      });
      const data = await response.json();

      if (!response.ok || !data?.ok) {
        throw new Error(data?.message || 'Impossible de changer de médecin.');
      }

      location.reload();
    } catch (error) {
      const message = error.message || 'Impossible de changer de médecin.';

      if (typeof window.showToast === 'function') {
        window.showToast(message, 'error');
      } else {
        console.error(message);
      }

      select.disabled = false;
    }
  }

  function initSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    if (!sidebar || !toggleBtn) return;

    const STORAGE_KEY = 'marki_sidebar_collapsed';
    const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';

    function setCollapsed(collapsed) {
      if (collapsed) {
        document.body.classList.add('is-sidebar-collapsed');
        sidebar.classList.add('is-collapsed');
        toggleBtn.setAttribute('title', 'Agrandir le menu');
        toggleBtn.setAttribute('aria-label', 'Agrandir le menu');
        const textSpan = toggleBtn.querySelector('.sidebar__toggle-text');
        if (textSpan) textSpan.textContent = 'Agrandir le menu';
      } else {
        document.body.classList.remove('is-sidebar-collapsed');
        sidebar.classList.remove('is-collapsed');
        toggleBtn.setAttribute('title', 'Réduire le menu');
        toggleBtn.setAttribute('aria-label', 'Réduire le menu');
        const textSpan = toggleBtn.querySelector('.sidebar__toggle-text');
        if (textSpan) textSpan.textContent = 'Réduire le menu';
      }
      try {
        localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
      } catch (e) {}
    }

    if (isCollapsed) {
      setCollapsed(true);
    }

    toggleBtn.addEventListener('click', () => {
      const currentlyCollapsed = sidebar.classList.contains('is-collapsed') || document.body.classList.contains('is-sidebar-collapsed');
      setCollapsed(!currentlyCollapsed);
    });
  }

  function initGlobalTooltips() {
    let tooltipEl = document.getElementById('mk-floating-tooltip');
    if (!tooltipEl) {
      tooltipEl = document.createElement('div');
      tooltipEl.id = 'mk-floating-tooltip';
      tooltipEl.className = 'mk-floating-tooltip';
      tooltipEl.setAttribute('role', 'tooltip');
      document.body.appendChild(tooltipEl);
    }

    let activeTarget = null;
    let showTimer = null;

    function getTargetTooltipText(target) {
      if (!target || target.nodeType !== Node.ELEMENT_NODE) return null;
      const el = target.closest('[data-tooltip], .mk-tooltip, [title]');
      if (!el) return null;

      if (el.hasAttribute('title') && !el.getAttribute('data-tooltip')) {
        const titleText = el.getAttribute('title');
        if (titleText) {
          el.setAttribute('data-tooltip', titleText);
        }
        el.removeAttribute('title');
      }

      return {
        element: el,
        text: el.getAttribute('data-tooltip')
      };
    }

    function hideTooltip() {
      clearTimeout(showTimer);
      activeTarget = null;
      tooltipEl.classList.remove('is-visible');
    }

    function positionTooltip(element, text) {
      if (!element || !text) {
        hideTooltip();
        return;
      }

      tooltipEl.textContent = text;
      tooltipEl.className = 'mk-floating-tooltip';

      const rect = element.getBoundingClientRect();
      const tooltipRect = tooltipEl.getBoundingClientRect();

      let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
      const padding = 10;
      left = Math.max(padding, Math.min(window.innerWidth - tooltipRect.width - padding, left));

      const spaceAbove = rect.top;
      let top;
      if (spaceAbove >= tooltipRect.height + 10) {
        top = rect.top - tooltipRect.height - 8;
        tooltipEl.classList.add('is-top');
      } else {
        top = rect.bottom + 8;
        tooltipEl.classList.add('is-bottom');
      }

      tooltipEl.style.left = `${Math.round(left)}px`;
      tooltipEl.style.top = `${Math.round(top)}px`;
      tooltipEl.classList.add('is-visible');
    }

    document.addEventListener('mouseover', event => {
      const info = getTargetTooltipText(event.target);
      if (!info || !info.text) {
        hideTooltip();
        return;
      }

      if (activeTarget === info.element) return;
      activeTarget = info.element;

      clearTimeout(showTimer);
      showTimer = setTimeout(() => {
        if (activeTarget === info.element && document.body.contains(info.element)) {
          positionTooltip(info.element, info.text);
        }
      }, 40);
    }, { passive: true });

    document.addEventListener('mouseout', event => {
      if (activeTarget && !activeTarget.contains(event.relatedTarget)) {
        hideTooltip();
      }
    }, { passive: true });

    document.addEventListener('click', hideTooltip, { passive: true });
    window.addEventListener('scroll', hideTooltip, { passive: true });
  }

  document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    window.setInterval(updateClock, 1000);
    document
      .getElementById('header-doctor-select')
      ?.addEventListener('change', changeDoctor);

    document.querySelectorAll('.sidebar__item[role="button"]').forEach(item => {
      item.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          item.click();
        }
      });
    });

    initSidebar();
    initGlobalTooltips();
  });
})();
