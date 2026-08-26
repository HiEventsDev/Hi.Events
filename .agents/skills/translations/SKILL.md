---
name: translations
description: Frontend translation workflow using Lingui - extracting, adding, and compiling translations for all supported languages
---

# Frontend Translation Workflow (Lingui)

IMPORTANT: Always update translations as you develop features. When adding new translatable strings, immediately add translations for all supported languages.

## Core Commands

```bash
cd frontend

# Extract translatable strings (use --clean for accurate counts)
yarn messages:extract --clean

# Compile translations for production
yarn messages:compile

# Check for untranslated strings
cd scripts && ./list_untranslated_strings.sh
```

## Process

1. **Extract**: `yarn messages:extract --clean`
2. **Check**: Look at the output table for missing translation counts
3. **Add translations**: Update the `.po` files for each language
4. **Verify**: Run extract again to confirm 0 missing
5. **Compile**: `yarn messages:compile`

## Adding Translations

Add entries to each locale's `.po` file in `frontend/src/locales/`:

```po
#: src/path/to/component.tsx:123
msgid "Your English String"
msgstr "Translated String"
```

## Supported Languages

| Code | Language |
|------|----------|
| en | English (source - no translation needed) |
| de | Deutsch |
| es | Espanol |
| fr | Francais |
| pt | Portugues |
| pt-br | Portugues do Brasil |
| it | Italiano |
| nl | Nederlands |
| zh-cn | Simplified Chinese |
| zh-hk | Traditional Chinese (HK) |
| vi | Tieng Viet |
| ru | Russian (currently untranslated) |

## Troubleshooting

- **Counts seem wrong**: Use `--clean` flag to remove obsolete entries
- **Translation not appearing**: Run `yarn messages:compile` after adding
- **Syntax errors**: Check for proper escaping of quotes in `.po` files
