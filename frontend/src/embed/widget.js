/* eslint-disable lingui/no-unlocalized-strings */
(function (scriptElement) {
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

    if (window.HiEventsWidget) {
        const existing = window.HiEventsWidget;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', safe(() => existing.scan()));
        } else {
            safe(() => existing.scan())();
        }
        return;
    }

    let scriptOrigin;
    try {
        scriptOrigin = new URL(scriptElement.src).origin;
    } catch (e) {
        console.error('HiEvent widget error: Invalid script URL');
        return;
    }

    const PREFIX = 'data-hievents-';
    const MOUNTED_FLAG = 'data-hievents-mounted';
    const SANDBOX = [
        'allow-forms', 'allow-scripts', 'allow-same-origin', 'allow-popups',
        'allow-popups-to-escape-sandbox', 'allow-top-navigation',
        'allow-top-navigation-by-user-activation', 'allow-downloads', 'allow-modals',
        'allow-orientation-lock', 'allow-pointer-lock', 'allow-presentation'
    ].join(' ');
    const ALLOW = `payment ${scriptOrigin}; publickey-credentials-get ${scriptOrigin}`;
    const MOBILE_BREAKPOINT = 640;
    const CHECKOUT_MODAL_WIDTH = 760;
    const WIDGET_MODAL_WIDTH = 560;
    const OWNED_PARAMS = ['hievents_event', 'hievents_order', 'hievents_session'];
    const STRIPE_RETURN_PARAMS = ['payment_intent', 'payment_intent_client_secret', 'redirect_status'];
    const FORWARDED_HOST_PARAMS = ['aff', 'promo_code', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    const RESUME_KEY = 'hievents.checkout.resume';
    const WIDGET_PATHS = {
        lineup: '/lineup',
        schedule: '/schedule',
    };
    const WIDGET_TITLES = {
        lineup: 'Hi.Events Lineup',
        schedule: 'Hi.Events Schedule',
        events: 'Hi.Events Events',
    };

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

    const parentBaseUrl = () => stripParams(OWNED_PARAMS.concat(STRIPE_RETURN_PARAMS));

    const hostParams = FORWARDED_HOST_PARAMS
        .map((key) => [key, topParams.get(key)])
        .filter(([, value]) => !!value);

    const instances = new Map();
    let instanceCounter = 0;
    const instanceNamespace = Math.random().toString(36).slice(2);
    let modal = null;
    let globalListenersBound = false;

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

    const preconnect = () => {
        try {
            if (document.querySelector(`link[rel="preconnect"][href="${scriptOrigin}"]`)) return;
            const link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = scriptOrigin;
            document.head.appendChild(link);
        } catch (e) { /* noop */ }
    };

    const emit = (name, detail, element) => {
        const init = { detail: detail || {}, bubbles: true, composed: true };
        try {
            if (element && element.isConnected) {
                element.dispatchEvent(new CustomEvent(name, init));
            } else {
                window.dispatchEvent(new CustomEvent(name, init));
            }
        } catch (e) { /* noop */ }
    };

    const setFullScreenFixed = (el) => {
        el.style.position = 'fixed';
        el.style.top = '0';
        el.style.left = '0';
        el.style.right = '0';
        el.style.bottom = '0';
    };

    const viewportSize = () => {
        const vv = window.visualViewport;
        const hasViewport = !!(vv && vv.width && vv.height);
        return {
            width: hasViewport ? vv.width * vv.scale : window.innerWidth,
            height: hasViewport ? vv.height * vv.scale : window.innerHeight,
        };
    };

    const checkoutSrc = (path) => {
        const separator = path.indexOf('?') > -1 ? '&' : '?';
        return scriptOrigin + path + separator + 'embed=modal&parentUrl=' + encodeURIComponent(parentBaseUrl());
    };

    const openModal = (options) => {
        if (modal) return;

        const abort = new AbortController();
        const activeElement = document.activeElement;
        const prevFocus = (activeElement && activeElement.shadowRoot && activeElement.shadowRoot.activeElement) || activeElement;
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
        dialog.setAttribute('aria-label', options.label);
        const ds = dialog.style;
        ds.position = 'relative';
        ds.background = options.background || '#ffffff';
        ds.overflow = 'hidden';
        ds.boxSizing = 'border-box';
        ds.display = 'flex';
        ds.flexDirection = 'column';
        ds.flex = '0 0 auto';
        ds.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.3)';
        ds.transition = 'height 0.2s ease';

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', options.kind === 'checkout' ? 'Close checkout' : 'Close');
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
        cs.background = 'rgba(255, 255, 255, 0.92)';
        cs.boxShadow = '0 1px 4px rgba(0, 0, 0, 0.25)';
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
        iframe.setAttribute('title', options.title);
        if (options.iframeId) iframe.id = options.iframeId;
        const ifs = iframe.style;
        ifs.border = 'none';
        ifs.width = '100%';
        ifs.flex = '1 1 auto';
        ifs.background = 'transparent';
        iframe.src = options.src;

        const applySizing = () => {
            if (!modal) return;
            const { width: vw, height: vh } = viewportSize();
            const isWidget = modal.kind === 'widget';

            if (vw <= MOBILE_BREAKPOINT) {
                bs.alignItems = 'stretch';
                bs.justifyContent = isWidget ? 'flex-end' : 'flex-start';
                bs.padding = '0';
                ds.width = '100%';
                ds.maxWidth = 'none';
                ds.borderRadius = isWidget ? '16px 16px 0 0' : '0';
                ds.height = isWidget
                    ? `${Math.min(modal.contentHeight || Math.round(vh * 0.6), Math.round(vh * 0.92))}px`
                    : `${vh}px`;
            } else {
                const maxHeight = Math.round(vh * 0.92);
                bs.alignItems = 'center';
                bs.justifyContent = 'center';
                bs.padding = '16px';
                ds.width = `${isWidget ? WIDGET_MODAL_WIDTH : CHECKOUT_MODAL_WIDTH}px`;
                ds.maxWidth = '96vw';
                ds.borderRadius = '12px';
                ds.height = isWidget
                    ? `${Math.min(modal.contentHeight || 420, maxHeight)}px`
                    : `${maxHeight}px`;
            }
        };

        const requestClose = () => {
            if (!modal) return;
            if (modal.kind === 'widget') {
                closeModal();
                return;
            }
            try {
                iframe.contentWindow.postMessage({ type: 'hievents:request-close' }, scriptOrigin);
            } catch (e) {
                closeModal();
                return;
            }
            if (modal.ackTimer) clearTimeout(modal.ackTimer);
            modal.ackTimer = setTimeout(safe(closeModal), 4000);
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
            console.error('HiEvent widget error: failed to open modal');
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

        modal = {
            kind: options.kind,
            host,
            dialog,
            iframe,
            spinner,
            closeBtn,
            abort,
            prevFocus,
            scrollY,
            originalBody,
            inerted,
            ackTimer: null,
            contentHeight: null,
            origin: options.origin || null,
            applySizing,
        };

        applySizing();
        try { closeBtn.focus(); } catch (e) { /* noop */ }
    };

    const openCheckoutModal = (path, originElement) => {
        if (typeof path !== 'string' || !path.startsWith('/checkout/')) {
            return;
        }

        if (modal && modal.kind === 'widget') {
            modal.kind = 'checkout';
            modal.contentHeight = null;
            modal.dialog.setAttribute('aria-label', 'Checkout');
            modal.closeBtn.setAttribute('aria-label', 'Close checkout');
            modal.dialog.style.background = '#ffffff';
            modal.iframe.setAttribute('title', 'Hi.Events Checkout');
            modal.iframe.removeAttribute('id');
            modal.spinner.style.display = 'flex';
            try {
                modal.iframe.contentWindow.location.replace(checkoutSrc(path));
            } catch (e) {
                modal.iframe.src = checkoutSrc(path);
            }
            modal.applySizing();
            emit('hievents:checkout-opened', {}, modal.origin);
            return;
        }

        if (modal) return;

        openModal({
            kind: 'checkout',
            label: 'Checkout',
            title: 'Hi.Events Checkout',
            src: checkoutSrc(path),
            origin: originElement,
        });
        emit('hievents:checkout-opened', {}, originElement);
    };

    const closeModal = () => {
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
        } catch (e) { /* noop */ }

        if (current.kind === 'checkout') {
            clearResume();
            emit('hievents:checkout-closed', {}, current.origin);
        }
    };

    const EVENT_LIST_ONLY_ATTRIBUTES = ['organizer-id', 'widget-type', 'layout', 'limit', 'event-category', 'show-past', 'open-in'];

    const openEventWidgetModal = (source, eventId) => {
        if (modal) return;
        const attributes = Object.assign({}, source.attributes, { id: String(eventId) });
        EVENT_LIST_ONLY_ATTRIBUTES.forEach((name) => { delete attributes[name]; });
        const iframeId = `hievents-modal-${instanceNamespace}-${source.index}-${encodeURIComponent(eventId)}`;
        openModal({
            kind: 'widget',
            label: 'Tickets',
            title: 'Hi.Events Widget',
            iframeId,
            background: attributes['background-color'] || '#ffffff',
            src: buildWidgetSrc(attributes, iframeId, ['Mode=modal']),
            origin: source.element,
        });
    };

    const instanceForSource = (source) => {
        if (modal && modal.iframe.contentWindow === source) {
            return { element: modal.origin };
        }
        for (const instance of instances.values()) {
            if (instance.iframe && instance.iframe.contentWindow === source) return instance;
        }
        return null;
    };

    const handleMessage = (event) => {
        if (event.origin !== scriptOrigin) {
            return;
        }
        const data = event.data || {};
        const source = instanceForSource(event.source);
        const originElement = source ? source.element : null;

        switch (data.type) {
            case 'resize': {
                const height = Number(data.height);
                if (!Number.isFinite(height) || !data.iframeId) break;
                const clamped = Math.max(0, Math.min(height, 20000));
                if (modal && modal.kind === 'widget' && modal.iframe.id === data.iframeId) {
                    modal.contentHeight = clamped;
                    modal.applySizing();
                    break;
                }
                const instance = instances.get(data.iframeId);
                if (instance && instance.autoResize && instance.iframe) {
                    instance.iframe.style.height = `${clamped}px`;
                }
                break;
            }
            case 'hievents:open-checkout':
                openCheckoutModal(data.path, originElement);
                break;
            case 'hievents:close-checkout':
                closeModal();
                break;
            case 'hievents:close-pending':
                if (modal && modal.ackTimer) {
                    clearTimeout(modal.ackTimer);
                    modal.ackTimer = null;
                }
                break;
            case 'hievents:checkout-progress':
                if (data.eventId && data.orderShortId) {
                    const step = data.step || 'details';
                    saveResume({
                        eventId: data.eventId,
                        orderShortId: data.orderShortId,
                        sessionId: data.sessionId || null,
                        step,
                        reservedUntil: data.reservedUntil || null,
                        locale: data.locale || null,
                    });
                    if (!modal || modal.lastStep !== step) {
                        if (modal) modal.lastStep = step;
                        emit('hievents:checkout-step', { eventId: data.eventId, step }, originElement);
                    }
                }
                break;
            case 'hievents:checkout-cleared':
                clearResume();
                break;
            case 'hievents:open-widget':
                if (source && source.attributes && data.eventId) {
                    openEventWidgetModal(source, data.eventId);
                }
                break;
            case 'hievents:order-completed':
                emit('hievents:order-completed', {
                    eventId: data.eventId,
                    currency: data.currency,
                    total: data.total,
                    quantity: data.quantity,
                    status: data.status,
                }, originElement);
                break;
        }
    };

    const attributesOf = (element) => {
        const attributes = {};
        Array.from(element.attributes).forEach((attr) => {
            if (attr.name === MOUNTED_FLAG) {
                return;
            }
            if (attr.name.startsWith(PREFIX)) {
                attributes[attr.name.substring(PREFIX.length)] = attr.value;
            } else if (element.tagName === 'HIEVENTS-WIDGET' && attr.name !== 'class' && attr.name !== 'style' && attr.name !== 'id') {
                attributes[attr.name === 'event-id' ? 'id' : attr.name] = attr.value;
            }
        });
        return attributes;
    };

    const toParamName = (attributeName) => attributeName
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');

    const buildWidgetSrc = (attributes, iframeId, extraParams) => {
        const type = attributes['widget-type'] || 'widget';
        const organizerId = attributes['organizer-id'];
        const eventId = attributes.id;
        let path;

        if (type === 'events' || (organizerId && !eventId)) {
            path = `/widget/organizer/${encodeURIComponent(organizerId)}`;
        } else {
            path = `/widget/${encodeURIComponent(eventId)}${WIDGET_PATHS[type] || ''}`;
        }

        const params = [
            `iframeId=${iframeId}`,
            `parentUrl=${encodeURIComponent(parentBaseUrl())}`,
        ];
        Object.keys(attributes).forEach((name) => {
            if (name === 'id' || name === 'organizer-id') return;
            params.push(`${toParamName(name)}=${encodeURIComponent(attributes[name])}`);
        });
        hostParams.forEach(([key, value]) => {
            params.push(`${key}=${encodeURIComponent(value)}`);
        });
        (extraParams || []).forEach((param) => params.push(param));

        return `${scriptOrigin}${path}?${params.join('&')}`;
    };

    const isValidTarget = (attributes) => {
        const type = attributes['widget-type'] || 'widget';
        if (type === 'events') return !!attributes['organizer-id'];
        return !!attributes.id || !!attributes['organizer-id'];
    };

    const cssColor = (value, fallback) => (
        typeof value === 'string' && /^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([0-9\s.,%/+-]+\)|[a-z]+)$/i.test(value.trim())
            ? value.trim()
            : fallback
    );

    const constructedSheet = (cssText) => {
        try {
            const sheet = new CSSStyleSheet();
            sheet.replaceSync(cssText);
            return sheet;
        } catch (e) {
            return null;
        }
    };

    const FIXED_HEIGHT_CSS = ':where(.hievents-widget[data-hievents-autoresize="false"] > iframe, '
        + 'hievents-widget[data-hievents-autoresize="false"] > iframe, hievents-widget[autoresize="false"] > iframe) { height: 100%; }';
    let fixedHeightStylesAdded = false;

    const addFixedHeightStyles = () => {
        if (fixedHeightStylesAdded) return;
        fixedHeightStylesAdded = true;
        const sheet = constructedSheet(FIXED_HEIGHT_CSS);
        if (sheet && 'adoptedStyleSheets' in document) {
            document.adoptedStyleSheets = document.adoptedStyleSheets.concat(sheet);
            return;
        }
        const style = document.createElement('style');
        style.textContent = FIXED_HEIGHT_CSS;
        document.head.appendChild(style);
    };

    const renderButton = (element, attributes, instance) => {
        const root = element.shadowRoot || element.attachShadow({ mode: 'open' });
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('part', 'button');
        button.textContent = attributes['button-text'] || 'Get tickets';
        const background = cssColor(attributes['secondary-color'], '#7b5db8');
        const color = cssColor(attributes['secondary-text-color'], '#ffffff');
        const radiusValue = parseFloat(attributes.radius);
        const radius = Number.isFinite(radiusValue) ? `${Math.max(0, Math.min(radiusValue, 32))}px` : '10px';
        const css = `
            :host { display: inline-block; }
            button {
                display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                min-height: 44px; padding: 0 22px; border: 0; border-radius: ${radius};
                background: ${background}; color: ${color};
                font: inherit; font-weight: 600; font-size: 1rem; line-height: 1.2;
                cursor: pointer; transition: filter 0.15s ease, transform 0.15s ease;
                -webkit-tap-highlight-color: transparent;
            }
            button:hover { filter: brightness(1.06); }
            button:active { transform: translateY(1px); }
            button:focus-visible { outline: 2px solid ${background}; outline-offset: 3px; }
        `;
        root.innerHTML = '';
        const sheet = constructedSheet(css);
        if (sheet && 'adoptedStyleSheets' in root) {
            root.adoptedStyleSheets = [sheet];
        } else {
            const style = document.createElement('style');
            style.textContent = css;
            root.appendChild(style);
        }
        root.appendChild(button);

        button.addEventListener('click', safe(() => {
            const iframeId = `hievents-modal-${instanceNamespace}-${instance.index}`;
            openModal({
                kind: 'widget',
                label: button.textContent,
                title: 'Hi.Events Widget',
                iframeId,
                background: attributes['background-color'] || '#ffffff',
                src: buildWidgetSrc(attributes, iframeId, ['Mode=modal']),
                origin: element,
            });
        }));
        instance.button = button;
    };

    const mount = (element) => {
        if (!element || element.hasAttribute(MOUNTED_FLAG)) return;
        const attributes = attributesOf(element);

        if (!isValidTarget(attributes)) {
            console.error('HiEvent widget error: data-hievents-id or data-hievents-organizer-id is required');
            return;
        }

        element.setAttribute(MOUNTED_FLAG, '');
        bindGlobalListeners();

        const index = instanceCounter++;
        const iframeId = `hievents-iframe-${instanceNamespace}-${index}`;
        const instance = {
            index,
            element,
            attributes,
            iframe: null,
            autoResize: attributes.autoresize !== 'false',
        };
        instances.set(iframeId, instance);

        if (attributes['widget-type'] === 'button') {
            renderButton(element, attributes, instance);
            return;
        }

        if (element.tagName === 'HIEVENTS-WIDGET' && !element.style.display) {
            element.style.display = 'block';
            instance.setDisplay = true;
        }

        if (!instance.autoResize) {
            addFixedHeightStyles();
        }

        const iframe = document.createElement('iframe');
        iframe.setAttribute('sandbox', SANDBOX);
        iframe.setAttribute('allow', ALLOW);
        iframe.setAttribute('title', WIDGET_TITLES[attributes['widget-type']] || 'Hi.Events Widget');
        if (attributes.lazy !== 'false') {
            iframe.setAttribute('loading', 'lazy');
        }
        iframe.style.border = 'none';
        iframe.style.width = '100%';
        iframe.style.display = 'block';
        iframe.style.colorScheme = 'normal';
        iframe.id = iframeId;
        iframe.src = buildWidgetSrc(attributes, iframeId);
        instance.iframe = iframe;
        element.appendChild(iframe);
    };

    const unmount = (element) => {
        for (const [iframeId, instance] of instances.entries()) {
            if (instance.element !== element) continue;
            if (instance.iframe) {
                try { instance.iframe.remove(); } catch (e) { /* noop */ }
            }
            if (instance.button && element.shadowRoot) {
                element.shadowRoot.replaceChildren(document.createElement('slot'));
                if ('adoptedStyleSheets' in element.shadowRoot) {
                    element.shadowRoot.adoptedStyleSheets = [];
                }
            }
            if (instance.setDisplay && element.style.display === 'block') {
                element.style.display = '';
            }
            instances.delete(iframeId);
        }
        element.removeAttribute(MOUNTED_FLAG);
    };

    const scan = (root) => {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('.hievents-widget, hievents-widget').forEach((element) => mount(element));
    };

    const releaseDetached = () => {
        for (const instance of instances.values()) {
            if (!instance.element.isConnected) {
                unmount(instance.element);
            }
        }
    };

    const observeDom = () => {
        if (typeof MutationObserver === 'undefined') return;
        const added = new Set();
        let removed = false;
        let scheduled = false;
        const flush = safe(() => {
            scheduled = false;
            if (removed) {
                removed = false;
                releaseDetached();
            }
            added.forEach((node) => {
                if (!node.isConnected) return;
                if (node.matches('.hievents-widget')) {
                    mount(node);
                } else {
                    scan(node);
                }
            });
            added.clear();
        });
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) added.add(node);
                });
                if (mutation.removedNodes.length) removed = true;
            });
            if ((added.size || removed) && !scheduled) {
                scheduled = true;
                (window.requestAnimationFrame || setTimeout)(flush);
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    };

    function bindGlobalListeners() {
        if (globalListenersBound) return;
        globalListenersBound = true;
        window.addEventListener('message', safe(handleMessage));
    }

    const defineCustomElement = () => {
        if (typeof customElements === 'undefined' || customElements.get('hievents-widget')) return;

        class HiEventsWidgetElement extends HTMLElement {
            connectedCallback() {
                safe(() => mount(this))();
            }

            disconnectedCallback() {
                safe(() => unmount(this))();
            }

            attributeChangedCallback() {
                if (!this.hasAttribute(MOUNTED_FLAG)) return;
                if (this.pendingRemount) return;
                this.pendingRemount = true;
                Promise.resolve().then(safe(() => {
                    this.pendingRemount = false;
                    unmount(this);
                    mount(this);
                }));
            }

            static get observedAttributes() {
                return [
                    'event-id', 'organizer-id', 'widget-type', 'widget', 'primary-color', 'primary-text-color',
                    'secondary-color', 'secondary-text-color', 'background-color', 'padding', 'locale',
                    'continue-button-text', 'button-text', 'checkout', 'products', 'categories', 'sections',
                    'occurrence-id', 'promo-code', 'theme', 'font', 'radius', 'hide-promo-code',
                    'hide-category-titles', 'show-lineup', 'autoresize', 'lazy', 'layout', 'limit', 'event-category',
                    'show-past', 'open-in', 'prefill-email', 'prefill-first-name', 'prefill-last-name',
                ];
            }
        }

        customElements.define('hievents-widget', HiEventsWidgetElement);
    };

    const resumeCheckout = () => {
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
            if (resumed && resumed.locale && String(resumed.orderShortId) === String(returnOrderId)) {
                returnParams.set('Locale', resumed.locale);
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
            bindGlobalListeners();
            openCheckoutModal(path, null);

            try {
                window.history.replaceState({}, document.title, parentBaseUrl());
            } catch (e) {
                console.error('HiEvent widget error: Unable to clean return URL');
            }
            return;
        }

        const resume = readResume();
        const stillReserved = resume && typeof resume.reservedUntil === 'number'
            && resume.reservedUntil > Date.now();

        if (stillReserved) {
            let path = `/checkout/${encodeURIComponent(resume.eventId)}/${encodeURIComponent(resume.orderShortId)}/${encodeURIComponent(resume.step || 'details')}`;
            const resumeParams = new URLSearchParams();
            if (resume.sessionId) resumeParams.set('session_identifier', resume.sessionId);
            if (resume.locale) resumeParams.set('Locale', resume.locale);
            const resumeQuery = resumeParams.toString();
            if (resumeQuery) {
                path += `?${resumeQuery}`;
            }
            bindGlobalListeners();
            openCheckoutModal(path, null);
        } else if (resume) {
            clearResume();
        }
    };

    window.HiEventsWidget = {
        scan: safe(scan),
        mount: safe(mount),
        unmount: safe(unmount),
    };
    window.hiEventWidgetLoaded = true;

    preconnect();
    defineCustomElement();

    const start = safe(() => {
        scan();
        observeDom();
        resumeCheckout();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(document.currentScript);
