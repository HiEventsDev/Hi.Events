---
paths:
  - "frontend/src/**/*.tsx"
  - "frontend/src/**/*.scss"
---

# Frontend UI patterns

- Create/edit modals use `common/Modal` with a `ModalFooter` (Cancel + primary action; `start` for a destructive or secondary action), never a full-width submit button.
- Settings pages live under `/settings/*`, built from `common/SettingsPage`. Keep the `/account/*` and `/manage/profile*` redirects: sent emails still link to them.
- Event and organizer settings are one section per page (`/manage/{event,organizer}/:id/settings/:section`), declared as groups for `common/SectionedSettings`. A section's `anchorId` is its pre-split `#hash`: emails and the Stripe return URL still link to `settings#<anchorId>`, which redirects to the section, so never rename one. Link to a section by its path, and put a new setting in the section it belongs to rather than a catch-all. Each section renders a `common/SettingsPage` with `SettingsSection` cards: simple controls in `SettingsRow` (label and description left, control right, `inlineControl` for switches), self-labelled widgets (editors, `CustomSelect`, pickers) in `SettingsBlock`, `SettingsSaveFooter` on a single-card form and `SettingsSaveBar` after a form that spans several cards (`SettingsSections`). Hydrate with `setInitialValues` + `setValues` and `resetDirty()` on save so the unsaved-changes state is accurate.
- `global.scss` gives every `.mantine-InputWrapper-root` and `.mantine-Switch-root` a bottom margin. For inputs inline in a row, zero it for that row (`.row :global(.mantine-InputWrapper-root) { margin-bottom: 0; }`) instead of a magic `mt`/`mb` on the sibling.
- Add a `data-testid` only to elements an E2E spec drives: buttons, menu items, and widgets with no accessible label (`CustomSelect` takes `dataTestId`; options become `<dataTestId>-option-<value>`). Kebab-case `<feature>-<element>`, e.g. `promo-code-create-button`.
- List tables use `common/TanStackTable`: one line per row, the identifying column `meta.sticky: 'left'`, the actions column `meta.sticky: 'right'`, and horizontal scroll at every width instead of a stacked mobile layout. Build cells from `TanStackTable/cells.tsx` (`IdentityCell`, `CellLink`, `CellText`, `CellCopy`, `EmptyCell`), statuses from `common/StatusBadge`, and sortable headings with `sortableHeader` + `tableSortFromQuery` (`TanStackTable/sorting.tsx`). Columns whose cells handle their own clicks set `meta.interactive` so `onRowClick` doesn't fire. A table whose identifying cell needs two lines (name over email) sets `rowLines={2}` and uses `<IdentityCell stacked>`, so every row keeps the same height; never let a cell wrap or stack to a variable height.
- A list page wraps its table in `common/TableCard`: `<ToolBar embedded>` (search, filters, then `ToolBarSlot` for the column toggle and actions), the table with `noCard`, `TableCardSkeleton` while loading and `TableCardFooter` for the range and pagination.
