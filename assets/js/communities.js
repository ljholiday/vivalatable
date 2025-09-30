/**
 * VivalaTable Communities
 * Handles community tab switching and join functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    initCommunityTabs();
    initJoinButtons();
});

/**
 * Initialize tab functionality for communities
 */
function initCommunityTabs() {
    const communityTabs = document.querySelectorAll('[data-filter]');
    const communityTabContents = document.querySelectorAll('.vt-communities-tab-content');

    if (!communityTabs.length) {
        return;
    }

    communityTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');

            // Update active tab
            communityTabs.forEach(t => {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
            });
            this.classList.add('is-active');
            this.setAttribute('aria-selected', 'true');

            // Show/hide content
            communityTabContents.forEach(content => {
                const tab = content.getAttribute('data-tab');
                content.style.display = (tab === filter) ? '' : 'none';
            });
        });
    });
}

/**
 * Initialize join button functionality
 */
function initJoinButtons() {
    const joinBtns = document.querySelectorAll('.join-community-btn');
    joinBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const communityId = this.getAttribute('data-community-id');
            const communityName = this.getAttribute('data-community-name');
            const isPersonalCommunity = this.textContent.trim() === 'Connect';

            const action = isPersonalCommunity ? 'Connect to' : 'Join';
            if (!confirm(`${action} community "${communityName}"?`)) {
                return;
            }

            this.disabled = true;
            this.textContent = 'Joining...';

            fetch('/api/communities/' + communityId + '/join', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'community_id=' + communityId + '&nonce=' + getCSRFToken()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.textContent = 'Member';
                    this.disabled = false;
                    this.onclick = () => window.location.href = '/communities/' + data.community_slug;

                    vtShowSuccess(`Welcome to ${communityName}!`);
                } else {
                    throw new Error(data.message || 'Failed to join community');
                }
            })
            .catch(error => {
                console.error('Error joining community:', error);
                vtShowError('Failed to join community: ' + error.message);
                this.disabled = false;
                this.textContent = isPersonalCommunity ? 'Connect' : 'Join';
            });
        });
    });
}

/**
 * Get CSRF token from meta tag
 */
function getCSRFToken() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    return csrfToken ? csrfToken.getAttribute('content') : '';
}

/**
 * Escape HTML for safe insertion
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}