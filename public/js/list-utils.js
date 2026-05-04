/**
 * Global List Management Utilities
 * Provides reusable pagination, filtering, and tab management for all list views
 * Can be imported in any list view (purchase, sales, inventory, etc)
 */

class ListManager {
  constructor(options = {}) {
    this.currentPage = 1;
    this.pageSize = options.pageSize || 20;
    this.allRows = [];
    this.filteredRows = [];
    this.currentFilter = options.defaultFilter || 'all';
    this.filters = options.filters || {};
    this.onRenderCallback = options.onRender || null;
    this.containerSelector = options.containerSelector || '#listContainer';
    this.tabsSelector = options.tabsSelector || '#listTabs';
  }

  /**
   * Set all available rows
   */
  setRows(rows) {
    this.allRows = rows || [];
    this.currentPage = 1;
    this.applyFilter(this.currentFilter);
  }

  /**
   * Apply filter based on filter name
   */
  applyFilter(filterName) {
    this.currentFilter = filterName;
    const filterFn = this.filters[filterName] || ((row) => true);
    this.filteredRows = this.allRows.filter(filterFn);
    this.currentPage = 1;
    this.render();
    this.updateTabsUI();
  }

  /**
   * Get paginated rows for current page
   */
  getPaginatedRows() {
    const start = (this.currentPage - 1) * this.pageSize;
    const end = start + this.pageSize;
    return this.filteredRows.slice(start, end);
  }

  /**
   * Get total pages
   */
  getTotalPages() {
    return Math.ceil(this.filteredRows.length / this.pageSize);
  }

  /**
   * Go to specific page
   */
  goToPage(pageNum) {
    const maxPage = this.getTotalPages();
    this.currentPage = Math.max(1, Math.min(pageNum, maxPage));
    this.render();
  }

  /**
   * Next page
   */
  nextPage() {
    this.goToPage(this.currentPage + 1);
  }

  /**
   * Previous page
   */
  prevPage() {
    this.goToPage(this.currentPage - 1);
  }

  /**
   * Update tabs UI to show counts and highlight active
   */
  updateTabsUI() {
    const tabsContainer = document.querySelector(this.tabsSelector);
    if (!tabsContainer) return;

    // Update counts for each tab
    Object.keys(this.filters).forEach(filterName => {
      const filterFn = this.filters[filterName];
      const count = this.allRows.filter(filterFn).length;
      const tab = tabsContainer.querySelector(`[data-filter="${filterName}"]`);
      if (tab) {
        const badge = tab.querySelector('.count-badge');
        if (badge) {
          badge.textContent = count;
        }
      }
    });

    // Highlight active tab
    tabsContainer.querySelectorAll('[data-filter]').forEach(tab => {
      if (tab.getAttribute('data-filter') === this.currentFilter) {
        tab.classList.add('active');
      } else {
        tab.classList.remove('active');
      }
    });
  }

  /**
   * Render the list
   */
  render() {
    if (!this.onRenderCallback) {
      console.warn('No onRender callback defined for ListManager');
      return;
    }

    const paginatedRows = this.getPaginatedRows();
    const totalPages = this.getTotalPages();
    const totalRows = this.filteredRows.length;

    this.onRenderCallback({
      rows: paginatedRows,
      currentPage: this.currentPage,
      totalPages: totalPages,
      totalRows: totalRows,
      pageSize: this.pageSize,
      hasNextPage: this.currentPage < totalPages,
      hasPrevPage: this.currentPage > 1
    });

    this.renderPagination();
  }

  /**
   * Render pagination controls
   */
  renderPagination() {
    const totalPages = this.getTotalPages();
    const totalRows = this.filteredRows.length;

    if (totalRows === 0) return;

    let paginationHtml = `<nav aria-label="Pagination" class="d-flex justify-content-between align-items-center gap-2 mt-3 mb-2" style="font-size:0.9rem;">`;
    
    paginationHtml += `<div class="text-muted small">
      Showing ${Math.min((this.currentPage - 1) * this.pageSize + 1, totalRows)} - ${Math.min(this.currentPage * this.pageSize, totalRows)} of ${totalRows}
    </div>`;

    paginationHtml += `<ul class="pagination mb-0 gap-1" style="font-size:0.85rem;">`;

    // Previous button
    paginationHtml += `<li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
      <button class="page-link page-prev" style="padding:0.25rem 0.5rem">← Prev</button>
    </li>`;

    // Page numbers
    const maxButtons = 5;
    let startPage = Math.max(1, this.currentPage - Math.floor(maxButtons / 2));
    let endPage = Math.min(totalPages, startPage + maxButtons - 1);
    if (endPage - startPage + 1 < maxButtons) {
      startPage = Math.max(1, endPage - maxButtons + 1);
    }

    if (startPage > 1) {
      paginationHtml += `<li class="page-item"><button class="page-link page-go" data-page="1" style="padding:0.25rem 0.5rem">1</button></li>`;
      if (startPage > 2) paginationHtml += `<li class="page-item disabled"><span class="page-link" style="padding:0.25rem 0.5rem">...</span></li>`;
    }

    for (let i = startPage; i <= endPage; i++) {
      paginationHtml += `<li class="page-item ${i === this.currentPage ? 'active' : ''}">
        <button class="page-link page-go" data-page="${i}" style="padding:0.25rem 0.5rem">${i}</button>
      </li>`;
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) paginationHtml += `<li class="page-item disabled"><span class="page-link" style="padding:0.25rem 0.5rem">...</span></li>`;
      paginationHtml += `<li class="page-item"><button class="page-link page-go" data-page="${totalPages}" style="padding:0.25rem 0.5rem">${totalPages}</button></li>`;
    }

    // Next button
    paginationHtml += `<li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
      <button class="page-link page-next" style="padding:0.25rem 0.5rem">Next →</button>
    </li>`;

    paginationHtml += `</ul></nav>`;

    const container = document.querySelector(this.containerSelector);
    if (container) {
      let paginationContainer = container.querySelector('.pagination-container');
      if (!paginationContainer) {
        paginationContainer = document.createElement('div');
        paginationContainer.className = 'pagination-container';
        container.appendChild(paginationContainer);
      }
      paginationContainer.innerHTML = paginationHtml;

      // Wire up pagination buttons
      paginationContainer.querySelector('.page-prev')?.addEventListener('click', () => this.prevPage());
      paginationContainer.querySelector('.page-next')?.addEventListener('click', () => this.nextPage());
      paginationContainer.querySelectorAll('.page-go').forEach(btn => {
        btn.addEventListener('click', (e) => this.goToPage(parseInt(e.target.getAttribute('data-page'), 10)));
      });
    }
  }

  /**
   * Setup tab switching
   */
  setupTabs() {
    const tabsContainer = document.querySelector(this.tabsSelector);
    if (!tabsContainer) return;

    tabsContainer.querySelectorAll('[data-filter]').forEach(tab => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        const filterName = tab.getAttribute('data-filter');
        this.applyFilter(filterName);
      });
    });

    this.updateTabsUI();
  }

  /**
   * Get filter count (useful for rendering tabs)
   */
  getFilterCount(filterName) {
    const filterFn = this.filters[filterName];
    if (!filterFn) return 0;
    return this.allRows.filter(filterFn).length;
  }
}

// Export for use in modules or globally
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ListManager;
}
