(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.cl-sales-order-detail').forEach(function (root) {
            const headings = Array.from(root.querySelectorAll('[data-so-section-toggle]'));
            const allButton = root.querySelector('[data-so-sections-toggle-all]');

            function setExpanded(button, expanded) {
                const sectionId = button.getAttribute('data-so-section-toggle');
                root.querySelectorAll('[data-so-section-row="' + CSS.escape(sectionId) + '"]').forEach(function (row) {
                    row.hidden = !expanded;
                    if (expanded) row.style.removeProperty('display');
                    else row.style.setProperty('display', 'none', 'important');
                });
                button.setAttribute('aria-expanded', String(expanded));
                const title = button.querySelector('.so-section-title');
                button.setAttribute('aria-label', (expanded ? 'Hide ' : 'Show ') + (title ? title.textContent.trim() : 'section') + ' lines');
                const chevron = button.querySelector('.so-section-chevron');
                if (chevron) chevron.style.transform = expanded ? '' : 'rotate(180deg)';
            }

            function syncAllButton() {
                if (!allButton) return;
                const anyExpanded = headings.some(function (button) { return button.getAttribute('aria-expanded') === 'true'; });
                allButton.setAttribute('aria-expanded', String(anyExpanded));
                allButton.querySelector('span').textContent = anyExpanded ? 'Collapse All' : 'Expand All';
                allButton.querySelector('i').className = 'bi ' + (anyExpanded ? 'bi-arrows-collapse' : 'bi-arrows-expand') + ' me-1';
            }

            root.addEventListener('click', function (event) {
                const button = event.target.closest('[data-so-section-toggle], [data-so-sections-toggle-all]');
                if (!button || !root.contains(button)) return;
                event.preventDefault();
                if (button === allButton) {
                    const expand = !headings.some(function (heading) { return heading.getAttribute('aria-expanded') === 'true'; });
                    headings.forEach(function (heading) { setExpanded(heading, expand); });
                } else {
                    setExpanded(button, button.getAttribute('aria-expanded') !== 'true');
                }
                syncAllButton();
            });
            syncAllButton();
        });
    });
})();
