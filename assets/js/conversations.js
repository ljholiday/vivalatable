/**
 * Conversations - Circles of Trust Filtering
 */

(function() {
	'use strict';

	const nav = document.querySelector('.vt-conversations-nav');
	const list = document.getElementById('vt-convo-list');
	const circleStatus = document.getElementById('vt-circle-status');

	console.log('[Conversations] Script loaded', { nav, list, circleStatus });

	if (!nav || !list) {
		console.error('[Conversations] Missing required elements', { nav, list });
		return;
	}

	// Get nonce from meta tag
	const nonce = document.querySelector('meta[name="csrf-token"]')?.content;

	function loadConversations(options = {}) {
		const circle = options.circle || 'inner';
		const filter = options.filter || '';
		const page = options.page || 1;

		console.log('[Conversations] loadConversations called', { circle, filter, page });

		// Add loading state with visual feedback
		list.classList.add('vt-is-loading');
		list.style.opacity = '0.5';

		if (circleStatus) {
			circleStatus.innerHTML = '<span class="vt-text-muted">Loading ' + circle + ' circle...</span>';
		}

		// Prepare form data
		const formData = new FormData();
		formData.append('nonce', nonce);
		formData.append('circle', circle);
		formData.append('page', page);
		if (filter) {
			formData.append('filter', filter);
		}

		// Make AJAX request
		console.log('[Conversations] Fetching /ajax/conversations', { nonce, circle, page, filter });
		fetch('/ajax/conversations', {
			method: 'POST',
			body: formData
		})
		.then(response => {
			console.log('[Conversations] Response received', response);
			return response.json();
		})
		.then(data => {
			console.log('[Conversations] Data received', data);
			console.log('[Conversations] Success:', data.success);
			console.log('[Conversations] Meta:', data.data?.meta);

			if (data.success) {
				list.innerHTML = data.data.html;
				console.log('[Conversations] Updated list HTML');

				// Update circle status with metadata
				if (circleStatus && data.data.meta) {
					const meta = data.data.meta;
					const circleLabel = circle.charAt(0).toUpperCase() + circle.slice(1);
					circleStatus.innerHTML =
						'<strong class="vt-text-primary">' + circleLabel + ' Circle</strong> ' +
						'<span class="vt-text-muted">(' + meta.count + ' conversation' + (meta.count !== 1 ? 's' : '') + ')</span>';
					console.log('[Conversations] Updated status to:', circleLabel, meta.count);
				}
			} else {
				console.error('[Conversations] AJAX error:', data.message);
				list.innerHTML = '<div class="vt-text-center vt-p-4"><p class="vt-text-muted">Error: ' + (data.message || 'Unknown error') + '</p></div>';
			}
		})
		.catch(error => {
			console.error('Error loading conversations:', error);
			list.innerHTML = '<div class="vt-text-center vt-p-4"><p class="vt-text-muted">Network error. Please try again.</p></div>';
		})
		.finally(() => {
			list.classList.remove('vt-is-loading');
			list.style.opacity = '1';
		});
	}

	// Handle button clicks
	nav.addEventListener('click', function(e) {
		console.log('[Conversations] Click detected', e.target);
		const button = e.target.closest('button[data-circle]');
		console.log('[Conversations] Button found:', button);
		if (!button) return;

		e.preventDefault();

		const circle = button.dataset.circle;
		console.log('[Conversations] Loading circle:', circle);

		// Update button states
		nav.querySelectorAll('button[data-circle]').forEach(btn => {
			btn.classList.remove('is-active');
			btn.setAttribute('aria-selected', 'false');
		});

		button.classList.add('is-active');
		button.setAttribute('aria-selected', 'true');

		// Load conversations for selected circle
		loadConversations({ circle: circle });
	});

	// Don't reload on page load - use server-rendered content
	// Only reload when user clicks buttons
})();