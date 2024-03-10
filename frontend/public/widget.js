/* eslint-disable lingui/no-unlocalized-strings */
(function () {
    const isScriptLoaded = () => !!window.hiEventWidgetLoaded;

    const loadWidget = () => {
        window.hiEventWidgetLoaded = true; // Mark script as loaded

        let scriptOrigin;
        try {
            const scriptURL = document.currentScript.src;
            scriptOrigin = new URL(scriptURL).origin;
        } catch (e) {
            console.error('HiEvent widget error: Invalid script URL');
            return;
        }

        const widgets = document.querySelectorAll('.hievents-widget');
        widgets.forEach((widget) => {
            const eventId = widget.getAttribute('data-hievents-id');
            if (!eventId) {
                console.error('HiEvent widget error: data-hievents-id is required');
                return;
            }

            const iframe = document.createElement('iframe');
            iframe.setAttribute('sandbox', 'allow-forms allow-scripts allow-same-origin');
            iframe.setAttribute('title', 'Hi.Events Widget');
            iframe.style.border = 'none';
            iframe.style.min_height = '60px';
            iframe.width = '100%';
            iframe.height = '100%';

            let src = `${scriptOrigin}/widget/${encodeURIComponent(eventId)}?`;
            Array.from(widget.attributes).forEach(attr => {
                if (attr.name.startsWith('data-hievents-') && attr.name !== 'data-hievents-id') {
                    const paramName = attr.name.substring(13).replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                    params.push(`${paramName}=${encodeURIComponent(attr.value)}`);
                }
            });

            iframe.src = src + params.join('&');

            widget.appendChild(iframe);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadWidget);
    } else {
        if (!isScriptLoaded()) {
            loadWidget();
        }
    }
})();
