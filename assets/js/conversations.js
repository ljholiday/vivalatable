/**
 * Conversations - Circles of Trust Filtering
 */

(function() {
	'use strict';

	const nav = document.querySelector('.vt-conversations-nav');
	const list = document.getElementById('vt-convo-list');
	const circleStatus = document.getElementById('vt-circle-status');

	if (!nav || !list) {
		return;
	}

	// Get nonce from meta tag
	const nonce = document.querySelector('meta[name="csrf-token"]')?.content;

	// Track current state
	let currentCircle = 'inner';
	let currentFilter = '';

	function loadConversations(options = {}) {
		const circle = options.circle || currentCircle;
		const filter = options.filter !== undefined ? options.filter : currentFilter;
		const page = options.page || 1;

		// Update current state
		currentCircle = circle;
		currentFilter = filter;

		// Add loading state with visual feedback
		list.classList.add('vt-is-loading');
		list.style.opacity = '0.5';

		if (circleStatus) {
			const loadingText = filter ? circle + ' circle - ' + filter : circle + ' circle';
			circleStatus.innerHTML = '<span class="vt-text-muted">Loading ' + loadingText + '...</span>';
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
		fetch('/ajax/conversations', {
			method: 'POST',
			body: formData
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				list.innerHTML = data.data.html;

				// Update circle status with metadata
				if (circleStatus && data.data.meta) {
					const meta = data.data.meta;
					const circleLabel = circle.charAt(0).toUpperCase() + circle.slice(1);
					const labelText = circle === 'all' ? 'All' : circleLabel + ' Circle';
					const filterText = filter ? ' - ' + filter.charAt(0).toUpperCase() + filter.slice(1) : '';
					circleStatus.innerHTML =
						'<strong class="vt-text-primary">' + labelText + filterText + '</strong> ' +
						'<span class="vt-text-muted">(' + meta.count + ' conversation' + (meta.count !== 1 ? 's' : '') + ')</span>';
				}
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
			list.style.opacity = '1';
		});
	}

	// Handle circle button clicks
	nav.addEventListener('click', function(e) {
		const circleButton = e.target.closest('button[data-circle]');
		const filterButton = e.target.closest('button[data-filter]');

		if (circleButton) {
			e.preventDefault();
			const circle = circleButton.dataset.circle;

			// Update circle button states
			nav.querySelectorAll('button[data-circle]').forEach(btn => {
				btn.classList.remove('is-active');
				btn.setAttribute('aria-selected', 'false');
			});

			circleButton.classList.add('is-active');
			circleButton.setAttribute('aria-selected', 'true');

			// Load conversations for selected circle, keeping current filter
			loadConversations({ circle: circle, filter: currentFilter });
		} else if (filterButton) {
			e.preventDefault();
			const filter = filterButton.dataset.filter;

			// Toggle filter on/off
			const newFilter = (currentFilter === filter) ? '' : filter;

			// Update filter button state
			nav.querySelectorAll('button[data-filter]').forEach(btn => {
				btn.classList.remove('is-active');
			});

			if (newFilter) {
				filterButton.classList.add('is-active');
			}

			// Load conversations with new filter, keeping current circle
			loadConversations({ circle: currentCircle, filter: newFilter });
		}
	});

	// Don't reload on page load - use server-rendered content
	// Only reload when user clicks buttons
})();