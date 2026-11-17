<?php
/**
 * Template Name: FAQ Page
 * Description: Dedicated FAQ page template with standard editor content/shortcode support, styled to match the look of the existing /faq/ questions.
 *
 * @package FoundationPress
 * @since FoundationPress 1.0.0
 */

// - custom js
wp_enqueue_script('', get_template_directory_uri() . '/assets/javascript/templates/page-default.min.js', array('app'), '1.0', true);

// - determine parent section id, if any
$siblings = array();
$sibling_nav_areas = array(
    'banner' => '',
    'end' => '',
);
if ($ancestors = get_post_ancestors($post->ID)) {
    $parent_section_id = $ancestors[0];

    $siblings = get_pages(array(
        'sort_order' => 'asc',
        'sort_column' => 'menu_order',
        'parent' => $parent_section_id,
    ));

    if ($siblings) {
        foreach ($siblings as $i => $sibling) {
            if ($sibling->ID == $post->ID) {
                break;
            }
        }
    }

    // -- create 2 sibling navigation areas (one for the banner, one for the end of the page)
    if ($siblings) {
        foreach (array_keys($sibling_nav_areas) as $key) {
            $sibling_nav_areas[$key] .= '
            <div class="sibling-nav '.$key.'">
                <button class="dropdown button" type="button" data-toggle="sibling-nav-dropdown-'.$key.'">'.sprintf(__('More about %s', 'foundationpress'), get_the_title($parent_section_id)).'</button>
                <div class="dropdown-pane" id="sibling-nav-dropdown-'.$key.'" data-dropdown data-hover="true" data-hover-pane="true">
                    <ul>';
                    foreach ($siblings as $sibling) {
                        if ($sibling->ID == $post->ID) {
                            $sibling_nav_areas[$key] .= '
                            <li><a class="mrcurrentsibling" href="'.get_permalink($sibling).'">'.$sibling->post_title.'</a></li>';
                        } else {
                            $sibling_nav_areas[$key] .= '
                            <li><a href="'.get_permalink($sibling).'">'.$sibling->post_title.'</a></li>';
                        }
                    }
                    $sibling_nav_areas[$key] .= '
                    </ul>
                </div>
            </div>';
        }
    }

    // -- inherit theme colour, if possible
    if ($theme_color = get_field('theme_color', $parent_section_id)) {
        add_body_class($theme_color);
    }
}

// - header
get_header();

// - page banner
$banner = array(
    'image' => get_field('page_banner_image'),
    'image_wide' => get_field('page_banner_image_wide'),
);
$banner_content = get_field('page_banner_content');

if ($banner['image'] && $banner['image_wide']) {
    $banner_style = 'background-image:url('.$banner['image_wide']['url'].');';

    echo '
<div id="page-banner" role="region" aria-label="Page Banner" style="'.$banner_style.'">
    <img src="'.$banner['image']['url'].'" alt="'.$banner['image']['alt'].'" class="banner-image">
    <img src="'.$banner['image_wide']['url'].'" alt="'.$banner['image_wide']['alt'].'" class="banner-image-wide">

    <div class="page-banner-content">
        <div class="wrapper">
            <div class="inner">';?>
            
               <?php 
    $current = $post->ID;
    $parent = $post->post_parent;
    $grandparent_get = get_post($parent);
    $grandparent = $grandparent_get ? $grandparent_get->post_parent : 0;
    ?>
   <h1>  
       <div id="title-bg-wrapper"></div>
       <ul id="page_titles">
           <?php print_page_parents($reverse = true);?>
       </ul>             
   </h1>
                
                <?php echo '
                <div class="content">
                    '.$banner_content.'
                </div>
            </div>
        </div>
    </div>
  
</div>';
}

// - content box
?>

<div id="content">

<div class="wrapper has-2 split-4-8">
    <div class="breadcrumb-wrapper">
        <?php foundationpress_breadcrumb(); ?>
    </div>

    <div class="content col textContent">
        <h2><?php single_post_title(); ?></h2>

        <?php if ( has_excerpt() ) : ?>
            <div class="page-excerpt" style="color: #181818; font-size: 20px; line-height: 28px; font-weight: 400; margin-top: 15px; margin-bottom: 20px;">
                <?php the_excerpt(); ?>
            </div>
        <?php endif; ?>

        <div class="clear"></div>

        <style>
                      .wrapper {
    max-width: 71.25rem;
    margin: 0 auto;
    position: relative;
}
   
            /* Match the typography and style of the existing /faq/ questions */
            .faq-content .sogc-faq-container {
                max-width: 100% !important;
                margin-top: 20px !important;
            }
            .faq-content .sogc-faq-item {
                border: none !important;
                border-bottom: 1px solid #eaeaea !important;
                border-radius: 0 !important;
                background: transparent !important;
                margin-bottom: 24px !important;
                padding-bottom: 18px !important;
                box-shadow: none !important;
            }
            .faq-content .sogc-faq-question {
                font-family: inherit !important;
                font-size: 18pt !important;
                font-weight: 700 !important;
                color: #2b2b2b !important;
                line-height: 1.35 !important;
                padding: 0 0 12px 0 !important;
                background: transparent !important;
                cursor: pointer !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
            }
            .faq-content .sogc-faq-question-text {
                font-size: 18pt !important;
                font-weight: 700 !important;
                color: #2b2b2b !important;
                line-height: 1.35 !important;
            }
            .faq-content .sogc-faq-question:hover .sogc-faq-question-text {
                color: #575194 !important;
            }
            .faq-content .sogc-faq-chevron {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 28px !important;
                height: 28px !important;
                flex-shrink: 0 !important;
                color: #7468A8 !important;
                margin-left: 12px !important;
                transition: transform 0.2s ease !important;
            }
            .faq-content .sogc-faq-item[open] .sogc-faq-chevron {
                transform: rotate(180deg) !important;
            }
            .faq-content .sogc-faq-answer {
                padding: 6px 0 12px 0 !important;
                font-size: 16px !important;
                line-height: 1.65 !important;
                color: #404040 !important;
            }
            .faq-content .sogc-faq-answer p {
                font-size: 16px !important;
                line-height: 1.65 !important;
                margin-bottom: 14px !important;
                color: #404040 !important;
            }
             
            .faq-content .sogc-faq-answer li {
                font-size: 16px !important;
                line-height: 1.65 !important;
                margin-bottom: 6px !important;
                color: #404040 !important;
            }
            .faq-content .sogc-faq-answer a {
                color: #575194 !important;
                text-decoration: underline !important;
            }
            .faq-content .sogc-faq-answer a:hover {
                color: #22282b !important;
            }
        </style>

        <div class="entry-content faq-content">
            <?php echo apply_filters( 'the_content', $post->post_content ); ?>
        </div>
       
        <!-- Mobile ad -->
        <?php if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE == "fr" ) : ?>
            <div id="subpageadmobilefr" class="mobile-only" style="margin-top: 36px; margin-left:5px;"></div>
        <?php else : ?>
            <div id="subpageadmobile" class="mobile-only" style="margin-top: 36px; margin-left:5px;"></div>
        <?php endif; ?>
        <!-- End mobile ad -->      
       
    </div>

    <div class="content col sidebar">

        <ul id="sidebar_titles">
            <?php print_page_parents($reverse = true);?>
        </ul>

        <ul>
            <?php 
            $parent = get_post_ancestors($post->ID);
            $lastNum = end($parent);
            if ($lastNum) {
                wp_list_pages("title_li=&child_of=".$lastNum);
            }
            ?>
        </ul>
        <div class="clear"></div>
        
        <!-- Sidebar ad -->
        <div class="sogcads ads">
            <?php if ( function_exists( 'the_ad_placement' ) ) { the_ad_placement( 'sidebar-ad' ); } ?>
        </div>
        <!-- End Sidebar ad -->       
    </div>
    
</div>

</div>
<script>
    jQuery(document).ready(function($) {
       $('body').addClass('page-template-default');
    });
    </script>
<?php
// - features slider
get_template_part('template-parts/feature-slider');

// - button bar
get_template_part('template-parts/button-bar');

// - footer
get_footer();
