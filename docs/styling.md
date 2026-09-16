# Styling and theming

## The Kalium specificity rule

**Every CSS rule for a form control must be scoped under the component's root
class.** This is not stylistic preference — it is required.

Kalium styles inputs with attribute selectors:

```css
input[type="text"], input[type="email"], input[type="tel"] { ... }
```

That selector has specificity `(0,1,1)`. A single utility class such as
`.lccl-bdf__input` is only `(0,1,0)`, so the theme wins regardless of load
order, and fields get repainted white with dark text.

Scoping under the root class raises it to `(0,2,0)`, which beats the theme:

```css
/* Loses to Kalium */
.lccl-bdf__input { background-color: #202020; }

/* Wins */
.lccl-bdf .lccl-bdf__input { background-color: #202020; }
```

Kalium also sets `box-shadow`, `height`, `text-transform`, and
`letter-spacing` on form controls and buttons, so those are explicitly reset in
the form stylesheet rather than left to inherit.

## Stylesheets

| File | Component | Root class |
| --- | --- | --- |
| `assets/css/lccl-de-blood-donor-form.css` | Registration form | `.lccl-bdf` |
| `assets/css/lccl-de.css` | Hello card | `.lccl-de-card` |

Two classes relate to the district dependency described in
[shortcodes.md](shortcodes.md):

- `.lccl-bdf__select[data-locked="true"]` — the blood bank field while it is
  waiting on a district, dimmed with a `not-allowed` cursor.
- `.lccl-bdf__notice` — the inline prompt shown when someone tries to open the
  locked field. Uses `--lccl-bdf-required` and toggles via the `hidden`
  attribute.

Both are enqueued from inside their shortcode callback, so they only load on
pages using that element. Versioning uses `LCCL_DE_VERSION` for cache busting —
bump the plugin version after changing CSS.

## Form CSS variables

All declared on `.lccl-bdf`. Override them in the theme or a WPBakery custom
CSS box to retheme without touching the plugin.

The defaults follow the Kalium construction child theme palette. Primary gold
(`#f7c016`) is intentionally unused — a form does not need an accent colour.

| Variable | Default | Maps to |
| --- | --- | --- |
| `--lccl-bdf-bg` | `#ffffff` | White |
| `--lccl-bdf-heading` | `#333333` | Dark / heading / button hover |
| `--lccl-bdf-text` | `#555555` | Paragraph / body text |
| `--lccl-bdf-field-text` | `#6b6b6b` | Form field text |
| `--lccl-bdf-field-bg` | `#f7f7f7` | Form field background |
| `--lccl-bdf-border` | `#c4c4c4` | Form field border |
| `--lccl-bdf-border-focus` | `#333333` | Dark, used for focus instead of gold |
| `--lccl-bdf-required` | `#d0021b` | Required asterisks only — not in the site palette |
| `--lccl-bdf-font` | `"CooperHewitt-Book", sans-serif` | Site body face |
| `--lccl-bdf-gap` | `22px` | Grid gap |
| `--lccl-bdf-pad` | `0` | Form padding |

`.lccl-bdf` also sets `color-scheme: light` so the native select dropdown
renders light.

### Font

The form uses `"CooperHewitt-Book"`, the same `@font-face` Typolab already
loads site-wide. The plugin does **not** ship or enqueue the font file —
it assumes the theme stylesheet has already defined the family. `font-weight`
is `400` throughout so the browser does not faux-bold Book.

If the form is ever rendered on a page that does not load Typolab, it falls
back to `sans-serif`.

### Switching to a dark palette

```css
.lccl-bdf {
	--lccl-bdf-bg: #1c1c1c;
	--lccl-bdf-text: #cfcfcf;
	--lccl-bdf-heading: #e0e0e0;
	--lccl-bdf-field-text: #cfcfcf;
	--lccl-bdf-field-bg: #202020;
	--lccl-bdf-border: #3c3c3c;
	--lccl-bdf-border-focus: #7a7a7a;
	color-scheme: dark;
}
```

The select chevron is an inline SVG data URI with a hard coded stroke colour,
so it needs editing in the stylesheet rather than through a variable.

## Type sizes

Slightly larger than the previous pass so Cooper Hewitt Book remains readable.

| Element | Size |
| --- | --- |
| Title | 28px (24px on small screens) |
| Root / inputs | 17px |
| Labels, checkboxes, submit, required note, intro, section heading | 16px |
| Privacy copy | 15px |
| Inline notice | 14px |

Inputs stay at or above 16px so iOS Safari does not zoom on focus.

## Width

The form sets `width: 100%` with **no `max-width`** and zero padding, so it
fills whatever container it is placed in — typically `.wpb_wrapper` inside a
WPBakery column. Control the width and gutters with the row and column
settings in the builder rather than in plugin CSS.

To constrain it locally:

```css
.lccl-bdf { max-width: 900px; margin: 0 auto; }
```

## Layout

A two column CSS grid. Full width rows use `.lccl-bdf__field--full`, applied to
Address and the notify checkbox.

Row order: first/last name, address, city/postal code, email/phone,
district/blood bank, donation preference/donation history, contact method
(alone), notify checkbox.

## Responsive behaviour

| Breakpoint | Behaviour |
| --- | --- |
| Above 782px | Two columns, auto width submit button |
| 782px and below | Single column, full width button, gap 18px |
| 480px and below | Field height stays 48px |

Deliberate mobile choices:

- Inputs use `font-size: 17px`. Anything smaller than 16px makes iOS Safari
  zoom the page when a field gains focus.
- Minimum field height is 48px to stay above the recommended tap target size.
- `minmax(0, 1fr)` grid columns prevent long select option text from forcing
  horizontal overflow.
- Transitions are disabled under `prefers-reduced-motion: reduce`.

Verified at 390px and 1280px: no horizontal overflow at either width.
