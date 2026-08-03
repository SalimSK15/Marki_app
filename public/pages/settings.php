<?php

declare(strict_types=1);

header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

$context = require __DIR__ . '/../../app/web_bootstrap.php';
$capabilities = $context['capabilities'] ?? [];

if (!($capabilities['settings.view'] ?? false)) {
    http_response_code(403);
    echo '<section class="v1-page"><div class="v1-message is-error">Vous n’avez pas accès aux paramètres.</div></section>';
    exit;
}

$canManageQr = (bool) ($capabilities['settings.manage_doctor'] ?? false);
$canManageTeam = (bool) ($capabilities['team.manage'] ?? false);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?>
<section class="v1-page v1-settings-page" aria-labelledby="settings-page-title">
  <header class="v1-page__header">
    <div>
      <p class="v1-page__eyebrow">Informations générales</p>
      <h2 id="settings-page-title">Paramètres</h2>
      <p>Gérez les informations de la structure, le profil du médecin et les accès de l’équipe.</p>
    </div>
  </header>

  <div id="settings-page-message" class="v1-message" role="status" aria-live="polite"></div>

  <form id="settings-form" class="v1-settings-grid" novalidate>
    <section class="v1-card" id="clinic-settings-card" aria-labelledby="clinic-settings-title">
      <div class="v1-card__header">
        <div>
          <p class="v1-page__eyebrow">Structure</p>
          <h3 id="clinic-settings-title">Cabinet ou clinique</h3>
        </div>
      </div>

      <div class="v1-form__grid">
        <label class="v1-field v1-field--wide">
          <span>Nom de la structure <strong class="required-mark" aria-hidden="true">*</strong></span>
          <input type="text" id="settings-clinic-name" name="clinic_name" required>
        </label>

        <label class="v1-field">
          <span>Type de structure</span>
          <select id="settings-clinic-type" name="clinic_type">
            <option value="solo">Cabinet individuel</option>
            <option value="clinic">Clinique</option>
            <option value="hospital_simple">Établissement médical</option>
          </select>
        </label>

        <label class="v1-field">
          <span>Téléphone de la structure</span>
          <input type="tel" id="settings-clinic-phone" name="clinic_phone" inputmode="tel" data-dz-phone-auto>
        </label>

        <label class="v1-field v1-field--wide">
          <span>Adresse</span>
          <input type="text" id="settings-clinic-address" name="clinic_address" autocomplete="street-address">
        </label>

        <div class="v1-location-fields" data-algeria-location-group>
          <label class="v1-field">
            <span>Wilaya / région</span>
            <select id="settings-clinic-wilaya" name="clinic_wilaya" data-algeria-wilaya>
              <option value="">Choisir une wilaya</option>
            </select>
          </label>

          <label class="v1-field">
            <span>Ville / commune</span>
            <input type="text" id="settings-clinic-city" name="clinic_city" list="settings-clinic-city-options" data-algeria-city autocomplete="address-level2">
            <datalist id="settings-clinic-city-options"></datalist>
          </label>
        </div>

        <label class="v1-field v1-field--wide">
          <span>Fuseau horaire <strong class="required-mark" aria-hidden="true">*</strong></span>
          <span class="timezone-select-wrap">
            <img id="settings-timezone-flag" class="timezone-flag" src="assets/icons/flags/dz.svg" alt="">
            <select id="settings-clinic-timezone" name="clinic_timezone" required>
              <option value="Africa/Algiers">Algérie — Africa/Algiers</option>
              <option value="America/Toronto">Canada — America/Toronto</option>
              <option value="Europe/Paris">France — Europe/Paris</option>
              <option value="Africa/Tunis">Tunisie — Africa/Tunis</option>
              <option value="America/New_York">États-Unis — America/New_York</option>
            </select>
          </span>
          <small>Les dates et les heures seront affichées selon le fuseau choisi.</small>
        </label>
      </div>
    </section>

    <section class="v1-card" id="doctor-settings-card" aria-labelledby="doctor-settings-title">
      <div class="v1-card__header">
        <div>
          <p class="v1-page__eyebrow">Profil professionnel</p>
          <h3 id="doctor-settings-title">Profil du médecin</h3>
        </div>
      </div>

      <div class="v1-form__grid">
        <label class="v1-field v1-field--wide">
          <span>Nom affiché <strong class="required-mark" aria-hidden="true">*</strong></span>
          <input type="text" id="settings-doctor-name" name="doctor_display_name" required>
        </label>

        <label class="v1-field v1-field--wide">
          <span>Spécialité</span>
          <input type="text" id="settings-doctor-specialty" name="doctor_specialty">
        </label>

        <label class="v1-field v1-field--wide">
          <span>Numéro d’agrément / licence</span>
          <input type="text" id="settings-doctor-license" name="doctor_license_number">
        </label>

        <label class="v1-field v1-field--wide">
          <span>Adresse professionnelle</span>
          <input type="text" id="settings-doctor-address" name="doctor_address">
        </label>
      </div>
    </section>

    <div class="v1-settings-actions" id="settings-actions">
      <p id="settings-context-note">Vérifiez les informations avant de les enregistrer.</p>
      <button type="submit" class="v1-button v1-button--primary" id="settings-save-button">
        Enregistrer les paramètres
      </button>
    </div>
  </form>

  <?php if ($canManageQr): ?>
  <section id="public-registration-section" class="v1-card qr-admin-card v1-collapsible-section is-collapsed" aria-labelledby="public-registration-title">
    <div class="v1-card__header qr-admin-card__header">
      <div>
        <p class="v1-page__eyebrow">Inscription publique</p>
        <h3 id="public-registration-title">QR code du médecin</h3>
        <p>Permettez aux patients de rejoindre la liste d’attente depuis leur téléphone.</p>
        <p class="qr-admin-scope-note">Ce QR est lié au médecin sélectionné. Dans une clinique, chaque médecin possède son propre QR à afficher sur sa porte, à la réception ou dans ses messages aux patients.</p>
      </div>
      <div class="v1-collapsible-section__actions">
        <span id="qr-admin-status" class="qr-admin-status">Chargement…</span>
        <button
          type="button"
          class="v1-button v1-button--secondary v1-section-toggle"
          data-settings-section-toggle="public-registration-section"
          aria-expanded="false"
          aria-controls="public-registration-section-body"
        >
          <span data-section-toggle-label>Afficher</span>
          <span class="v1-section-toggle__chevron" aria-hidden="true">
            <svg viewBox="0 0 20 20" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
      </div>
    </div>

    <div id="public-registration-section-body" class="v1-collapsible-section__body">
    <div id="qr-admin-message" class="v1-message" role="status" aria-live="polite"></div>

    <div class="qr-admin-layout">
      <section class="qr-admin-preview" aria-labelledby="qr-preview-title">
        <div class="qr-admin-preview__top">
          <div>
            <p class="v1-page__eyebrow">QR permanent</p>
            <h4 id="qr-preview-title">Affiche à imprimer</h4>
          </div>
          <span id="qr-token-version" class="v1-soft-badge">Version —</span>
        </div>

        <div id="qr-code-canvas" class="qr-code-canvas" aria-label="QR code d’inscription"></div>
        <p class="qr-admin-doctor" id="qr-admin-doctor-name">—</p>
        <p class="qr-admin-clinic" id="qr-admin-clinic-name">—</p>
        <p class="qr-admin-scan-hint">Scannez pour rejoindre la liste d’attente</p>

        <label class="v1-field qr-admin-link-field">
          <span>Lien public</span>
          <input type="text" id="qr-public-url" readonly>
        </label>

        <div class="qr-admin-actions">
          <button type="button" class="v1-button v1-button--secondary" id="qr-copy-link">Copier le lien</button>
          <button type="button" class="v1-button v1-button--secondary" id="qr-open-link">Tester la page</button>
          <button type="button" class="v1-button v1-button--secondary" id="qr-download">Télécharger</button>
          <button type="button" class="v1-button v1-button--secondary" id="qr-print">Imprimer</button>
        </div>

        <p class="qr-admin-network-warning" id="qr-network-warning" hidden>
          Pour tester le QR avec un téléphone sur le réseau local, ouvrez MARKI avec l’adresse IP de cet ordinateur avant de télécharger le QR.
        </p>
      </section>

      <div class="qr-admin-settings">
        <section class="qr-admin-panel">
          <div class="qr-admin-panel__header">
            <div>
              <h4>Disponibilité</h4>
              <p>Le QR imprimé reste identique lors d’une désactivation temporaire.</p>
            </div>
            <button type="button" class="v1-button v1-button--primary" id="qr-toggle-button">Activer</button>
          </div>

          <div class="qr-admin-metrics">
            <div><span>Scans aujourd’hui</span><strong id="qr-scans-today">0</strong></div>
            <div><span>Inscriptions aujourd’hui</span><strong id="qr-registrations-today">0</strong></div>
            <div><span>Dernier scan</span><strong id="qr-last-scan">—</strong></div>
          </div>
        </section>

        <form id="qr-settings-form" class="qr-admin-panel" novalidate>
          <div class="qr-admin-panel__header">
            <div>
              <h4>Règles d’inscription</h4>
              <p>Le téléphone et le consentement restent obligatoires pour la V1.</p>
            </div>
          </div>

          <div class="v1-form__grid">
            <label class="qr-admin-check v1-field--wide">
              <input type="checkbox" id="qr-birth-date-required" name="birth_date_required">
              <span>Rendre la date de naissance obligatoire</span>
            </label>

            <label class="v1-field">
              <span>Limite d’inscriptions QR par jour</span>
              <input type="number" id="qr-max-registrations" name="max_public_registrations_per_day" min="1" max="1000" placeholder="Sans limite">
              <small class="v1-field-error" data-qr-error="max_public_registrations_per_day"></small>
            </label>

            <label class="v1-field">
              <span>Durée du lien de suivi</span>
              <select id="qr-session-duration" name="public_session_duration_minutes">
                <option value="120">2 heures</option>
                <option value="360">6 heures</option>
                <option value="720">12 heures</option>
                <option value="1440">24 heures</option>
                <option value="4320">3 jours</option>
                <option value="10080">7 jours</option>
              </select>
              <small class="v1-field-error" data-qr-error="public_session_duration_minutes"></small>
            </label>
          </div>

          <details class="qr-admin-messages">
            <summary>Personnaliser les messages publics</summary>
            <div class="qr-admin-messages__grid">
              <label class="v1-field">
                <span>Liste du jour non ouverte</span>
                <textarea id="qr-message-day-not-open" rows="3" maxlength="1000"></textarea>
              </label>
              <label class="v1-field">
                <span>Inscriptions ouvertes</span>
                <textarea id="qr-message-open" rows="3" maxlength="1000"></textarea>
              </label>
              <label class="v1-field">
                <span>Inscriptions fermées</span>
                <textarea id="qr-message-closed" rows="3" maxlength="1000"></textarea>
              </label>
              <label class="v1-field">
                <span>Liste en pause</span>
                <textarea id="qr-message-paused" rows="3" maxlength="1000"></textarea>
              </label>
              <label class="v1-field">
                <span>Journée terminée</span>
                <textarea id="qr-message-completed" rows="3" maxlength="1000"></textarea>
              </label>
              <label class="v1-field">
                <span>QR désactivé</span>
                <textarea id="qr-message-disabled" rows="3" maxlength="1000"></textarea>
              </label>
              <label class="v1-field">
                <span>En dehors des horaires</span>
                <textarea id="qr-message-outside-schedule" rows="3" maxlength="1000"></textarea>
              </label>
              <label class="v1-field">
                <span>Inscription confirmée</span>
                <textarea id="qr-message-success" rows="3" maxlength="1000"></textarea>
              </label>
            </div>
          </details>

          <div class="qr-admin-form-actions">
            <button type="submit" class="v1-button v1-button--primary" id="qr-save-settings">Enregistrer les réglages</button>
            <button type="button" class="v1-button v1-button--danger-outline" id="qr-regenerate">Régénérer pour sécurité</button>
          </div>
        </form>
      </div>
    </div>
    </div>
  </section>

  <div id="qr-confirm-modal" class="team-modal" hidden aria-hidden="true">
    <button type="button" class="team-modal__backdrop" data-close-qr-confirm aria-label="Fermer"></button>
    <div class="team-modal__dialog team-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="qr-confirm-title">
      <div class="team-form__header">
        <div>
          <p class="v1-page__eyebrow">Sécurité</p>
          <h4 id="qr-confirm-title">Confirmer l’action</h4>
        </div>
        <button type="button" class="v1-icon-button" data-close-qr-confirm aria-label="Fermer">×</button>
      </div>
      <p id="qr-confirm-message"></p>
      <div class="v1-form__actions">
        <button type="button" class="v1-button v1-button--secondary" data-close-qr-confirm>Annuler</button>
        <button type="button" class="v1-button v1-button--primary" id="qr-confirm-action">Confirmer</button>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($canManageTeam): ?>
  <section id="team-settings-section" class="v1-card team-card v1-collapsible-section is-collapsed" aria-labelledby="team-settings-title">
    <div class="v1-card__header team-card__header">
      <div>
        <p class="v1-page__eyebrow">Équipe et accès</p>
        <h3 id="team-settings-title">Comptes de la structure</h3>
        <p>Chaque personne utilise son propre compte. Un compte désactivé conserve tout son historique.</p>
      </div>
      <div class="v1-collapsible-section__actions">
        <button
          type="button"
          class="v1-button v1-button--secondary v1-section-toggle"
          data-settings-section-toggle="team-settings-section"
          aria-expanded="false"
          aria-controls="team-settings-section-body"
        >
          <span data-section-toggle-label>Afficher</span>
          <span class="v1-section-toggle__chevron" aria-hidden="true">
            <svg viewBox="0 0 20 20" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
        <button type="button" class="v1-button v1-button--primary" id="team-new-account-button">
          Nouveau compte
        </button>
      </div>
    </div>

    <div id="team-settings-section-body" class="v1-collapsible-section__body">
    <div id="team-message" class="v1-message" role="status" aria-live="polite"></div>

    <div class="v1-table-wrap team-table-wrap">
      <table class="v1-table team-table">
        <thead>
          <tr>
            <th>Utilisateur</th>
            <th>Type</th>
            <th>Accès</th>
            <th>État</th>
            <th>Dernière connexion</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="team-table-body">
          <tr><td colspan="6" class="v1-empty-cell">Chargement de l’équipe…</td></tr>
        </tbody>
      </table>
    </div>
    </div>
  </section>

  <div id="team-account-modal" class="team-modal" hidden aria-hidden="true">
    <button
      type="button"
      class="team-modal__backdrop"
      id="team-account-modal-backdrop"
      aria-label="Fermer"
    ></button>

    <div class="team-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="team-form-title">
      <form id="team-account-form" class="v1-form team-form" novalidate>
        <div class="team-form__header">
          <div>
            <p class="v1-page__eyebrow">Compte</p>
            <h4 id="team-form-title">Nouveau compte</h4>
          </div>
          <button type="button" class="v1-icon-button" id="team-form-close-button" aria-label="Fermer">×</button>
        </div>

        <input type="hidden" id="team-user-id" name="user_id">

        <div class="v1-form__grid">
          <label class="v1-field v1-field--wide">
            <span>Nom complet <strong class="required-mark" aria-hidden="true">*</strong></span>
            <input type="text" id="team-full-name" name="full_name" required>
            <small class="v1-field-error" data-team-error="full_name"></small>
          </label>

          <label class="v1-field">
            <span>Type de compte <strong class="required-mark" aria-hidden="true">*</strong></span>
            <select id="team-account-type" name="account_type" required>
              <option value="secretary">Secrétariat</option>
              <option value="doctor">Médecin</option>
            </select>
            <small class="v1-field-error" data-team-error="account_type"></small>
          </label>

          <label class="v1-field">
            <span>Courriel</span>
            <input type="email" id="team-email" name="email" autocomplete="email">
            <small class="v1-field-error" data-team-error="email"></small>
          </label>

          <label class="v1-field">
            <span>Téléphone</span>
            <input
              type="tel"
              id="team-phone"
              name="phone"
              inputmode="numeric"
              autocomplete="tel"
              maxlength="13"
              placeholder="0550 80 30 90"
              data-dz-mobile
            >
            <small class="v1-field-hint">Format local : 0550 80 30 90</small>
            <small class="v1-field-error" data-team-error="phone"></small>
          </label>

          <label class="v1-field v1-field--wide">
            <span>Mot de passe temporaire</span>
            <input type="password" id="team-temporary-password" name="temporary_password" autocomplete="new-password">
            <small class="v1-field-hint" id="team-password-hint">10 caractères minimum, avec majuscule, minuscule et chiffre.</small>
            <small class="v1-field-error" data-team-error="temporary_password"></small>
          </label>

          <label class="v1-field v1-field--wide" id="team-job-title-field">
            <span>Fonction</span>
            <input type="text" id="team-job-title" name="job_title" placeholder="Secrétaire médicale">
            <small class="v1-field-error" data-team-error="job_title"></small>
          </label>

          <label class="v1-field v1-field--wide" id="team-specialty-field" hidden>
            <span>Spécialité</span>
            <input type="text" id="team-specialty" name="specialty">
            <small class="v1-field-error" data-team-error="specialty"></small>
          </label>

          <label class="v1-field v1-field--wide" id="team-license-field" hidden>
            <span>Numéro d’agrément / licence</span>
            <input type="text" id="team-license-number" name="license_number">
            <small class="v1-field-error" data-team-error="license_number"></small>
          </label>

          <div class="team-doctor-access-note v1-field--wide" id="team-doctor-access-note" hidden>
            Un médecin accède à sa Liste du jour, à ses patients, à ses historiques et à son profil. Les niveaux ci-dessous concernent uniquement le secrétariat.
          </div>

          <label class="v1-field v1-field--wide" id="team-access-level-field">
            <span>Niveau d’accès</span>
            <select id="team-access-level" name="access_level">
              <option value="queue_only">Liste du jour seulement</option>
              <option value="queue_and_patients">Liste, patients et historique</option>
              <option value="full">Accès opérationnel étendu</option>
            </select>
            <small class="v1-field-error" data-team-error="access_level"></small>
          </label>

          <fieldset class="team-doctors-field v1-field--wide" id="team-doctors-field">
            <legend>Médecins autorisés <strong class="required-mark" aria-hidden="true">*</strong></legend>
            <div id="team-doctors-options" class="team-doctors-options"></div>
            <small class="v1-field-error" data-team-error="doctor_ids"></small>
          </fieldset>
        </div>

        <div class="v1-form__actions">
          <button type="button" class="v1-button v1-button--secondary" id="team-form-cancel-button">Annuler</button>
          <button type="submit" class="v1-button v1-button--primary" id="team-form-save-button">Enregistrer le compte</button>
        </div>
      </form>
    </div>
  </div>

  <div id="team-confirm-modal" class="team-modal team-modal--confirm" hidden aria-hidden="true">
    <button
      type="button"
      class="team-modal__backdrop"
      id="team-confirm-backdrop"
      aria-label="Fermer"
    ></button>

    <div class="team-modal__dialog team-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="team-confirm-title">
      <div class="team-form__header">
        <div>
          <p class="v1-page__eyebrow">Confirmation</p>
          <h4 id="team-confirm-title">Modifier ce compte ?</h4>
        </div>
        <button type="button" class="v1-icon-button" id="team-confirm-close-button" aria-label="Fermer">×</button>
      </div>

      <p id="team-confirm-message"></p>

      <div class="v1-form__actions">
        <button type="button" class="v1-button v1-button--secondary" id="team-confirm-cancel-button">Annuler</button>
        <button type="button" class="v1-button v1-button--primary" id="team-confirm-button">Confirmer</button>
      </div>
    </div>
  </div>
  <?php endif; ?>
</section>
