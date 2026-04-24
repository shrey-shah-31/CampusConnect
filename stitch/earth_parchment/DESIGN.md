# Design System Strategy: The Tactile Archivist

## 1. Overview & Creative North Star
In the context of a Placement Management System, we are not simply building a database; we are curating professional journeys. The "Creative North Star" for this design system is **The Tactile Archivist**. 

Unlike standard SaaS platforms that rely on rigid grids and clinical white space, this system embraces a high-end editorial aesthetic. It feels like a physical portfolio of fine paper and stone. We move away from "template" layouts by using intentional asymmetry, overlapping card elements, and a typographic scale that prioritizes authority and breathing room. Every interaction must feel weighted, deliberate, and premium.

---

## 2. Color & Tonal Depth
This system rejects the "flat" web. We use a palette of warm, organic tones to create a sense of heritage and trust.

### The Palette
*   **Background (`#fff8f4`):** Our canvas. It is a soft, off-white that prevents eye strain and feels more premium than pure hex white.
*   **Primary (`#765a19` / `#e0bc71`):** A sophisticated gold/ochre used for calls to action and brand expression.
*   **Secondary (`#80552b`):** A leather-toned brown for supporting elements and interactive accents.
*   **Surface Tiers:** We use `surface_container` levels (Lowest to Highest) to define hierarchy.

### The "No-Line" Rule
**Explicit Instruction:** Designers are prohibited from using 1px solid borders to section off content. Boundaries must be defined solely through background color shifts.
*   Place a `surface_container_lowest` card on a `surface_container_low` background to create a soft, natural lift.
*   If a visual break is needed, use a change in tonal value rather than a line.

### The "Glass & Gradient" Rule
To add "soul" to the interface:
*   **CTAs:** Use a subtle linear gradient from `primary` to `primary_container` (Top-Left to Bottom-Right) to provide a soft, light-catching sheen.
*   **Overlays:** Use semi-transparent surface colors with a `backdrop-blur (12px-20px)` for floating modals or navigation bars. This makes the UI feel integrated and airy.

---

## 3. Typography: Editorial Authority
We utilize **Manrope** for its clean, geometric, yet humanistic qualities. The hierarchy is designed to feel like a high-end magazine.

*   **Display (lg/md):** Reserved for high-impact moments (e.g., "Welcome, Director"). These should use tight letter-spacing (-0.02em) to feel cohesive and modern.
*   **Headlines:** Used for section titles. Give these ample "sky" (margin-top) to let the content breathe.
*   **Body (lg/md):** Set with a generous line-height (1.6) to ensure readability during long placement reviews.
*   **Labels:** Always uppercase with slight letter-spacing (+0.05em) when used for metadata, providing a "tagged" archival look.

---

## 4. Elevation & Depth: Tonal Layering
Depth is not achieved through shadows alone, but through "The Layering Principle."

*   **Layering Principle:** Treat the UI as a series of physical sheets. An "Inner Card" should sit on a `surface_container_high` background and be colored `surface_container_highest`. 
*   **Ambient Shadows:** For floating elements (Modals, Popovers), use a shadow tinted with the `on_surface` color at 6% opacity. 
    *   *Value:* `0px 12px 32px rgba(32, 27, 19, 0.06)`
*   **Ghost Borders:** If a border is required for accessibility in input fields, use the `outline_variant` at **20% opacity**. Never use a 100% opaque border.

---

## 5. Components

### Cards (The Archival Sheet)
*   **Styling:** Radius `md` (12px). No borders. Use `surface_container_low` for the base and `surface_container_highest` for hover states.
*   **Interaction:** On hover, the card should lift slightly (Y-axis -4px) with a transition of `0.3s ease`.

### Buttons (The Primary Action)
*   **Primary:** Background `primary`, text `on_primary`. 0.5rem (8px) corners.
*   **Secondary:** Background `secondary_container`, text `on_secondary_container`.
*   **Transitions:** All buttons must utilize a `0.3s cubic-bezier(0.4, 0, 0.2, 1)` transition for background-color and transform shifts.

### Input Fields (The Focused Entry)
*   **Default:** `surface_variant` background, no border, 8px rounded corners.
*   **Focus State:** Shift background to `surface` and add a 2px "Ghost Border" using the `primary` color at 40% opacity. This creates a "glow" rather than a hard line.

### Chips & Tags
*   For placement status (e.g., "Pending," "Confirmed").
*   Use `secondary_fixed_dim` with `on_secondary_fixed_variant` text. Avoid high-contrast "stoplight" colors (Red/Green) in favor of more muted, sophisticated variants of those hues.

---

## 6. Do’s and Don’ts

### Do:
*   **Embrace Asymmetry:** Align a headline to the left but offset the supporting card to the right to create visual interest.
*   **Use Generous Padding:** If you think there is enough padding, add 8px more. The "Archivist" style requires extreme breathing room.
*   **Layer Surfaces:** Use `surface_dim` for the main background and `surface_bright` for active content areas.

### Don’t:
*   **Don't use Dividers:** Never use a `<hr>` or a 1px line to separate list items. Use vertical space or a `surface_container` color shift.
*   **Don't use Pure Black Shadows:** Shadows must always be tinted with the background's warm tones to maintain the "Tactile" feel.
*   **Don't use Standard "Blue" for Links:** Use the `secondary` or `accent` colors to maintain the warmth of the palette.

---

## 7. Interaction Motion
All movement within the system should mimic the physical world. 
*   **Modals:** Should "grow" from the point of origin rather than just appearing.
*   **State Changes:** Use a 0.3s duration for all color transitions to ensure the interface feels smooth and expensive, never jarring.