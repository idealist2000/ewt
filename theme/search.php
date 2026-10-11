<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * TAM ARAMA SONUÇLARI SAYFASI
 *
 * WordPress'in standart `?s=` aramasını (get_search_form() ile üretilen) yakalar,
 * ancak wp_posts üzerinde yavaş bir LIKE taraması yerine aynı hızlı arama
 * indeksini (bkz. inc/site-search.php -> ewt_search_query()) kullanır.
 * Böylece başlık kutusundaki anlık öneriler ile bu sayfadaki sonuçlar
 * birebir aynı sıralama mantığını (relevans skoru) paylaşır.
 */
get_header();

$s        = get_search_query();
$per_page = 12;
$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$offset   = ( $paged - 1 ) * $per_page;

$data  = ( $s !== '' ) ? ewt_search_query( $s, array( 'limit' => $per_page, 'offset' => $offset ) ) : array( 'rows' => array(), 'total' => 0 );
$ids   = wp_list_pluck( $data['rows'], 'id' );
$total = $data['total'];
$total_pages = $total ? (int) ceil( $total / $per_page ) : 0;
?>

<div class="ewt-container ewt-search-results-page">
	<header class="ewt-archive-header">
		<h1 class="ewt-archive-title">
			<?php echo $s !== '' ? sprintf( '“%s” için arama sonuçları', esc_html( $s ) ) : 'Site İçi Arama'; ?>
		</h1>
		<?php get_search_form(); ?>
		<?php if ( $s !== '' ) : ?>
			<p class="ewt-search-results-count"><?php echo (int) $total; ?> sonuç bulundu.</p>
		<?php endif; ?>
	</header>

	<div class="ewt-content-area">
		<?php if ( $s === '' ) : ?>
			<p>Aramak istediğiniz kelimeyi yukarıya yazın.</p>

		<?php elseif ( empty( $ids ) ) : ?>
			<div class="ewt-search-empty">
				<p>😕 “<?php echo esc_html( $s ); ?>” için bir sonuç bulamadık.</p>
				<ul>
					<li>Yazımı kontrol edin ya da daha kısa/genel bir kelime deneyin</li>
					<li>Örnek: “present perfect”, “a1 kelimeler”, “toefl okuma”</li>
				</ul>
			</div>

		<?php else :
			$rows_by_id = array();
			foreach ( $data['rows'] as $row ) $rows_by_id[ (int) $row->id ] = $row;

			$result_query = new WP_Query( array(
				'post_type'           => 'any',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'ignore_sticky_posts' => true,
			) );
			?>
			<div class="ewt-post-grid">
				<?php
				while ( $result_query->have_posts() ) :
					$result_query->the_post();
					$row = isset( $rows_by_id[ get_the_ID() ] ) ? $rows_by_id[ get_the_ID() ] : null;
					get_template_part( 'template-parts/content-search-result', null, array( 'row' => $row ) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<nav class="ewt-pagination" aria-label="Sayfalar">
					<?php if ( $paged > 1 ) : ?>
						<a class="page-numbers" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>">← Önceki</a>
					<?php endif; ?>
					<span class="page-numbers current"><?php echo (int) $paged; ?> / <?php echo (int) $total_pages; ?></span>
					<?php if ( $paged < $total_pages ) : ?>
						<a class="page-numbers" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>">Sonraki →</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
