/* eslint-disable lingui/no-unlocalized-strings */

(function () {
    const isScriptLoaded = () => !!window.hiEventWidgetLoaded;

    const loadWidget = () => {
        window.hiEventWidgetLoaded = true;

        const scriptURL = document.currentScript && document.currentScript.src;
        const scriptOrigin = new URL(scriptURL).origin;

        const widgets = document.querySelectorAll('.hievents-widget');
        widgets.forEach((widget) => {
            const eventId = widget.getAttribute('data-hievents-id');
            const primaryColor = widget.getAttribute('data-hievents-primary-color');
            const primaryTextColor = widget.getAttribute('data-hievents-primary-text-color');
            const secondaryColor = widget.getAttribute('data-hievents-secondary-color');
            const secondaryTextColor = widget.getAttribute('data-hievents-secondary-text-color');
            const widgetType = widget.getAttribute('data-hievents-widget-type');
            const widgetVersion = widget.getAttribute('data-hievents-widget-version');
            const locale = widget.getAttribute('data-hievents-locale');

            if (!eventId) {
                console.error('HiEvent widget error: data-hievents-id is required');
                return;
            }

            const iframe = document.createElement('iframe');
            iframe.setAttribute('sandbox', 'allow-forms allow-scripts allow-same-origin');
            iframe.setAttribute('title', 'Hi.Events Widget');
            iframe.style.border = 'none';
            iframe.width = '100%';
            iframe.height = '100%';

            console.log(scriptOrigin);
            iframe.src = scriptOrigin + '/widget/' + encodeURIComponent(eventId) +
                '?primaryColor=' + encodeURIComponent(primaryColor) +
                '&primaryTextColor=' + encodeURIComponent(primaryTextColor) +
                '&secondaryColor=' + encodeURIComponent(secondaryColor) +
                '&secondaryTextColor=' + encodeURIComponent(secondaryTextColor) +
                '&widgetType=' + encodeURIComponent(widgetType) +
                '&widgetVersion=' + encodeURIComponent(widgetVersion) +
                '&locale=' + encodeURIComponent(locale);

            widget.appendChild(iframe);
        });
    };

    if (!isScriptLoaded()) {
        loadWidget();
    }
})();
