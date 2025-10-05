/**
 * VivalaTable Communities
 * Handles community tab switching and join functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    initCommunityTabs();
    initJoinButtons();
    initInvitationCopy();
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

/**
 * Initialize invitation link copy functionality
 */
function initInvitationCopy() {
    const copyLinkBtn = document.querySelector('.vt-copy-invitation-link');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function() {
            const linkInput = document.getElementById('invitation-link');
            linkInput.select();
            document.execCommand('copy');
            this.textContent = 'Copied!';
            setTimeout(() => {
                this.textContent = 'Copy';
            }, 2000);
        });
    }

    const copyWithMessageBtn = document.querySelector('.vt-copy-invitation-with-message');
    if (copyWithMessageBtn) {
        copyWithMessageBtn.addEventListener('click', function() {
            const link = document.getElementById('invitation-link').value;
            const message = document.getElementById('custom-message').value;
            const fullText = message ? message + '\n\n' + link : link;

            navigator.clipboard.writeText(fullText).then(() => {
                this.textContent = 'Copied!';
                setTimeout(() => {
                    this.textContent = 'Copy Link with Message';
                }, 2000);
            });
        });
    }
}

/**
 * Change member role (called from template)
 */
function changeMemberRole(memberId, newRole, communityId) {
    if (!confirm('Are you sure you want to change this member\'s role to ' + newRole + '?')) {
        location.reload();
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId + '/role', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            role: newRole,
            nonce: getCSRFToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('member-row-' + memberId);
            const badge = row.querySelector('.vt-badge');
            badge.className = 'vt-badge vt-badge-' + (newRole === 'admin' ? 'primary' : 'secondary');
            badge.textContent = newRole.charAt(0).toUpperCase() + newRole.slice(1);
            alert('Member role updated successfully!');
        } else {
            alert('Error: ' + (data.message || 'Failed to update member role'));
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update member role. Please try again.');
        location.reload();
    });
}

/**
 * Remove member from community (called from template)
 */
function removeMember(memberId, memberName, communityId) {
    if (!confirm('Are you sure you want to remove ' + memberName + ' from this community? This action cannot be undone.')) {
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            nonce: getCSRFToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('member-row-' + memberId);
            row.remove();
            alert('Member removed successfully!');

            const tbody = document.querySelector('.vt-table tbody');
            if (!tbody || tbody.children.length === 0) {
                location.reload();
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to remove member'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to remove member. Please try again.');
    });
}