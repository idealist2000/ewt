<?php
/**
 * DİNLEME & KONUŞMA LABORATUVARI (v1.6.0)
 *
 * [ewt_listening_lab] kısa kodu ile ziyaretçi/öğrenci/öğretmen:
 *   1) Seviye (A1-C2) + konu seçerek AI ile özgün bir DİNLEME PARÇASI üretir
 *      (metin, tarayıcının ücretsiz Web Speech API'siyle sesli okunur — ek maliyet yok)
 *   2) Parçayı dinledikten sonra ANLAMA TESTİ (quiz) çözer
 *   3) Ardından KONUŞMA ÇALIŞMASINA geçer: tartışma soruları, rol yapma senaryosu,
 *      shadowing (tekrar) cümleleri + mikrofonla kendi sesini kaydedip dinleme
 *
 * Otomatik yerleştirme: ewt_listening ve ewt_speaking CPT'lerinin tekil (single)
 * sayfalarında, yazı içeriğinin ALTINA otomatik eklenir (Ayarlar'dan kapatılabilir).
 * Yazının seviye taxonomy'sinden (örn. a1-seviyesi-listening) seviye otomatik algılanır
 * ve laboratuvarda önceden seçili gelir; yazı başlığı da konu alanına önerilir.
 *
 * Kısa kod her yerde kullanılabilir:
 *   [ewt_listening_lab]                              → tüm seviyeler seçilebilir
 *   [ewt_listening_lab level="b1"]                   → B1 önceden seçili
 *   [ewt_listening_lab topic="daily routines"]       → konu önceden dolu
 *   [ewt_listening_lab mode="speaking"]              → başlık/vurgu konuşma odaklı
 *   [ewt_listening_lab title="Kendi Başlığım"]
 *
 * Güvenlik / maliyet: frontend-generator ile AYNI korumalar kullanılır —
 * IP başına saatlik ortak hız limiti (ewt_generator_check_rate_limit),
 * honeypot + minimum doldurma süresi, nonce, 7 günlük önbellek,
 * tüm AI çıktısı sunucuda doğrulanıp temizlenir (JS tarafında .textContent ile basılır).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------
 * SEVİYE → PARÇA UZUNLUĞU / ZORLUK HARİTASI
 * ------------------------------------------------- */
function ewt_lab_level_specs() {
	return array(
		'a1' => array( 'label' => 'A1 - Başlangıç',  'words' => '70-100',  'style' => 'Çok basit cümleler, şimdiki ve geniş zaman, en temel 500 kelime.' ),
		'a2' => array( 'label' => 'A2 - Temel',      'words' => '110-140', 'style' => 'Basit bağlaçlarla (and, but, because) bağlanan kısa cümleler, geçmiş zaman kullanılabilir.' ),
		'b1' => array( 'label' => 'B1 - Orta',       'words' => '150-190', 'style' => 'Günlük hayat ve deneyim anlatımı, çeşitli zamanlar, orta sıklıkta kelimeler.' ),
		'b2' => array( 'label' => 'B2 - Orta Üstü',  'words' => '200-240', 'style' => 'Soyut konular, görüş bildirme, karmaşık cümle yapıları, deyimler kullanılabilir.' ),
		'c1' => array( 'label' => 'C1 - İleri',      'words' => '250-300', 'style' => 'Akademik/profesyonel üslup, ileri kelime dağarcığı, ima ve ince anlam farkları.' ),
		'c2' => array( 'label' => 'C2 - Uzman',      'words' => '300-350', 'style' => 'Ana dil seviyesinde doğallık, nadir kelimeler, kültürel referanslar, nüanslı anlatım.' ),
	);
}

/* -------------------------------------------------
 * AI PROMPT'LARI (tek çağrıda parça + quiz + konuşma)
 * ------------------------------------------------- */
function ewt_lab_system_prompt() {
	return 'Sen bir İngilizce dinleme-konuşma materyali üretim API\'sisin. SADECE geçerli, ham JSON döndürürsün. '
		. 'Markdown kod bloğu (```), açıklama, giriş/kapanış cümlesi KULLANMA — yanıtının TAMAMI tek bir JSON nesnesi olmalı. '
		. 'Dinleme parçası (passage) TAMAMEN İNGİLİZCE olmalı ve sesli okumaya uygun, akıcı, doğal konuşma diliyle yazılmalı '
		. '(kısaltma okunuşlarına dikkat: "Dr." yerine "Doctor" gibi, rakamlar yazıyla). '
		. 'Quiz soruları İngilizce, açıklamaları (explanation) TÜRKÇE olmalı. '
		. 'Konuşma bölümündeki yönergeler İngilizce, ama roleplay senaryosunun kurulum açıklaması Türkçe olabilir.';
}

function ewt_lab_user_prompt( $topic, $level_key, $quiz_count ) {
	$specs = ewt_lab_level_specs();
	$spec  = isset( $specs[ $level_key ] ) ? $specs[ $level_key ] : $specs['b1'];

	return '"' . $topic . '" konusunda, ' . $spec['label'] . ' (CEFR ' . strtoupper( $level_key ) . ') seviyesinde bir İngilizce dinleme-konuşma çalışması hazırla. '
		. 'Dinleme parçası ' . $spec['words'] . ' kelime uzunluğunda olmalı. Üslup: ' . $spec['style'] . ' '
		. 'Tam olarak şu JSON şemasını kullan, başka HİÇBİR ŞEY yazma: '
		. '{'
		. '"title": "çalışmanın kısa İngilizce başlığı", '
		. '"passage": "dinleme parçasının tam metni (tek paragraf ya da \\n\\n ile ayrılmış 2-3 kısa paragraf)", '
		. '"quiz": {"questions": [ {"q": "İngilizce anlama sorusu", "options": ["A","B","C","D"], "correct": 0, "explanation": "doğru cevabın kısa TÜRKÇE açıklaması"} ]}, '
		. '"speaking": {'
		. '"warmup": ["dinlemeden önce/sonra sorulabilecek 2 kısa İngilizce ısınma sorusu"], '
		. '"questions": ["parçayla ilgili 4 İngilizce tartışma/konuşma sorusu (basitten zora)"], '
		. '"roleplay": "parçanın konusuyla ilgili kısa bir rol yapma senaryosu (kurulumu Türkçe açıklanabilir, örnek replikler İngilizce)", '
		. '"shadowing": ["parçadan seçilmiş, telaffuz çalışmasına uygun 5 cümle (parçadaki halleriyle birebir aynı)"]'
		. '}}. '
		. 'Quiz tam olarak ' . intval( $quiz_count ) . ' soru içermeli ve sorular SADECE parçadaki bilgilerle cevaplanabilmeli. '
		. '"correct" alanı doğru seçeneğin "options" dizisindeki index numarasıdır (0\'dan başlar).';
}

/* -------------------------------------------------
 * JSON DOĞRULAMA / TEMİZLEME
 * ------------------------------------------------- */
function ewt_lab_validate_json( $data ) {
	if ( ! is_array( $data ) ) return false;
	if ( empty( $data['passage'] ) || ! is_string( $data['passage'] ) || mb_strlen( $data['passage'] ) < 100 ) return false;
	if ( empty( $data['quiz'] ) || ! ewt_validate_quiz_json( $data['quiz'] ) ) return false;
	if ( empty( $data['speaking'] ) || ! is_array( $data['speaking'] ) ) return false;

	$sp = $data['speaking'];
	if ( empty( $sp['questions'] ) || ! is_array( $sp['questions'] ) ) return false;
	if ( empty( $sp['shadowing'] ) || ! is_array( $sp['shadowing'] ) ) return false;
	return true;
}

function ewt_lab_sanitize_string_list( $list, $max_items = 8 ) {
	$out = array();
	if ( ! is_array( $list ) ) return $out;
	foreach ( $list as $item ) {
		if ( ! is_string( $item ) && ! is_numeric( $item ) ) continue;
		$clean = ewt_strip_ai_citations( sanitize_text_field( (string) $item ) );
		if ( $clean !== '' ) $out[] = $clean;
		if ( count( $out ) >= $max_items ) break;
	}
	return $out;
}

function ewt_lab_sanitize_json( $data ) {
	$passage = ewt_strip_ai_citations( sanitize_textarea_field( $data['passage'] ) );

	$sp  = $data['speaking'];
	$out = array(
		'title'   => isset( $data['title'] ) ? ewt_strip_ai_citations( sanitize_text_field( (string) $data['title'] ) ) : 'Listening Practice',
		'passage' => $passage,
		'quiz'    => ewt_sanitize_quiz_json( $data['quiz'] ),
		'speaking' => array(
			'warmup'    => ewt_lab_sanitize_string_list( isset( $sp['warmup'] ) ? $sp['warmup'] : array(), 3 ),
			'questions' => ewt_lab_sanitize_string_list( $sp['questions'], 6 ),
			'roleplay'  => isset( $sp['roleplay'] ) ? ewt_strip_ai_citations( sanitize_textarea_field( (string) $sp['roleplay'] ) ) : '',
			'shadowing' => ewt_lab_sanitize_string_list( $sp['shadowing'], 6 ),
		),
	);
	return $out;
}

/* -------------------------------------------------
 * YAZIDAN SEVİYE ALGILAMA (taxonomy terim slug'ından)
 * örn. "a1-seviyesi-listening" → "a1", "b2-seviyesi-speaking" → "b2"
 * ------------------------------------------------- */
function ewt_lab_detect_level_from_post( $post_id, $post_type ) {
	$terms = get_the_terms( $post_id, $post_type . '_seviye' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) return '';
	foreach ( $terms as $term ) {
		if ( preg_match( '/^(a1|a2|b1|b2|c1|c2)\b/i', $term->slug, $m ) ) {
			return strtolower( $m[1] );
		}
		// Slug'da yoksa terim adında da dene (örn. "A1 Seviyesi")
		if ( preg_match( '/\b(a1|a2|b1|b2|c1|c2)\b/i', $term->name, $m ) ) {
			return strtolower( $m[1] );
		}
	}
	return '';
}

/* -------------------------------------------------
 * KISA KOD: [ewt_listening_lab level="" topic="" title="" mode="listening"]
 * ------------------------------------------------- */
function ewt_listening_lab_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'level' => '',
		'topic' => '',
		'title' => '',
		'mode'  => 'listening', // listening | speaking (sadece başlık/vurguyu değiştirir; akış aynıdır)
	), $atts, 'ewt_listening_lab' );

	if ( get_option( 'ewt_lab_enabled', '1' ) !== '1' ) {
		return '<div class="ewt-lab ewt-lab-disabled"><p>Dinleme &amp; Konuşma Laboratuvarı şu anda kullanılamıyor.</p></div>';
	}

	$GLOBALS['ewt_lab_used'] = true; // wp_footer'da JS/CSS'i tetikler

	$mode = ( $atts['mode'] === 'speaking' ) ? 'speaking' : 'listening';
	$default_title = ( $mode === 'speaking' )
		? '🎤 Konuşma Laboratuvarı: Dinle → Test Çöz → Konuş'
		: '🎧 Dinleme Laboratuvarı: Dinle → Test Çöz → Konuş';
	$title = $atts['title'] !== '' ? $atts['title'] : $default_title;

	$level  = strtolower( sanitize_key( $atts['level'] ) );
	$topic  = sanitize_text_field( $atts['topic'] );
	$levels = ewt_lab_level_specs();
	if ( ! isset( $levels[ $level ] ) ) $level = '';

	ob_start();
	?>
	<div class="ewt-lab" data-mode="<?php echo esc_attr( $mode ); ?>">
		<div class="ewt-lab-header">
			<p class="ewt-lab-title" role="heading" aria-level="3"><?php echo esc_html( $title ); ?></p>
			<p class="ewt-lab-sub">Seviyeni ve konunu seç; sana özel bir dinleme parçası, anlama testi ve konuşma çalışması oluşturalım. Metin, tarayıcın tarafından sesli okunur — istediğin hızda, istediğin kadar dinleyebilirsin.</p>
		</div>

		<ol class="ewt-lab-steps">
			<li class="ewt-lab-step-tab is-active" data-step="1"><span>1</span> Oluştur</li>
			<li class="ewt-lab-step-tab" data-step="2"><span>2</span> 🎧 Dinle</li>
			<li class="ewt-lab-step-tab" data-step="3"><span>3</span> 📝 Test</li>
			<li class="ewt-lab-step-tab" data-step="4"><span>4</span> 🎤 Konuş</li>
		</ol>

		<!-- ADIM 1: FORM -->
		<div class="ewt-lab-panel is-active" data-panel="1">
			<form class="ewt-lab-form" data-rendered-at="<?php echo esc_attr( time() ); ?>">
				<?php wp_nonce_field( 'ewt_lab_generate', 'ewt_lab_nonce' ); ?>
				<input type="text" name="ewt_lab_hp" class="ewt-lab-hp" tabindex="-1" autocomplete="off" value="" aria-hidden="true" />

				<div class="ewt-lab-row">
					<label>Seviye
						<select name="level" class="ewt-lab-level">
							<?php foreach ( $levels as $key => $spec ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $level, $key ); ?>><?php echo esc_html( $spec['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="ewt-lab-topic-label">Konu
						<input type="text" name="topic" class="ewt-lab-topic" value="<?php echo esc_attr( $topic ); ?>" placeholder="örn. daily routines, travel, technology, at the restaurant..." maxlength="100" required />
					</label>
				</div>

				<button type="submit" class="ewt-lab-submit">✨ Dinleme Parçası Oluştur</button>
			</form>
			<div class="ewt-lab-error" role="alert" style="display:none;"></div>
		</div>

		<!-- ADIM 2: DİNLEME -->
		<div class="ewt-lab-panel" data-panel="2">
			<h4 class="ewt-lab-passage-title"></h4>
			<div class="ewt-lab-player">
				<button type="button" class="ewt-lab-play">▶️ Dinle</button>
				<button type="button" class="ewt-lab-pause" style="display:none;">⏸️ Duraklat</button>
				<button type="button" class="ewt-lab-restart">🔄 Baştan</button>
				<label class="ewt-lab-rate-label">Hız
					<select class="ewt-lab-rate">
						<option value="0.7">🐢 Yavaş (0.7x)</option>
						<option value="0.85">Rahat (0.85x)</option>
						<option value="1" selected>Normal (1x)</option>
						<option value="1.15">Hızlı (1.15x)</option>
					</select>
				</label>
				<span class="ewt-lab-listen-count" title="Kaç kez dinledin"></span>
			</div>
			<p class="ewt-lab-tts-unsupported" style="display:none;">⚠️ Tarayıcınız sesli okumayı desteklemiyor; metni aşağıdan okuyabilirsiniz.</p>

			<div class="ewt-lab-passage-wrap">
				<button type="button" class="ewt-lab-toggle-text">👁️ Metni Göster</button>
				<div class="ewt-lab-passage is-hidden"></div>
			</div>

			<div class="ewt-lab-nav">
				<button type="button" class="ewt-lab-next" data-goto="3">Teste Geç →</button>
			</div>
		</div>

		<!-- ADIM 3: TEST -->
		<div class="ewt-lab-panel" data-panel="3">
			<div class="ewt-lab-quiz"></div>
			<div class="ewt-lab-nav">
				<button type="button" class="ewt-lab-prev" data-goto="2">← Tekrar Dinle</button>
				<button type="button" class="ewt-lab-next ewt-lab-next-speaking" data-goto="4" style="display:none;">Konuşma Çalışmasına Geç →</button>
			</div>
		</div>

		<!-- ADIM 4: KONUŞMA -->
		<div class="ewt-lab-panel" data-panel="4">
			<div class="ewt-lab-speaking"></div>

			<div class="ewt-lab-recorder">
				<h5>🎙️ Kendini Kaydet &amp; Dinle</h5>
				<p class="ewt-lab-recorder-hint">Soruları cevaplarken veya shadowing yaparken kendi sesini kaydet, sonra dinleyip telaffuzunu karşılaştır. Ses kaydın <strong>hiçbir yere gönderilmez</strong>, sadece tarayıcında kalır.</p>
				<div class="ewt-lab-recorder-controls">
					<button type="button" class="ewt-lab-rec-start">⏺️ Kaydı Başlat</button>
					<button type="button" class="ewt-lab-rec-stop" style="display:none;">⏹️ Kaydı Bitir</button>
					<span class="ewt-lab-rec-timer" style="display:none;">00:00</span>
				</div>
				<audio class="ewt-lab-rec-audio" controls style="display:none;"></audio>
				<p class="ewt-lab-rec-unsupported" style="display:none;">⚠️ Tarayıcınız ses kaydını desteklemiyor.</p>
			</div>

			<div class="ewt-lab-nav">
				<button type="button" class="ewt-lab-prev" data-goto="3">← Teste Dön</button>
				<button type="button" class="ewt-lab-again">🔁 Yeni Çalışma Oluştur</button>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ewt_listening_lab', 'ewt_listening_lab_shortcode' );

/* -------------------------------------------------
 * OTOMATİK YERLEŞTİRME: ewt_listening & ewt_speaking tekil sayfaları
 * İçeriğin altına laboratuvarı ekler; seviye taxonomy'den, konu başlıktan gelir.
 * ------------------------------------------------- */
function ewt_lab_auto_insert( $content ) {
	if ( is_admin() || ! is_singular( array( 'ewt_listening', 'ewt_speaking' ) ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( get_option( 'ewt_lab_enabled', '1' ) !== '1' || get_option( 'ewt_lab_auto_insert', '1' ) !== '1' ) {
		return $content;
	}
	// Yazar içeriğe kısa kodu elle eklediyse ikinci kez ekleme.
	if ( has_shortcode( $content, 'ewt_listening_lab' ) ) {
		return $content;
	}

	$post_id   = get_the_ID();
	$post_type = get_post_type( $post_id );
	$level     = ewt_lab_detect_level_from_post( $post_id, $post_type );
	$mode      = ( $post_type === 'ewt_speaking' ) ? 'speaking' : 'listening';
	$topic     = wp_strip_all_tags( get_the_title( $post_id ) );

	$shortcode = sprintf(
		'[ewt_listening_lab mode="%s" level="%s" topic="%s"]',
		esc_attr( $mode ),
		esc_attr( $level ),
		esc_attr( str_replace( array( '[', ']', '"' ), '', $topic ) )
	);

	return $content . "\n" . do_shortcode( $shortcode );
}
add_filter( 'the_content', 'ewt_lab_auto_insert', 20 );

/* -------------------------------------------------
 * JS/CSS: sadece laboratuvar kullanılan sayfalarda, wp_footer'da yüklenir
 * (frontend-generator ile aynı desen — Elementor "Kısa Kod" widget'ı ile de uyumlu)
 * ------------------------------------------------- */
function ewt_lab_print_assets_footer() {
	if ( empty( $GLOBALS['ewt_lab_used'] ) ) return;
	?>
	<link rel="stylesheet" href="<?php echo esc_url( EWT_URI . '/assets/css/listening-lab.css?ver=' . EWT_VERSION ); ?>" />
	<script>var ewtLab = <?php echo wp_json_encode( array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	) ); ?>;</script>
	<script src="<?php echo esc_url( EWT_URI . '/assets/js/listening-lab.js?ver=' . EWT_VERSION ); ?>" defer></script>
	<?php
}
add_action( 'wp_footer', 'ewt_lab_print_assets_footer', 5 );

/* -------------------------------------------------
 * AJAX: dinleme-konuşma paketi üret (HERKESE AÇIK — nopriv dahil)
 * ------------------------------------------------- */
function ewt_ajax_lab_generate() {
	if ( get_option( 'ewt_lab_enabled', '1' ) !== '1' ) {
		wp_send_json_error( array( 'message' => 'Bu özellik şu anda kullanılamıyor.' ) );
	}

	if ( ! check_ajax_referer( 'ewt_lab_generate', 'nonce', false ) ) {
		wp_send_json_error( array( 'code' => 'nonce_expired', 'message' => 'Oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.' ) );
	}

	// Honeypot
	if ( ! empty( $_POST['ewt_lab_hp'] ) ) {
		wp_send_json_error( array( 'message' => 'İşlem doğrulanamadı.' ) );
	}

	// Minimum doldurma süresi (bot filtresi)
	$rendered_at = isset( $_POST['rendered_at'] ) ? intval( $_POST['rendered_at'] ) : 0;
	if ( $rendered_at && ( time() - $rendered_at ) < 3 ) {
		wp_send_json_error( array( 'message' => 'Lütfen birkaç saniye bekleyip tekrar deneyin.' ) );
	}

	$levels = ewt_lab_level_specs();
	$level  = isset( $_POST['level'] ) ? strtolower( sanitize_key( $_POST['level'] ) ) : 'b1';
	if ( ! isset( $levels[ $level ] ) ) $level = 'b1';

	$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
	$topic = mb_substr( trim( $topic ), 0, 100 );
	if ( mb_strlen( $topic ) < 3 ) {
		wp_send_json_error( array( 'message' => 'Lütfen en az 3 karakterlik bir konu yazın.' ) );
	}

	$quiz_count = max( 3, min( 10, intval( get_option( 'ewt_lab_quiz_questions', 5 ) ) ) );

	// Önbellek: aynı seviye+konu 7 gün içinde tekrar istenirse API'ye gitmeden döner.
	$cache_key = 'ewt_lab_' . md5( $level . '|' . mb_strtolower( $topic ) . '|' . $quiz_count );
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		wp_send_json_success( $cached );
	}

	// Ön yüz üretici ile ORTAK saatlik IP limiti (toplam AI maliyetini tek limitten kontrol eder).
	if ( ! ewt_generator_check_rate_limit() ) {
		$phone = get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' );
		wp_send_json_error( array( 'message' => 'Bu saatlik ücretsiz üretim hakkınızı doldurdunuz. Daha fazla çalışma için ' . $phone . ' numarasından bize ulaşabilirsiniz.' ) );
	}

	$provider = get_option( 'ewt_default_ai_provider', 'youcom' );
	$raw      = ewt_call_ai_provider( $provider, ewt_lab_system_prompt(), ewt_lab_user_prompt( $topic, $level, $quiz_count ), true );
	if ( is_wp_error( $raw ) ) {
		wp_send_json_error( array( 'message' => $raw->get_error_message() ) );
	}

	$clean = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', trim( $raw ) );
	$data  = json_decode( $clean, true );

	if ( ! ewt_lab_validate_json( $data ) ) {
		wp_send_json_error( array( 'message' => 'Çalışma oluşturulamadı, lütfen tekrar deneyin.' ) );
	}

	$payload = ewt_lab_sanitize_json( $data );
	$payload['level']       = $level;
	$payload['level_label'] = $levels[ $level ]['label'];
	$payload['topic']       = $topic;

	set_transient( $cache_key, $payload, WEEK_IN_SECONDS );

	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_ewt_lab_generate', 'ewt_ajax_lab_generate' );
add_action( 'wp_ajax_nopriv_ewt_lab_generate', 'ewt_ajax_lab_generate' );
