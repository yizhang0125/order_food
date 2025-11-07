document.addEventListener('DOMContentLoaded', function() {
    // Edit Category Modal Handling
    const editModal = document.getElementById('editCategoryModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const category = JSON.parse(button.getAttribute('data-category'));
            
            // Populate form fields
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_description').value = category.description || '';
        });
    }

    // Form Validation
    const editForm = document.getElementById('editCategoryForm');
    if (editForm) {
        editForm.addEventListener('submit', function(event) {
            const nameInput = document.getElementById('edit_name');
            const descriptionInput = document.getElementById('edit_description');
            
            let isValid = true;
            
            // Name validation
            if (!nameInput.value.trim() || nameInput.value.length < 2) {
                nameInput.classList.add('is-invalid');
                isValid = false;
            } else {
                nameInput.classList.remove('is-invalid');
            }
            
            // Description validation (optional)
            if (descriptionInput.value.length > 500) {
                descriptionInput.classList.add('is-invalid');
                isValid = false;
            } else {
                descriptionInput.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }
});
