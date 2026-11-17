<?php
/**
 * SOGC GitHub Plugin Updater
 * 
 * Enables standard WordPress in-dashboard update notifications and 1-click
 * updates for private or public plugins hosted in the sogc-org GitHub organization.
 * 
 * @package SOGC_Plugins
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SOGC_GitHub_Plugin_Updater')) {

    class SOGC_GitHub_Plugin_Updater {

        private $plugin_file;
        private $plugin_slug;
        private $github_repo;       // Format: 'owner/repo' e.g. 'sogc-org/sogc-wp-plugins'
        private $github_subfolder;  // Subfolder within repo e.g. 'plugins/sogc-ai-alt-text'
        private $current_version;
        private $access_token;
        private $github_api_result = null;

        /**
         * Constructor
         * 
         * @param string $plugin_file Main plugin file (__FILE__)
         * @param string $github_repo 'owner/repo' on GitHub
         * @param string $current_version Current installed plugin version
         * @param string $github_subfolder Subfolder inside repo (or empty if root)
         * @param string $access_token Optional GitHub Personal Access Token (for private repos)
         */
        public function __construct($plugin_file, $github_repo, $current_version, $github_subfolder = '', $access_token = '') {
            $this->plugin_file      = $plugin_file;
            $this->plugin_slug      = plugin_basename($plugin_file);
            $this->github_repo      = trim($github_repo, '/');
            $this->current_version  = $current_version;
            $this->github_subfolder = trim($github_subfolder, '/');
            
            // Check for access token: passed in, via constant, or via option
            if (!empty($access_token)) {
                $this->access_token = $access_token;
            } elseif (defined('SOGC_GITHUB_TOKEN') && SOGC_GITHUB_TOKEN) {
                $this->access_token = SOGC_GITHUB_TOKEN;
            } else {
                $this->access_token = get_option('sogc_github_access_token', '');
            }

            // Hook into WordPress update transients and plugin details popup
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
            add_filter('plugins_api', array($this, 'plugin_popup_details'), 20, 3);
            add_filter('upgrader_source_selection', array($this, 'fix_source_directory'), 10, 4);
        }

        /**
         * Query GitHub API for the latest release
         */
        private function get_latest_release() {
            if ($this->github_api_result !== null) {
                return $this->github_api_result;
            }

            $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
            $args = array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                ),
            );

            if (!empty($this->access_token)) {
                $args['headers']['Authorization'] = 'Bearer ' . $this->access_token;
            }

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $this->github_api_result = false;
                return false;
            }

            $data = json_decode(wp_remote_retrieve_body($response));
            if (!empty($data) && isset($data->tag_name)) {
                $this->github_api_result = $data;
                return $data;
            }

            $this->github_api_result = false;
            return false;
        }

        /**
         * Check if a newer version is available on GitHub
         */
        public function check_for_update($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }

            $release = $this->get_latest_release();
            if (!$release) {
                return $transient;
            }

            // Clean version tag (strip leading 'v' e.g. 'v1.1.0' -> '1.1.0')
            $remote_version = ltrim($release->tag_name, 'vV');

            if (version_compare($this->current_version, $remote_version, '<')) {
                // Determine zipball package URL
                $download_url = $release->zipball_url;
                if (!empty($this->access_token)) {
                    // For private repos, query param or header needed
                    $download_url = add_query_arg('access_token', $this->access_token, $download_url);
                }

                $package = array(
                    'slug'        => dirname($this->plugin_slug),
                    'plugin'      => $this->plugin_slug,
                    'new_version' => $remote_version,
                    'url'         => $release->html_url,
                    'package'     => $download_url,
                    'icons'       => array(),
                    'banners'     => array(),
                    'tested'      => get_bloginfo('version'),
                    'requires_php'=> '7.4',
                );

                $transient->response[$this->plugin_slug] = (object) $package;
            }

            return $transient;
        }

        /**
         * Supply release details for the "View version details" modal in wp-admin
         */
        public function plugin_popup_details($result, $action, $args) {
            if ($action !== 'plugin_information' || empty($args->slug)) {
                return $result;
            }

            if ($args->slug !== dirname($this->plugin_slug) && $args->slug !== $this->plugin_slug) {
                return $result;
            }

            $release = $this->get_latest_release();
            if (!$release) {
                return $result;
            }

            $remote_version = ltrim($release->tag_name, 'vV');

            $info = new stdClass();
            $info->name           = dirname($this->plugin_slug);
            $info->slug           = dirname($this->plugin_slug);
            $info->version        = $remote_version;
            $info->author         = '<a href="https://sogc.org">Society of Obstetricians and Gynaecologists of Canada (SOGC)</a>';
            $info->homepage       = $release->html_url;
            $info->requires       = '5.6';
            $info->tested         = get_bloginfo('version');
            $info->downloaded     = 0;
            $info->last_updated   = $release->published_at;
            $info->sections       = array(
                'description' => !empty($release->body) ? nl2br(esc_html($release->body)) : 'Latest release from SOGC GitHub repository.',
                'changelog'   => !empty($release->body) ? nl2br(esc_html($release->body)) : 'See GitHub releases for changelog.',
            );
            $info->download_link  = $release->zipball_url;

            return $info;
        }

        /**
         * Correct folder structure if GitHub zipped a monorepo or prefixed commit hash
         */
        public function fix_source_directory($source, $remote_source, $upgrader, $hook_extra = null) {
            global $wp_filesystem;

            // Only run for our plugin
            if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
                return $source;
            }

            $plugin_dir_name = dirname($this->plugin_slug);

            // If the plugin lives in a subfolder inside the repo (e.g. plugins/sogc-ai-alt-text)
            if (!empty($this->github_subfolder)) {
                $sub_path = trailingslashit($source) . $this->github_subfolder;
                if ($wp_filesystem->is_dir($sub_path)) {
                    $correct_dir = trailingslashit($remote_source) . $plugin_dir_name;
                    $wp_filesystem->move($sub_path, $correct_dir);
                    return trailingslashit($correct_dir);
                }
            }

            // Normal single repo rename (GitHub gives folder name like 'owner-repo-commit')
            $correct_dir = trailingslashit($remote_source) . $plugin_dir_name;
            if ($source !== $correct_dir) {
                $wp_filesystem->move($source, $correct_dir);
                return trailingslashit($correct_dir);
            }

            return $source;
        }
    }
}
