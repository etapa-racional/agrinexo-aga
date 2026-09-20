<?php

// The AGT shell's tool-surface tag, behind consent.
?>
<script>
    (function () {
        'use strict';

        // Signed out, the shell renders its defaults. With a token, the claims
        // decide, and a token that does not decode renders nothing - the same
        // answer the server gives it.
        function consent() {
            var raw = null;
            try {
                raw = localStorage.getItem('auth_token');
            } catch (e) {
                /* Storage unavailable. */
            }
            if (!raw) {
                return { ai: true, webmcp: true };
            }

            var claims = null;
            try {
                claims = AuthGuard.decodeJwtPayload(raw);
            } catch (e) {
                /* Not parseable. */
            }
            return {
                ai: !!claims && claims.ai === true,
                webmcp: !!claims && claims.webmcp === true
            };
        }

        var booted = consent();
        window.mskAiConsent = booted;

        // The injection wmx/aibridge.js already uses for webmcp.js.
        function inject(src, attr, value) {
            var tag = document.createElement('script');
            tag.src = src;
            tag.setAttribute(attr, value);
            tag.async = false;
            document.head.appendChild(tag);
        }

        if (booted.webmcp) {
            inject('../wmx/webmcp.js', 'data-wmx-base',
                <?php echo json_encode($mskToolSurface, JSON_HEX_TAG); ?>);

            // askAgrinexoAGA: the browser agent's route to this shell's
            // assistant, and to the tools wmx does not publish. Needs the chat
            // as well as the surface, so both switches.
            if (booted.ai) {
                inject('../agt/webmcp-ask.js', 'data-agt-base',
                    <?php echo json_encode($GLOBALS['AGN_CFG_LANGUAGE']['agt_dir'] ?? '../agt', JSON_HEX_TAG); ?>);
            }
        }

        // Wrapped rather than edited, so the wrapper leaves with this file.
        var inner = window.refreshMenuAuthState;
        window.refreshMenuAuthState = function () {
            if (typeof inner === 'function') {
                inner.apply(this, arguments);
            }

            var now = consent();
            window.mskAiConsent = now;

            // The registrar cannot be unregistered live, so a token that
            // disagrees about WebMCP needs a reload. After it booted equals now.
            if (now.webmcp !== booted.webmcp) {
                location.reload();
                return;
            }

            // This shell's pane runs its loop server-side, where agt/index.php
            // refuses the turn outright; hiding the toggle keeps the user from
            // opening a pane that can only answer 403.
            if (!now.ai) {
                var toggle = document.getElementById('assistantToggle');
                if (toggle) {
                    toggle.style.display = 'none';
                }
                if (typeof setAssistantOpen === 'function') {
                    setAssistantOpen(false);
                }
            }
        };

        // The footer already ran refreshMenuAuthState() before this file loaded.
        window.refreshMenuAuthState();
    })();
</script>
