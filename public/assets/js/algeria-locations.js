(function () {
  'use strict';

  const WILAYAS = [
    [1,'Adrar'],[2,'Chlef'],[3,'Laghouat'],[4,'Oum El Bouaghi'],[5,'Batna'],[6,'Béjaïa'],[7,'Biskra'],[8,'Béchar'],[9,'Blida'],[10,'Bouira'],[11,'Tamanrasset'],[12,'Tébessa'],[13,'Tlemcen'],[14,'Tiaret'],[15,'Tizi Ouzou'],[16,'Alger'],[17,'Djelfa'],[18,'Jijel'],[19,'Sétif'],[20,'Saïda'],[21,'Skikda'],[22,'Sidi Bel Abbès'],[23,'Annaba'],[24,'Guelma'],[25,'Constantine'],[26,'Médéa'],[27,'Mostaganem'],[28,"M'Sila"],[29,'Mascara'],[30,'Ouargla'],[31,'Oran'],[32,'El Bayadh'],[33,'Illizi'],[34,'Bordj Bou Arréridj'],[35,'Boumerdès'],[36,'El Tarf'],[37,'Tindouf'],[38,'Tissemsilt'],[39,'El Oued'],[40,'Khenchela'],[41,'Souk Ahras'],[42,'Tipaza'],[43,'Mila'],[44,'Aïn Defla'],[45,'Naâma'],[46,'Aïn Témouchent'],[47,'Ghardaïa'],[48,'Relizane'],[49,'Timimoun'],[50,'Bordj Badji Mokhtar'],[51,'Ouled Djellal'],[52,'Béni Abbès'],[53,'In Salah'],[54,'In Guezzam'],[55,'Touggourt'],[56,'Djanet'],[57,"El M'Ghair"],[58,'El Meniaa'],[59,'Aflou'],[60,'Barika'],[61,'El Kantara'],[62,'Bir El Ater'],[63,'El Aricha'],[64,'Ksar Chellala'],[65,'Aïn Oussara'],[66,'Messaad'],[67,'Ksar El Boukhari'],[68,'Bou Saâda'],[69,'El Abiodh Sidi Cheikh']
  ];

  const DATA_URL = 'https://cdn.jsdelivr.net/gh/RachidBR/algerian-wilayas-with-municipalities@main/wilayas-with-municipalities.json';
  let datasetPromise = null;

  function normalized(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');
  }

  async function dataset() {
    if (datasetPromise) return datasetPromise;
    datasetPromise = (async () => {
      const cacheKey = 'marki.algeria.locations.v2026.69';
      try {
        const cached = localStorage.getItem(cacheKey);
        if (cached) return JSON.parse(cached);
      } catch (_) {}

      try {
        const response = await fetch(DATA_URL, { cache: 'force-cache' });
        if (!response.ok) throw new Error('dataset');
        const data = await response.json();
        try { localStorage.setItem(cacheKey, JSON.stringify(data)); } catch (_) {}
        return data;
      } catch (_) {
        return [];
      }
    })();
    return datasetPromise;
  }

  function fillWilayas(select, selectedValue = '') {
    if (!select) return;
    const selectedNormalized = normalized(selectedValue);
    select.innerHTML = '<option value="">Choisir une wilaya</option>' + WILAYAS.map(([code, name]) => {
      const selected = normalized(name) === selectedNormalized || String(code) === String(selectedValue) ? ' selected' : '';
      return `<option value="${name}" data-wilaya-code="${code}"${selected}>${String(code).padStart(2, '0')} — ${name}</option>`;
    }).join('');
  }

  async function fillCommunes(wilayaSelect, cityInput, datalist) {
    if (!wilayaSelect || !cityInput || !datalist) return;
    const option = wilayaSelect.selectedOptions[0];
    const code = Number(option?.dataset.wilayaCode || 0);
    datalist.innerHTML = '';
    if (!code) return;

    const data = await dataset();
    const wilaya = data.find(item => Number(item.wilayaCode) === code);
    const names = Array.isArray(wilaya?.communes)
      ? wilaya.communes.map(item => item.nameFr).filter(Boolean).sort((a, b) => a.localeCompare(b, 'fr'))
      : [];
    datalist.innerHTML = names.map(name => `<option value="${String(name).replace(/"/g, '&quot;')}"></option>`).join('');
  }

  function bindGroup(group) {
    if (!group || group.dataset.locationsBound === '1') return;
    group.dataset.locationsBound = '1';
    const wilaya = group.querySelector('[data-algeria-wilaya]');
    const city = group.querySelector('[data-algeria-city]');
    const list = group.querySelector('datalist');
    if (!wilaya || !city || !list) return;

    const initialWilaya = wilaya.dataset.initialValue || wilaya.value;
    fillWilayas(wilaya, initialWilaya);
    fillCommunes(wilaya, city, list);
    wilaya.addEventListener('change', () => {
      city.value = '';
      fillCommunes(wilaya, city, list);
    });
  }

  function bind(root = document) {
    root.querySelectorAll('[data-algeria-location-group]').forEach(bindGroup);
  }

  window.MarkiAlgeriaLocations = { WILAYAS, bind, fillWilayas, fillCommunes };
  document.addEventListener('DOMContentLoaded', () => bind(document));
  new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
    if (node.nodeType === Node.ELEMENT_NODE) bind(node);
  }))).observe(document.documentElement, { childList: true, subtree: true });
})();
