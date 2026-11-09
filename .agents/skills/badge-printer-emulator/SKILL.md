---
name: badge-printer-emulator
description: Emulate a Brother QL-820NWB AirPrint label printer with macOS's ippeveprinter and print badges to it from Safari in the iOS Simulator, to see exactly what a real badge printer receives. Use when changing badge layout, print CSS or door and kiosk printing.
---

# Badge printer emulator

Check-in vendors most often recommend the Brother QL-820NWB, and it's the model they support for iPads (AirPrint). `brother-ql820nwb.conf` reproduces its AirPrint attributes from a real Brother QL dump: URF raster only, 300 dpi 8-bit grey, black only, Brother DK label sizes with the 62 × 100 mm DK-1202 badge label loaded.

## Run it

Use bash (zsh doesn't split arguments) and run it in the background:

```bash
S=<scratch dir>; mkdir -p "$S/spool" "$S/out"
ippeveprinter -vvv -k -d "$S/spool" -p 8631 -r _universal -a .agents/skills/badge-printer-emulator/brother-ql820nwb.conf "Brother QL-820NWB" > "$S/printer.log" 2>&1
```

The iOS Simulator finds it over Bonjour. Stop it by killing the process.

## Print to it

1. On a booted iPad simulator, open a check-in list with badge printing on in Safari (`https://localhost:8443/check-in/<short_id>`).
2. Scan by typing the ticket code followed by Return (USB scanner mode), then tap Print my badge (kiosk) or Print badge (attendee sheet).
3. In the print sheet pick Brother QL-820NWB and paper 62 x 100 mm, then Print.
4. Decode the job: `python3 -I .agents/skills/badge-printer-emulator/urf2png.py "$S/spool/<N>-<name>.urf" "$S/out/badge"`. It prints the page size and writes `-gray.png` (what iOS sent) and `-thermal.png` (dithered to dots, as the print head prints it).

## What to check

- The page is 62.0 × 100.0 mm and nothing is cropped (Safari's own margins, `getIosPrintScale`).
- The footer shows only the site origin, never `/check-in/…`.
- With This printer only prints black on, ribbons are solid black with readable white text.
- QR modules are at least 3 dots wide if the QR code has to scan.

## Gotchas

- `-a` can't be combined with `-f`, `-M` or `-m`; everything lives in the conf file.
- Jobs fail with "Unsupported media-col collection value" unless `media-size-supported` lists the size iOS sends.
- iOS caches printers by name. After stopping the emulator, cancel the retrying job on the iPad.
- Desktop Chrome kiosk printing needs the emulator as a macOS printer (`lpadmin -p BadgeEmulator -E -v ipp://localhost:8631/ipp/print -m everywhere`). That changes system settings, so ask before running it.
