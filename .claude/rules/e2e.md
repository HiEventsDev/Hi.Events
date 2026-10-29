---
paths:
  - "e2e/**"
---

# E2E specs

- Arrange data through the API or `factory`, drive only the flow under test through the UI with a thin page object, and assert on real page content, not just a URL change.
- Tag fast, load-bearing checks with `@smoke`. Reserve E2E for real user journeys; pure logic belongs in backend tests.
- To run specs against uncommitted changes, use the e2e-dev-stack skill.
