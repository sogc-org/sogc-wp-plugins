<?php
/**
 * Plugin Name: SOGC AI Alt Text & Image Describer
 * Plugin URI: https://sogc.org/
 * Description: Automatically generates medical, accessible WCAG-compliant alt text and descriptions for WordPress media using Google Gemini Vision. Supports real-time upload processing and bulk background generation.
 * Version: 1.0.1
 * Author: Society of Obstetricians and Gynaecologists of Canada (SOGC)
 * Author URI: https://sogc.org/
 * License: GPLv2 or later
 * Text Domain: sogc-ai-alt
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOGC_AI_Alt_Text {

    private static $instance = null;
    private $option_name = 'sogc_ai_alt_settings';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_attachment', array($this, 'handle_image_upload'));
        add_action('wp_ajax_sogc_ai_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_sogc_ai_bulk_generate', array($this, 'ajax_bulk_generate'));
        add_action('wp_ajax_sogc_ai_process_single', array($this, 'ajax_process_single'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Default configuration options
     */
    public function get_options() {
        $defaults = array(
            'gemini_api_key'      => '',
            'gemini_model'        => 'gemini-3.5-flash-lite',
            'auto_on_upload'      => 1,
            'overwrite_existing'  => 0,
            'batch_size'          => 1,
            'max_chars'           => 125,
            'site_focus'          => 'Menopause and U (SOGC)',
            'custom_prompt'       => "You are an accessibility and medical communications specialist for the Society of Obstetricians and Gynaecologists of Canada (SOGC).\n\nGenerate an accurate, compassionate, and descriptive alt text for this image following WCAG 2.1 AA accessibility guidelines:\n1. Accurately describe what is depicted (people, clinical diagrams, symptoms, or infographics) with healthcare and women's health context.\n2. Keep it under {MAX_CHARS} characters so screen readers read it smoothly.\n3. Do NOT start with 'Image of', 'Photo of', or 'Picture of'.\n4. If the image contains text (e.g. diagrams, logos, infographics), accurately transcribe or summarize the text.",
        );
        $saved = get_option($this->option_name, array());
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Register Admin Menu under Settings and Media
     */
    public function add_admin_menu() {
        add_options_page(
            __('SOGC AI Alt Text', 'sogc-ai-alt'),
            __('SOGC AI Alt Text', 'sogc-ai-alt'),
            'manage_options',
            'sogc-ai-alt-text',
            array($this, 'render_settings_page')
        );

        add_media_page(
            __('Bulk Alt Text (AI)', 'sogc-ai-alt'),
            __('Bulk Alt Text (AI)', 'sogc-ai-alt'),
            'manage_options',
            'sogc-ai-alt-bulk',
            array($this, 'render_bulk_page')
        );
    }

    /**
     * Register Settings
     */
    public function register_settings() {
        register_setting('sogc_ai_alt_group', $this->option_name, array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        $sanitized['gemini_api_key']     = sanitize_text_field($input['gemini_api_key'] ?? '');
        $sanitized['gemini_model']       = sanitize_text_field($input['gemini_model'] ?? 'gemini-2.5-flash');
        $sanitized['auto_on_upload']     = !empty($input['auto_on_upload']) ? 1 : 0;
        $sanitized['overwrite_existing'] = !empty($input['overwrite_existing']) ? 1 : 0;
        $sanitized['batch_size']         = max(1, min(50, intval($input['batch_size'] ?? 10)));
        $sanitized['max_chars']          = max(50, min(300, intval($input['max_chars'] ?? 125)));
        $sanitized['site_focus']         = sanitize_text_field($input['site_focus'] ?? 'SOGC');
        $sanitized['custom_prompt']      = sanitize_textarea_field($input['custom_prompt'] ?? '');
        return $sanitized;
    }

    /**
     * Enqueue Admin JS/CSS on our plugin pages
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_sogc-ai-alt-text' && $hook !== 'media_page_sogc-ai-alt-bulk') {
            return;
        }

        wp_enqueue_style('sogc-ai-alt-admin', false);
        wp_add_inline_style('sogc-ai-alt-admin', '
            .sogc-card { background: #fff; border: 1px solid #ccd0d4; padding: 24px; border-radius: 8px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 900px; }
            .sogc-card h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px; }
            .sogc-badge { display: inline-block; background: #602d89; color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase; }
            .sogc-progress-bar { width: 100%; height: 24px; background: #e2e8f0; border-radius: 12px; overflow: hidden; margin: 16px 0; position: relative; }
            .sogc-progress-fill { width: 0%; height: 100%; background: linear-gradient(90deg, #602d89, #8e44ad); transition: width 0.3s ease; }
            .sogc-progress-text { position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #1e293b; }
            .sogc-log { background: #0f172a; color: #f8fafc; padding: 14px; border-radius: 6px; font-family: monospace; font-size: 13px; max-height: 350px; overflow-y: auto; line-height: 1.5; }
            .sogc-log .item-success { color: #4ade80; }
            .sogc-log .item-skip { color: #94a3b8; }
            .sogc-log .item-error { color: #f87171; }
        ');
    }

    /**
     * Render Plugin Settings Page
     */
    public function render_settings_page() {
        $opts = $this->get_options();
        ?>
        <div class="wrap">
            <h1><?php _e('SOGC AI Alt Text & Image Describer', 'sogc-ai-alt'); ?> <span class="sogc-badge">v1.0.0</span></h1>
            <p><?php _e('Powered by Google Gemini Vision. Automatically generates medical and WCAG-accessible descriptions for all SOGC web properties.', 'sogc-ai-alt'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('sogc_ai_alt_group'); ?>

                <div class="sogc-card">
                    <h2><span class="dashicons dashicons-admin-generic"></span> <?php _e('API Configuration', 'sogc-ai-alt'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="gemini_api_key"><?php _e('Gemini API Key', 'sogc-ai-alt'); ?></label></th>
                            <td>
                                <input name="<?php echo $this->option_name; ?>[gemini_api_key]" type="password" id="gemini_api_key" value="<?php echo esc_attr($opts['gemini_api_key']); ?>" class="regular-text" placeholder="AIzaSy...">
                                <p class="description">
                                    <?php _e('Get a free API key from', 'sogc-ai-alt'); ?> 
                                    <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer"><?php _e('Google AI Studio (free tier)', 'sogc-ai-alt'); ?> &rarr;</a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gemini_model"><?php _e('Vision Model', 'sogc-ai-alt'); ?></label></th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[gemini_model]" id="gemini_model">
                                    <option value="gemini-3.5-flash-lite" <?php selected($opts['gemini_model'], 'gemini-3.5-flash-lite'); ?>>Gemini 3.5 Flash Lite (Recommended - High Quota & Fast Vision)</option>
                                    <option value="gemini-3.8-flash" <?php selected($opts['gemini_model'], 'gemini-3.8-flash'); ?>>Gemini 3.8 Flash</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="sogc-card">
                    <h2><span class="dashicons dashicons-controls-repeat"></span> <?php _e('Automation & Workflow Settings', 'sogc-ai-alt'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Auto-Generate on Upload', 'sogc-ai-alt'); ?></th>
                            <td>
                                <label for="auto_on_upload">
                                    <input name="<?php echo $this->option_name; ?>[auto_on_upload]" type="checkbox" id="auto_on_upload" value="1" <?php checked($opts['auto_on_upload'], 1); ?>>
                                    <?php _e('Automatically generate alt text and description whenever an image is uploaded in Media Library or page editor.', 'sogc-ai-alt'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Overwrite Existing Alt Text', 'sogc-ai-alt'); ?></th>
                            <td>
                                <label for="overwrite_existing">
                                    <input name="<?php echo $this->option_name; ?>[overwrite_existing]" type="checkbox" id="overwrite_existing" value="1" <?php checked($opts['overwrite_existing'], 1); ?>>
                                    <?php _e('If checked, bulk generator will replace existing alt text. (Leave unchecked to only process images missing alt text).', 'sogc-ai-alt'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="batch_size"><?php _e('Batch Size (Per Request)', 'sogc-ai-alt'); ?></label></th>
                            <td>
                                <input name="<?php echo $this->option_name; ?>[batch_size]" type="number" id="batch_size" min="1" max="50" value="<?php echo esc_attr($opts['batch_size']); ?>" class="small-text">
                                <p class="description"><?php _e('Number of images to process in each AJAX batch iteration during bulk runs (Default: 10).', 'sogc-ai-alt'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="max_chars"><?php _e('Max Character Length', 'sogc-ai-alt'); ?></label></th>
                            <td>
                                <input name="<?php echo $this->option_name; ?>[max_chars]" type="number" id="max_chars" min="50" max="300" value="<?php echo esc_attr($opts['max_chars']); ?>" class="small-text">
                                <p class="description"><?php _e('WCAG accessibility recommendation is 100-125 characters (Default: 125).', 'sogc-ai-alt'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="site_focus"><?php _e('Site Brand / Medical Focus', 'sogc-ai-alt'); ?></label></th>
                            <td>
                                <input name="<?php echo $this->option_name; ?>[site_focus]" type="text" id="site_focus" value="<?php echo esc_attr($opts['site_focus']); ?>" class="regular-text">
                                <p class="description"><?php _e('e.g., "Menopause and U (SOGC)", "YourPeriod.ca", "PregnancyInfo.ca", "SOGC Clinical Guidelines".', 'sogc-ai-alt'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="custom_prompt"><?php _e('Vision AI System Prompt', 'sogc-ai-alt'); ?></label></th>
                            <td>
                                <textarea name="<?php echo $this->option_name; ?>[custom_prompt]" id="custom_prompt" rows="7" class="large-text code"><?php echo esc_textarea($opts['custom_prompt']); ?></textarea>
                                <p class="description"><?php _e('Use <code>{MAX_CHARS}</code> and <code>{SITE_FOCUS}</code> as dynamic tags in your instructions.', 'sogc-ai-alt'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save Settings', 'sogc-ai-alt')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Bulk Generator Admin Screen (Instant Load with Async Stats Counter)
     */
    public function render_bulk_page() {
        $opts = $this->get_options();
        ?>
        <div class="wrap">
            <h1><?php _e('Bulk AI Alt Text Generator', 'sogc-ai-alt'); ?> <span class="sogc-badge">SOGC</span></h1>
            <p><?php _e('Batch process your existing Media Library using Gemini Vision to achieve 100% WCAG accessibility coverage.', 'sogc-ai-alt'); ?></p>

            <?php if (empty($opts['gemini_api_key'])) : ?>
                <div class="notice notice-error">
                    <p><?php printf(__('Please set your Gemini API key in <a href="%s">SOGC AI Alt Settings</a> before starting bulk generation.', 'sogc-ai-alt'), admin_url('options-general.php?page=sogc-ai-alt-text')); ?></p>
                </div>
            <?php endif; ?>

            <div class="sogc-card">
                <h2><span class="dashicons dashicons-format-image"></span> <?php _e('Media Library Status', 'sogc-ai-alt'); ?></h2>
                <p>
                    <strong><?php _e('Total Images in Library:', 'sogc-ai-alt'); ?></strong> <span id="sogc-total-count"><span class="spinner is-active" style="float:none; margin:0 5px;"></span> Scanning...</span><br>
                    <strong><?php _e('Images Missing Alt Text:', 'sogc-ai-alt'); ?></strong> <span id="sogc-missing-count" style="color: #e11d48; font-weight: 700;"><span class="spinner is-active" style="float:none; margin:0 5px;"></span> Calculating...</span>
                </p>

                <div class="sogc-progress-bar">
                    <div id="sogc-progress-fill" class="sogc-progress-fill"></div>
                    <div id="sogc-progress-text" class="sogc-progress-text">Ready</div>
                </div>

                <div style="margin-top: 16px;">
                    <button type="button" id="sogc-start-btn" class="button button-primary button-hero" disabled>
                        <span class="dashicons dashicons-update" style="vertical-align: text-bottom;"></span> <?php _e('Start Bulk Generation', 'sogc-ai-alt'); ?>
                    </button>
                    <button type="button" id="sogc-stop-btn" class="button button-secondary button-hero" style="display: none; margin-left: 10px;">
                        <?php _e('Pause', 'sogc-ai-alt'); ?>
                    </button>
                </div>
            </div>

            <div class="sogc-card">
                <h2><span class="dashicons dashicons-list-view"></span> <?php _e('Live Processing Log', 'sogc-ai-alt'); ?></h2>
                <div id="sogc-log" class="sogc-log">
                    <div>Waiting to start...</div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var isRunning = false;
            var totalToProcess = 0;
            var processed = 0;
            var queue = [];
            var hasApiKey = <?php echo !empty($opts['gemini_api_key']) ? 'true' : 'false'; ?>;

            // Fetch Library Counts Asynchronously after page renders instantly
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'sogc_ai_get_stats',
                    nonce: '<?php echo wp_create_nonce('sogc_ai_bulk_nonce'); ?>'
                },
                success: function(resp) {
                    if (resp.success && resp.data) {
                        $('#sogc-total-count').text(resp.data.total.toLocaleString());
                        $('#sogc-missing-count').text(resp.data.missing.toLocaleString());
                        totalToProcess = resp.data.missing;

                        if (hasApiKey && totalToProcess > 0) {
                            $('#sogc-start-btn').prop('disabled', false);
                            logMessage('Library scanned: ' + totalToProcess + ' images need alt text. Ready to start.', '');
                        } else if (totalToProcess === 0) {
                            logMessage('Great news! All images in your library already have alt text.', 'item-success');
                        }
                    }
                },
                error: function() {
                    $('#sogc-total-count').text('Error counting');
                    $('#sogc-missing-count').text('Error counting');
                    if (hasApiKey) $('#sogc-start-btn').prop('disabled', false);
                }
            });

            $('#sogc-start-btn').on('click', function() {
                if (isRunning) return;
                isRunning = true;
                $('#sogc-start-btn').hide();
                $('#sogc-stop-btn').show();
                logMessage('Fetching image queue...', '');
                fetchQueueAndProcess();
            });

            $('#sogc-stop-btn').on('click', function() {
                isRunning = false;
                $('#sogc-stop-btn').hide();
                $('#sogc-start-btn').show().text('Resume Bulk Generation');
                logMessage('Batch processing paused by user.', 'item-skip');
            });

            function fetchQueueAndProcess() {
                if (!isRunning) return;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'sogc_ai_bulk_generate',
                        nonce: '<?php echo wp_create_nonce('sogc_ai_bulk_nonce'); ?>'
                    },
                    success: function(resp) {
                        if (!resp.success) {
                            logMessage('Error: ' + (resp.data ? resp.data.message : 'Unknown error'), 'item-error');
                            isRunning = false;
                            $('#sogc-stop-btn').hide();
                            $('#sogc-start-btn').show();
                            return;
                        }

                        queue = resp.data.queue || [];
                        if (queue.length === 0) {
                            isRunning = false;
                            $('#sogc-stop-btn').hide();
                            $('#sogc-start-btn').show().text('Completed');
                            logMessage('All images already have alt text!', 'item-success');
                            return;
                        }

                        logMessage('Found ' + queue.length + ' images to process in this queue. Analyzing one-by-one...', '');
                        processSingleNext();
                    },
                    error: function(xhr, status, error) {
                        logMessage('Error fetching queue: ' + error, 'item-error');
                        isRunning = false;
                        $('#sogc-stop-btn').hide();
                        $('#sogc-start-btn').show();
                    }
                });
            }

            function processSingleNext() {
                if (!isRunning) return;

                if (queue.length === 0) {
                    fetchQueueAndProcess();
                    return;
                }

                var attId = queue.shift();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    timeout: 45000,
                    data: {
                        action: 'sogc_ai_process_single',
                        attachment_id: attId,
                        nonce: '<?php echo wp_create_nonce('sogc_ai_bulk_nonce'); ?>'
                    },
                    success: function(resp) {
                        var nextDelay = 4200; // Safe default for Gemini free tier (15 RPM limit)

                        if (resp.success && resp.data) {
                            var item = resp.data;
                            if (item.status === 'ok') {
                                processed++;
                                logMessage('[ID ' + item.id + '] ' + item.title + ' -> "' + item.alt + '"', 'item-success');
                            } else if (item.status === 'rate_limit') {
                                // Re-queue the attachment to retry after waiting
                                queue.unshift(attId);
                                var waitSeconds = item.retry_after ? Math.ceil(item.retry_after) : 40;
                                nextDelay = waitSeconds * 1000;
                                logMessage('[ID ' + item.id + '] Rate limit reached (15 RPM free tier). Pausing ' + waitSeconds + 's then retrying...', 'item-skip');
                            } else {
                                processed++;
                                logMessage('[ID ' + item.id + '] ' + item.title + ' -> ' + item.message, 'item-error');
                            }
                        } else {
                            processed++;
                            logMessage('[ID ' + attId + '] Failed: ' + (resp.data ? resp.data.message : 'Unknown error'), 'item-error');
                        }

                        updateProgress();
                        if (isRunning) {
                            setTimeout(processSingleNext, nextDelay);
                        }
                    },
                    error: function(xhr, status, error) {
                        processed++;
                        logMessage('[ID ' + attId + '] Network/Timeout Error: ' + error, 'item-error');
                        updateProgress();
                        if (isRunning) {
                            setTimeout(processSingleNext, 4200);
                        }
                    }
                });
            }

            function updateProgress() {
                var pct = totalToProcess > 0 ? Math.min(100, Math.round((processed / totalToProcess) * 100)) : 100;
                $('#sogc-progress-fill').css('width', pct + '%');
                $('#sogc-progress-text').text(processed + ' / ' + totalToProcess + ' (' + pct + '%)');
                $('#sogc-missing-count').text(Math.max(0, totalToProcess - processed).toLocaleString());
            }

            function logMessage(msg, cssClass) {
                var $log = $('#sogc-log');
                var timestamp = new Date().toLocaleTimeString();
                var $line = $('<div>').text('[' + timestamp + '] ' + msg);
                if (cssClass) $line.addClass(cssClass);
                $log.append($line);
                $log.scrollTop($log[0].scrollHeight);
            }
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to get real-time media counts asynchronously
     */
    public function ajax_get_stats() {
        check_ajax_referer('sogc_ai_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $total   = $this->count_total_images();
        $missing = $this->count_images_missing_alt();

        wp_send_json_success(array(
            'total'   => $total,
            'missing' => $missing,
        ));
    }

    /**
     * AJAX handler for Bulk Generation (Fetch Queue of IDs)
     */
    public function ajax_bulk_generate() {
        check_ajax_referer('sogc_ai_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $opts = $this->get_options();
        if (empty($opts['gemini_api_key'])) {
            wp_send_json_error(array('message' => 'Gemini API Key is missing.'));
        }

        $overwrite = $opts['overwrite_existing'];
        $meta_query = array();
        if (!$overwrite) {
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key'     => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_wp_attachment_image_alt',
                    'value'   => '',
                    'compare' => '=',
                ),
            );
        }

        $query = new WP_Query(array(
            'post_type'      => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png', 'image/webp', 'image/gif'),
            'post_status'    => 'inherit',
            'posts_per_page' => 100, // Fetch up to 100 queue IDs
            'meta_query'     => $meta_query,
            'fields'         => 'ids',
        ));

        wp_send_json_success(array(
            'queue'     => $query->posts,
            'remaining' => $this->count_images_missing_alt(),
        ));
    }

    /**
     * AJAX handler for Single Image Generation (Fast, Real-Time Feedback)
     */
    public function ajax_process_single() {
        check_ajax_referer('sogc_ai_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $att_id = intval($_POST['attachment_id'] ?? 0);
        if (!$att_id) {
            wp_send_json_error(array('message' => 'Invalid attachment ID.'));
        }

        $opts = $this->get_options();
        $result = $this->generate_alt_for_attachment($att_id, $opts);

        wp_send_json_success($result);
    }

    /**
     * Automatic generation on upload hook
     */
    public function handle_image_upload($attachment_id) {
        $opts = $this->get_options();
        if (empty($opts['auto_on_upload']) || empty($opts['gemini_api_key'])) {
            return;
        }

        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }

        // Generate alt text and update description
        $this->generate_alt_for_attachment($attachment_id, $opts);
    }

    /**
     * Core generator: Calls Gemini Vision API & updates attachment
     */
    public function generate_alt_for_attachment($attachment_id, $opts = null) {
        if (!$opts) {
            $opts = $this->get_options();
        }

        $title = get_the_title($attachment_id);
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            return array('id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => 'Local image file not found.');
        }

        $mime_type = wp_check_filetype($file_path)['type'];
        if (!in_array($mime_type, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'))) {
            return array('id' => $attachment_id, 'title' => $title, 'status' => 'skip', 'message' => 'Unsupported MIME type: ' . $mime_type);
        }

        // Read image and base64 encode
        $image_data = file_get_contents($file_path);
        if (!$image_data) {
            return array('id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => 'Unable to read image bytes.');
        }

        // If image is larger than 3MB, resize down in memory to save tokens/bandwidth
        if (strlen($image_data) > 3 * 1024 * 1024) {
            $editor = wp_get_image_editor($file_path);
            if (!is_wp_error($editor)) {
                $editor->resize(1200, 1200, false);
                $temp_file = wp_tempnam();
                $editor->save($temp_file);
                $image_data = file_get_contents($temp_file);
                @unlink($temp_file);
            }
        }

        $base64_image = base64_encode($image_data);

        // Build prompt
        $prompt = str_replace(
            array('{MAX_CHARS}', '{SITE_FOCUS}'),
            array($opts['max_chars'], $opts['site_focus']),
            $opts['custom_prompt']
        );

        // Call Gemini Vision API using standard generateContent
        $model = !empty($opts['gemini_model']) ? $opts['gemini_model'] : 'gemini-3.5-flash-lite';
        $api_key = $opts['gemini_api_key'];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        $payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt),
                        array(
                            'inline_data' => array(
                                'mime_type' => $mime_type,
                                'data'      => $base64_image,
                            )
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'maxOutputTokens' => 150,
            )
        );

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode($payload),
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            return array('id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : 'HTTP ' . $code;

            // Detect Gemini Free Tier 429 Rate Limit
            if ($code === 429 || stripos($err_msg, 'quota exceeded') !== false || stripos($err_msg, 'rate limit') !== false) {
                $retry_after = 40;
                if (preg_match('/retry in\s+([0-9.]+)\s*s/i', $err_msg, $match)) {
                    $retry_after = floatval($match[1]) + 2;
                }
                return array(
                    'id'          => $attachment_id,
                    'title'       => $title,
                    'status'      => 'rate_limit',
                    'retry_after' => $retry_after,
                    'message'     => $err_msg
                );
            }

            return array('id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => $err_msg);
        }

        $generated_alt = '';
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $generated_alt = trim($data['candidates'][0]['content']['parts'][0]['text']);
        } elseif (isset($data['output']['parts'][0]['text'])) {
            $generated_alt = trim($data['output']['parts'][0]['text']);
        } elseif (isset($data['output']['text'])) {
            $generated_alt = trim($data['output']['text']);
        }

        if (empty($generated_alt)) {
            return array('id' => $attachment_id, 'title' => $title, 'status' => 'error', 'message' => 'Empty AI response.');
        }

        // Clean up quotes or prefixes
        $generated_alt = trim($generated_alt, "\"' \t\n\r\0\x0B");
        $generated_alt = preg_replace('/^(Alt text:\s*|Description:\s*)/i', '', $generated_alt);

        // Update WordPress Media Library
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($generated_alt));

        // Also update post_content (attachment description) if empty
        $post = get_post($attachment_id);
        if ($post && empty($post->post_content)) {
            wp_update_post(array(
                'ID'           => $attachment_id,
                'post_content' => sanitize_text_field($generated_alt),
            ));
        }

        return array('id' => $attachment_id, 'title' => $title, 'status' => 'ok', 'alt' => $generated_alt);
    }

    /**
     * Helpers for counting
     */
    public function count_images_missing_alt() {
        global $wpdb;
        $sql = "
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
              AND p.post_mime_type LIKE 'image/%'
              AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ";
        return intval($wpdb->get_var($sql));
    }

    public function count_total_images() {
        global $wpdb;
        $sql = "
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'attachment'
              AND p.post_mime_type LIKE 'image/%'
        ";
        return intval($wpdb->get_var($sql));
    }
}

// Initialize Plugin
add_action('plugins_loaded', array('SOGC_AI_Alt_Text', 'get_instance'));

// Initialize GitHub Auto-Updater
require_once plugin_dir_path(__FILE__) . 'class-sogc-github-updater.php';
new SOGC_GitHub_Plugin_Updater(
    __FILE__,
    'sogc-org/sogc-ai-alt-text',
    '1.0.1'
);
