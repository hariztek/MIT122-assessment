name: Student SkillBridge Design System
colors:
  surface: '#16181a'
  surface-dim: '#101214'
  surface-bright: '#2c3136'
  surface-container-lowest: '#0c0e10'
  surface-container-low: '#1c1f22'
  surface-container: '#22262a'
  surface-container-high: '#2a2f33'
  surface-container-highest: '#343a3f'
  on-surface: '#f4f5ef'
  on-surface-variant: '#9ba0a3'
  inverse-surface: '#f4f5ef'
  inverse-on-surface: '#16181a'
  outline: '#5c6368'
  outline-variant: '#3a3f43'
  surface-tint: '#d3e34a'
  primary: '#d3e34a'
  on-primary: '#16181a'
  primary-container: '#3a4210'
  on-primary-container: '#e6f180'
  inverse-primary: '#66701c'
  secondary: '#9ba0a3'
  on-secondary: '#16181a'
  secondary-container: '#2a2f33'
  on-secondary-container: '#c8cdd0'
  tertiary: '#a9c7b8'
  on-tertiary: '#16181a'
  tertiary-container: '#26362e'
  on-tertiary-container: '#c5e3d3'
  error: '#ffb4a6'
  on-error: '#3a0905'
  error-container: '#5c150c'
  on-error-container: '#ffdad4'
  background: '#16181a'
  on-background: '#f4f5ef'
  surface-variant: '#2a2f33'
typography:
  headline-xl:
    fontFamily: Zilla Slab
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1.1'
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Zilla Slab
    fontSize: 34px
    fontWeight: '700'
    lineHeight: '1.15'
  headline-lg-mobile:
    fontFamily: Zilla Slab
    fontSize: 28px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-md:
    fontFamily: Zilla Slab
    fontSize: 22px
    fontWeight: '600'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Work Sans
    fontSize: 17px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Work Sans
    fontSize: 15px
    fontWeight: '400'
    lineHeight: '1.55'
  label-lg:
    fontFamily: Work Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
  label-md:
    fontFamily: Work Sans
    fontSize: 11px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.16em
rounded:
  sm: 0.375rem
  DEFAULT: 0.75rem
  md: 0.75rem
  lg: 1rem
  xl: 1.25rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 48px
  xl: 80px
  gutter: 20px
  margin-mobile: 16px
  margin-desktop: 40px
---

## Brand & Style

Student SkillBridge is a free peer-to-peer skill-learning platform for university students. The visual identity — "Night Pinboard" — evokes evening study sessions and campus noticeboards: dark, focused surfaces punctuated by a single electric chartreuse accent. The personality is friendly and student-run, never corporate or institutional.

The aesthetic is **Dark / High-Energy / Approachable**. Slab-serif headlines give a hand-pinned, editorial character; pill-shaped controls keep interactions soft and inviting. The dark canvas signals a tool students use on their own time, and the chartreuse accent marks every action that connects two people.

## Colors

A near-black charcoal palette with one loud accent. Color is rationed: chartreuse means "act here."

- **Primary (#D3E34A):** Electric chartreuse for primary actions, match scores, links, and brand moments. Always paired with charcoal text (#16181A) — never white.
- **Surface (#16181A):** Charcoal canvas for all pages.
- **Surface Container (#22262A):** Raised charcoal for cards, panels, and inputs; higher steps (#2A2F33, #343A3F) for nested or active layers.
- **On-Surface (#F4F5EF):** Warm off-white for headings and primary text; #9BA0A3 for secondary text and metadata.
- **Tertiary (#A9C7B8):** Muted sage, reserved for positive/confirmed states (accepted requests, completed sessions).

Chartreuse should occupy well under 10% of any screen. Body text is never chartreuse.

## Typography

**Zilla Slab** carries all headlines and card titles. Its slab serifs feel like poster type on a campus noticeboard — sturdy and characterful without being formal. Use weight 700 for page titles, 600 for card and list titles.

**Work Sans** handles all UI and body text: buttons, labels, forms, metadata, and paragraphs. It stays legible at small sizes on dark surfaces.

Uppercase labels (nav, section eyebrows, status tags) are set in Work Sans 600 at 11px with 0.16em tracking, usually in chartreuse or the secondary grey. Avoid letter-spacing on Zilla Slab.

## Layout & Spacing

An 8px base unit governs all spacing.

- **Mobile:** 4-column grid, 16px margins and gutters; touch targets 44px minimum.
- **Desktop:** 12-column centered grid, 1280px max width, 24px gutters, 40px minimum margins.

Group content with padding and surface steps rather than borders. Dark UIs get muddy when crowded — keep `md` (24px) padding inside cards and `lg` (48px) between page sections.

## Elevation & Depth

Depth comes from **tonal lightening**, not shadows: each layer up uses the next surface-container step. Shadows are near-invisible on charcoal, so use them only for overlays and modals (0 8px 32px rgba(0,0,0,0.5)).

- **Level 0 (Base):** #16181A page background.
- **Level 1 (Cards):** #22262A, no shadow.
- **Level 2 (Hover/active cards, dropdowns):** #2A2F33.
- **Level 3 (Modals):** #2A2F33 with the heavy overlay shadow and a 1px #3A3F43 border.

## Shapes

Friendly and rounded. Buttons, chips, and tags are **full pills (9999px)**. Cards and panels use 0.75rem; large feature containers 1.25rem. Inputs use 0.75rem. No sharp corners anywhere — the pill language is the system's signature, inherited from the noticeboard aesthetic.

## Components

### Buttons
- **Primary:** Chartreuse pill, charcoal text, Work Sans 600 14px, 10px 18px padding.
- **Secondary:** Transparent pill with 1.5px #3A3F43 border and off-white text; border lightens to #5C6368 on hover.
- **Tertiary:** Ghost; chartreuse text, no container.

### Input Fields
- #22262A background, 1px #3A3F43 border, 0.75rem radius, off-white text with #9BA0A3 placeholders. Focus border becomes chartreuse.

### Cards
- Match and skill cards on #22262A at 0.75rem radius with 16–18px padding. Title in Zilla Slab 600, metadata in Work Sans #9BA0A3, match score in chartreuse Work Sans 600.

### Chips
- Skill tags and modes (In person, Online) are pills: chartreuse text on 12%-opacity chartreuse (rgba(211,227,74,0.12)). Status chips use sage for accepted/completed, #9BA0A3 for pending.

### Match Scores
- The deterministic score (e.g. "85 match") always appears in chartreuse with its plain-language breakdown (+50 skill · +25 mode · +10 experience) in secondary grey beneath.

### Lists
- Zilla Slab titles, Work Sans metadata, 1px #2A2F33 dividers, 24px vertical padding. No zebra striping.

### Selection Controls
- Checkboxes, radios, and toggles fill chartreuse when selected with charcoal checkmarks; unselected states use #3A3F43 outlines
