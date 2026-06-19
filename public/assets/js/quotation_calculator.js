// Minimal quotation calculator & product autocomplete (ES5-compat)
document.addEventListener('DOMContentLoaded', function(){
    // Product autocomplete is now provided by CoreLynkAutocomplete (behavior-preserving extraction).
    // Keep a backward-compatible global function for any older pages/scripts.
    window.attachProductAutocomplete = function(input, byName){
        if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
            return window.CoreLynkAutocomplete.attachProductAutocomplete(input, byName);
        }
    };

    // Attach to any existing rows (same selectors as before)
    if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.initProductAutofill) {
        window.CoreLynkAutocomplete.initProductAutofill(document);
    }

    // NOTE: Totals are calculated server-side. This script should not do client-side math.
    // Keep a debounced hook in case other scripts want to trigger UI refresh.
    var recalc = debounce(function(){
        // Intentionally empty: backend is source of truth.
        // Any page that needs totals updated should do so via its existing AJAX flow.
    }, 200);

    // bind change/input events for recalculation (incl. discount/tax)
    document.addEventListener('change', function(e){ if (e.target && (e.target.classList && (e.target.classList.contains('line-qty') || e.target.classList.contains('line-price') || e.target.classList.contains('line-discount') || e.target.classList.contains('line-tax') || e.target.classList.contains('line-tax-type') || e.target.classList.contains('line-discount-type') || e.target.id === 'shipping_amount'))) recalc(); });
    document.addEventListener('input', function(e){ if (e.target && (e.target.classList && (e.target.classList.contains('line-qty') || e.target.classList.contains('line-price') || e.target.classList.contains('line-discount') || e.target.classList.contains('line-tax') || e.target.id === 'shipping_amount'))) recalc(); });
});
