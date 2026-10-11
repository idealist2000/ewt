<?php
/**
 * AI İÇERİK OLUŞTURMA WIDGET'I
 *
 * Yazı editöründe sağ tarafta bir meta box:
 * - Sağlayıcı seçimi: You.com ($100 ücretsiz kredi ile), Claude, OpenAI
 * - Konu / talimat girilir, "İçerik Üret" butonuna basılır
 * - admin-ajax.php üzerinden sunucu tarafında ilgili API'ye istek atılır (anahtarlar asla tarayıcıya gönderilmez)
 * - Sonuç editör alanına eklenir (Gutenberg + Classic Editor desteklenir)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI çıktısındaki atıf/kaynak numaralandırma işaretlerini temizler.
 * Özellikle You.com Research/Express API'leri gibi "kaynak destekli" (grounded) API'ler,
 * cümle sonlarına [[5, 3]] veya [1, 2] gibi kaynak referans işaretleri ekleyebilir.
 * Bu işaretler blog/ders içeriğinde anlamsız kaldığından üretilen her metinden otomatik temizlenir.
 */
function ewt_strip_ai_citations( $text ) {
	if ( ! $text ) return $text;

	// [[5, 3]], [[12]] gibi çift köşeli parantez atıfları
	$text = preg_replace( '/\s*\[\[\s*\d+(?:\s*,\s*\d+)*\s*\]\]/u', '', $text );
	// [5, 3], [12] gibi tekli köşeli parantez atıfları (markdown link syntax'ı [metin](url) etkilenmez, çünkü içerik yalnızca sayılardan oluşmalı)
	$text = preg_replace( '/\s*\[\s*\d+(?:\s*,\s*\d+)*\s*\](?!\()/u', '', $text );

	// Noktalama öncesi kalan boşlukları ve fazla boşlukları düzelt
	$text = preg_replace( '/[ \t]+([.,!?;:])/u', '$1', $text );
	$text = preg_replace( '/[ \t]{2,}/u', ' ', $text );

	return trim( $text );
}

function ewt_add_ai_content_box() {
	foreach ( array_merge( array_keys( ewt_cpt_config() ), array( 'post' ) ) as $pt ) {
		add_meta_box( 'ewt_ai_content_box', '🤖 AI İçerik Oluştur', 'ewt_ai_content_box_html', $pt, 'side', 'high' );
	}
}
add_action( 'add_meta_boxes', 'ewt_add_ai_content_box' );

/**
 * CPT'ye göre özelleştirilmiş sistem promptu (yaş grubu / sınav türüne uygun üslup talimatı).
 */
function ewt_content_system_prompt( $post_type = '' ) {
	$base = 'Sen deneyimli bir İngilizce öğretmenisin. Türk öğrenciler için pedagojik olarak sağlam, SEO uyumlu HTML formatlı ders içeriği üretiyorsun. Başlıklar için <h2>/<h3>, örnekler için <ul><li> kullan. Cevabının başına veya sonuna herhangi bir giriş/kapanış cümlesi ekleme, doğrudan HTML içerikle başla.';

	$cpt_style = array(
		'ewt_preschool'  => 'okul öncesi (3-6 yaş) çocuklara yönelik, çok basit, oyunlaştırılmış, tekrar eden ve görsel destekli',
		'ewt_primary'    => 'ilkokul (7-10 yaş) öğrencilerine yönelik, eğlenceli, kısa cümleli ve basit örnekli',
		'ewt_secondary'  => 'ortaokul öğrencilerine yönelik, günlük hayattan örneklerle desteklenmiş',
		'ewt_highschool' => 'lise öğrencilerine yönelik, sınav odaklı ve akademik',
		'ewt_ydt'        => 'lise son sınıf ve YKS/AYT\'ye hazırlanan öğrencilere yönelik, dilbilgisi ve kelime bilgisi ağırlıklı, çıkmış soru mantığına uygun',
		'ewt_toefl'      => 'TOEFL sınavına hazırlanan öğrencilere yönelik, akademik İngilizce ve sınav stratejileri içeren',
		'ewt_gre'        => 'GRE sınavına hazırlanan öğrencilere yönelik, ileri düzey akademik kelime ve analitik okuma odaklı',
		'ewt_yds'        => 'YDS sınavına hazırlanan öğrencilere yönelik, dilbilgisi ve çeviri odaklı',
		'ewt_gmat'       => 'GMAT sınavına hazırlanan öğrencilere yönelik, iş İngilizcesi ve analitik okuma/verbal odaklı',
		'ewt_ielts'      => 'IELTS sınavına (Academic veya General Training) hazırlanan öğrencilere yönelik, band score stratejileri ve gerçek sınav formatına uygun',
		'ewt_yokdil'     => 'YÖKDİL sınavına (Fen/Sağlık/Sosyal Bilimler alanlarından birine) hazırlanan akademisyen adaylarına yönelik, alanına özgü akademik terminoloji ve paragraf/çeviri odaklı',
		'ewt_mesleki'    => 'çalışan yetişkinlere ve mesleki İngilizce ihtiyacı olan profesyonellere yönelik, gerçek iş hayatı senaryoları ve sektöre özgü terminoloji içeren',
	);

	if ( isset( $cpt_style[ $post_type ] ) ) {
		$base .= ' İçerik özellikle ' . $cpt_style[ $post_type ] . ' bir üslupla yazılmalı.';
	}

	return $base;
}

/**
 * Kullanıcı promptunu; seçilen CPT'ye uygun zorluk derecesi açıklaması ve hedef kelime sayısıyla zenginleştirir.
 * Çocuklara yönelik CPT'lerde (okul öncesi/ilkokul) somut nesne/hayvan/renk isimlerinin vurgulanmasını ister,
 * böylece bu kelimeler daha sonra görsel (flashcard) eşleştirmede kullanılabilir.
 */
function ewt_content_user_prompt( $topic, $post_type, $difficulty_value, $word_count, $focus_keyword = '' ) {
	$opts = ewt_difficulty_options_for_cpt( $post_type );
	$difficulty_label = isset( $opts[ $difficulty_value ] ) ? $opts[ $difficulty_value ] : $difficulty_value;
	$word_count = max( 50, min( 5000, intval( $word_count ) ?: 400 ) );

	$prompt  = $topic;
	$prompt .= "\n\nZorluk derecesi: " . $difficulty_label . '.';
	$prompt .= ' Yaklaşık ' . $word_count . ' kelime uzunluğunda yaz.';

	if ( in_array( $post_type, array( 'ewt_preschool', 'ewt_primary' ), true ) ) {
		$prompt .= ' İçerikte geçen somut nesne/hayvan/renk/meyve gibi görselleştirilebilir kelimeleri <strong> ile vurgula; bu kelimeler daha sonra görsel eşleştirme (flashcard) için kullanılacak.';
	}

	if ( $focus_keyword ) $prompt .= ewt_seo_prompt_rules( $focus_keyword, $word_count );
	return $prompt;
}

/**
 * OTOMATİK SEVİYE TESPİTİ modunda kullanılan kullanıcı promptu.
 * ewt_content_user_prompt()'un aksine, burada zorluk derecesi ELLE verilmez;
 * bunun yerine CPT'nin gerçek "Seviye" taxonomy terimleri AI'ya listelenir ve
 * AI'dan hem en uygun seviyeyi seçmesi hem de o seviyeye uygun içerik üretmesi istenir.
 *
 * AI'nin seçtiği seviyeyi güvenilir şekilde ayıklamak için, yanıtın EN BAŞINA
 * <!-- EWT_SEVIYE: slug --> şeklinde bir HTML yorumu yazması istenir (görünmez, zararsız);
 * bu satır ewt_extract_seviye_marker() ile ayıklanıp içerikten temizlenir.
 *
 * @param array $terms [ [ 'slug' => ..., 'name' => ... ], ... ] — ewt_get_seviye_terms() çıktısı
 */
function ewt_content_user_prompt_auto_seviye( $topic, $post_type, $terms, $word_count, $focus_keyword = '' ) {
	$word_count = max( 50, min( 5000, intval( $word_count ) ?: 400 ) );

	$term_list = array();
	foreach ( $terms as $t ) {
		$term_list[] = $t['slug'] . ' (' . $t['name'] . ')';
	}

	$prompt  = $topic;
	$prompt .= "\n\nBu içerik için mevcut seviye kategorileri: " . implode( ', ', $term_list ) . '.';
	$prompt .= ' Konuya en uygun SEVİYEYİ bu listeden seç ve içeriği o seviyeye uygun zorlukta yaz.';
	$prompt .= ' Yaklaşık ' . $word_count . ' kelime uzunluğunda yaz.';
	$prompt .= ' Yanıtının EN BAŞINA, başka hiçbir şey yazmadan, tam olarak şu formatta bir satır ekle: <!-- EWT_SEVIYE: secilen-slug --> (secilen-slug yerine yukarıdaki listeden seçtiğin TAM slug\'ı yaz). Bu satırdan hemen sonra, boşluk bırakmadan doğrudan HTML içeriğe başla.';

	if ( in_array( $post_type, array( 'ewt_preschool', 'ewt_primary' ), true ) ) {
		$prompt .= ' İçerikte geçen somut nesne/hayvan/renk/meyve gibi görselleştirilebilir kelimeleri <strong> ile vurgula; bu kelimeler daha sonra görsel eşleştirme (flashcard) için kullanılacak.';
	}

	if ( $focus_keyword ) $prompt .= ewt_seo_prompt_rules( $focus_keyword, $word_count );
	return $prompt;
}

/**
 * AI yanıtının başındaki <!-- EWT_SEVIYE: slug --> işaretini ayıklar ve içerikten temizler.
 * $valid_slugs verilirse, sadece bu listede olan bir slug kabul edilir (halüsinasyona karşı korumalı);
 * eşleşme yoksa seviye null döner ama içerik olduğu gibi (marker temizlenmiş) kullanılabilir.
 *
 * @return array [ 'seviye_slug' => string|null, 'content' => string ]
 */
function ewt_extract_seviye_marker( $content, $valid_slugs = null ) {
	$seviye_slug = null;

	if ( preg_match( '/^\s*<!--\s*EWT_SEVIYE:\s*([a-z0-9\-]+)\s*-->\s*/i', $content, $m ) ) {
		$candidate = sanitize_title( $m[1] );
		if ( $valid_slugs === null || in_array( $candidate, $valid_slugs, true ) ) {
			$seviye_slug = $candidate;
		}
		$content = preg_replace( '/^\s*<!--\s*EWT_SEVIYE:\s*([a-z0-9\-]+)\s*-->\s*/i', '', $content, 1 );
	}

	return array( 'seviye_slug' => $seviye_slug, 'content' => $content );
}

function ewt_ai_content_box_html( $post ) {
	$default_provider = get_option( 'ewt_default_ai_provider', 'youcom' );
	$difficulty_opts   = ewt_difficulty_options_for_cpt( $post->post_type );
	wp_nonce_field( 'ewt_ai_generate', 'ewt_ai_nonce' );
	?>
	<div class="ewt-ai-box">
		<p>
			<label for="ewt_ai_provider"><strong>Sağlayıcı</strong></label>
			<select id="ewt_ai_provider" class="widefat">
				<option value="youcom" <?php selected( $default_provider, 'youcom' ); ?>>You.com</option>
				<option value="claude" <?php selected( $default_provider, 'claude' ); ?>>Claude</option>
				<option value="openai" <?php selected( $default_provider, 'openai' ); ?>>OpenAI</option>
			</select>
		</p>
		<p>
			<label for="ewt_ai_difficulty"><strong>Konu Anlatım Zorluk Derecesi</strong></label>
			<select id="ewt_ai_difficulty" class="widefat">
				<?php ewt_render_difficulty_select_options( $difficulty_opts ); ?>
			</select>
			<span class="description">Bu içerik türüne (<?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?>) uygun zorluk seçenekleri otomatik listelenir.</span>
		</p>
		<p>
			<label for="ewt_ai_word_count"><strong>Hedef Kelime Sayısı</strong></label>
			<input type="number" id="ewt_ai_word_count" class="widefat" value="400" min="50" max="5000" step="50" />
		</p>
		<p>
			<label for="ewt_ai_prompt"><strong>Konu / Talimat</strong></label>
			<textarea id="ewt_ai_prompt" class="widefat" rows="4" placeholder="örn: 'be' fiili konu anlatımı, örnekler ve Türkçe çeviri içeren bir ders metni yaz."></textarea>
		</p>
		<p>
			<button type="button" class="button button-primary widefat" id="ewt_ai_generate_btn">İçerik Üret</button>
		</p>
		<p id="ewt_ai_status" style="display:none;"></p>
		<p class="description">Üretilen metin editöre eklenir; yayınlamadan önce mutlaka pedagojik olarak gözden geçirin.</p>
	</div>
	<?php
}

function ewt_enqueue_ai_admin_js( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
	wp_enqueue_script( 'ewt-ai-admin', EWT_URI . '/assets/js/admin-ai-widget.js', array( 'jquery', 'wp-data', 'wp-blocks' ), EWT_VERSION, true );
	wp_localize_script( 'ewt-ai-admin', 'ewtAi', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ewt_ai_generate' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_enqueue_ai_admin_js' );

/**
 * AJAX handler: seçilen sağlayıcıya göre içerik üret
 */
function ewt_ajax_generate_content() {
	check_ajax_referer( 'ewt_ai_generate', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$provider   = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : 'youcom';
	$topic      = isset( $_POST['prompt'] ) ? sanitize_textarea_field( $_POST['prompt'] ) : '';
	$post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$difficulty = isset( $_POST['difficulty'] ) ? sanitize_text_field( $_POST['difficulty'] ) : '';
	$word_count = isset( $_POST['word_count'] ) ? intval( $_POST['word_count'] ) : 400;

	if ( empty( $topic ) ) wp_send_json_error( 'Lütfen bir talimat girin.' );

	$post_type     = $post_id ? get_post_type( $post_id ) : 'post';
	$system_prompt = ewt_content_system_prompt( $post_type );
	$focus_keyword = isset( $_POST['focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ) ) : ewt_seo_focus( $post_id );
	$prompt        = ewt_content_user_prompt( $topic, $post_type, $difficulty, $word_count, $focus_keyword );

	switch ( $provider ) {
		case 'claude':
			$result = ewt_call_claude_api( $system_prompt, $prompt );
			break;
		case 'openai':
			$result = ewt_call_openai_api( $system_prompt, $prompt );
			break;
		default:
			$result = ewt_call_youcom_api( $system_prompt, $prompt );
			break;
	}

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( array( 'content' => $result ) );
}
add_action( 'wp_ajax_ewt_generate_content', 'ewt_ajax_generate_content' );

/**
 * $raw = true verilirse wpautop() UYGULANMADAN, sadece atıf temizliği yapılmış ham metin döner.
 * Bu, otomatik seviye tespiti modunda <!-- EWT_SEVIYE: slug --> işaretini wpautop paragraf
 * sarmalamasına girmeden güvenilir şekilde ayıklamak için gereklidir (bkz. ewt_extract_seviye_marker()).
 * Varsayılan davranış (mevcut tekil widget ve normal toplu üretim) HİÇ DEĞİŞMEDİ.
 */
/**
 * Sağlayıcı adına (youcom/claude/openai) göre doğru API fonksiyonunu çağıran ortak kısayol.
 */
function ewt_call_ai_provider( $provider, $system, $prompt, $raw = false ) {
	switch ( $provider ) {
		case 'claude': return ewt_call_claude_api( $system, $prompt, $raw );
		case 'openai': return ewt_call_openai_api( $system, $prompt, $raw );
		default:       return ewt_call_youcom_api( $system, $prompt, $raw );
	}
}

/** Hedef uzunluğa göre çıktı bütçesi; kelime sayısı bir üretim hedefidir. */
function ewt_content_token_budget( $prompt ) {
	$words = preg_match( '/Yaklaşık\s+(\d+)\s+kelime/u', $prompt, $matches ) ? min( 5000, (int) $matches[1] ) : 400;
	return max( 2000, $words * 4 );
}

function ewt_call_claude_api( $system, $prompt, $raw = false ) {
	$api_key = get_option( 'ewt_claude_api_key' );
	if ( ! $api_key ) return new WP_Error( 'no_key', 'Claude API anahtarı ayarlanmamış. Görünüm > English Web Ayarları\'ndan ekleyin.' );

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => ewt_content_token_budget( $prompt ) > 4000 ? 180 : 60,
		'headers' => array(
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		),
		'body' => wp_json_encode( array(
			'model'      => 'claude-sonnet-5',
			'max_tokens' => ewt_content_token_budget( $prompt ),
			'system'     => $system,
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) ),
	) );

	if ( is_wp_error( $response ) ) return $response;
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['content'][0]['text'] ) ) return new WP_Error( 'api_error', 'Claude API yanıtı alınamadı: ' . wp_remote_retrieve_body( $response ) );
	if ( isset( $body['stop_reason'] ) && $body['stop_reason'] === 'max_tokens' ) return new WP_Error( 'ewt_truncated', 'Claude yanıtı uzunluk sınırında kesildi. Daha düşük kelime hedefiyle yeniden deneyin; yazı oluşturulmadı.' );
	$clean = ewt_strip_ai_citations( $body['content'][0]['text'] );
	return $raw ? $clean : wpautop( $clean );
}

function ewt_call_openai_api( $system, $prompt, $raw = false ) {
	$api_key = get_option( 'ewt_openai_api_key' );
	if ( ! $api_key ) return new WP_Error( 'no_key', 'OpenAI API anahtarı ayarlanmamış. Görünüm > English Web Ayarları\'ndan ekleyin.' );

	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
		'timeout' => ewt_content_token_budget( $prompt ) > 4000 ? 180 : 60,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'body' => wp_json_encode( array(
			'model'    => 'gpt-4o-mini',
			'max_tokens' => min( 16384, ewt_content_token_budget( $prompt ) ),
			'messages' => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
		) ),
	) );

	if ( is_wp_error( $response ) ) return $response;
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['choices'][0]['message']['content'] ) ) return new WP_Error( 'api_error', 'OpenAI API yanıtı alınamadı: ' . wp_remote_retrieve_body( $response ) );
	if ( isset( $body['choices'][0]['finish_reason'] ) && $body['choices'][0]['finish_reason'] === 'length' ) return new WP_Error( 'ewt_truncated', 'OpenAI yanıtı uzunluk sınırında kesildi. Daha düşük kelime hedefiyle yeniden deneyin; yazı oluşturulmadı.' );
	$clean = ewt_strip_ai_citations( $body['choices'][0]['message']['content'] );
	return $raw ? $clean : wpautop( $clean );
}

/**
 * You.com API — Research ve Express modları desteklenir (Görünüm > English Web Ayarları'ndan seçilir).
 * Research: https://api.you.com/v1/research  (derinlemesine, kaynak destekli, daha yavaş/kaliteli)
 * Express:  https://api.you.com/v1/ai/search (arama + LLM cevabı tek çağrıda, hızlı/ucuz)
 */
function ewt_call_youcom_api( $system, $prompt, $raw = false ) {
	$api_key = get_option( 'ewt_youcom_api_key' );
	if ( ! $api_key ) return new WP_Error( 'no_key', 'You.com API anahtarı ayarlanmamış. Görünüm > English Web Ayarları\'ndan ekleyin.' );

	$mode        = get_option( 'ewt_youcom_mode', 'research' );
	$full_prompt = $system . "\n\n" . $prompt;

	return ( $mode === 'express' )
		? ewt_youcom_express( $full_prompt, $api_key, $raw )
		: ewt_youcom_research( $full_prompt, $api_key, $raw );
}

function ewt_youcom_research( $prompt, $key, $raw = false, $timeout = 180, $effort_override = null ) {
	$effort = $effort_override === null ? get_option( 'ewt_youcom_research_effort', 'standard' ) : $effort_override;
	if ( ! in_array( $effort, array( 'lite', 'standard', 'exhaustive' ), true ) ) $effort = 'standard';
	$response = wp_remote_post( 'https://api.you.com/v1/research', array(
		'timeout' => max( 1, min( 180, (int) $timeout ) ),
		'headers' => array( 'Content-Type' => 'application/json', 'X-API-Key' => $key ),
		'body'    => wp_json_encode( array( 'input' => $prompt, 'research_effort' => $effort ) ),
	) );

	if ( is_wp_error( $response ) ) return $response;
	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'youcom_http_error', 'You.com Research API hatası (HTTP ' . $code . ').', array( 'status' => $code ) );
	}
	if ( ! is_array( $body ) ) {
		return new WP_Error( 'youcom_invalid_response', 'You.com geçerli bir JSON yanıtı döndürmedi.' );
	}
	foreach ( array( $body['output']['content'] ?? null, $body['answer'] ?? null ) as $candidate ) {
		if ( is_string( $candidate ) && trim( $candidate ) !== '' ) {
			$c = ewt_strip_ai_citations( $candidate );
			return $raw ? $c : wpautop( $c );
		}
	}

	$err = isset( $body['detail'] ) ? $body['detail'] : ( isset( $body['message'] ) ? $body['message'] : 'You.com Research API hatası (HTTP ' . $code . ').' );
	return new WP_Error( 'youcom_error', is_array( $err ) ? wp_json_encode( $err ) : $err );
}

function ewt_youcom_express( $prompt, $key, $raw = false, $timeout = 120 ) {
	$response = wp_remote_post( 'https://api.you.com/v1/ai/search', array(
		'timeout' => max( 1, min( 120, (int) $timeout ) ),
		'headers' => array( 'Content-Type' => 'application/json', 'X-API-Key' => $key ),
		'body'    => wp_json_encode( array( 'query' => $prompt ) ),
	) );

	if ( is_wp_error( $response ) ) return $response;
	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'youcom_http_error', 'You.com Express API hatası (HTTP ' . $code . ').', array( 'status' => $code ) );
	}
	if ( ! is_array( $body ) ) {
		return new WP_Error( 'youcom_invalid_response', 'You.com geçerli bir JSON yanıtı döndürmedi.' );
	}
	$candidates = array(
		$body['answer'] ?? null,
		$body['output']['content'] ?? null,
		$body['choices'][0]['message']['content'] ?? null,
	);
	foreach ( $candidates as $candidate ) {
		if ( is_string( $candidate ) && trim( $candidate ) !== '' ) {
			$c = ewt_strip_ai_citations( $candidate );
			return $raw ? $c : wpautop( $c );
		}
	}

	$err = isset( $body['detail'] ) ? $body['detail'] : 'You.com Express API hatası (HTTP ' . $code . ').';
	return new WP_Error( 'youcom_express_error', is_array( $err ) ? wp_json_encode( $err ) : $err );
}
