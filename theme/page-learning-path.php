<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
$p = ewt_path_preferences();
$page = isset( $_GET['ewt_page'] ) && is_scalar( $_GET['ewt_page'] ) ? max( 1, absint( $_GET['ewt_page'] ) ) : 1;
$results = ewt_path_query( $p, $page );
?>
<section class="ewt-container ewt-path-results" aria-labelledby="ewt-path-results-title">
	<h1 id="ewt-path-results-title">Sana uygun İngilizce içerikler</h1>
	<p><?php echo esc_html( strtoupper( $p['level'] ) . ' · ' . ewt_path_audiences()[ $p['audience'] ] . ' · ' . ewt_path_goals()[ $p['goal'] ] . ( $p['target'] ? ' · ' . ewt_path_targets( $p['goal'] )[ $p['target'] ] : '' ) ); ?></p>
	<p><?php echo esc_html( 'easy' === $p['order'] ? 'Bilinen seviyeler A1’den C2’ye sıralanır; aynı seviyede hedefine en ilgili içerik önce gelir. Seviyesi belirtilmemiş içerikler sondadır.' : 'Sınav/amaç, hedef, eğitim grubu ve seçtiğin seviyeye yakınlık birlikte değerlendirilir. Daha az ilgili içerikler listenin devamında yer alır.' ); ?> Eşleştirme yayınlanmış içeriklerin etiketlerine dayanır; puan veya sınav başarısı garantisi vermez.</p>
	<p><?php echo esc_html( number_format_i18n( $results->found_posts ) ); ?> içerik</p>
	<?php if ( $results->found_posts && ewt_ebook_enabled() ) ewt_ebook_path_controls( $p, $results->found_posts ); ?>
	<?php if ( $results->have_posts() ) : ?>
		<div class="ewt-path-cards">
		<?php while ( $results->have_posts() ) : $results->the_post(); ?>
			<article class="ewt-path-card">
				<p class="ewt-path-badge"><?php echo esc_html( ewt_path_post_level( get_the_ID() ) . ' · ' . get_post_type_object( get_post_type() )->labels->singular_name ); ?></p>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_the_excerpt() ) ), 30 ) ); ?></p>
				<a href="<?php the_permalink(); ?>">İçeriği incele →</a>
			</article>
		<?php endwhile; wp_reset_postdata(); ?>
		</div>
		<nav class="ewt-path-pagination" aria-label="Öneri sayfaları"><?php
		$base = add_query_arg( array( 'ewt_path' => 1, 'ewt_level' => $p['level'], 'ewt_audience' => $p['audience'], 'ewt_goal' => $p['goal'], 'ewt_target' => $p['target'], 'ewt_order' => $p['order'], 'ewt_page' => 999999 ), home_url( '/' ) );
		echo wp_kses_post( paginate_links( array( 'base' => str_replace( '999999', '%#%', $base ), 'format' => '', 'current' => $page, 'total' => $results->max_num_pages, 'prev_text' => '← Önceki', 'next_text' => 'Sonraki →' ) ) );
		?></nav>
	<?php else : ?>
		<p>Bu sayfada henüz içerik yok. <?php if ( $page > 1 ) : ?><a href="<?php echo esc_url( remove_query_arg( 'ewt_page' ) ); ?>">İlk öneri sayfasına dön.</a><?php else : ?>Yeni dersler yayımlandığında burada görünecek.<?php endif; ?></p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>
