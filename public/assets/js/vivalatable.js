/**
 * VivalaTable Core JavaScript
 * Core functionality shared across the application
 */

document.addEventListener('DOMContentLoaded', function() {
    // Basic form enhancement
    const forms = document.querySelectorAll('form:not([data-custom-handler])');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Loading...';
            }
        });
    });

    // Basic navigation active states
    const navItems = document.querySelectorAll('.vt-main-nav-item');
    navItems.forEach(item => {
        if (item.href && window.location.pathname.includes(item.href.split('/').pop())) {
            item.classList.add('active');
        }
    });
});