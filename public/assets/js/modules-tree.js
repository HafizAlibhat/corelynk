/*
 modules-tree.js
 Renders modules.json as a tree structure in #corelynk-modules-tree
 Simple vanilla JS, no external dependencies
*/
(function(){
    function qs(id){ return document.getElementById(id); }
    function resolveModulesUrl(){
        if (window.MODULES_JSON_URL) return window.MODULES_JSON_URL;
        // Try to locate this script tag and derive the app base from its src
        try {
            const script = document.querySelector('script[src$="modules-tree.js"]');
            if (script && script.src) {
                const src = script.src;
                const base = src.replace(/\/assets\/js\/modules-tree\.js(\?.*)?$/, '');
                return base + '/modules.json';
            }
        } catch (e) {
            // ignore and fallback
        }
        // Fallback: use current path minus last segment
        try {
            const origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
            const parts = window.location.pathname.split('/');
            // If last part looks like a file, remove it; otherwise remove the trailing empty string
            if (!parts[parts.length-1]) parts.pop();
            parts.pop();
            const basePath = parts.join('/') || '';
            return origin + basePath + '/modules.json';
        } catch (e) {
            return '/modules.json';
        }
    }

    function fetchModules(){
        const url = resolveModulesUrl();
        return fetch(url, {cache:'no-cache'})
            .then(response => {
                if (!response.ok) throw new Error('Failed to load ' + url + ' (status ' + response.status + ')');
                return response.text();
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (err) {
                    // Provide useful error in UI
                    const root = document.getElementById('corelynk-modules-tree');
                    if (root) root.innerHTML = '<div class="alert alert-danger">Error parsing modules.json: ' + err.message + '</div>';
                    throw err;
                }
            });
    }
    function buildTree(modules){
        // Group modules by type for demo tree structure
        const roots = {
            'Production': [],
            'Accounting': [],
            'Inventory': [],
            'Quality Control': [],
            'Vendors': [],
            'Employees': [],
            'Reports': [],
            'Other': []
        };
        modules.forEach(m => {
            let group = 'Other';
            if(/production|work-order|process|log/i.test(m.name)) group = 'Production';
            else if(/account/i.test(m.name)) group = 'Accounting';
            else if(/inventory|component/i.test(m.name)) group = 'Inventory';
            else if(/quality/i.test(m.name)) group = 'Quality Control';
            else if(/vendor/i.test(m.name)) group = 'Vendors';
            else if(/employee/i.test(m.name)) group = 'Employees';
            else if(/report/i.test(m.name)) group = 'Reports';
            roots[group].push(m);
        });
        return roots;
    }
    function renderTree(roots){
        const root = qs('corelynk-modules-tree');
        if(!root) return;
        root.innerHTML = '';
        const ul = document.createElement('ul');
        ul.className = 'modules-tree-root';
        Object.entries(roots).forEach(([group, mods]) => {
            if(mods.length === 0) return;
            const li = document.createElement('li');
            li.innerHTML = `<span style="font-weight:600">${group}</span>`;
            const sub = document.createElement('ul');
            mods.forEach(m => {
                const subli = document.createElement('li');
                const link = document.createElement('a');
                link.href = m.path;
                link.textContent = m.name;
                link.className = '';
                link.style.cursor = 'pointer';
                link.addEventListener('click', function(e){
                    e.preventDefault();
                    window.open(m.path, '_blank');
                });
                subli.appendChild(link);
                const typeSpan = document.createElement('span');
                typeSpan.className = 'text-muted small ms-2';
                typeSpan.textContent = m.type;
                subli.appendChild(typeSpan);
                sub.appendChild(subli);
            });
            li.appendChild(sub);
            ul.appendChild(li);
        });
        root.appendChild(ul);
    }
    document.addEventListener('DOMContentLoaded', function(){
        fetchModules().then(buildTree).then(renderTree);
    });
})();