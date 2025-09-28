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

    // Circles of Trust conversation filtering
    const circleButtons = document.querySelectorAll('[data-circle]');
    const conversationList = document.getElementById('vt-convo-list');

    if (circleButtons.length > 0 && conversationList) {
        circleButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                // Update active state
                circleButtons.forEach(btn => {
                    btn.classList.remove('is-active');
                    btn.setAttribute('aria-selected', 'false');
                });
                this.classList.add('is-active');
                this.setAttribute('aria-selected', 'true');

                // Get circle level and current filter
                const circle = this.getAttribute('data-circle');
                const urlParams = new URLSearchParams(window.location.search);
                const filter = urlParams.get('filter') || '';

                // Load conversations for this circle
                loadConversationsByCircle(circle, filter);
            });
        });
    }

    function loadConversationsByCircle(circle, filter) {
        if (!conversationList) return;

        // Show loading state
        conversationList.innerHTML = '<div class="vt-text-center vt-p-4"><p>Loading conversations...</p></div>';

        // Prepare form data for AJAX call
        const formData = new FormData();
        formData.append('action', 'get_conversations');
        formData.append('circle', circle);
        formData.append('filter', filter);
        formData.append('limit', '20');

        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            formData.append('nonce', csrfToken.getAttribute('content'));
        }

        // Make AJAX call
        fetch('/ajax/conversations', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.conversations) {
                renderConversations(data.conversations);
            } else {
                conversationList.innerHTML = '<div class="vt-text-center vt-p-4"><h3 class="vt-heading vt-heading-sm vt-mb-4">No Conversations Found</h3><p class="vt-text-muted">There are no conversations to display for this circle.</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading conversations:', error);
            conversationList.innerHTML = '<div class="vt-text-center vt-p-4"><p class="vt-text-muted">Error loading conversations. Please try again.</p></div>';
        });
    }

    function renderConversations(conversations) {
        if (!conversations || conversations.length === 0) {
            conversationList.innerHTML = '<div class="vt-text-center vt-p-4"><h3 class="vt-heading vt-heading-sm vt-mb-4">No Conversations Found</h3><p class="vt-text-muted">There are no conversations to display.</p></div>';
            return;
        }

        let html = '';
        conversations.forEach(conversation => {
            let conversationType = 'General Discussion';
            if (conversation.event_id) {
                conversationType = 'Event Discussion';
            } else if (conversation.community_id) {
                conversationType = 'Community Discussion';
            }

            const truncatedContent = conversation.content ? truncateWords(conversation.content, 15) : '';

            html += `
                <div class="vt-section">
                    <div class="vt-flex vt-flex-between vt-mb-4">
                        <h3 class="vt-heading vt-heading-sm">
                            <a href="/conversations/${escapeHtml(conversation.slug)}" class="vt-text-primary">${escapeHtml(conversation.title || conversation.subject)}</a>
                        </h3>
                    </div>

                    <div class="vt-mb-4">
                        <div class="vt-flex vt-gap vt-mb-4">
                            <span class="vt-text-muted">${conversationType}</span>
                        </div>
                    </div>

                    ${truncatedContent ? `<div class="vt-mb-4"><p class="vt-text-muted">${escapeHtml(truncatedContent)}</p></div>` : ''}

                    <div class="vt-flex vt-flex-between">
                        <div class="vt-stat">
                            <div class="vt-stat-number vt-text-primary">${parseInt(conversation.reply_count) || 0}</div>
                            <div class="vt-stat-label">Replies</div>
                        </div>
                        <a href="/conversations/${escapeHtml(conversation.slug)}" class="vt-btn">View Details</a>
                    </div>
                </div>
            `;
        });

        conversationList.innerHTML = html;
    }

    function truncateWords(text, wordLimit) {
        if (!text) return '';
        const words = text.split(' ');
        if (words.length <= wordLimit) return text;
        return words.slice(0, wordLimit).join(' ') + '...';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});