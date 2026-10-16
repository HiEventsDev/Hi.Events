---
paths:
  - "backend/app/**/TwoFactor/**"
  - "backend/app/**/*TwoFactor*.php"
  - "backend/app/**/*TrustedDevice*.php"
  - "backend/tests/**/TwoFactor/**"
  - "frontend/src/**/TwoFactor/**"
  - "frontend/src/**/*woFactor*"
---

# Two-factor authentication

- Check codes only through `TwoFactorVerifier`; it holds the per-user lockout that stops brute force across fresh challenges.
- The verified login challenge is reused for multi-account selection; never ask for or re-send the password.
- Any new path that changes a password or disables/resets 2FA must revoke trusted devices (`user_trusted_devices`).
- Recovery codes are sha256 hashes, not app-key HMACs, so they survive `APP_KEY` rotation.
- New endpoints a not-yet-enrolled member needs go on the `EnsureTwoFactorEnrolled` allowlist.
- Every 2FA reset goes through `TwoFactorResetService`.
