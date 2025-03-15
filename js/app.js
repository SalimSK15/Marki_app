// script.js

// Fonction pour gérer l'activation des éléments du menu
function activateMenuItem(event) {
    // Supprimez la classe active de tous les éléments
    document.querySelectorAll('.sidebar__item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Ajoutez la classe active à l'élément cliqué
    event.currentTarget.classList.add('active');
}

// Ajoutez des écouteurs d'événements à chaque élément du menu
document.querySelectorAll('.sidebar__item').forEach(item => {
    item.addEventListener('click', activateMenuItem);
});


// Fonction pour charger le contenu d'une page
function loadPage(page) {
    const mainContent = document.getElementById('main-content');
    
    // Utilisez fetch pour charger le contenu de la page
    fetch(`pages/${page}.html`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur de chargement de la page');
            }
            return response.text();
        })
        .then(html => {
            mainContent.innerHTML = html; // Insère le contenu dans le conteneur
        })
        .catch(error => {
            console.error('Erreur:', error);
            mainContent.innerHTML = '<p>Erreur de chargement de la page.</p>';
        });
}

// Ajoutez des écouteurs d'événements aux éléments de la sidebar
document.querySelectorAll('.sidebar__item').forEach(item => {
    item.addEventListener('click', function() {
        const page = this.getAttribute('data-page'); // Récupère le nom de la page
        loadPage(page); // Charge la page correspondante
    });
});

// Charge la page par défaut ( dashboard )
// loadPage('dashboard');
loadPage('settings');