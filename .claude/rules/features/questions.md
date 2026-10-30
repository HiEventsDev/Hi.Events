---
paths:
  - "backend/app/**/Question/**"
  - "backend/app/**/Questions/**"
  - "backend/app/**/*Question*.php"
  - "frontend/src/components/routes/event/Questions/**"
  - "frontend/src/components/forms/QuestionForm/**"
  - "frontend/src/components/common/CheckoutQuestion/**"
  - "e2e/**/question*"
---

# Registration questions

- `QuestionInput` (`common/CheckoutQuestion`) is the only question renderer: checkout, the widget, box office, organizer answer editing and both builder previews use it. A new question type needs a case there and in `forms/QuestionForm/questionTypes.ts`.
- `applies_to_all_tickets` keeps `product_questions` rows in sync rather than resolving at read time: `QuestionTicketScopeService` attaches every TICKET product on question save and is called from `CreateProductService` and `EditProductHandler`, so readers keep using `product_ids`. Omitting the field on `PUT` clears it, so older API clients that send explicit `product_ids` keep their meaning.
- New questions are created with `order = max + 1`. The builder sorts each section separately but sends the order and attendee ids together, so `order` stays consistent across both.
- Template and type labels are built in getters (`getQuestionTemplates`, `getQuestionTypeOptions`), never at module scope (SSR locale bug).
- Follow-ups use `condition_question_id` + `condition_values` (parent option labels, any-of). `QuestionVisibilityService` (PHP) and `utilites/questionLogic.ts` must stay in step: a follow-up shows only when its parent is shown (not hidden; attached to the ticket for attendee parents) and the parent's answer matches.
- Checkout rules skip required checks and validation for follow-ups whose condition is unmet, and `CompleteOrderHandler`/`BoxOfficeOrderCreationService` drop their answers. Answers to hidden or unattached questions are still stored: that is existing public-API behaviour.
- Checkout rules resolve each attendee's ticket from `product_price_id` (as the handler does), never the request's `product_id`, or a mismatched pair skips visibility and eligibility checks. `OrderQuestionRule` is implicit, so an omitted `order.questions` still enforces every shown required order question (a deliberate public-API tightening on this branch; call it out in release notes).
- `QuestionLogicService` validates logic on save. A `PUT` that omits `condition_question_id` or `disqualifying_options` keeps the stored values (explicit `null` clears them), unlike `applies_to_all_tickets`. Option renames arrive as `option_renames` and rewrite follow-up conditions.
- `QuestionOrderService` keeps follow-ups directly after their parent in `order` (same section only). Deleting a parent also deletes its follow-ups, unless anything in that subtree has answers.
- Mantine 9's `setPath` does not create missing objects: any form that renders `QuestionInput` needs an empty response object per question first.
- Checkout eligibility UX is settled: never reorder questions. An ineligible answer shows `EligibilityNotice` directly under it, every input after it is disabled with `LockedFields` (later questions, later sections, later attendee cards), and the submit button is disabled with the organizer's message and a Leave checkout link under it.
