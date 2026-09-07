// Pure JS only. Expects vendorId, vendorBase, csrfTokenName, csrfTokenValue set in the page.

(function(){
'use strict';

function escapeHtml(s) {
    if (!s) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

window.editContact = function(btn) {
    if (!btn) return;
    const contactId = btn.getAttribute('data-contact-id');
    const name = btn.getAttribute('data-name');
    const phone = btn.getAttribute('data-phone');
    const cnic = btn.getAttribute('data-cnic');
    const designation = btn.getAttribute('data-designation');

    const nameInput = document.getElementById('contact_name');
    const phoneInput = document.getElementById('contact_phone');
    const cnicInput = document.getElementById('contact_cnic');
    const designationInput = document.getElementById('contact_designation');
    const contactIdInput = document.getElementById('contact_id_input');
    const form = document.getElementById('vendorContactForm');

    if (nameInput) nameInput.value = name || '';
    if (phoneInput) phoneInput.value = phone || '';
    if (cnicInput) cnicInput.value = cnic || '';
    if (designationInput) designationInput.value = designation || '';
    if (contactIdInput) contactIdInput.value = contactId || '';

    if (form && typeof vendorBase !== 'undefined' && typeof vendorId !== 'undefined') {
        form.setAttribute('action', vendorBase + '/' + vendorId + '/updateContact/' + contactId);
    }

    const contactFormWrapper = document.getElementById('contact_form');
    const addBtn = document.getElementById('btn-add-contact');
    if (contactFormWrapper) contactFormWrapper.classList.remove('d-none');
    if (addBtn) addBtn.style.display = 'none';
};

window.deleteContact = function(btn) {
    if (!btn) return;
    const contactId = btn.getAttribute('data-contact-id');
    if (!contactId) return;
    if (!confirm('Delete this contact?')) return;

    const fd = new FormData();
    if (typeof csrfTokenName !== 'undefined' && typeof csrfTokenValue !== 'undefined' && csrfTokenName) {
        fd.append(csrfTokenName, csrfTokenValue);
    }

    const deleteUrl = (typeof vendorBase !== 'undefined' && typeof vendorId !== 'undefined')
        ? vendorBase + '/' + vendorId + '/deleteContact/' + contactId
        : '/vendors/' + vendorId + '/deleteContact/' + contactId;

    fetch(deleteUrl, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => {
        const ct = r.headers.get('content-type') || '';
        if (r.ok && ct.indexOf('application/json') !== -1) return r.json();
        return r.text().then(t => { throw new Error('Non-JSON response: ' + r.status + ' ' + t.substring(0,150)); });
    })
    .then(j => {
        if (j && j.success) {
            const elem = document.getElementById('contact_li_' + contactId);
            if (elem) elem.remove();
            alert('Contact deleted');
        } else {
            alert('Error: ' + (j && j.message ? j.message : 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('Failed to delete: ' + err.message);
    });
};

document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btn-add-contact');
    const contactForm = document.getElementById('contact_form');
    const cancelBtn = document.getElementById('cancel_contact');
    const vendorContactForm = document.getElementById('vendorContactForm');

    if (btn && contactForm && vendorContactForm) {
        btn.addEventListener('click', function(){
            // reset form
            ['contact_name','contact_phone','contact_cnic','contact_designation','contact_id_input'].forEach(id=>{
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            // set add action
            if (typeof vendorBase !== 'undefined' && typeof vendorId !== 'undefined') {
                vendorContactForm.setAttribute('action', vendorBase + '/' + vendorId + '/addContact');
            }
            contactForm.classList.remove('d-none');
            btn.style.display = 'none';
        });
    }

    if (cancelBtn && contactForm && btn) {
        cancelBtn.addEventListener('click', function(){
            contactForm.classList.add('d-none');
            btn.style.display = 'block';
        });
    }

    if (vendorContactForm) {
        vendorContactForm.addEventListener('submit', function(e){
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            const url = form.getAttribute('action');
            if (!url) {
                alert('Form action missing');
                return;
            }
            // Append CSRF token if available
            if (typeof csrfTokenName !== 'undefined' && typeof csrfTokenValue !== 'undefined' && csrfTokenName) {
                data.append(csrfTokenName, csrfTokenValue);
            }
            console.log('Form submit to:', url, 'CSRF:', csrfTokenName, csrfTokenValue);

            fetch(url, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => {
                const ct = r.headers.get('content-type') || '';
                if (r.ok && ct.indexOf('application/json') !== -1) return r.json();
                return r.text().then(t => { throw new Error('Non-JSON response: ' + r.status); });
            })
            .then(j => {
                if (j && j.success) {
                    const c = j.data;
                    let container = document.getElementById('contacts_container');
                    if (!container) {
                        container = document.createElement('div');
                        container.className = 'list-group';
                        container.id = 'contacts_container';
                        const contactsList = document.getElementById('contacts_list');
                        if (contactsList) {
                            contactsList.innerHTML = '';
                            contactsList.appendChild(container);
                        }
                    }

                    const existing = document.getElementById('contact_li_' + c.id);
                    if (existing) {
                        existing.querySelector('.flex-grow-1').innerHTML =
                            '<strong>' + escapeHtml(c.name) + '</strong>' +
                            '<div class="small text-muted">' + escapeHtml(c.designation || '') +
                            (c.cnic ? (' • ' + escapeHtml(c.cnic)) : '') +
                            (c.phone ? (' • ' + escapeHtml(c.phone)) : '') + '</div>';
                    } else {
                        const div = document.createElement('div');
                        div.id = 'contact_li_' + c.id;
                        div.className = 'list-group-item d-flex justify-content-between align-items-center';
                        div.innerHTML =
                            '<div class="flex-grow-1"><strong>' + escapeHtml(c.name) + '</strong>' +
                            '<div class="small text-muted">' + escapeHtml(c.designation || '') +
                            (c.cnic ? (' • ' + escapeHtml(c.cnic)) : '') +
                            (c.phone ? (' • ' + escapeHtml(c.phone)) : '') + '</div></div>' +
                            '<div class="d-flex gap-2">' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary" data-contact-id="' + c.id + '" data-name="' + escapeHtml(c.name).replace(/"/g, '&quot;') + '" data-phone="' + escapeHtml(c.phone || '').replace(/"/g, '&quot;') + '" data-cnic="' + escapeHtml(c.cnic || '').replace(/"/g, '&quot;') + '" data-designation="' + escapeHtml(c.designation || '').replace(/"/g, '&quot;') + '" onclick="editContact(this)"><i class="bi bi-pencil"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger" data-contact-id="' + c.id + '" onclick="deleteContact(this)"><i class="bi bi-trash"></i></button>' +
                            (c.is_primary ? '<span class="badge bg-primary">Primary</span>' : '') +
                            '</div>';
                        container.insertBefore(div, container.firstChild);
                    }

                    form.reset();
                    const contactIdInput = document.getElementById('contact_id_input');
                    if (contactIdInput) contactIdInput.value = '';
                    if (contactForm) contactForm.classList.add('d-none');
                    if (btn) btn.style.display = 'block';
                    alert('Contact saved');
                } else {
                    alert('Error: ' + (j && j.message ? j.message : 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Form error:', err);
                alert('Failed: ' + err.message);
            });
        });
    }
});
})();