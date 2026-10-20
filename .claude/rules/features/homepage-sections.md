---
paths:
  - "backend/app/**/*HomepageSection*.php"
  - "frontend/src/utilites/homepageSection*.ts"
  - "frontend/src/components/layouts/EventHomepage/Sections/**"
  - "frontend/src/components/routes/event/HomepageDesigner/**"
  - "frontend/src/components/routes/organizer/OrganizerHomepageDesigner/**"
---

# Homepage sections

- User-supplied `homepage_sections` (events and organizers) are saved only through `HomepageSectionsSanitizer`: it purifies HTML, keeps only image ids owned by this event/organizer, and only allows YouTube/Vimeo video links.
- The About section renders `event.description`; never copy the description into a section, because SEO, listings, emails and the API all read it. Extra copy belongs in `TEXT` sections.
