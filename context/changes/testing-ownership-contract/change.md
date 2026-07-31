---
change_id: testing-ownership-contract
title: Ownership contract test across GameMatch/Team routes
status: implemented
created: 2026-07-31
updated: 2026-07-31
archived_at: null
---

## Notes

Open a change folder for rollout Phase 3 of context/foundation/test-plan.md: "Ownership contract".
Risks covered: #5 (No centralized authorization policy — a future endpoint touching GameMatch/Team could skip user-scoping and expose another user's match, IDOR).
Test types planned: integration (feature, contract-style).
Risk response intent: Every route touching GameMatch (existing and future) denies access (404, no existence leak) to a user who swaps in another user's ID — verifiable by one shared pattern, not per-endpoint memory. Must challenge: existing owner-isolation tests cover this forever — they cover today's endpoints only; a new endpoint written without awareness of the $request->user()->gameMatches() pattern could use GameMatch::find($id) directly and quietly break isolation. Avoid: a test that checks only one endpoint (e.g. edit) and calls the topic closed, while delete/view may have a different code path.
