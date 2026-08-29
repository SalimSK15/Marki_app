/**
 * MARKI — mouvement et etats de chargement
 * ============================================================
 * Ce fichier n'appelle aucune API et ne modifie aucune donnee.
 * Il observe le document et ajoute les classes d'animation
 * definies dans design-system/motion.css.
 *
 * Consequence : la logique metier de app.js reste inchangee.
 * Si ce fichier est retire, l'application fonctionne toujours,
 * simplement sans animation.
 * ============================================================
 */
(function () {
  'use strict';

  const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------
     1. COMPTEURS
     Les nombres defilent jusqu'a leur nouvelle valeur au lieu
     de sauter. La duree est courte et bornee : sur un grand
     ecart, on accelere plutot que d'allonger l'attente.
     --------------------------------------------------------- */
  const COUNTER_IDS = [
    'counter-waiting',
    'counter-absent',
    'counter-done',
    'counter-canceled',
    'day-list-results-count'
  ];

  function animateNumber(element, from, to) {
    if (REDUCED || from === to) {
      return;
    }

    const duration = Math.min(680, 220 + Math.abs(to - from) * 26);
    const start = performance.now();

    element.classList.add('is-changing');

    function step(now) {
      const progress = Math.min(1, (now - start) / duration);
      // Sortie douce : rapide au debut, freine a l'arrivee.
      const eased = 1 - Math.pow(1 - progress, 3);
      element.textContent = String(Math.round(from + (to - from) * eased));

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        element.textContent = String(to);
        element.classList.remove('is-changing');
      }
    }

    requestAnimationFrame(step);
  }

  function watchCounter(element) {
    if (!element || element.dataset.mkCounterWatched === '1') {
      return;
    }

    element.dataset.mkCounterWatched = '1';
    element.classList.add('mk-count');

    let previous = parseInt(element.textContent, 10);
    previous = Number.isNaN(previous) ? 0 : previous;

    const observer = new MutationObserver(() => {
      const raw = element.textContent.trim();
      const match = raw.match(/-?\d+/);

      if (!match) {
        return;
      }

      const next = parseInt(match[0], 10);

      if (next === previous || element.dataset.mkAnimating === '1') {
        previous = next;
        return;
      }

      // On neutralise l'observateur pendant qu'on ecrit nous-memes.
      element.dataset.mkAnimating = '1';
      observer.disconnect();

      // Le libelle « 12 patients » garde son texte : on n'anime
      // que lorsque la cellule ne contient qu'un nombre.
      if (raw === match[0]) {
        animateNumber(element, previous, next);
      }

      previous = next;

      window.setTimeout(() => {
        element.dataset.mkAnimating = '0';
        observer.observe(element, { childList: true, characterData: true, subtree: true });
      }, 720);
    });

    observer.observe(element, { childList: true, characterData: true, subtree: true });
  }

  /* ---------------------------------------------------------
     2. SQUELETTES
     Tant que la liste charge, on montre la forme du tableau
     plutot que le mot « Chargement ».
     --------------------------------------------------------- */
  function skeletonRows(columns, rows) {
    const widths = ['30%', '70%', '55%', '40%', '60%', '45%'];
    let html = '';

    for (let r = 0; r < rows; r += 1) {
      html += '<tr class="mk-skeleton-row" aria-hidden="true">';
      for (let c = 0; c < columns; c += 1) {
        html += `<td><span class="mk-skeleton mk-skeleton--text" style="width:${widths[c % widths.length]}"></span></td>`;
      }
      html += '</tr>';
    }

    return html;
  }

  function replaceLoadingCells(root) {
    const cells = (root || document).querySelectorAll(
      '.table-empty-state, .v1-empty-cell'
    );

    cells.forEach(cell => {
      const text = cell.textContent.trim().toLowerCase();

      if (!text.startsWith('chargement')) {
        return;
      }

      const body = cell.closest('tbody');
      const table = cell.closest('table');

      if (!body || !table) {
        return;
      }

      const columns = table.querySelectorAll('thead th').length || 6;

      // Le texte reste lisible par les lecteurs d'ecran.
      body.innerHTML =
        `<tr class="mk-sr-loading"><td colspan="${columns}">` +
        '<span class="v1-sr-only sr-only">Chargement en cours</span>' +
        '</td></tr>' +
        skeletonRows(columns, 6);
    });
  }

  /* ---------------------------------------------------------
     3. LIGNES DE LA FILE
     - arrivee en cascade au premier rendu
     - surlignage cyan des lignes reellement nouvelles
     --------------------------------------------------------- */
  const seenEntries = new Map();

  function markTableChanges(body) {
    const key = body.id || 'default';
    const known = seenEntries.get(key);
    const rows = body.querySelectorAll('tr[data-entry-id]');

    if (!rows.length) {
      return;
    }

    const current = new Set();
    rows.forEach(row => current.add(row.dataset.entryId));

    if (!known) {
      // Premier affichage : cascade d'arrivee, pas de surlignage.
      if (!REDUCED) {
        body.classList.add('mk-stagger');
        rows.forEach(row => row.classList.add('mk-enter'));
        window.setTimeout(() => {
          body.classList.remove('mk-stagger');
          rows.forEach(row => row.classList.remove('mk-enter'));
        }, 700);
      }
      seenEntries.set(key, current);
      return;
    }

    if (!REDUCED) {
      rows.forEach(row => {
        if (!known.has(row.dataset.entryId)) {
          row.classList.add('mk-just-changed');
          window.setTimeout(() => row.classList.remove('mk-just-changed'), 1000);
        }
      });
    }

    seenEntries.set(key, current);
  }

  /* ---------------------------------------------------------
     4. SIGNAL DE VIE
     Un point qui bat quand la liste du jour est active.
     Il disparait des que la liste est en pause ou cloturee.
     --------------------------------------------------------- */
  function syncLiveDot() {
    const badge = document.getElementById('day-status-badge');

    if (!badge) {
      return;
    }

    const isOpen = badge.classList.contains('list-status-badge--open');
    let dot = badge.querySelector('.mk-live-dot');

    if (isOpen && !dot) {
      dot = document.createElement('span');
      dot.className = 'mk-live-dot';
      badge.prepend(dot);
    } else if (!isOpen && dot) {
      dot.remove();
    }
  }

  /* ---------------------------------------------------------
     5. BOUTONS QUI TRAVAILLENT
     Un bouton desactive pendant un envoi affiche un anneau.
     --------------------------------------------------------- */
  function watchBusyButtons() {
    document.addEventListener('submit', event => {
      const form = event.target;

      if (!(form instanceof HTMLFormElement)) {
        return;
      }

      const submit = form.querySelector('button[type="submit"]:not([disabled])');

      if (!submit || REDUCED) {
        return;
      }

      submit.classList.add('mk-is-busy');

      // Filet de securite : si la requete echoue sans reponse,
      // le bouton redevient utilisable.
      window.setTimeout(() => submit.classList.remove('mk-is-busy'), 8000);
    }, true);
  }

  /* ---------------------------------------------------------
     Observation globale
     --------------------------------------------------------- */
  function scan(root) {
    COUNTER_IDS.forEach(id => watchCounter(document.getElementById(id)));
    replaceLoadingCells(root);
    syncLiveDot();

    document.querySelectorAll('tbody[id]').forEach(body => {
      if (body.querySelector('tr[data-entry-id]')) {
        markTableChanges(body);
      }
    });
  }

  function start() {
    scan(document);
    watchBusyButtons();

    const main = document.getElementById('main-content') || document.body;

    const observer = new MutationObserver(mutations => {
      let touched = false;

      for (const mutation of mutations) {
        if (mutation.type === 'childList' && mutation.addedNodes.length) {
          touched = true;
          break;
        }
      }

      if (touched) {
        scan(main);
      }
    });

    observer.observe(main, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
