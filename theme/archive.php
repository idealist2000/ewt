<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

// Normal blog bölümüne ait klasik arşivler (kategori/etiket/yazar/tarih) kenar çubuklu gösterilir.
// Ders (CPT) arşivleri ve onların "Seviye" taksonomi sayfaları bilinçli olarak tam genişlik kalır.
$ewt_is_classic_blog_archive = is_category() || is_tag() || is_author() || is_date();
?>
<div class="ewt-container">
	<?php if ( $ewt_is_classic_blog_archive ) : ?>
	<div class="ewt-layout-with-sidebar">
		<div class="ewt-layout-main">
	<?php endif; ?>

	<header class="ewt-archive-header<?php echo $ewt_is_classic_blog_archive ? ' ewt-archive-header--inline' : ''; ?>">
		<h1 class="ewt-archive-title">
			<?php
			if ( is_post_type_archive() ) {
				post_type_archive_title();
			} elseif ( is_tax() ) {
				single_term_title();
			} else {
				the_archive_title();
			}
			?>
		</h1>
		<?php if ( ! $ewt_is_classic_blog_archive ) : ?>
			<p class="ewt-archive-note">İçerikler eskiden yeniye doğru sıralanmıştır — konuyu ilk yazıdan takip edebilirsiniz.</p>
		<?php endif; ?>
	</header>

	<div class="ewt-content-area">
		<?php if ( have_posts() ) : ?>
			<div class="ewt-post-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'template-parts/content-cpt' ); ?>
			<?php endwhile; ?>
			</div>
			<div class="ewt-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p>Bu kategoride henüz içerik yok.</p>
		<?php endif; ?>
	</div>

	<?php if ( $ewt_is_classic_blog_archive ) : ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
