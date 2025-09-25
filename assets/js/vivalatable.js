/**
 * VivalaTable JavaScript
 * Basic functionality for the VivalaTable application
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('VivalaTable loaded');

    // Basic form enhancement
    const forms = document.querySelectorAll('form');
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