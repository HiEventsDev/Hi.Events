---
paths:
  - "frontend/src/**/*.tsx"
  - "frontend/src/**/*.scss"
---

# Frontend UI patterns

- Create/edit modals use `common/Modal` with a `ModalFooter` (Cancel + primary action; `start` for a destructive or secondary action), never a full-width submit button.
- Settings pages live under `/settings/*`, built from `common/SettingsPage`. Keep the `/account/*` and `/manage/profile*` redirects: sent emails still link to them.
- `global.scss` gives every `.mantine-InputWrapper-root` and `.mantine-Switch-root` a bottom margin. For inputs inline in a row, zero it for that row (`.row :global(.mantine-InputWrapper-root) { margin-bottom: 0; }`) instead of a magic `mt`/`mb` on the sibling.
- Add a `data-testid` only to elements an E2E spec drives: buttons, menu items, and widgets with no accessible label (`CustomSelect` takes `dataTestId`; options become `<dataTestId>-option-<value>`). Kebab-case `<feature>-<element>`, e.g. `promo-code-create-button`.
