<?php
/**
 * English Web TR Theme - functions.php
 * Tüm tema özelliklerinin merkezi bootstrap dosyası.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EWT_VERSION', '1.14.1' ); // Öğrenme yolu ve kısa seviye testi.
define( 'EWT_DIR', get_template_directory() );
define( 'EWT_URI', get_template_directory_uri() );

/* -------------------------------------------------
 * TEMA KURULUMU
 * ------------------------------------------------- */
function ewt_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 100,
		'width'       => 320,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus( array(
		'primary' => __( 'Ana Mega Menü', 'englishweb' ),
		'footer'  => __( 'Footer Menü', 'englishweb' ),
	) );
}
add_action( 'after_setup_theme', 'ewt_theme_setup' );

/* -------------------------------------------------
 * SCRIPT & STYLE YÜKLEME
 * ------------------------------------------------- */
function ewt_enqueue_assets() {
	wp_enqueue_style( 'ewt-main', EWT_URI . '/assets/css/main.css', array(), EWT_VERSION );
	wp_enqueue_style( 'ewt-nostalgia-timeline', EWT_URI . '/assets/css/nostalgia-timeline.css', array('ewt-main'), EWT_VERSION );
	wp_enqueue_style( 'ewt-anniversary', EWT_URI . '/assets/css/anniversary.css', array('ewt-main'), EWT_VERSION );
	wp_enqueue_script( 'ewt-anniversary', EWT_URI . '/assets/js/anniversary.js', array(), EWT_VERSION, true );
	wp_enqueue_script( 'ewt-main', EWT_URI . '/assets/js/main.js', array(), EWT_VERSION, true );
	wp_enqueue_script( 'ewt-chatbot', EWT_URI . '/assets/js/chatbot.js', array(), EWT_VERSION, true );

	wp_localize_script( 'ewt-chatbot', 'ewtChatbot', array(
		'phone'     => get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ),
		'whatsapp'  => function_exists( 'ewt_campus_wa' ) ? ewt_campus_wa() : preg_replace( '/[^0-9]/', '', get_option( 'ewt_tutoring_phone', '+905305714067' ) ),
		'siteName'  => get_bloginfo( 'name' ),
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'ewt_lesson_assistant' ),
		'isLesson'  => is_singular( array_merge( array( 'post' ), array_keys( ewt_cpt_config() ) ) ),
		'postId'    => get_queried_object_id(),
		'contactEnabled' => ewt_campus_options()['contact_enabled'] === '1',
	) );
}
add_action( 'wp_enqueue_scripts', 'ewt_enqueue_assets' );

/* -------------------------------------------------
 * DOSYA INCLUDE'LARI
 * ------------------------------------------------- */
require_once EWT_DIR . '/inc/cpt-taxonomies.php';
require_once EWT_DIR . '/inc/mega-menu.php';
require_once EWT_DIR . '/inc/settings-page.php';
require_once EWT_DIR . '/inc/seo-meta-box.php';
require_once EWT_DIR . '/inc/ai-content-widget.php';
require_once EWT_DIR . '/inc/esp-it-generator.php';
require_once EWT_DIR . '/inc/featured-image-widget.php';
require_once EWT_DIR . '/inc/chatbot.php';
require_once EWT_DIR . '/inc/topic-speaking.php';
require_once EWT_DIR . '/inc/learning-path.php';
require_once EWT_DIR . '/inc/placement-test.php';
require_once EWT_DIR . '/inc/bulk-tools.php';
require_once EWT_DIR . '/inc/frontend-generator.php';
require_once EWT_DIR . '/inc/listening-speaking-lab.php';
require_once EWT_DIR . '/inc/template-tags.php';
require_once EWT_DIR . '/inc/site-search.php';
require_once EWT_DIR . '/inc/internal-linking.php';
require_once EWT_DIR . '/inc/url-export.php';
require_once EWT_DIR . '/inc/draft-publish.php';
require_once EWT_DIR . '/inc/ebook-builder.php';
require_once EWT_DIR . '/inc/learning-path-ebook.php';

/* -------------------------------------------------
 * ADMIN PANEL CSS (meta box'lar, ayarlar, AI Araçları sayfaları)
 * ------------------------------------------------- */
function ewt_enqueue_admin_css( $hook ) {
	$relevant = array( 'post.php', 'post-new.php' );
	$is_relevant_hook = in_array( $hook, $relevant, true )
		|| strpos( $hook, 'ewt-settings' ) !== false
		|| strpos( $hook, 'ewt-bulk-content' ) !== false
		|| strpos( $hook, 'ewt-bulk-image' ) !== false
		|| strpos( $hook, 'ewt-search-index' ) !== false
		|| strpos( $hook, 'ewt-link' ) !== false;

	if ( ! $is_relevant_hook ) return;
	wp_enqueue_style( 'ewt-admin', EWT_URI . '/assets/css/admin.css', array(), EWT_VERSION );
}
add_action( 'admin_enqueue_scripts', 'ewt_enqueue_admin_css' );

/* -------------------------------------------------
 * WIDGET ALANLARI
 * ------------------------------------------------- */
function ewt_register_sidebars() {
	register_sidebar( array(
		'name'          => __( 'Blog Kenar Çubuğu', 'englishweb' ),
		'id'            => 'blog-sidebar',
		'description'   => 'Blog listeleme sayfası (Ayarlar > Okuma\'da seçtiğiniz "Yazılar Sayfası") ve tekil blog yazılarında görünür. Ders (CPT) sayfalarında görünmez.',
		'before_widget' => '<div class="ewt-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="ewt-widget-title">',
		'after_title'   => '</h3>',
	) );
	register_sidebar( array(
		'name'          => __( 'Footer Sütun 1', 'englishweb' ),
		'id'            => 'footer-1',
		'before_widget' => '<div class="ewt-footer-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="ewt-footer-title">',
		'after_title'   => '</h4>',
	) );
	register_sidebar( array(
		'name'          => __( 'Footer Sütun 2', 'englishweb' ),
		'id'            => 'footer-2',
		'before_widget' => '<div class="ewt-footer-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="ewt-footer-title">',
		'after_title'   => '</h4>',
	) );
	register_sidebar( array(
		'name'          => __( 'Footer Sütun 3', 'englishweb' ),
		'id'            => 'footer-3',
		'before_widget' => '<div class="ewt-footer-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="ewt-footer-title">',
		'after_title'   => '</h4>',
	) );
}
add_action( 'widgets_init', 'ewt_register_sidebars' );

/* -------------------------------------------------
 * GÜVENLİK: dosya boyutu / editör genişliği
 * ------------------------------------------------- */
add_theme_support( 'editor-styles' );
function ewt_content_width() {
	$GLOBALS['content_width'] = 840;
}
add_action( 'after_setup_theme', 'ewt_content_width', 0 );

require_once EWT_DIR . '/inc/seasonal-campus.php';

require_once EWT_DIR . '/inc/k12-publisher.php';

/* 21. YIL NOSTALJİ ZAMAN TÜNELİ — BAĞIMSIZ SANAL SAYFA */
function ewt_nostalgia_register_route() {
    add_rewrite_rule('^2005ten-bugune/?$', 'index.php?ewt_nostalgia=1', 'top');
}
add_action('init','ewt_nostalgia_register_route');
add_filter('query_vars', function($vars) { $vars[]='ewt_nostalgia'; return $vars; });
add_action('after_switch_theme', function(){ ewt_nostalgia_register_route(); flush_rewrite_rules(); });
add_filter('template_include', function($template){
    if (get_query_var('ewt_nostalgia')) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
        return EWT_DIR . '/page-nostalgia-timeline.php';
    }
    return $template;
}, 99);
add_filter('pre_get_document_title', function($title){
    return get_query_var('ewt_nostalgia') ? '2005’ten Bugüne: English Web Tr 21 Yıllık Yolculuk' : $title;
});
