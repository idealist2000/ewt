<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
	$post_type    = get_post_type();
	$with_sidebar = ( $post_type === 'post' ); // Sadece normal blog yazıları kenar çubuklu; ders (CPT) sayfaları tam genişlik kalır.
	?>
	<?php if ( $with_sidebar ) : ?>
	<div class="ewt-container">
		<div class="ewt-layout-with-sidebar">
			<div class="ewt-layout-main">
	<?php endif; ?>

	<article class="ewt-single<?php echo $with_sidebar ? '' : ' ewt-container'; ?>">
		<header class="ewt-single-header">
			<?php ewt_print_level_badges( get_the_ID(), $post_type ); ?>
			<h1 class="ewt-single-title"><?php the_title(); ?></h1>
			<p class="ewt-single-meta">
				<?php echo esc_html( get_the_date() ); ?>
				<?php if ( get_post_type() === 'post' ) echo ' &middot; ' . esc_html( get_the_author() ); ?>
			</p>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="ewt-single-thumb"><?php the_post_thumbnail( 'large' ); ?></div>
		<?php endif; ?>

		<?php
		$ewt_rendered = apply_filters( 'the_content', get_the_content() );
		$ewt_toc_data = ewt_add_toc_anchors_and_build( $ewt_rendered );
		?>
		<?php if ( $ewt_toc_data['toc'] ) echo $ewt_toc_data['toc']; ?>

		<div class="ewt-single-content">
			<?php echo $ewt_toc_data['content']; ?>
		</div>

		<?php ewt_topic_speaking_render(); ?>
		<div class="ewt-single-cta">
			<h3>Bu konuda birebir çalışmak ister misin?</h3>
			<p>Online veya KDZ Ereğli'de yüz yüze özel İngilizce dersi için hemen iletişime geç.</p>
			<a class="ewt-cta-button" href="tel:<?php echo esc_attr( get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ) ); ?>">📞 Özel Ders İçin Ara</a>
		</div>

		<?php if ( comments_open() || get_comments_number() ) comments_template(); ?>
	</article>

	<?php if ( $with_sidebar ) : ?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	</div>
	<?php endif; ?>
	<?php
endwhile;
get_footer();
