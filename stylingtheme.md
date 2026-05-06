# Flux UI — Project Styling Guide

A reference for configuring and maintaining a consistent black-and-white Flux UI design system across this Laravel + Livewire project.

---

## 1. Installation & CSS Entry Point

`resources/css/app.css` is the single source of truth for all styling config. The minimum required setup:

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';

/* Required for Flux dark mode (.dark class strategy) */
@custom-variant dark (&:where(.dark, .dark *));
```

> Flux v2 requires **Tailwind CSS v4.2 or later**. There is no `tailwind.config.js` for dark mode — everything lives in CSS.

---

## 2. Typography

### Font Family — Inter

Flux recommends **Inter** as the UI font. Load it via Bunny Fonts (GDPR-safe Google Fonts mirror) in your layout `<head>`:

```html
<head>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
</head>
```

Register it as the default sans font in `app.css`:

```css
@theme {
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
}
```

### Type Scale

Use Tailwind's default type scale. Recommended conventions for this project:

| Element        | Class                          | Weight     |
|----------------|--------------------------------|------------|
| Page heading   | `text-2xl` / `text-3xl`        | `font-bold` |
| Section title  | `text-xl`                      | `font-semibold` |
| Body text      | `text-sm` / `text-base`        | `font-normal` |
| Label / caption| `text-xs`                      | `font-medium` |
| Code           | `font-mono text-sm`            | `font-normal` |

### Prose (rich text blocks)

For article-style content, use `@tailwindcss/typography`:

```html
<article class="prose dark:prose-invert max-w-none">
  <!-- content -->
</article>
```

`prose-invert` automatically flips all typography colors for dark mode — no manual overrides needed.

---

## 3. Black & White Theme

This project uses a **monochrome accent** — no colour, just zinc grays with black as the primary interactive colour. This gives a clean, editorial feel in both modes.

### `app.css` — Full Theme Block

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';

/* Required for Flux dark mode */
@custom-variant dark (&:where(.dark, .dark *));

/* ─── Typography ───────────────────────────── */
@theme {
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
}

/* ─── B&W Accent (light mode) ──────────────── */
@theme {
  --color-accent:            var(--color-zinc-900);
  --color-accent-content:    var(--color-zinc-700);
  --color-accent-foreground: var(--color-white);
}

/* ─── B&W Accent (dark mode) ───────────────── */
@layer theme {
  .dark {
    --color-accent:            var(--color-zinc-100);
    --color-accent-content:    var(--color-zinc-300);
    --color-accent-foreground: var(--color-zinc-900);
  }
}
```

**What this does:**
- **Light mode** — primary buttons/actions are near-black (`zinc-900`) with white text
- **Dark mode** — primary buttons/actions flip to near-white (`zinc-100`) with dark text
- Flux's adaptive variables handle this automatically — no `dark:bg-*` needed per-component

### Base Color

Flux defaults to `zinc` as the base gray. Keep it. It's the cleanest neutral for a b/w system:

```css
/* No override needed — zinc is the Flux default */
/* If you ever switch to neutral/slate, add this: */

/* @theme {
  --color-zinc-*: var(--color-neutral-*);
} */
```

---

## 4. Layout File Setup

In your root Blade layout (`resources/views/components/layouts/app.blade.php`), include these two Flux directives:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name') }}</title>

  <!-- Inter Font -->
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <!-- Flux: handles .dark class on <html> based on user preference / system -->
  @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased">
  {{ $slot }}

  @fluxScripts
</body>
</html>
```

> Remove `@fluxAppearance` only if you want to control the `.dark` class yourself manually.

---

## 5. Dark Mode Toggle Component

Flux ships a built-in appearance toggle. Drop it anywhere in your UI:

```blade
{{-- Simple 3-way toggle: light / dark / system --}}
<flux:dropdown x-data align="end">
  <flux:button variant="subtle" square aria-label="Toggle theme">
    <flux:icon.sun    x-show="$flux.appearance === 'light'"  variant="mini" />
    <flux:icon.moon   x-show="$flux.appearance === 'dark'"   variant="mini" />
    <flux:icon.computer-desktop x-show="$flux.appearance === 'system'" variant="mini" />
  </flux:button>

  <flux:menu>
    <flux:menu.item icon="sun"              @click="$flux.appearance = 'light'">Light</flux:menu.item>
    <flux:menu.item icon="moon"             @click="$flux.appearance = 'dark'">Dark</flux:menu.item>
    <flux:menu.item icon="computer-desktop" @click="$flux.appearance = 'system'">System</flux:menu.item>
  </flux:menu>
</flux:dropdown>
```

---

## 6. Component Usage Conventions

### Buttons

```blade
{{-- Primary — uses accent (black/white adaptive) --}}
<flux:button variant="primary">Save changes</flux:button>

{{-- Secondary — zinc bordered --}}
<flux:button variant="outline">Cancel</flux:button>

{{-- Ghost — no background --}}
<flux:button variant="subtle">View details</flux:button>

{{-- Danger --}}
<flux:button variant="danger">Delete</flux:button>
```

### Inputs

```blade
<flux:field>
  <flux:label>Email address</flux:label>
  <flux:input type="email" wire:model="email" placeholder="you@example.com" />
  <flux:error name="email" />
</flux:field>
```

### Cards / Panels

Flux doesn't ship a `card` component — use Tailwind utilities directly, consistent with Flux's zinc palette:

```blade
<div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 shadow-sm">
  <!-- card content -->
</div>
```

### Modals

```blade
<flux:modal name="confirm-delete" class="max-w-sm">
  <flux:heading>Delete record?</flux:heading>
  <flux:text>This action cannot be undone.</flux:text>

  <div class="flex gap-2 mt-4">
    <flux:button variant="danger" wire:click="delete">Delete</flux:button>
    <flux:button variant="outline" x-on:click="$flux.modal('confirm-delete').close()">Cancel</flux:button>
  </div>
</flux:modal>
```

### Navigation

```blade
<flux:navlist>
  <flux:navlist.item href="/dashboard" icon="home"    :current="request()->is('dashboard')">Dashboard</flux:navlist.item>
  <flux:navlist.item href="/settings"  icon="cog-6-tooth" :current="request()->is('settings*')">Settings</flux:navlist.item>
</flux:navlist>
```

To strip accent colour from nav items (pure zinc/gray style):

```blade
<flux:navlist.item :accent="false" href="/about">About</flux:navlist.item>
```

---

## 7. Global Component Overrides

To override a Flux component's style globally (without publishing its Blade file), target its `data-flux-*` attribute:

```css
/* Make all buttons slightly more rounded */
[data-flux-button] {
  @apply rounded-lg;
}

/* Force black button in light, white in dark — explicit override */
[data-flux-button][data-variant="primary"] {
  @apply bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900
         hover:bg-zinc-700 dark:hover:bg-zinc-300;
}
```

Place these overrides in `app.css` after the Flux import.

---

## 8. Quick Reference

| Token | Value |
|---|---|
| Base font | Inter 400/500/600 |
| Base color (gray) | `zinc` |
| Accent light | `zinc-900` (near-black) |
| Accent dark | `zinc-100` (near-white) |
| Foreground light | `white` |
| Foreground dark | `zinc-900` |
| Body bg light | `white` |
| Body bg dark | `zinc-950` |
| Border light | `zinc-200` |
| Border dark | `zinc-800` |
| Dark mode strategy | `.dark` class on `<html>` (selector) |
| Tailwind version | v4.2+ |
| Flux version | v2.x |
