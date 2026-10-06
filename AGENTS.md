# AGENTS.md

## Commands

| Command                         | What it does                                                                 |
| ------------------------------- | ---------------------------------------------------------------------------- |
| `npm run lint`                  | Prettier `--check .` **and** `php vendor/bin/pint --test`                    |
| `npm run lint:fix`              | Prettier `--write .` **and** `php vendor/bin/pint` — run after any bulk edit |
| `npm test`                      | `php artisan test`                                                           |
| `composer test`                 | same, but clears the config cache first                                      |
| `npm run build` / `npm run dev` | Vite build / dev server                                                      |

Run `npm run lint` before you claim a change is done. Formatting is enforced by a pre-commit hook: `.husky/pre-commit` → `npx lint-staged` → Prettier on `*.{js,json,css,md,yml,yaml}`, Pint on `*.php`. Config lives in `.prettierrc`, `.prettierignore`, `.lintstagedrc`.

**Pre-existing test failure:** `WhatsAppMessageTest::test_sends_image_with_caption_stores_media_and_logs_reminder` ends in `Fatal error: Premature end of PHP process`. It is not caused by your change — the rest of the suite passes (382/384, 2 skipped).

**`gh` CLI is not installed** on this machine, so the issue-tracker and triage-label workflows below cannot run until it is.

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

### TypeSafe skill (model-invoked)

Installed globally from `typesafe-ai/skills` into `~/.agents/skills/`. Reached automatically when a feature needs programmable AI common sense (routing, ranking, extraction, verification) or when an LLM prompt-and-parse step could become a structured decision. Treats the live TypeSafe docs (https://docs.typesafe.ai/llms.txt) as the source of truth.

### UI/UX skill (model-invoked)

`ui-ux-pro-max` — installed globally into `~/.agents/skills/`. Searchable design-intelligence database: styles, colour palettes, font pairings, product types, UX guidelines, chart types, and 22 stacks. Reached automatically for interface work — designing, reviewing, or fixing UI.

This repo's UI is **Blade + Tailwind 4 + Vite** (not React Native/Flutter), so use `--stack html-tailwind`, and check the skill's **App UI** checklist before shipping screens (it covers touch targets, safe areas, dark-mode contrast, and no-emoji-as-icon). Its scripts need Python 3; the interpreter path is recorded in that skill's own `SKILL.md`.

### Skill locations

All skills are installed globally under `~/.agents/skills/` (mattpocock engineering + taste-skill design + typesafe-ai), discoverable by opencode from `.opencode/`, `.claude/`, and `.agents/` paths. None are committed to this repo. Restart opencode to pick up new skills added mid-session.
