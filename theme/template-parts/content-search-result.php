<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Tam sonuç sayfasında (search.php) tek bir sonucu kart olarak basar.
 * $args['row'] => ewt_search_query()'den gelen ilgili satır (excerpt/cpt_label için).
 */
$row       = isset( $args['row'] ) ? $args['row'] : null;
$post_type = get_post_type();
?>
<article class="ewt-card ewt-search-result-card">
	<a href="<?php the_permalink(); ?>" class="ewt-card-thumb-link">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'medium_large', array( 'class' => 'ewt-card-thumb' ) ); ?>
		<?php else : ?>
			<div class="ewt-card-thumb ewt-card-thumb-placeholder"><span class="dashicons dashicons-book-alt"></span></div>
		<?php endif; ?>
	</a>
	<div class="ewt-card-body">
		<?php if ( $row && ! empty( $row->cpt_label ) ) : ?>
			<span class="ewt-badge-cpt"><?php echo esc_html( $row->cpt_label ); ?></span>
		<?php endif; ?>
		<?php ewt_print_level_badges( get_the_ID(), $post_type ); ?>
		<h3 class="ewt-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="ewt-card-excerpt">
			<?php echo esc_html( ( $row && ! empty( $row->excerpt ) ) ? $row->excerpt : wp_trim_words( get_the_excerpt(), 18 ) ); ?>
		</p>
		<p class="ewt-card-date"><?php echo esc_html( get_the_date() ); ?></p>
	</div>
</article>
