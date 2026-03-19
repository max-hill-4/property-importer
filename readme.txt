=== Property Importer for Estate Agents ===
Contributors: max-hill-4
Tags: real estate, property, estate agent, lettings, property search
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A property listing and search plugin for UK estate agents. Import from XML feeds, display properties with AJAX search, hero sliders, and fully customisable styling.

== Description ==

Property Importer is a lightweight, customisable property listing plugin built for UK estate agents. It imports properties from Rightmove-format XML feeds and provides shortcodes for displaying them with live AJAX search, featured sliders, and full-bleed hero sections.

**Features:**

* **XML Feed Import** — Import properties from any Rightmove V3-format XML feed. Supports scheduled auto-import (every 30 min, hourly, 2h, 6h, daily).
* **Auto-sync** — Properties removed from the feed are automatically deleted from WordPress on the next import.
* **AJAX Property Search** — Live search with filters for status, bedrooms, min/max price, and keyword. No page reload.
* **Featured Sliders** — Display a carousel of property cards with `[featured_properties]`.
* **Hero Sections** — Full-bleed image hero with slideshow for a single property `[featured_property id="123"]` or a group `[featured_properties_hero ids="1,2,3"]`.
* **🎨 Styling Panel** — Admin UI to customise font family, font size, accent colour, border radius, and choose from 3 layout variants for cards, heroes, and sliders — no CSS editing required.
* **Virtual Tour support** — Lightbox video overlay from Facebook Reels or any video URL.
* **Custom images per property** — Add extra images via the admin that appear alongside feed images in the hero.
* **Hero image exclusions** — Exclude specific feed images from the hero on a per-property basis.

**Shortcodes:**

* `[property_search_form]` — Full AJAX search form + results grid
* `[featured_properties count="6" status="for-sale"]` — Card carousel slider
* `[featured_property id="123"]` — Single full-bleed hero
* `[featured_properties_hero ids="1,2,3"]` — Multi-property hero slider
* `[featured_properties_hero street="High Street"]` — Auto-find by address
* `[featured_properties_hero postcode="NN10"]` — Auto-find by postcode

== Installation ==

1. Upload the `property-importer` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Go to **Properties → Import Properties**, paste your XML feed URL, and save.
4. Run an initial import.
5. Use the shortcodes above on any page or post.
6. Customise appearance via **Properties → 🎨 Styling**.

== Frequently Asked Questions ==

= What XML feed format does this support? =
Rightmove V3 XML format, as exported by most UK property CRM systems (10ninety, Jupix, Reapit, Alto, etc.).

= Will it delete properties that are removed from my feed? =
Yes. On every import, any property whose agent reference is no longer present in the feed is permanently deleted from WordPress.

= Can I use multiple feeds? =
Currently one feed URL is supported. Multiple feed support is planned.

= Does it work with any theme? =
Yes. All styles are self-contained and scoped to plugin CSS classes.

== Screenshots ==

1. Property search form with live AJAX results
2. Featured properties card slider
3. Full-bleed hero with image slideshow
4. Styling admin panel — font, colour, and layout options

== Changelog ==

= 1.5 =
* Added Styling admin panel: font family, font size, accent colour, border radius
* 3 layout options for search cards, hero sections, and sliders
* Font loaded dynamically from Google Fonts based on admin selection

= 1.4 =
* Auto-delete properties removed from XML feed on import

= 1.3 =
* Added price display on hero thumbnails
* Updated default font to Outfit
* Button colours inherit from site accent

= 1.2 =
* Virtual tour lightbox support
* Hero image exclusion per property
* Custom extra images per property
* Featured properties hero group slider shortcode

= 1.1 =
* AJAX property search with price, bedroom, status filters
* Pagination support

= 1.0 =
* Initial release: XML import, property CPT, featured slider, single hero
