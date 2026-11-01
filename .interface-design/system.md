# Pricore Design System

## Intent

**Who:** Developers and team leads managing private Composer packages. Technical people who visit periodically — not living in this tool all day, but expecting precision when they do.

**Task:** Connect repositories, monitor package syncs, control access through tokens, manage team members. An operations loop around package distribution.

**Feel:** Purposeful and precise, like well-organized infrastructure. Warm enough to feel crafted (not sterile), cool enough to feel trustworthy. The orange accent brings life to an otherwise restrained palette — like a status light on professional equipment.

---

## Typography

**Typeface:** Inter (loaded from rsms.me CDN) — a purpose-built screen typeface optimized for legibility at small sizes. Variable font with `InterVariable` for smooth weight interpolation.

**Root font size:** `13px` — dense, tool-like spacing appropriate for developer infrastructure. All spacing (`p-*`, `gap-*`, `h-*`) scales from this root.

**Type floor:** the small sizes are pinned in `@theme` (`resources/css/app.css`) so they don't shrink with the dense root. Nothing readable goes below 12px.

| Role | Class | Computed | Usage |
|------|-------|----------|-------|
| Package/page hero title | `text-3xl font-semibold tracking-tight` | ~22.75px | Package page header |
| Page title | `text-2xl font-semibold tracking-tight` | 19.5px | Dashboard org name |
| Page title (settings/lists) | `text-xl font-medium` (`HeadingSmall`) | ~16.25px | List pages, sections |
| Section heading | `text-lg font-semibold tracking-tight` | ~14.6px | Dashboard sections |
| Body | inherited (no class) | 13px | All UI text, descriptions, labels |
| `text-sm` | pinned | 13px / 20px | Same size as body — use for explicitness, not to shrink |
| `text-xs` | pinned | 12px / 16px | Meta, badges, column headers, timestamps |
| Metric value | `text-2xl`–`text-3xl font-semibold tabular-nums` | 19.5–22.75px | Downloads, counts |
| Code/versions | `font-mono` | 13px | Versions, commit hashes, package names in lists |

**Exceptions below 12px (deliberate):** sidebar nav labels `text-[10px]` (kept compact on purpose), release version `text-[10.5px]`, `⌘K` hint `text-[11px]`.

**README / prose:** `prose` (not `prose-sm`) with `prose-code:text-[0.923em] prose-pre:text-xs prose-table:text-sm [&_pre_code]:text-[length:inherit]` — prose-sm compounds em shrinking and put code at 8px.

**Muted text:** `text-muted-foreground` — used for descriptions, timestamps, secondary information.

**Links:** Underline with `decoration-neutral-300 dark:decoration-neutral-500`, transitions to `decoration-current` on hover. Offset: `underline-offset-4`.

**Heading components:**
- `Heading` — page-level: `mb-8 space-y-0.5`, title + optional description
- `HeadingSmall` — section-level: `mt-0.5` gap between title and description

---

## Color

All colors use **OKLCH color space** for perceptual uniformity.

### Light Mode

| Token | Value | Role |
|-------|-------|------|
| `background` | `oklch(0.995 0.002 90)` | Page background, near-white with warm tint |
| `foreground` | `oklch(0.18 0.01 260)` | Primary text, very dark blue-gray |
| `card` | `oklch(1 0 0)` | Card surfaces, pure white |
| `primary` | `oklch(0.69 0.2 37)` | Orange accent — brand color |
| `primary-foreground` | `oklch(0.99 0 0)` | Text on primary |
| `secondary` | `oklch(0.97 0.003 90)` | Subtle background (near-neutral; a 0.008 warm tint read as beige and was reverted) |
| `secondary-foreground` | `oklch(0.25 0.01 260)` | Text on secondary |
| `muted` | `oklch(0.965 0.003 90)` | Muted backgrounds |
| `muted-foreground` | `oklch(0.5 0.01 260)` | Secondary text, 50% weight |
| `accent` | `oklch(0.96 0.003 90)` | Hover states |
| `surface-inset` | `oklch(0.975 0.003 90)` | Inset surfaces: install bar, table headers, icon tiles, segmented controls |
| `destructive` | `oklch(0.55 0.22 25)` | Error/danger actions |
| `border` | `oklch(0.93 0.003 90)` | Default borders |
| `input` | `oklch(0.93 0.003 90)` | Input borders (matches border) |
| `ring` | `oklch(0.88 0.005 90)` | Focus rings |

### Dark Mode

| Token | Value | Role |
|-------|-------|------|
| `background` | `oklch(0.13 0.01 260)` | Dark page, blue-gray undertone |
| `foreground` | `oklch(0.95 0 0)` | Primary text, near-white |
| `card` | `oklch(0.16 0.01 260)` | Elevated surface |
| `primary` | `oklch(0.69 0.2 37)` | Orange accent (same as light) |
| `primary-foreground` | `oklch(0.99 0 0)` | Text on primary |
| `muted` | `oklch(0.22 0.01 260)` | Muted backgrounds |
| `muted-foreground` | `oklch(0.65 0 0)` | Secondary text |
| `destructive` | `oklch(0.45 0.18 25)` | Darker red for dark backgrounds |
| `border` | `oklch(0.26 0.01 260)` | Subtle borders |
| `ring` | `oklch(0.42 0.01 260)` | Focus rings |

### Sidebar (both modes have dedicated palette)

The sidebar is always dark, creating a navigation "frame" around content.

| Token | Light | Dark |
|-------|-------|------|
| `sidebar` | `oklch(0.14 0.01 260)` | `oklch(0.11 0.01 260)` |
| `sidebar-foreground` | `oklch(0.96 0 0)` | `oklch(0.95 0 0)` |
| `sidebar-accent` | `oklch(0.24 0.015 260)` | `oklch(0.22 0.01 260)` |
| `sidebar-border` | `oklch(0.26 0.015 260)` | `oklch(0.24 0.01 260)` |

### Semantic Colors (direct Tailwind)

| Meaning | Light | Dark |
|---------|-------|------|
| Success | `text-emerald-600`, `bg-emerald-100` | `text-emerald-400`, `bg-emerald-900/30` |
| Warning | `text-amber-600` | `text-amber-400` |
| Danger | `text-red-600`, `bg-red-100` | `text-red-400`, `bg-red-900/30` |
| Positive delta | `text-green-600` | `text-green-400` |

### Chart Colors

Five distinct colors for data visualization, different palettes per mode:
- Light: warm hues (amber → teal → indigo → gold → orange)
- Dark: cooler saturated hues (indigo → teal → gold → purple → red)

---

## Spacing

**Base unit:** 4px (Tailwind default)

**Key patterns:**
| Context | Value | Class |
|---------|-------|-------|
| Card internal padding | 24px | `px-6 py-6` (Card has `py-6`, Header/Content/Footer have `px-6`) |
| Card internal gap | 24px | `gap-6` (between Card children) |
| Card header gap | 6px | `gap-1.5` (between title and description) |
| Grid gap | 16px | `gap-4` |
| Grid gap (large) | 24px | `gap-6` |
| Page heading margin | 32px bottom | `mb-8` |
| Form field spacing | 24px | `space-y-6` |
| List item spacing | 12px | `space-y-3` |
| Content max width | 80rem | `max-w-7xl` |
| Content padding | 24px | `p-6` |
| Sidebar nav gap | 4px | `gap-1` |
| Nav item padding | `px-2 py-2.5` | Compact icon+label nav |

---

## Depth

**Strategy:** Flat surfaces defined by borders, not shadows. Cards have no shadows — depth comes from border contrast and background differentiation. Shadows are reserved for floating/overlay elements only.

**Shadow scale:**
| Token | Value | Usage |
|-------|-------|-------|
| `shadow-xs` | `0 1px 2px 0 rgb(0 0 0 / 0.03)` | Inputs |
| `shadow-lg` | `0 10px 15px, 0 4px 6px (0.05)` | Dialogs, overlays |

**Depth behavior:**
- Cards: No shadow — defined by `border` only
- Dialogs: `shadow-lg`
- Buttons: gradient variants create inherent depth

---

## Radius

**Base:** `0.5rem` (8px)

| Token | Value | Usage |
|-------|-------|-------|
| `radius-lg` | `0.5rem` | Cards, dialogs, inputs, buttons |
| `radius-md` | `calc(0.5rem - 2px)` = 6px | Inner elements |
| `radius-sm` | `calc(0.5rem - 4px)` = 4px | Small nested elements |
| Full | `rounded-full` | Badges, avatars, progress bars |
| Extra large | `rounded-xl` | Empty states, info boxes |

---

## Surfaces

### Card

The primary container pattern. White on light, dark blue-gray on dark.

```
bg-card text-card-foreground
rounded-lg border
py-6 (vertical padding on card itself)
px-6 (horizontal padding on header/content/footer)
gap-6 (between card children)
```

**Interactive cards** add: `group cursor-pointer`

### Empty State

Dashed-border container for zero-data states.

```
rounded-xl border border-dashed bg-muted/20
px-6 py-16 text-center
```

Content: icon in `rounded-full bg-muted/50 p-4`, title `text-lg font-medium`, description `text-sm text-muted-foreground max-w-sm`, optional CTA button `mt-6`.

### Info Box

Informational callout with icon.

```
rounded-xl border bg-muted/30 dark:bg-muted/10 p-5
```

Layout: flex with `gap-3`, icon in `rounded-lg p-2`, text in `flex-1`.

### Dialog

Centered modal overlay.

```
Overlay: bg-black/80
Content: bg-background rounded-lg border p-6 shadow-lg gap-4
Max width: sm:max-w-lg
```

Animations: `fade-in-0`, `zoom-in-95` on open; reverse on close. Duration: 200ms.

---

## Components

### Button

**Signature element** — raised tactile buttons. Gradient down + thick bottom border = physically raised. Text shadow adds legibility on colored backgrounds.

**Tactile system:** Buttons are **raised** (gradient down, `border-b-2`). Inputs are **inset** (gradient up, `border-t-2`). This pairing creates a consistent physical language.

**Variants:**
| Variant | Style |
|---------|-------|
| `default` (primary) | Orange gradient down, `border border-b-2`, text shadow `rgba(0,0,0,0.2)`, white text |
| `secondary` | Gray gradient down, `border border-b-2`, `shadow-xs`, light text shadow (dark in dark mode) |
| `destructive` | Red gradient down, `border border-b-2`, text shadow, white text |
| `outline` | Transparent with border, hover fills with accent |
| `ghost` | No background, hover fills with accent |
| `link` | Underline, primary color |

**Sizes:**
| Size | Height | Padding |
|------|--------|---------|
| `default` | `h-9` | `px-4 py-2` |
| `sm` | `h-8` | `px-3` |
| `lg` | `h-10` | `px-6` |
| `icon` | `size-9` | — |

**Interactions:**
- Hover: gradient flattens to darker stop (`hover:from-button-*-to hover:to-button-*-to`), `bg-accent` (ghost/outline)
- Active: `scale-[0.98]` — micro-shrink on press
- Focus: `ring-2 ring-ring/50 ring-offset-2`
- Disabled: `opacity-50 pointer-events-none`

**Text shadow:** `[text-shadow:0_1px_0_rgba(0,0,0,0.2)]` for dark backgrounds, `[text-shadow:0_1px_0_rgba(255,255,255,0.6)]` for light (secondary).

**Icon sizing:** `[&_svg:not([class*='size-'])]:size-4` — auto-sizes unclassed SVGs to 16px.

### Badge

**Soft pill** — the house label style, used for every status/label. Tinted background, colored text, no border. Hand-rolled label spans should use `Badge` instead.

```
rounded-full px-2 py-px text-xs font-medium [&>svg]:size-3
```

| Variant | Style | Used for |
|---------|-------|----------|
| `default` | `bg-primary/10 text-primary` | "Latest", brand highlights |
| `secondary` | `bg-muted text-muted-foreground` | Neutral: Pending, roles |
| `destructive` | `bg-red-500/10 text-red-600` | Failed, vulnerabilities, critical |
| `success` | `bg-emerald-500/10 text-emerald-700` | OK, Synced, active |
| `warning` | `bg-amber-500/10 text-amber-700` | Medium severity (high = orange via className) |
| `info` | `bg-blue-500/10 text-blue-700` | Low severity |
| `outline` | `ring-1 ring-border ring-inset text-muted-foreground` | Rare neutral outline |

Dark mode bumps tints to `/15` and text to the `-400` shade.

**Not badges:** version chips (`rounded-md border bg-surface-inset font-mono`) and count pills in tabs (`rounded-full bg-muted px-1.5 text-xs`).

### Input

**Inset form input** — gradient up + thick top border creates a pressed/recessed feel, complementing the raised buttons.

```
h-10 rounded-lg border border-t-2 border-input px-3 py-2
bg-gradient-to-t from-white to-stone-50
dark:from-white/[0.02] dark:to-white/[0.06]
text-base md:text-sm shadow-xs
```

Focus: `border-ring ring-4 ring-ring/20` — wide, soft ring
Invalid: `border-destructive ring-destructive/20`
Selection: `bg-primary text-primary-foreground`

### Textarea

Same inset pattern as Input:
```
rounded-lg border border-t-2 border-input
bg-gradient-to-t from-white to-stone-50
dark:from-white/[0.02] dark:to-white/[0.06]
shadow-xs min-h-[80px]
```

Focus: `border-ring ring-4 ring-ring/20`

### Select Trigger

Same inset pattern as Input:
```
h-9 rounded-lg border border-t-2 border-input
bg-gradient-to-t from-white to-stone-50
dark:from-white/[0.02] dark:to-white/[0.06]
shadow-xs
```

Focus: `border-ring ring-4 ring-ring/20`

### Alert

Informational banner with grid layout for icon alignment.

```
rounded-lg border px-4 py-3 text-sm
grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr]
```

Variants: `default` (neutral), `destructive` (error).

### Table

Clean data table with hover rows.

| Part | Style |
|------|-------|
| Table | `w-full text-sm` |
| Header | `[&_tr]:border-b` |
| Head cell | `h-12 px-4 font-medium text-muted-foreground` |
| Body row | `border-b hover:bg-muted/50` |
| Body cell | `p-4` |
| Footer | `bg-muted/50 border-t` |

### Metric Strip (replaces Stat Card grids)

One bordered card split into cells instead of a row of tall stat cards. Used for the package Downloads tab and the repository header.

```
grid divide-y sm:grid-cols-3 sm:divide-x sm:divide-y-0 rounded-lg border bg-card
cell: px-5 py-4 — label text-muted-foreground, value text-2xl font-semibold tabular-nums
```

Trend inline next to value: `text-xs` with Trending icon, emerald up / red down. Show exact numbers (`toLocaleString`), not compact "2.8K".

**Vitals row** (repository header): same idea as `<dl>` under a header card — `px-6 py-4`, `dt text-xs text-muted-foreground`, `dd font-medium` with a `StatusDot`.

`StatCard` still exists but is no longer used on redesigned pages.

### Skeleton

Loading placeholder.

```
bg-primary/10 animate-pulse rounded-md
```

### Tooltip

Small contextual popup.

```
bg-primary text-primary-foreground
rounded-md px-3 py-1.5 text-xs
```

Arrow: `size-2.5 fill-primary rounded-[2px]`
Delay: `0ms` (instant).

### Progress Bar / Distribution Bar

Horizontal progress indicators.

```
Container: h-2 rounded-full bg-secondary
Fill: h-full transition-all [variant color]
```

Progress variants: `default` (primary), `success` (green-600), `warning` (yellow-600), `danger` (red-600).

---

## Layout

### App Shell

Two layout modes:
1. **Sidebar layout** (primary) — narrow icon sidebar + content area
2. **Header layout** — top navigation bar + content

### Sidebar

Narrow, always-dark sidebar with icon+label navigation.

```
Width: narrow (icon-based)
Header: h-16, centered logo, border-b
Footer: border-t, user menu
Nav items: flex-col items-center gap-1, icon size-5, label text-[10px] (intentionally compact)
```

Active state: `bg-sidebar-accent font-medium text-sidebar-accent-foreground`
Inactive: `text-sidebar-foreground/70`
Hover: `bg-sidebar-accent text-sidebar-accent-foreground`

### Content Area

```
mx-auto max-w-7xl flex-1 flex-col gap-4 rounded-xl
```

### Page Header (Header layout)

```
Border: border-b border-sidebar-border/80
Height: h-16
Content: mx-auto max-w-7xl px-4
```

Active nav indicator: `absolute bottom-0 h-0.5 w-full bg-black dark:bg-white`

### Breadcrumbs

```
Container: border-b border-sidebar-border/70, h-12
Text: text-neutral-500
```

### Responsive Grid

```
sm:grid-cols-2 lg:grid-cols-3 gap-4
```

For activity feeds: `lg:grid-cols-2 gap-6`

---

## Interaction

### Transitions

**Default duration:** `150ms` — fast, responsive.
**Shadow transitions:** `200ms` — slightly softer for depth changes.

### Hover Patterns

- **Card title text:** `group-hover:text-primary` — color hint
- **Arrow icons:** `group-hover:translate-x-0.5 group-hover:-translate-y-0.5` — directional nudge
- **Icon containers:** `group-hover:bg-muted` — background fills
- **Links:** underline decoration transitions to current color
- **Buttons:** gradient flattens on hover (gradient variants) or `bg-accent` (flat)

### Active/Press

- `active:scale-[0.98]` — micro-shrink on press

### Focus

- **Buttons:** `focus-visible:ring-2 ring-ring/50 ring-offset-2`
- **Inputs/Textarea/Select:** `focus-visible:ring-4 ring-ring/20` — wider, softer ring
- Destructive contexts: `ring-destructive/20 dark:ring-destructive/40`

---

## Icons

**Library:** Lucide React

**Sizing convention:**
| Context | Size |
|---------|------|
| Button icons (auto) | `size-4` (16px) |
| Sidebar nav | `size-5` (20px), `strokeWidth={1.75}` |
| Inline with text | `h-3.5 w-3.5` or `h-4 w-4` |
| Empty state hero | `h-8 w-8` |
| Header actions | `!size-5` |
| Badge icons | `size-3` (12px) |
| Stat card icons | `h-4 w-4` in `rounded-md bg-muted/50 p-1.5` container |

**Style:** `text-muted-foreground` by default. Interaction surfaces use `opacity-80 group-hover:opacity-100`.

---

## Animation

**Library:** `tw-animate-css`

**Dialog animations:**
- Enter: `animate-in fade-in-0 zoom-in-95`
- Exit: `animate-out fade-out-0 zoom-out-95`
- Duration: `200ms`

**Tooltip animations:**
- `animate-in fade-in-0 zoom-in-95`
- Directional: `slide-in-from-top-2` etc based on `data-[side=*]`

**Skeleton:** `animate-pulse`

**Scroll stability:** `scrollbar-gutter: stable` on html element.

---

## Patterns

### Data Slot Convention

All UI primitives use `data-slot` attributes for styling hooks and testing:
```tsx
<div data-slot="card" />
<div data-slot="card-header" />
<button data-slot="button" />
```

### Variant System (CVA)

Components use Class Variance Authority for variant composition:
```tsx
const variants = cva('base-classes', {
    variants: { variant: {}, size: {} },
    defaultVariants: {}
});
```

### Polymorphism (asChild)

Button and Badge support `asChild` via Radix `Slot` for rendering as child element:
```tsx
<Button asChild><Link href="/">Home</Link></Button>
```

### Group Hover

Cards use `group` on the container and `group-hover:*` on children for coordinated hover effects:
```tsx
<Card className="group">
    <span className="group-hover:text-primary">Title</span>
    <ArrowUpRight className="group-hover:translate-x-0.5" />
</Card>
```

### Package Row (`components/package-card.tsx`)

Row inside a `CardList`: `vendor/` muted + name, description (italic "No description" fallback), then right-aligned fixed-width columns — source (repo/mirror icon + name, lg+), updated (md+), "N versions" (sm+), version chip (`Tag` icon, `bg-surface-inset font-mono`), arrow nudge. `hideVendor` inside vendor groups, `hideRepository` on repository pages.

### Vendor-Grouped List (Packages page)

Packages grouped by Composer vendor: header = mono initial tile (`size-6 rounded-md border bg-card`) + `font-mono` vendor + count, then a `CardList` per vendor. Client-side filter input (`max-w-sm`) above.

### Detail Page Header (Package page)

Icon tile `size-14 rounded-xl border bg-surface-inset` · title `text-3xl` + version chip · description `text-base text-muted-foreground` · source line (colored provider icon + identifier, sync Badge, relative time, visibility). Right side: "Downloads" label, total `text-3xl`, SVG sparkline (140×32, `text-primary`, dashed when empty) — clickable to the Downloads tab. Actions as a ghost icon button.

### Install Command Bar

`rounded-lg border bg-surface-inset py-2 pr-2 pl-4 font-mono text-base`: muted `$`, command, `CopyButton` on the right.

### Underline Tabs

`nav flex gap-6 border-b` (no overflow-x-auto — it caused a scrollbar); tab = `-mb-px border-b-2 pb-3 text-base font-medium`, active `border-foreground text-foreground`, inactive `border-transparent text-muted-foreground`. Icon `size-4`; optional count pill.

### About Sidebar (no card)

GitHub-style sidebar next to README (`lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-10`): sections divided by `divide-y`, heading `font-semibold`, facts as label-left / value-right rows. No card border — a card felt too narrow.

### Data Table (Versions)

Bordered card, header row `bg-surface-inset text-xs font-medium text-muted-foreground`, rows `py-2.5` with fixed-width columns (Advisories w-28, Size w-16, Commit w-20, Released w-28). Quiet cells: severity as colored text with `ShieldAlert`, size as muted text, em dash for empty. Row actions (e.g. `composer require` copy button) sit at the right edge of the first column so they align, and appear on row hover (`opacity-0 group-hover/version:opacity-100`). Filters: compact search (`max-w-xs h-9`) + segmented control (`rounded-lg border bg-surface-inset p-0.5`, active `bg-card shadow-xs`), count on the right.

### Sync Status (`components/sync-status.tsx`)

- `StatusDot`: `size-2 rounded-full` — emerald ok, red failed, amber pending, muted neutral.
- `SyncHealthStrip`: last 10 syncs as `h-5 w-1.5 rounded-full` bars (oldest→newest, empty slots `bg-muted`), tooltip per bar. Signature element for repository health — used on repository page and dashboard tiles.

### Dashboard (Health Board)

Header: org name `text-2xl` + summary links (packages · repositories · members) + health summary pill (dots with healthy/failing/pending counts). Repository tiles grid (`sm:2 lg:3`, failing first, max 6): provider tile, name, mono identifier, status dot + label, synced time, health strip. Then Security panel (severity segmented bar + legend, empty state with ShieldCheck) next to downloads chart (`lg:col-span-2`).

### Quick Menus (`components/nav-quick-menu.tsx`)

Radix HoverCard on sidebar Repos/Packages (`openDelay 60`, `closeDelay 80`, `duration-100`), `side="right"`, `w-64 rounded-lg border bg-popover shadow-lg`. "Recently visited" label, up to 5 items (shared `recentlyVisited` prop), footer "All …" link.

### Toasts (`components/ui/toast.tsx`)

Neutral `bg-popover` surface for every variant, `rounded-xl`, soft layered shadow. Status via round tinted icon badge (`size-8`, same tints as Badge) and a 2px countdown bar at the bottom (`animate-toast-progress`, paused on hover). Title `font-semibold`, close button appears on hover.

### List Item Pattern (Activity Feed)

Items in a list with metadata:
```
flex items-center justify-between border-b pb-2 last:border-0
```
Left: content with title link + metadata badges. Right: timestamp in `text-xs text-muted-foreground`.
