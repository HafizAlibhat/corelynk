(function () {
    if (window.__corelynkProductHoverPreviewInit) {
        return;
    }
    window.__corelynkProductHoverPreviewInit = true;

    function canUseHover() {
        try {
            return window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        } catch (e) {
            return true;
        }
    }

    if (!canUseHover()) {
        return;
    }

    var previewCard = null;
    var previewImg = null;
    var activeThumb = null;

    function ensurePreviewCard() {
        if (previewCard) {
            return;
        }
        previewCard = document.createElement('div');
        previewCard.id = 'global-product-hover-preview';
        previewCard.setAttribute('aria-hidden', 'true');

        previewImg = document.createElement('img');
        previewImg.alt = 'Product preview';

        previewCard.appendChild(previewImg);
        document.body.appendChild(previewCard);
    }

    function resolvePreviewSource(img) {
        if (!img) {
            return '';
        }

        var dataSrc = img.getAttribute('data-preview-src');
        if (dataSrc) {
            return dataSrc;
        }

        var host = img.closest('[data-image-src]');
        if (host) {
            var hostSrc = host.getAttribute('data-image-src');
            if (hostSrc) {
                return hostSrc;
            }
        }

        return img.getAttribute('src') || '';
    }

    function positionPreview(clientX, clientY) {
        if (!previewCard) {
            return;
        }

        var margin = 10;
        var gap = 14;
        var cardW = previewCard.offsetWidth || 262;
        var cardH = previewCard.offsetHeight || 262;
        var vw = window.innerWidth || document.documentElement.clientWidth || 0;
        var vh = window.innerHeight || document.documentElement.clientHeight || 0;

        var left = clientX + gap;
        var top = clientY + gap;

        if (left + cardW + margin > vw) {
            left = clientX - cardW - gap;
        }
        if (top + cardH + margin > vh) {
            top = vh - cardH - margin;
        }

        if (left < margin) {
            left = margin;
        }
        if (top < margin) {
            top = margin;
        }

        previewCard.style.left = String(left) + 'px';
        previewCard.style.top = String(top) + 'px';
    }

    function showPreview(img, event) {
        var src = resolvePreviewSource(img);
        if (!src) {
            hidePreview();
            return;
        }

        ensurePreviewCard();
        activeThumb = img;
        previewImg.src = src;
        previewCard.style.display = 'block';
        previewCard.setAttribute('aria-hidden', 'false');
        positionPreview(event.clientX || 0, event.clientY || 0);
    }

    function hidePreview() {
        if (!previewCard) {
            activeThumb = null;
            return;
        }
        previewCard.style.display = 'none';
        previewCard.setAttribute('aria-hidden', 'true');
        activeThumb = null;
    }

    function getThumbFromEventTarget(target) {
        if (!target || !target.closest) {
            return null;
        }
        return target.closest('img.js-product-hover-thumb');
    }

    document.addEventListener('mouseover', function (event) {
        var img = getThumbFromEventTarget(event.target);
        if (!img) {
            return;
        }
        showPreview(img, event);
    });

    document.addEventListener('mousemove', function (event) {
        if (!activeThumb || !previewCard || previewCard.style.display === 'none') {
            return;
        }
        positionPreview(event.clientX || 0, event.clientY || 0);
    });

    document.addEventListener('mouseout', function (event) {
        if (!activeThumb) {
            return;
        }
        var fromThumb = getThumbFromEventTarget(event.target);
        if (!fromThumb || fromThumb !== activeThumb) {
            return;
        }

        var toThumb = getThumbFromEventTarget(event.relatedTarget);
        if (toThumb && toThumb === activeThumb) {
            return;
        }

        hidePreview();
    });

    document.addEventListener('scroll', function () {
        if (activeThumb) {
            hidePreview();
        }
    }, true);

    window.addEventListener('blur', hidePreview);
    window.addEventListener('resize', hidePreview);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hidePreview();
        }
    });
})();
