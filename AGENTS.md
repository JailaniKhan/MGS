# AGENTS.md

## Agent skills

### Issue tracker

Issues and specs live in this repo's GitHub Issues, operated via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Design skills (model-invoked, anti-slop frontend)

Installed globally from `Leonxlnx/taste-skill` into `~/.agents/skills/`. Available in any repo, reached automatically when the task fits — not via `/slash` invocation.

- `design-taste-frontend` — default v2. Anti-slop landing pages / portfolios / redesigns; reads the brief, infers the design direction, ships non-templated UI.
- `design-taste-frontend-v1` — original v1. Pin only if v2 breaks a specific workflow. Keep disabled when v2 is active (see `opencode.json` permissions).
- `gpt-taste` — stricter GPT/Codex variant; higher layout variance, stronger GSAP direction.
- `redesign-existing-projects` — audit-first: survey an existing UI, then fix layout / spacing / hierarchy / styling.
- `high-end-visual-design` — soft, calm, expensive UI; lower contrast, whitespace, premium fonts, spring motion.
- `minimalist-ui` — editorial product UI (Notion/Linear vibes); restrained palette, crisp structure.
- `industrial-brutalist-ui` — hard mechanical language; Swiss type, sharp contrast, experimental layout.
- `stitch-design-taste` — Google Stitch-compatible rules; optional `DESIGN.md` export.
- `full-output-enforcement` — fires when the model ships truncated output; full output, no placeholder comments.

Dials (in `design-taste-frontend`): `DESIGN_VARIANCE` (1–10 layout experimentation), `MOTION_INTENSITY` (animation depth), `VISUAL_DENSITY` (info per viewport).

### Skill locations

All skills are installed globally under `~/.agents/skills/` (mattpocock engineering + taste-skill design), discoverable by opencode from `.opencode/`, `.claude/`, and `.agents/` paths. None are committed to this repo. Restart opencode to pick up new skills added mid-session.
