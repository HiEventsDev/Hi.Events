---
paths:
  - "backend/app/**/*Spam*.php"
  - "backend/app/**/SpamEvents/**"
  - "backend/app/**/*Suspicious*.php"
  - "backend/app/Services/Domain/Message/**"
  - "backend/app/Jobs/Message/**"
  - "backend/app/Services/Infrastructure/Ai/**"
  - "backend/app/**/*Turnstile*.php"
  - "backend/app/Validators/Rules/PersonNameRule.php"
  - "frontend/src/components/common/Turnstile/**"
---

# Abuse & spam

- Any new public order, waitlist or contact path must respect event status: public orders on non-LIVE events only for the event's own account and never on PENDING_MANUAL_REVIEW; waitlist joins and organizer contact require LIVE.
- New abuse signals go in `SuspiciousActivityDetector`. Each finding needs a `nextStep`, and each item sets `accountId` where one exists so the report shows the account and links to its admin page (`?action=suspend` opens the suspend modal). Names and titles in the report go through `StringHelper::escapeMarkdown`; markdown mail otherwise renders `[text](url)` inside `{{ }}` as a live link.
- ARCHIVED events are spam-checked like LIVE ones (`EventSpamCheckService::CHECKED_EVENT_STATUSES`): on entering the status and on content edits. Approving a flagged event restores `previous_event_status`. Mail is not withheld while the first check runs; that window is accepted.
- Any user text that reaches a buyer's email belongs in `EventSpamCheckContentService`, and editing it dispatches a re-check through `EventSpamCheckDispatchService`. Custom email templates go in as raw Liquid source (hashed) plus a sample render (not hashed: the preview context is random); never strip Liquid, string literals print.
- Spam-review notices go to the account email, never `organizers.email` (unverified, free text).

## Message AI review

- With `MessageAiReviewService::isEnabled()` (SaaS mode, `APP_MESSAGE_AI_REVIEW_ENABLED`, an Anthropic key) an event message entering `PENDING_REVIEW` goes to `ReviewMessageWithAiJob` instead of `MessagePendingReviewJob`. It mirrors the CRM campaign check (`.claude/rules/ee/crm.md`); keep the two in step.
- `MessageAutoReviewService` stores the verdict in `messages.ai_review` (`decision` `APPROVED`/`NEEDS_PERSON`, `confidence`, `reasons`, `reviewed_at`) and only acts while the message is still `PENDING_REVIEW`. An approval at or above `APP_MESSAGE_AI_REVIEW_CONFIDENCE_THRESHOLD` releases the message through `MessageApprovalService::releasePendingReview`, the same path as an admin approval. Anything else (not approved, under the threshold, job failed) is recorded as `NEEDS_PERSON` and only then goes to `MessagePendingReviewJob`. The check never rejects or cancels a message.
- Anything that releases a held message goes through `MessageApprovalService`; its status changes are conditional on `PENDING_REVIEW`, so an admin and the check can't both release it.
- Message text is untrusted: everything entering a review prompt goes through `UntrustedPromptText` (angle brackets replaced, links listed separately), shared with the campaign check.
- A message containing an image (an `<img>`, or a CSS `url(…)` such as a background image, which the message purifier keeps) is never approved by the check and never sent to the model (it can't read images); it goes to a person. A retried job acts on the verdict already recorded instead of asking again.
- Test sends (`send_data.is_test`) don't count towards the daily message limit once delivered, but a test held for review does, so review requests stay capped. `POST events/{id}/messages` is throttled (`message-send`) below anything a real sender reaches, to bound test sends.
