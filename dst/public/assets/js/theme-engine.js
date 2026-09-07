/**
 * CoreLynk theme engine
 * Theme compatibility layer for the design system and legacy light/dark switcher.
 */
(function (window, document) {
  'use strict';

  var STORAGE_KEY = 'corelynk_design_theme_v1';
  var LEGACY_KEYS = ['global_theme_pref', 'theme'];
  var DEFAULT_THEME = 'corelynk-enterprise';
  var THEMES = [
    'corelynk-enterprise',
    'minimal-white',
    'executive-blue',
    'graphite-dark',
    'glass',
    'modern-purple',
    'classic-erp',
    'compact-erp'
  ];

  function isDarkPreference() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  function fromLegacyPreference(value) {
    if (value === 'dark') {
      return 'graphite-dark';
    }
    if (value === 'auto') {
      return isDarkPreference() ? 'graphite-dark' : DEFAULT_THEME;
    }
    if (value === 'light' || value === 'disabled') {
      return DEFAULT_THEME;
    }
    return null;
  }

  function readStoredTheme() {
    var value = null;
    try {
      value = window.localStorage.getItem(STORAGE_KEY);
      if (value && THEMES.indexOf(value) !== -1) {
        return value;
      }
      value = null;
      if (!value) {
        for (var i = 0; i < LEGACY_KEYS.length; i += 1) {
          value = window.localStorage.getItem(LEGACY_KEYS[i]);
          if (value) {
            break;
          }
        }
      }
    } catch (err) {
      value = null;
    }

    return fromLegacyPreference(value) || DEFAULT_THEME;
  }

  function setAttributePair(theme) {
    var html = document.documentElement;
    var body = document.body;

    html.setAttribute('data-cl-theme', theme);
    html.setAttribute('data-bs-theme', theme === 'graphite-dark' ? 'dark' : 'light');

    if (body) {
      body.setAttribute('data-cl-theme', theme);
      if (theme === 'graphite-dark') {
        body.classList.add('theme-dark');
      } else {
        body.classList.remove('theme-dark');
      }
    }
  }

  function persistTheme(theme) {
    try {
      window.localStorage.setItem(STORAGE_KEY, theme);
      window.localStorage.setItem('global_theme_pref', theme === 'graphite-dark' ? 'dark' : 'light');
    } catch (err) {
      // Ignore storage failures so theme switching never blocks rendering.
    }
  }

  function applyTheme(theme, shouldPersist) {
    var nextTheme = THEMES.indexOf(theme) === -1
      ? (fromLegacyPreference(theme) || DEFAULT_THEME)
      : theme;
    setAttributePair(nextTheme);
    if (shouldPersist !== false) {
      persistTheme(nextTheme);
    }
    window.dispatchEvent(new CustomEvent('corelynk:themechange', {
      detail: { theme: nextTheme }
    }));
    return nextTheme;
  }

  function initGlobalSearch() {
    var openButtons = document.querySelectorAll('[data-cl-search-open]');
    var closeButton = document.getElementById('clCloseGlobalSearch');
    var palette = document.getElementById('clGlobalSearchPalette');
    var input = document.getElementById('clGlobalSearchInput');
    var topbarInput = document.getElementById('clTopbarSearchInput');
    var dropdown = document.getElementById('clGlobalSearchDropdown');
    var endpoint = palette ? palette.getAttribute('data-search-endpoint') : '';
    var timer = null;
    var activeIndex = -1;
    var lastOpener = null;

    if (!palette || !input || !dropdown || !endpoint) {
      return;
    }

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function closeDropdown() {
      dropdown.classList.add('d-none');
      dropdown.innerHTML = '';
      activeIndex = -1;
    }

    function renderResults(items, query) {
      if (!Array.isArray(items) || items.length === 0) {
        dropdown.innerHTML = '<div class="cl-global-search-empty">No results for "' + escapeHtml(query) + '"</div>'
          + '<a class="cl-global-search-all" href="' + endpoint + '?q=' + encodeURIComponent(query) + '">Open full search</a>';
        dropdown.classList.remove('d-none');
        return;
      }

      dropdown.innerHTML = items.slice(0, 12).map(function (item) {
        return '<a class="cl-global-search-item" href="' + escapeHtml(item.url || '#') + '">'
          + '<div class="cl-global-search-main">'
          + '<span class="cl-global-search-title"><i class="bi ' + escapeHtml(item.icon || 'bi-search') + ' me-1"></i>' + escapeHtml(item.title || '') + '</span>'
          + '<span class="cl-global-search-module">' + escapeHtml(item.module || 'Result') + '</span>'
          + '</div><div class="cl-global-search-sub">' + escapeHtml(item.subtitle || '') + '</div></a>';
      }).join('') + '<a class="cl-global-search-all" href="' + endpoint + '?q=' + encodeURIComponent(query) + '">See all results</a>';
      dropdown.classList.remove('d-none');
      activeIndex = -1;
    }

    function fetchResults(query) {
      if (query.length < 2) {
        closeDropdown();
        return;
      }

      window.fetch(endpoint + '?format=json&limit=8&q=' + encodeURIComponent(query), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) { renderResults(payload.data || [], query); })
        .catch(closeDropdown);
    }

    function openPalette(opener) {
      lastOpener = opener || document.activeElement;
      if (topbarInput && topbarInput.value.trim() !== '' && input.value.trim() === '') {
        input.value = topbarInput.value.trim();
      }
      palette.classList.remove('d-none');
      palette.setAttribute('aria-hidden', 'false');
      document.body.classList.add('cl-global-search-open');
      window.setTimeout(function () {
        input.focus();
        if (input.value.trim().length >= 2) {
          fetchResults(input.value.trim());
        }
      }, 0);
    }

    function closePalette() {
      palette.classList.add('d-none');
      palette.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('cl-global-search-open');
      input.blur();
      closeDropdown();
      if (lastOpener && typeof lastOpener.focus === 'function') {
        lastOpener.focus();
      }
      lastOpener = null;
    }

    input.addEventListener('input', function () {
      var query = input.value.trim();
      window.clearTimeout(timer);
      timer = window.setTimeout(function () { fetchResults(query); }, 220);
    });

    input.addEventListener('keydown', function (event) {
      var items = Array.from(dropdown.querySelectorAll('.cl-global-search-item'));
      if (event.key === 'Escape') {
        closePalette();
      } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) {
        event.preventDefault();
        activeIndex = event.key === 'ArrowDown'
          ? (activeIndex + 1) % items.length
          : (activeIndex <= 0 ? items.length - 1 : activeIndex - 1);
        items.forEach(function (item, index) { item.classList.toggle('active', index === activeIndex); });
      } else if (event.key === 'Enter' && input.value.trim() !== '') {
        event.preventDefault();
        window.location.href = items[activeIndex >= 0 ? activeIndex : 0]
          ? items[activeIndex >= 0 ? activeIndex : 0].getAttribute('href')
          : endpoint + '?q=' + encodeURIComponent(input.value.trim());
      }
    });

    openButtons.forEach(function (openButton) {
      openButton.addEventListener('click', function () { openPalette(openButton); });
    });
    if (closeButton) {
      closeButton.addEventListener('click', closePalette);
    }

    document.addEventListener('keydown', function (event) {
      var target = event.target;
      var tag = target && target.tagName ? target.tagName.toLowerCase() : '';
      var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || Boolean(target && target.isContentEditable);
      var shortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
      var quickOpen = !typing && !event.ctrlKey && !event.metaKey && !event.altKey && event.key === '/';
      if (shortcut || quickOpen) {
        event.preventDefault();
        openPalette();
        input.select();
      } else if (event.key === 'Escape' && !palette.classList.contains('d-none')) {
        closePalette();
      }
    });

    palette.addEventListener('click', function (event) {
      if (event.target && event.target.getAttribute('data-close') === '1') {
        closePalette();
      }
    });
  }

  function initMobileNavigation() {
    var navigation = document.getElementById('globalNav');
    var toggler = document.querySelector('[data-cl-mobile-nav-toggle]');
    var closeButtons = document.querySelectorAll('[data-cl-mobile-nav-close]');
    var firstFocusable = navigation ? navigation.querySelector('a, button') : null;
    if (!navigation) {
      return;
    }

    function setNavigationOpen(open, restoreFocus) {
      navigation.classList.toggle('show', open);
      navigation.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.classList.toggle('cl-mobile-nav-open', open);
      if (toggler) {
        toggler.classList.toggle('collapsed', !open);
        toggler.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggler.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      }
      if (open && firstFocusable) {
        window.setTimeout(function () { firstFocusable.focus(); }, 40);
      } else if (!open && restoreFocus && toggler) {
        toggler.focus();
      }
    }

    function closeNavigation() {
      setNavigationOpen(false, false);
    }

    if (toggler) {
      toggler.addEventListener('click', function () {
        setNavigationOpen(!navigation.classList.contains('show'), false);
      });
    }

    closeButtons.forEach(function (button) {
      button.addEventListener('click', function () { setNavigationOpen(false, true); });
    });

    navigation.addEventListener('click', function (event) {
      var link = event.target.closest ? event.target.closest('.dropdown-item, .cl-sidebar-submenu-link, .nav-item > .nav-link') : null;
      if (window.innerWidth < 1200 && link && !link.hasAttribute('data-cl-submenu-toggle')) {
        closeNavigation();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && navigation.classList.contains('show')) {
        setNavigationOpen(false, true);
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 1200) {
        closeNavigation();
        navigation.removeAttribute('aria-hidden');
      } else if (!navigation.classList.contains('show')) {
        navigation.setAttribute('aria-hidden', 'true');
      }
    });

    if (window.innerWidth < 1200) {
      navigation.setAttribute('aria-hidden', navigation.classList.contains('show') ? 'false' : 'true');
    }
  }

  function init() {
    applyTheme(readStoredTheme(), false);

    var promoSearchButton = document.getElementById('clPromoSearch');
    if (promoSearchButton) {
      promoSearchButton.addEventListener('click', function () {
        var globalSearchButton = document.getElementById('clOpenGlobalSearch');
        if (globalSearchButton) {
          globalSearchButton.click();
        }
      });
    }

    document.addEventListener('click', function (event) {
      var legacyToggle = event.target.closest ? event.target.closest('[data-theme]') : null;
      if (!legacyToggle || legacyToggle.hasAttribute('data-cl-theme')) {
        return;
      }
      event.preventDefault();
      var preference = legacyToggle.getAttribute('data-theme');
      if (preference === 'auto') {
        try {
          window.localStorage.removeItem(STORAGE_KEY);
          window.localStorage.setItem('global_theme_pref', 'auto');
        } catch (err) {
          // Apply the current system preference even when storage is unavailable.
        }
        applyTheme(isDarkPreference() ? 'graphite-dark' : DEFAULT_THEME, false);
      } else {
        applyTheme(preference, true);
      }
    });

    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (event) {
        try {
          if (!window.localStorage.getItem(STORAGE_KEY) && window.localStorage.getItem('global_theme_pref') === 'auto') {
            applyTheme(event.matches ? 'graphite-dark' : DEFAULT_THEME, false);
          }
        } catch (err) {
          // Ignore storage restrictions; the current theme remains usable.
        }
      });
    }

    initGlobalSearch();
    initMobileNavigation();
  }

  function bindToggle(selector) {
    var nodes = document.querySelectorAll(selector);
    nodes.forEach(function (node) {
      node.addEventListener('click', function (event) {
        var theme = node.getAttribute('data-cl-theme');
        if (!theme) {
          theme = node.getAttribute('data-theme');
        }
        if (theme) {
          event.preventDefault();
          applyTheme(theme, true);
        }
      });
    });
  }

  window.CoreLynkThemeEngine = {
    themes: THEMES.slice(),
    getTheme: readStoredTheme,
    setTheme: applyTheme,
    init: init,
    bindToggle: bindToggle
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
