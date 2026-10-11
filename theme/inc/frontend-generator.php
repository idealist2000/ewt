<?php
/**
 * ÖN YÜZ MATERYAL ÜRETİCİ (Faz 2)
 *
 * [ewt_generator] kısa kodu, ziyaretçilerin konu + seviye seçerek anında
 * Çalışma Kağıdı / Sınav / Ders Planı / Quiz üretebildiği bir form gösterir.
 *
 * Güvenlik/maliyet notları (bu araç, admin-only toplu araçlardan farklı olarak
 * HERKESE AÇIKTIR, bu yüzden ekstra korumalar içerir):
 * - Saatlik IP başına üretim limiti (varsayılan 6, Ayarlar'dan değiştirilebilir)
 * - Honeypot alanı + minimum doldurma süresi kontrolü (basit bot koruması)
 * - Aynı konu+seviye+tür için 7 gün önbellek (tekrar eden isteklerde API çağrısı yapılmaz)
 * - Üretilen HTML, frontend'e gönderilmeden önce wp_kses_post() ile temizlenir
 * - AI sağlayıcısı ziyaretçiye SORULMAZ; sitenin varsayılan sağlayıcısı kullanılır (maliyet kontrolü)
 * - Görünüm > English Web Ayarları'ndan tek tuşla kapatılabilir (acil durum anahtarı)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Üretilebilecek materyal türleri.
 */
function ewt_generator_type_labels() {
	return array(
		'worksheet'   => array( 'label' => 'Çalışma Kağıdı', 'icon' => '📝' ),
		'exam'        => array( 'label' => 'Sınav',          'icon' => '📄' ),
		'lesson_plan' => array( 'label' => 'Ders Planı',      'icon' => '📚' ),
		'quiz'        => array( 'label' => 'Quiz',            'icon' => '🎯' ),
	);
}

/* -------------------------------------------------
 * PROMPT ÜRETİCİLER
 * ------------------------------------------------- */
function ewt_generator_system_prompt( $type ) {
	$base = 'Sen deneyimli bir İngilizce öğretmenisin. Türk öğrenciler/öğretmenler için pedagojik olarak sağlam, HTML formatlı (başlıklar için <h2>/<h3>, listeler için <ol>/<ul><li>, gerekirse <table>) eğitim materyali üretiyorsun. Yanıtının başına veya sonuna hiçbir giriş/kapanış cümlesi ekleme, doğrudan HTML içerikle başla.';

	switch ( $type ) {
		case 'worksheet':
			return $base . ' Şu an bir ÇALIŞMA KAĞIDI hazırlıyorsun: kısa bir konu özeti (3-4 cümle), ardından en az 8 çeşitli alıştırma (boşluk doldurma, eşleştirme, çoktan seçmeli karışık), ve en sonda ayrı bir "Cevap Anahtarı" başlığı altında tüm doğru cevaplar.';
		case 'exam':
			return $base . ' Şu an bir SINAV kağıdı hazırlıyorsun: en az 10 soru (çoktan seçmeli / boşluk doldurma / doğru-yanlış karışık), her sorunun puan değerini belirt (toplamda 100 puan), ve en sonda ayrı bir "Cevap Anahtarı" başlığı altında doğru cevaplar.';
		case 'lesson_plan':
			return $base . ' Şu an ÖĞRETMENLER için bir DERS PLANI hazırlıyorsun. Sırasıyla şu başlıkları kullan: "Öğrenme Hedefleri", "Süre", "Materyaller", "Isınma Etkinliği (Warm-up)", "Sunum (Presentation)", "Uygulama (Practice)", "Üretim (Production)", "Değerlendirme", "Ev Ödevi".';
		default:
			return $base;
	}
}

function ewt_generator_user_prompt( $type, $topic, $level_label ) {
	$nouns = array( 'worksheet' => 'çalışma kağıdı', 'exam' => 'sınav', 'lesson_plan' => 'ders planı' );
	$noun  = isset( $nouns[ $type ] ) ? $nouns[ $type ] : 'içerik';
	return '"' . $topic . '" konusu için ' . $level_label . ' seviyesine uygun bir ' . $noun . ' hazırla.';
}

function ewt_generator_quiz_system_prompt() {
	return 'Sen bir İngilizce quiz üretim API\'sisin. SADECE geçerli, ham JSON döndürürsün. Markdown kod bloğu (```), açıklama, giriş/kapanış cümlesi KULLANMA — yanıtının TAMAMI tek bir JSON nesnesi olmalı.';
}

function ewt_generator_quiz_user_prompt( $topic, $level_label ) {
	return '"' . $topic . '" konusu için ' . $level_label . ' seviyesine uygun, 8 sorudan oluşan çoktan seçmeli bir İngilizce quiz hazırla. '
		. 'Tam olarak şu JSON şemasını kullan, başka HİÇBİR ŞEY yazma: '
		. '{"title": "quiz başlığı", "questions": [ {"q": "soru metni", "options": ["A","B","C","D"], "correct": 0, "explanation": "doğru cevabın kısa açıklaması"} ]}. '
		. '"correct" alanı, doğru seçeneğin "options" dizisindeki index numarasıdır (0\'dan başlar).';
}

/**
 * Üretilen HTML'de "Cevap Anahtarı" başlığından itibaren her şeyi katlanabilir
 * bir <details> içine alır (öğrenci/ziyaretçi önce görmesin, isterse açsın).
 */
function ewt_wrap_answer_key_collapsible( $html ) {
	if ( ! preg_match( '/<h[23]\b[^>]*>\s*(?:📋\s*)?Cevap Anahtarı/iu', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$pos    = $m[0][1];
	$before = substr( $html, 0, $pos );
	$after  = substr( $html, $pos );
	return $before . '<details class="ewt-answer-key"><summary>📋 Cevap Anahtarını Göster</summary>' . $after . '</details>';
}

/* -------------------------------------------------
 * QUIZ JSON DOĞRULAMA / TEMİZLEME
 * ------------------------------------------------- */
function ewt_validate_quiz_json( $data ) {
	if ( ! is_array( $data ) || empty( $data['questions'] ) || ! is_array( $data['questions'] ) ) return false;
	foreach ( $data['questions'] as $q ) {
		if ( ! is_array( $q ) ) return false;
		if ( empty( $q['q'] ) || ! is_string( $q['q'] ) ) return false;
		if ( empty( $q['options'] ) || ! is_array( $q['options'] ) || count( $q['options'] ) < 2 ) return false;
		if ( ! isset( $q['correct'] ) || ! is_numeric( $q['correct'] ) ) return false;
		if ( intval( $q['correct'] ) < 0 || intval( $q['correct'] ) >= count( $q['options'] ) ) return false;
	}
	return true;
}

function ewt_sanitize_quiz_json( $data ) {
	$out = array(
		'title'     => isset( $data['title'] ) ? ewt_strip_ai_citations( sanitize_text_field( $data['title'] ) ) : 'Quiz',
		'questions' => array(),
	);
	foreach ( $data['questions'] as $q ) {
		$options = array();
		foreach ( $q['options'] as $opt ) {
			$options[] = ewt_strip_ai_citations( sanitize_text_field( (string) $opt ) );
		}
		$out['questions'][] = array(
			'q'           => ewt_strip_ai_citations( sanitize_text_field( $q['q'] ) ),
			'options'     => $options,
			'correct'     => intval( $q['correct'] ),
			'explanation' => isset( $q['explanation'] ) ? ewt_strip_ai_citations( sanitize_text_field( (string) $q['explanation'] ) ) : '',
		);
	}
	return $out;
}

/* -------------------------------------------------
 * HIZ SINIRLAMA (IP başına saatlik)
 * ------------------------------------------------- */
function ewt_generator_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
}

function ewt_generator_check_rate_limit() {
	$limit = max( 1, intval( get_option( 'ewt_generator_rate_limit', 6 ) ) );
	$key   = 'ewt_gen_rl_' . md5( ewt_generator_client_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) return false;
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return true;
}

/* -------------------------------------------------
 * KISA KOD: [ewt_generator cpt="" types="worksheet,exam,lesson_plan,quiz" title="..."]
 * ------------------------------------------------- */
function ewt_generator_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'cpt'   => '',
		'types' => 'worksheet,exam,lesson_plan,quiz',
		'title' => 'Ücretsiz İngilizce Materyal Üretici',
	), $atts, 'ewt_generator' );

	$GLOBALS['ewt_generator_used'] = true; // wp_footer'da JS/CSS'i tetikler

	if ( get_option( 'ewt_generator_enabled', '1' ) !== '1' ) {
		return '<div class="ewt-generator ewt-generator-disabled"><p>Bu özellik şu anda kullanılamıyor. Materyal talebi için bize <a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', get_option( 'ewt_tutoring_phone', '' ) ) ) . '">ulaşabilirsiniz</a>.</p></div>';
	}

	$all_types    = ewt_generator_type_labels();
	$requested    = array_filter( array_map( 'trim', explode( ',', $atts['types'] ) ) );
	$active_types = array();
	foreach ( $requested as $t ) {
		if ( isset( $all_types[ $t ] ) ) $active_types[ $t ] = $all_types[ $t ];
	}
	if ( empty( $active_types ) ) $active_types = $all_types;

	$cpt_context = sanitize_text_field( $atts['cpt'] );
	$level_opts  = ewt_difficulty_options_for_cpt( $cpt_context );

	ob_start();
	?>
	<div class="ewt-generator" data-cpt-context="<?php echo esc_attr( $cpt_context ); ?>">
		<h3 class="ewt-generator-title"><?php echo esc_html( $atts['title'] ); ?></h3>
		<p class="ewt-generator-sub">Konunu ve seviyeni seç, saniyeler içinde sana özel materyal oluşturalım.</p>

		<form class="ewt-generator-form" data-rendered-at="<?php echo esc_attr( time() ); ?>">
			<?php wp_nonce_field( 'ewt_frontend_generate', 'ewt_gen_nonce' ); ?>
			<input type="text" name="ewt_gen_hp" class="ewt-gen-hp" tabindex="-1" autocomplete="off" value="" aria-hidden="true" />

			<div class="ewt-generator-row">
				<label>Materyal Türü
					<select name="gen_type" class="ewt-gen-type">
						<?php foreach ( $active_types as $key => $info ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $info['icon'] . ' ' . $info['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Seviye
					<select name="level" class="ewt-gen-level">
						<?php foreach ( $level_opts as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<label class="ewt-generator-topic-label">Konu
				<input type="text" name="topic" class="ewt-gen-topic" placeholder="örn. present perfect tense, hayvanlar, classroom rules..." maxlength="100" required />
			</label>

			<button type="submit" class="ewt-generator-submit">✨ Oluştur</button>
		</form>

		<div class="ewt-generator-result" aria-live="polite" style="display:none;"></div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ewt_generator', 'ewt_generator_shortcode' );

/**
 * JS/CSS'i, sayfada [ewt_generator] gerçekten KULLANILMIŞSA (Elementor "Kısa Kod" widget'ı
 * dahil — bu yüzden wp_enqueue_scripts yerine, içerik render edildikten sonra wp_footer'da,
 * doğrudan <link>/<script> basarak) yükler. Kullanılmayan sayfalarda hiçbir ek dosya yüklenmez.
 */
function ewt_generator_print_assets_footer() {
	if ( empty( $GLOBALS['ewt_generator_used'] ) ) return;
	?>
	<link rel="stylesheet" href="<?php echo esc_url( EWT_URI . '/assets/css/frontend-generator.css?ver=' . EWT_VERSION ); ?>" />
	<script>var ewtGenerator = <?php echo wp_json_encode( array(
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'whatsapp' => preg_replace( '/[^0-9]/', '', get_option( 'ewt_tutoring_phone', '+905305714067' ) ),
	) ); ?>;</script>
	<script src="<?php echo esc_url( EWT_URI . '/assets/js/frontend-generator.js?ver=' . EWT_VERSION ); ?>" defer></script>
	<?php
}
add_action( 'wp_footer', 'ewt_generator_print_assets_footer', 5 );

/* -------------------------------------------------
 * AJAX: ziyaretçiden gelen isteği üret (HERKESE AÇIK — nopriv dahil)
 * ------------------------------------------------- */
function ewt_ajax_frontend_generate() {
	if ( get_option( 'ewt_generator_enabled', '1' ) !== '1' ) {
		wp_send_json_error( array( 'message' => 'Bu özellik şu anda kullanılamıyor.' ) );
	}

	if ( ! check_ajax_referer( 'ewt_frontend_generate', 'nonce', false ) ) {
		wp_send_json_error( array( 'code' => 'nonce_expired', 'message' => 'Oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.' ) );
	}

	// Honeypot: gerçek ziyaretçiler bu alanı görmez/doldurmaz.
	if ( ! empty( $_POST['ewt_gen_hp'] ) ) {
		wp_send_json_error( array( 'message' => 'İşlem doğrulanamadı.' ) );
	}

	// Form render edildikten en az 3 saniye sonra gönderilmeli (basit bot/otomasyon filtresi).
	$rendered_at = isset( $_POST['rendered_at'] ) ? intval( $_POST['rendered_at'] ) : 0;
	if ( $rendered_at && ( time() - $rendered_at ) < 3 ) {
		wp_send_json_error( array( 'message' => 'Lütfen birkaç saniye bekleyip tekrar deneyin.' ) );
	}

	$type = isset( $_POST['gen_type'] ) ? sanitize_key( $_POST['gen_type'] ) : 'worksheet';
	if ( ! array_key_exists( $type, ewt_generator_type_labels() ) ) $type = 'worksheet';

	$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
	$topic = mb_substr( trim( $topic ), 0, 100 );
	if ( mb_strlen( $topic ) < 3 ) {
		wp_send_json_error( array( 'message' => 'Lütfen en az 3 karakterlik bir konu yazın.' ) );
	}

	$cpt_context = isset( $_POST['cpt_context'] ) ? sanitize_text_field( $_POST['cpt_context'] ) : '';
	$level_key   = isset( $_POST['level'] ) ? sanitize_text_field( $_POST['level'] ) : '';
	$level_opts  = ewt_difficulty_options_for_cpt( $cpt_context );
	$level_label = isset( $level_opts[ $level_key ] ) ? $level_opts[ $level_key ] : ( $level_key ? $level_key : 'A1-C2 karışık seviye' );

	// Önbellek: aynı tür+seviye+konu için 7 gün içinde tekrar istenirse API'ye gitmeden anında döner.
	$cache_key = 'ewt_gen_' . md5( $type . '|' . $level_key . '|' . mb_strtolower( $topic ) );
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		wp_send_json_success( $cached );
	}

	if ( ! ewt_generator_check_rate_limit() ) {
		$phone = get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' );
		wp_send_json_error( array( 'message' => 'Bu saatlik ücretsiz üretim hakkınızı doldurdunuz. Daha fazla materyal için ' . $phone . ' numarasından bize ulaşabilirsiniz.' ) );
	}

	$provider = get_option( 'ewt_default_ai_provider', 'youcom' );
	$payload  = array();

	if ( $type === 'quiz' ) {
		$raw = ewt_call_ai_provider( $provider, ewt_generator_quiz_system_prompt(), ewt_generator_quiz_user_prompt( $topic, $level_label ), true );
		if ( is_wp_error( $raw ) ) wp_send_json_error( array( 'message' => $raw->get_error_message() ) );

		$clean = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', trim( $raw ) );
		$data  = json_decode( $clean, true );

		if ( ! ewt_validate_quiz_json( $data ) ) {
			wp_send_json_error( array( 'message' => 'Quiz oluşturulamadı, lütfen tekrar deneyin.' ) );
		}

		$payload['result_type'] = 'quiz';
		$payload['quiz']        = ewt_sanitize_quiz_json( $data );
	} else {
		$system  = ewt_generator_system_prompt( $type );
		$prompt  = ewt_generator_user_prompt( $type, $topic, $level_label );
		$content = ewt_call_ai_provider( $provider, $system, $prompt, false );
		if ( is_wp_error( $content ) ) wp_send_json_error( array( 'message' => $content->get_error_message() ) );

		$content = ewt_wrap_answer_key_collapsible( $content );
		$content = wp_kses_post( $content ); // herkese açık uç nokta: HTML çıkışı tekrar temizlenir

		$payload['result_type'] = 'html';
		$payload['html']        = $content;
	}

	$type_labels             = ewt_generator_type_labels();
	$payload['type_label']   = $type_labels[ $type ]['label'];
	$payload['topic']        = $topic;

	set_transient( $cache_key, $payload, WEEK_IN_SECONDS );

	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_ewt_frontend_generate', 'ewt_ajax_frontend_generate' );
add_action( 'wp_ajax_nopriv_ewt_frontend_generate', 'ewt_ajax_frontend_generate' );
