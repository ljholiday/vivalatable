/**
 * VivalaTable Conversations
 * Handles conversation filtering by Circles of Trust
 */

document.addEventListener('DOMContentLoaded', function() {
    const circleButtons = document.querySelectorAll('[data-circle]');
    const conversationList = document.getElementById('vt-convo-list');

    if (circleButtons.length === 0 || !conversationList) {
        return;
    }

    let currentCircle = 'inner';
    let currentFilter = new URLSearchParams(window.location.search).get('filter') || '';
    let isLoading = false;

    // Initialize circle button handlers
    circleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            if (isLoading) return;

            const circle = this.getAttribute('data-circle');
            if (circle === currentCircle) return;

            loadConversations(circle, currentFilter);
        });
    });

    // Initialize type filter handlers
    const typeFilterLinks = document.querySelectorAll('.vt-conversations-nav a[href*="filter"], .vt-conversations-nav a[href="/conversations"]');
    typeFilterLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            if (isLoading) return;

            const url = new URL(this.href, window.location.origin);
            const filter = url.searchParams.get('filter') || '';

            if (filter === currentFilter) return;

            currentFilter = filter;
            loadConversations(currentCircle, filter);

            updateTypeFilterStates(this);
        });
    });

    function loadConversations(circle, filter) {
        isLoading = true;
        updateCircleButtonStates(circle);
        showLoadingState();

        const formData = new FormData();
        formData.append('circle', circle);
        formData.append('filter', filter);
        formData.append('page', '1');
        formData.append('nonce', getCSRFToken());

        fetch('/ajax/conversations', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateConversationList(data.html || '');
                currentCircle = circle;
            } else {
                throw new Error(data.message || 'Failed to load conversations');
            }
        })
        .catch(error => {
            console.error('Error loading conversations:', error);
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

    function updateTypeFilterStates(activeLink) {
        const typeFilterLinks = document.querySelectorAll('.vt-conversations-nav a[href*="filter"], .vt-conversations-nav a[href="/conversations"]');
        typeFilterLinks.forEach(link => link.classList.remove('is-active'));
        activeLink.classList.add('is-active');
    }

    function showLoadingState() {
        conversationList.innerHTML =
            '<div class="vt-text-center vt-p-4">' +
                '<h3 class="vt-heading vt-heading-sm vt-mb-4">Loading...</h3>' +
                '<p class="vt-text-muted">Filtering conversations by trust circle...</p>' +
            '</div>';
    }

    function showErrorState(message) {
        conversationList.innerHTML =
            '<div class="vt-text-center vt-p-4">' +
                '<h3 class="vt-heading vt-heading-sm vt-mb-4">Error Loading Conversations</h3>' +
                '<p class="vt-text-muted">' + escapeHtml(message) + '</p>' +
                '<button class="vt-btn" onclick="location.reload()">Retry</button>' +
            '</div>';
    }

    function updateConversationList(html) {
        if (html && html.trim()) {
            conversationList.innerHTML = html;
        } else {
            conversationList.innerHTML =
                '<div class="vt-text-center vt-p-4">' +
                    '<h3 class="vt-heading vt-heading-sm vt-mb-4">No Conversations Found</h3>' +
                    '<p class="vt-text-muted">No conversations in this trust circle.</p>' +
                '</div>';
        }
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
});