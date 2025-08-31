#!/bin/bash

# This script lists all untranslated strings in a .po file.

# arbitrary translation file
poFile="../src/locales/pt.po"

if [ -f "$poFile" ]; then
    echo "Checking file: $poFile"

    awk '
    BEGIN { RS=""; FS="\n" }
    {
        msgid = ""; msgstr = ""; references = "";
        in_msgid = 0; in_msgstr = 0; is_obsolete = 0;

        # Check if this entry is obsolete (contains #~ lines)
        for (i = 1; i <= NF; i++) {
            if ($i ~ /^#~/) {
                is_obsolete = 1;
                break;
            }
        }

        # Skip obsolete entries
        if (is_obsolete) {
            next;
        }

        for (i = 1; i <= NF; i++) {
            if ($i ~ /^msgid "/) {
                msgid = $i; in_msgid = 1; in_msgstr = 0;
            } else if ($i ~ /^msgstr "/) {
                msgstr = $i; in_msgstr = 1; in_msgid = 0;
            } else if (in_msgid && $i ~ /^"/) {
                msgid = msgid "\n" $i;
            } else if (in_msgstr && $i ~ /^"/) {
                msgstr = msgstr "\n" $i;
            } else if ($i ~ /^#:/) {
                references = $i;
            } else {
                in_msgid = 0; in_msgstr = 0;
            }
        }

        # Normalize msgstr and msgid to make comparison easier
        gsub(/\n/, "", msgid);
        gsub(/\n/, "", msgstr);

        # Skip the file header entry (empty msgid)
        if (msgid == "msgid \"\"") {
            next;
        }

        if (msgstr == "msgstr \"\"") {
            if (references != "") {
                print references;
            }
            print msgid;
            print msgstr "\n";
        }
    }
    ' "$poFile"
else
    echo "File not found: $poFile"
fi