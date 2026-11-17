<?php
/**
 * Plugin Name:       SOGC FAQ with SEO/AEO Schema (WPML Ready)
 * Description:       Lightweight FAQ custom post type and taxonomy with multilingual schema support, WPML auto-detection, and animated chevron accordion.
 * Version:           1.2.0
 * Author:            SOGC Digital Team
 * Text Domain:       sogc-faq
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOGC_FAQ_Plugin {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type_and_taxonomy' ] );
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_shortcode( 'sogc_faq', [ $this, 'render_faq_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'sogc-faq', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function register_post_type_and_taxonomy() {
        // Taxonomy: faq_category
        $tax_labels = [
            'name'              => _x( 'FAQ Categories', 'taxonomy general name', 'sogc-faq' ),
            'singular_name'     => _x( 'FAQ Category', 'taxonomy singular name', 'sogc-faq' ),
            'search_items'      => __( 'Search FAQ Categories', 'sogc-faq' ),
            'all_items'         => __( 'All FAQ Categories', 'sogc-faq' ),
            'parent_item'       => __( 'Parent FAQ Category', 'sogc-faq' ),
            'parent_item_colon' => __( 'Parent FAQ Category:', 'sogc-faq' ),
            'edit_item'         => __( 'Edit FAQ Category', 'sogc-faq' ),
            'update_item'       => __( 'Update FAQ Category', 'sogc-faq' ),
            'add_new_item'      => __( 'Add New FAQ Category', 'sogc-faq' ),
            'new_item_name'     => __( 'New FAQ Category Name', 'sogc-faq' ),
            'menu_name'         => __( 'Categories', 'sogc-faq' ),
        ];

        register_taxonomy( 'faq_category', [ 'faq' ], [
            'hierarchical'      => true,
            'labels'            => $tax_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'show_in_rest'      => true,
            'rewrite'           => [ 'slug' => 'faq-category', 'with_front' => false ],
        ] );

        // Custom Post Type: faq
        $cpt_labels = [
            'name'               => _x( 'FAQs', 'post type general name', 'sogc-faq' ),
            'singular_name'      => _x( 'FAQ', 'post type singular name', 'sogc-faq' ),
            'menu_name'          => _x( 'FAQs', 'admin menu', 'sogc-faq' ),
            'name_admin_bar'     => _x( 'FAQ', 'add new on admin bar', 'sogc-faq' ),
            'add_new'            => _x( 'Add New', 'faq', 'sogc-faq' ),
            'add_new_item'       => __( 'Add New FAQ Question', 'sogc-faq' ),
            'new_item'           => __( 'New FAQ', 'sogc-faq' ),
            'edit_item'          => __( 'Edit FAQ', 'sogc-faq' ),
            'view_item'          => __( 'View FAQ', 'sogc-faq' ),
            'all_items'          => __( 'All FAQs', 'sogc-faq' ),
            'search_items'       => __( 'Search FAQs', 'sogc-faq' ),
            'not_found'          => __( 'No FAQs found.', 'sogc-faq' ),
            'not_found_in_trash' => __( 'No FAQs found in Trash.', 'sogc-faq' ),
        ];

        register_post_type( 'faq', [
            'labels'             => $cpt_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'faqs', 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-editor-help',
            'supports'           => [ 'title', 'editor', 'revisions' ],
            'taxonomies'         => [ 'faq_category' ],
            'show_in_rest'       => true,
        ] );
    }

    public function register_assets() {
        wp_register_style(
            'sogc-faq-styles',
            plugin_dir_url( __FILE__ ) . 'assets/css/faq-styles.css',
            [],
            '1.2.0'
        );
    }

    public function render_faq_shortcode( $atts ) {
        $attributes = shortcode_atts( [
            'category' => '',
            'count'    => -1,
            'orderby'  => 'menu_order date',
            'order'    => 'ASC',
        ], $atts, 'sogc_faq' );

        $current_lang = apply_filters( 'wpml_current_language', null );
        $in_language  = ( $current_lang === 'fr' ) ? 'fr-CA' : 'en-CA';

        $query_args = [
            'post_type'        => 'faq',
            'posts_per_page'   => intval( $attributes['count'] ),
            'orderby'          => sanitize_text_field( $attributes['orderby'] ),
            'order'            => sanitize_text_field( $attributes['order'] ),
            'post_status'      => 'publish',
            'suppress_filters' => false,
        ];

        if ( ! empty( $attributes['category'] ) ) {
            $category_slug = sanitize_title( $attributes['category'] );
            $term          = get_term_by( 'slug', $category_slug, 'faq_category' );

            if ( $term && ! is_wp_error( $term ) ) {
                $translated_term_id = apply_filters( 'wpml_object_id', $term->term_id, 'faq_category', true, $current_lang );
                $query_args['tax_query'] = [
                    [
                        'taxonomy' => 'faq_category',
                        'field'    => 'term_id',
                        'terms'    => $translated_term_id ? (int) $translated_term_id : $term->term_id,
                    ],
                ];
            } else {
                $query_args['tax_query'] = [
                    [
                        'taxonomy' => 'faq_category',
                        'field'    => 'slug',
                        'terms'    => $category_slug,
                    ],
                ];
            }
        }

        $faqs_query = new WP_Query( $query_args );

        if ( ! $faqs_query->have_posts() ) {
            return '';
        }

        wp_enqueue_style( 'sogc-faq-styles' );

        $faq_schema_items = [];
        $item_index       = 0;
        ob_start();
        ?>
        <div class="sogc-faq-container" itemscope itemtype="https://schema.org/FAQPage">
            <?php
            while ( $faqs_query->have_posts() ) :
                $faqs_query->the_post();

                $question     = get_the_title();
                $raw_content  = get_the_content();
                $answer_html  = apply_filters( 'the_content', $raw_content );
                $plain_answer = wp_strip_all_tags( $answer_html );
                $is_first     = ( 0 === $item_index );

                $faq_schema_items[] = [
                    '@type'          => 'Question',
                    'name'           => esc_html( wp_strip_all_tags( $question ) ),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $plain_answer,
                    ],
                ];
                ?>
                <details class="sogc-faq-item" <?php echo $is_first ? 'open' : ''; ?> itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <summary class="sogc-faq-question" itemprop="name">
                        <span class="sogc-faq-question-text"><?php echo esc_html( $question ); ?></span>
                        <span class="sogc-faq-chevron" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </summary>
                    <div class="sogc-faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <div class="sogc-faq-answer-inner">
                            <div class="sogc-faq-answer-content" itemprop="text">
                                <?php echo $answer_html; ?>
                            </div>
                        </div>
                    </div>
                </details>
            <?php
                $item_index++;
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
        <?php
        $html_output = ob_get_clean();

        $org_name = ( $current_lang === 'fr' )
            ? 'La Société des obstétriciens et gynécologues du Canada (SOGC)'
            : 'The Society of Obstetricians and Gynaecologists of Canada (SOGC)';

        $schema_payload = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'inLanguage' => $in_language,
            'isPartOf'   => [
                '@type' => 'Organization',
                '@id'   => 'https://sogc.org/#organization',
                'name'  => esc_html( $org_name ),
                'url'   => 'https://sogc.org',
            ],
            'mainEntity' => $faq_schema_items,
        ];

        $json_ld = '<script type="application/ld+json">' . wp_json_encode( $schema_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';

        return $html_output . "\n" . $json_ld;
    }
}

new SOGC_FAQ_Plugin();