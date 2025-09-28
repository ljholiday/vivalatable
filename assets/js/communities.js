/**
 * VivalaTable Communities
 * Handles community filtering by Circles of Trust and join functionality
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

    function loadCommunities(circle) {
        isLoading = true;
        updateCircleButtonStates(circle);
        showLoadingState();

        const formData = new FormData();
        formData.append('circle', circle);
        formData.append('page', '1');
        formData.append('nonce', getCSRFToken());

        fetch('/ajax/communities', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCommunityList(data.html || '');
                currentCircle = circle;
            } else {
                throw new Error(data.message || 'Failed to load communities');
            }
        })
        .catch(error => {
            console.error('Error loading communities:', error);
            showErrorState(error.message);
        })
        .finally(() => {
            isLoading = false;
        });
    }

    function updateCircleButtonStates(activeCircle) {
        circleButtons.forEach(button => {
            const circle = button.getAttribute('data-circle');
            if (circle === activeCircle) {
                button.classList.add('is-active');
                button.setAttribute('aria-selected', 'true');
            } else {
                button.classList.remove('is-active');
                button.setAttribute('aria-selected', 'false');
            }
        });
    }

    function showLoadingState() {
        communityList.innerHTML =
            '<div class="vt-text-center vt-p-4">' +
                '<h3 class="vt-heading vt-heading-sm vt-mb-4">Loading...</h3>' +
                '<p class="vt-text-muted">Filtering communities by trust circle...</p>' +
            '</div>';
    }

    function showErrorState(message) {
        communityList.innerHTML =
            '<div class="vt-text-center vt-p-4">' +
                '<h3 class="vt-heading vt-heading-sm vt-mb-4">Error Loading Communities</h3>' +
                '<p class="vt-text-muted">' + escapeHtml(message) + '</p>' +
                '<button class="vt-btn" onclick="location.reload()">Retry</button>' +
            '</div>';
    }

    function updateCommunityList(html) {
        if (html && html.trim()) {
            communityList.innerHTML = html;
            // Re-initialize join buttons after new content is loaded
            initJoinButtons();
        } else {
            communityList.innerHTML =
                '<div class="vt-text-center vt-p-4">' +
                    '<h3 class="vt-heading vt-heading-sm vt-mb-4">No Communities Found</h3>' +
                    '<p class="vt-text-muted">No communities in this trust circle.</p>' +
                '</div>';
        }
    }

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

                        alert(`Welcome to ${communityName}!`);
                    } else {
                        throw new Error(data.message || 'Failed to join community');
                    }
                })
                .catch(error => {
                    console.error('Error joining community:', error);
                    alert('Failed to join community: ' + error.message);
                    this.disabled = false;
                    this.textContent = isPersonalCommunity ? 'Connect' : 'Join';
                });
            });
        });
    }

    function getCSRFToken() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        return csrfToken ? csrfToken.getAttribute('content') : '';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize join buttons on page load
    initJoinButtons();
});