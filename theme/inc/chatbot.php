<?php
/**
 * CHATBOT
 *
 * Genel yönlendirme ve tekil ders sayfalarında bağlama duyarlı AI yardımcısı.
 * You.com anahtarı yalnızca sunucuda okunur; tarayıcıya gönderilmez.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function ewt_render_chatbot() {
	$contact_enabled = ewt_campus_options()['contact_enabled'] === '1';
	$phone = get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' );
	$wa = ewt_campus_wa();
	$greet = get_option( 'ewt_chatbot_greeting', "Merhaba! 👋 English Web TR'ye hoş geldin. Sana nasıl yardımcı olabilirim?" );
	?>
	<div id="ewt-chatbot" aria-live="polite">
		<button type="button" id="ewt-chatbot-toggle" aria-label="Sorunuz var mı? Sohbeti aç/kapat" aria-controls="ewt-chatbot-panel" aria-expanded="false">
			<span class="ewt-chat-icon" aria-hidden="true">💬</span>
			<span class="ewt-chat-label">Sorunuz var mı?</span>
		</button>
		<div id="ewt-chatbot-panel" class="ewt-hidden">
			<div class="ewt-chatbot-header">
				<span>English Web TR Asistan</span>
				<button id="ewt-chatbot-close" aria-label="Kapat">✕</button>
			</div>
			<div id="ewt-chatbot-messages">
				<div class="ewt-msg ewt-bot"><?php echo esc_html( $greet ); ?></div>
			</div>
			<div id="ewt-chatbot-quick-replies"></div>
			<form id="ewt-chatbot-question" class="ewt-hidden">
				<label for="ewt-chatbot-input">Bu paragrafla ilgili sorun nedir?</label>
				<div class="ewt-chatbot-input-row">
					<textarea id="ewt-chatbot-input" maxlength="500" rows="2" placeholder="Sorunu buraya yaz..."></textarea>
					<button type="button" id="ewt-chatbot-mic" aria-label="Soruyu sesle yaz" title="Soruyu sesle yaz">🎤</button>
					<button type="submit" id="ewt-chatbot-send">Gönder</button>
				</div>
				<p id="ewt-chatbot-voice-status" class="ewt-chatbot-status" aria-live="polite"></p>
			</form>
			<?php if ( $contact_enabled ) : ?><div class="ewt-chatbot-footer">
				<a href="tel:<?php echo esc_attr( $phone ); ?>" class="ewt-chatbot-cta ewt-call">📞 Ara</a>
				<a href="https://wa.me/<?php echo esc_attr( $wa ); ?>" target="_blank" rel="noopener" class="ewt-chatbot-cta ewt-wa">WhatsApp</a>
			</div><?php endif; ?>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'ewt_render_chatbot' );

/** Ziyaretçi başına basit saatlik maliyet koruması. */
function ewt_lesson_assistant_rate_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	return 'ewt_lesson_help_' . hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
}

/** Keep handled application errors readable behind hosts that replace 4xx/5xx bodies. */
function ewt_lesson_assistant_error( $message, $status, $code ) {
	nocache_headers();
	wp_send_json_error( array(
		'message' => $message,
		'code' => $code,
		'http_status' => $status,
	), 200 );
}

function ewt_ajax_lesson_assistant() {
	foreach ( array( 'nonce', 'request_type', 'context', 'question', 'title', 'post_id' ) as $field ) {
		if ( isset( $_POST[ $field ] ) && ! is_scalar( $_POST[ $field ] ) ) {
			ewt_lesson_assistant_error( 'İstek biçimi geçersiz. Sayfayı yenileyip tekrar deneyin.', 400, 'invalid_input' );
		}
	}
	if ( ! check_ajax_referer( 'ewt_lesson_assistant', 'nonce', false ) ) {
		ewt_lesson_assistant_error( 'Oturum doğrulanamadı. Lütfen sayfayı yenileyin.', 403, 'invalid_nonce' );
	}

	$rate_key = ewt_lesson_assistant_rate_key();
	$used     = (int) get_transient( $rate_key );
	if ( $used >= 10 ) {
		ewt_lesson_assistant_error( 'Saatlik yardım sınırına ulaştınız. Bir süre sonra yeniden deneyin.', 429, 'rate_limit' );
	}

	$action_type = isset( $_POST['request_type'] ) ? sanitize_key( wp_unslash( $_POST['request_type'] ) ) : 'question';
	$context     = isset( $_POST['context'] ) ? sanitize_textarea_field( wp_unslash( $_POST['context'] ) ) : '';
	$question    = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
	$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

	$context  = function_exists( 'mb_substr' ) ? mb_substr( $context, 0, 1800 ) : substr( $context, 0, 1800 );
	$question = function_exists( 'mb_substr' ) ? mb_substr( $question, 0, 500 ) : substr( $question, 0, 500 );
	$title    = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 180 ) : substr( $title, 0, 180 );

	if ( ! in_array( $action_type, array( 'examples', 'question' ), true ) || strlen( $context ) < 20 ) {
		ewt_lesson_assistant_error( 'Yardım için yeterli paragraf bağlamı bulunamadı.', 400, 'invalid_context' );
	}
	if ( $action_type === 'question' && strlen( $question ) < 3 ) {
		ewt_lesson_assistant_error( 'Lütfen sorunuzu biraz daha ayrıntılı yazın.', 400, 'invalid_question' );
	}
	if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
		ewt_lesson_assistant_error( 'Bu içerik yardım için uygun değil.', 400, 'invalid_post' );
	}

	$system = 'Sen English Web TR sitesinin sabırlı İngilizce öğretmenisin. Yalnızca verilen ders başlığı ve paragraf bağlamına dayan. Türkçe, kısa, anlaşılır ve öğrencinin seviyesine uygun yanıt ver. Gerektiğinde İngilizce örneklerin hemen ardından Türkçe anlamlarını yaz. Bilmediğin veya bağlamda olmayan bir noktayı uydurma. Markdown tablo kullanma.';
	if ( $action_type === 'examples' ) {
		$prompt = "Ders: {$title}\nParagraf: {$context}\n\nBu paragraftaki kuralı veya fikri tek cümlede sadeleştir; ardından birbirinden farklı 4 yeni İngilizce örnek ve Türkçe karşılıklarını ver. En sonda öğrencinin anlayıp anlamadığını ölçen tek bir kısa soru sor.";
	} else {
		$prompt = "Ders: {$title}\nParagraf: {$context}\nÖğrencinin sorusu: {$question}\n\nSoruyu doğrudan yanıtla. Gerekirse en fazla 3 kısa örnek ver ve yaygın bir hatayı belirt.";
	}

	$mode = get_option( 'ewt_youcom_mode', 'research' ) === 'express' ? 'express' : 'research';
	$cache_key = 'ewt_help_' . md5( $mode . '|' . $action_type . '|' . $title . '|' . $context . '|' . $question );
	$answer    = get_transient( $cache_key );
	if ( ! is_string( $answer ) || $answer === '' ) {
		$key = get_option( 'ewt_youcom_api_key' );
		if ( ! $key ) {
			ewt_lesson_assistant_error( 'You.com API anahtarı henüz ayarlanmamış.', 503, 'missing_key' );
		}
		// Failed provider calls can also consume credit: reserve this attempt first.
		set_transient( $rate_key, $used + 1, HOUR_IN_SECONDS );
		try {
			// Return a controlled error before common 30-second hosting limits.
			$full_prompt = $system . "\n\n" . $prompt . "\nYanıtı en fazla 250 kelimeyle sınırla.";
			$answer = $mode === 'express'
					? ewt_youcom_express( $full_prompt, $key, true, 20 )
					: ewt_youcom_research( $full_prompt, $key, true, 20, 'lite' );
		} catch ( Throwable $error ) {
			error_log( 'EWT lesson assistant: provider_exception' );
			ewt_lesson_assistant_error( 'Yanıt işlenirken sunucu hatası oluştu (EWT-PROVIDER). Site yöneticisi PHP hata günlüğünü kontrol etmeli.', 500, 'provider_exception' );
		}
		if ( is_wp_error( $answer ) ) {
			error_log( 'EWT lesson assistant: ' . $answer->get_error_code() );
			$message = 'You.com yanıtı alınamadı. Biraz sonra yeniden deneyin (EWT-PROVIDER).';
			$data = $answer->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
			if ( $answer->get_error_code() === 'http_request_failed' ) {
				$message = 'You.com bağlantısı tamamlanamadı; zaman aşımı veya bağlantı sorunu olabilir (EWT-CONNECTION). Lütfen biraz sonra deneyin.';
				if ( preg_match( '/cURL error 28|timed out|timeout/i', $answer->get_error_message() ) ) {
					$message = 'You.com ' . ucfirst( $mode ) . ' 20 saniye içinde yanıt vermedi (EWT-YOUCOM-TIMEOUT). Lütfen biraz sonra yeniden deneyin.';
				}
			} elseif ( in_array( $status, array( 401, 403 ), true ) ) {
				$message = 'You.com erişimi reddetti (HTTP ' . $status . '). Site yöneticisi kayıtlı API anahtarını ve API yetkisini kontrol etmeli.';
			} elseif ( in_array( $status, array( 402, 429 ), true ) ) {
				$message = 'You.com kullanım sınırı yanıtı verdi (HTTP ' . $status . '). Site yöneticisi kredi bakiyesini ve istek limitini kontrol etmeli.';
			} elseif ( $status ) {
				$message = 'You.com servis hatası (HTTP ' . $status . '). Lütfen site yöneticisine bu kodu iletin.';
			} elseif ( $answer->get_error_code() === 'youcom_invalid_response' ) {
				$message = 'You.com beklenen yanıt biçimini göndermedi (EWT-FORMAT).';
			}
			ewt_lesson_assistant_error( $message, 502, $answer->get_error_code() );
		}
		if ( ! is_string( $answer ) || trim( $answer ) === '' ) {
			ewt_lesson_assistant_error( 'You.com boş bir yanıt döndürdü (EWT-EMPTY).', 502, 'empty_answer' );
		}
		$answer = sanitize_textarea_field( $answer );
		set_transient( $cache_key, $answer, DAY_IN_SECONDS );
	}

	wp_send_json_success( array( 'answer' => $answer ) );
}
add_action( 'wp_ajax_ewt_lesson_assistant', 'ewt_ajax_lesson_assistant' );
add_action( 'wp_ajax_nopriv_ewt_lesson_assistant', 'ewt_ajax_lesson_assistant' );
