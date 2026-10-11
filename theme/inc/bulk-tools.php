<?php
/**
 * TOPLU ÜRETİM ARAÇLARI (v2)
 *
 * 1) Focus Keyword listesi (elle yazarak VEYA CSV yükleyerek) verip toplu içerik üretimi.
 *    - Akış artık "ÖNCE CPT SEÇ" mantığında: hedef içerik türü en üstte seçilir, o CPT'nin
 *      dashboard'dan önceden oluşturulmuş gerçek "Seviye" terimleri hemen altında gösterilir.
 *    - CSV'de "seviye" sütunu boş bırakılırsa, AI o CPT'nin mevcut seviye terimleri arasından
 *      anahtar kelimeye en uygun olanı seçer ve içeriği o seviyeye atar (bkz. ewt_extract_seviye_marker()).
 *    - Doldurulmuş "seviye" hücreleri (mevcut bir terimle eşleşirse) hâlâ "nokta atışı" olarak kullanılır.
 *    - İsteğe bağlı: featured image'a EK olarak, içerik gövdesine 0-4 adet alakalı Pexels görseli gömülür.
 *    - İşlemler artık tarayıcıdan AYNI ANDA birkaç (ayarlanabilir) istek göndererek çalışır; böylece
 *      1000 satırlık listeler bile eskiye kıyasla çok daha hızlı tamamlanır.
 * 2) Seçili CPT içinde öne çıkan görseli olmayan yazıları bulup toplu görsel atama (Pexels/OpenAI),
 *    aynı eşzamanlı işlem motoruyla hızlandırılmış hâlde, çocuklara yönelik "izole nesne" modu dahil.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/csv-categories.php';

function ewt_bulk_tools_menu() {
	add_menu_page(
		'English Web AI Araçları',
		'AI Araçları',
		'manage_options',
		'ewt-bulk-content',
		'ewt_bulk_content_page_html',
		'dashicons-superhero',
		59
	);
	add_submenu_page( 'ewt-bulk-content', 'Toplu İçerik Üretimi', 'Toplu İçerik Üretimi', 'manage_options', 'ewt-bulk-content', 'ewt_bulk_content_page_html' );
	add_submenu_page( 'ewt-bulk-content', 'Toplu Görsel Atama', 'Toplu Görsel Atama', 'manage_options', 'ewt-bulk-image', 'ewt_bulk_image_page_html' );
}
add_action( 'admin_menu', 'ewt_bulk_tools_menu' );

function ewt_bulk_cpt_select_html( $id ) {
	echo '<select id="' . esc_attr( $id ) . '" class="regular-text ewt-bulk-cpt-select">';
	foreach ( ewt_cpt_config() as $slug => $data ) {
		echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $data[1] ) . '</option>';
	}
	echo '<option value="post">Normal Blog Yazısı</option>';
	echo '</select>';
}

/* -------------------------------------------------
 * SAYFA 1: TOPLU İÇERİK ÜRETİMİ (FK bazlı + CSV)
 * ------------------------------------------------- */
function ewt_bulk_content_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	?>
	<div class="wrap">
		<h1>🚀 Toplu İçerik Üretimi (Focus Keyword Bazlı)</h1>
		<p>Binlerce anahtar kelimeyi bile tek seferde işleyebilirsiniz. Adımlar: <strong>1) İçerik türünü (CPT) seçin</strong>, <strong>2) anahtar kelimeleri girin</strong>, <strong>3) başlatın</strong>. Seviye/zorluk bilgisini boş bırakırsanız AI, o içerik türü için dashboard'dan önceden oluşturduğunuz gerçek seviye kategorileri arasından en uygununu otomatik seçer.</p>

		<h2 class="title">1) Hedef İçerik Türü (CPT)</h2>
		<table class="form-table">
			<tr>
				<th>İçerik Türü</th>
				<td><?php ewt_bulk_cpt_select_html( 'ewt_bulk_post_type' ); ?></td>
			</tr>
			<tr>
				<th>Bu Türde Mevcut Seviyeler</th>
				<td>
					<div id="ewt_bulk_seviye_chips" class="ewt-seviye-chips"></div>
					<p class="description" id="ewt_bulk_seviye_hint"></p>
				</td>
			</tr>
		</table>

		<h2 class="title">2) Genel Ayarlar (CSV'de boş bırakılan alanlar için de varsayılan olarak kullanılır)</h2>
		<table class="form-table">
			<tr>
				<th>Seviye Boşsa Yedek Zorluk Derecesi</th>
				<td>
					<select id="ewt_bulk_difficulty" class="regular-text"></select>
					<p class="description">Bu içerik türü için henüz hiç seviye kategorisi oluşturmadıysanız (yukarıda liste boşsa) AI otomatik tespit yapamaz; bu durumda bu sabit zorluk derecesi kullanılır. Seviye kategorileriniz varsa bu alan sadece CSV/liste satırında seviye elle belirtilmişse ama eşleşme bulunamazsa yedek olarak devreye girer.</p>
				</td>
			</tr>
			<tr>
				<th>Varsayılan Hedef Kelime Sayısı</th>
				<td><input type="number" id="ewt_bulk_word_count" value="400" min="50" max="5000" step="50" class="small-text" /></td>
			</tr>
			<tr>
				<th>AI Sağlayıcı</th>
				<td>
					<select id="ewt_bulk_provider" class="regular-text">
						<option value="youcom">You.com</option>
						<option value="claude">Claude</option>
						<option value="openai">OpenAI</option>
					</select>
				</td>
			</tr>
			<tr>
				<th>Yayın Durumu</th>
				<td>
					<select id="ewt_bulk_status" class="regular-text">
						<option value="draft">Taslak</option>
						<option value="publish" selected>Doğrudan Yayınla</option>
					</select>
				</td>
			</tr>
			<tr>
				<th>İçerik Talimat Şablonu</th>
				<td>
					<textarea id="ewt_bulk_prompt_template" class="large-text" rows="3">{fk} anahtar kelimesi için Türk öğrencilere yönelik SEO uyumlu bir ders/blog içeriği yaz.</textarea>
					<p class="description"><code>{fk}</code> yazdığınız yere ilgili satırdaki focus keyword otomatik yerleştirilir.</p>
				</td>
			</tr>
			<tr>
				<th>Gövde İçi Ek Görsel (Pexels)</th>
				<td>
					<label>
						<input type="checkbox" id="ewt_bulk_inline_images_enabled" value="1" checked="checked" />
						Featured image'a ek olarak içerik gövdesine de alakalı Pexels görselleri ekle
					</label>
					<br />
					<label style="margin-top:6px;display:inline-block;">
						Adet:
						<select id="ewt_bulk_inline_images_count">
							<option value="1">1</option>
							<option value="2" selected="selected">2</option>
							<option value="3">3</option>
							<option value="4">4</option>
						</select>
					</label>
					<p class="description">Görseller, üretilen içerikteki &lt;h2&gt; başlıklarının hemen altına, o bölümün konusuna göre otomatik yerleştirilir. Pexels API anahtarınız Ayarlar'da tanımlı olmalı.</p>
				</td>
			</tr>
			<tr>
				<th>Eşzamanlı İstek Sayısı</th>
				<td>
					<input type="number" id="ewt_bulk_concurrency" value="4" min="1" max="8" class="small-text" />
					<p class="description">Aynı anda kaç satırın işleneceği. Yüksek değer hızlandırır ama AI sağlayıcınızın hız limitine (rate limit) daha çabuk çarpabilirsiniz; 3-5 arası önerilir.</p>
				</td>
			</tr>
		</table>

		<hr />

		<h2 class="title">Yöntem A — Basit Liste (tüm satırlar yukarıdaki genel ayarları kullanır)</h2>
		<textarea id="ewt_bulk_fk_list" class="large-text code" rows="8" placeholder="a1 seviyesi ingilizce kelimeler&#10;b2 ingilizce kelimeler&#10;present perfect tense konu anlatımı&#10;(1000 satıra kadar girebilirsiniz)"></textarea>

		<h2 class="title">Yöntem B — CSV Yükle (satır bazlı "nokta atışı" seviye / kelime sayısı)</h2>
		<p class="description">
			Sütun sırası: <code>keyword,cpt,seviye,kelime_sayisi</code> (başlık satırı isteğe bağlıdır). <code>cpt</code> sütununa CPT slug'ı veya görünen adı yazabilirsiniz — boşsa yukarıda seçtiğiniz CPT kullanılır.
			<strong>seviye</strong> hücresine kategori adı veya kodu yazabilirsiniz. Eksik kategorileri oluşturma ve boş IELTS satırlarını konuya göre eşleştirme için aşağıdaki seçeneği kullanın.
		</p>
		<p>
			<input type="file" id="ewt_bulk_csv_file" accept=".csv,text/csv" />
			<label style="display:block;margin:12px 0"><input type="checkbox" id="ewt_bulk_csv_categories" checked /> CSV'den eksik seviye/kategorileri oluştur</label>
			<span class="description" style="display:block">Başlatınca: seviye doluysa ilgili içerik türünde mevcut kategori kullanılır veya yenisi oluşturulur. Her satıra tek kategori adı ya da kodu yazın. Boş IELTS satırları başlığa göre konu kategorisine atanır; belirsiz başlıklar IELTS Genel Bilgiler'e gider. Diğer derslerde boş seviye için mevcut otomatik seçim kullanılır. Bu seçenek kapalıysa eski kategori davranışı geçerlidir. CSV satırlarından başarıyla üretilen içerikler doğrudan yayınlanır; yukarıdaki yayın durumu seçimi elle girilen liste için geçerlidir. Kelime hedefi 50–5000 aralığında kullanılabilir.</span>
			<button type="button" class="button" id="ewt_bulk_csv_template_btn">📄 Örnek CSV Şablonu İndir</button>
			<button type="button" class="button" id="ewt_bulk_csv_clear_btn">CSV'yi Temizle / Yöntem A'ya Dön</button>
		</p>
		<div id="ewt_bulk_csv_preview_wrap" style="display:none;">
			<p><strong id="ewt_bulk_csv_summary"></strong></p>
			<table class="widefat striped" id="ewt_bulk_csv_preview_table">
				<thead><tr><th>Keyword</th><th>CPT</th><th>Seviye</th><th>Kelime Sayısı</th></tr></thead>
				<tbody id="ewt_bulk_csv_preview_body"></tbody>
			</table>
		</div>

		<p style="margin-top:20px;">
			<button type="button" class="button button-primary button-hero" id="ewt_bulk_start_btn">Toplu Üretimi Başlat</button>
			<button type="button" class="button" id="ewt_bulk_stop_btn" style="display:none;">Durdur</button>
			<button type="button" class="button" id="ewt_bulk_export_btn" style="display:none;">📥 Sonuçları CSV İndir</button>
			<span id="ewt_bulk_progress_text"></span>
		</p>

		<table class="widefat striped" id="ewt_bulk_results_table" style="display:none;">
			<thead><tr><th>Focus Keyword</th><th>CPT</th><th>Seviye</th><th>Durum</th><th>Sonuç</th></tr></thead>
			<tbody id="ewt_bulk_results_body"></tbody>
		</table>
	</div>
	<?php
}

/* -------------------------------------------------
 * SAYFA 2: TOPLU GÖRSEL ATAMA
 * ------------------------------------------------- */
function ewt_bulk_image_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	?>
	<div class="wrap">
		<h1>🖼️ Toplu Öne Çıkan Görsel Atama</h1>
		<p>Seçtiğiniz içerik türünde öne çıkan görseli <strong>olmayan</strong> yazıları tarar, her biri için başlığa göre Pexels'ten (veya OpenAI ile üreterek) otomatik görsel atar.</p>

		<table class="form-table">
			<tr>
				<th>Hedef İçerik Türü</th>
				<td><?php ewt_bulk_cpt_select_html( 'ewt_bulk_img_post_type' ); ?></td>
			</tr>
			<tr>
				<th>Sağlayıcı</th>
				<td>
					<select id="ewt_bulk_img_provider" class="regular-text">
						<option value="pexels">Pexels (stok fotoğraf - hızlı/ücretsiz)</option>
						<option value="openai">OpenAI (AI ile özgün üretim)</option>
					</select>
				</td>
			</tr>
			<tr>
				<th>Görsel Modu</th>
				<td>
					<label>
						<input type="checkbox" id="ewt_bulk_img_child_object" value="1" />
						👶 Çocuklar için nesne görseli (izole obje, beyaz arkaplan, flashcard stili)
					</label>
					<p class="description">Okul öncesi / ilkokul kelime içerikleri için işaretleyin: her başlık, temiz/izole obje-hayvan-renk fotoğrafı aramasına/üretimine dönüştürülür.</p>
				</td>
			</tr>
			<tr>
				<th>Maksimum Yazı Sayısı</th>
				<td><input type="number" id="ewt_bulk_img_limit" value="30" min="1" max="1000" class="small-text" /></td>
			</tr>
			<tr>
				<th>Eşzamanlı İstek Sayısı</th>
				<td>
					<input type="number" id="ewt_bulk_img_concurrency" value="4" min="1" max="8" class="small-text" />
					<p class="description">Pexels ücretsiz plan saatlik istek limitine sahiptir; çok yüksek değerlerde hata görürseniz düşürün.</p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" class="button button-secondary" id="ewt_bulk_img_scan_btn">1) Görseli Olmayan Yazıları Bul</button>
			<button type="button" class="button button-primary button-hero" id="ewt_bulk_img_start_btn" disabled>2) Toplu Görsel Ata</button>
			<button type="button" class="button" id="ewt_bulk_img_export_btn" style="display:none;">📥 Sonuçları CSV İndir</button>
			<span id="ewt_bulk_img_progress_text"></span>
		</p>

		<table class="widefat striped" id="ewt_bulk_img_results_table" style="display:none;">
			<thead><tr><th>Yazı</th><th>Durum</th><th>Sonuç</th></tr></thead>
			<tbody id="ewt_bulk_img_results_body"></tbody>
		</table>
	</div>
	<?php
}

/**
 * Verilen değeri (elle girilmiş seviye hücresi) bir CPT'nin gerçek Seviye taxonomy terimleriyle eşleştirir.
 * Slug ya da görünen ada göre (büyük/küçük harf duyarsız) eşleşme arar.
 *
 * @return array|null [ 'slug' => ..., 'name' => ... ] ya da eşleşme yoksa null
 */
function ewt_match_seviye_term( $post_type, $value ) {
	$value = trim( (string) $value );
	if ( ! $value ) return null;

	$terms = ewt_get_seviye_terms( $post_type );
	if ( empty( $terms ) ) return null;

	$slug_candidate = sanitize_title( $value );
	foreach ( $terms as $t ) {
		if ( $t['slug'] === $slug_candidate ) return $t;
	}
	foreach ( $terms as $t ) {
		if ( mb_strtolower( $t['name'] ) === mb_strtolower( $value ) ) return $t;
	}
	return null;
}

/* -------------------------------------------------
 * AJAX: bir FK için içerik üret + yazı oluştur (JS her satır için eşzamanlı havuzdan çağırır)
 * ------------------------------------------------- */
function ewt_ajax_bulk_generate_content() {
	check_ajax_referer( 'ewt_bulk_tools', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$fk            = isset( $_POST['fk'] ) ? sanitize_text_field( $_POST['fk'] ) : '';
	$post_type     = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
	$provider      = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : 'youcom';
	$status        = isset( $_POST['status'] ) && $_POST['status'] === 'publish' ? 'publish' : 'draft';
	$template      = isset( $_POST['template'] ) ? sanitize_textarea_field( $_POST['template'] ) : '{fk} için bir ders içeriği yaz.';
	$seviye_input  = isset( $_POST['difficulty'] ) ? sanitize_text_field( $_POST['difficulty'] ) : ''; // satır bazlı "seviye" değeri — BOŞ ise (ve bu CPT'de terim varsa) AI otomatik tespit tetiklenir
	$fallback_difficulty = isset( $_POST['fallback_difficulty'] ) ? sanitize_text_field( $_POST['fallback_difficulty'] ) : ''; // bu CPT'de HİÇ seviye terimi yoksa devreye giren sabit yedek
	$word_count    = isset( $_POST['word_count'] ) ? intval( $_POST['word_count'] ) : 400;
	$inline_images = isset( $_POST['inline_images'] ) ? max( 0, min( 4, intval( $_POST['inline_images'] ) ) ) : 0;

	if ( ! $fk ) wp_send_json_error( 'Focus keyword boş olamaz.' );
	if ( ! post_type_exists( $post_type ) ) wp_send_json_error( 'Geçersiz içerik türü: CSV cpt sütununu kontrol edin.' );
	$csv_categories = isset( $_POST['csv_categories'] ) && $_POST['csv_categories'] === '1';
	$csv_import = isset( $_POST['csv_import'] ) && $_POST['csv_import'] === '1';
	if ( $csv_import ) $status = 'publish';
	$type_object = get_post_type_object( $post_type );
	if ( ! current_user_can( $type_object->cap->edit_posts ) || ( $status === 'publish' && ! current_user_can( $type_object->cap->publish_posts ) ) ) wp_send_json_error( 'Bu içerik türü için yazma veya yayınlama yetkiniz yok.' );

	$topic          = str_replace( '{fk}', $fk, $template );
	$system_prompt  = ewt_content_system_prompt( $post_type );
	$available_terms = ewt_get_seviye_terms( $post_type );
	$child_object_auto = in_array( $post_type, array( 'ewt_preschool', 'ewt_primary' ), true );

	$csv_term = null;
	if ( $csv_categories && ( $seviye_input !== '' || $post_type === 'ewt_ielts' ) ) {
		$category = $seviye_input !== '' ? $seviye_input : ewt_csv_ielts_category_for_keyword( $fk );
		$csv_term = ewt_csv_ensure_category( $post_type, $category );
		if ( is_wp_error( $csv_term ) ) wp_send_json_error( $csv_term->get_error_message() );
		$seviye_input = $csv_term['name'];
	}
	$matched_term = $csv_term ? $csv_term : ( $seviye_input ? ewt_match_seviye_term( $post_type, $seviye_input ) : null );
	$auto_seviye  = ! $seviye_input && ! empty( $available_terms );

	if ( $auto_seviye ) {
		// Seviye boş VE bu CPT için gerçek seviye terimleri var: AI'dan hem seviye seçmesini hem içerik üretmesini iste.
		$prompt  = ewt_content_user_prompt_auto_seviye( $topic, $post_type, $available_terms, $word_count );
		$result  = ewt_call_ai_provider( $provider, $system_prompt, $prompt, true ); // $raw = true

		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

		$valid_slugs = wp_list_pluck( $available_terms, 'slug' );
		$extracted   = ewt_extract_seviye_marker( $result, $valid_slugs );
		$content     = wpautop( $extracted['content'] );

		$resolved_term = null;
		if ( $extracted['seviye_slug'] ) {
			foreach ( $available_terms as $t ) {
				if ( $t['slug'] === $extracted['seviye_slug'] ) { $resolved_term = $t; break; }
			}
		}
	} else {
		// Klasik yol: seviye elle verilmiş, YA DA bu CPT için henüz hiç seviye terimi yok (otomatik tespit imkansız).
		// İkinci durumda satır boşsa genel/yedek zorluk derecesine düşülür.
		$difficulty_for_prompt = $seviye_input !== '' ? $seviye_input : $fallback_difficulty;
		$prompt  = ewt_content_user_prompt( $topic, $post_type, $difficulty_for_prompt, $word_count );
		$result  = ewt_call_ai_provider( $provider, $system_prompt, $prompt, false );

		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

		$content       = $result;
		$resolved_term = $matched_term; // elle girilen değer gerçek bir terimle eşleştiyse ata, eşleşmediyse null (yine de içerikte zorluk metni olarak kullanıldı)
	}

	$post_id = wp_insert_post( array(
		'post_type'    => $post_type,
		'post_status'  => $status,
		'tax_input'    => $csv_term ? array( $post_type . '_seviye' => array( $csv_term['id'] ) ) : array(),
		'post_title'   => ucfirst( $fk ),
		'post_content' => $content,
	), true );

	if ( is_wp_error( $post_id ) ) wp_send_json_error( $post_id->get_error_message() );

	if ( $resolved_term && taxonomy_exists( $post_type . '_seviye' ) ) {
		$assigned = wp_set_object_terms( $post_id, $csv_term ? array( $csv_term['id'] ) : $resolved_term['slug'], $post_type . '_seviye' );
		if ( $csv_term && ( is_wp_error( $assigned ) || empty( $assigned ) ) ) {
			if ( wp_delete_post( $post_id, true ) ) wp_send_json_error( 'Kategori atanamadı. Bu işlemde oluşturulan yeni yazı kaldırıldı; satırı yeniden deneyin.' );
			wp_send_json_error( 'Kategori atanamadı ve yeni yazı kaldırılamadı. Kontrol edilmesi gereken yazı ID: ' . $post_id );
		}
	}

	// Gövde içi ek Pexels görselleri (featured image'a ek olarak) — hatalar sessizce yutulur, içerik değişmeden kalır.
	if ( $inline_images > 0 ) {
		$with_images = ewt_insert_inline_pexels_images( $content, $post_id, $fk, $inline_images, $child_object_auto );
		if ( $with_images !== $content ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $with_images ) );
		}
	}

	// SEO alanlarını FK'ya göre ayarla (başlık, açıklama, slug, Yoast/Rank Math senkronu)
	$plain_excerpt = wp_trim_words( wp_strip_all_tags( $content ), 20 );
	ewt_apply_seo_fields( $post_id, $fk, ucfirst( $fk ), $plain_excerpt, true );

	wp_send_json_success( array(
		'post_id'     => $post_id,
		'edit_link'   => get_edit_post_link( $post_id, 'raw' ),
		'view_link'   => get_permalink( $post_id ),
		'status'      => $status,
		'post_type'   => $post_type,
		'seviye_name' => $resolved_term ? $resolved_term['name'] : '—',
	) );
}
add_action( 'wp_ajax_ewt_bulk_generate_content', 'ewt_ajax_bulk_generate_content' );

/* -------------------------------------------------
 * AJAX: seçili CPT'de öne çıkan görseli olmayan yazıları listele
 * ------------------------------------------------- */
function ewt_ajax_bulk_list_no_thumbnail() {
	check_ajax_referer( 'ewt_bulk_tools', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
	$limit     = isset( $_POST['limit'] ) ? max( 1, min( 1000, intval( $_POST['limit'] ) ) ) : 30;

	$query = new WP_Query( array(
		'post_type'      => $post_type,
		'posts_per_page' => $limit,
		'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
		'meta_query'      => array(
			array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ),
		),
		'fields' => 'ids',
	) );

	$items = array();
	foreach ( $query->posts as $pid ) {
		$items[] = array( 'id' => $pid, 'title' => get_the_title( $pid ) );
	}

	wp_send_json_success( array( 'items' => $items ) );
}
add_action( 'wp_ajax_ewt_bulk_list_no_thumbnail', 'ewt_ajax_bulk_list_no_thumbnail' );

/* -------------------------------------------------
 * Admin JS'i yalnızca ilgili iki sayfada yükle + CPT/zorluk/seviye haritalarını aktar
 * ------------------------------------------------- */
function ewt_enqueue_bulk_tools_js( $hook ) {
	if ( strpos( $hook, 'ewt-bulk-content' ) === false && strpos( $hook, 'ewt-bulk-image' ) === false ) return;

	wp_enqueue_script( 'ewt-bulk-tools', EWT_URI . '/assets/js/admin-bulk-tools.js', array( 'jquery' ), EWT_VERSION, true );

	$cpt_map = array();
	foreach ( ewt_cpt_config() as $slug => $data ) {
		$cpt_map[ $slug ] = $data[1];
	}
	$cpt_map['post'] = 'Normal Blog Yazısı';

	wp_localize_script( 'ewt-bulk-tools', 'ewtBulk', array(
		'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		'nonce'          => wp_create_nonce( 'ewt_bulk_tools' ),
		'imageNonce'     => wp_create_nonce( 'ewt_ai_image_generate' ),
		'cptMap'         => $cpt_map,
		'difficultyMap'  => ewt_all_difficulty_maps(),
		'seviyeTermsMap' => ewt_all_seviye_terms_map(),
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_enqueue_bulk_tools_js' );
