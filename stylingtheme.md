# style.md — Governance Intelligence UI System
> Shadcn/ui-inspired · sizing verified against source · works for new and existing projects

---

## How to read this guide

Each step is split into two tracks:

- **New project** — set it up from scratch, no existing styles to worry about
- **Existing project** — audit what's already there first, then migrate incrementally without breaking live pages

Always finish one step before starting the next. Never do a bulk find-and-replace across the whole codebase in one pass.

---

## Step 1 — Design Tokens

Tokens are the single source of truth for every color, radius, shadow, and layout value. No component should ever hard-code a hex value.

### New project

Paste this block at the very top of `public/assets/css/app.css`, before any other rules:

```css
:root {
  /* Backgrounds */
  --bg:           #ffffff;
  --bg-card:      #ffffff;
  --bg-sidebar:   #f6f6f7;
  --bg-muted:     #f4f4f5;
  --bg-popover:   #ffffff;

  /* Borders */
  --border:       #e4e4e7;
  --border-input: #e4e4e7;
  --ring:         #09090b;

  /* Text */
  --text:         #09090b;
  --text-muted:   #71717a;
  --text-dim:     #52525b;

  /* Primary action */
  --accent:       #09090b;
  --accent-fg:    #ffffff;
  --accent-dim:   #27272a;

  /* Semantic colors — always in bg/fg pairs */
  --success:      #16a34a;
  --success-bg:   #f0fdf4;
  --warning:      #ca8a04;
  --warning-bg:   #fefce8;
  --danger:       #dc2626;
  --danger-bg:    #fef2f2;
  --info:         #3b82f6;
  --info-bg:      #eff6ff;

  /* Shape */
  --radius:       0.625rem;  /* 10px  — cards, modals */
  --radius-sm:    0.375rem;  /* 6px   — buttons, inputs (rounded-md) */
  --radius-lg:    0.875rem;  /* 14px  — large surfaces */
  --radius-full:  9999px;    /* pill  — badges only */

  /* Depth */
  --shadow:    0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
  --shadow-lg: 0 8px 24px rgba(0,0,0,0.08);

  /* Layout */
  --sidebar-w: 240px;
  --topbar-h:  64px;
}
```

### Existing project

1. Open `public/assets/css/app.css` and search for any existing `:root { }` block.
2. **If one exists:** add only the missing tokens into it — do not duplicate or overwrite values that are already there and working.
3. **Tokens to add first** (these are new and won't conflict): `--bg-muted`, `--bg-popover`, `--border-input`, `--ring`, all `*-bg` semantic pairs, `--radius-full`, `--shadow-md`, `--shadow-lg`.
4. **Tokens to update carefully** (check usages before changing): `--radius`, `--radius-sm` — search the codebase for every place they're used and confirm the visual change is acceptable before saving.
5. **Do not rename** existing tokens that components already reference. Add the new name alongside the old one, then migrate references page by page.

> **Conflict check:** If the project already uses `--accent` for something other than the primary action color, rename the incoming token (e.g. `--primary`) to avoid a silent override.

---

## Step 2 — Typography

### New project

```css
body {
  font-family: 'Inter', 'Roboto', sans-serif;
  font-size:   0.875rem;  /* 14px — shadcn base */
  line-height: 1.5;
  color:       var(--text);
  background:  var(--bg);
  -webkit-font-smoothing: antialiased;
}

h1 { font-size: 1.5rem;    font-weight: 700; letter-spacing: -0.01em; }
h2 { font-size: 1.25rem;   font-weight: 600; }
h3 { font-size: 1.0625rem; font-weight: 600; }
h4 { font-size: 0.9375rem; font-weight: 500; }
```

### Existing project

1. Check which font is currently loaded in `<head>`. If `Roboto` is already there and working, don't remove it — just prepend `Inter` to the `font-family` stack so it becomes the preferred face when available.
2. Search `app.css` for any hard-coded `font-size: 16px` on `body`. Switching to `0.875rem` will visually shrink all text — do this intentionally on a staging page, not as a side effect.
3. If heading sizes are already defined, compare them to this scale before overwriting. Reduce differences gradually if the gap is large.
4. Note any page-level `<style>` blocks that override `font-family` — schedule them for removal once the global stack is confirmed stable (see Phase 4 of the migration checklist).

---

## Step 3 — Buttons

Shadcn's verified size scale:

| Variant | Height   | Padding X | px height |
|---------|----------|-----------|-----------|
| sm      | `h-9`    | `px-3`    | 36px      |
| default | `h-10`   | `px-4`    | 40px      |
| lg      | `h-11`   | `px-8`    | 44px      |
| icon    | `size-9` | —         | 36×36px   |

> Height must be set explicitly. Padding-only sizing drifts off the scale.

### New project

```css
/* Base — shared by all variants */
.btn {
  display:         inline-flex;
  align-items:     center;
  justify-content: center;
  gap:             0.375rem;
  height:          2.5rem;           /* 40px — h-10 */
  padding:         0 1rem;           /* px-4 */
  border-radius:   var(--radius-sm); /* 6px — rounded-md */
  font-size:       0.875rem;
  font-weight:     500;
  line-height:     1;
  cursor:          pointer;          /* explicit — Tailwind v4 defaults to 'default' */
  border:          1px solid transparent;
  white-space:     nowrap;
  text-decoration: none;
  transition:      background 120ms ease, opacity 120ms ease, box-shadow 120ms ease;
  user-select:     none;
}

.btn:focus-visible {
  outline:        2px solid var(--ring);
  outline-offset: 2px;
}

.btn:disabled { opacity: 0.5; pointer-events: none; }

/* Variants */
.btn-primary   { background: var(--accent);    color: var(--accent-fg); border-color: var(--accent); }
.btn-primary:hover  { background: var(--accent-dim); border-color: var(--accent-dim); }

.btn-secondary { background: var(--bg-muted);  color: var(--text); border-color: var(--border); }
.btn-secondary:hover { background: var(--border); }

.btn-outline   { background: var(--bg);        color: var(--text); border-color: var(--border-input); }
.btn-outline:hover   { background: var(--bg-muted); }

.btn-ghost     { background: transparent;      color: var(--text); border-color: transparent; }
.btn-ghost:hover     { background: var(--bg-muted); }

.btn-danger    { background: var(--danger);    color: #ffffff; border-color: var(--danger); }
.btn-danger:hover    { background: #b91c1c; }

/* Sizes */
.btn-sm   { height: 2.25rem; padding: 0 0.75rem; font-size: 0.8125rem; } /* 36px — h-9  */
.btn-lg   { height: 2.75rem; padding: 0 2rem;    font-size: 1rem;      } /* 44px — h-11 */
.btn-icon { height: 2.25rem; width:   2.25rem;   padding: 0;           } /* 36×36px      */
```

### Existing project

1. **Audit first.** Search for `.btn`, `.button`, `.btn-primary` and any other button class names in use. List every one found across `app.css` and any page-level `<style>` blocks.
2. **Do not delete old classes yet.** Add the new `.btn` base and variant classes alongside them in `app.css`.
3. For any existing button that uses padding-only sizing, add `height: 2.5rem` directly to that existing class. This is a targeted fix that doesn't touch anything else.
4. Replace hard-coded `background` colors with token references one class at a time. Visual check in the browser after each change.
5. Migrate HTML to the new class names in a separate pass, template by template, after the CSS is confirmed stable.
6. Remove old button classes only once no HTML references them — search the entire `views/` directory before deleting.

---

## Step 4 — Cards

Shadcn card anatomy: `card > card-header > [card-title + card-description]`, `card-content`, `card-footer`.

### New project

```css
.card             { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.card-header      { padding: 1.25rem 1.5rem 0; }
.card-title       { font-size: 0.9375rem; font-weight: 600; color: var(--text); }
.card-description { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.125rem; }
.card-content     { padding: 1rem 1.5rem; }
.card-footer      { padding: 0 1.5rem 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
```

### Existing project

1. Search for existing card patterns — common names: `.card`, `.panel`, `.box`, `.widget`.
2. If an existing `.card` already has most of the right styles, add only the missing properties (`box-shadow`, `overflow: hidden`) rather than replacing the whole block.
3. Add the sub-element classes (`.card-header`, `.card-content`, `.card-footer`) as new rules — they won't conflict with anything unless the markup already uses those exact class names.
4. Migrate one template file to the `card-header / card-content / card-footer` markup structure first. Verify visually before rolling it out to all card usages.

---

## Step 5 — Form Inputs

Shadcn pins `input` height at `h-9` = 36px. Padding-only sizing causes misalignment when inputs sit next to buttons in the same row.

### New project

```css
.input,
.textarea,
.select {
  display:       block;
  width:         100%;
  height:        2.25rem;           /* 36px — h-9 */
  padding:       0 0.75rem;
  font-size:     0.875rem;
  line-height:   1.5;
  color:         var(--text);
  background:    var(--bg);
  border:        1px solid var(--border-input);
  border-radius: var(--radius-sm);
  transition:    border-color 120ms ease, box-shadow 120ms ease;
  outline:       none;
}

.textarea {
  height:         auto;
  min-height:     5rem;
  padding-top:    0.5rem;
  padding-bottom: 0.5rem;
  resize:         vertical;
}

.input::placeholder,
.textarea::placeholder { color: var(--text-muted); }

/* Double ring — matches shadcn's ring-2 ring-offset-2 */
.input:focus,
.textarea:focus,
.select:focus {
  border-color: var(--ring);
  box-shadow:   0 0 0 2px var(--bg), 0 0 0 4px var(--ring);
}

.form-label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--text); margin-bottom: 0.375rem; }
.form-hint  { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.25rem; }
.form-error { font-size: 0.8125rem; color: var(--danger);     margin-top: 0.25rem; }
```

### Existing project

1. Search for existing `input`, `textarea`, `select` styles. Note whether they use `height`, `padding`, or both.
2. If inputs currently rely on padding alone (e.g. `padding: 8px 12px`), add `height: 2.25rem` alongside — safe if the existing padding is already close to the right size.
3. Check every form page for inputs sitting next to buttons in the same row. If they look misaligned after the height pin, the button also needs its height set (Step 3). Fix them together.
4. Replace hard-coded border colors with `var(--border-input)` one form at a time.
5. Update focus styles last — the double-ring pattern is purely additive and won't break anything.

---

## Step 6 — Badges

Shadcn badge: fixed `h-5` (20px), `text-xs` (12px), `rounded-full` (pill). Not a rounded rectangle.

| Property      | Shadcn value      | Common mistake           |
|---------------|-------------------|--------------------------|
| height        | 20px fixed        | padding-only, grows      |
| border-radius | `9999px` pill     | `6px` rounded rectangle  |
| font-size     | `0.75rem` (xs)    | `0.875rem` (sm)          |
| font-weight   | `500` medium      | `600` semibold           |

### New project

```css
.badge {
  display:       inline-flex;
  align-items:   center;
  gap:           0.25rem;
  height:        1.25rem;            /* 20px — h-5 */
  padding:       0 0.5rem;           /* px-2 */
  font-size:     0.75rem;            /* text-xs */
  font-weight:   500;
  border-radius: var(--radius-full); /* pill */
  border:        1px solid transparent;
  line-height:   1;
  white-space:   nowrap;
}

.badge-default { background: var(--bg-muted);   color: var(--text-dim); border-color: var(--border); }
.badge-success { background: var(--success-bg); color: var(--success);  border-color: #bbf7d0; }
.badge-warning { background: var(--warning-bg); color: var(--warning);  border-color: #fde68a; }
.badge-danger  { background: var(--danger-bg);  color: var(--danger);   border-color: #fecaca; }
.badge-info    { background: var(--info-bg);    color: var(--info);     border-color: #bfdbfe; }
```

> **Badge vs chip:** A badge is a read-only status label (20px, pill). A chip is interactive or has a close button (taller, `rounded-md`). Don't use badge markup for chips.

### Existing project

1. Search for existing badge/tag/chip classes. Note their current `border-radius` and whether they're used as read-only labels or interactive filters.
2. **If the existing badge is a rounded rectangle** (6px radius): changing to `border-radius: var(--radius-full)` affects every badge site-wide — do this deliberately. Check tables, cards, and headers.
3. **If `height` is not set:** add `height: 1.25rem` to pin it. Verify nothing clips on any badge-heavy page.
4. **If `font-size` is currently `0.875rem`:** dropping to `0.75rem` makes badges smaller — legibility-check on dense tables before confirming.
5. Do not migrate the per-variant border colors until the semantic `*-bg` token pairs from Step 1 are in place.

---

## Step 7 — Tables

### New project

```css
.table-wrapper { border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }

table                  { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead                  { background: var(--bg-muted); }
thead th               { padding: 0.625rem 1rem; text-align: left; font-weight: 500; font-size: 0.8125rem; color: var(--text-muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
tbody td               { padding: 0.75rem 1rem; color: var(--text); border-bottom: 1px solid var(--border); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td      { background: #fafafa; }
```

### Existing project

1. Check if tables currently use `border-collapse: separate`. Switching to `collapse` removes all `border-spacing` — verify the change doesn't break any table relying on that gap.
2. Add `.table-wrapper` as a new `<div>` wrapper in templates. It won't affect existing tables until the class is applied.
3. Replace hard-coded `border-color` on `th` and `td` with `var(--border)` one table at a time.
4. If a table already has a row hover background, only replace it after confirming `#fafafa` suits the context — it's very subtle and may not have enough contrast on non-white backgrounds.

---

## Step 8 — Alerts

### New project

```css
.alert             { display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.875rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-muted); font-size: 0.875rem; color: var(--text); }
.alert-success     { background: var(--success-bg); border-color: #bbf7d0; color: #14532d; }
.alert-warning     { background: var(--warning-bg); border-color: #fde68a; color: #713f12; }
.alert-danger      { background: var(--danger-bg);  border-color: #fecaca; color: #7f1d1d; }
.alert-info        { background: var(--info-bg);    border-color: #bfdbfe; color: #1e3a5f; }
.alert-icon        { flex-shrink: 0; margin-top: 1px; }
.alert-title       { font-weight: 600; margin-bottom: 0.125rem; }
.alert-description { font-size: 0.8125rem; }
```

### Existing project

1. Locate existing alert/notification/flash styles — often named `.alert`, `.flash`, `.notification`, `.banner`.
2. If the existing danger alert uses a solid red background, switching to the tinted `--danger-bg` pattern is a visible design change. Preview it in context before applying globally.
3. `.alert-title` and `.alert-description` are new classes — add them freely, they won't conflict with anything until applied to markup.

---

## Step 9 — Sidebar

> The sidebar is the riskiest migration target because it affects every page. Do it last.

### New project

```css
/* Shell */
.layout       { display: grid; grid-template-columns: var(--sidebar-w) 1fr; grid-template-rows: var(--topbar-h) 1fr; min-height: 100vh; }
.topbar       { grid-column: 2; height: var(--topbar-h); display: flex; align-items: center; padding: 0 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg); }
.sidebar      { grid-row: 1 / -1; width: var(--sidebar-w); background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 1rem 0.75rem; overflow-y: auto; }
.main-content { padding: 1.5rem; overflow-y: auto; }

/* Nav */
.nav-label { font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.5rem; margin: 1rem 0 0.25rem; }
.nav-item  { display: flex; align-items: center; gap: 0.5rem; padding: 0.4375rem 0.625rem; border-radius: var(--radius-sm); font-size: 0.875rem; color: var(--text-dim); text-decoration: none; transition: background 120ms ease, color 120ms ease; cursor: pointer; user-select: none; }
.nav-item:hover  { background: var(--border); color: var(--text); }
.nav-item.active { font-weight: 600; color: var(--text); background: transparent; } /* weight only — no filled pill */

/* Submenu */
.nav-chevron      { width: 14px; height: 14px; color: var(--text-muted); transition: transform 150ms ease; flex-shrink: 0; margin-left: auto; }
.nav-chevron.open { transform: rotate(90deg); }
.nav-submenu      { display: none; padding-left: 1.25rem; margin-top: 0.125rem; border-left: 1px solid var(--border); margin-left: 0.875rem; }
.nav-submenu.open { display: block; }
.nav-submenu .nav-item { font-size: 0.8125rem; padding: 0.375rem 0.5rem; }
```

### Existing project

1. Audit `views/layouts/main.php`. Note what CSS currently controls sidebar width, background, and nav item states.
2. **Colors first, layout last.** Update `background`, `border-right`, and nav item colors to use tokens. This is safe and non-breaking.
3. If the existing layout uses `position: fixed` or `flexbox` instead of `grid`, do not switch layout mechanisms in the same change. Update colors and nav item styles first, then address the layout separately.
4. The active nav item rule — **no filled dark background, weight only** — is a deliberate design decision. Remove the fill when you're intentionally aligning to this system, not as a side effect of a refactor.
5. List all inline `<style>` blocks in view files that override sidebar styles. Schedule them for removal once the global sidebar rules are confirmed stable.

---

## Step 10 — Interaction Rules

| Property           | Value                      | Notes                                          |
|--------------------|----------------------------|------------------------------------------------|
| Transition speed   | `120ms – 150ms`            | Never exceed 200ms for micro-interactions      |
| Easing             | `ease`                     | `ease-out` for elements entering the viewport  |
| Hover signal       | Background fill change     | Don't rely on color shift alone                |
| Focus ring         | 2px outline + 2px offset   | Matches shadcn `ring-2 ring-offset-2`          |
| Active nav item    | `font-weight: 600`, no fill| Weight alone carries the active state          |
| Button cursor      | `pointer` explicit         | Tailwind v4 defaults to `default`              |

For existing projects: search `app.css` for `transition`. Any hover or focus transition over `300ms` should be shortened to stay in the 120–150ms range.

---

## Step 11 — What Not To Do

- ❌ Hard-coded hex values in component styles — use tokens
- ❌ Gold or amber accents (`#b8860b`, `#d97706`)
- ❌ Dark global backgrounds (`#1a1a2e`, `#0f172a`) on the app shell
- ❌ Multiple conflicting font stacks on the same page
- ❌ Heavy drop shadows (`box-shadow: 0 20px 60px ...`)
- ❌ Stable rules in inline `<style>` blocks — migrate to `app.css`
- ❌ Filled dark background on the active sidebar nav item
- ❌ Mixing Tailwind utility classes with this token system on the same element
- ❌ Badge `border-radius: 6px` — badges are pills, not rounded rectangles
- ❌ Button or input height set by padding alone — always pin `height` explicitly
- ❌ Bulk find-and-replace across the whole codebase in one commit — one component at a time

---

## Step 12 — Checklists

### New project

- [ ] Token block added to top of `app.css`, including `--radius-full: 9999px`
- [ ] Font stack: `Inter, Roboto, sans-serif`
- [ ] All component blocks (buttons, cards, inputs, badges, tables, alerts, sidebar) added to `app.css`
- [ ] Button heights confirmed: default=40px, sm=36px, lg=44px
- [ ] Badges using `border-radius: var(--radius-full)` — pill shape
- [ ] Inputs height-pinned at 36px
- [ ] Sidebar markup from `views/layouts/main.php`, chevron JS from `public/assets/js/app.js`
- [ ] No hard-coded hex values in any component CSS

### Existing project — phased migration

Work through each phase in order. Check an item only when it's confirmed stable in the browser, not just when the CSS is written.

**Phase 1 — Tokens (safe to do any time, non-breaking)**
- [ ] New tokens added to existing `:root` block without removing old ones
- [ ] Semantic `*-bg` pairs added (`--success-bg`, `--danger-bg`, etc.)
- [ ] `--radius-full: 9999px` added
- [ ] No token renames that silently change existing component colors

**Phase 2 — Components (one at a time, visual check after each)**
- [ ] Buttons: `height` pinned, variants updated, old classes kept until HTML migrated
- [ ] Inputs: `height: 2.25rem` added, focus ring updated
- [ ] Badges: `border-radius` changed to pill, `height: 1.25rem` pinned — checked on all pages that show badges
- [ ] Cards: sub-element classes added, markup migrated in one template first
- [ ] Alerts: tinted background pattern applied, solid fills removed
- [ ] Tables: `border-collapse` confirmed, `.table-wrapper` div added

**Phase 3 — Layout (do last)**
- [ ] Sidebar token colors updated, nav item styles aligned
- [ ] Active nav item: filled background removed, weight-only rule confirmed
- [ ] Layout mechanism (grid/flex/fixed) evaluated — only changed if necessary
- [ ] Inline `<style>` blocks in view files inventoried

**Phase 4 — Cleanup**
- [ ] Old button/badge/card classes with no HTML references removed from `app.css`
- [ ] `views/dashboard/index.php` inline style block audited — stable rules moved to `app.css`
- [ ] All remaining hard-coded hex values in component CSS replaced with tokens
- [ ] Font stack conflicts resolved — single stack in `body`, no page-level overrides

---

## Appendix — Shadcn Size Reference (verified from source)

```
Button default  → height: 2.5rem  (40px) | padding: 0 1rem    | border-radius: 6px (rounded-md)
Button sm       → height: 2.25rem (36px) | padding: 0 0.75rem | border-radius: 6px
Button lg       → height: 2.75rem (44px) | padding: 0 2rem    | border-radius: 6px
Button icon     → 2.25rem × 2.25rem square (36px)

Badge           → height: 1.25rem (20px) | padding: 0 0.5rem  | border-radius: 9999px (pill) | font-size: 0.75rem
Input / Select  → height: 2.25rem (36px) | padding: 0 0.75rem | border-radius: 6px

Card            → border-radius: 0.625rem (10px)
Modal / Sheet   → border-radius: 0.875rem (14px)
```