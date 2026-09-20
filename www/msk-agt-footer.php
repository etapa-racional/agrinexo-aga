<?php
// The tool surface this shell publishes to the browser.
$mskToolSurface = $GLOBALS['AGN_CFG_LANGUAGE']['wmx_dir'] ?? '../wmx';
?>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', (event) => {
        const htmlElement = document.documentElement;
        const switchElement = document.getElementById('darkModeSwitch');

        // Set the default theme to dark if no setting is found in local storage
        const currentTheme = localStorage.getItem('bsTheme') || 'light';
        htmlElement.setAttribute('data-bs-theme', currentTheme);
        switchElement.checked = currentTheme === 'dark';

        switchElement.addEventListener('change', function() {
            if (this.checked) {
                htmlElement.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('bsTheme', 'dark');
            } else {
                htmlElement.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('bsTheme', 'light');
            }
        });
    });
</script>


<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<script src="ger/bst/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Shared client-side guard for pages restricted to the superuser (System, id 1).
    // This is only a UX gate: app/*.php pages have no server-side session, so the
    // real enforcement always lives in the corresponding api/*.php endpoint, which
    // validates the JWT signature and re-checks the user id on every request.
    const AuthGuard = {
        decodeJwtPayload(token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                return JSON.parse(jsonPayload);
            } catch (e) {
                return null;
            }
        },

        getUserId(token) {
            const payload = token ? this.decodeJwtPayload(token) : null;
            if (!payload || !payload.user_id) return null;
            if (payload.exp && payload.exp * 1000 < Date.now()) return null;
            return payload.user_id - 10000;
        },

        // Redirects away and returns false if the token doesn't belong to the
        // superuser; returns true (and does nothing) otherwise.
        requireSuperuser(authToken, notifyFn, redirectUrl) {
            redirectUrl = redirectUrl || 'databases.php';
            if (this.getUserId(authToken) !== 1) {
                if (typeof notifyFn === 'function') {
                    notifyFn(<?php echo tj('shell.superuser_only'); ?>, 'negative', 'lock');
                }
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1500);
                return false;
            }
            return true;
        }
    };
</script>

<script>
    const appMenuToggle = document.getElementById('appMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const contentWrapper = document.getElementById('contentWrapper');
    let sidebarState = 'expanded';
    let assistantFrameKrd = null;

    const isWideScreen = () => window.matchMedia('(min-width: 1200px)').matches;

    function toggleSidebar() {
        if (sidebarState === 'expanded') {
            sidebar.classList.remove('sidebar-expanded');
            sidebar.classList.add('sidebar-collapsed');
            contentWrapper.classList.remove('sidebar-expanded');
            contentWrapper.classList.add('sidebar-collapsed');
            sidebarState = 'collapsed';
        } else {
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.classList.add('sidebar-expanded');
            contentWrapper.classList.remove('sidebar-collapsed');
            contentWrapper.classList.add('sidebar-expanded');
            sidebarState = 'expanded';
        }
    }

    // On wide screens the hamburger is a sidebar toggle; below 1200px it stays
    // a Bootstrap dropdown (the sidebar is hidden there).
    function syncAppMenuMode() {
        if (!appMenuToggle) return;
        if (isWideScreen()) {
            appMenuToggle.removeAttribute('data-bs-toggle');
            const dd = bootstrap.Dropdown.getInstance(appMenuToggle);
            if (dd) dd.hide();
        } else {
            appMenuToggle.setAttribute('data-bs-toggle', 'dropdown');
        }
    }

    if (appMenuToggle) {
        syncAppMenuMode();
        window.addEventListener('resize', syncAppMenuMode);

        appMenuToggle.addEventListener('click', (e) => {
            if (isWideScreen()) {
                e.preventDefault();
                toggleSidebar();
            }
        });
    }

    // ===== ASSISTANT PANE =====
    // Two things decide whether the pane is open: whether it is available at
    // all (the assistant needs a selected database) and assistantOpen below.
    // The viewport decides what "open" looks like - an inline column at
    // >=1200px, an off-canvas overlay with a scrim below.
    //
    // refreshMenuAuthState() only reports availability; it must never write
    // assistantOpen, or selecting a database would reopen a pane the user had
    // deliberately closed.
    const assistantToggle = document.getElementById('assistantToggle');
    const assistantPane = document.getElementById('assistantPane');
    const shellScrim = document.getElementById('shellScrim');
    const assistantFrame = document.getElementById('assistantFrame');

    // Plain page-lifetime state, like sidebarState above: resets on reload,
    // nothing is stored. Starts open only on wide screens, where the pane is
    // an inline column beside the content. As an overlay it would cover the
    // page the user just opened - and connecting a database would slide it in
    // (via refreshMenuAuthState) only for the navigation that follows to slide
    // it straight back out again.
    let assistantOpen = isWideScreen();

    function applyAssistantState() {
        const open = !!window.krd && assistantOpen;
        assistantPane.classList.toggle('assistant-collapsed', !open);
        if (assistantToggle) {
            assistantToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        // The scrim belongs to the overlay only. At >=1200px the pane is an
        // inline column and dimming the content would be wrong.
        shellScrim.classList.toggle('active', open && !isWideScreen());

        // Loaded lazily and then left alone: src is set the first time the
        // pane is actually opened for a given database, so closing and
        // reopening costs nothing and a user who never opens it never hits
        // the pane at all.
        //
        // agt_dir, not app_dir: the pane sits in the agent's own instance
        // directory so its pathless config require lands on the same
        // config.php the agent reads. ?lang= because it has its own dictionary.
        if (open && assistantFrameKrd !== window.krd) {
            assistantFrameKrd = window.krd;
            assistantFrame.src = <?php echo json_encode(
                                        ($GLOBALS['AGN_CFG_LANGUAGE']['agt_dir'] ?? '../agt') . '/chat.php?lang='
                                            . ($GLOBALS['AGN_CFG_LANGUAGE']['code'] ?? 'en') . '&krd=',
                                        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
                                    ); ?> + encodeURIComponent(window.krd);
        } else if (!window.krd) {
            assistantFrameKrd = null;
        }
    }

    function setAssistantOpen(open) {
        assistantOpen = open;
        applyAssistantState();
    }

    // Below 1200px the pane is an overlay sitting on top of the content, so it
    // gets out of the way when the content frame navigates - the same thing
    // the scrim and Esc do. Above 1200px it is a side-by-side column and stays
    // put. The listener is on 'load' rather than on the .src assignments in
    // msk-agt-main.php because most navigation never touches .src: the app pages
    // move themselves with location.href (databases.php -> fields.php on
    // connect, login.php on success). 'load' catches both routes.
    let contentFrameLoaded = false;
    document.getElementById('contentFrame').addEventListener('load', () => {
        // Skip the initial databases.php load - nothing has been navigated yet.
        if (!contentFrameLoaded) {
            contentFrameLoaded = true;
            return;
        }
        if (!isWideScreen()) setAssistantOpen(false);
    });

    if (assistantToggle) {
        assistantToggle.addEventListener('click', () => setAssistantOpen(!assistantOpen));
    }
    shellScrim.addEventListener('click', () => setAssistantOpen(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !isWideScreen()) setAssistantOpen(false);
    });
    // Crossing the 1200px boundary switches the pane between inline and
    // overlay, which changes whether the scrim should be up.
    window.addEventListener('resize', applyAssistantState);

    // ===== NAVIGATION / OFFLINE =====
    // Every #contentFrame navigation goes through mskNavigate() - the onclick
    // handlers msk_frame_onclick() emits all call it - so the offline check
    // sits in one place rather than once per caller.
    //
    // It navigates the TOP page, not the frame: a dead pane sitting under a
    // live app bar is worse than a clean offline page, and the user cannot
    // tell the difference between "still loading" and "never will".
    //
    // navigator.onLine is false only when the OS reports no connectivity. A
    // reachable LAN with a dead server still reports true, so that case falls
    // through to the frame's own request failing - which msk-sw.js catches and
    // answers with the cached offline page.
    const MSK_OFFLINE_URL = <?php echo json_encode(
                                'msk-offline.php?lang=' . ($GLOBALS['AGN_CFG_LANGUAGE']['code'] ?? 'en'),
                                JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
                            ); ?>;

    function mskNavigate(url) {
        if (!navigator.onLine) {
            window.location.href = MSK_OFFLINE_URL;
            return;
        }
        document.getElementById('contentFrame').src = url;
    }

    // ===== SHELL API FOR THE FRAMES =====
    // Reached as window.top.mskDialog(...) / window.top.mskOffline().
    //
    // window.top, NOT window.parent: app/fields.php hosts nested iframes, so
    // for app/vegetation.php window.parent is fields.php and
    // this shell is one level further up. Callers guard, because app/*.php can
    // also be opened directly, where window.top is the page itself:
    //
    //   if (window.top !== window && typeof window.top.mskDialog === 'function') {
    //       window.top.mskDialog({
    //           title: '...', message: '...', cancel: true, persistent: true
    //       }).onOk(() => { ... });
    //   }
    //
    // Shaped like Quasar's $q.dialog() so the app pages' call sites read the
    // same, but built on the Bootstrap modal already loaded above - the shell
    // has no Quasar, and pulling it in would drop its global reset on top of
    // Bootstrap across every page here.
    window.mskOffline = function () {
        window.location.href = MSK_OFFLINE_URL;
    };

    const MSK_DIALOG_LABELS = {
        ok: <?php echo tj('shell.dialog_ok'); ?>,
        cancel: <?php echo tj('shell.dialog_cancel'); ?>
    };

    window.mskDialog = function (opts) {
        opts = opts || {};
        const handlers = { ok: [], cancel: [], dismiss: [] };
        // Anything other than a button press - backdrop, Esc, close() - is a
        // dismiss, so that is the default until a button says otherwise.
        let outcome = 'dismiss';

        const el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        // Static skeleton only. Caller-supplied strings go in via textContent
        // below: the message crosses a frame boundary, and an app page
        // interpolating a database value into it would otherwise turn a data
        // bug into XSS out here in the shell.
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title"></h5></div>' +
            '<div class="modal-body"></div>' +
            '<div class="modal-footer"></div>' +
            '</div></div>';

        const headEl = el.querySelector('.modal-header');
        const bodyEl = el.querySelector('.modal-body');
        const footEl = el.querySelector('.modal-footer');

        if (opts.title) {
            el.querySelector('.modal-title').textContent = opts.title;
        } else {
            headEl.remove();
        }
        // html: true is an explicit opt-in, as it is in Quasar.
        if (opts.html) bodyEl.innerHTML = opts.message || '';
        else bodyEl.textContent = opts.message || '';

        // Cancel first, then OK - Quasar's order.
        [['cancel', opts.cancel, 'btn-secondary'], ['ok', opts.ok, 'btn-primary']]
            .forEach(([kind, spec, cls]) => {
                if (kind === 'cancel' && !spec) return;
                if (kind === 'ok' && spec === false) return;
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn ' + cls;
                b.textContent = typeof spec === 'string' ? spec
                    : (spec && spec.label) || MSK_DIALOG_LABELS[kind];
                b.addEventListener('click', () => {
                    outcome = kind;
                    modal.hide();
                });
                footEl.appendChild(b);
            });

        document.body.appendChild(el);
        const modal = new bootstrap.Modal(el, {
            backdrop: opts.persistent ? 'static' : true,
            keyboard: !opts.persistent
        });

        // Built and torn down per call, like Quasar does, so two frames calling
        // at once cannot fight over one shared element.
        el.addEventListener('hidden.bs.modal', () => {
            // onDismiss fires on every close, including OK and Cancel - the
            // guard is only so a plain dismiss does not fire it twice.
            if (outcome !== 'dismiss') handlers[outcome].forEach((fn) => fn());
            handlers.dismiss.forEach((fn) => fn());
            modal.dispose();
            el.remove();
        });

        modal.show();

        const api = {
            onOk(fn) { handlers.ok.push(fn); return api; },
            onCancel(fn) { handlers.cancel.push(fn); return api; },
            onDismiss(fn) { handlers.dismiss.push(fn); return api; }
        };
        return api;
    };

    // ===== PROPOSAL BRIDGE =====
    // Carries a validated draft from the assistant pane to a form in the content
    // frame. Reached as window.top.mskProposeFill(...) / .mskRegisterProposalTarget(...),
    // the same window.top convention mskDialog uses, and for the same reason:
    // app pages can be nested (crops.php inside fields.php), so window.parent is
    // not reliably this shell.
    //
    // The assistant never touches the target page's DOM. It hands over a payload;
    // the page assigns it to its own form state and the user reviews and saves.
    // That keeps the write on the existing form submit - same endpoint, same JWT,
    // same validation - rather than opening a second write path.
    // A target registers a handler and, optionally, meta describing WHICH record
    // it is currently showing. The crops target needs that: fields.php registers
    // it per field, so "the crops target is registered" does not mean it is the
    // right one to deliver to.
    const mskProposalTargets = {};
    let mskPendingProposal = null;

    window.mskRegisterProposalTarget = function (name, handler, meta) {
        if (typeof handler !== 'function') return;
        mskProposalTargets[name] = { handler: handler, meta: meta || {} };

        // A proposal that arrived while this page was still loading is delivered
        // as soon as it registers - that is the whole point of queueing it.
        if (mskPendingProposal && mskPendingProposal.target === name
            && mskProposalTargetAccepts(name, mskPendingProposal.payload)) {
            const payload = mskPendingProposal.payload;
            mskPendingProposal = null;
            mskDeliverProposal(name, payload);
            return;
        }

        // fields.php registering is the middle hop of a crops delivery: the
        // shell navigated here so that this router could open the right field,
        // after which it re-registers 'crops' for that field and the branch
        // above runs.
        if (name === 'fields' && mskPendingProposal && mskPendingProposal.target === 'crops') {
            mskRouteToField(mskPendingProposal.payload);
        }
    };

    window.mskUnregisterProposalTarget = function (name) {
        delete mskProposalTargets[name];
    };

    function mskDeliverProposal(name, payload) {
        try {
            mskProposalTargets[name].handler(payload);
        } catch (e) {
            console.error('mskProposeFill delivery failed:', e);
        }
    }

    // A registered target accepts a payload when it is showing the record the
    // payload is about. Only field-scoped targets constrain anything; everything
    // else accepts whatever it is handed, exactly as before.
    function mskProposalTargetAccepts(name, payload) {
        const entry = mskProposalTargets[name];
        if (!entry) return false;

        const wanted = payload && payload.field_id;
        if (!wanted) return true;

        const showing = entry.meta && entry.meta.aoi;
        if (!showing) return false;

        return String(showing) === String(wanted);
    }

    // Asks fields.php to open a given field on a given tab. Returns whether the
    // router was there to ask.
    function mskRouteToField(payload) {
        const entry = mskProposalTargets['fields'];
        if (!entry) return false;

        try {
            entry.handler({ field_id: payload.field_id, tab: 'crops' });
        } catch (e) {
            console.error('mskProposeFill field routing failed:', e);
            return false;
        }
        return true;
    }

    // Returns 'delivered' | 'navigating' | 'offline'. The caller shows its own
    // feedback; the shell deliberately does not toast on the assistant's behalf.
    window.mskProposeFill = function (target, payload) {
        const page = String(target || '') + '.php';

        // A scouting entry names its photograph by NUMBER: the bytes live only
        // in the assistant frame and never reach the server, so a number is the
        // only handle a model can hold. The pane swaps them for the real images
        // before calling this; a call arriving from the WebMCP registrar cannot,
        // so the swap happens here too - the last point on that path still able
        // to reach the frame holding the pictures.
        //
        // Guarded on the function existing: a draft can be delivered with no
        // assistant frame open at all, and a missing resolver just means the
        // numbers travel on unchanged.
        if (payload && payload.scouting_images) {
            try {
                const frame = document.getElementById('assistantFrame');
                const resolve = frame && frame.contentWindow && frame.contentWindow.mskResolveScoutingImages;
                if (typeof resolve === 'function') payload = resolve(payload);
            } catch (e) {
                console.error('scouting image resolution failed:', e);
            }
        }

        if (mskProposalTargetAccepts(target, payload)) {
            mskDeliverProposal(target, payload);
            if (!isWideScreen()) setAssistantOpen(false);
            return 'delivered';
        }

        if (!navigator.onLine) {
            window.mskOffline();
            return 'offline';
        }

        mskPendingProposal = { target: target, payload: payload };

        // A crops proposal has no page of its own: the crop form lives on
        // fields.php, so the route is always through the fields router - which is
        // either already here, or arrives with the page we are about to load.
        //
        // A crop draft whose field did not resolve is unroutable, and must be
        // reported as such: falling through to the generic branch navigates to
        // fields.php with no krd, rendering "KRD parameter required".
        // The WebMCP registrar tests for exactly this string.
        if (target === 'crops') {
            if (!payload || !payload.field_id) {
                mskPendingProposal = null;
                return 'unroutable';
            }
            if (!mskRouteToField(payload)) {
                mskNavigate(<?php echo json_encode(
                                ($GLOBALS['AGN_CFG_LANGUAGE']['app_dir'] ?? '../app') . '/',
                                JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
                            ); ?> + 'fields.php');
            }
            if (!isWideScreen()) setAssistantOpen(false);
            return 'navigating';
        }

        // Target page is not loaded. Queue, then route through mskNavigate() so
        // the offline check stays in one place, and let the register call above
        // flush the queue once the page comes up.
        mskNavigate(<?php echo json_encode(
                        ($GLOBALS['AGN_CFG_LANGUAGE']['app_dir'] ?? '../app') . '/',
                        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
                    ); ?> + page);
        if (!isWideScreen()) setAssistantOpen(false);
        return 'navigating';
    };

    let installPrompt = null;
    const installItem = document.getElementById('installItem');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        installPrompt = e;
        installItem.removeAttribute('hidden');
    });

    function mskInstall() {
        if (!installPrompt) return;
        installPrompt.prompt();
        disableInstallPrompt();
    }

    function disableInstallPrompt() {
        installPrompt = null;
        installItem.setAttribute('hidden', '');
    }

    window.addEventListener('appinstalled', disableInstallPrompt);

    // ===== SERVICE WORKER =====
    // Offline fallback only - see msk-sw.js. Registered after load so it never
    // competes with the shell's own requests. Silently ignored where service
    // workers are unavailable, which includes any non-HTTPS origin other than
    // localhost; the app simply stays online-only there.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('msk-sw.js').catch(() => {});
        });
    }

    // Adjusts which menu items are visible based on login status (no
    // login / normal user / superuser) and whether a database has been
    // selected yet. Re-run after logout, after a database is picked
    // (window.parent.refreshMenuAuthState from databases.php/subscribe.php)
    // and (via window.parent.refreshMenuAuthState from app-pt/login.php)
    // after a fresh login, since none of those reload the shell itself.
    window.refreshMenuAuthState = function() {
        const token = localStorage.getItem('auth_token');
        const uid = AuthGuard.getUserId(token);
        const isLoggedIn = uid !== null;
        const isSuperuser = uid === 1;
        const hasDatabase = !!window.krd;
        // The avatar (Perfil/Alterar Palavra-passe/Sair) always reflects real
        // login status. The app-navigation links additionally need a
        // selected database, since e.g. Talhões has nothing to show otherwise.
        document.querySelectorAll('[data-requires="login"]').forEach((el) => {
            el.style.display = (isLoggedIn && hasDatabase) ? '' : 'none';
        });
        document.querySelectorAll('[data-requires="superuser"]').forEach((el) => {
            el.style.display = (isSuperuser && hasDatabase) ? '' : 'none';
        });
        // A third bucket, for controls that need a database but no particular
        // role - the assistant toggle. Not foldable into the two above: both
        // of those additionally require a login.
        document.querySelectorAll('[data-requires="database"]').forEach((el) => {
            el.style.display = hasDatabase ? '' : 'none';
        });
        document.getElementById('userMenuLoggedIn').style.display = isLoggedIn ? '' : 'none';
        document.getElementById('userMenuAnon').style.display = isLoggedIn ? 'none' : '';
        const dbLabel = document.getElementById('selectedDbLabel');
        if (dbLabel) dbLabel.textContent = window.dbName ? ' [' + window.dbName + ']' : '';

        // Availability only - the pane's own open/closed state, its width and
        // its src all belong to applyAssistantState() above. The content pane
        // needs no width adjustment either: it is flex: 1, so it reclaims the
        // space the moment the assistant collapses.
        applyAssistantState();
    };
    refreshMenuAuthState();
</script>

<!-- WebMCP: the farm's tools, published to the browser's own agent.

     The registrar alone: the only reader here is the browser's own agent, and
     an agent able to call these tools is what provides the surface. The pane in
     this shell runs its loop server-side and never reads window.wmxWebMCP.
     wmx-tools/README-development.md covers the shim and when to load it.

     At the top level so the tools register in the document that also holds
     mskProposeFill, which is what lets propose_crop reach the real form. The
     'unroutable' return above and the scouting-image resolution are the two
     contracts the registrar depends on.

     Unreachable surface: the tag 404s, nothing registers, the pane is
     unaffected.

     The tag now lives in msk-ai-gate-agt.php, which injects it only if the
     account's consent claims allow it. Restore the <script> tag here and delete
     that file to back the switches out. -->
<?php include "msk-ai-gate-agt.php"; ?>
