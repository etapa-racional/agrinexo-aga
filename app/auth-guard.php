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
                    notifyFn('Acesso restrito ao administrador do sistema.', 'negative', 'lock');
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
