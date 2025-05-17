const toggleButtons = document.querySelectorAll('.view-toggle');
const notesContainer = document.getElementById('notesContainer');

toggleButtons.forEach(button => {
    button.addEventListener('click', () => {
        toggleButtons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        const view = button.getAttribute('data-view');

        document.querySelectorAll('.note-card').forEach(card => {
            if (view === 'list') {
                card.classList.remove('col-md-4');
                card.classList.add('col-12', 'mb-2');
                card.querySelector('.card').classList.add('flex-row');
                card.querySelector('.card-body').classList.add('d-flex', 'flex-column');
            } else {
                card.classList.add('col-md-4');
                card.classList.remove('col-12', 'mb-2');
                card.querySelector('.card').classList.remove('flex-row');
                card.querySelector('.card-body').classList.remove('d-flex', 'flex-column');
            }
        });
    });
});

// Pin/unpin functionality
async function togglePin(button, noteId) {
    const noteCard = button.closest('.note-card');
    const isPinned = noteCard.classList.contains('pinned-note');
    const action = isPinned ? 'unpin' : 'pin';

    try {
        const response = await fetch('home.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `note_id=${noteId}&pin_action=${action}`
        });
        const data = await response.json();

        if (data.status === 'success') {
            noteCard.classList.toggle('pinned-note', action === 'pin');
            noteCard.setAttribute('data-updated-at', data.updated_at);

            if (action === 'pin') {
                noteCard.setAttribute('data-pinned-at', data.pinned_at);
                if (!noteCard.querySelector('.badge.bg-warning')) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-warning text-dark mb-2';
                    badge.textContent = 'Pinned';
                    noteCard.querySelector('.card-body').prepend(badge);
                }
            } else {
                const badge = noteCard.querySelector('.badge.bg-warning');
                if (badge) badge.remove();
                noteCard.removeAttribute('data-pinned-at');
            }

            button.innerHTML = action === 'pin' ? 'Unpin <span class="pin-icon">📌</span>' : 'Pin <span class="pin-icon">📌</span>';
            button.classList.toggle('btn-warning', action === 'pin');
            button.classList.toggle('btn-outline-secondary', action !== 'pin');

            reorderNotes();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Reorder notes based on pinned status
function reorderNotes() {
    const container = document.getElementById('notesContainer');
    const notes = Array.from(container.querySelectorAll('.note-card:not([style*="display: none"])'));

    notes.sort((a, b) => {
        const aPinned = a.classList.contains('pinned-note');
        const bPinned = b.classList.contains('pinned-note');

        if (aPinned && bPinned) {
            const aTime = a.getAttribute('data-pinned-at') || '1970-01-01 00:00:00';
            const bTime = b.getAttribute('data-pinned-at') || '1970-01-01 00:00:00';
            return new Date(bTime) - new Date(aTime);
        } else if (aPinned) {
            return -1;
        } else if (bPinned) {
            return 1;
        } else {
            const aId = parseInt(a.getAttribute('data-note-id'));
            const bId = parseInt(b.getAttribute('data-note-id'));
            return bId - aId;
        }
    });

    notes.forEach(note => container.appendChild(note));
}

// Search functionality
const searchInput = document.getElementById('searchInput');
const clearSearch = document.getElementById('clearSearch');
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearSearch.style.display = this.value ? '' : 'none';

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = this.value.toLowerCase().trim();

        document.querySelectorAll('.note-card').forEach(card => {
            if (searchTerm === '') {
                card.style.display = '';
                return;
            }

            const title = card.querySelector('.card-title').textContent.toLowerCase();
            const content = card.querySelector('.card-text').textContent.toLowerCase();

            card.style.display = (title.includes(searchTerm) || content.includes(searchTerm)) ? '' : 'none';
        });

        reorderNotes();
    }, 300);
});

clearSearch.addEventListener('click', function() {
    searchInput.value = '';
    this.style.display = 'none';
    const event = new Event('input');
    searchInput.dispatchEvent(event);
    searchInput.focus();
});

// Label filtering
document.querySelectorAll('.label-filter-item').forEach(item => {
    item.addEventListener('click', function() {
        const labelId = this.dataset.labelId;

        document.querySelectorAll('.label-filter-item').forEach(i => {
            i.classList.toggle('active', i === this);
        });

        // Filter notes
        document.querySelectorAll('.note-card').forEach(card => {
            if (labelId === 'all') {
                card.style.display = '';
            } else {
                const cardLabels = card.dataset.labels ? card.dataset.labels.split(',') : [];
                card.style.display = cardLabels.includes(labelId) ? '' : 'none';
            }
        });

        reorderNotes();
    });
});

// Rename Modal
const renameLabelModal = document.getElementById('renameLabelModal');
if (renameLabelModal) {
    renameLabelModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const labelId = button.getAttribute('data-label-id');
        const labelName = button.getAttribute('data-label-name');
        const modal = this;

        modal.querySelector('#renameLabelId').value = labelId;
        modal.querySelector('#newLabelName').value = labelName;
    });
}

// Manage Labels Modal
const manageLabelsModal = document.getElementById('manageLabelsModal');
if (manageLabelsModal) {
    manageLabelsModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const noteId = button.getAttribute('data-note-id');
        const modal = this;

        modal.querySelector('#manageNoteId').value = noteId;

        // Get current labels for this note
        const card = document.querySelector(`.note-card[data-note-id="${noteId}"]`);
        const currentLabels = card ? (card.dataset.labels || '').split(',') : [];

        // Update checkboxes
        modal.querySelectorAll('.form-check-input').forEach(checkbox => {
            checkbox.checked = currentLabels.includes(checkbox.value);
        });
    });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    reorderNotes();

    document.querySelectorAll('.delete-label-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); 
        });
    });
});