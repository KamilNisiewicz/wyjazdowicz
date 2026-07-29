---
change_id: testing-geocoding-distance-coverage
title: Geocoding and distance coverage (test rollout Phase 1)
status: implemented
created: 2026-07-29
updated: 2026-07-29
archived_at: null
---

## Notes

Open a change folder for rollout Phase 1 of context/foundation/test-plan.md: "Geocoding & distance coverage".
Risks covered: #2 (Nominatim returns ambiguous/wrong city, wrong distance computed), #6 (Nominatim external API failure — timeout, rate-limit, empty response — while adding an away match).
Test types planned: integration (feature, Http::fake()).
Risk response intent:
- #2: prove a user adding an away match in a city with multiple geocoding matches sees a candidate list to confirm (not silent auto-pick of the first result), and picking the same city twice yields the same distance.
- #6: prove that when Nominatim doesn't respond, errors, or returns empty during away-match creation, the user sees a clear error message and can retry — the app never saves a match with distance_km = null/garbage data, nor throws a raw 500.
After creating the folder, follow the downstream continuation rule.
