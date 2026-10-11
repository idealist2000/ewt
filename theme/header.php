<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="ewt-skip-link screen-reader-text" href="#ewt-main-content">İçeriğe geç</a>

<div class="ewt-topbar">
	<div class="ewt-container ewt-topbar-inner">
		<span class="ewt-topbar-item">✨ A1'den C2'ye, TOEFL'dan GMAT'a — tek adres</span>
		<a class="ewt-topbar-item ewt-topbar-phone" href="tel:<?php echo esc_attr( get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ) ); ?>">📞 Özel Ders: <?php echo esc_html( get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ) ); ?></a>
	</div>
</div>

<header id="ewt-site-header" class="ewt-site-header">
	<div class="ewt-header-inner">
		<div class="ewt-logo-wrap">
			<?php ewt_campus_logo(); ?>
		</div>

		<nav class="ewt-nav" aria-label="Ana Menü">
			<?php ewt_render_mega_menu(); ?>
		</nav>

		<button type="button" id="ewt-search-trigger" class="ewt-search-trigger" aria-label="Sitede ara" aria-haspopup="dialog" aria-controls="ewt-search-overlay">
			<span aria-hidden="true">🔍</span>
		</button>

		<a href="tel:<?php echo esc_attr( get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ) ); ?>" class="ewt-header-cta">📞 Özel Ders Al</a>

		<button id="ewt-mobile-toggle" class="ewt-mobile-toggle" aria-expanded="false" aria-controls="ewt-primary-menu" aria-label="Menüyü aç/kapat">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>

<?php ewt_path_render_bar(); ?>

<aside id="ewt-anniversary" class="ewt-anniversary" aria-label="English Web Tr 21. yıl dönümü" hidden></aside>

<main id="ewt-main-content" class="ewt-main-content">
	<?php ewt_breadcrumb(); ewt_campus_countdown(); ?>
