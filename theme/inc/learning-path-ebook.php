<?php
/** Link-only learning-path books using the existing public ebook pipeline. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ewt_ebook_collect_path( $input ) {
	$p = ewt_path_preferences( $input );
	$query = ewt_path_query( $p, 1, ewt_ebook_max_posts() );
	$chapters = ewt_ebook_collect_posts( wp_list_pluck( $query->posts, 'ID' ), true );
	if ( is_wp_error( $chapters ) ) return $chapters;
	return array(
		'chapters' => $chapters,
		'preferences' => $p,
		'title' => strtoupper( $p['level'] ) . ' ' . ewt_path_goals()[ $p['goal'] ] . ' Öğrenme Yolu',
	);
}
function ewt_ebook_path_controls( $p, $total ) {
	$limit = min( (int) $total, ewt_ebook_max_posts() );
	?>
	<aside class="ewt-path-ebook" aria-labelledby="ewt-path-ebook-heading">
		<h2 id="ewt-path-ebook-heading">Önerilerini PDF e-kitap olarak sakla</h2>
		<p><?php echo esc_html( sprintf( 'Seçtiğin sıralamadaki ilk %d içeriğin başlığı ve tıklanabilir bağlantısı eklenir. Ders metinleri PDF’ye aktarılmaz.', $limit ) ); ?> Açılan görünümde “PDF olarak kaydet” seçeneğini kullan.</p>
		<form id="ewt-path-ebook-form" data-rendered-at="<?php echo esc_attr( time() ); ?>" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'ewt_ebook_action', 'nonce', false ); ?>
			<input type="hidden" name="action" value="ewt_ebook_create">
			<input type="hidden" name="source" value="learning_path">
			<input type="hidden" name="format" value="pdf">
			<?php foreach ( $p as $key => $value ) : ?><input type="hidden" name="<?php echo esc_attr( 'ewt_' . $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endforeach; ?>
			<label class="ewt-path-ebook-hp" aria-hidden="true">Bu alanı boş bırak<input type="text" name="ewt_ebook_hp" tabindex="-1" autocomplete="off"></label>
			<button type="submit" hidden>PDF e-kitabımı oluştur</button>
		</form>
		<p id="ewt-path-ebook-status" role="status" aria-live="polite"></p>
		<a id="ewt-path-ebook-open" hidden target="_blank" rel="noopener">PDF görünümünü aç</a>
		<noscript><p>PDF oluşturmak için JavaScript’i etkinleştir.</p></noscript>
	</aside>
	<?php
}
add_action( 'wp_enqueue_scripts', function () {
	if ( ! get_query_var( 'ewt_path' ) || ! ewt_ebook_enabled() ) return;
	wp_enqueue_script( 'ewt-path-ebook', EWT_URI . '/assets/js/learning-path-ebook.js', array(), EWT_VERSION, true );
} );
function ewt_ebook_render_link_print_html( $book ) {
	$p = ewt_path_preferences( array_combine( array_map( function ( $key ) { return 'ewt_' . $key; }, array_keys( $book['preferences'] ) ), array_values( $book['preferences'] ) ) );
	$summary = strtoupper( $p['level'] ) . ' · ' . ewt_path_audiences()[ $p['audience'] ] . ' · ' . ewt_path_goals()[ $p['goal'] ];
	if ( $p['target'] ) $summary .= ' · ' . ewt_path_targets( $p['goal'] )[ $p['target'] ];
	ob_start(); ?>
<!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $book['title'] ); ?></title>
<style>
@page { size: A4; margin: 20mm; }
* { box-sizing: border-box; }
body { margin: 0; background: #edf2f7; color: #182d43; font: 16px/1.6 Arial, sans-serif; }
.ewt-link-bar { padding: 1rem; text-align: center; background: #182d43; color: #fff; }
button { padding: .7rem 1rem; border: 0; border-radius: 6px; cursor: pointer; font: inherit; }
.ewt-link-doc { max-width: 820px; margin: 2rem auto; padding: 2rem; background: #fff; }
h1 { font-size: 1.8rem; } h2 { font-size: 1.2rem; }
a { color: #154a72; overflow-wrap: anywhere; }
li { padding: .8rem 0; break-inside: avoid; border-bottom: 1px solid #dce3ed; }
.ewt-link-title { font-weight: bold; } .ewt-link-url { display: block; font-size: .85rem; }
.ewt-link-level { display: block; font-size: .85rem; color: #465c70; }
@media print { body { background: #fff; font-size: 11pt; } .ewt-link-bar { display: none; } .ewt-link-doc { max-width: none; margin: 0; padding: 0; } a { text-decoration: underline; } }
@media (max-width: 600px) { .ewt-link-doc { margin: 0; padding: 1rem; } }
</style></head><body>
<div class="ewt-link-bar"><button type="button" onclick="window.print()">PDF olarak kaydet / Yazdır</button><p>Yazıcı seçiminden “PDF olarak kaydet”i seç. Bağlantılar PDF’de tıklanabilir kalır.</p></div>
<main class="ewt-link-doc">
	<header><p><?php echo esc_html( $book['site'] ); ?></p><h1><?php echo esc_html( $book['title'] ); ?></h1><p><?php echo esc_html( $summary ); ?></p>
	<p><?php echo esc_html( 'easy' === $p['order'] ? 'Sıralama: kolaydan zora' : 'Sıralama: en ilgili içerik önce' ); ?> · <?php echo esc_html( count( $book['chapters'] ) ); ?> içerik</p></header>
	<h2>Çalışma bağlantıların</h2>
	<ol><?php foreach ( $book['chapters'] as $ch ) : ?>
		<li><a class="ewt-link-title" href="<?php echo esc_url( $ch['permalink'] ); ?>"><?php echo esc_html( $ch['title'] ); ?></a>
			<span class="ewt-link-level"><?php echo esc_html( ewt_path_post_level( $ch['id'] ) . ' · ' . $ch['type_label'] ); ?></span>
			<a class="ewt-link-url" href="<?php echo esc_url( $ch['permalink'] ); ?>"><?php echo esc_html( $ch['permalink'] ); ?></a></li>
	<?php endforeach; ?></ol>
	<footer><p>İçerikler sitede güncellenebilir; okumak için bağlantıları aç.</p><a href="<?php echo esc_url( $book['site_url'] ); ?>"><?php echo esc_html( $book['site'] ); ?></a></footer>
</main></body></html>
	<?php return ob_get_clean();
}
