---
paths:
  - "backend/app/**/*HomepageSection*.php"
  - "frontend/src/utilites/homepageSection*.ts"
  - "frontend/src/components/layouts/EventHomepage/**"
  - "frontend/src/components/layouts/HomepageFrame/**"
  - "frontend/src/constants/eventLayouts.ts"
  - "frontend/src/components/routes/event/HomepageDesigner/**"
  - "frontend/src/components/routes/organizer/OrganizerHomepageDesigner/**"
---

# Homepage sections

- User-supplied `homepage_sections` (events and organizers) are saved only through `HomepageSectionsSanitizer`: it purifies HTML, keeps only image ids owned by this event/organizer, and only allows YouTube/Vimeo video links.
- The About section renders `event.description`; never copy the description into a section, because SEO, listings, emails and the API all read it. Extra copy belongs in `TEXT` sections.
- Event page layouts (Marquee, Flyer, Story, Invite) are rows of traits in `constants/eventLayouts.ts`; CSS keys off the traits (`data-rail`, `data-poster`, `data-width`, `data-centered`), never layout names. A new layout is a new row, a designer sketch in `LayoutPicker/LayoutPreview.tsx`, and a backend `HomepageLayout` case. `DEFAULT_EVENT_LAYOUT` applies to every event without a saved layout.
- Sections render in columns from ~350px (phones, Flyer on tablet) to ~690px, so their responsive rules use container queries (`hp.narrow`, `hp.compact`) on the section, never viewport media queries. Dialogs portal to `main` and keep viewport rules.
- Status labels ("This event has ended", "Happening now") come from the server's `lifecycle_status`, never the client clock, so SSR and hydration agree. An ended event renders no Tickets block and no ticket rail; Marquee drops to the medium width.
- The intro always shows the venue's full address with Get directions, even when the Location section repeats it lower down.
