/** CoreLynk canonical list-view progressive enhancement. */
(function (window, document) {
  'use strict';

  var numberHeaders = /^(total|amount|balance|price|cost|debit|credit|qty|quantity|stock|on hand|paid|remaining|value|rate)/i;
  var stateHeaders = /^(status|state|type)$/i;
  var primaryHeaders = /^(customer|vendor|product|name|title|description|employee|account)$/i;
  var secondaryHeaders = /^(#|id|number|code|date|created|updated|reference|ref)$/i;

  function normalizeStatus(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[_\s]+/g, '-')
      .replace(/[^a-z0-9-]/g, '');
  }

  function directViewAction(cell) {
    var existingDirect = Array.from(cell.querySelectorAll('a')).find(function (link) {
      return !link.closest('.dropdown-menu') && (/\bview\b/i.test(link.getAttribute('title') || '') || link.querySelector('.bi-eye'));
    });
    var menuView = Array.from(cell.querySelectorAll('.dropdown-menu a')).find(function (link) {
      return /^view(?: details?)?$/i.test(link.textContent.trim()) || link.classList.contains('doc-view') || Boolean(link.querySelector('.bi-eye'));
    });

    if (!existingDirect && menuView) {
      existingDirect = menuView.cloneNode(true);
      existingDirect.className = 'cl-action-icon cl-action-view';
      existingDirect.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
      existingDirect.setAttribute('title', 'View');
      existingDirect.setAttribute('aria-label', 'View');
      var dropdown = cell.querySelector('.dropdown');
      cell.insertBefore(existingDirect, dropdown || cell.firstChild);
      var menuItem = menuView.closest('li');
      if (menuItem) {
        menuItem.remove();
      }
    }

    if (existingDirect) {
      existingDirect.classList.add('cl-action-icon', 'cl-action-view');
      existingDirect.setAttribute('title', existingDirect.getAttribute('title') || 'View');
      existingDirect.setAttribute('aria-label', existingDirect.getAttribute('aria-label') || 'View');
    }

    cell.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (button) {
      button.classList.add('cl-action-icon');
      button.setAttribute('title', button.getAttribute('title') || 'More actions');
      button.setAttribute('aria-label', button.getAttribute('aria-label') || 'More actions');
      button.setAttribute('data-bs-boundary', 'viewport');
    });

    consolidateActions(cell, existingDirect);
  }

  function consolidateActions(cell, viewAction) {
    if (!viewAction || cell.querySelector('.dropdown')) return;
    var container = viewAction.parentElement && viewAction.parentElement.classList.contains('btn-group')
      ? viewAction.parentElement
      : cell;
    var secondary = Array.from(container.children).filter(function (node) {
      return node !== viewAction && node.matches && node.matches('a, button, form');
    });
    if (!secondary.length) return;

    var dropdown = document.createElement('div');
    dropdown.className = 'dropdown';
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'cl-action-icon dropdown-toggle';
    toggle.setAttribute('data-bs-toggle', 'dropdown');
    toggle.setAttribute('data-bs-boundary', 'viewport');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'More actions');
    toggle.setAttribute('title', 'More actions');
    toggle.innerHTML = '<i class="bi bi-three-dots-vertical" aria-hidden="true"></i>';
    var menu = document.createElement('ul');
    menu.className = 'dropdown-menu dropdown-menu-end';

    secondary.forEach(function (control) {
      var item = document.createElement('li');
      if (control.matches('form')) {
        control.classList.add('cl-action-menu-form');
        var formControl = control.querySelector('button, a');
        if (formControl) formControl.classList.add('dropdown-item', 'cl-action-menu-item');
      } else {
        control.classList.add('dropdown-item', 'cl-action-menu-item');
      }
      item.appendChild(control);
      menu.appendChild(item);
    });

    dropdown.appendChild(toggle);
    dropdown.appendChild(menu);
    container.appendChild(dropdown);
  }

  function enhanceStateCell(cell) {
    var badge = cell.querySelector('.badge, .cl-badge, .status-badge, .status-pill, .tag-pill, .tt-status');
    var text = cell.textContent.trim();
    if (!badge && cell.children.length === 1 && cell.firstElementChild.matches('span, a')) {
      badge = cell.firstElementChild;
    }
    if (!badge && text && text !== '-' && cell.children.length === 0) {
      badge = document.createElement('span');
      badge.textContent = text;
      cell.textContent = '';
      cell.appendChild(badge);
    }
    if (badge) {
      badge.classList.add('cl-badge');
      if (!badge.dataset.status) {
        badge.dataset.status = normalizeStatus(badge.textContent);
      }
    }
  }

  function enhanceTable(table) {
    if (table.hasAttribute('data-cl-table-ignore')) {
      return;
    }
    var firstPass = table.dataset.clTableReady !== '1';
    table.dataset.clTableReady = '1';
    table.classList.add('cl-table');

    var wrapper = table.closest('.table-responsive, .cl-table-scroll');
    if (wrapper) {
      wrapper.classList.add('cl-table-scroll');
    } else if (firstPass && table.parentNode) {
      wrapper = document.createElement('div');
      wrapper.className = 'cl-table-scroll';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
    if (wrapper) {
      bindScrollBridge(wrapper);
    }

    var headers = Array.from(table.querySelectorAll(':scope > thead > tr:first-child > th'));
    if (!headers.length) {
      headers = Array.from(table.querySelectorAll(':scope > tr:first-child > th'));
    }
    if (!headers.length) {
      return;
    }

    headers.forEach(function (header, index) {
      var name = header.textContent.trim().replace(/\s+/g, ' ');
      var className = '';
      if (numberHeaders.test(name)) className = 'cl-cell-number';
      else if (stateHeaders.test(name)) className = 'cl-cell-state';
      else if (/^actions?$/i.test(name)) className = 'cl-col-actions';
      else if (primaryHeaders.test(name)) className = 'cl-cell-primary';
      else if (secondaryHeaders.test(name)) className = 'cl-cell-secondary';
      if (className) header.classList.add(className);

      table.querySelectorAll(':scope > tbody > tr, :scope > tr:not(:first-child)').forEach(function (row) {
        var cell = row.children[index];
        if (!cell) return;
        if (className) cell.classList.add(className === 'cl-col-actions' ? 'cl-table-actions' : className);
        if (className === 'cl-cell-state') enhanceStateCell(cell);
        if (className === 'cl-col-actions') directViewAction(cell);
      });
    });
  }

  function nearestScrollableY(node) {
    var current = node.parentElement;
    while (current && current !== document.body) {
      var style = window.getComputedStyle(current);
      var canScroll = /(auto|scroll|overlay)/.test(style.overflowY);
      if (canScroll && current.scrollHeight > current.clientHeight + 1) {
        return current;
      }
      current = current.parentElement;
    }
    return document.scrollingElement || document.documentElement;
  }

  function bindScrollBridge(wrapper) {
    if (wrapper.dataset.clScrollBridge === '1') return;
    wrapper.dataset.clScrollBridge = '1';
    wrapper.addEventListener('wheel', function (event) {
      if (event.defaultPrevented || event.ctrlKey || event.shiftKey || Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
        return;
      }
      var canScrollY = wrapper.scrollHeight > wrapper.clientHeight + 1;
      if (canScrollY) {
        var atTop = wrapper.scrollTop <= 0;
        var atBottom = wrapper.scrollTop + wrapper.clientHeight >= wrapper.scrollHeight - 1;
        if (!(event.deltaY < 0 && atTop) && !(event.deltaY > 0 && atBottom)) {
          return;
        }
      }
      var target = nearestScrollableY(wrapper);
      if (!target) return;
      var before = target.scrollTop;
      target.scrollTop += event.deltaY;
      if (target.scrollTop !== before) {
        event.preventDefault();
      }
    }, { passive: false });
  }

  function enhanceProductChips(root) {
    root.querySelectorAll('.view-more-products, .view-more-link').forEach(function (control) {
      var match = control.textContent.match(/(?:view\s*)?(\d+)\s*more/i);
      if (!match) return;
      control.textContent = '+' + match[1];
      control.classList.add('cl-tag-chip', 'cl-tag-chip--more');
      control.setAttribute('aria-label', control.getAttribute('title') || ('Show ' + match[1] + ' more items'));
    });
    root.querySelectorAll('.doc-tags .badge, .doc-tags [class*="tag-"]').forEach(function (tag) {
      tag.classList.add('cl-tag-chip');
    });
  }

  /**
   * Places a fixed-position row-action menu (.pl-more-menu) next to its trigger.
   *
   * The list pages used to pin the menu at the trigger's bottom edge and nothing
   * else, so a row near the foot of the window opened its menu below the fold —
   * all the user saw was a clipped sliver. This flips the menu above the button
   * when there is no room under it, keeps it inside the viewport on every side,
   * and lets a menu taller than the window scroll instead of being cut off.
   */
  function openFloatingMenu(menu, trigger) {
    if (!menu || !trigger) return;

    var gap = 4;
    var pad = 8;

    // Measure while it is laid out but not yet visible, so nothing flashes.
    menu.style.visibility = 'hidden';
    menu.style.maxHeight = '';
    menu.style.overflowY = '';
    menu.style.right = 'auto';
    menu.classList.add('is-open');

    var rect = trigger.getBoundingClientRect();
    var width = menu.offsetWidth;
    var height = menu.offsetHeight;
    var roomBelow = window.innerHeight - rect.bottom - gap - pad;
    var roomAbove = rect.top - gap - pad;

    if (height > Math.max(roomBelow, roomAbove)) {
      menu.style.maxHeight = Math.max(120, Math.max(roomBelow, roomAbove)) + 'px';
      menu.style.overflowY = 'auto';
      height = menu.offsetHeight;
    }

    var top = (height <= roomBelow || roomBelow >= roomAbove)
      ? rect.bottom + gap
      : rect.top - gap - height;
    top = Math.min(Math.max(pad, top), Math.max(pad, window.innerHeight - pad - height));

    var left = Math.min(Math.max(pad, rect.right - width), Math.max(pad, window.innerWidth - pad - width));

    menu.style.top = top + 'px';
    menu.style.left = left + 'px';
    menu.style.visibility = '';
  }

  function closeFloatingMenus() {
    document.querySelectorAll('.pl-more-menu.is-open').forEach(function (menu) {
      menu.classList.remove('is-open');
    });
  }

  // The menu is fixed to the viewport: once the page moves under it, it is
  // pointing at nothing, so it closes rather than floating over the wrong row.
  window.addEventListener('scroll', closeFloatingMenus, true);
  window.addEventListener('resize', closeFloatingMenus);
  window.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeFloatingMenus();
  });

  window.clOpenFloatingMenu = openFloatingMenu;
  window.clCloseFloatingMenus = closeFloatingMenus;

  function enhance(root) {
    root.querySelectorAll('table').forEach(enhanceTable);
    enhanceProductChips(root);
  }

  function init() {
    var roots = document.querySelectorAll('.cl-list-host-page, .cl-list-page, [data-cl-list]');
    roots.forEach(enhance);

    if (!window.MutationObserver) return;
    roots.forEach(function (root) {
      var observer = new MutationObserver(function (records) {
        if (!records.some(function (record) { return record.addedNodes.length > 0; })) return;
        enhance(root);
      });
      observer.observe(root, { childList: true, subtree: true });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
