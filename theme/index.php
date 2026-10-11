<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="ewt-container">
	<div class="ewt-content-area">
		<?php if ( have_posts() ) : ?>
			<div class="ewt-post-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'template-parts/content-cpt' ); ?>
			<?php endwhile; ?>
			</div>
			<div class="ewt-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p>Herhangi bir içerik bulunamadı.</p>
		<?php endif; ?>
	</div>
</div>
<?php get_footer(); ?>
