<?php
/** A local grammar/reading screening, independent of paid provider APIs. */
if ( ! defined( 'ABSPATH' ) ) exit;
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_front_page() || get_query_var( 'ewt_path' ) ) return;
	$bank = json_decode( file_get_contents( EWT_DIR . '/assets/placement-questions.json' ), true );
	wp_enqueue_script( 'ewt-placement-score', EWT_URI . '/assets/js/placement-score.js', array(), EWT_VERSION, true );
	wp_enqueue_script( 'ewt-placement-test', EWT_URI . '/assets/js/placement-test.js', array( 'ewt-learning-path', 'ewt-placement-score' ), EWT_VERSION, true );
	wp_localize_script( 'ewt-placement-test', 'ewtPlacement', array( 'questions' => is_array( $bank ) ? $bank : array() ) );
} );
function ewt_placement_render() {
	if ( ! is_front_page() || get_query_var( 'ewt_path' ) ) return;
	?>
	<section id="ewt-placement-test" class="ewt-container ewt-placement" aria-labelledby="ewt-placement-heading">
		<div class="ewt-placement-intro">
			<span class="ewt-path-badge">Öğrenme yoluna ilk adım</span>
			<h2 id="ewt-placement-heading">İngilizce seviyeni keşfet</h2>
			<p>A1–C2 aralığında 30 kısa soruyla dil bilgisi, kelime ve okuma becerilerini dene. Yaklaşık 10–15 dakika ayır; bilmediğin soruda “Bilmiyorum”u seç.</p>
			<p>Bu kısa ön değerlendirme resmî CEFR belgesi veya sınav puanı vermez; dinleme, konuşma ve yazma üretimini ölçmez. Sonuç, yukarıda seçtiğin eğitim grubu ve hedefle birlikte içerik önerilerini düzenler.</p>
			<p>Cevapların yalnızca bu sayfada işlenir. Sayfayı kapatırsan test sıfırlanır. Öğrenme seçimlerin bu tarayıcıda hatırlanır.</p>
			<button type="button" data-pt="start">Seviye testine başla</button>
			<noscript><p>Test için JavaScript gerekir. Üstteki seçim alanından seviyeni kendin belirleyerek içeriklere ulaşabilirsin.</p></noscript>
		</div>
		<form data-pt="form" hidden>
			<p data-pt="progress" role="status"></p>
			<progress data-pt="meter" max="30" value="0" aria-label="Test ilerlemesi"></progress>
			<fieldset><legend data-pt="question" tabindex="-1" lang="en"></legend><div data-pt="options"></div></fieldset>
			<p data-pt="error" role="alert"></p>
			<div class="ewt-placement-actions"><button type="button" data-pt="back">Önceki soru</button><button type="submit" data-pt="next">Sonraki soru</button><button type="button" data-pt="cancel">Testi kapat</button></div>
		</form>
		<div data-pt="result" hidden>
			<h3 data-pt="result-title" tabindex="-1"></h3>
			<p data-pt="summary"></p>
			<p data-pt="context"></p>
			<ul data-pt="breakdown"></ul>
			<button type="button" data-pt="apply">Bu seviyeye göre içerikleri göster</button>
			<button type="button" data-pt="restart">Testi yeniden çöz</button>
			<details><summary>Cevapları ve açıklamaları incele</summary><ol data-pt="review"></ol></details>
		</div>
	</section>
	<?php
}
