<?php
/**
 * home.php — Blog listeleme şablonu.
 *
 * WordPress bunu şu durumda kullanır: Ayarlar > Okuma'da "Anasayfanızda gösterilecek içerik"
 * için "Statik bir sayfa" seçip, Anasayfa'dan AYRI bir "Yazılar Sayfası" belirlediğinizde
 * (örn. Anasayfa = "Anasayfa", Yazılar Sayfası = "Blog"). Bu sayede özel tasarımlı anasayfanız
 * (front-page.php) ile normal blog listeniz (kenar çubuklu) birbirinden tamamen ayrışır.
 *
 * "Anasayfanızda gösterilecek içerik: Son yazılarım" seçiliyse ve ayrı bir statik anasayfa
 * tanımlamadıysanız, front-page.php zaten anasayfa için devreye girer; bu dosya o durumda kullanılmaz.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="ewt-container">
	<div class="ewt-layout-with-sidebar">
		<div class="ewt-layout-main">
			<header class="ewt-archive-header ewt-archive-header--inline">
				<h1 class="ewt-archive-title"><?php single_post_title(); ?></h1>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="ewt-post-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php get_template_part( 'template-parts/content-cpt' ); ?>
				<?php endwhile; ?>
				</div>
				<div class="ewt-pagination"><?php the_posts_pagination(); ?></div>
			<?php else : ?>
				<p>Henüz blog yazısı yok.</p>
			<?php endif; ?>
		</div>

		<?php get_sidebar(); ?>
	</div>
</div>
<?php get_footer(); ?>
