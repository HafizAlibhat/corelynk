/*
 modules-dashboard.js
 Front-end widget to show system modules from /modules.json
 - Fetches /modules.json
 - Renders list of modules with link, description, visibility toggle
 - Visibility & removals stored in localStorage (quick prototype)
 - Provides export/import of settings
*/
(function(){
    const containerId = 'corelynk-modules-panel';
    function qs(id){ return document.getElementById(id); }

    function loadModules(){
        fetch('/modules.json', {cache: 'no-cache'})
            .then(r => r.json())
            .then(render)
            .catch(err => {
                console.error('Failed to load modules.json', err);
                render([]);
            });
    }

    function getState(){
        try{
            const s = localStorage.getItem('corelynk.modules.state');
            return s ? JSON.parse(s) : { visibility: {}, removed: {} };
        }catch(e){ return { visibility: {}, removed: {} }; }
    }

    function saveState(state){
        localStorage.setItem('corelynk.modules.state', JSON.stringify(state));
    }

    function render(modules){
        let root = qs(containerId);
        if(!root){
            console.warn('Modules panel container not found:', containerId);
            return;
        }
        const state = getState();
        root.innerHTML = '';

        const header = document.createElement('div');
        header.className = 'd-flex align-items-center justify-content-between mb-2';
        header.innerHTML = `<strong>System Modules</strong>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-1" id="modules-export">Export</button>
                <button class="btn btn-sm btn-outline-secondary" id="modules-import-btn">Import</button>
            </div>`;
        root.appendChild(header);

        const list = document.createElement('div');
        list.className = 'list-group';

        modules.forEach(mod => {
            const id = mod.id || mod.name;
            const removed = state.removed[id];
            const visible = state.visibility[id] !== undefined ? state.visibility[id] : true;

            const item = document.createElement('div');
            item.className = 'list-group-item d-flex align-items-start justify-content-between';
            item.style.gap = '12px';

            const left = document.createElement('div');
            left.style.flex = '1';
            left.innerHTML = `<div style="font-weight:600">${mod.name}</div>
                <div class="text-muted small">${mod.path || ''} ${mod.type ? ' • '+mod.type: ''}</div>`;

            const actions = document.createElement('div');
            actions.className = 'd-flex align-items-center gap-2';

            const anchor = document.createElement('a');
            anchor.href = mod.path || '#';
            anchor.target = '_blank';
            anchor.className = 'btn btn-sm btn-outline-primary';
            anchor.textContent = 'Open';
            if(removed) anchor.style.display = 'none';

            const toggle = document.createElement('input');
            toggle.type = 'checkbox';
            toggle.checked = visible && !removed;
            toggle.title = 'Show on dashboard';
            toggle.className = 'form-check-input';
            toggle.style.width = '1.2rem';

            toggle.addEventListener('change', () => {
                state.visibility[id] = toggle.checked;
                // un-remove if user toggles on
                if(toggle.checked && state.removed[id]) delete state.removed[id];
                saveState(state);
                anchor.style.display = (toggle.checked && !state.removed[id]) ? '' : 'none';
            });

            const removeBtn = document.createElement('button');
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.textContent = removed ? 'Restore' : 'Remove';
            removeBtn.addEventListener('click', () => {
                if(removed){
                    delete state.removed[id];
                } else {
                    state.removed[id] = true;
                    // mark visibility false
                    state.visibility[id] = false;
                }
                saveState(state);
                render(modules);
            });

            actions.appendChild(anchor);
            actions.appendChild(toggle);
            actions.appendChild(removeBtn);

            item.appendChild(left);
            item.appendChild(actions);
            list.appendChild(item);
        });

        root.appendChild(list);

        // Export/import handlers
        const exportBtn = qs('modules-export');
        if(exportBtn){
            exportBtn.addEventListener('click', ()=>{
                const s = localStorage.getItem('corelynk.modules.state') || '{}';
                const blob = new Blob([s], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = 'modules-state.json'; document.body.appendChild(a); a.click(); a.remove();
                URL.revokeObjectURL(url);
            });
        }

        const importBtn = qs('modules-import-btn');
        if(importBtn){
            importBtn.addEventListener('click', ()=>{
                const input = document.createElement('input');
                input.type = 'file'; input.accept = 'application/json';
                input.onchange = (e)=>{
                    const f = e.target.files[0];
                    if(!f) return;
                    const reader = new FileReader();
                    reader.onload = function(){
                        try{
                            const parsed = JSON.parse(reader.result);
                            localStorage.setItem('corelynk.modules.state', JSON.stringify(parsed));
                            loadModules();
                        }catch(err){ alert('Invalid JSON file'); }
                    };
                    reader.readAsText(f);
                };
                input.click();
            });
        }
    }

    // Auto-init on DOM ready: attach to container id if present
    document.addEventListener('DOMContentLoaded', function(){
        loadModules();
    });
})();
