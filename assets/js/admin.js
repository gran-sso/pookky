/* assets/js/admin.js */
/* Pookky Admin Control Panel interactive handlers and AJAX utilities */

document.addEventListener('DOMContentLoaded', function() {
    initDraftCleanup();
    initStatusToggles();
    initVariantActions();
    initReturnActions();
});

// Automatically purge temporary product creation/edit drafts from sessionStorage on successful actions
function initDraftCleanup() {
    try {
        if (document.querySelector('.alert-success')) {
            const keysToRemove = [];
            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.startsWith('veesta_product_draft_')) {
                    keysToRemove.push(key);
                }
            }
            keysToRemove.forEach(function(k) { sessionStorage.removeItem(k); });
        }
    } catch (e) {}
}

// Dynamic status selectors (Active/Inactive toggles) for products/categories
function initStatusToggles() {
    document.querySelectorAll('.status-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const table = this.getAttribute('data-table');
            const id = this.getAttribute('data-id');
            const status = this.checked ? 1 : 0;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('table', table);
            formData.append('id', id);
            formData.append('status', status);
            formData.append('csrf_token', csrfToken);
            
            fetch('process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Flash notification
                    console.log(data.message);
                } else {
                    alert(data.message);
                    // Revert check
                    this.checked = !this.checked;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Status update failed.');
                this.checked = !this.checked;
            });
        });
    });
}

// Handler to edit or delete variants in admin
function initVariantActions() {
    const editModal = document.getElementById('variantEditModal');
    if (!editModal) return;

    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-variant-id');
        const sku = button.getAttribute('data-sku');
        const price = button.getAttribute('data-price');
        const stock = button.getAttribute('data-stock');

        // Populate fields
        editModal.querySelector('#modal-variant-id').value = id;
        editModal.querySelector('#modal-sku').value = sku;
        editModal.querySelector('#modal-price').value = price;
        editModal.querySelector('#modal-stock').value = stock;
    });
}

// Return requests approvals/rejections in admin
function initReturnActions() {
    document.querySelectorAll('.return-action-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const returnId = this.getAttribute('data-return-id');
            const status = this.getAttribute('data-status'); // approved or rejected
            const notesInput = document.getElementById('admin-notes-' + returnId);
            const notes = notesInput ? notesInput.value.trim() : '';

            if (status === 'rejected' && !notes) {
                alert('Please provide internal notes/reason for rejection.');
                return;
            }

            if (!confirm(`Are you sure you want to mark this request as ${status}?`)) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'process_return');
            formData.append('return_id', returnId);
            formData.append('status', status);
            formData.append('admin_notes', notes);
            formData.append('csrf_token', csrfToken);

            fetch('process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to process return request.');
            });
        });
    });
}
