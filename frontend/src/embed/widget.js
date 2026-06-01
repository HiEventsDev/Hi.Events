/* eslint-disable lingui/no-unlocalized-strings */
(function(scriptElement) {
    const isScriptLoaded = () => !!window.hiEventWidgetLoaded;

    const logError = (e) => {
        try { console.error('HiEvent widget error:', e); } catch (ignored) { /* noop */ }
    };

    const safe = (fn) => function () {
        try {
            return fn.apply(this, arguments);
        } catch (e) {
            logError(e);
        }
    };

    const loadWidget = safe(() => {
        window.hiEventWidgetLoaded = true;

        let scriptOrigin;
        try {
            scriptOrigin = new URL(scriptElement.src).origin;
        } catch (e) {
            console.error('HiEvent widget error: Invalid script URL');
            return;
        }

        const SANDBOX = [
            'allow-forms', 'allow-scripts', 'allow-same-origin', 'allow-popups',
            'allow-popups-to-escape-sandbox', 'allow-top-navigation',
            'allow-top-navigation-by-user-activation', 'allow-downloads', 'allow-modals',
            'allow-orientation-lock', 'allow-pointer-lock', 'allow-presentation'
        ].join(' ');
        const ALLOW = `payment ${scriptOrigin}; publickey-credentials-get ${scriptOrigin}`;
        const MOBILE_BREAKPOINT = 640;
        const MODAL_WIDTH = 760;

        const topParams = new URLSearchParams(window.location.search);
        const returnEventId = topParams.get('hievents_event');
        const returnOrderId = topParams.get('hievents_order');
        const returnSession = topParams.get('hievents_session');
        const isPaymentReturn = !!(returnEventId && returnOrderId);

        const stripParams = (keys) => {
            try {
                const url = new URL(window.location.href);
                keys.forEach((key) => url.searchParams.delete(key));
                return url.toString();
            } catch (e) {
                return window.location.href;
            }
        };

        const OWNED_PARAMS = ['hievents_event', 'hievents_order', 'hievents_session'];
        const STRIPE_RETURN_PARAMS = ['payment_intent', 'payment_intent_client_secret', 'redirect_status'];
        const parentBaseUrl = stripParams(OWNED_PARAMS.concat(STRIPE_RETURN_PARAMS));

        const resizableIframes = new Set();
        let modal = null;

        const RESUME_KEY = 'hievents.checkout.resume';
        const topStorage = () => {
            try { return window.sessionStorage; } catch (e) { return null; }
        };
        const saveResume = (data) => {
            const store = topStorage();
            if (store) {
                try { store.setItem(RESUME_KEY, JSON.stringify(data)); } catch (e) { /* noop */ }
            }
        };
        const clearResume = () => {
            const store = topStorage();
            if (store) {
                try { store.removeItem(RESUME_KEY); } catch (e) { /* noop */ }
            }
        };
        const readResume = () => {
            const store = topStorage();
            if (!store) return null;
            try { return JSON.parse(store.getItem(RESUME_KEY) || 'null'); } catch (e) { return null; }
        };

        const setFullScreenFixed = (el) => {
            el.style.position = 'fixed';
            el.style.top = '0';
            el.style.left = '0';
            el.style.right = '0';
            el.style.bottom = '0';
        };

        const openCheckoutModal = (path) => {
            if (modal || typeof path !== 'string' || !path.startsWith('/checkout/')) {
                return;
            }

            const separator = path.indexOf('?') > -1 ? '&' : '?';
            const src = scriptOrigin + path + separator
                + 'embed=modal&parentUrl=' + encodeURIComponent(parentBaseUrl);

            const abort = new AbortController();
            const prevFocus = document.activeElement;
            const scrollY = window.scrollY || window.pageYOffset || 0;
            const originalBody = {
                position: document.body.style.position,
                top: document.body.style.top,
                width: document.body.style.width,
                overflow: document.body.style.overflow,
            };

            const inerted = [];

            const host = document.createElement('div');
            host.id = 'hievents-checkout-modal';
            setFullScreenFixed(host);
            host.style.zIndex = '2147483647';
            const shadow = host.attachShadow({ mode: 'open' });

            const backdrop = document.createElement('div');
            const bs = backdrop.style;
            setFullScreenFixed(backdrop);
            bs.background = 'rgba(0, 0, 0, 0.6)';
            bs.display = 'flex';
            bs.flexDirection = 'column';
            bs.boxSizing = 'border-box';

            const dialog = document.createElement('div');
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-label', 'Checkout');
            const ds = dialog.style;
            ds.position = 'relative';
            ds.background = '#ffffff';
            ds.overflow = 'hidden';
            ds.boxSizing = 'border-box';
            ds.display = 'flex';
            ds.flexDirection = 'column';
            ds.flex = '0 0 auto';
            ds.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.3)';

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.setAttribute('aria-label', 'Close checkout');
            closeBtn.textContent = '×';
            const cs = closeBtn.style;
            cs.position = 'absolute';
            cs.top = '10px';
            cs.right = '10px';
            cs.zIndex = '2';
            cs.width = '34px';
            cs.height = '34px';
            cs.padding = '0';
            cs.border = 'none';
            cs.borderRadius = '50%';
            cs.cursor = 'pointer';
            cs.background = 'rgba(0, 0, 0, 0.06)';
            cs.color = '#111111';
            cs.fontSize = '22px';
            cs.lineHeight = '34px';
            cs.textAlign = 'center';

            const spinner = document.createElement('div');
            const sp = spinner.style;
            setFullScreenFixed(spinner);
            sp.position = 'absolute';
            sp.display = 'flex';
            sp.alignItems = 'center';
            sp.justifyContent = 'center';
            sp.color = '#888888';
            sp.fontFamily = 'sans-serif';
            sp.fontSize = '14px';
            spinner.textContent = 'Loading…';

            const iframe = document.createElement('iframe');
            iframe.setAttribute('sandbox', SANDBOX);
            iframe.setAttribute('allow', ALLOW);
            iframe.setAttribute('title', 'Hi.Events Checkout');
            const ifs = iframe.style;
            ifs.border = 'none';
            ifs.width = '100%';
            ifs.flex = '1 1 auto';
            ifs.background = '#ffffff';
            iframe.src = src;

            const applySizing = () => {
                const vw = window.innerWidth;
                const vh = (window.visualViewport && window.visualViewport.height) || window.innerHeight;

                if (vw <= MOBILE_BREAKPOINT) {
                    bs.alignItems = 'stretch';
                    bs.justifyContent = 'flex-start';
                    bs.padding = '0';
                    ds.width = '100%';
                    ds.maxWidth = 'none';
                    ds.borderRadius = '0';
                    ds.height = `${vh}px`;
                } else {
                    bs.alignItems = 'center';
                    bs.justifyContent = 'center';
                    bs.padding = '16px';
                    ds.width = `${MODAL_WIDTH}px`;
                    ds.maxWidth = '96vw';
                    ds.borderRadius = '12px';
                    ds.height = `${Math.round(vh * 0.92)}px`;
                }
            };

            const requestClose = () => {
                if (!modal) return;
                try {
                    iframe.contentWindow.postMessage({ type: 'hievents:request-close' }, scriptOrigin);
                } catch (e) {
                    closeCheckoutModal();
                    return;
                }
                if (modal.ackTimer) clearTimeout(modal.ackTimer);
                modal.ackTimer = setTimeout(safe(closeCheckoutModal), 4000);
            };

            backdrop.addEventListener('click', safe((e) => {
                if (e.target === backdrop) requestClose();
            }), { signal: abort.signal });
            closeBtn.addEventListener('click', safe(requestClose), { signal: abort.signal });
            document.addEventListener('keydown', safe((e) => {
                if (e.key === 'Escape') requestClose();
            }), { signal: abort.signal });
            window.addEventListener('resize', safe(applySizing), { signal: abort.signal });
            window.addEventListener('orientationchange', safe(applySizing), { signal: abort.signal });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', safe(applySizing), { signal: abort.signal });
            }
            iframe.addEventListener('load', safe(() => {
                spinner.style.display = 'none';
            }), { signal: abort.signal });

            dialog.appendChild(closeBtn);
            dialog.appendChild(spinner);
            dialog.appendChild(iframe);
            backdrop.appendChild(dialog);
            shadow.appendChild(backdrop);

            try {
                Array.from(document.body.children).forEach((el) => {
                    if (el.nodeType === 1 && !el.hasAttribute('inert')) {
                        el.setAttribute('inert', '');
                        inerted.push(el);
                    }
                });
                document.body.style.top = `-${scrollY}px`;
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
                document.body.style.overflow = 'hidden';
                document.body.appendChild(host);
            } catch (e) {
                console.error('HiEvent widget error: failed to open checkout modal');
                inerted.forEach((el) => {
                    try { el.removeAttribute('inert'); } catch (err) { /* noop */ }
                });
                document.body.style.position = originalBody.position;
                document.body.style.top = originalBody.top;
                document.body.style.width = originalBody.width;
                document.body.style.overflow = originalBody.overflow;
                try { abort.abort(); } catch (err) { /* noop */ }
                try { host.remove(); } catch (err) { /* noop */ }
                return;
            }

            applySizing();
            try {
                closeBtn.focus();
            } catch (e) {
                /* noop */
            }

            modal = { host, abort, prevFocus, scrollY, originalBody, inerted, ackTimer: null, requestClose };
        };

        const closeCheckoutModal = () => {
            if (!modal) return;
            const current = modal;
            modal = null;

            try { current.abort.abort(); } catch (e) { /* noop */ }
            if (current.ackTimer) clearTimeout(current.ackTimer);
            try { current.host.remove(); } catch (e) { /* noop */ }
            current.inerted.forEach((el) => {
                try { el.removeAttribute('inert'); } catch (e) { /* noop */ }
            });

            document.body.style.position = current.originalBody.position;
            document.body.style.top = current.originalBody.top;
            document.body.style.width = current.originalBody.width;
            document.body.style.overflow = current.originalBody.overflow;
            window.scrollTo(0, current.scrollY);

            try {
                if (current.prevFocus && current.prevFocus.focus) current.prevFocus.focus();
            } catch (e) {
                /* noop */
            }

            clearResume();
        };

        window.addEventListener('message', safe((event) => {
            if (event.origin !== scriptOrigin) {
                return;
            }
            const data = event.data || {};
            switch (data.type) {
                case 'resize': {
                    const height = Number(data.height);
                    if (Number.isFinite(height) && data.iframeId && resizableIframes.has(data.iframeId)) {
                        const clamped = Math.max(0, Math.min(height, 20000));
                        const targetIframe = document.getElementById(data.iframeId);
                        if (targetIframe) targetIframe.style.height = `${clamped}px`;
                    }
                    break;
                }
                case 'hievents:open-checkout':
                    openCheckoutModal(data.path);
                    break;
                case 'hievents:close-checkout':
                    closeCheckoutModal();
                    break;
                case 'hievents:close-pending':
                    if (modal && modal.ackTimer) {
                        clearTimeout(modal.ackTimer);
                        modal.ackTimer = null;
                    }
                    break;
                case 'hievents:checkout-progress':
                    if (data.eventId && data.orderShortId) {
                        saveResume({
                            eventId: data.eventId,
                            orderShortId: data.orderShortId,
                            sessionId: data.sessionId || null,
                            step: data.step || 'details',
                            reservedUntil: data.reservedUntil || null,
                        });
                    }
                    break;
                case 'hievents:checkout-cleared':
                    clearResume();
                    break;
            }
        }));

        const widgetInstanceId = Math.random().toString(36).slice(2);
        const widgets = document.querySelectorAll('.hievents-widget');
        widgets.forEach((widget, index) => {
            const eventId = widget.getAttribute('data-hievents-id');
            if (!eventId) {
                console.error('HiEvent widget error: data-hievents-id is required');
                return;
            }

            const iframe = document.createElement('iframe');
            iframe.setAttribute('sandbox', SANDBOX);
            iframe.setAttribute('allow', ALLOW);
            iframe.setAttribute('title', 'Hi.Events Widget');
            iframe.style.border = 'none';
            iframe.style.width = '100%';

            const iframeId = `hievents-iframe-${widgetInstanceId}-${index}`;
            iframe.id = iframeId;

            const parentUrlParam = `parentUrl=${encodeURIComponent(parentBaseUrl)}`;
            const src = `${scriptOrigin}/widget/${encodeURIComponent(eventId)}?iframeId=${iframeId}&${parentUrlParam}&`;

            const params = [];
            Array.from(widget.attributes).forEach((attr) => {
                if (attr.name.startsWith('data-hievents-') && attr.name !== 'data-hievents-id') {
                    const paramName = attr.name.substring(13).replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                    params.push(`${paramName}=${encodeURIComponent(attr.value)}`);
                }
            });

            iframe.src = src + params.join('&');
            widget.appendChild(iframe);

            if (widget.getAttribute('data-hievents-autoresize') !== 'false') {
                resizableIframes.add(iframeId);
            }
        });

        if (isPaymentReturn) {
            const resumed = readResume();
            const recoveredSession = (resumed && String(resumed.orderShortId) === String(returnOrderId))
                ? resumed.sessionId
                : null;
            const sessionForReturn = returnSession || recoveredSession;

            const returnParams = new URLSearchParams();
            if (sessionForReturn) {
                returnParams.set('session_identifier', sessionForReturn);
            }
            STRIPE_RETURN_PARAMS.forEach((key) => {
                const value = topParams.get(key);
                if (value) {
                    returnParams.set(key, value);
                }
            });

            const query = returnParams.toString();
            const path = `/checkout/${encodeURIComponent(returnEventId)}/${encodeURIComponent(returnOrderId)}/payment_return`
                + (query ? `?${query}` : '');
            openCheckoutModal(path);

            try {
                window.history.replaceState({}, document.title, parentBaseUrl);
            } catch (e) {
                console.error('HiEvent widget error: Unable to clean return URL');
            }
        } else {
            const resume = readResume();
            const stillReserved = resume && typeof resume.reservedUntil === 'number'
                && resume.reservedUntil > Date.now();

            if (stillReserved) {
                let path = `/checkout/${encodeURIComponent(resume.eventId)}/${encodeURIComponent(resume.orderShortId)}/${encodeURIComponent(resume.step || 'details')}`;
                if (resume.sessionId) {
                    path += `?session_identifier=${encodeURIComponent(resume.sessionId)}`;
                }
                openCheckoutModal(path);
            } else if (resume) {
                clearResume();
            }
        }
    });

    try {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadWidget);
        } else if (!isScriptLoaded()) {
            loadWidget();
        }
    } catch (e) {
        logError(e);
    }
})(document.currentScript);
