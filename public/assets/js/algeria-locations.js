(function () {
  'use strict';

  /*
  |--------------------------------------------------------------------------
  | Wilayas algériennes
  |--------------------------------------------------------------------------
  | La liste reste intégrée au projet afin que le premier menu fonctionne
  | immédiatement, même sans connexion Internet.
  |--------------------------------------------------------------------------
  */
  const WILAYAS = [
    [1, 'Adrar'], [2, 'Chlef'], [3, 'Laghouat'], [4, 'Oum El Bouaghi'],
    [5, 'Batna'], [6, 'Béjaïa'], [7, 'Biskra'], [8, 'Béchar'],
    [9, 'Blida'], [10, 'Bouira'], [11, 'Tamanrasset'], [12, 'Tébessa'],
    [13, 'Tlemcen'], [14, 'Tiaret'], [15, 'Tizi Ouzou'], [16, 'Alger'],
    [17, 'Djelfa'], [18, 'Jijel'], [19, 'Sétif'], [20, 'Saïda'],
    [21, 'Skikda'], [22, 'Sidi Bel Abbès'], [23, 'Annaba'], [24, 'Guelma'],
    [25, 'Constantine'], [26, 'Médéa'], [27, 'Mostaganem'], [28, "M'Sila"],
    [29, 'Mascara'], [30, 'Ouargla'], [31, 'Oran'], [32, 'El Bayadh'],
    [33, 'Illizi'], [34, 'Bordj Bou Arréridj'], [35, 'Boumerdès'],
    [36, 'El Tarf'], [37, 'Tindouf'], [38, 'Tissemsilt'], [39, 'El Oued'],
    [40, 'Khenchela'], [41, 'Souk Ahras'], [42, 'Tipaza'], [43, 'Mila'],
    [44, 'Aïn Defla'], [45, 'Naâma'], [46, 'Aïn Témouchent'],
    [47, 'Ghardaïa'], [48, 'Relizane'], [49, 'Timimoun'],
    [50, 'Bordj Badji Mokhtar'], [51, 'Ouled Djellal'], [52, 'Béni Abbès'],
    [53, 'In Salah'], [54, 'In Guezzam'], [55, 'Touggourt'], [56, 'Djanet'],
    [57, "El M'Ghair"], [58, 'El Meniaa'], [59, 'Aflou'], [60, 'Barika'],
    [61, 'El Kantara'], [62, 'Bir El Ater'], [63, 'El Aricha'],
    [64, 'Ksar Chellala'], [65, 'Aïn Oussara'], [66, 'Messaad'],
    [67, 'Ksar El Boukhari'], [68, 'Bou Saâda'],
    [69, 'El Abiodh Sidi Cheikh']
  ];

  const CACHE_KEY = 'marki.algeria.communes.geoalgeria.v1';
  const REMOTE_DATA_URL =
    'https://cdn.jsdelivr.net/npm/geoalgeria@1.1.1/data/ecommerce/communes.json';

  let datasetPromise = null;
  let datasetSource = 'none';

  function normalized(value) {
    return String(value ?? '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]/g, '');
  }

  function escapeAttribute(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function scriptUrl() {
    const current = document.currentScript;

    if (current?.src) {
      return current.src;
    }

    const matchingScript = [...document.scripts].find(script =>
      script.src.includes('/algeria-locations.js')
    );

    return matchingScript?.src || window.location.href;
  }

  const LOCAL_DATA_URL = new URL(
    '../data/algeria-communes.json',
    scriptUrl()
  ).toString();

  function isFlatDataset(data) {
    return Array.isArray(data)
      && data.length > 0
      && data.some(item =>
        item
        && item.wilaya_code !== undefined
        && typeof item.commune_name_fr === 'string'
      );
  }

  function isNestedDataset(data) {
    return Array.isArray(data)
      && data.length > 0
      && data.some(item =>
        item
        && item.wilayaCode !== undefined
        && Array.isArray(item.communes)
      );
  }

  function isValidDataset(data) {
    return isFlatDataset(data) || isNestedDataset(data);
  }

  async function fetchDataset(url, cacheMode) {
    const response = await fetch(url, {
      cache: cacheMode,
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`Données géographiques indisponibles (${response.status}).`);
    }

    const data = await response.json();

    if (!isValidDataset(data)) {
      throw new Error('Format des données géographiques invalide.');
    }

    return data;
  }

  function readCachedDataset() {
    try {
      const raw = localStorage.getItem(CACHE_KEY);

      if (!raw) {
        return null;
      }

      const data = JSON.parse(raw);
      return isValidDataset(data) ? data : null;
    } catch (error) {
      return null;
    }
  }

  function cacheDataset(data) {
    try {
      localStorage.setItem(CACHE_KEY, JSON.stringify(data));
    } catch (error) {
      // Le cache est facultatif. Le fichier local reste la source principale.
    }
  }

  async function dataset() {
    if (datasetPromise) {
      return datasetPromise;
    }

    datasetPromise = (async () => {
      /*
      |-----------------------------------------------------------------------
      | 1. Fichier local du projet
      |-----------------------------------------------------------------------
      | Une fois installé, il fonctionne sans Internet et en navigation privée.
      |-----------------------------------------------------------------------
      */
      try {
        const localData = await fetchDataset(LOCAL_DATA_URL, 'force-cache');
        datasetSource = 'local';
        cacheDataset(localData);
        return localData;
      } catch (localError) {
        console.info(
          '[MARKI] Fichier local des communes non disponible.',
          localError.message
        );
      }

      /*
      |-----------------------------------------------------------------------
      | 2. Copie du navigateur
      |-----------------------------------------------------------------------
      | Utile sur un appareil ayant déjà chargé les données.
      |-----------------------------------------------------------------------
      */
      const cachedData = readCachedDataset();

      if (cachedData) {
        datasetSource = 'browser-cache';
        return cachedData;
      }

      /*
      |-----------------------------------------------------------------------
      | 3. Secours Internet
      |-----------------------------------------------------------------------
      | Ce secours permet de continuer à travailler si le fichier local n'a
      | pas encore été installé. Les données sont ensuite mises en cache.
      |-----------------------------------------------------------------------
      */
      try {
        const remoteData = await fetchDataset(REMOTE_DATA_URL, 'no-store');
        datasetSource = 'remote';
        cacheDataset(remoteData);
        return remoteData;
      } catch (remoteError) {
        console.warn(
          '[MARKI] Impossible de charger les communes.',
          remoteError.message
        );
        datasetSource = 'none';
        return [];
      }
    })();

    return datasetPromise;
  }

  function communeNamesForWilaya(data, code) {
    let names = [];

    if (isFlatDataset(data)) {
      names = data
        .filter(item => Number(item.wilaya_code) === Number(code))
        .map(item => item.commune_name_fr)
        .filter(Boolean);
    } else if (isNestedDataset(data)) {
      const wilaya = data.find(
        item => Number(item.wilayaCode) === Number(code)
      );

      names = Array.isArray(wilaya?.communes)
        ? wilaya.communes
          .map(item => item.nameFr || item.name_fr || item.name)
          .filter(Boolean)
        : [];
    }

    return [...new Set(names)]
      .sort((left, right) => left.localeCompare(right, 'fr'));
  }

  function fillWilayas(select, selectedValue = '') {
    if (!select) {
      return;
    }

    const selectedNormalized = normalized(selectedValue);

    select.innerHTML = [
      '<option value="">Choisir une wilaya</option>',
      ...WILAYAS.map(([code, name]) => {
        const selected =
          normalized(name) === selectedNormalized
          || String(code) === String(selectedValue)
            ? ' selected'
            : '';

        return `
          <option
            value="${escapeAttribute(name)}"
            data-wilaya-code="${code}"
            ${selected}
          >
            ${String(code).padStart(2, '0')} — ${escapeAttribute(name)}
          </option>
        `;
      })
    ].join('');
  }

  function setLocationStatus(group, text, state = '') {
    let status = group.querySelector('[data-algeria-location-status]');

    if (!status) {
      status = document.createElement('small');
      status.dataset.algeriaLocationStatus = '1';
      status.className = 'marki-location-status';
      status.setAttribute('aria-live', 'polite');
      group.append(status);
    }

    status.textContent = text;
    status.classList.toggle('is-warning', state === 'warning');
  }

  async function fillCommunes(wilayaSelect, cityInput, datalist, group) {
    if (!wilayaSelect || !cityInput || !datalist) {
      return;
    }

    const requestId = String(Date.now()) + Math.random();
    group.dataset.communesRequestId = requestId;

    const option = wilayaSelect.selectedOptions[0];
    const code = Number(option?.dataset.wilayaCode || 0);

    datalist.innerHTML = '';

    if (!code) {
      cityInput.placeholder = 'Choisir d’abord une wilaya';
      setLocationStatus(group, '');
      return;
    }

    cityInput.placeholder = 'Chargement des communes…';
    setLocationStatus(group, 'Chargement des communes…');

    const data = await dataset();

    if (group.dataset.communesRequestId !== requestId) {
      return;
    }

    const names = communeNamesForWilaya(data, code);

    datalist.innerHTML = names
      .map(name => `<option value="${escapeAttribute(name)}"></option>`)
      .join('');

    cityInput.placeholder = names.length > 0
      ? 'Choisir ou saisir une commune'
      : 'Saisir la commune manuellement';

    if (names.length > 0) {
      setLocationStatus(group, '');
      return;
    }

    setLocationStatus(
      group,
      'La liste locale des communes est indisponible. La saisie manuelle reste possible.',
      'warning'
    );
  }

  function bindGroup(group) {
    if (!group || group.dataset.locationsBound === '1') {
      return;
    }

    const wilaya = group.querySelector('[data-algeria-wilaya]');
    const city = group.querySelector('[data-algeria-city]');
    const list = group.querySelector('datalist');

    if (!wilaya || !city || !list) {
      return;
    }

    group.dataset.locationsBound = '1';

    const initialWilaya = wilaya.dataset.initialValue || wilaya.value;

    fillWilayas(wilaya, initialWilaya);
    fillCommunes(wilaya, city, list, group);

    wilaya.addEventListener('change', () => {
      city.value = '';
      fillCommunes(wilaya, city, list, group);
    });
  }

  function bind(root = document) {
    if (root.matches?.('[data-algeria-location-group]')) {
      bindGroup(root);
    }

    root
      .querySelectorAll?.('[data-algeria-location-group]')
      .forEach(bindGroup);
  }

  window.MarkiAlgeriaLocations = {
    WILAYAS,
    bind,
    fillWilayas,
    fillCommunes,
    getDatasetSource: () => datasetSource,
    localDataUrl: LOCAL_DATA_URL
  };

  document.addEventListener('DOMContentLoaded', () => bind(document));

  new MutationObserver(records => {
    records.forEach(record => {
      record.addedNodes.forEach(node => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          bind(node);
        }
      });
    });
  }).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
})();
