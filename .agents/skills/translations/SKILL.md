---
name: translations
description: Add or update frontend translations with Lingui. Use after adding or changing any user-facing frontend string, to extract it, translate it into every supported locale and compile.
---

# Frontend translations (Lingui)

Every new or changed user-facing string is translated into all supported locales in the same change.

## Steps

```bash
cd frontend
yarn messages:extract --clean   # the table shows missing translations per locale
```

1. Add a translation to every `.po` file in `frontend/src/locales/` except `en.po` (English is the source):

   ```po
   #: src/path/to/component.tsx:123
   msgid "Your English String"
   msgstr "Translated String"
   ```

2. Run `yarn messages:extract --clean` again and confirm 0 missing for each locale.
3. Run `yarn messages:compile`.

`frontend/scripts/list_untranslated_strings.sh` lists anything still untranslated.

## Troubleshooting

- Counts look wrong: run extract with `--clean` to drop obsolete entries.
- Translation not showing: run `yarn messages:compile`.
- `.po` syntax errors: check the escaping of quotes.
