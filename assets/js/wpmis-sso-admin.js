// assets/js/wpmis-sso-admin.js

document.addEventListener('DOMContentLoaded', function() {
    const addButton = document.getElementById('add-secondary-site');
    const wrapper = document.getElementById('secondary-sites-wrapper');

    if (addButton) {
        addButton.addEventListener('click', function(e) {
            e.preventDefault();
            const field = document.createElement('div');
            field.className = 'secondary-site-field';
            field.innerHTML = '<input type="url" name="wpmis_sso_settings[secondary_sites][]" value="" size="50" required /> <button type="button" class="button remove-secondary-site">Remove</button>';
            wrapper.appendChild(field);
        });
    }

    if (wrapper) {
        wrapper.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-secondary-site')) {
                e.target.parentElement.remove();
            }
        });
    }
});