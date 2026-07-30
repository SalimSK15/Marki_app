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
  });
})();
