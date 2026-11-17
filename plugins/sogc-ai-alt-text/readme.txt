# SOGC AI Alt Text & Image Describer

A lightweight, permanent WordPress plugin developed for the Society of Obstetricians and Gynaecologists of Canada (SOGC) web network (Menopause and U, YourPeriod, PregnancyInfo, SexAndU, and SOGC main site).

## Features
- **Powered by Google Gemini 2.5 Flash Vision**: Ultra-fast, highly accurate image descriptions using Google AI Studio free tier.
- **Medical & Accessibility Focus**: Prompts tailored specifically for healthcare, menopause, reproductive health, and WCAG 2.1 AA screen reader standards.
- **Real-Time Upload Automation**: Automatically generates alt text and image descriptions whenever an image is uploaded through the media library or block editor.
- **Bulk Media Generator**: Processes hundreds of legacy images across the media library with real-time AJAX progress tracking and pause/resume controls.
- **Multi-Site Configurable**: Each SOGC site can specify its own brand focus (e.g., "Menopause and U", "YourPeriod.ca") and character limit.

## Installation
1. Upload the `sogc-ai-alt-text` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress under **Plugins > Installed Plugins**.
3. Go to **Settings > SOGC AI Alt Text**:
   - Enter your **Gemini API Key** (from [Google AI Studio](https://aistudio.google.com/)).
   - Save Settings.
4. To process existing media, navigate to **Media > Bulk Alt Text (AI)** and click **Start Bulk Generation**.
