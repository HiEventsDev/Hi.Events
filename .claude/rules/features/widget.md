---
paths:
  - "frontend/src/embed/**"
  - "frontend/public/widget.js"
  - "frontend/src/widget-test/**"
  - "frontend/src/components/widget/**"
  - "frontend/src/components/common/WidgetEditor/**"
  - "frontend/src/components/layouts/ProductWidget/**"
  - "frontend/src/components/layouts/OrganizerEventsWidget/**"
  - "frontend/src/components/layouts/EventContentWidget/**"
  - "frontend/src/utilites/widget*.ts"
  - "frontend/src/routeLoaders/widgetRouteLoader.ts"
  - "backend/app/**/Widget*"
---

# Embeddable widget

- `widget.js` is served from our origin to every live embed, so a change reaches every host page at once. Existing `.hievents-widget` + `data-hievents-*` markup must keep rendering as before; new behaviour goes behind new, optional attributes.
- `src/embed/widget.js` is the source and `public/widget.js` a committed copy (served in dev, copied into the build). Copy it after every change.
- Attributes reach the iframe as PascalCase params (`data-hievents-continue-button-text` → `ContinueButtonText`). `resolveWidgetConfig` maps them onto the snake_case keys that saved widgets store. Host-page `aff`, `promo_code` and `utm_*` are forwarded as-is.
- Language: a `data-hievents-locale` attribute wins, then a saved widget's `locale` (the SSR loader sets `widgetLocale` on the request context; the client reads it from the hydrated loader data), then the visitor's language. Old snippets hardcode `data-hievents-locale="en"` with `data-hievents-widget-version="1.0"`; that pair is ignored.
- A saved widget's embed carries only what the host page needs (`HOST_KEYS` in `WidgetEditor/embedCode.ts`); the rest comes from `/public/widgets/{short_id}`, and attributes override it. The Button type's text, colours and radius are drawn by `widget.js` before anything loads, so they live in the embed code; the editor tells users to re-copy the code when a saved widget's embed attributes change. Orders store `widget_id` for stats (seat-picker orders too). Views count anonymous loads, deduped per IP and written in batches (`WidgetViewIncrementService`), never one UPDATE per load.
- Widget routes report the height of `WidgetFrame`'s inner wrapper, never `document.scrollHeight`, so they can shrink. Dialogs inside auto-resized embeds are placed near the click and clamped to fit (`EmbeddedDialogOffset`); Mantine's default sizing uses `100dvh`, which is the whole content height in an auto-resized iframe.
- Widget routes render real content on the server, so any input that appears before hydration must stay disabled until `useIsHydrated()` (see `NumberSelector`), or the visitor's input is wiped.
- The editor preview loads the real widget route with `Preview=true` and pushes settings over `postMessage`; preview mode never creates orders.
- Widgets run on third-party pages: keep widget routes free of heavy imports, and let the SSR loaders prefetch everything the first paint needs.
