<?php
/**
 * TEMA AYARLARI SAYFASI
 * Görünüm > English Web Ayarları altında:
 * - You.com / Claude / OpenAI API anahtarları (AI içerik + otomatik görsel widget'ları bunları kullanır)
 * - Özel ders yönlendirme telefon numarası (chatbot burayı kullanır)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function ewt_settings_menu() {
	add_theme_page(
		'English Web Ayarları',
		'English Web Ayarları',
		'manage_options',
		'ewt-settings',
		'ewt_settings_page_html'
	);
}
add_action( 'admin_menu', 'ewt_settings_menu' );

function ewt_register_settings() {
	$fields = array(
		'ewt_youcom_api_key'   => 'sanitize_text_field',
		'ewt_youcom_mode'      => 'sanitize_text_field',
		'ewt_youcom_research_effort' => 'sanitize_text_field',
		'ewt_claude_api_key'   => 'sanitize_text_field',
		'ewt_openai_api_key'   => 'sanitize_text_field',
		'ewt_pexels_api_key'   => 'sanitize_text_field',
		'ewt_default_ai_provider' => 'sanitize_text_field',
		'ewt_default_image_provider' => 'sanitize_text_field',
		'ewt_tutoring_phone'   => 'sanitize_text_field',
		'ewt_chatbot_greeting' => 'sanitize_textarea_field',
		'ewt_generator_enabled'    => 'ewt_sanitize_bool_01',
		'ewt_generator_rate_limit' => 'absint',
		'ewt_lab_enabled'          => 'ewt_sanitize_bool_01',
		'ewt_lab_auto_insert'      => 'ewt_sanitize_bool_01',
		'ewt_lab_quiz_questions'   => 'absint',
		'ewt_ebook_enabled'        => 'ewt_sanitize_bool_01',
		'ewt_ebook_rate_limit'     => 'absint',
		'ewt_ebook_max_posts'      => 'absint',
		'ewt_ebook_email_capture'  => 'ewt_sanitize_bool_01',
		'ewt_ebook_home_enabled'   => 'ewt_sanitize_bool_01',
		'ewt_ebook_page_auto'      => 'ewt_sanitize_bool_01',
		'ewt_ebook_page_slug'      => 'sanitize_title',
		'ewt_ebook_cta_enabled'    => 'ewt_sanitize_bool_01',
	);
	foreach ( $fields as $key => $cb ) {
		register_setting( 'ewt_settings_group', $key, array( 'sanitize_callback' => $cb ) );
	}
}
add_action( 'admin_init', 'ewt_register_settings' );

/**
 * Checkbox tipi ayarlar için ortak sanitize fonksiyonu — sadece "1" veya "0" döner.
 */
function ewt_sanitize_bool_01( $value ) {
	return $value === '1' ? '1' : '0';
}

function ewt_settings_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	?>
	<div class="wrap">
		<h1>English Web TR - Tema Ayarları</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'ewt_settings_group' ); ?>

			<div class="ewt-youcom-hero">
				<strong>You.com</strong> — Research ve Express API'leri aracılığıyla gerçek zamanlı, web destekli AI yanıtları sunar. Yeni hesaplarda genellikle $100 ücretsiz kredi bulunur.
				<a href="https://you.com/platform/api-keys" target="_blank" rel="noopener" class="button button-small">API Anahtarı Al →</a>
				<a href="https://docs.you.com" target="_blank" rel="noopener" class="button button-small">Dokümantasyon</a>
			</div>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ewt_default_ai_provider">Varsayılan AI Sağlayıcı</label></th>
					<td>
						<select name="ewt_default_ai_provider" id="ewt_default_ai_provider">
							<?php $current = get_option( 'ewt_default_ai_provider', 'youcom' ); ?>
							<option value="youcom" <?php selected( $current, 'youcom' ); ?>>You.com ($100 ücretsiz kredi)</option>
							<option value="claude" <?php selected( $current, 'claude' ); ?>>Claude (Anthropic)</option>
							<option value="openai" <?php selected( $current, 'openai' ); ?>>OpenAI</option>
						</select>
						<p class="description">İçerik oluşturma ve öne çıkan görsel widget'larında varsayılan olarak kullanılır; her yazıda değiştirilebilir.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_youcom_api_key">You.com API Anahtarı</label></th>
					<td>
						<input type="password" class="regular-text" name="ewt_youcom_api_key" id="ewt_youcom_api_key" value="<?php echo esc_attr( get_option( 'ewt_youcom_api_key' ) ); ?>" autocomplete="off" />
						<button type="button" class="button ewt-test-provider-btn" data-provider="youcom" data-key-field="ewt_youcom_api_key">✓ Bağlantıyı Test Et</button>
						<span class="ewt-test-result" data-for="youcom"></span>
						<p class="description">Anahtarınızı <a href="https://you.com/platform/api-keys" target="_blank" rel="noopener">you.com/platform/api-keys</a> adresinden alabilirsiniz. Hesabınızda $100 ücretsiz kredi bulunur.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">You.com API Modu</th>
					<td>
						<?php $yc_mode = get_option( 'ewt_youcom_mode', 'research' ); ?>
						<label><input type="radio" name="ewt_youcom_mode" value="research" <?php checked( $yc_mode, 'research' ); ?> /> <strong>Research API</strong> — çok adımlı, kaynak destekli, en kaliteli sonuç (daha yavaş)</label><br />
						<label><input type="radio" name="ewt_youcom_mode" value="express" <?php checked( $yc_mode, 'express' ); ?> /> <strong>Express API</strong> — arama + LLM cevabı tek çağrıda, daha hızlı ve düşük maliyetli</label>
					</td>
				</tr>
				<tr id="ewt-youcom-effort-row">
					<th scope="row"><label for="ewt_youcom_research_effort">Research Effort</label></th>
					<td>
						<?php $yc_effort = get_option( 'ewt_youcom_research_effort', 'standard' ); ?>
						<select name="ewt_youcom_research_effort" id="ewt_youcom_research_effort">
							<option value="lite" <?php selected( $yc_effort, 'lite' ); ?>>Lite — en hızlı, daha az kaynak</option>
							<option value="standard" <?php selected( $yc_effort, 'standard' ); ?>>Standard — dengeli (önerilen)</option>
							<option value="exhaustive" <?php selected( $yc_effort, 'exhaustive' ); ?>>Exhaustive — en derin, en yavaş, en fazla kredi</option>
						</select>
						<p class="description">Sadece Research modu için geçerlidir. Standard, Lite'ın 2-3 katı kredi harcar.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_claude_api_key">Claude (Anthropic) API Anahtarı</label></th>
					<td>
						<input type="password" class="regular-text" name="ewt_claude_api_key" id="ewt_claude_api_key" value="<?php echo esc_attr( get_option( 'ewt_claude_api_key' ) ); ?>" autocomplete="off" />
						<button type="button" class="button ewt-test-provider-btn" data-provider="claude" data-key-field="ewt_claude_api_key">✓ Bağlantıyı Test Et</button>
						<span class="ewt-test-result" data-for="claude"></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_openai_api_key">OpenAI API Anahtarı</label></th>
					<td>
						<input type="password" class="regular-text" name="ewt_openai_api_key" id="ewt_openai_api_key" value="<?php echo esc_attr( get_option( 'ewt_openai_api_key' ) ); ?>" autocomplete="off" />
						<button type="button" class="button ewt-test-provider-btn" data-provider="openai" data-key-field="ewt_openai_api_key">✓ Bağlantıyı Test Et</button>
						<span class="ewt-test-result" data-for="openai"></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_pexels_api_key">Pexels API Anahtarı</label></th>
					<td>
						<input type="password" class="regular-text" name="ewt_pexels_api_key" id="ewt_pexels_api_key" value="<?php echo esc_attr( get_option( 'ewt_pexels_api_key' ) ); ?>" autocomplete="off" />
						<button type="button" class="button ewt-test-provider-btn" data-provider="pexels" data-key-field="ewt_pexels_api_key">✓ Bağlantıyı Test Et</button>
						<span class="ewt-test-result" data-for="pexels"></span>
						<p class="description">Ücretsiz anahtar: <a href="https://www.pexels.com/api/" target="_blank" rel="noopener">pexels.com/api</a>. Toplu ve tekil öne çıkan görsel widget'larında stok fotoğraf araması için kullanılır.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_default_image_provider">Varsayılan Görsel Sağlayıcı</label></th>
					<td>
						<select name="ewt_default_image_provider" id="ewt_default_image_provider">
							<?php $cur_img = get_option( 'ewt_default_image_provider', 'pexels' ); ?>
							<option value="pexels" <?php selected( $cur_img, 'pexels' ); ?>>Pexels (stok fotoğraf, hızlı ve ücretsiz)</option>
							<option value="openai" <?php selected( $cur_img, 'openai' ); ?>>OpenAI (AI ile özgün görsel üretimi)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_tutoring_phone">Özel Ders Telefon Numarası</label></th>
					<td>
						<input type="text" class="regular-text" name="ewt_tutoring_phone" id="ewt_tutoring_phone" value="<?php echo esc_attr( get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ) ); ?>" />
						<p class="description">Chatbot, özel ders talep eden ziyaretçileri bu numaraya (arama ve WhatsApp) yönlendirir.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_chatbot_greeting">Chatbot Karşılama Mesajı</label></th>
					<td>
						<textarea class="large-text" rows="3" name="ewt_chatbot_greeting" id="ewt_chatbot_greeting"><?php echo esc_textarea( get_option( 'ewt_chatbot_greeting', 'Merhaba! 👋 English Web TR\'ye hoş geldin. Sana nasıl yardımcı olabilirim?' ) ); ?></textarea>
					</td>
				</tr>
			</table>

			<h2 class="title">Ön Yüz Materyal Üretici ([ewt_generator] kısa kodu)</h2>
			<p class="description">Ziyaretçilerin konu + seviye seçerek anında çalışma kağıdı/sınav/ders planı/quiz üretebildiği herkese açık araç. Her üretim AI sağlayıcı maliyetinizi kullandığı için hız sınırı önerilir.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Durum</th>
					<td>
						<input type="hidden" name="ewt_generator_enabled" value="0" />
						<label><input type="checkbox" name="ewt_generator_enabled" value="1" <?php checked( get_option( 'ewt_generator_enabled', '1' ), '1' ); ?> /> Etkin (kapatırsanız kısa kodun bulunduğu her yerde nazik bir "kullanılamıyor" mesajı gösterilir)</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_generator_rate_limit">Saatlik Üretim Limiti (IP başına)</label></th>
					<td>
						<input type="number" min="1" max="100" class="small-text" name="ewt_generator_rate_limit" id="ewt_generator_rate_limit" value="<?php echo esc_attr( get_option( 'ewt_generator_rate_limit', 6 ) ); ?>" />
						<p class="description">Bir ziyaretçi (IP) bu sayıyı aşarsa, tekrar denemeden önce özel ders telefon numaranıza yönlendiren bir mesaj görür.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">Dinleme &amp; Konuşma Laboratuvarı ([ewt_listening_lab] kısa kodu)</h2>
			<p class="description">Ziyaretçilerin seviye + konu seçerek AI ile dinleme parçası ürettiği, tarayıcının ücretsiz sesli okuma özelliğiyle dinlediği, ardından anlama testi çözüp konuşma/shadowing çalışmasına geçtiği araç. Listening ve Speaking derslerinin (CPT) tekil sayfalarına otomatik eklenir. Saatlik IP limiti, yukarıdaki Materyal Üretici ile ORTAKTIR.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Durum</th>
					<td>
						<input type="hidden" name="ewt_lab_enabled" value="0" />
						<label><input type="checkbox" name="ewt_lab_enabled" value="1" <?php checked( get_option( 'ewt_lab_enabled', '1' ), '1' ); ?> /> Etkin (kapatırsanız kısa kodun bulunduğu her yerde nazik bir "kullanılamıyor" mesajı gösterilir)</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Otomatik Yerleştirme</th>
					<td>
						<input type="hidden" name="ewt_lab_auto_insert" value="0" />
						<label><input type="checkbox" name="ewt_lab_auto_insert" value="1" <?php checked( get_option( 'ewt_lab_auto_insert', '1' ), '1' ); ?> /> Listening ve Speaking derslerinin (tekil sayfalarının) altına laboratuvarı otomatik ekle</label>
						<p class="description">Dersin seviyesi (taxonomy'den) otomatik algılanıp önceden seçili gelir; ders başlığı konu alanına önerilir. Bir derste elle [ewt_listening_lab] kullanılmışsa ikinci kez eklenmez.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_lab_quiz_questions">Test Soru Sayısı</label></th>
					<td>
						<input type="number" min="3" max="10" class="small-text" name="ewt_lab_quiz_questions" id="ewt_lab_quiz_questions" value="<?php echo esc_attr( get_option( 'ewt_lab_quiz_questions', 5 ) ); ?>" />
						<p class="description">Dinleme parçasından sonra sorulacak anlama testi soru sayısı (3-10 arası, varsayılan 5).</p>
					</td>
				</tr>
			</table>

			<h2 class="title">Ziyaretçi E-Kitap Oluşturucu ([ewt_ebook_builder] kısa kodu)</h2>
			<p class="description">Ziyaretçilerin ders türü + seviye filtreleyip istedikleri konuları seçerek kendilerine özel PDF/EPUB çalışma kitabı oluşturduğu araç. <strong>AI maliyeti yoktur</strong> — sadece sitenizde zaten yayında olan içerikleri derler. Kayıtlar için: <a href="<?php echo esc_url( admin_url( 'themes.php?page=ewt-ebook-leads' ) ); ?>">Görünüm &gt; E-Kitap Kayıtları</a></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Durum</th>
					<td>
						<input type="hidden" name="ewt_ebook_enabled" value="0" />
						<label><input type="checkbox" name="ewt_ebook_enabled" value="1" <?php checked( get_option( 'ewt_ebook_enabled', '1' ), '1' ); ?> /> Etkin (kapatırsanız kısa kodun bulunduğu her yerde nazik bir "kullanılamıyor" mesajı gösterilir)</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_ebook_max_posts">Kitap Başına En Fazla Konu</label></th>
					<td>
						<input type="number" min="3" max="100" class="small-text" name="ewt_ebook_max_posts" id="ewt_ebook_max_posts" value="<?php echo esc_attr( get_option( 'ewt_ebook_max_posts', 30 ) ); ?>" />
						<p class="description">Bir ziyaretçinin tek kitaba ekleyebileceği konu sayısı (3-100, varsayılan 30). Yüksek değerler PHP bellek kullanımını artırır; paylaşımlı hosting'de 30-40 bandını aşmayın.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_ebook_rate_limit">Saatlik E-Kitap Limiti (IP başına)</label></th>
					<td>
						<input type="number" min="1" max="50" class="small-text" name="ewt_ebook_rate_limit" id="ewt_ebook_rate_limit" value="<?php echo esc_attr( get_option( 'ewt_ebook_rate_limit', 5 ) ); ?>" />
						<p class="description">Bir ziyaretçi (IP) saatte bu sayıdan fazla kitap oluşturamaz. Bot ve kötüye kullanıma karşı ana korumadır.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Ana Sayfa Bölümü</th>
					<td>
						<input type="hidden" name="ewt_ebook_home_enabled" value="0" />
						<label><input type="checkbox" name="ewt_ebook_home_enabled" value="1" <?php checked( get_option( 'ewt_ebook_home_enabled', '1' ), '1' ); ?> /> Ana sayfada, Dinleme Laboratuvarı'nın altında oluşturucuyu göster</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_ebook_page_slug">E-Kitap Sayfası</label></th>
					<td>
						<?php $slug = get_option( 'ewt_ebook_page_slug', 'ingilizce-e-kitaplar' ); ?>
						<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
						<input type="text" class="regular-text" style="width:260px" name="ewt_ebook_page_slug" id="ewt_ebook_page_slug" value="<?php echo esc_attr( $slug ); ?>" />
						<br />
						<input type="hidden" name="ewt_ebook_page_auto" value="0" />
						<label><input type="checkbox" name="ewt_ebook_page_auto" value="1" <?php checked( get_option( 'ewt_ebook_page_auto', '1' ), '1' ); ?> /> Bu sayfanın içeriğinin sonuna oluşturucuyu otomatik ekle</label>
						<p class="description">
							<?php if ( get_page_by_path( $slug ) ) : ?>
								<span style="color:#00792b;">✔ Sayfa bulundu.</span> Sayfada elle <code>[ewt_ebook_builder]</code> kullanırsanız ikinci kez eklenmez.
							<?php else : ?>
								<span style="color:#b32d2e;">⚠ Bu slug'a sahip bir sayfa yok.</span> Sayfalar &gt; Yeni Ekle ile bu kalıcı bağlantıya sahip bir sayfa oluşturun; site genelindeki CTA butonları buraya yönlenir.
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Site Geneli CTA</th>
					<td>
						<input type="hidden" name="ewt_ebook_cta_enabled" value="0" />
						<label><input type="checkbox" name="ewt_ebook_cta_enabled" value="1" <?php checked( get_option( 'ewt_ebook_cta_enabled', '1' ), '1' ); ?> /> Tüm ders ve blog yazılarının altına "Bu konuyu kendi kitabına ekle" kutusu ekle</label>
						<p class="description"><strong>Siteyi yavaşlatmaz:</strong> bu kutu ek JavaScript, ek CSS dosyası veya ek veritabanı sorgusu getirmez — yalnızca sayfa başına bir kez basılan ~1 KB satır içi stil kullanır. Oluşturucunun asıl dosyaları sadece kısa kodun bulunduğu sayfalarda yüklenir. Butona tıklayan ziyaretçi, e-kitap sayfasına <em>o ders zaten seçili halde</em> gider.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">E-posta Toplama</th>
					<td>
						<input type="hidden" name="ewt_ebook_email_capture" value="0" />
						<label><input type="checkbox" name="ewt_ebook_email_capture" value="1" <?php checked( get_option( 'ewt_ebook_email_capture', '1' ), '1' ); ?> /> İndirme formunda <strong>isteğe bağlı</strong> e-posta alanı göster</label>
						<p class="description">Zorunlu değildir; ziyaretçi boş bırakıp kitabını yine de alabilir. Toplanan adresler "E-Kitap Kayıtları" sayfasında listelenir ve CSV olarak dışa aktarılır. KVKK açısından: formda ne amaçla kullanılacağını belirtin ve Gizlilik Politikanıza bir satır ekleyin.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Ayarları Kaydet' ); ?>
		</form>
		<hr />
		<p><strong>Not:</strong> API anahtarları veritabanında saklanır ve hiçbir zaman sayfa kaynak koduna (frontend HTML/JS) yazdırılmaz; tüm AI çağrıları sunucu tarafında (admin-ajax.php) yapılır.</p>
	</div>
	<?php
}

/**
 * "Bağlantıyı Test Et" butonları için AJAX handler.
 * Kaydedilmemiş (henüz "Ayarları Kaydet"e basılmamış) anahtarları da test edebilmek için
 * anahtar, kaydedilmiş option yerine doğrudan alandan (POST) alınır.
 */
function ewt_ajax_test_provider() {
	check_ajax_referer( 'ewt_test_provider', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
	$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';

	if ( ! $api_key ) wp_send_json_error( 'Lütfen önce ilgili alana bir API anahtarı girin.' );

	switch ( $provider ) {
		case 'youcom':
			$mode = get_option( 'ewt_youcom_mode', 'research' );
			if ( $mode === 'express' ) {
				$response = wp_remote_post( 'https://api.you.com/v1/ai/search', array(
					'timeout' => 30,
					'headers' => array( 'Content-Type' => 'application/json', 'X-API-Key' => $api_key ),
					'body'    => wp_json_encode( array( 'query' => 'Yalnızca "Tamam" yaz.' ) ),
				) );
			} else {
				$response = wp_remote_post( 'https://api.you.com/v1/research', array(
					'timeout' => 30,
					'headers' => array( 'Content-Type' => 'application/json', 'X-API-Key' => $api_key ),
					'body'    => wp_json_encode( array( 'input' => 'Yalnızca "Tamam" yaz.', 'research_effort' => 'lite' ) ),
				) );
			}
			if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) wp_send_json_success( 'Bağlantı başarılı (' . $mode . ' modu).' );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$err  = isset( $body['detail'] ) ? $body['detail'] : ( isset( $body['message'] ) ? $body['message'] : 'HTTP ' . $code );
			wp_send_json_error( is_array( $err ) ? wp_json_encode( $err ) : $err );
			break;

		case 'claude':
			$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
				'timeout' => 30,
				'headers' => array( 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 10, 'messages' => array( array( 'role' => 'user', 'content' => 'Yalnızca "Tamam" yaz.' ) ) ) ),
			) );
			if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) wp_send_json_success( 'Bağlantı başarılı.' );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			wp_send_json_error( isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code );
			break;

		case 'openai':
			$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'model' => 'gpt-4o-mini', 'max_tokens' => 10, 'messages' => array( array( 'role' => 'user', 'content' => 'Yalnızca "Tamam" yaz.' ) ) ) ),
			) );
			if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) wp_send_json_success( 'Bağlantı başarılı.' );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			wp_send_json_error( isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code );
			break;

		case 'pexels':
			$response = wp_remote_get( add_query_arg( array( 'query' => 'apple', 'per_page' => 1 ), 'https://api.pexels.com/v1/search' ), array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => $api_key ),
			) );
			if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) wp_send_json_success( 'Bağlantı başarılı.' );
			wp_send_json_error( 'HTTP ' . $code );
			break;

		default:
			wp_send_json_error( 'Bilinmeyen sağlayıcı.' );
	}
}
add_action( 'wp_ajax_ewt_test_provider', 'ewt_ajax_test_provider' );

function ewt_enqueue_settings_admin_js( $hook ) {
	if ( strpos( $hook, 'ewt-settings' ) === false ) return;
	wp_enqueue_script( 'ewt-settings-admin', EWT_URI . '/assets/js/admin-settings.js', array( 'jquery' ), EWT_VERSION, true );
	wp_localize_script( 'ewt-settings-admin', 'ewtSettingsTest', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ewt_test_provider' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_enqueue_settings_admin_js' );
