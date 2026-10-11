<?php
/**
 * Blog kenar çubuğu (sadece normal blog bölümünde kullanılır: home.php ve single.php'de post_type='post' için).
 * Ders (CPT) sayfaları bilinçli olarak tam genişlik kalır, bu şablonu kullanmaz.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<aside class="ewt-sidebar" aria-label="Kenar Çubuğu">
	<?php if ( is_active_sidebar( 'blog-sidebar' ) ) : ?>
		<?php dynamic_sidebar( 'blog-sidebar' ); ?>
	<?php else : ?>
		<!-- Görünüm > Widget'lar > "Blog Kenar Çubuğu" alanına widget eklediğinizde bu varsayılan içeriğin yerini alır -->
		<div class="ewt-widget">
			<h3 class="ewt-widget-title">Ara</h3>
			<?php get_search_form(); ?>
		</div>
		<div class="ewt-widget">
			<h3 class="ewt-widget-title">Son Yazılar</h3>
			<ul>
				<?php
				$ewt_recent = wp_get_recent_posts( array( 'numberposts' => 5, 'post_status' => 'publish' ) );
				foreach ( $ewt_recent as $ewt_p ) {
					echo '<li><a href="' . esc_url( get_permalink( $ewt_p['ID'] ) ) . '">' . esc_html( $ewt_p['post_title'] ) . '</a></li>';
				}
				?>
			</ul>
		</div>
		<div class="ewt-widget">
			<h3 class="ewt-widget-title">Kategoriler</h3>
			<ul>
				<?php wp_list_categories( array( 'title_li' => '', 'show_count' => true ) ); ?>
			</ul>
		</div>
		<div class="ewt-widget ewt-widget-cta">
			<h3 class="ewt-widget-title">Özel Ders</h3>
			<p>Online veya KDZ Ereğli'de birebir İngilizce dersi al.</p>
			<a class="ewt-cta-button" href="tel:<?php echo esc_attr( get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ) ); ?>">📞 Ara</a>
		</div>
	<?php endif; ?>
</aside>
