const ADD_PATIENT_API_URL = '/Marki_app/Partie_medecin/public/api/queue_add_patient.php';
const UPDATE_QUEUE_STATUS_API_URL = '/Marki_app/Partie_medecin/public/api/queue_update_status.php';
const UPDATE_PATIENT_API_URL = '/Marki_app/Partie_medecin/public/api/queue_update_patient.php';
const TOGGLE_QUEUE_STATUS_API_URL = '/Marki_app/Partie_medecin/public/api/queue_toggle_status.php';

/*
|--------------------------------------------------------------------------
| État temporaire de confirmation d’un téléphone partagé
|--------------------------------------------------------------------------
| Le premier submit affiche un avertissement.
| Le deuxième submit confirme l’utilisation du numéro partagé.
|--------------------------------------------------------------------------
*/
let pendingSharedPhonePayload = null;
let pendingSharedPhoneMode = null;

/*
|--------------------------------------------------------------------------
| Récupérer les éléments de la modal patient
|--------------------------------------------------------------------------
*/
function getAddPatientModalElements() {
  return {
    modal: document.getElementById('addPatientModal'),
    openBtn: document.getElementById('openAddPatientModalBtn'),
    closeBtn: document.getElementById('closeAddPatientModalBtn'),
    cancelBtn: document.getElementById('cancelAddPatientBtn'),
    form: document.getElementById('addPatientForm'),
    formModeInput: document.getElementById('addPatientFormMode'),
    entryIdInput: document.getElementById('addPatientEntryId'),
    messageBox: document.getElementById('addPatientFormMessage'),
    submitBtn: document.getElementById('submitAddPatientBtn'),
    titleEl: document.getElementById('addPatientModalTitle'),
    fullNameInput: document.getElementById('addPatientFullName'),
    phoneInput: document.getElementById('addPatientPhone'),
    birthDateInput: document.getElementById('addPatientBirthDate'),
    backdrop: document.querySelector('[data-close-add-patient-modal]'),
    sharedPhoneConfirmBox: document.getElementById(
      'sharedPhoneConfirmBox'
    ),
    sharedPhoneConfirmText: document.getElementById(
      'sharedPhoneConfirmText'
    )
  };
}
/*
|--------------------------------------------------------------------------
| Normaliser un nom de personne côté front
|--------------------------------------------------------------------------
| Objectifs :
| - enlever les espaces inutiles
| - éviter les doubles espaces
| - mettre une casse propre :
|   première lettre de chaque mot en majuscule, le reste en minuscule
|--------------------------------------------------------------------------
|
| Pourquoi le faire aussi côté front ?
| - meilleure UX
| - données plus propres dès la saisie
| - cohérence visuelle immédiate
|--------------------------------------------------------------------------
*/
function normalizePersonName(value) {
  if (!value) return '';

  return String(value)
    .trim()
    .replace(/\s+/g, ' ')
    .split(' ')
    .map(word => {
      const lower = word.toLowerCase();
      return lower.charAt(0).toUpperCase() + lower.slice(1);
    })
    .join(' ');
}
/*
|--------------------------------------------------------------------------
| Normaliser un numéro de téléphone côté front
|--------------------------------------------------------------------------
| Exemples :
| +213 551 70 07 10  -> 0551700710
| 00213 551700710    -> 0551700710
|--------------------------------------------------------------------------
*/
function normalizePhoneNumber(value) {
  let digits = String(value ?? '').replace(/\D+/g, '');

  let hadAlgerianCountryCode = false;

  if (digits.startsWith('00213')) {
    digits = digits.slice(5);
    hadAlgerianCountryCode = true;
  } else if (
    digits.startsWith('213')
    && digits.length >= 12
  ) {
    digits = digits.slice(3);
    hadAlgerianCountryCode = true;
  }

  if (
    hadAlgerianCountryCode
    && digits.length === 9
  ) {
    digits = `0${digits}`;
  }

  return digits;
}

/*
|--------------------------------------------------------------------------
| Vérifier le format général du téléphone
|--------------------------------------------------------------------------
*/
function isValidPhoneNumber(phone) {
  return /^\d{8,15}$/.test(phone);
}
/*
|--------------------------------------------------------------------------
| Lire une réponse HTTP en JSON robuste
|--------------------------------------------------------------------------
| Pourquoi cette fonction ?
| - si le backend renvoie du HTML / warning PHP / notice,
|   response.json() casse brutalement
| - ici on lit d'abord le texte brut, puis on parse le JSON
|--------------------------------------------------------------------------
*/
async function parseJsonResponseSafely(response) {
  const rawText = await response.text();

  try {
    return JSON.parse(rawText);
  } catch (error) {
    console.error('Réponse backend non JSON :', rawText);
    throw new Error('Le serveur a renvoyé une réponse invalide. Vérifie l’endpoint PHP.');
  }
}
/*
|--------------------------------------------------------------------------
| Ouvrir la modal d'ajout patient
|--------------------------------------------------------------------------
| Cette fonction :
| - vérifie que la modal existe
| - vérifie aussi que le bouton n'est pas désactivé
| - ouvre ensuite la modal et reset le formulaire
|--------------------------------------------------------------------------
|
| Pourquoi vérifier le bouton ici aussi ?
| - sécurité UX supplémentaire
| - évite une ouverture accidentelle si quelqu’un appelle la fonction
|   manuellement depuis la console
|--------------------------------------------------------------------------
*/
function openAddPatientModal() {
  const {
    modal,
    fullNameInput,
    messageBox,
    form,
    openBtn
  } = getAddPatientModalElements();

  /*
  |--------------------------------------------------------------
  | Si la modal n'existe pas, on ne fait rien
  |--------------------------------------------------------------
  */
  if (!modal) return;

  /*
  |--------------------------------------------------------------
  | Si le bouton est désactivé, on bloque l'ouverture
  |--------------------------------------------------------------
  | Cela signifie en pratique que la liste est fermée.
  */
  if (openBtn && openBtn.disabled) {
    return;
  }

  /*
  |--------------------------------------------------------------
  | Ouvrir visuellement la modal
  |--------------------------------------------------------------
  */
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');

  /*
  |--------------------------------------------------------------
  | Réinitialiser le message de formulaire
  |--------------------------------------------------------------
  */
  if (messageBox) {
    messageBox.textContent = '';
    messageBox.className = 'marki-form__message';
  }

  /*
  |--------------------------------------------------------------
  | Réinitialiser le formulaire
  |--------------------------------------------------------------
  */
  if (form) {
    form.reset();
    form.dataset.submitting = 'false';
  }
  resetSharedPhoneConfirmation();
  setPatientModalMode('create');
  /*
  |--------------------------------------------------------------
  | Focus automatique sur le nom complet
  |--------------------------------------------------------------
  */
  if (fullNameInput) {
    setTimeout(() => fullNameInput.focus(), 0);
  }
}
/*
|--------------------------------------------------------------------------
| Fermer la modal patient
|--------------------------------------------------------------------------
*/
function closeAddPatientModal() {
  const { modal, messageBox, form, submitBtn } = getAddPatientModalElements();

  if (!modal) return;

  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');

  if (messageBox) {
    messageBox.textContent = '';
    messageBox.className = 'marki-form__message';
  }

  if (form) {
    form.reset();
    form.dataset.submitting = 'false';
  }

  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Ajouter le patient';
  }

  resetSharedPhoneConfirmation();
  setPatientModalMode('create');
}
/*
|--------------------------------------------------------------------------
| Fermer la modal patient avec la touche Échap
|--------------------------------------------------------------------------
| La fonction est enregistrée une seule fois pour toute l’application.
| Elle récupère toujours la modal actuellement présente dans le DOM.
|--------------------------------------------------------------------------
*/
function handlePatientModalKeydown(event) {
  if (event.key !== 'Escape') {
    return;
  }

  const { modal } = getAddPatientModalElements();

  if (!modal?.classList.contains('is-open')) {
    return;
  }

  closeAddPatientModal();
}
/*
|--------------------------------------------------------------------------
| Mettre à jour le message utilisateur dans la modal
|--------------------------------------------------------------------------
*/
function setAddPatientFormMessage(message, type = 'error') {
  const { messageBox } = getAddPatientModalElements();
  if (!messageBox) return;

  messageBox.textContent = message;
  messageBox.className = `marki-form__message is-${type}`;
}
/*
|--------------------------------------------------------------------------
| Texte normal du bouton selon le mode
|--------------------------------------------------------------------------
*/
function getPatientDefaultSubmitLabel(mode) {
  return mode === 'edit'
    ? 'Mettre à jour'
    : 'Ajouter le patient';
}

/*
|--------------------------------------------------------------------------
| Texte explicite du bouton après avertissement familial
|--------------------------------------------------------------------------
*/
function getSharedPhoneSubmitLabel(mode) {
  return mode === 'edit'
    ? 'Confirmer et mettre à jour'
    : 'Confirmer et ajouter';
}

/*
|--------------------------------------------------------------------------
| Réinitialiser l’avertissement de téléphone partagé
|--------------------------------------------------------------------------
*/
function resetSharedPhoneConfirmation() {
  const {
    sharedPhoneConfirmBox,
    sharedPhoneConfirmText,
    submitBtn,
    formModeInput
  } = getAddPatientModalElements();

  pendingSharedPhonePayload = null;
  pendingSharedPhoneMode = null;

  if (sharedPhoneConfirmBox) {
    sharedPhoneConfirmBox.hidden = true;
  }

  if (sharedPhoneConfirmText) {
    sharedPhoneConfirmText.textContent = '';
  }

  if (submitBtn) {
    /*
    |--------------------------------------------------------------------------
    | Retirer l’apparence orange du bouton de confirmation
    |--------------------------------------------------------------------------
    */
    submitBtn.classList.remove('is-confirmation');

    const mode = formModeInput?.value || 'create';

    submitBtn.textContent = getPatientDefaultSubmitLabel(mode);
  }
}
/*
|--------------------------------------------------------------------------
| Afficher l’avertissement de téléphone familial
|--------------------------------------------------------------------------
| Le bouton principal devient le bouton de confirmation.
|--------------------------------------------------------------------------
*/
function showSharedPhoneConfirmation(message, mode, payload) {
  const {
    sharedPhoneConfirmBox,
    sharedPhoneConfirmText,
    submitBtn
  } = getAddPatientModalElements();

  if (
    !sharedPhoneConfirmBox
    || !sharedPhoneConfirmText
    || !submitBtn
  ) {
    return;
  }

  /*
  |--------------------------------------------------------------------------
  | Préparer le payload du deuxième clic
  |--------------------------------------------------------------------------
  */
  pendingSharedPhonePayload = {
    ...payload,
    allow_shared_phone: true
  };

  pendingSharedPhoneMode = mode;

  sharedPhoneConfirmText.textContent = message;
  sharedPhoneConfirmBox.hidden = false;

  /*
  |--------------------------------------------------------------------------
  | Faire passer le bouton principal en mode confirmation orange
  |--------------------------------------------------------------------------
  */
  submitBtn.classList.add('is-confirmation');
  submitBtn.textContent = getSharedPhoneSubmitLabel(mode);
}
/*
|--------------------------------------------------------------------------
| Effacer les messages quand l’utilisateur modifie un champ
|--------------------------------------------------------------------------
| Modifier une valeur annule aussi la confirmation précédente.
|--------------------------------------------------------------------------
*/
function clearPatientFormFeedback() {
  const { messageBox } = getAddPatientModalElements();

  if (messageBox) {
    messageBox.textContent = '';
    messageBox.className = 'marki-form__message';
  }

  resetSharedPhoneConfirmation();
}
/*
|--------------------------------------------------------------------------
| Soumettre la modal patient
|--------------------------------------------------------------------------
| Première requête :
| - ajout normal
| - ou réponse serveur demandant une confirmation
|
| Deuxième requête :
| - envoie allow_shared_phone = true
|--------------------------------------------------------------------------
*/
async function handleAddPatientSubmit(event) {
  event.preventDefault();

  const {
    form,
    submitBtn,
    fullNameInput,
    phoneInput,
    birthDateInput,
    formModeInput,
    entryIdInput
  } = getAddPatientModalElements();

  if (
    !form
    || !submitBtn
    || !fullNameInput
    || !phoneInput
  ) {
    return;
  }

  if (form.dataset.submitting === 'true') {
    return;
  }

  const mode = formModeInput?.value || 'create';
  const fullName = normalizePersonName(fullNameInput.value);
  const phone = normalizePhoneNumber(phoneInput.value);
  const birthDate = birthDateInput?.value.trim() || '';

  fullNameInput.value = fullName;
  /*
  |--------------------------------------------------------------------------
  | Afficher le téléphone normalisé dans le formulaire
  |--------------------------------------------------------------------------
  */
  phoneInput.value = phone;
  /*
  |--------------------------------------------------------------------------
  | Validation du nom
  |--------------------------------------------------------------------------
  */
  if (!fullName) {
    setAddPatientFormMessage(
      'Le nom complet est obligatoire.',
      'error'
    );

    fullNameInput.focus();
    return;
  }

  /*
  |--------------------------------------------------------------------------
  | Validation du téléphone
  |--------------------------------------------------------------------------
  */
  if (!phone) {
    setAddPatientFormMessage('Le numéro de téléphone est obligatoire.', 'error');

    phoneInput.focus();
    return;
  }
  /*
  |--------------------------------------------------------------------------
  | Vérifier le nombre de chiffres
  |--------------------------------------------------------------------------
  */
  if (!isValidPhoneNumber(phone)) {
    setAddPatientFormMessage(
      'Le numéro de téléphone doit contenir entre 8 et 15 chiffres.',
      'error'
    );

    phoneInput.focus();
    return;
  }
  /*
  |--------------------------------------------------------------------------
  | Construire les données normales du formulaire
  |--------------------------------------------------------------------------
  */
  const currentPayload = {
    full_name: fullName,
    phone,
    birth_date: birthDate || null,
    source: 'secretary'
  };

  if (mode === 'edit' && entryIdInput?.value) {
    currentPayload.entry_id = Number(entryIdInput.value);
  }

  /*
  |--------------------------------------------------------------------------
  | Si un avertissement a déjà été affiché, le deuxième clic utilise
  | le payload confirmé avec allow_shared_phone = true.
  |--------------------------------------------------------------------------
  */
  const isSharedPhoneConfirmation =
    pendingSharedPhonePayload !== null
    && pendingSharedPhoneMode === mode;

  const payloadToSend = isSharedPhoneConfirmation
    ? pendingSharedPhonePayload
    : currentPayload;

  form.dataset.submitting = 'true';
  submitBtn.disabled = true;

  submitBtn.textContent = isSharedPhoneConfirmation
    ? 'Confirmation...'
    : mode === 'edit'
      ? 'Mise à jour...'
      : 'Ajout en cours...';

  setAddPatientFormMessage('', 'error');

  try {
    const { response, data } = await submitPatientModalPayload(
      mode,
      payloadToSend
    );

    /*
    |--------------------------------------------------------------------------
    | Le numéro appartient déjà à un autre nom
    |--------------------------------------------------------------------------
    | On affiche un avertissement orange et on attend un deuxième clic.
    |--------------------------------------------------------------------------
    */
    if (
      !response.ok
      && data?.error_code === 'PHONE_SHARED_CONFIRMATION_REQUIRED'
    ) {
      showSharedPhoneConfirmation(
        data.message,
        mode,
        currentPayload
      );

      return;
    }

    /*
    |--------------------------------------------------------------------------
    | Erreur normale : doublon de nom, validation, liste fermée...
    |--------------------------------------------------------------------------
    */
    if (!response.ok) {
      resetSharedPhoneConfirmation();

      setAddPatientFormMessage(
        data?.message || 'Impossible de traiter la demande.',
        'error'
      );

      return;
    }

    resetSharedPhoneConfirmation();

    setAddPatientFormMessage(
      data?.message || (
        mode === 'edit'
          ? 'Patient mis à jour avec succès.'
          : 'Patient ajouté avec succès.'
      ),
      'success'
    );

    await loadDashboardData();

    setTimeout(() => {
      closeAddPatientModal();
    }, 400);
  } catch (error) {
    console.error('Erreur formulaire patient :', error);

    resetSharedPhoneConfirmation();

    setAddPatientFormMessage(
      error.message || 'Une erreur réseau est survenue.',
      'error'
    );
  } finally {
    form.dataset.submitting = 'false';
    submitBtn.disabled = false;

    if (
      pendingSharedPhonePayload !== null
      && pendingSharedPhoneMode === mode
    ) {
      submitBtn.textContent = getSharedPhoneSubmitLabel(mode);
    } else {
      submitBtn.textContent = getPatientDefaultSubmitLabel(mode);
    }
  }
}

async function updateQueueEntryStatus(entryId, status) {
    const response = await fetch(UPDATE_QUEUE_STATUS_API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            entry_id: entryId,
            status: status
        })
    });

    const data = await parseJsonResponseSafely(response);

    if (!response.ok) {
        throw new Error(data?.message || 'Impossible de mettre à jour le statut.');
    }

    return data;
}

/*
|--------------------------------------------------------------------------
| Basculer le statut de la liste du jour
|--------------------------------------------------------------------------
| Cette fonction appelle l'API backend qui fait :
| - open   -> closed
| - closed -> open
|
| Pourquoi une fonction dédiée ?
| - code plus lisible
| - réutilisable
| - plus simple à maintenir
|--------------------------------------------------------------------------
*/
async function toggleTodayQueueStatus() {
    const response = await fetch(TOGGLE_QUEUE_STATUS_API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },

        /*
        |--------------------------------------------------------------
        | Ici on n’a pas besoin d’envoyer de body pour la V1
        |--------------------------------------------------------------
        | Le backend sait déjà retrouver la queue du jour grâce au
        | contexte dev + à la date du jour.
        */
        body: JSON.stringify({})
    });

    const data = await parseJsonResponseSafely(response);

    /*
    |--------------------------------------------------------------
    | Si l'API répond en erreur, on remonte un vrai message utile
    |--------------------------------------------------------------
    */
    if (!response.ok) {
        throw new Error(data?.message || 'Impossible de modifier le statut de la liste.');
    }

    return data;
}
/*
|--------------------------------------------------------------------------
| Brancher les événements de la modal patient
|--------------------------------------------------------------------------
*/
function bindAddPatientModalEvents() {
  const {
    openBtn,
    closeBtn,
    cancelBtn,
    backdrop,
    form,
    fullNameInput,
    phoneInput,
    birthDateInput
  } = getAddPatientModalElements();

  openBtn?.addEventListener(
    'click',
    openAddPatientModal
  );

  closeBtn?.addEventListener(
    'click',
    closeAddPatientModal
  );

  cancelBtn?.addEventListener(
    'click',
    closeAddPatientModal
  );

  backdrop?.addEventListener(
    'click',
    closeAddPatientModal
  );

  form?.addEventListener(
    'submit',
    handleAddPatientSubmit
  );

  /*
  |--------------------------------------------------------------------------
  | Toute modification annule l’ancienne confirmation
  |--------------------------------------------------------------------------
  */
  fullNameInput?.addEventListener(
    'input',
    clearPatientFormFeedback
  );

  phoneInput?.addEventListener(
    'input',
    clearPatientFormFeedback
  );

  birthDateInput?.addEventListener(
    'change',
    clearPatientFormFeedback
  );
}
/*
|--------------------------------------------------------------------------
| Binder le bouton Fermer / Réouvrir la liste
|--------------------------------------------------------------------------
| Ce binder :
| - récupère le bouton #toggle-list-btn
| - empêche les doubles clics pendant la requête
| - appelle l'API
| - recharge le dashboard après succès
|--------------------------------------------------------------------------
*/
function bindToggleListButton() {
    const toggleButton = document.getElementById('toggle-list-btn');

    /*
    |--------------------------------------------------------------
    | Sécurité : si le bouton n'existe pas, on ne fait rien
    |--------------------------------------------------------------
    */
    if (!toggleButton) {
        return;
    }

    /*
    |--------------------------------------------------------------
    | On attache le click handler
    |--------------------------------------------------------------
    */
    toggleButton.addEventListener('click', async function () {
        /*
        |----------------------------------------------------------
        | Empêcher le double clic pendant la requête
        |----------------------------------------------------------
        */
        toggleButton.disabled = true;

        /*
        |----------------------------------------------------------
        | Sauvegarder le texte actuel pour pouvoir le restaurer
        |----------------------------------------------------------
        */
        const previousLabel = toggleButton.textContent;
        toggleButton.textContent = 'Mise à jour...';

        try {
            /*
            |------------------------------------------------------
            | Appel backend : bascule du statut de la liste
            |------------------------------------------------------
            */
            const result = await toggleTodayQueueStatus();

            /*
            |------------------------------------------------------
            | Recharger complètement le dashboard
            |------------------------------------------------------
            | Pourquoi reload complet ?
            | - met à jour le badge
            | - met à jour le texte du bouton
            | - garde une source de vérité unique côté API
            */
            await loadDashboardData();

            /*
            |------------------------------------------------------
            | Option simple V1 : feedback utilisateur minimal
            |------------------------------------------------------
            */
            console.log(result?.message || 'Statut de la liste mis à jour.');

        } catch (error) {
            /*
            |------------------------------------------------------
            | Erreur : on log + on affiche une alerte simple V1
            |------------------------------------------------------
            */
            console.error('Erreur toggle liste :', error);
            toggleButton.textContent = previousLabel;
            alert(error.message || 'Impossible de modifier le statut de la liste.');
        } finally {
           /*
          |--------------------------------------------------------------------------
          | Le texte est déjà recalculé par updateQueueStatusBadge()
          |--------------------------------------------------------------------------
          */
          toggleButton.disabled = false;
        }
    });
}
// ==========================================================
// MENU / NAVIGATION
// ==========================================================

function setActiveMenuItem(page) {
    document.querySelectorAll('.sidebar__item').forEach(item => {
        item.classList.remove('active');

        if (item.getAttribute('data-page') === page) {
            item.classList.add('active');
        }
    });
}

// ==========================================================
// CHARGEMENT DES PAGES
// ==========================================================

function loadPage(page) {
    const mainContent = document.getElementById('main-content');

    fetch(`pages/${page}.html`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur de chargement de la page');
            }
            return response.text();
        })
        .then(html => {
            mainContent.innerHTML = html;

            // Initialisation spécifique selon la page chargée
            initPage(page);
        })
        .catch(error => {
            console.error('Erreur:', error);
            mainContent.innerHTML = '<p>Erreur de chargement de la page.</p>';
        });
}

function initPage(page) {
    if (page === 'dashboard') {
        initDashboardPage();
    }

    if (page === 'settings') {
        addSaveButtonListener();
    }
}

// ==========================================================
// DASHBOARD / LISTE DU JOUR
// ==========================================================

/*
|--------------------------------------------------------------------------
| Initialisation spécifique à la page dashboard
|--------------------------------------------------------------------------
| Ordre choisi :
| 1. binder la modal nouveau patient
| 2. binder le bouton fermer / réouvrir la liste
| 3. charger les données de la page
|--------------------------------------------------------------------------
*/
function initDashboardPage() {
    bindAddPatientModalEvents();
    bindToggleListButton();
    loadDashboardData();
}

/*
|--------------------------------------------------------------------------
| Charger les données du dashboard
|--------------------------------------------------------------------------
*/
async function loadDashboardData() {
  try {
    const response = await fetch(
      'api/queue_entries.php'
    );

    const result =
      await parseJsonResponseSafely(response);

    if (!response.ok || !result.ok) {
      throw new Error(
        result?.message
          || 'Impossible de charger le dashboard.'
      );
    }

    const queue = result.data.queue;
    const entries = result.data.entries;
    const counts = result.data.counts;

    updateQueueStatusBadge(queue);
    renderDashboardTable(entries);
    renderDashboardCounters(counts);
  } catch (error) {
    console.error('Erreur dashboard :', error);

    const tableBody = document.getElementById(
      'day-list-table-body'
    );

    if (tableBody) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="6" class="table-empty-state">
            Impossible de charger les données.
          </td>
        </tr>
      `;
    }
  }
}
/*
|--------------------------------------------------------------------------
| Mettre à jour l'état du bouton "Nouveau patient"
|--------------------------------------------------------------------------
| Cette fonction rend le bouton cohérent avec l'état de la liste :
| - si la liste est ouverte  -> bouton actif
| - si la liste est fermée   -> bouton désactivé
|--------------------------------------------------------------------------
|
| Pourquoi une fonction dédiée ?
| - logique plus lisible
| - réutilisable
| - évite de mélanger trop de responsabilités dans une seule fonction
|--------------------------------------------------------------------------
*/
function updateAddPatientButtonState(queue) {
    const addPatientButton = document.getElementById('openAddPatientModalBtn');

    /*
    |--------------------------------------------------------------
    | Si le bouton n'existe pas, on sort sans erreur
    |--------------------------------------------------------------
    */
    if (!addPatientButton) {
        return;
    }

    /*
    |--------------------------------------------------------------
    | Déterminer si la liste est ouverte
    |--------------------------------------------------------------
    */
    const isOpen = queue?.status === 'open';

    /*
    |--------------------------------------------------------------
    | Désactiver / réactiver le bouton
    |--------------------------------------------------------------
    */
    addPatientButton.disabled = !isOpen;

    /*
    |--------------------------------------------------------------
    | Adapter le texte visible
    |--------------------------------------------------------------
    | On garde l'icône si elle existe déjà dans le HTML.
    | Ici on ne remplace que le texte du <span>.
    */
    const buttonLabel = addPatientButton.querySelector('span');

    if (buttonLabel) {
        buttonLabel.textContent = isOpen
            ? 'Nouveau patient'
            : 'Liste fermée';
    }

    /*
    |--------------------------------------------------------------
    | Accessibilité / UX
    |--------------------------------------------------------------
    | Le title aide à comprendre pourquoi le bouton est désactivé.
    */
    addPatientButton.title = isOpen
        ? 'Ajouter un nouveau patient à la liste du jour'
        : 'Impossible d’ajouter un patient : la liste du jour est fermée';
}
/*
|--------------------------------------------------------------------------
| Mettre à jour le badge Liste ouverte / fermée
|--------------------------------------------------------------------------
| Cette fonction met à jour :
| - le badge d'état
| - le bouton Fermer / Réouvrir la liste
| - le bouton Nouveau patient
|--------------------------------------------------------------------------
|
| Pourquoi centraliser ici ?
| - toute l'UI dépend de queue.status
| - quand loadDashboardData() recharge la queue,
|   tout l'état visuel se met à jour au même endroit
|--------------------------------------------------------------------------
*/
function updateQueueStatusBadge(queue) {
    const badge = document.getElementById('list-status-badge');
    const toggleButton = document.getElementById('toggle-list-btn');

    /*
    |--------------------------------------------------------------
    | Sécurité minimale
    |--------------------------------------------------------------
    */
    if (!badge || !toggleButton) return;

    /*
    |--------------------------------------------------------------
    | Calculer l'état métier
    |--------------------------------------------------------------
    */
    const isOpen = queue.status === 'open';

    /*
    |--------------------------------------------------------------
    | Mettre à jour le badge
    |--------------------------------------------------------------
    */
    badge.textContent = isOpen ? 'Liste ouverte' : 'Liste fermée';
    badge.classList.remove('list-status-badge--open', 'list-status-badge--closed');
    badge.classList.add(isOpen ? 'list-status-badge--open' : 'list-status-badge--closed');

    /*
    |--------------------------------------------------------------
    | Mettre à jour le bouton fermer / réouvrir
    |--------------------------------------------------------------
    */
    toggleButton.textContent = isOpen ? 'Fermer la liste' : 'Réouvrir la liste';
    toggleButton.classList.remove('btn-toggle-list--close', 'btn-toggle-list--open');
    toggleButton.classList.add(isOpen ? 'btn-toggle-list--close' : 'btn-toggle-list--open');

    /*
    |--------------------------------------------------------------
    | Mettre aussi à jour le bouton Nouveau patient
    |--------------------------------------------------------------
    | Cela garde l'interface cohérente avec l'état réel de la liste.
    */
    updateAddPatientButtonState(queue);
}

/*
|--------------------------------------------------------------------------
| Construire le tableau des patients du jour
|--------------------------------------------------------------------------
*/
function renderDashboardTable(entries) {
    const tableBody = document.getElementById('day-list-table-body');

    if (!tableBody) return;

    if (!entries || entries.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="table-empty-state">
                    Aucun patient pour aujourd'hui
                </td>
            </tr>
        `;

        updatePatientDetails(null);
        return;
    }

    tableBody.innerHTML = entries.map((entry, index) => `
        <tr
            class="patient-row ${index === 0 ? 'is-selected' : ''}"
            data-entry-id="${entry.id}"
        >
            <td>${entry.number}</td>
            <td class="patient-name-cell">${escapeHtml(entry.display_name ?? '')}</td>
            <td class="patient-phone-cell">${escapeHtml(entry.phone ?? '-')}</td>
            <td>${escapeHtml(entry.time ?? '-')}</td>
            <td>${renderStatusPill(entry.status)}</td>
            <td>
                <div class="table-actions">
                    <button class="btn-action-icon btn-action-icon--view" type="button" title="Voir">
                        <span>👁</span>
                    </button>
                    <button class="btn-action-icon btn-action-icon--edit" type="button" title="Modifier">
                        <span>✎</span>
                    </button>
                    <button class="btn-action-icon btn-action-icon--absent" type="button" title="Absent">
                        <span>✕</span>
                    </button>

                    <button class="btn-action-icon btn-action-icon--done" type="button" title="Terminer">
                        <span>✓</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    updatePatientDetails(entries[0]);
    bindPatientRowEvents(entries);
}

/*
|--------------------------------------------------------------------------
| Construire le badge de statut
|--------------------------------------------------------------------------
| Mapping DB -> UI :
| waiting = En attente
| no_show = Absent
| done = Terminé
|--------------------------------------------------------------------------
*/
function renderStatusPill(status) {
    if (status === 'waiting') {
        return '<span class="status-pill status-pill--waiting">En attente</span>';
    }

    if (status === 'no_show') {
        return '<span class="status-pill status-pill--absent">Absent</span>';
    }

    if (status === 'done') {
        return '<span class="status-pill status-pill--done">Terminé</span>';
    }

    return `<span class="status-pill">${escapeHtml(status)}</span>`;
}
function formatBirthDate(value) {
    if (!value) return '-';

    const parts = String(value).split('-');
    if (parts.length !== 3) return escapeHtml(String(value));

    const [year, month, day] = parts;
    return `${day}/${month}/${year}`;
}

function formatSourceLabel(source) {
    if (source === 'secretary') return 'Secrétaire';
    if (source === 'doctor') return 'Médecin';
    if (source === 'qr') return 'QR code';
    if (source === 'link') return 'Lien public';
    if (!source) return '-';
    return escapeHtml(String(source));
}

function renderDetailStatusPill(status) {
    return renderStatusPill(status);
}

function renderDefaultPatientHistory() {
    return `
        <li>
            <span>Aucune visite récente</span>
            <strong class="history-status">-</strong>
        </li>
    `;
}

function updatePatientDetails(entry) {
    const nameEl = document.getElementById('patient-details-name');
    const phoneEl = document.getElementById('patient-details-phone');
    const birthDateEl = document.getElementById('patient-details-birth-date');
    const sourceEl = document.getElementById('patient-details-source');
    const statusEl = document.getElementById('patient-details-status');
    const notesEl = document.getElementById('patient-details-notes');
    const historyEl = document.getElementById('patient-details-history');

    if (!nameEl || !phoneEl || !birthDateEl || !sourceEl || !statusEl || !notesEl || !historyEl) {
        return;
    }

    if (!entry) {
        nameEl.textContent = 'Aucun patient sélectionné';
        phoneEl.textContent = '-';
        birthDateEl.textContent = '-';
        sourceEl.textContent = '-';
        statusEl.innerHTML = '<span class="status-pill">-</span>';
        notesEl.textContent = 'Aucune note disponible.';
        historyEl.innerHTML = renderDefaultPatientHistory();
        return;
    }

    nameEl.textContent = entry.display_name || '-';
    phoneEl.textContent = entry.phone || '-';
    birthDateEl.textContent = formatBirthDate(entry.birth_date);
    sourceEl.textContent = formatSourceLabel(entry.source);
    statusEl.innerHTML = renderDetailStatusPill(entry.status);

    notesEl.textContent = 'Aucune note disponible pour le moment.';
    historyEl.innerHTML = renderDefaultPatientHistory();
}

function selectPatientRowByEntryId(entryId) {
    const rows = document.querySelectorAll('.patient-row');

    rows.forEach(row => {
        const isSelected = String(row.dataset.entryId) === String(entryId);
        row.classList.toggle('is-selected', isSelected);
    });
}
function findEntryById(entries, entryId) {
    return entries.find(entry => String(entry.id) === String(entryId)) || null;
}
function bindPatientRowEvents(entries) {
    const rows = document.querySelectorAll('.patient-row');

    rows.forEach(row => {
        const entryId = row.dataset.entryId;
        const entry = findEntryById(entries, entryId);

        if (!entry) return;

        row.addEventListener('click', (event) => {
            const clickedActionButton = event.target.closest('.btn-action-icon');

            if (clickedActionButton && !clickedActionButton.classList.contains('btn-action-icon--view')) {
                return;
            }

            selectPatientRowByEntryId(entry.id);
            updatePatientDetails(entry);
        });

        const viewBtn = row.querySelector('.btn-action-icon--view');
        if (viewBtn) {
            viewBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                selectPatientRowByEntryId(entry.id);
                updatePatientDetails(entry);
            });
        }

        const editBtn = row.querySelector('.btn-action-icon--edit');
        if (editBtn) {
            editBtn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openEditPatientModal(entry);
            });
        }

        const absentBtn = row.querySelector('.btn-action-icon--absent');
        if (absentBtn) {
            absentBtn.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();

                try {
                    await updateQueueEntryStatus(entry.id, 'no_show');
                    await loadDashboardData();
                } catch (error) {
                    console.error('Erreur statut absent:', error);
                    alert(error.message || 'Impossible de marquer le patient absent.');
                }
            });
        }

        const doneBtn = row.querySelector('.btn-action-icon--done');
        if (doneBtn) {
            doneBtn.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();

                try {
                    await updateQueueEntryStatus(entry.id, 'done');
                    await loadDashboardData();
                } catch (error) {
                    console.error('Erreur statut terminé:', error);
                    alert(error.message || 'Impossible de terminer le patient.');
                }
            });
        }
    });
}
/*
|--------------------------------------------------------------------------
| Mettre à jour les compteurs
|--------------------------------------------------------------------------
*/
function renderDashboardCounters(counts) {
    const waitingEl = document.getElementById('counter-waiting');
    const absentEl = document.getElementById('counter-absent');
    const doneEl = document.getElementById('counter-done');

    if (waitingEl) waitingEl.textContent = counts.waiting ?? 0;
    if (absentEl) absentEl.textContent = counts.absent ?? 0;
    if (doneEl) doneEl.textContent = counts.done ?? 0;
}

// ==========================================================
// SETTINGS
// ==========================================================

function addSaveButtonListener() {
    const saveButton = document.getElementById('save-button');

    if (saveButton) {
        saveButton.addEventListener('click', function () {
            const nom = document.getElementById('nom-prenom')?.value;
            const specialite = document.getElementById('specialite')?.value;
            const telephone = document.getElementById('telephone')?.value;
            const email = document.getElementById('email')?.value;
            const adresse = document.getElementById('adresse')?.value;

            console.log('Nom et Prénom:', nom);
            console.log('Spécialité:', specialite);
            console.log('Téléphone:', telephone);
            console.log('Email:', email);
            console.log('Adresse du cabinet:', adresse);

            this.classList.toggle('clicked');

            setTimeout(() => {
                this.classList.remove('clicked');
            }, 300);
        });
    }
}

// ==========================================================
// HELPERS
// ==========================================================

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}

// ==========================================================
// EVENTS MENU
// ==========================================================

document.querySelectorAll('.sidebar__item').forEach(item => {
    item.addEventListener('click', function () {
        const page = this.getAttribute('data-page');
        setActiveMenuItem(page);
        loadPage(page);
    });
});

// ==========================================================
// PAGE PAR DÉFAUT AU REFRESH
// ==========================================================

/*
|--------------------------------------------------------------------------
| Initialisation globale de l’application
|--------------------------------------------------------------------------
| Le listener clavier est installé une seule fois.
| Les pages internes peuvent ensuite être rechargées sans duplication.
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', function () {
  document.addEventListener(
    'keydown',
    handlePatientModalKeydown
  );

  setActiveMenuItem('dashboard');
  loadPage('dashboard');
});

/*
|--------------------------------------------------------------------------
| Configurer la modal en création ou en modification
|--------------------------------------------------------------------------
*/
function setPatientModalMode(mode = 'create', entry = null) {
  const {
    titleEl,
    submitBtn,
    formModeInput,
    entryIdInput,
    fullNameInput,
    phoneInput,
    birthDateInput
  } = getAddPatientModalElements();

  if (formModeInput) {
    formModeInput.value = mode;
  }

  if (entryIdInput) {
    entryIdInput.value = entry?.id ?? '';
  }

  if (mode === 'edit') {
    if (titleEl) {
      titleEl.textContent = 'Modifier le patient';
    }

    if (submitBtn) {
      submitBtn.textContent = getPatientDefaultSubmitLabel('edit');
    }

    if (fullNameInput) {
      fullNameInput.value = entry?.display_name ?? '';
    }

    if (phoneInput) {
      phoneInput.value = entry?.phone ?? '';
    }

    if (birthDateInput) {
      birthDateInput.value = entry?.birth_date ?? '';
    }

    return;
  }

  if (titleEl) {
    titleEl.textContent = 'Nouveau patient';
  }

  if (submitBtn) {
    submitBtn.textContent = getPatientDefaultSubmitLabel('create');
  }

  if (entryIdInput) {
    entryIdInput.value = '';
  }
}
/*
|--------------------------------------------------------------------------
| Envoyer la requête d'ajout patient
|--------------------------------------------------------------------------
| Cette fonction centralise l'appel API pour pouvoir la réutiliser
| en mode normal ou en mode "confirmation téléphone partagé".
|--------------------------------------------------------------------------
*/
async function submitPatientModalPayload(mode, payload) {
  const endpoint = mode === 'edit'
    ? UPDATE_PATIENT_API_URL
    : ADD_PATIENT_API_URL;

  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  });

  const data = await parseJsonResponseSafely(response);

  return { response, data };
}
/*
|--------------------------------------------------------------------------
| Ouvrir la modal en mode modification
|--------------------------------------------------------------------------
*/
function openEditPatientModal(entry) {
  const {
    modal,
    fullNameInput,
    messageBox,
    form
  } = getAddPatientModalElements();

  if (!modal || !entry) {
    return;
  }

  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');

  if (messageBox) {
    messageBox.textContent = '';
    messageBox.className = 'marki-form__message';
  }

  if (form) {
    form.dataset.submitting = 'false';
  }

  resetSharedPhoneConfirmation();
  setPatientModalMode('edit', entry);

  if (fullNameInput) {
    setTimeout(() => fullNameInput.focus(), 0);
  }
}