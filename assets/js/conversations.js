/**
 * Conversations - Circles of Trust Filtering
 */

(function() {
	'use strict';

	const nav = document.querySelector('.vt-conversations-nav');
	const list = document.getElementById('vt-convo-list');

	if (!nav || !list) {
		return;
	}

	// Get nonce from meta tag
	const nonce = document.querySelector('meta[name="csrf-token"]')?.content;

	function loadConversations(options = {}) {
		const circle = options.circle || 'inner';
		const filter = options.filter || '';
		const page = options.page || 1;

		// Add loading state
		list.classList.add('vt-is-loading');

		// Prepare form data
		const formData = new FormData();
		formData.append('nonce', nonce);
		formData.append('circle', circle);
		formData.append('page', page);
		if (filter) {
			formData.append('filter', filter);
		}

		// Make AJAX request
		fetch('/ajax/conversations', {
			method: 'POST',
			body: formData
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				list.innerHTML = data.data.html;
			} else {
				list.innerHTML = '<div class="vt-text-center vt-p-4"><p class="vt-text-muted">Error: ' + (data.message || 'Unknown error') + '</p></div>';
			}
		})
		.catch(error => {
			console.error('Error loading conversations:', error);
			list.innerHTML = '<div class="vt-text-center vt-p-4"><p class="vt-text-muted">Network error. Please try again.</p></div>';
		})
		.finally(() => {
			list.classList.remove('vt-is-loading');
		});
	}

	// Handle button clicks
	nav.addEventListener('click', function(e) {
		const button = e.target.closest('button[data-circle]');
		if (!button) return;

		e.preventDefault();

		const circle = button.dataset.circle;

		// Update button states
		nav.querySelectorAll('button').forEach(btn => {
			btn.classList.remove('is-active');
			btn.setAttribute('aria-selected', 'false');
		});

		button.classList.add('is-active');
		button.setAttribute('aria-selected', 'true');

		// Load conversations for selected circle
		loadConversations({ circle: circle });
	});

	// Load initial conversations on page load
	const activeButton = nav.querySelector('button.is-active');
	const initialCircle = activeButton?.dataset.circle || 'inner';
	loadConversations({ circle: initialCircle });
})();