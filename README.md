# SOGC WordPress Plugins & Optimization Assets

Shared, production-tested WordPress plugins, accessible templates, and performance tools for The Society of Obstetricians and Gynaecologists of Canada (SOGC) web properties.

---

## Repository Contents

### 1. `plugins/sogc-ai-alt-text/`
* **Purpose**: Automated, medical/WCAG-compliant alt text and description generator powered by Google Gemini Vision.
* **Default Model**: `gemini-3.5-flash-lite` on the standard `v1beta/models/{model}:generateContent` endpoint (free tier, 1,500 requests/day, zero recurring API cost).
* **Key Features**:
  * Auto-generates alt text on media upload (`add_attachment`).
  * Bulk processor with async 1-by-1 AJAX queue runner and live progress counter (`upload.php?page=sogc-ai-alt-bulk`).
  * Settings page (`options-general.php?page=sogc-ai-alt-text`) for API key management and prompt tuning.

### 2. `plugins/sogc-faq/`
* **Purpose**: Lightweight FAQ custom post type and taxonomy with animated chevron accordions and multilingual support.
* **Key Features**:
  * Native accessible `<details>` / `<summary>` accordions.
  * WPML language synchronization (`en-CA` / `fr-CA`).
  * Shortcode: `[sogc_faq category="menopause" count="-1"]`.
  * Automated `schema.org/FAQPage` JSON-LD generator for Google Rich Results.

### 3. `shared-theme-assets/`
* **`page-faq.php`**: Full-width accessible FAQ page template with accordion styling and sidebar hierarchy.
* **`minify-css.ps1`**: PowerShell script to minify and compress Foundation/theme CSS down ~20% for faster Core Web Vitals.

---

## Installation Across SOGC Sites

1. Copy the desired plugin folder from `plugins/` into your WordPress site's `wp-content/plugins/` directory.
2. In the WordPress Admin, go to **Plugins > Installed Plugins** and click **Activate**.
3. For AI Alt Text: Navigate to **Settings > SOGC AI Alt Text**, enter your Google Gemini API key, and click **Save Settings**.
