# AGENTS.md

Guidance for AI coding agents (and humans) working on this repository. Read [README.md](README.md) first for what the product does.

## Commands

```bash
composer install && pnpm install
pnpm build                                   # SPA → public/app (gitignored). Rebuild after every frontend change.
npx tsc --noEmit -p .                        # frontend typecheck (there is no lint or test setup yet)
php -l <file>                                # PHP syntax check
php bin/console debug:router                 # list routes
php bin/console doctrine:migrations:migrate -n
php bin/console app:create-admin-user <email> <password>
php -S 127.0.0.1:8000 -t public              # local server
```

### Running against a throwaway database

Never test against `var/data_dev.db`: it holds the owner's real feeds and account. Point `DATABASE_URL` at a scratch file instead:

```bash
export DATABASE_URL="sqlite:////tmp/fp-scratch.db"
php bin/console doctrine:migrations:migrate -n
php bin/console app:create-admin-user test@example.test "$(openssl rand -hex 12)"
php -d variables_order=EGPCS -S 127.0.0.1:8765 -t public
```

**Gotcha:** PHP's built-in server does not expose shell environment variables to Symfony unless you pass `-d variables_order=EGPCS`. Without it, `.env` wins and you silently hit the dev database.

## Architecture

- **Symfony 8 / PHP 8.4 backend** (`src/`). JSON API under `/api`, form login at `/login`, and the public RSS endpoint at `/filtered/{id}-{slug}`.
- **React 19 SPA** (`frontend/src`), built by Vite into `public/app/app.{js,css}` and loaded by `templates/spa/index.html.twig`. Symfony serves that template for `/`, `/login` and `/admin/...`. A new SPA route also needs a matching Symfony route in `AdminSpaController`/`HomeController`, otherwise a hard refresh returns a 404.
- **SQLite via Doctrine.** `PodcastFeed` stores rules as JSON in `filters`, and the generated XML in `cachedXml` (24 h TTL, cleared on edit).
- **Auth:** every `User` is an admin (`getRoles()` always adds `ROLE_ADMIN`). Admin API mutations require the `X-CSRF-Token` header, using the `admin-api` token from `/api/bootstrap`.

### Filter semantics (keep frontend and backend in sync)

`FeedFilterService::itemMatches()`:

- Rules are grouped by category (`title`, `duration`). Every category must pass (AND across categories).
- Within a category, rules fold left to right using each rule's `combinator`. The first rule's combinator is ignored. There is no operator precedence: `A OR B AND C` means `(A OR B) AND C`.
- Title matching is case-insensitive substring matching. Duration comes from `itunes:duration` (seconds, `MM:SS` or `HH:MM:SS`).
- An episode with no parseable duration **fails** any duration rule.

`frontend/src/lib/format.ts` (`groupRules`, `summarizeRules`) mirrors this logic for the editor's live summary. If you change one side, change the other.

### Feed rewriting

`FeedFilterService::filterFeedXml()` removes non-matching `<item>`s, overwrites the channel `<title>`/`<description>`, rewrites `atom:link rel="self"`, and **strips `itunes:new-feed-url`**. Apple Podcasts follows that tag and would move subscribers back to the unfiltered source. Leave `itunes:block` alone: it only controls directory listing.

When setting text on DOM nodes, use `textContent`, not `nodeValue` or `createElement($name, $value)`. Those don't escape `&` and silently produce empty elements.

## Frontend conventions

- All styling lives in `frontend/src/styles.css`. It uses design tokens on `:root` with a `prefers-color-scheme: dark` override, plus a small set of utility classes (`.btn`, `.card`, `.panel`, `.field`, `.chips`, …). Reuse tokens and classes; don't add inline styles or hard-coded colours.
- Spacing comes from `gap` on grid/flex containers, not margins on children. Grid containers that can hold long text need `grid-template-columns: minmax(0, 1fr)`, otherwise long URLs overflow on mobile.
- User feedback goes through `useToast()`. Destructive actions confirm inline. Don't use `window.alert` or `window.confirm`.
- Icons come from `components/ui/Icon.tsx` (inline SVG paths). Add new ones there.
- Copy is written for listeners and feed owners, not developers: no "SPA", "cache TTL" or framework names in the UI.

## Known issues and findings

From a review in October 2026. Already fixed: `&` escaping in channel title/description, `itunes:new-feed-url` leaking, Enter in the search box submitting the form, mobile horizontal overflow, the session-expiry JSON parse error, and the full UI/UX rework.

Still open:

- **No stale-cache fallback.** If the source feed fails after the cache expires, `/filtered/...` returns a 500 instead of the last good XML. Every request after expiry refetches synchronously, so concurrent requests stampede.
- **Duration parsing** fails on fractional values (`3600.0`, `1:02:03.5`).
- **Saving a feed** re-downloads the whole source feed just to find its artwork (up to 15 s). The artwork from the directory search result is discarded.
- **SSRF surface:** `sourceUrl` can be any URL, including internal addresses. Only admins can set it, but it's worth restricting to public http(s) hosts.
- **`CreateAdminUserCommand`** takes the password as a CLI argument, so it ends up in shell history. Prefer an interactive prompt.
- **Every feed is public.** There is no draft/published flag.
- **Unused config:** `compose.yaml` and `compose.override.yaml` define a Postgres service from the Doctrine recipe, but the app uses SQLite.
- **Unknown URLs** outside the SPA routes get Symfony's 404 page, not the SPA's "Page not found" screen.

## Production readiness

Gaps to close before public deployment, roughly in priority order:

1. **Tests.** There are none. Start with `FeedFilterService` using fixture RSS files: rule combinations, duration formats, entity escaping, `new-feed-url` stripping.
2. **Serve stale cache on upstream errors**, and log fetch failures.
3. **Login throttling** (`login_throttling` in `security.yaml`).
4. **Secrets:** set a real `APP_SECRET` via env or Symfony secrets. `.env` only has a placeholder.
5. **Deployment:** no Dockerfile or prod config yet. Build the SPA in CI, set `APP_ENV=prod`, and persist `var/` (the SQLite DB) on a volume.
6. **Background refresh:** move feed fetching to a scheduled job (Symfony Messenger/Scheduler), so requests never wait on upstream.
7. **Product:** episode preview in the editor, draft/published flag, more rule types.
