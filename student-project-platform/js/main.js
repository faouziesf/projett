// js/main.js - JavaScript principal

// Initialisation quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Initialiser les graphiques de progression circulaire
    initProgressCircles();
    
    // Initialiser les fonctionnalités AJAX
    initAjaxForms();
});

// Fonction pour initialiser les cercles de progression
function initProgressCircles() {
    const circles = document.querySelectorAll('.progress-circle');
    circles.forEach(circle => {
        const progress = circle.dataset.progress || 0;
        circle.style.setProperty('--progress', progress);
    });
}

// Fonction pour initialiser les formulaires AJAX
function initAjaxForms() {
    // Mise à jour du statut des tâches
    const taskCheckboxes = document.querySelectorAll('.task-checkbox');
    taskCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateTaskStatus(this.dataset.taskId, this.checked);
        });
    });
    
    // Ajout de commentaires
    const commentForms = document.querySelectorAll('.comment-form');
    commentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitComment(this);
        });
    });
}

// Mettre à jour le statut d'une tâche
function updateTaskStatus(taskId, completed) {
    const status = completed ? 'completed' : 'todo';
    
    fetch('/api/update_task.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            task_id: taskId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour l'affichage
            const taskItem = document.querySelector(`[data-task-id="${taskId}"]`).closest('.task-item');
            taskItem.className = `task-item status-${status}`;
            
            // Mettre à jour le pourcentage du projet si disponible
            if (data.project_progress !== undefined) {
                updateProjectProgress(data.project_progress);
            }
            
            showNotification('Tâche mise à jour avec succès', 'success');
        } else {
            showNotification('Erreur lors de la mise à jour', 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la mise à jour', 'error');
    });
}

// Soumettre un commentaire
function submitComment(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    fetch('/api/add_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Réinitialiser le formulaire
            form.reset();
            
            // Ajouter le commentaire à la liste
            addCommentToList(data.comment);
            
            showNotification('Commentaire ajouté avec succès', 'success');
        } else {
            showNotification('Erreur lors de l\'ajout du commentaire', 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'ajout du commentaire', 'error');
    });
}

// Ajouter un commentaire à la liste
function addCommentToList(comment) {
    const commentsList = document.querySelector('.comments-list');
    if (commentsList) {
        const commentHtml = `
            <div class="comment-item type-${comment.type} fade-in">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <strong>${comment.author_name}</strong>
                    <small class="text-muted">${comment.created_at}</small>
                </div>
                <p class="mb-1">${comment.comment}</p>
                ${comment.type === 'recommendation' ? '<span class="badge bg-warning">Recommandation</span>' : ''}
            </div>
        `;
        commentsList.insertAdjacentHTML('afterbegin', commentHtml);
    }
}

// Mettre à jour le pourcentage d'un projet
function updateProjectProgress(progress) {
    const progressCircles = document.querySelectorAll('.progress-circle');
    progressCircles.forEach(circle => {
        circle.style.setProperty('--progress', progress);
        circle.textContent = progress + '%';
    });
    
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        bar.style.width = progress + '%';
        bar.textContent = progress + '%';
    });
}

// Afficher une notification toast
function showNotification(message, type = 'info') {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : 
                   type === 'error' ? 'bg-danger' : 
                   type === 'warning' ? 'bg-warning' : 'bg-info';
    
    const toastHtml = `
        <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
            <div class="toast-body">
                ${message}
                <button type="button" class="btn-close btn-close-white float-end" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    // Supprimer le toast après fermeture
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

// Créer le conteneur de toasts s'il n'existe pas
function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
    return container;
}

// Confirmer la suppression
function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
    return confirm(message);
}

// Upload de fichier avec progression
function uploadFile(input, projectId) {
    const file = input.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('document', file);
    formData.append('project_id', projectId);
    
    // Créer une barre de progression
    const progressContainer = createProgressBar();
    
    fetch('/api/upload_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        progressContainer.remove();
        
        if (data.success) {
            showNotification('Document uploadé avec succès', 'success');
            // Recharger la liste des documents
            location.reload();
        } else {
            showNotification('Erreur lors de l\'upload: ' + data.message, 'error');
        }
    })
    .catch(error => {
        progressContainer.remove();
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'upload', 'error');
    });
}

// Créer une barre de progression pour l'upload
function createProgressBar() {
    const container = document.createElement('div');
    container.className = 'progress mt-2';
    container.innerHTML = `
        <div class="progress-bar progress-bar-striped progress-bar-animated" 
             role="progressbar" style="width: 100%">
            Upload en cours...
        </div>
    `;
    
    // Ajouter après le champ de fichier
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.parentNode.insertBefore(container, fileInput.nextSibling);
    }
    
    return container;
}

// Filtrer les projets
function filterProjects(status = '') {
    const projectCards = document.querySelectorAll('.project-card');
    
    projectCards.forEach(card => {
        if (status === '' || card.classList.contains('status-' + status)) {
            card.style.display = 'block';
            card.classList.add('fade-in');
        } else {
            card.style.display = 'none';
        }
    });
}

// Rechercher dans les projets
function searchProjects(query) {
    const projectCards = document.querySelectorAll('.project-card');
    query = query.toLowerCase();
    
    projectCards.forEach(card => {
        const title = card.querySelector('.card-title').textContent.toLowerCase();
        const description = card.querySelector('.card-text').textContent.toLowerCase();
        
        if (title.includes(query) || description.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Marquer toutes les notifications comme lues
function markAllNotificationsAsRead() {
    fetch('/api/mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Initialiser les graphiques (si Chart.js est disponible)
function initCharts() {
    // Graphique de répartition des projets par statut
    const statusChartCanvas = document.getElementById('statusChart');
    if (statusChartCanvas && typeof Chart !== 'undefined') {
        const ctx = statusChartCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['En cours', 'Terminés', 'En pause', 'Planification'],
                datasets: [{
                    data: [
                        parseInt(statusChartCanvas.dataset.inProgress) || 0,
                        parseInt(statusChartCanvas.dataset.completed) || 0,
                        parseInt(statusChartCanvas.dataset.paused) || 0,
                        parseInt(statusChartCanvas.dataset.planning) || 0
                    ],
                    backgroundColor: ['#0dcaf0', '#198754', '#ffc107', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}