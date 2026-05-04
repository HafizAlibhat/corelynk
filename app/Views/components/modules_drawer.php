<style>
/* Simple right-side drawer (no Bootstrap dependency) */
#modulesDrawer {
    position: fixed;
    top: 0;
    right: -420px;
    width: 420px;
    height: 100vh;
    background: var(--white);
    box-shadow: -4px 0 16px rgba(0,0,0,0.2);
    transition: right 240ms ease-in-out;
    z-index: 2000;
    overflow-y: auto;
}
body.theme-dark #modulesDrawer { background: #0f172a; color: var(--gray-600); }
#modulesDrawer.open { right: 0; }
#modulesDrawer .drawer-header { padding: 1rem; border-bottom: 1px solid rgba(0,0,0,0.06); }
#modulesDrawer .drawer-body { padding: 1rem; }
#modulesDrawerClose { position: absolute; left: 10px; top: 10px; }
</style>

<div id="modulesDrawer" aria-hidden="true">
    <div class="drawer-header d-flex align-items-center">
        <button id="modulesDrawerClose" class="btn btn-sm btn-outline-secondary me-2" title="Close">×</button>
        <h5 class="mb-0">System Modules</h5>
    </div>
    <div class="drawer-body">
        <?= view('components/modules_panel') ?>
    </div>
</div>

<script>
    (function(){
        const drawer = document.getElementById('modulesDrawer');
        const openBtn = document.getElementById('modulesModalBtn');
        const closeBtn = document.getElementById('modulesDrawerClose');
        function open(){ drawer.classList.add('open'); drawer.setAttribute('aria-hidden','false'); }
        function close(){ drawer.classList.remove('open'); drawer.setAttribute('aria-hidden','true'); }
        if (openBtn) {
            openBtn.addEventListener('click', function(e){
                // prevent Bootstrap default if present
                try{ e.preventDefault(); } catch(e){}
                // toggle
                if (drawer.classList.contains('open')) close(); else open();
            });
        }
        if (closeBtn) closeBtn.addEventListener('click', close);
        // close on ESC
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
    })();
</script>
