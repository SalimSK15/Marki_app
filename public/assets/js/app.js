const ADD_PATIENT_API_URL = '/Marki_app/Partie_medecin/public/api/queue_add_patient.php';
const UPDATE_QUEUE_STATUS_API_URL = '/Marki_app/Partie_medecin/public/api/queue_update_status.php';
const UPDATE_PATIENT_API_URL = '/Marki_app/Partie_medecin/public/api/queue_update_patient.php';
const TOGGLE_QUEUE_STATUS_API_URL = '/Marki_app/Partie_medecin/public/api/queue_toggle_status.php';
const CHANGE_QUEUE_DAY_STATUS_API_URL = '/Marki_app/Partie_medecin/public/api/queue_change_day_status.php';
const QUEUE_ENTRIES_API_URL = '/Marki_app/Partie_medecin/public/api/queue_entries.php';

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
| Action métier en attente de confirmation
|--------------------------------------------------------------------------
*/
let pendingQueueAction = null;
/*
|--------------------------------------------------------------------------
| État central de la Liste du jour
|--------------------------------------------------------------------------
| entries :
| toutes les entrées reçues de l’API, dans l’ordre FIFO.
|
| selectedEntryId :
| patient actuellement affiché dans le panneau de détails.
|
| Le patient actuel, lui, est toujours calculé comme le premier
| patient ayant le statut waiting.
|--------------------------------------------------------------------------
*/
const dashboardState = {
  entries: [],
  queue: null,
  searchTerm: '',
  statusFilter: 'all',
  pageSize: 12,
  currentPage: 1,
  selectedEntryId: null,
  scrollToEntryId: null
};

/*
|--------------------------------------------------------------------------
| Temporisation légère de la recherche
|--------------------------------------------------------------------------
| On évite de recalculer le tableau à chaque frappe extrêmement rapide.
|--------------------------------------------------------------------------
*/
let dashboardSearchTimer = null;
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
  if (window.MarkiPhone) {
    return window.MarkiPhone.localMobileDigits(value);
  }

  return String(value ?? '').replace(/\D+/g, '').slice(0, 10);
}

/*
|--------------------------------------------------------------------------
| Vérifier le format général du téléphone
|--------------------------------------------------------------------------
*/
function isValidPhoneNumber(phone) {
  return window.MarkiPhone
    ? window.MarkiPhone.isValidMobile(phone)
    : /^0[567]\d{8}$/.test(phone);
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
    throw new Error('Le serveur a renvoyé une réponse invalide. Réessayez dans quelques instants.');
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
  const queueActionModal = document.getElementById('queueActionModal');

  if (queueActionModal?.classList.contains('is-open')) {
    closeQueueActionModal();
    return;
  }

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
  phoneInput.value = window.MarkiPhone ? window.MarkiPhone.formatMobile(phone) : phone;
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
      'Le numéro doit être sous forme de 0551223344',
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


/*
|--------------------------------------------------------------------------
| Afficher une notification non bloquante
|--------------------------------------------------------------------------
*/
function showToast(message, type = 'info') {
  let container = document.getElementById('marki-toast-container');

  if (!container) {
    container = document.createElement('div');
    container.id = 'marki-toast-container';
    container.className = 'marki-toast-container';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    document.body.append(container);
  }

  const toast = document.createElement('div');
  toast.className = `marki-toast marki-toast--${type}`;
  toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
  toast.textContent = message;

  container.append(toast);

  requestAnimationFrame(() => {
    toast.classList.add('is-visible');
  });

  window.setTimeout(() => {
    toast.classList.remove('is-visible');
    window.setTimeout(() => toast.remove(), 250);
  }, 3200);
}

window.showToast = showToast;

/*
|--------------------------------------------------------------------------
| Récupérer les éléments de la modal de confirmation métier
|--------------------------------------------------------------------------
*/
function getQueueActionModalElements() {
  return {
    modal: document.getElementById('queueActionModal'),
    title: document.getElementById('queueActionModalTitle'),
    message: document.getElementById('queueActionModalMessage'),
    closeBtn: document.getElementById('closeQueueActionModalBtn'),
    cancelBtn: document.getElementById('cancelQueueActionBtn'),
    secondaryBtn: document.getElementById('secondaryQueueActionBtn'),
    confirmBtn: document.getElementById('confirmQueueActionBtn'),
    reasonGroup: document.getElementById('queueActionReasonGroup'),
    reasonSelect: document.getElementById('queueActionReason'),
    backdrop: document.querySelector('[data-close-queue-action-modal]')
  };
}

/*
|--------------------------------------------------------------------------
| Fermer la modal de confirmation métier
|--------------------------------------------------------------------------
*/
function closeQueueActionModal() {
  const {
    modal,
    secondaryBtn,
    confirmBtn,
    reasonGroup,
    reasonSelect
  } = getQueueActionModalElements();

  if (!modal) return;

  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');

  if (secondaryBtn) {
    secondaryBtn.hidden = true;
    secondaryBtn.textContent = 'Mettre en pause';
  }

  if (confirmBtn) {
    confirmBtn.disabled = false;
    confirmBtn.textContent = 'Confirmer';
    confirmBtn.classList.remove('btn-primary');
    confirmBtn.classList.add('btn-danger');
  }

  if (reasonGroup) {
    reasonGroup.hidden = true;
  }

  if (reasonSelect) {
    reasonSelect.innerHTML = '';
  }

  pendingQueueAction = null;
}

/*
|--------------------------------------------------------------------------
| Ouvrir une confirmation métier réutilisable
|--------------------------------------------------------------------------
*/
function openQueueActionConfirmation(options) {
  const {
    modal,
    title,
    message,
    secondaryBtn,
    confirmBtn,
    reasonGroup,
    reasonSelect
  } = getQueueActionModalElements();

  if (!modal || !title || !message || !confirmBtn) {
    return;
  }

  pendingQueueAction = options;
  title.textContent = options.title || 'Confirmer l’action';
  message.textContent = options.message || '';
  confirmBtn.textContent = options.confirmLabel || 'Confirmer';
  confirmBtn.classList.toggle(
    'btn-primary',
    options.confirmType === 'primary'
  );
  confirmBtn.classList.toggle(
    'btn-danger',
    options.confirmType !== 'primary'
  );

  if (secondaryBtn) {
    secondaryBtn.hidden = typeof options.onSecondary !== 'function';
    secondaryBtn.textContent = options.secondaryLabel || 'Mettre en pause';
  }

  const reasonOptions = Array.isArray(options.reasonOptions)
    ? options.reasonOptions
    : [];

  if (reasonGroup && reasonSelect) {
    reasonGroup.hidden = reasonOptions.length === 0;
    reasonSelect.innerHTML = '';

    reasonOptions.forEach(optionData => {
      const option = document.createElement('option');
      option.value = optionData.value;
      option.textContent = optionData.label;
      option.selected = optionData.value === options.defaultReason;
      reasonSelect.append(option);
    });
  }

  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');

  window.setTimeout(() => confirmBtn.focus(), 0);
}

/*
|--------------------------------------------------------------------------
| Brancher la modal de confirmation métier
|--------------------------------------------------------------------------
*/
function bindQueueActionModalEvents() {
  const {
    closeBtn,
    cancelBtn,
    secondaryBtn,
    confirmBtn,
    reasonSelect,
    backdrop
  } = getQueueActionModalElements();

  closeBtn?.addEventListener('click', closeQueueActionModal);
  cancelBtn?.addEventListener('click', closeQueueActionModal);
  backdrop?.addEventListener('click', closeQueueActionModal);

  secondaryBtn?.addEventListener('click', async () => {
    const callback = pendingQueueAction?.onSecondary;

    if (typeof callback !== 'function') return;

    secondaryBtn.disabled = true;

    try {
      await callback();
      closeQueueActionModal();
    } catch (error) {
      console.error('Erreur action secondaire :', error);
      showToast(
        error.message || 'Impossible d’exécuter cette action.',
        'error'
      );
    } finally {
      secondaryBtn.disabled = false;
    }
  });

  confirmBtn?.addEventListener('click', async () => {
    const callback = pendingQueueAction?.onConfirm;

    if (typeof callback !== 'function') return;

    confirmBtn.disabled = true;
    const previousLabel = confirmBtn.textContent;
    confirmBtn.textContent = 'Traitement...';

    try {
      await callback({
        reason: reasonSelect?.value || null
      });
      closeQueueActionModal();
    } catch (error) {
      console.error('Erreur action confirmée :', error);
      showToast(
        error.message || 'Impossible d’exécuter cette action.',
        'error'
      );
      confirmBtn.disabled = false;
      confirmBtn.textContent = previousLabel;
    }
  });
}

async function updateQueueEntryStatus(
  entryId,
  status,
  extraPayload = {}
) {
  const response = await fetch(UPDATE_QUEUE_STATUS_API_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      entry_id: entryId,
      status,
      ...extraPayload
    })
  });

  const data = await parseJsonResponseSafely(response);

  if (!response.ok) {
    throw new Error(
      data?.message || 'Impossible de mettre à jour le statut.'
    );
  }

  return data;
}

/*
|--------------------------------------------------------------------------
| Ouvrir ou fermer les nouvelles inscriptions
|--------------------------------------------------------------------------
| Cette action ne clôture pas la journée et ne bloque pas les patients
| déjà présents dans la file.
|--------------------------------------------------------------------------
*/
async function toggleTodayQueueStatus() {
  const response = await fetch(TOGGLE_QUEUE_STATUS_API_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({})
  });

  const data = await parseJsonResponseSafely(response);

  if (!response.ok) {
    throw new Error(
      data?.message || 'Impossible de modifier l’état des inscriptions.'
    );
  }

  return data;
}

/*
|--------------------------------------------------------------------------
| Modifier l'état opérationnel de la Liste du jour
|--------------------------------------------------------------------------
*/
async function changeTodayQueueDayStatus(
  action,
  cancellationReason = null
) {
  const payload = { action };

  if (cancellationReason) {
    payload.cancellation_reason = cancellationReason;
  }

  const response = await fetch(CHANGE_QUEUE_DAY_STATUS_API_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  });

  const data = await parseJsonResponseSafely(response);

  if (!response.ok) {
    throw new Error(
      data?.message || 'Impossible de modifier l’état de la liste.'
    );
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
| Brancher l'ouverture / fermeture des inscriptions
|--------------------------------------------------------------------------
*/
function bindToggleListButton() {
  const toggleButton = document.getElementById('toggle-list-btn');

  if (!toggleButton) return;

  toggleButton.addEventListener('click', () => {
    const registrationIsOpen =
      dashboardState.queue?.registration_status === 'open';

    const title = registrationIsOpen
      ? 'Fermer les inscriptions ?'
      : 'Rouvrir les inscriptions ?';

    const message = registrationIsOpen
      ? 'Aucun nouveau patient ne pourra s’inscrire manuellement, par QR code ou par lien public. Les patients déjà inscrits resteront traitables.'
      : 'Les nouvelles inscriptions seront de nouveau autorisées.';

    openQueueActionConfirmation({
      title,
      message,
      confirmLabel: registrationIsOpen
        ? 'Fermer les inscriptions'
        : 'Rouvrir les inscriptions',
      confirmType: registrationIsOpen ? 'danger' : 'primary',
      onConfirm: async () => {
        const result = await toggleTodayQueueStatus();
        showToast(result.message, 'success');
        await loadDashboardData();
      }
    });
  });
}

/*
|--------------------------------------------------------------------------
| Ouvrir / fermer le menu de gestion de la journée
|--------------------------------------------------------------------------
*/
function closeQueueDayMenu() {
  const menu = document.getElementById('queue-day-menu');
  const button = document.getElementById('queue-day-menu-btn');

  if (menu) menu.hidden = true;
  if (button) button.setAttribute('aria-expanded', 'false');
}

function handleDocumentQueueDayMenuClick(event) {
  if (!event.target.closest('.queue-day-menu-wrapper')) {
    closeQueueDayMenu();
  }
}

function bindQueueDayControls() {
  const menuButton = document.getElementById('queue-day-menu-btn');
  const menu = document.getElementById('queue-day-menu');
  const pauseButton = document.getElementById('pause-day-btn');
  const resumeButton = document.getElementById('resume-day-btn');
  const completeButton = document.getElementById('complete-day-btn');
  const reopenButton = document.getElementById(
    'reopen-completed-day-btn'
  );

  menuButton?.addEventListener('click', event => {
    event.stopPropagation();

    if (!menu) return;

    menu.hidden = !menu.hidden;
    menuButton.setAttribute(
      'aria-expanded',
      menu.hidden ? 'false' : 'true'
    );
  });

  document.removeEventListener(
    'click',
    handleDocumentQueueDayMenuClick
  );
  document.addEventListener(
    'click',
    handleDocumentQueueDayMenuClick
  );

  /*
  |--------------------------------------------------------------------------
  | Bouton de secours : annuler immédiatement la clôture
  |--------------------------------------------------------------------------
  | Aucun message de confirmation n'est affiché. Le backend restaure la queue
  | et uniquement les patients annulés automatiquement par la clôture.
  |--------------------------------------------------------------------------
  */
  reopenButton?.addEventListener('click', async () => {
    if (reopenButton.disabled) return;

    reopenButton.disabled = true;

    try {
      const result = await changeTodayQueueDayStatus('reopen');
      showToast(result.message, 'success');
      await loadDashboardData({ focusCurrent: true });
    } catch (error) {
      console.error('Erreur annulation de clôture :', error);
      showToast(
        error.message || 'Impossible d’annuler la clôture.',
        'error'
      );
    } finally {
      reopenButton.disabled = false;
    }
  });

  pauseButton?.addEventListener('click', () => {
    closeQueueDayMenu();

    openQueueActionConfirmation({
      title: 'Mettre la liste en pause ?',
      message: 'Les patients conserveront leur position. Les nouvelles inscriptions seront fermées et le traitement pourra reprendre plus tard.',
      confirmLabel: 'Mettre en pause',
      onConfirm: async () => {
        const result = await changeTodayQueueDayStatus('pause');
        showToast(result.message, 'info');
        await loadDashboardData();
      }
    });
  });

  resumeButton?.addEventListener('click', async () => {
    closeQueueDayMenu();

    try {
      const result = await changeTodayQueueDayStatus('resume');
      showToast(result.message, 'success');
      await loadDashboardData({ focusCurrent: true });
    } catch (error) {
      showToast(error.message, 'error');
    }
  });

  completeButton?.addEventListener('click', async () => {
    closeQueueDayMenu();

    const waitingCount = Number(
      document.getElementById('counter-waiting')?.textContent || 0
    );

    const completeDay = async () => {
      const result = await changeTodayQueueDayStatus(
        'complete',
        'end_of_day'
      );
      showToast(result.message, 'success');
      await loadDashboardData();
    };

    if (waitingCount === 0) {
      try {
        await completeDay();
      } catch (error) {
        showToast(error.message, 'error');
      }
      return;
    }

    openQueueActionConfirmation({
      title: 'Clôturer la journée ?',
      message: `${waitingCount} patient(s) sont encore en attente. Ils seront tous marqués « Annulés — fin de journée » et devront s’inscrire de nouveau lors d’une prochaine journée.`,
      confirmLabel: `Clôturer et annuler ${waitingCount} inscription(s)`,
      secondaryLabel: 'Mettre en pause',
      onSecondary: async () => {
        const result = await changeTodayQueueDayStatus('pause');
        showToast(result.message, 'info');
        await loadDashboardData();
      },
      onConfirm: completeDay
    });
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

  if (!mainContent) {
    return;
  }

  fetch(`pages/${encodeURIComponent(page)}.html`, {
    cache: 'no-store'
  })
    .then(response => {
      if (!response.ok) {
        throw new Error('Erreur de chargement de la page');
      }

      return response.text();
    })
    .then(html => {
      mainContent.innerHTML = html;
      initPage(page);
    })
    .catch(error => {
      console.error('Erreur :', error);
      mainContent.innerHTML = '<p>Erreur de chargement de la page.</p>';
    });
}

function initPage(page) {
  if (page === 'dashboard') {
    initDashboardPage();
    return;
  }

  if (typeof window.initMarkiV1Page === 'function') {
    window.initMarkiV1Page(page);
  }
}
window.loadPage = loadPage;
window.setActiveMenuItem = setActiveMenuItem;

// ==========================================================
// DASHBOARD / LISTE DU JOUR
// ==========================================================
/*
|--------------------------------------------------------------------------
| Réinitialiser la vue du dashboard
|--------------------------------------------------------------------------
| Utilisé lorsque la page Dashboard est chargée à nouveau.
|--------------------------------------------------------------------------
*/
function resetDashboardViewState() {
  dashboardState.entries = [];
  dashboardState.queue = null;
  dashboardState.searchTerm = '';
  dashboardState.statusFilter = 'all';
  dashboardState.pageSize = 12;
  dashboardState.currentPage = 1;
  dashboardState.selectedEntryId = null;
  dashboardState.scrollToEntryId = null;
}

/*
|--------------------------------------------------------------------------
| Trouver le patient actuel selon la logique FIFO
|--------------------------------------------------------------------------
| La liste reçue de l’API est déjà triée par position_number.
| Le premier patient waiting est donc le patient actuel.
|--------------------------------------------------------------------------
*/
function getCurrentWaitingEntry() {
  return dashboardState.entries.find(
    entry => entry.status === 'waiting'
  ) || null;
}

/*
|--------------------------------------------------------------------------
| Normaliser une valeur utilisée dans la recherche
|--------------------------------------------------------------------------
*/
function normalizeDashboardSearchValue(value) {
  return String(value ?? '')
    .trim()
    .toLocaleLowerCase('fr');
}

/*
|--------------------------------------------------------------------------
| Appliquer la recherche et le filtre de statut
|--------------------------------------------------------------------------
*/
function getFilteredDashboardEntries() {
  const searchTerm = normalizeDashboardSearchValue(
    dashboardState.searchTerm
  );

  const searchedPhone = searchTerm.replace(/\D+/g, '');

  return dashboardState.entries.filter(entry => {
    /*
    |--------------------------------------------------------------------------
    | Filtre par statut
    |--------------------------------------------------------------------------
    */
    const matchesStatus =
      dashboardState.statusFilter === 'all'
      || entry.status === dashboardState.statusFilter;

    if (!matchesStatus) {
      return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Sans recherche, le statut suffit
    |--------------------------------------------------------------------------
    */
    if (searchTerm === '') {
      return true;
    }

    const patientName =
      normalizeDashboardSearchValue(
        entry.display_name
      );

    const patientPhone = String(
      entry.phone ?? ''
    ).replace(/\D+/g, '');

    const matchesName =
      patientName.includes(searchTerm);

    const matchesPhone =
      searchedPhone !== ''
      && patientPhone.includes(searchedPhone);

    return matchesName || matchesPhone;
  });
}

/*
|--------------------------------------------------------------------------
| Calculer le nombre de pages
|--------------------------------------------------------------------------
*/
function getDashboardTotalPages(filteredEntries) {
  return Math.max(
    1,
    Math.ceil(
      filteredEntries.length / dashboardState.pageSize
    )
  );
}

/*
|--------------------------------------------------------------------------
| Récupérer les entrées de la page actuelle
|--------------------------------------------------------------------------
*/
function getCurrentDashboardPageEntries(filteredEntries) {
  const startIndex =
    (dashboardState.currentPage - 1)
    * dashboardState.pageSize;

  const endIndex =
    startIndex + dashboardState.pageSize;

  return filteredEntries.slice(
    startIndex,
    endIndex
  );
}

/*
|--------------------------------------------------------------------------
| Synchroniser les contrôles HTML avec l’état JavaScript
|--------------------------------------------------------------------------
*/
function syncDashboardControls() {
  const searchInput =
    document.getElementById('day-list-search');

  const filterSelect =
    document.getElementById('day-list-filter');

  if (
    searchInput
    && searchInput.value !== dashboardState.searchTerm
  ) {
    searchInput.value = dashboardState.searchTerm;
  }

  if (filterSelect) {
    filterSelect.value =
      dashboardState.statusFilter;
  }

  document
    .querySelectorAll('[data-page-size]')
    .forEach(button => {
      const pageSize =
        Number(button.dataset.pageSize);

      button.classList.toggle(
        'is-active',
        pageSize === dashboardState.pageSize
      );
    });
}

/*
|--------------------------------------------------------------------------
| Rendre une entrée visible dans la pagination
|--------------------------------------------------------------------------
| Si la recherche ou le filtre cache le patient actuel,
| on revient à la vue générale pour pouvoir le retrouver.
|--------------------------------------------------------------------------
*/
function ensureDashboardEntryIsVisible(entryId) {
  let filteredEntries =
    getFilteredDashboardEntries();

  const isPresent =
    filteredEntries.some(
      entry => String(entry.id) === String(entryId)
    );

  if (!isPresent) {
    dashboardState.searchTerm = '';
    dashboardState.statusFilter = 'all';

    filteredEntries =
      getFilteredDashboardEntries();
  }

  const entryIndex =
    filteredEntries.findIndex(
      entry => String(entry.id) === String(entryId)
    );

  if (entryIndex === -1) {
    return;
  }

  dashboardState.currentPage =
    Math.floor(
      entryIndex / dashboardState.pageSize
    ) + 1;
}

/*
|--------------------------------------------------------------------------
| Faire défiler uniquement la zone du tableau
|--------------------------------------------------------------------------
| On ne déplace pas toute la page.
|--------------------------------------------------------------------------
*/
function scrollDashboardEntryIntoView(
  entryId,
  useSmoothScroll = true
) {
  const container =
    document.querySelector('.waiting-list');

  const row = document.querySelector(
    `.patient-row[data-entry-id="${entryId}"]`
  );

  if (!container || !row) {
    return;
  }

  const containerRect =
    container.getBoundingClientRect();

  const rowRect =
    row.getBoundingClientRect();

  const targetScrollTop =
    container.scrollTop
    + rowRect.top
    - containerRect.top
    - (
      container.clientHeight
      - row.clientHeight
    ) / 2;

  container.scrollTo({
    top: Math.max(0, targetScrollTop),
    behavior: useSmoothScroll
      ? 'smooth'
      : 'auto'
  });
}

/*
|--------------------------------------------------------------------------
| Revenir au patient actuel
|--------------------------------------------------------------------------
*/
function focusCurrentPatient() {
  const currentEntry =
    getCurrentWaitingEntry();

  if (!currentEntry) {
    return;
  }

  ensureDashboardEntryIsVisible(
    currentEntry.id
  );

  dashboardState.selectedEntryId =
    currentEntry.id;

  dashboardState.scrollToEntryId =
    currentEntry.id;

  syncDashboardControls();
  renderDashboardView();
}

/*
|--------------------------------------------------------------------------
| Sélectionner un patient pour afficher ses détails
|--------------------------------------------------------------------------
| Cette action ne change pas le patient actuel FIFO.
|--------------------------------------------------------------------------
*/
function selectDashboardEntry(entryId) {
  dashboardState.selectedEntryId =
    Number(entryId);

  document
    .querySelectorAll('.patient-row')
    .forEach(row => {
      row.classList.toggle(
        'is-selected',
        String(row.dataset.entryId)
          === String(entryId)
      );
    });

  const selectedEntry = findEntryById(
    dashboardState.entries,
    entryId
  );

  updatePatientDetails(selectedEntry);
  renderCurrentPatientButton();
}

/*
|--------------------------------------------------------------------------
| Mettre à jour le nombre de résultats
|--------------------------------------------------------------------------
*/
function renderDashboardResultsCount(totalResults) {
  const resultElement =
    document.getElementById(
      'day-list-results-count'
    );

  if (!resultElement) {
    return;
  }

  resultElement.textContent =
    totalResults <= 1
      ? `${totalResults} patient`
      : `${totalResults} patients`;
}

/*
|--------------------------------------------------------------------------
| Construire une pagination compacte
|--------------------------------------------------------------------------
| Exemple :
| 1 2 3 4 5
|
| Pour un grand nombre de pages :
| 1 … 6 7 8 … 15
|--------------------------------------------------------------------------
*/
function buildPaginationItems(
  totalPages,
  currentPage
) {
  if (totalPages <= 7) {
    return Array.from(
      { length: totalPages },
      (_, index) => index + 1
    );
  }

  const pages = new Set([
    1,
    totalPages,
    currentPage - 1,
    currentPage,
    currentPage + 1
  ]);

  if (currentPage <= 3) {
    pages.add(2);
    pages.add(3);
    pages.add(4);
  }

  if (currentPage >= totalPages - 2) {
    pages.add(totalPages - 1);
    pages.add(totalPages - 2);
    pages.add(totalPages - 3);
  }

  const validPages = [...pages]
    .filter(page => page >= 1 && page <= totalPages)
    .sort((left, right) => left - right);

  const items = [];

  validPages.forEach((page, index) => {
    const previousPage =
      validPages[index - 1];

    if (
      previousPage !== undefined
      && page - previousPage > 1
    ) {
      items.push('ellipsis');
    }

    items.push(page);
  });

  return items;
}

/*
|--------------------------------------------------------------------------
| Afficher la pagination
|--------------------------------------------------------------------------
*/
function renderDashboardPagination(totalPages) {
  const pagination =
    document.getElementById(
      'day-list-pagination'
    );

  if (!pagination) {
    return;
  }

  const pageItems = buildPaginationItems(
    totalPages,
    dashboardState.currentPage
  );

  const previousDisabled =
    dashboardState.currentPage === 1;

  const nextDisabled =
    dashboardState.currentPage === totalPages;

  const pageButtons = pageItems
    .map(item => {
      if (item === 'ellipsis') {
        return `
          <span
            class="pagination__ellipsis"
            aria-hidden="true"
          >
            …
          </span>
        `;
      }

      const isActive =
        item === dashboardState.currentPage;

      return `
        <button
          class="pagination__item ${isActive ? 'is-active' : ''}"
          type="button"
          data-page="${item}"
          ${isActive ? 'aria-current="page"' : ''}
        >
          ${item}
        </button>
      `;
    })
    .join('');

  pagination.innerHTML = `
    <button
      class="pagination__item pagination__item--navigation"
      type="button"
      data-page="${dashboardState.currentPage - 1}"
      ${previousDisabled ? 'disabled' : ''}
      aria-label="Page précédente"
    >
      ‹
    </button>

    ${pageButtons}

    <button
      class="pagination__item pagination__item--navigation"
      type="button"
      data-page="${dashboardState.currentPage + 1}"
      ${nextDisabled ? 'disabled' : ''}
      aria-label="Page suivante"
    >
      ›
    </button>
  `;
}

/*
|--------------------------------------------------------------------------
| Mettre à jour le bouton Patient actuel
|--------------------------------------------------------------------------
*/
function renderCurrentPatientButton() {
  const button =
    document.getElementById(
      'focus-current-patient-btn'
    );

  const label =
    document.getElementById(
      'focus-current-patient-label'
    );

  if (!button || !label) {
    return;
  }

  const currentEntry =
    getCurrentWaitingEntry();

  if (!currentEntry) {
    button.disabled = true;
    label.textContent =
      'Aucun patient en attente';

    return;
  }

  button.disabled = false;

  label.textContent =
    `Voir le patient actuel · N° ${currentEntry.number}`;
}
/*
|--------------------------------------------------------------------------
| Brancher les contrôles de la Liste du jour
|--------------------------------------------------------------------------
*/
function bindDashboardListControls() {
  const searchInput =
    document.getElementById(
      'day-list-search'
    );

  const filterSelect =
    document.getElementById(
      'day-list-filter'
    );

  const currentPatientButton =
    document.getElementById(
      'focus-current-patient-btn'
    );

  const pagination =
    document.getElementById(
      'day-list-pagination'
    );

  /*
  |--------------------------------------------------------------------------
  | Recherche avec une temporisation légère de 150 ms
  |--------------------------------------------------------------------------
  */
  searchInput?.addEventListener(
    'input',
    event => {
      clearTimeout(dashboardSearchTimer);

      dashboardSearchTimer = setTimeout(
        () => {
          dashboardState.searchTerm =
            event.target.value;

          dashboardState.currentPage = 1;
          dashboardState.scrollToEntryId = null;

          renderDashboardView();
        },
        150
      );
    }
  );

  /*
  |--------------------------------------------------------------------------
  | Filtre par statut
  |--------------------------------------------------------------------------
  */
  filterSelect?.addEventListener(
    'change',
    event => {
      dashboardState.statusFilter =
        event.target.value;

      dashboardState.currentPage = 1;
      dashboardState.scrollToEntryId = null;

      renderDashboardView();
    }
  );

  /*
  |--------------------------------------------------------------------------
  | Affichage 12, 24 ou 48
  |--------------------------------------------------------------------------
  */
  document
    .querySelectorAll('[data-page-size]')
    .forEach(button => {
      button.addEventListener(
        'click',
        () => {
          const pageSize =
            Number(button.dataset.pageSize);

          if (![12, 24, 48].includes(pageSize)) {
            return;
          }

          dashboardState.pageSize = pageSize;
          dashboardState.currentPage = 1;
          dashboardState.scrollToEntryId = null;

          renderDashboardView();

          document
            .querySelector('.waiting-list')
            ?.scrollTo({
              top: 0,
              behavior: 'smooth'
            });
        }
      );
    });

  /*
  |--------------------------------------------------------------------------
  | Pagination avec délégation d’événement
  |--------------------------------------------------------------------------
  */
  pagination?.addEventListener(
    'click',
    event => {
      const pageButton =
        event.target.closest('[data-page]');

      if (
        !pageButton
        || pageButton.disabled
      ) {
        return;
      }

      const requestedPage =
        Number(pageButton.dataset.page);

      if (
        !Number.isInteger(requestedPage)
        || requestedPage < 1
      ) {
        return;
      }

      dashboardState.currentPage =
        requestedPage;

      dashboardState.scrollToEntryId = null;

      renderDashboardView();

      document
        .querySelector('.waiting-list')
        ?.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
    }
  );

  /*
  |--------------------------------------------------------------------------
  | Retour immédiat au patient actuel FIFO
  |--------------------------------------------------------------------------
  */
  currentPatientButton?.addEventListener(
    'click',
    focusCurrentPatient
  );
}
/*
|--------------------------------------------------------------------------
| Initialiser la page Liste du jour
|--------------------------------------------------------------------------
*/
function initDashboardPage() {
  resetDashboardViewState();

  bindAddPatientModalEvents();
  bindQueueActionModalEvents();
  bindToggleListButton();
  bindQueueDayControls();
  bindDashboardListControls();
  bindPatientDetailsEvents();

  /*
  |--------------------------------------------------------------------------
  | Au premier affichage, sélectionner automatiquement
  | le premier patient en attente.
  |--------------------------------------------------------------------------
  */
  loadDashboardData({
    focusCurrent: true
  });
}

/*
|--------------------------------------------------------------------------
| Charger les données du dashboard
|--------------------------------------------------------------------------
| focusCurrent :
| demande à l’interface de revenir automatiquement au prochain patient
| en attente après Terminer ou Absent.
|--------------------------------------------------------------------------
*/
async function loadDashboardData({ focusCurrent = false } = {}) {
  try {
    const response = await fetch(
      QUEUE_ENTRIES_API_URL
    );

    const result =
      await parseJsonResponseSafely(response);

    if (!response.ok || !result.ok) {
      throw new Error(
        result?.message
        || 'Impossible de charger le dashboard.'
      );
    }

    dashboardState.queue =
      result.data.queue;

    /*
    |--------------------------------------------------------------------------
    | Sécuriser l’ordre FIFO côté front
    |--------------------------------------------------------------------------
    | Même si l’API trie déjà les données, le front conserve explicitement
    | l’ordre basé sur le numéro d’inscription.
    |--------------------------------------------------------------------------
    */
    dashboardState.entries = [
      ...(result.data.entries ?? [])
    ].sort(
      (left, right) =>
        Number(left.number)
        - Number(right.number)
    );

    const currentEntry =
      getCurrentWaitingEntry();

    const selectedEntryStillExists =
      dashboardState.entries.some(
        entry =>
          String(entry.id)
          === String(
            dashboardState.selectedEntryId
          )
      );

    /*
    |--------------------------------------------------------------------------
    | Premier chargement ou passage au patient suivant
    |--------------------------------------------------------------------------
    */
    if (
      focusCurrent
      || dashboardState.selectedEntryId === null
    ) {
      dashboardState.selectedEntryId =
        currentEntry?.id
        ?? dashboardState.entries[0]?.id
        ?? null;

      if (currentEntry) {
        ensureDashboardEntryIsVisible(
          currentEntry.id
        );

        dashboardState.scrollToEntryId =
          currentEntry.id;
      }
    } else if (!selectedEntryStillExists) {
      dashboardState.selectedEntryId =
        currentEntry?.id
        ?? dashboardState.entries[0]?.id
        ?? null;
    }

    updateQueueStatusBadge(
      dashboardState.queue
    );

    renderDashboardCounters(
      result.data.counts
    );

    syncDashboardControls();
    renderDashboardView();
  } catch (error) {
    console.error(
      'Erreur dashboard :',
      error
    );

    const tableBody =
      document.getElementById(
        'day-list-table-body'
      );

    if (tableBody) {
      tableBody.innerHTML = `
        <tr>
          <td
            colspan="6"
            class="table-empty-state"
          >
            Impossible de charger les données.
          </td>
        </tr>
      `;
    }
  }
}
/*
|--------------------------------------------------------------------------
| Mettre à jour le bouton Nouveau patient
|--------------------------------------------------------------------------
*/
function updateAddPatientButtonState(queue) {
  const addPatientButton = document.getElementById('openAddPatientModalBtn');

  if (!addPatientButton) return;

  const canRegister =
    queue?.registration_status === 'open'
    && queue?.day_status === 'active';

  addPatientButton.disabled = !canRegister;

  const buttonLabel = addPatientButton.querySelector('span');

  if (buttonLabel) {
    if (queue?.day_status === 'completed') {
      buttonLabel.textContent = 'Journée clôturée';
    } else if (queue?.day_status === 'paused') {
      buttonLabel.textContent = 'Liste en pause';
    } else if (queue?.registration_status === 'closed') {
      buttonLabel.textContent = 'Inscriptions fermées';
    } else {
      buttonLabel.textContent = 'Nouveau patient';
    }
  }

  addPatientButton.title = canRegister
    ? 'Ajouter un nouveau patient à la liste du jour'
    : 'Les nouvelles inscriptions sont actuellement indisponibles';
}

/*
|--------------------------------------------------------------------------
| Mettre à jour les deux états de la queue
|--------------------------------------------------------------------------
*/
function updateQueueStatusBadge(queue) {
  const dayBadge = document.getElementById('day-status-badge');
  const registrationBadge = document.getElementById(
    'registration-status-badge'
  );
  const toggleButton = document.getElementById('toggle-list-btn');
  const menuButton = document.getElementById('queue-day-menu-btn');
  const pauseButton = document.getElementById('pause-day-btn');
  const resumeButton = document.getElementById('resume-day-btn');
  const completeButton = document.getElementById('complete-day-btn');
  const reopenButton = document.getElementById(
    'reopen-completed-day-btn'
  );

  if (!queue) return;

  const dayStatus = queue.day_status;
  const registrationsOpen = queue.registration_status === 'open';
  const isCompleted = dayStatus === 'completed';
  const isPaused = dayStatus === 'paused';

  if (dayBadge) {
    dayBadge.classList.remove(
      'list-status-badge--open',
      'list-status-badge--paused',
      'list-status-badge--closed'
    );

    if (isCompleted) {
      dayBadge.textContent = 'Journée clôturée';
      dayBadge.classList.add('list-status-badge--closed');
    } else if (isPaused) {
      dayBadge.textContent = 'Liste en pause';
      dayBadge.classList.add('list-status-badge--paused');
    } else {
      dayBadge.textContent = 'Liste active';
      dayBadge.classList.add('list-status-badge--open');
    }
  }

  if (registrationBadge) {
    registrationBadge.textContent = registrationsOpen
      ? 'Inscriptions ouvertes'
      : 'Inscriptions fermées';

    registrationBadge.classList.toggle(
      'registration-status-badge--open',
      registrationsOpen
    );
    registrationBadge.classList.toggle(
      'registration-status-badge--closed',
      !registrationsOpen
    );
  }

  if (toggleButton) {
    toggleButton.textContent = registrationsOpen
      ? 'Fermer les inscriptions'
      : 'Rouvrir les inscriptions';

    toggleButton.disabled = isCompleted || isPaused;
    toggleButton.classList.toggle(
      'btn-toggle-list--close',
      registrationsOpen
    );
    toggleButton.classList.toggle(
      'btn-toggle-list--open',
      !registrationsOpen
    );
  }

  if (menuButton) {
    const menuWrapper = menuButton.closest(
      '.queue-day-menu-wrapper'
    );

    menuButton.disabled = false;

    if (menuWrapper) {
      menuWrapper.hidden = isCompleted;
    }

    if (isCompleted) {
      closeQueueDayMenu();
    }
  }

  if (reopenButton) {
    reopenButton.hidden = !isCompleted;
    reopenButton.disabled = false;
  }

  if (pauseButton) {
    pauseButton.hidden = isPaused || isCompleted;
  }

  if (resumeButton) {
    resumeButton.hidden = !isPaused || isCompleted;
  }

  if (completeButton) {
    completeButton.disabled = isCompleted;
  }

  updateAddPatientButtonState(queue);
}

/*
|--------------------------------------------------------------------------
| Construire le tableau visible
|--------------------------------------------------------------------------
| La liste conserve toujours son ordre FIFO.
|--------------------------------------------------------------------------
*/
function renderDashboardTable(entries) {
  const tableBody = document.getElementById('day-list-table-body');

  if (!tableBody) return;

  if (!entries || entries.length === 0) {
    const message = dashboardState.entries.length === 0
      ? 'Aucun patient pour aujourd’hui.'
      : 'Aucun patient ne correspond à la recherche.';

    tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="table-empty-state">${message}</td>
      </tr>
    `;
    return;
  }

  const currentEntry = getCurrentWaitingEntry();
  const currentEntryId = currentEntry?.id ?? null;
  const dayStatus = dashboardState.queue?.day_status;
  const canProcessPatients = dayStatus === 'active';
  const canEdit = dayStatus !== 'completed';

  tableBody.innerHTML = entries.map(entry => {
    const isCurrent = String(entry.id) === String(currentEntryId);
    const isSelected = String(entry.id) === String(
      dashboardState.selectedEntryId
    );
    const isWaiting = ['waiting', 'called'].includes(entry.status);
    const canChangeWaitingStatus = canProcessPatients && isWaiting;
    const canReturnToWaiting =
      canProcessPatients && entry.status === 'no_show';

    const rowClasses = [
      'patient-row',
      isCurrent ? 'is-current' : '',
      isSelected ? 'is-selected' : ''
    ].filter(Boolean).join(' ');

    let statusActions = '';

    if (isWaiting) {
      statusActions = `
        <button
          class="btn-action-icon btn-action-icon--absent"
          type="button"
          title="Marquer absent"
          ${canChangeWaitingStatus ? '' : 'disabled'}
        >
          <span aria-hidden="true">✕</span>
        </button>

        <button
          class="btn-action-icon btn-action-icon--done"
          type="button"
          title="Terminer"
          ${canChangeWaitingStatus ? '' : 'disabled'}
        >
          <span aria-hidden="true">✓</span>
        </button>

        <button
          class="btn-action-icon btn-action-icon--cancel"
          type="button"
          title="Annuler l’inscription"
          ${canChangeWaitingStatus ? '' : 'disabled'}
        >
          <span aria-hidden="true">⊘</span>
        </button>
      `;
    } else if (entry.status === 'no_show') {
      statusActions = `
        <button
          class="btn-action-icon btn-action-icon--return"
          type="button"
          title="Remettre en attente à la fin de la file"
          ${canReturnToWaiting ? '' : 'disabled'}
        >
          <span aria-hidden="true">↩</span>
        </button>
      `;
    }

    return `
      <tr class="${rowClasses}" data-entry-id="${entry.id}">
        <td>${entry.number}</td>
        <td class="patient-name-cell">
          ${escapeHtml(entry.display_name ?? '')}
        </td>
        <td class="patient-phone-cell">
          ${escapeHtml(window.MarkiPhone ? window.MarkiPhone.formatMobile(entry.phone ?? '') : (entry.phone ?? '-'))}
        </td>
        <td>${escapeHtml(entry.time ?? '-')}</td>
        <td>${renderStatusPill(entry.status)}</td>
        <td>
          <div class="table-actions">
            <button
              class="btn-action-icon btn-action-icon--view"
              type="button"
              title="Voir les détails"
              aria-label="Voir les détails de ${escapeHtml(entry.display_name ?? '')}"
            >
              <span aria-hidden="true">👁</span>
            </button>

            <button
              class="btn-action-icon btn-action-icon--edit"
              type="button"
              title="Modifier"
              ${canEdit ? '' : 'disabled'}
            >
              <span aria-hidden="true">✎</span>
            </button>

            ${statusActions}
          </div>
        </td>
      </tr>
    `;
  }).join('');

  bindPatientRowEvents(entries);
}
/*
|--------------------------------------------------------------------------
| Rendu central du dashboard
|--------------------------------------------------------------------------
| Ordre :
| 1. recherche et filtre
| 2. pagination
| 3. tableau
| 4. détails
| 5. bouton patient actuel
|--------------------------------------------------------------------------
*/
function renderDashboardView() {
  const filteredEntries =
    getFilteredDashboardEntries();

  const totalPages =
    getDashboardTotalPages(filteredEntries);

  /*
  |--------------------------------------------------------------------------
  | Empêcher une page inexistante après un filtre
  |--------------------------------------------------------------------------
  */
  dashboardState.currentPage = Math.min(
    Math.max(1, dashboardState.currentPage),
    totalPages
  );

  const pageEntries =
    getCurrentDashboardPageEntries(
      filteredEntries
    );

  /*
  |--------------------------------------------------------------------------
  | Si le patient sélectionné n’est pas présent sur cette page,
  | sélectionner automatiquement la première ligne visible.
  |--------------------------------------------------------------------------
  */
  const selectedEntryIsVisible =
    pageEntries.some(
      entry =>
        String(entry.id)
        === String(
          dashboardState.selectedEntryId
        )
    );

  if (!selectedEntryIsVisible) {
    dashboardState.selectedEntryId =
      pageEntries[0]?.id ?? null;
  }

  renderDashboardTable(pageEntries);

  renderDashboardPagination(totalPages);

  renderDashboardResultsCount(
    filteredEntries.length
  );

  renderCurrentPatientButton();

  syncDashboardControls();

  const selectedEntry = findEntryById(
    dashboardState.entries,
    dashboardState.selectedEntryId
  );

  updatePatientDetails(selectedEntry);

  /*
  |--------------------------------------------------------------------------
  | Après Terminer, Absent ou clic sur Patient actuel,
  | rendre automatiquement la bonne ligne visible.
  |--------------------------------------------------------------------------
  */
  const entryIdToScroll =
    dashboardState.scrollToEntryId;

  dashboardState.scrollToEntryId = null;

  if (entryIdToScroll !== null) {
    requestAnimationFrame(() => {
      scrollDashboardEntryIntoView(
        entryIdToScroll
      );
    });
  }
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

    if (status === 'canceled') {
        return '<span class="status-pill status-pill--canceled">Annulé</span>';
    }

    if (status === 'called') {
        return '<span class="status-pill status-pill--waiting">Appelé</span>';
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

function formatVisitDate(value) {
  if (!value) return '-';

  const date = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return escapeHtml(String(value));
  }

  return new Intl.DateTimeFormat('fr-DZ', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  }).format(date);
}

function formatRelativeVisitDate(value) {
  const match = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return '';

  const timezone = document.querySelector('meta[name="marki-timezone"]')?.content
    || 'Africa/Algiers';
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).formatToParts(new Date());
  const part = type => Number(parts.find(item => item.type === type)?.value || 0);
  const todayUtc = Date.UTC(part('year'), part('month') - 1, part('day'));
  const visitUtc = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  const days = Math.max(0, Math.round((todayUtc - visitUtc) / 86400000));

  if (days === 0) return 'Aujourd’hui';
  if (days === 1) return 'Hier';
  if (days < 31) return `Il y a ${days} jours`;
  const months = Math.floor(days / 30);
  if (months < 12) return `Il y a ${months} mois`;
  const years = Math.floor(days / 365);
  return `Il y a ${years} an${years > 1 ? 's' : ''}`;
}

function getVisitStatusLabel(status) {
  if (status === 'done') return 'Terminée';
  if (status === 'in_progress') return 'En cours';
  if (status === 'canceled') return 'Annulée';
  return status || '-';
}

function renderPatientHistory(visits) {
  if (!Array.isArray(visits) || visits.length === 0) {
    return renderDefaultPatientHistory();
  }

  return visits.map(visit => `
    <li>
      <span class="visit-date-stack">
        <span>${formatVisitDate(visit.visit_at)}</span>
        <small>${formatRelativeVisitDate(visit.visit_at)}</small>
      </span>
      <strong class="history-status history-status--${escapeHtml(visit.status)}">
        ${escapeHtml(getVisitStatusLabel(visit.status))}
      </strong>
    </li>
  `).join('');
}

function openSelectedPatientFullRecord() {
  const button = document.getElementById('view-full-patient-record-btn');

  const patientId = Number(button?.dataset.patientId || 0);

  if (patientId <= 0) {
    showToast('Aucune fiche patient liée à cette inscription.','error');
    return;
  }

  if (typeof window.openPatientProfile === 'function') {
    window.openPatientProfile(patientId);
    return;
  }

  sessionStorage.setItem('marki.openPatientId',String(patientId));

  setActiveMenuItem('patients');
  loadPage('patients');
}

function bindPatientDetailsEvents() {
  document
    .getElementById('view-full-patient-record-btn')
    ?.addEventListener('click', openSelectedPatientFullRecord);
}

function updatePatientDetails(entry) {
  const emptyState = document.getElementById('patient-details-empty');
  const detailsContent = document.getElementById('patient-details-content');
  const nameEl = document.getElementById('patient-details-name');
  const phoneEl = document.getElementById('patient-details-phone');
  const birthDateEl = document.getElementById('patient-details-birth-date');
  const sourceEl = document.getElementById('patient-details-source');
  const statusEl = document.getElementById('patient-details-status');
  const notesEl = document.getElementById('patient-details-notes');
  const historyEl = document.getElementById('patient-details-history');
  const fullRecordButton = document.getElementById(
    'view-full-patient-record-btn'
  );

  if (
    !emptyState
    || !detailsContent
    || !nameEl
    || !phoneEl
    || !birthDateEl
    || !sourceEl
    || !statusEl
    || !notesEl
    || !historyEl
    || !fullRecordButton
  ) {
    return;
  }

  if (!entry) {
    emptyState.hidden = false;
    detailsContent.hidden = true;
    fullRecordButton.hidden = !document.querySelector('.sidebar__item[data-page="patients"]');
    fullRecordButton.disabled = true;
    delete fullRecordButton.dataset.patientId;
    return;
  }

  emptyState.hidden = true;
  detailsContent.hidden = false;

  nameEl.textContent = entry.display_name || '-';
  phoneEl.textContent = window.MarkiPhone ? window.MarkiPhone.formatMobile(entry.phone || '') : (entry.phone || '-');
  birthDateEl.textContent = formatBirthDate(entry.birth_date);
  sourceEl.textContent = formatSourceLabel(entry.source);
  statusEl.innerHTML = renderDetailStatusPill(entry.status);

  notesEl.textContent = entry.patient_notes?.trim()
    || 'Aucune note disponible pour le moment.';

  historyEl.innerHTML = renderPatientHistory(
    entry.recent_visits
  );

  const patientId = Number(entry.patient_id || 0);
  const canViewPatients = Boolean(
    document.querySelector('.sidebar__item[data-page="patients"]')
  );

  fullRecordButton.hidden = !canViewPatients;
  fullRecordButton.disabled = !canViewPatients || patientId <= 0;

  if (canViewPatients && patientId > 0) {
    fullRecordButton.dataset.patientId = String(patientId);
  } else {
    delete fullRecordButton.dataset.patientId;
  }
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
/*
|--------------------------------------------------------------------------
| Exécuter une action de statut
|--------------------------------------------------------------------------
| Le bouton est désactivé pendant la requête afin d’éviter les doubles clics.
|--------------------------------------------------------------------------
*/
async function handleQueueEntryStatusAction(
  button,
  entry,
  newStatus,
  extraPayload = {}
) {
  if (!button || button.disabled) return;

  button.disabled = true;

  try {
    const result = await updateQueueEntryStatus(
      entry.id,
      newStatus,
      extraPayload
    );

    showToast(result.message, 'success');

    await loadDashboardData({
      focusCurrent: ['done', 'no_show', 'canceled'].includes(newStatus)
    });
  } catch (error) {
    console.error('Erreur de changement de statut :', error);
    showToast(
      error.message || 'Impossible de modifier le statut.',
      'error'
    );
    button.disabled = false;
  }
}

/*
|--------------------------------------------------------------------------
| Brancher les événements des lignes
|--------------------------------------------------------------------------
*/
function bindPatientRowEvents(entries) {
  const rows = document.querySelectorAll('.patient-row');

  rows.forEach(row => {
    const entry = findEntryById(entries, row.dataset.entryId);

    if (!entry) return;

    row.addEventListener('click', event => {
      if (event.target.closest('.btn-action-icon')) return;
      selectDashboardEntry(entry.id);
    });

    row.querySelector('.btn-action-icon--view')?.addEventListener(
      'click',
      event => {
        event.preventDefault();
        event.stopPropagation();
        selectDashboardEntry(entry.id);
      }
    );

    const editButton = row.querySelector('.btn-action-icon--edit');
    editButton?.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();

      if (!editButton.disabled) {
        openEditPatientModal(entry);
      }
    });

    const absentButton = row.querySelector('.btn-action-icon--absent');
    absentButton?.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      handleQueueEntryStatusAction(absentButton, entry, 'no_show');
    });

    const doneButton = row.querySelector('.btn-action-icon--done');
    doneButton?.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      handleQueueEntryStatusAction(doneButton, entry, 'done');
    });

    const cancelButton = row.querySelector('.btn-action-icon--cancel');
    cancelButton?.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();

      openQueueActionConfirmation({
        title: 'Annuler cette inscription ?',
        message: `${entry.display_name} ne passera pas chez le médecin. Choisissez la raison à conserver dans l’historique.`,
        confirmLabel: 'Annuler l’inscription',
        defaultReason: 'patient_request',
        reasonOptions: [
          {
            value: 'patient_request',
            label: 'Annulation demandée par le patient'
          },
          {
            value: 'registration_error',
            label: 'Erreur d’inscription'
          },
          {
            value: 'doctor_unavailable',
            label: 'Médecin indisponible'
          },
          {
            value: 'other',
            label: 'Autre raison'
          }
        ],
        onConfirm: async ({ reason }) => {
          await handleQueueEntryStatusAction(
            cancelButton,
            entry,
            'canceled',
            {
              cancellation_reason: reason || 'other'
            }
          );
        }
      });
    });

    const returnButton = row.querySelector('.btn-action-icon--return');
    returnButton?.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();

      openQueueActionConfirmation({
        title: 'Remettre le patient en attente ?',
        message: `${entry.display_name} sera replacé à la fin de la file afin de respecter les patients qui ont continué à attendre.`,
        confirmLabel: 'Remettre en attente',
        confirmType: 'primary',
        onConfirm: async () => {
          await handleQueueEntryStatusAction(
            returnButton,
            entry,
            'waiting'
          );
        }
      });
    });
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
    const canceledEl = document.getElementById('counter-canceled');

    if (waitingEl) waitingEl.textContent = counts.waiting ?? 0;
    if (absentEl) absentEl.textContent = counts.absent ?? 0;
    if (doneEl) doneEl.textContent = counts.done ?? 0;
    if (canceledEl) canceledEl.textContent = counts.canceled ?? 0;
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
      phoneInput.value = window.MarkiPhone ? window.MarkiPhone.formatMobile(entry?.phone ?? '') : (entry?.phone ?? '');
      window.MarkiPhone?.bind(modal);
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