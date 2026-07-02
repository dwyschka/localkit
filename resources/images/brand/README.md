# localkit brand assets — "Signal Paw"

Cat paw + broadcast waves: a pet device controlled locally.

## Files

| File | Use |
| --- | --- |
| `localkit-mark.svg` | Core mark (paw + waves), single colour via `currentColor`. |
| `localkit-mark-simple.svg` | Paw only, no waves — for small / favicon sizes where the waves would blur. |
| `localkit-app-icon.svg` | App icon — amber tile + ink paw (96×96, 24px corner radius). |
| `localkit-app-icon-dark.svg` | App icon on a dark tile with an amber paw. |
| `localkit-lockup-dark.svg` | Horizontal lockup (icon + wordmark) for dark backgrounds. |
| `localkit-lockup-light.svg` | Horizontal lockup for light backgrounds. |

## Colours

| Token | Hex |
| --- | --- |
| Amber (primary / gradient start) | `#fbbf24` |
| Amber deep (gradient end) | `#d97706` |
| Ink (paw on light) | `#1c1917` |
| Dark tile | `#18181b` |

Tile gradient: `linear-gradient(140deg, #fbbf24, #d97706)`.

## Notes

- The single-colour marks use `currentColor`, so they inherit the surrounding text colour — set `color` (CSS) or `fill`/`stroke` to recolour.
- The wordmark is a bold geometric sans (weight 800, tight tracking). The lockup SVGs render it as live text with a system-sans fallback; outline the text if a specific font is required.
