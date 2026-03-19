# Property Importer for WordPress

A lightweight property listing and search plugin for UK estate agents. Import properties from Rightmove-format XML feeds and display them with live AJAX search, featured sliders, hero sections, and a no-code styling panel.

---

## Features

- **XML Feed Import** — Supports Rightmove V3 XML (10ninety, Jupix, Reapit, Alto, etc.)
- **Auto-sync** — Properties removed from the feed are automatically deleted on next import
- **Scheduled imports** — Every 30 min, hourly, 2h, 6h, or daily via WP-Cron
- **AJAX Property Search** — Live filtering by status, bedrooms, and price range — no page reload
- **Featured Sliders** — Card carousel with autoplay and pagination dots
- **Hero Sections** — Full-bleed image hero with background slideshow, price, address, and beds/baths
- **Virtual Tour** — Lightbox video overlay from any video URL (Facebook Reels, etc.)
- **Per-property image control** — Add extra images or exclude specific feed images from the hero
- **🎨 Styling Panel** — Choose font, accent colour, border radius, and layout from the WP admin — no CSS editing needed

---

## Shortcodes

| Shortcode | Description |
|---|---|
| `[property_search_form]` | Full AJAX search form + results grid |
| `[featured_properties count="6"]` | Card carousel slider |
| `[featured_properties status="for-sale"]` | Slider filtered by status |
| `[featured_property id="123"]` | Single full-bleed hero |
| `[featured_properties_hero ids="1,2,3"]` | Multi-property hero slider |
| `[featured_properties_hero street="High Street"]` | Hero — auto-find by street name |
| `[featured_properties_hero postcode="NN10"]` | Hero — auto-find by postcode |

---

## Styling Panel

Go to **Properties → 🎨 Styling** in WP Admin to customise:

| Setting | Options |
|---|---|
| Font Family | Outfit, Inter, Poppins, Lato, Montserrat, DM Sans |
| Font Size | 12 – 20px |
| Accent Colour | Colour picker |
| Card Border Radius | 0 – 24px |
| Search Card Layout | Classic Grid · Horizontal · List |
| Hero Layout | Full Bleed · Centred · Compact |
| Slider Layout | 3-Up · 2-Up · 1-Up |

All changes apply instantly on save — no CSS files to edit.

---

## Installation

1. Upload the `property-importer` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Go to **Properties → Import Properties**, paste your XML feed URL and save
4. Run an initial import
5. Drop shortcodes into any page
6. Customise via **Properties → 🎨 Styling**

---

## Requirements

- WordPress 6.0+
- PHP 7.4+

---

## Changelog

### 1.6
- Field Mapping admin panel: map any XML feed format to plugin fields
- Preset loader for Rightmove V3 / 10ninety feeds
- Import function refactored to use configurable field map

### 1.5
- Styling admin panel: font, colour, border radius, layout variants
- Font loaded dynamically from Google Fonts
- 3 layout options for cards, heroes, and sliders

### 1.4
- Auto-delete properties removed from XML feed

### 1.3
- Price shown on hero thumbnails
- Default font updated to Outfit
- Button colours configurable via styling panel

### 1.2
- Virtual tour lightbox
- Hero image exclusions per property
- Custom extra images per property
- `[featured_properties_hero]` group slider shortcode

### 1.0
- Initial release: XML import, property CPT, featured slider, single hero

---

## License

[GPL-2.0](LICENSE)
