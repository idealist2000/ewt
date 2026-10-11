<?php
/**
 * ZİYARETÇİ E-KİTAP OLUŞTURUCU (Faz 3)
 *
 * [ewt_ebook_builder] kısa kodu, ziyaretçilerin ders türü + seviye filtreleyip
 * istedikleri yazıları tek tek seçerek kendilerine özel bir e-kitap (PDF / EPUB)
 * oluşturmasını sağlar. KULLANICI GİRİŞİ GEREKMEZ.
 *
 * Güvenlik/maliyet notları (herkese açık uç nokta — frontend-generator.php ile aynı desen):
 * - Saatlik IP başına e-kitap limiti (varsayılan 5, Ayarlar'dan değiştirilebilir)
 * - Honeypot alanı + minimum doldurma süresi kontrolü
 * - Nonce doğrulaması (nopriv dahil)
 * - Maksimum yazı sayısı sınırı (varsayılan 30) — sunucu belleğini korur
 * - Aynı seçim kombinasyonu için 24 saat EPUB dosya önbelleği (yeniden üretilmez)
 * - Sadece 'publish' durumundaki, izin verilen post type'lardaki, parolasız yazılar okunur
 * - İçerik wp_kses_post() ile temizlenir, <script>/<iframe> tamamen atılır
 * - Üretilen EPUB dosyaları günlük cron ile 24 saat sonra silinir (disk şişmez)
 * - Görünüm > English Web Ayarları'ndan tek tuşla kapatılabilir (acil durum anahtarı)
 *
 * PDF stratejisi: sunucuya HİÇBİR kütüphane eklenmez. Ziyaretçiye kapak + içindekiler
 * + sayfa düzeni hazır, @page kurallı bir yazdırma görünümü açılır; tarayıcının
 * "PDF olarak kaydet" özelliğini kullanır. Türkçe karakter sorunu yaşanmaz.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EWT_EBOOK_TOKEN_TTL', 12 * HOUR_IN_SECONDS );
define( 'EWT_EBOOK_FILE_TTL', DAY_IN_SECONDS );

/* -------------------------------------------------
 * TEMEL YARDIMCILAR
 * ------------------------------------------------- */

function ewt_ebook_enabled() {
	return get_option( 'ewt_ebook_enabled', '1' ) === '1';
}

function ewt_ebook_max_posts() {
	$max = intval( get_option( 'ewt_ebook_max_posts', 30 ) );
	return max( 3, min( 100, $max ) );
}

function ewt_ebook_rate_limit() {
	return max( 1, intval( get_option( 'ewt_ebook_rate_limit', 5 ) ) );
}

function ewt_ebook_email_capture_enabled() {
	return get_option( 'ewt_ebook_email_capture', '1' ) === '1';
}

/**
 * E-kitaba dahil edilebilecek içerik türleri: tüm EWT ders CPT'leri + normal blog yazısı.
 */
function ewt_ebook_allowed_post_types() {
	$types = array_keys( ewt_cpt_config() );
	$types[] = 'post';
	return apply_filters( 'ewt_ebook_allowed_post_types', $types );
}

function ewt_ebook_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
}

function ewt_ebook_check_rate_limit() {
	$key   = 'ewt_ebook_rl_' . md5( ewt_ebook_client_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= ewt_ebook_rate_limit() ) return false;
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * Üretilen EPUB dosyalarının tutulduğu klasör: /wp-content/uploads/ewt-ebooks/
 */
function ewt_ebook_upload_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'ewt-ebooks';
	$url = trailingslashit( $up['baseurl'] ) . 'ewt-ebooks';

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		// Dizin listelemesini engelle (dosyaların kendisi indirilebilir kalır).
		@file_put_contents( $dir . '/index.html', '' );
	}

	return array( 'dir' => $dir, 'url' => $url );
}

/* -------------------------------------------------
 * İÇERİK TOPLAMA
 * ------------------------------------------------- */

/**
 * Verilen yazı ID'lerinden e-kitap veri yapısını kurar.
 * Sadece yayınlanmış, parolasız, izin verilen türdeki yazılar alınır.
 *
 * @return array|WP_Error
 */
function ewt_ebook_collect_posts( $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	if ( empty( $ids ) ) {
		return new WP_Error( 'ewt_ebook_empty', 'Lütfen en az bir konu seçin.' );
	}
	if ( count( $ids ) > ewt_ebook_max_posts() ) {
		return new WP_Error( 'ewt_ebook_too_many', sprintf( 'Bir e-kitaba en fazla %d konu ekleyebilirsiniz.', ewt_ebook_max_posts() ) );
	}

	$query = new WP_Query( array(
		'post__in'            => $ids,
		'orderby'             => 'post__in',
		'post_type'           => ewt_ebook_allowed_post_types(),
		'post_status'         => 'publish',
		'posts_per_page'      => count( $ids ),
		'has_password'        => false,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	) );

	if ( ! $query->have_posts() ) {
		return new WP_Error( 'ewt_ebook_not_found', 'Seçtiğiniz konular bulunamadı. Lütfen sayfayı yenileyip tekrar deneyin.' );
	}

	$chapters = array();

	foreach ( $query->posts as $post ) {
		$raw = $post->post_content;

		// Shortcode'ları çalıştırmıyoruz (quiz/generator gibi interaktif araçlar kitapta anlamsız
		// ve sunucuya gereksiz yük bindirir); sadece metne dönüştürüp atıyoruz.
		$raw = strip_shortcodes( $raw );
		$raw = wpautop( $raw );
		$raw = ewt_ebook_sanitize_html( $raw );
		$raw = ewt_ebook_expand_details( $raw );

		if ( trim( wp_strip_all_tags( $raw ) ) === '' ) continue;

		$pt_obj = get_post_type_object( $post->post_type );

		$chapters[] = array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'html'      => $raw,
			'permalink' => get_permalink( $post ),
			'type'      => $post->post_type,
			'type_label'=> $pt_obj ? $pt_obj->labels->singular_name : '',
			'levels'    => ewt_ebook_post_level_names( $post ),
		);
	}

	if ( empty( $chapters ) ) {
		return new WP_Error( 'ewt_ebook_no_content', 'Seçtiğiniz konularda kitaba aktarılabilecek metin bulunamadı.' );
	}

	return $chapters;
}

/**
 * Bir yazının "Seviye" taxonomy terim adlarını döndürür.
 */
function ewt_ebook_post_level_names( $post ) {
	$tax = $post->post_type . '_seviye';
	if ( ! taxonomy_exists( $tax ) ) return array();

	$terms = get_the_terms( $post, $tax );
	if ( is_wp_error( $terms ) || empty( $terms ) ) return array();

	return wp_list_pluck( $terms, 'name' );
}

/**
 * Herkese açık çıktı için HTML temizliği.
 * wp_kses_post'a ek olarak script/iframe/form gibi etkileşimli her şeyi atar.
 */
function ewt_ebook_sanitize_html( $html ) {
	$html = preg_replace( '#<(script|style|iframe|form|object|embed|video|audio|noscript)\b.*?</\1>#is', '', $html );
	$html = preg_replace( '#<(script|style|iframe|form|object|embed|video|audio|input|button)\b[^>]*/?>#i', '', $html );
	return wp_kses_post( $html );
}

/**
 * Katlanabilir blokları (<details>/<summary> — örn. frontend-generator'ın ürettiği
 * "Cevap Anahtarı" bölümleri) düz başlık + içerik yapısına çevirir.
 *
 * Gerekçe: <details> yazdırıldığında KAPALI basılır, yani öğrencinin elindeki
 * PDF'te cevap anahtarı görünmez. EPUB tarafında da her okuyucu <details>
 * desteklemez. Kitap çıktısında bu içeriğin her zaman görünür olması gerekir.
 */
function ewt_ebook_expand_details( $html ) {
	if ( stripos( $html, '<details' ) === false ) return $html;

	$html = preg_replace( '#<details\b[^>]*>#i', '<div class="ewt-ebook-answerkey">', $html );
	$html = preg_replace( '#<summary\b[^>]*>(.*?)</summary>#is', '<h3>$1</h3>', $html );
	$html = preg_replace( '#</details>#i', '</div>', $html );

	return $html;
}

/**
 * Bir seçimden kitap başlığı türetir (kullanıcı başlık girmediyse).
 */
function ewt_ebook_default_title( $chapters ) {
	$types = array_unique( array_filter( wp_list_pluck( $chapters, 'type_label' ) ) );
	$levels = array();
	foreach ( $chapters as $ch ) {
		$levels = array_merge( $levels, $ch['levels'] );
	}
	$levels = array_unique( array_filter( $levels ) );

	$parts = array();
	if ( count( $levels ) === 1 ) $parts[] = reset( $levels );
	if ( count( $types ) === 1 )  $parts[] = reset( $types );

	$base = $parts ? implode( ' ', $parts ) : 'İngilizce';
	return $base . ' Çalışma Kitabı';
}

/* -------------------------------------------------
 * YERLEŞTİRME YARDIMCILARI
 * ------------------------------------------------- */

/**
 * E-kitap sayfasının slug'ı (Ayarlar'dan değiştirilebilir).
 */
function ewt_ebook_page_slug() {
	$slug = trim( (string) get_option( 'ewt_ebook_page_slug', 'ingilizce-e-kitaplar' ), '/ ' );
	return $slug !== '' ? $slug : 'ingilizce-e-kitaplar';
}

/**
 * E-kitap sayfasının adresi. Sayfa gerçekten varsa kalıcı bağlantısı,
 * yoksa slug'dan türetilmiş adres döner.
 *
 * @param int $preselect_id Verilirse, o ders sayfada hazır seçili gelir.
 */
function ewt_ebook_page_url( $preselect_id = 0 ) {
	$slug = ewt_ebook_page_slug();
	$page = get_page_by_path( $slug );
	$url  = $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );

	if ( $preselect_id ) {
		$url = add_query_arg( 'ewt_add', absint( $preselect_id ), $url );
	}
	return $url;
}

/**
 * Şu an görüntülenen sayfa, e-kitap sayfası mı?
 */
function ewt_ebook_is_ebook_page() {
	return is_page() && get_post_field( 'post_name', get_queried_object_id() ) === ewt_ebook_page_slug();
}

/**
 * ?ewt_add=ID ile gelen ön seçimi doğrular.
 * Ders sayfalarındaki CTA'dan gelen ziyaretçi, oluşturucuyu o ders hazır
 * seçilmiş halde bulur — seçimi baştan yapmak zorunda kalmaz.
 *
 * @return array|null ['id' => int, 'title' => string, 'cpt' => string]
 */
function ewt_ebook_get_preselect() {
	if ( empty( $_GET['ewt_add'] ) ) return null;

	$id   = absint( $_GET['ewt_add'] );
	$post = $id ? get_post( $id ) : null;

	if ( ! $post || $post->post_status !== 'publish' ) return null;
	if ( ! in_array( $post->post_type, ewt_ebook_allowed_post_types(), true ) ) return null;
	if ( ! empty( $post->post_password ) ) return null;

	return array(
		'id'    => $post->ID,
		'title' => get_the_title( $post ),
		'cpt'   => $post->post_type,
	);
}

/* -------------------------------------------------
 * KISA KOD: [ewt_ebook_builder cpt="" title="..."]
 * ------------------------------------------------- */
function ewt_ebook_builder_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'cpt'   => '',
		'title' => 'Kendi İngilizce E-Kitabını Oluştur',
		'intro' => 'Ders türünü ve seviyeni seç, istediğin konuları işaretle — sana özel, kapaklı ve içindekiler tablolu bir çalışma kitabı saniyeler içinde hazır olsun.',
	), $atts, 'ewt_ebook_builder' );

	$GLOBALS['ewt_ebook_used'] = true; // wp_footer'da JS/CSS'i tetikler

	if ( ! ewt_ebook_enabled() ) {
		return '<div class="ewt-ebook ewt-ebook-disabled"><p>E-kitap oluşturucu şu anda kullanılamıyor. Kısa süre içinde tekrar deneyin.</p></div>';
	}

	// Seçilebilir ders türleri (içeriği olanlar önce gelsin diye sayıya göre değil,
	// admin tanımlı menü sırasına göre listeleniyor).
	$cpt_context = sanitize_key( $atts['cpt'] );
	$cpt_config  = ewt_cpt_config();

	// Ders sayfasındaki CTA'dan gelindiyse (?ewt_add=ID) o dersin türüyle açılsın.
	$preselect = ewt_ebook_get_preselect();
	if ( $preselect && ! $cpt_context ) {
		$cpt_context = $preselect['cpt'];
	}

	ob_start();
	?>
	<div class="ewt-ebook" data-cpt-context="<?php echo esc_attr( $cpt_context ); ?>" data-max="<?php echo esc_attr( ewt_ebook_max_posts() ); ?>">
		<h3 class="ewt-ebook-title"><?php echo esc_html( $atts['title'] ); ?></h3>
		<p class="ewt-ebook-sub"><?php echo esc_html( $atts['intro'] ); ?></p>

		<form class="ewt-ebook-form" data-rendered-at="<?php echo esc_attr( time() ); ?>">
			<?php wp_nonce_field( 'ewt_ebook_action', 'ewt_ebook_nonce' ); ?>
			<input type="text" name="ewt_ebook_hp" class="ewt-ebook-hp" tabindex="-1" autocomplete="off" value="" aria-hidden="true" />

			<div class="ewt-ebook-steps">
				<!-- ADIM 1 -->
				<div class="ewt-ebook-step">
					<span class="ewt-ebook-step-no">1</span>
					<label class="ewt-ebook-field">
						<span class="ewt-ebook-label">Ders Türü</span>
						<select class="ewt-ebook-cpt" name="ebook_cpt">
							<?php if ( ! $cpt_context ) : ?>
								<option value="">— Seçiniz —</option>
							<?php endif; ?>
							<?php foreach ( $cpt_config as $slug => $data ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cpt_context, $slug ); ?>><?php echo esc_html( $data[0] ); ?></option>
							<?php endforeach; ?>
							<option value="post" <?php selected( $cpt_context, 'post' ); ?>>Blog Yazıları</option>
						</select>
					</label>
				</div>

				<!-- ADIM 2 -->
				<div class="ewt-ebook-step">
					<span class="ewt-ebook-step-no">2</span>
					<label class="ewt-ebook-field">
						<span class="ewt-ebook-label">Seviye</span>
						<select class="ewt-ebook-level" name="ebook_level" disabled>
							<option value="">Önce ders türü seçin</option>
						</select>
					</label>
				</div>

				<!-- ADIM 3 -->
				<div class="ewt-ebook-step ewt-ebook-step-search">
					<span class="ewt-ebook-step-no">3</span>
					<label class="ewt-ebook-field">
						<span class="ewt-ebook-label">Konularda Ara <em>(isteğe bağlı)</em></span>
						<input type="search" class="ewt-ebook-search" placeholder="örn. present perfect, irregular verbs..." maxlength="60" />
					</label>
				</div>
			</div>

			<!-- KONU LİSTESİ -->
			<div class="ewt-ebook-list-wrap">
				<div class="ewt-ebook-list-head">
					<strong class="ewt-ebook-list-title">Konular</strong>
					<div class="ewt-ebook-list-actions">
						<button type="button" class="ewt-ebook-select-all" hidden>Tümünü Seç</button>
						<button type="button" class="ewt-ebook-clear" hidden>Temizle</button>
					</div>
				</div>
				<div class="ewt-ebook-list" aria-live="polite">
					<p class="ewt-ebook-placeholder">Başlamak için yukarıdan bir ders türü seçin.</p>
				</div>
				<button type="button" class="ewt-ebook-more" hidden>Daha fazla konu yükle</button>
			</div>

			<!-- SEPET / ÖZET -->
			<div class="ewt-ebook-cart" hidden>
				<div class="ewt-ebook-cart-head">
					<strong>Kitabında <span class="ewt-ebook-count">0</span> konu var</strong>
					<span class="ewt-ebook-limit">(en fazla <?php echo esc_html( ewt_ebook_max_posts() ); ?>)</span>
				</div>
				<ul class="ewt-ebook-cart-list"></ul>
			</div>

			<!-- ADIM 4: ÜRETİM -->
			<div class="ewt-ebook-finalize" hidden>
				<label class="ewt-ebook-field ewt-ebook-booktitle-field">
					<span class="ewt-ebook-label">Kitabının Adı <em>(isteğe bağlı)</em></span>
					<input type="text" class="ewt-ebook-booktitle" placeholder="örn. A2 Kelime Çalışma Kitabım" maxlength="80" />
				</label>

				<div class="ewt-ebook-format">
					<span class="ewt-ebook-label">Format</span>
					<div class="ewt-ebook-format-opts">
						<label class="ewt-ebook-radio">
							<input type="radio" name="ebook_format" value="pdf" checked />
							<span><strong>PDF</strong><em>Yazdırmak ve bilgisayarda okumak için</em></span>
						</label>
						<label class="ewt-ebook-radio">
							<input type="radio" name="ebook_format" value="epub" />
							<span><strong>EPUB</strong><em>Telefon, tablet ve e-kitap okuyucu için</em></span>
						</label>
					</div>
				</div>

				<?php if ( ewt_ebook_email_capture_enabled() ) : ?>
				<label class="ewt-ebook-field ewt-ebook-email-field">
					<span class="ewt-ebook-label">E-posta <em>(isteğe bağlı — yeni ücretsiz kitaplardan haberdar ol)</em></span>
					<input type="email" class="ewt-ebook-email" placeholder="ornek@eposta.com" maxlength="120" />
				</label>
				<?php endif; ?>

				<button type="submit" class="ewt-ebook-submit">📘 E-Kitabımı Oluştur</button>
			</div>
		</form>

		<div class="ewt-ebook-result" aria-live="polite" hidden></div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ewt_ebook_builder', 'ewt_ebook_builder_shortcode' );

/**
 * JS/CSS'i sadece kısa kod gerçekten kullanıldıysa yükler
 * (Elementor "Kısa Kod" widget'ı dahil çalışsın diye wp_footer'da).
 */
function ewt_ebook_print_assets_footer() {
	if ( empty( $GLOBALS['ewt_ebook_used'] ) ) return;
	?>
	<link rel="stylesheet" href="<?php echo esc_url( EWT_URI . '/assets/css/ebook-builder.css?ver=' . EWT_VERSION ); ?>" />
	<script>var ewtEbook = <?php echo wp_json_encode( array(
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'maxPosts'  => ewt_ebook_max_posts(),
		'perPage'   => 40,
		'preselect' => ewt_ebook_get_preselect(),
	) ); ?>;</script>
	<script src="<?php echo esc_url( EWT_URI . '/assets/js/ebook-builder.js?ver=' . EWT_VERSION ); ?>" defer></script>
	<?php
}
add_action( 'wp_footer', 'ewt_ebook_print_assets_footer', 5 );

/* -------------------------------------------------
 * OTOMATİK YERLEŞTİRME 1: E-KİTAP SAYFASI
 *
 * Ayarlarda belirtilen slug'a sahip sayfanın (varsayılan: ingilizce-e-kitaplar)
 * içeriğinin sonuna oluşturucuyu ekler. Sayfada zaten [ewt_ebook_builder]
 * kısa kodu elle kullanılmışsa İKİNCİ KEZ eklenmez.
 * ------------------------------------------------- */
function ewt_ebook_auto_insert_page( $content ) {
	if ( ! ewt_ebook_enabled() ) return $content;
	if ( get_option( 'ewt_ebook_page_auto', '1' ) !== '1' ) return $content;
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) return $content;
	if ( ! ewt_ebook_is_ebook_page() ) return $content;
	if ( has_shortcode( $content, 'ewt_ebook_builder' ) ) return $content;

	return $content . do_shortcode( '[ewt_ebook_builder]' );
}
add_filter( 'the_content', 'ewt_ebook_auto_insert_page', 20 );

/* -------------------------------------------------
 * OTOMATİK YERLEŞTİRME 2: SİTE GENELİ CTA (ders sayfalarının altında)
 *
 * PERFORMANS NOTU: Bu CTA bilinçli olarak "sıfır maliyetli" tutulmuştur —
 * ekstra JS yok, ekstra CSS dosyası yok (yalnızca ~1 KB satır içi stil, sayfa
 * başına bir kez), ekstra veritabanı sorgusu yok (zaten yüklü olan $post
 * nesnesi kullanılır). Oluşturucunun asıl CSS/JS dosyaları SADECE kısa kodun
 * bulunduğu sayfalarda yüklenir; bu CTA onları tetiklemez.
 * ------------------------------------------------- */
function ewt_ebook_append_cta( $content ) {
	if ( ! ewt_ebook_enabled() ) return $content;
	if ( get_option( 'ewt_ebook_cta_enabled', '1' ) !== '1' ) return $content;
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) return $content;
	if ( is_front_page() || ewt_ebook_is_ebook_page() ) return $content;

	$post = get_post();
	if ( ! $post || ! in_array( $post->post_type, ewt_ebook_allowed_post_types(), true ) ) return $content;

	// Sayfada oluşturucu zaten varsa CTA gereksizdir.
	if ( has_shortcode( $content, 'ewt_ebook_builder' ) ) return $content;

	return $content . ewt_ebook_cta_html( $post->ID );
}
add_filter( 'the_content', 'ewt_ebook_append_cta', 22 );

/**
 * CTA kutusunun HTML'i. Stil, sayfa başına yalnızca bir kez basılır.
 */
function ewt_ebook_cta_html( $post_id ) {
	$style = '';
	if ( empty( $GLOBALS['ewt_ebook_cta_css_done'] ) ) {
		$GLOBALS['ewt_ebook_cta_css_done'] = true;
		$style = '<style id="ewt-ebook-cta-css">'
			. '.ewt-ebook-cta{display:flex;align-items:center;gap:18px;flex-wrap:wrap;'
			. 'margin:32px 0;padding:20px 24px;border:1px solid var(--ewt-border,#e2e8f0);'
			. 'border-left:4px solid var(--ewt-primary,#2563eb);border-radius:var(--ewt-radius,14px);'
			. 'background:var(--ewt-surface,#fff)}'
			. '.ewt-ebook-cta-icon{font-size:30px;line-height:1}'
			. '.ewt-ebook-cta-body{flex:1 1 240px;min-width:0}'
			. '.ewt-ebook-cta-body strong{display:block;font-size:16px;color:var(--ewt-text,#1e293b);margin-bottom:3px}'
			. '.ewt-ebook-cta-body span{font-size:14px;color:var(--ewt-text-muted,#475569);line-height:1.5}'
			. '.ewt-ebook-cta-btn{display:inline-block;background:var(--ewt-accent,#f59e0b);color:#1e293b !important;'
			. 'font-weight:800;font-size:15px;padding:12px 24px;border-radius:10px;text-decoration:none !important;white-space:nowrap}'
			. '.ewt-ebook-cta-btn:hover{filter:brightness(.95)}'
			. '@media(max-width:600px){.ewt-ebook-cta{padding:16px 18px;gap:12px}.ewt-ebook-cta-btn{width:100%;text-align:center}}'
			. '</style>';
	}

	return $style
		. '<aside class="ewt-ebook-cta">'
		. '<span class="ewt-ebook-cta-icon" aria-hidden="true">📘</span>'
		. '<span class="ewt-ebook-cta-body">'
		. '<strong>Bu konuyu kendi kitabına ekle</strong>'
		. '<span>İstediğin konuları seç, kapaklı ve içindekiler tablolu ücretsiz bir PDF/EPUB çalışma kitabı oluştur. Üyelik gerekmez.</span>'
		. '</span>'
		. '<a class="ewt-ebook-cta-btn" href="' . esc_url( ewt_ebook_page_url( $post_id ) ) . '">E-Kitabımı Oluştur →</a>'
		. '</aside>';
}

/**
 * Ana sayfa bölümü — front-page.php içinden çağrılır.
 */
function ewt_ebook_home_section() {
	if ( ! ewt_ebook_enabled() ) return;
	if ( get_option( 'ewt_ebook_home_enabled', '1' ) !== '1' ) return;
	?>
	<section id="ewt-ebook-home" class="ewt-home-ebook-wrap">
		<div class="ewt-container">
			<h2 class="ewt-section-title">📘 Kendi İngilizce E-Kitabını Oluştur</h2>
			<p class="ewt-section-subtitle">Üye olmadan, tamamen ücretsiz: ders türünü ve seviyeni seç, istediğin konuları işaretle; kapaklı, içindekiler tablolu bir çalışma kitabını PDF ya da EPUB olarak indir.</p>
			<?php echo do_shortcode( '[ewt_ebook_builder title="Konularını Seç, Kitabını İndir"]' ); ?>
		</div>
	</section>
	<?php
}

/* -------------------------------------------------
 * AJAX 1: SEVİYE TERİMLERİ + KONU LİSTESİ (herkese açık)
 * ------------------------------------------------- */
function ewt_ajax_ebook_browse() {
	if ( ! ewt_ebook_enabled() ) {
		wp_send_json_error( array( 'message' => 'Bu özellik şu anda kullanılamıyor.' ) );
	}
	if ( ! check_ajax_referer( 'ewt_ebook_action', 'nonce', false ) ) {
		wp_send_json_error( array( 'code' => 'nonce_expired', 'message' => 'Oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.' ) );
	}

	$cpt = isset( $_POST['cpt'] ) ? sanitize_key( $_POST['cpt'] ) : '';
	if ( ! in_array( $cpt, ewt_ebook_allowed_post_types(), true ) ) {
		wp_send_json_error( array( 'message' => 'Geçersiz ders türü.' ) );
	}

	$level  = isset( $_POST['level'] ) ? sanitize_title( wp_unslash( $_POST['level'] ) ) : '';
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$search = mb_substr( trim( $search ), 0, 60 );
	$page   = max( 1, isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1 );
	$per    = 40;

	$args = array(
		'post_type'           => $cpt,
		'post_status'         => 'publish',
		'posts_per_page'      => $per,
		'paged'               => $page,
		'orderby'             => 'date',
		'order'               => 'ASC',
		'has_password'        => false,
		'ignore_sticky_posts' => true,
	);

	if ( $search !== '' ) {
		$args['s'] = $search;
	}

	$tax = $cpt . '_seviye';
	if ( $level !== '' && taxonomy_exists( $tax ) ) {
		$args['tax_query'] = array( array(
			'taxonomy' => $tax,
			'field'    => 'slug',
			'terms'    => $level,
		) );
	}

	$q     = new WP_Query( $args );
	$items = array();

	foreach ( $q->posts as $post ) {
		$excerpt = get_the_excerpt( $post );
		$items[] = array(
			'id'      => $post->ID,
			'title'   => get_the_title( $post ),
			'excerpt' => wp_trim_words( wp_strip_all_tags( $excerpt ), 18 ),
			'levels'  => implode( ', ', ewt_ebook_post_level_names( $post ) ),
		);
	}

	// Seviye terimleri (ilk sayfada gönderilir, JS bunu <select>'e doldurur).
	$levels = array();
	if ( $page === 1 && taxonomy_exists( $tax ) ) {
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$levels[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => intval( $t->count ) );
			}
		}
	}

	wp_send_json_success( array(
		'items'    => $items,
		'levels'   => $levels,
		'page'     => $page,
		'maxPages' => intval( $q->max_num_pages ),
		'total'    => intval( $q->found_posts ),
	) );
}
add_action( 'wp_ajax_ewt_ebook_browse', 'ewt_ajax_ebook_browse' );
add_action( 'wp_ajax_nopriv_ewt_ebook_browse', 'ewt_ajax_ebook_browse' );

/* -------------------------------------------------
 * AJAX 2: E-KİTABI ÜRET (herkese açık)
 * ------------------------------------------------- */
function ewt_ajax_ebook_create() {
	if ( ! ewt_ebook_enabled() ) {
		wp_send_json_error( array( 'message' => 'Bu özellik şu anda kullanılamıyor.' ) );
	}
	if ( ! check_ajax_referer( 'ewt_ebook_action', 'nonce', false ) ) {
		wp_send_json_error( array( 'code' => 'nonce_expired', 'message' => 'Oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.' ) );
	}

	// Honeypot
	if ( ! empty( $_POST['ewt_ebook_hp'] ) ) {
		wp_send_json_error( array( 'message' => 'İşlem doğrulanamadı.' ) );
	}

	// Form render edildikten en az 3 saniye sonra gönderilmeli.
	$rendered_at = isset( $_POST['rendered_at'] ) ? intval( $_POST['rendered_at'] ) : 0;
	if ( $rendered_at && ( time() - $rendered_at ) < 3 ) {
		wp_send_json_error( array( 'message' => 'Lütfen birkaç saniye bekleyip tekrar deneyin.' ) );
	}

	$format = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'pdf';
	if ( ! in_array( $format, array( 'pdf', 'epub' ), true ) ) $format = 'pdf';

	$ids = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array();
	$ids = array_map( 'absint', $ids );

	$chapters = ewt_ebook_collect_posts( $ids );
	if ( is_wp_error( $chapters ) ) {
		wp_send_json_error( array( 'message' => $chapters->get_error_message() ) );
	}

	if ( ! ewt_ebook_check_rate_limit() ) {
		wp_send_json_error( array( 'message' => sprintf(
			'Bu saat içinde %d e-kitap oluşturdunuz. Bir saat sonra tekrar deneyebilirsiniz.',
			ewt_ebook_rate_limit()
		) ) );
	}

	$book_title = isset( $_POST['book_title'] ) ? sanitize_text_field( wp_unslash( $_POST['book_title'] ) ) : '';
	$book_title = mb_substr( trim( $book_title ), 0, 80 );
	if ( $book_title === '' ) {
		$book_title = ewt_ebook_default_title( $chapters );
	}

	$book = array(
		'title'     => $book_title,
		'chapters'  => $chapters,
		'created'   => current_time( 'mysql' ),
		'site'      => get_bloginfo( 'name' ),
		'site_url'  => home_url( '/' ),
	);

	// İsteğe bağlı e-posta kaydı
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( $email && is_email( $email ) && ewt_ebook_email_capture_enabled() ) {
		ewt_ebook_save_lead( $email, $book_title, count( $chapters ), $format );
	}

	ewt_ebook_bump_stats( $chapters, $format );

	if ( $format === 'epub' ) {
		$result = ewt_ebook_build_epub( $book, $ids );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array(
			'format'   => 'epub',
			'url'      => $result['url'],
			'filename' => $result['filename'],
			'title'    => $book_title,
			'count'    => count( $chapters ),
		) );
	}

	// PDF = yazdırma görünümü. Seçim bir token altında saklanır, yeni sekmede açılır.
	$token = wp_generate_password( 20, false, false );
	set_transient( 'ewt_ebook_' . $token, $book, EWT_EBOOK_TOKEN_TTL );

	wp_send_json_success( array(
		'format' => 'pdf',
		'url'    => add_query_arg( 'ewt_ebook_print', $token, home_url( '/' ) ),
		'title'  => $book_title,
		'count'  => count( $chapters ),
	) );
}
add_action( 'wp_ajax_ewt_ebook_create', 'ewt_ajax_ebook_create' );
add_action( 'wp_ajax_nopriv_ewt_ebook_create', 'ewt_ajax_ebook_create' );

/* -------------------------------------------------
 * YAZDIRMA GÖRÜNÜMÜ (PDF)
 * ------------------------------------------------- */
function ewt_ebook_handle_print_request() {
	if ( empty( $_GET['ewt_ebook_print'] ) ) return;

	$token = sanitize_text_field( wp_unslash( $_GET['ewt_ebook_print'] ) );
	$book  = get_transient( 'ewt_ebook_' . $token );

	if ( ! $book || empty( $book['chapters'] ) ) {
		wp_die(
			'<h1>E-kitap bağlantısının süresi dolmuş</h1><p>Bu bağlantı 12 saat geçerlidir. Lütfen e-kitabınızı yeniden oluşturun.</p>',
			'E-kitap bulunamadı',
			array( 'response' => 410, 'back_link' => true )
		);
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	echo ewt_ebook_render_print_html( $book ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}
add_action( 'template_redirect', 'ewt_ebook_handle_print_request' );

/**
 * Kapak + içindekiler + bölümler içeren, yazdırmaya hazır tam HTML belge.
 */
function ewt_ebook_render_print_html( $book ) {
	$title = $book['title'];
	$date  = date_i18n( 'j F Y', strtotime( $book['created'] ) );
	$logo  = '';
	if ( function_exists( 'get_custom_logo' ) && has_custom_logo() ) {
		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_src = wp_get_attachment_image_src( $logo_id, 'medium' );
		if ( $logo_src ) $logo = $logo_src[0];
	}

	ob_start();
	?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title><?php echo esc_html( $title ); ?></title>
<style>
	@page { size: A4; margin: 20mm 18mm 22mm 18mm; }

	:root {
		--ink: #1a1a1a;
		--muted: #5b6470;
		--accent: #1e5eff;
		--line: #d8dee8;
	}

	* { box-sizing: border-box; }

	body {
		margin: 0;
		font-family: Georgia, "Times New Roman", serif;
		font-size: 11.5pt;
		line-height: 1.65;
		color: var(--ink);
		background: #eef1f6;
	}

	.ewt-doc {
		max-width: 210mm;
		margin: 0 auto;
		background: #fff;
		padding: 20mm 18mm;
		box-shadow: 0 2px 24px rgba(0,0,0,.12);
	}

	/* ---- Ekrandaki araç çubuğu (yazdırırken gizlenir) ---- */
	.ewt-bar {
		position: sticky; top: 0; z-index: 10;
		background: #16233a; color: #fff;
		padding: 14px 20px;
		display: flex; flex-wrap: wrap; gap: 12px;
		align-items: center; justify-content: center;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size: 14px;
	}
	.ewt-bar strong { font-weight: 600; }
	.ewt-bar button {
		background: var(--accent); color: #fff; border: 0;
		padding: 10px 20px; border-radius: 8px;
		font-size: 14px; font-weight: 600; cursor: pointer;
		font-family: inherit;
	}
	.ewt-bar button:hover { background: #1546c4; }
	.ewt-bar small { opacity: .75; display: block; width: 100%; text-align: center; }

	/* ---- Kapak ---- */
	.ewt-cover {
		min-height: 240mm;
		display: flex; flex-direction: column;
		justify-content: center; align-items: center;
		text-align: center;
		page-break-after: always;
		border-bottom: 1px dashed var(--line);
	}
	.ewt-cover img.logo { max-width: 160px; margin-bottom: 28px; }
	.ewt-cover .kicker {
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		letter-spacing: .18em; text-transform: uppercase;
		font-size: 9pt; color: var(--muted); margin-bottom: 18px;
	}
	.ewt-cover h1 {
		font-size: 30pt; line-height: 1.2; margin: 0 0 20px;
		max-width: 15em;
	}
	.ewt-cover .rule { width: 70px; height: 3px; background: var(--accent); margin: 0 0 22px; }
	.ewt-cover .meta { color: var(--muted); font-size: 10.5pt; }
	.ewt-cover .source {
		margin-top: 40px; font-size: 10pt; color: var(--muted);
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
	}
	.ewt-cover .source a { color: var(--accent); text-decoration: none; }

	/* ---- İçindekiler ---- */
	.ewt-toc { page-break-after: always; }
	.ewt-toc h2 {
		font-size: 18pt; margin: 0 0 6px;
		padding-bottom: 10px; border-bottom: 2px solid var(--ink);
	}
	.ewt-toc ol { list-style: none; padding: 0; margin: 22px 0 0; counter-reset: toc; }
	.ewt-toc li {
		counter-increment: toc;
		display: flex; align-items: baseline; gap: 10px;
		padding: 9px 0; border-bottom: 1px dotted var(--line);
	}
	.ewt-toc li::before {
		content: counter(toc);
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size: 9.5pt; font-weight: 700; color: var(--accent);
		min-width: 26px;
	}
	.ewt-toc .t { flex: 1; }
	.ewt-toc .lvl {
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size: 8.5pt; color: var(--muted); white-space: nowrap;
	}

	/* ---- Bölümler ---- */
	.ewt-chapter { page-break-before: always; }
	.ewt-chapter-no {
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size: 9pt; letter-spacing: .16em; text-transform: uppercase;
		color: var(--accent); font-weight: 700; margin-bottom: 6px;
	}
	.ewt-chapter h2 {
		font-size: 19pt; line-height: 1.25; margin: 0 0 4px;
	}
	.ewt-chapter .ch-meta {
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size: 9pt; color: var(--muted);
		padding-bottom: 14px; margin-bottom: 20px;
		border-bottom: 1px solid var(--line);
	}
	.ewt-chapter h3 { font-size: 14pt; margin: 22px 0 8px; }
	.ewt-chapter h4 { font-size: 12pt; margin: 18px 0 6px; }
	.ewt-chapter p { margin: 0 0 12px; orphans: 3; widows: 3; }
	.ewt-chapter ul, .ewt-chapter ol { margin: 0 0 14px; padding-left: 22px; }
	.ewt-chapter li { margin-bottom: 6px; }
	.ewt-chapter img { max-width: 100%; height: auto; page-break-inside: avoid; }
	.ewt-chapter table {
		width: 100%; border-collapse: collapse; margin: 0 0 16px;
		font-size: 10pt; page-break-inside: avoid;
	}
	.ewt-chapter th, .ewt-chapter td {
		border: 1px solid var(--line); padding: 7px 9px; text-align: left;
	}
	.ewt-chapter th { background: #f3f6fb; font-weight: 700; }
	.ewt-chapter blockquote {
		margin: 0 0 14px; padding: 4px 0 4px 16px;
		border-left: 3px solid var(--accent); color: var(--muted); font-style: italic;
	}
	.ewt-chapter a { color: var(--ink); text-decoration: underline; }
	.ewt-chapter code {
		font-family: "SFMono-Regular", Consolas, monospace; font-size: 10pt;
		background: #f3f6fb; padding: 1px 4px; border-radius: 3px;
	}
	/* Cevap anahtarı blokları — kitapta her zaman AÇIK görünür */
	.ewt-chapter .ewt-ebook-answerkey {
		border: 1px solid var(--line); border-left: 3px solid var(--accent);
		background: #f8fafc; border-radius: 6px;
		padding: 12px 16px; margin: 20px 0;
		page-break-inside: avoid;
	}
	.ewt-chapter .ewt-ebook-answerkey > h3 { margin-top: 0; font-size: 12.5pt; }
	.ewt-chapter .ewt-ebook-answerkey > *:last-child { margin-bottom: 0; }

	.ewt-chapter-src {
		margin-top: 18px; padding-top: 10px; border-top: 1px solid var(--line);
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size: 8pt; color: var(--muted); word-break: break-all;
	}

	/* ---- Arka kapak ---- */
	.ewt-back {
		page-break-before: always; text-align: center;
		padding-top: 60mm;
	}
	.ewt-back h2 { font-size: 16pt; margin-bottom: 10px; }
	.ewt-back p { color: var(--muted); font-size: 10.5pt; }
	.ewt-back a { color: var(--accent); text-decoration: none; font-weight: 700; }

	/* ---- YAZDIRMA ---- */
	@media print {
		body { background: #fff; }
		.ewt-bar { display: none !important; }
		.ewt-doc { max-width: none; margin: 0; padding: 0; box-shadow: none; }
		.ewt-cover { min-height: auto; height: 92vh; border-bottom: 0; }
		a { color: inherit !important; }
	}

	@media screen and (max-width: 820px) {
		.ewt-doc { padding: 24px 18px; }
		.ewt-cover { min-height: 70vh; }
		.ewt-cover h1 { font-size: 24pt; }
	}
</style>
</head>
<body>

<div class="ewt-bar">
	<strong>📘 E-kitabın hazır.</strong>
	<button type="button" onclick="window.print()">PDF olarak kaydet / Yazdır</button>
	<small>Açılan pencerede yazıcı olarak <b>"PDF olarak kaydet"</b> (Save as PDF) seçeneğini işaretle. Bu sayfayı kapatabilirsin.</small>
</div>

<div class="ewt-doc">

	<!-- KAPAK -->
	<section class="ewt-cover">
		<?php if ( $logo ) : ?>
			<img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="" />
		<?php endif; ?>
		<div class="kicker">Kişiye Özel Çalışma Kitabı</div>
		<h1><?php echo esc_html( $title ); ?></h1>
		<div class="rule"></div>
		<div class="meta">
			<?php echo esc_html( count( $book['chapters'] ) ); ?> konu &nbsp;•&nbsp; <?php echo esc_html( $date ); ?>
		</div>
		<div class="source">
			Hazırlayan: <a href="<?php echo esc_url( $book['site_url'] ); ?>"><?php echo esc_html( $book['site'] ); ?></a>
		</div>
	</section>

	<!-- İÇİNDEKİLER -->
	<section class="ewt-toc">
		<h2>İçindekiler</h2>
		<ol>
			<?php foreach ( $book['chapters'] as $ch ) : ?>
				<li>
					<span class="t"><?php echo esc_html( $ch['title'] ); ?></span>
					<?php if ( ! empty( $ch['levels'] ) ) : ?>
						<span class="lvl"><?php echo esc_html( implode( ', ', $ch['levels'] ) ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>

	<!-- BÖLÜMLER -->
	<?php $i = 0; foreach ( $book['chapters'] as $ch ) : $i++; ?>
		<section class="ewt-chapter">
			<div class="ewt-chapter-no">Bölüm <?php echo esc_html( $i ); ?></div>
			<h2><?php echo esc_html( $ch['title'] ); ?></h2>
			<div class="ch-meta">
				<?php
				$bits = array_filter( array( $ch['type_label'], implode( ', ', $ch['levels'] ) ) );
				echo esc_html( implode( ' • ', $bits ) );
				?>
			</div>
			<?php echo $ch['html']; // zaten ewt_ebook_sanitize_html()'den geçti ?>
			<div class="ewt-chapter-src">Kaynak: <?php echo esc_html( $ch['permalink'] ); ?></div>
		</section>
	<?php endforeach; ?>

	<!-- ARKA KAPAK -->
	<section class="ewt-back">
		<h2>Bu kitap <?php echo esc_html( $book['site'] ); ?> içeriklerinden derlendi.</h2>
		<p>
			Yüzlerce ücretsiz İngilizce konu anlatımı, test ve dinleme materyali için:<br />
			<a href="<?php echo esc_url( $book['site_url'] ); ?>"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $book['site_url'] ) ) ); ?></a>
		</p>
	</section>

</div>

<script>
	// Sayfa tam yüklendikten kısa süre sonra yazdırma penceresini otomatik aç.
	window.addEventListener('load', function () {
		setTimeout(function () { try { window.print(); } catch (e) {} }, 700);
	});
</script>
</body>
</html>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------
 * EPUB ÜRETİMİ (ZipArchive — harici kütüphane yok)
 * ------------------------------------------------- */
function ewt_ebook_build_epub( $book, $ids ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'ewt_ebook_nozip', 'Sunucunuzda EPUB desteği (ZipArchive) bulunmuyor. Lütfen PDF formatını seçin.' );
	}

	$paths = ewt_ebook_upload_dir();

	// Önbellek: aynı seçim + aynı başlık için dosya zaten varsa yeniden üretme.
	$hash     = md5( implode( ',', $ids ) . '|' . $book['title'] );
	$filename = 'ewt-ekitap-' . $hash . '.epub';
	$file     = trailingslashit( $paths['dir'] ) . $filename;

	if ( file_exists( $file ) && ( time() - filemtime( $file ) ) < EWT_EBOOK_FILE_TTL ) {
		return array( 'url' => trailingslashit( $paths['url'] ) . $filename, 'filename' => $filename );
	}

	$uuid = 'urn:uuid:' . wp_generate_uuid4();
	$zip  = new ZipArchive();

	if ( $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
		return new WP_Error( 'ewt_ebook_zipfail', 'E-kitap dosyası oluşturulamadı. Lütfen PDF formatını deneyin.' );
	}

	// 1) mimetype — SIKIŞTIRILMADAN ve ilk sırada eklenmeli (EPUB şartı)
	$zip->addFromString( 'mimetype', 'application/epub+zip' );
	$zip->setCompressionName( 'mimetype', ZipArchive::CM_STORE );

	// 2) container.xml
	$zip->addFromString( 'META-INF/container.xml',
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		'<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">' .
		'<rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles>' .
		'</container>'
	);

	// 3) Stil
	$zip->addFromString( 'OEBPS/style.css', ewt_ebook_epub_css() );

	// 4) Kapak sayfası
	$zip->addFromString( 'OEBPS/cover.xhtml', ewt_ebook_epub_cover( $book ) );

	// 5) Bölümler
	$manifest = '';
	$spine    = '';
	$navlis   = '';
	$ncxnav   = '';
	$i        = 0;

	foreach ( $book['chapters'] as $ch ) {
		$i++;
		$id   = 'ch' . $i;
		$name = $id . '.xhtml';

		$zip->addFromString( 'OEBPS/' . $name, ewt_ebook_epub_chapter( $ch, $i ) );

		$manifest .= '<item id="' . $id . '" href="' . $name . '" media-type="application/xhtml+xml"/>';
		$spine    .= '<itemref idref="' . $id . '"/>';
		$navlis   .= '<li><a href="' . $name . '">' . ewt_ebook_xml( $ch['title'] ) . '</a></li>';
		$ncxnav   .= '<navPoint id="nav' . $i . '" playOrder="' . ( $i + 1 ) . '">'
			. '<navLabel><text>' . ewt_ebook_xml( $ch['title'] ) . '</text></navLabel>'
			. '<content src="' . $name . '"/></navPoint>';
	}

	// 6) nav.xhtml (EPUB3 içindekiler)
	$zip->addFromString( 'OEBPS/nav.xhtml',
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		'<!DOCTYPE html>' . "\n" .
		'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="tr" xml:lang="tr">' .
		'<head><meta charset="utf-8"/><title>İçindekiler</title><link rel="stylesheet" type="text/css" href="style.css"/></head>' .
		'<body><nav epub:type="toc" id="toc"><h1>İçindekiler</h1><ol>' . $navlis . '</ol></nav></body></html>'
	);

	// 7) toc.ncx (eski okuyucular için geriye dönük uyumluluk)
	$zip->addFromString( 'OEBPS/toc.ncx',
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		'<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">' .
		'<head><meta name="dtb:uid" content="' . ewt_ebook_xml( $uuid ) . '"/>' .
		'<meta name="dtb:depth" content="1"/><meta name="dtb:totalPageCount" content="0"/>' .
		'<meta name="dtb:maxPageNumber" content="0"/></head>' .
		'<docTitle><text>' . ewt_ebook_xml( $book['title'] ) . '</text></docTitle>' .
		'<navMap>' .
		'<navPoint id="navcover" playOrder="1"><navLabel><text>Kapak</text></navLabel><content src="cover.xhtml"/></navPoint>' .
		$ncxnav .
		'</navMap></ncx>'
	);

	// 8) content.opf
	$zip->addFromString( 'OEBPS/content.opf',
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		'<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="tr">' .
		'<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">' .
		'<dc:identifier id="bookid">' . ewt_ebook_xml( $uuid ) . '</dc:identifier>' .
		'<dc:title>' . ewt_ebook_xml( $book['title'] ) . '</dc:title>' .
		'<dc:language>tr</dc:language>' .
		'<dc:creator>' . ewt_ebook_xml( $book['site'] ) . '</dc:creator>' .
		'<dc:publisher>' . ewt_ebook_xml( $book['site'] ) . '</dc:publisher>' .
		'<dc:source>' . ewt_ebook_xml( $book['site_url'] ) . '</dc:source>' .
		'<dc:date>' . ewt_ebook_xml( date_i18n( 'Y-m-d', strtotime( $book['created'] ) ) ) . '</dc:date>' .
		'<meta property="dcterms:modified">' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</meta>' .
		'</metadata>' .
		'<manifest>' .
		'<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>' .
		'<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>' .
		'<item id="css" href="style.css" media-type="text/css"/>' .
		'<item id="cover" href="cover.xhtml" media-type="application/xhtml+xml"/>' .
		$manifest .
		'</manifest>' .
		'<spine toc="ncx"><itemref idref="cover"/>' . $spine . '</spine>' .
		'</package>'
	);

	$zip->close();

	if ( ! file_exists( $file ) ) {
		return new WP_Error( 'ewt_ebook_zipfail', 'E-kitap dosyası yazılamadı. Lütfen PDF formatını deneyin.' );
	}

	return array( 'url' => trailingslashit( $paths['url'] ) . $filename, 'filename' => $filename );
}

function ewt_ebook_xml( $text ) {
	return htmlspecialchars( (string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function ewt_ebook_epub_css() {
	return "body{font-family:Georgia,serif;line-height:1.6;margin:5%;color:#1a1a1a}"
		. "h1{font-size:1.6em;line-height:1.25;margin:0 0 .2em}"
		. "h2{font-size:1.3em;margin:1.4em 0 .4em}"
		. "h3{font-size:1.12em;margin:1.2em 0 .3em}"
		. "p{margin:0 0 .8em;text-align:justify}"
		. "ul,ol{margin:0 0 1em;padding-left:1.3em}li{margin-bottom:.35em}"
		. "table{width:100%;border-collapse:collapse;margin:0 0 1em;font-size:.92em}"
		. "th,td{border:1px solid #d8dee8;padding:.4em .55em;text-align:left}"
		. "th{background:#f3f6fb}"
		. "blockquote{margin:0 0 1em;padding-left:1em;border-left:3px solid #1e5eff;color:#5b6470;font-style:italic}"
		. "a{color:#1e5eff}"
		. ".kicker{font-size:.75em;letter-spacing:.16em;text-transform:uppercase;color:#5b6470}"
		. ".chno{font-size:.75em;letter-spacing:.14em;text-transform:uppercase;color:#1e5eff;font-weight:bold;margin-bottom:.3em}"
		. ".meta{font-size:.82em;color:#5b6470;border-bottom:1px solid #d8dee8;padding-bottom:.7em;margin-bottom:1.2em}"
		. ".cover{text-align:center;margin-top:22%}"
		. ".ewt-ebook-answerkey{border:1px solid #d8dee8;border-left:3px solid #1e5eff;background:#f8fafc;padding:.7em .9em;margin:1.2em 0}"
		. ".ewt-ebook-answerkey h3{margin-top:0}"
		. ".src{margin-top:1.6em;padding-top:.6em;border-top:1px solid #d8dee8;font-size:.72em;color:#5b6470;word-break:break-all}";
}

function ewt_ebook_epub_cover( $book ) {
	$date = date_i18n( 'j F Y', strtotime( $book['created'] ) );
	$body = '<div class="cover">'
		. '<p class="kicker">Kişiye Özel Çalışma Kitabı</p>'
		. '<h1>' . ewt_ebook_xml( $book['title'] ) . '</h1>'
		. '<p>' . ewt_ebook_xml( count( $book['chapters'] ) . ' konu • ' . $date ) . '</p>'
		. '<p><small>Hazırlayan: <a href="' . ewt_ebook_xml( $book['site_url'] ) . '">' . ewt_ebook_xml( $book['site'] ) . '</a></small></p>'
		. '</div>';

	return ewt_ebook_xhtml_doc( $book['title'], $body );
}

function ewt_ebook_epub_chapter( $ch, $index ) {
	$meta = array_filter( array( $ch['type_label'], implode( ', ', $ch['levels'] ) ) );

	$body = '<div class="chno">' . ewt_ebook_xml( 'Bölüm ' . $index ) . '</div>'
		. '<h1>' . ewt_ebook_xml( $ch['title'] ) . '</h1>'
		. ( $meta ? '<div class="meta">' . ewt_ebook_xml( implode( ' • ', $meta ) ) . '</div>' : '' )
		. ewt_ebook_html_to_xhtml( $ch['html'] )
		. '<div class="src">Kaynak: ' . ewt_ebook_xml( $ch['permalink'] ) . '</div>';

	return ewt_ebook_xhtml_doc( $ch['title'], $body );
}

function ewt_ebook_xhtml_doc( $title, $body ) {
	return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<!DOCTYPE html>' . "\n"
		. '<html xmlns="http://www.w3.org/1999/xhtml" lang="tr" xml:lang="tr">'
		. '<head><meta charset="utf-8"/><title>' . ewt_ebook_xml( $title ) . '</title>'
		. '<link rel="stylesheet" type="text/css" href="style.css"/></head>'
		. '<body>' . $body . '</body></html>';
}

/**
 * WordPress HTML'ini EPUB'ın istediği katı XHTML'e çevirir.
 * Görseller çıkarılır (uzak URL'ler çevrimdışı okumada kırık görünür).
 */
function ewt_ebook_html_to_xhtml( $html ) {
	// Görselleri ve figure/figcaption yapısını at
	$html = preg_replace( '#<figure\b.*?</figure>#is', '', $html );
	$html = preg_replace( '#<img\b[^>]*/?>#i', '', $html );

	if ( ! class_exists( 'DOMDocument' ) ) {
		// Son çare: etiketleri sadeleştir
		return '<p>' . ewt_ebook_xml( wp_strip_all_tags( $html ) ) . '</p>';
	}

	$prev = libxml_use_internal_errors( true );

	$dom = new DOMDocument( '1.0', 'UTF-8' );
	$ok  = $dom->loadHTML(
		'<?xml encoding="UTF-8"?><html><body><div id="ewt-root">' . $html . '</div></body></html>',
		LIBXML_NOERROR | LIBXML_NOWARNING
	);

	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	if ( ! $ok ) {
		return '<p>' . ewt_ebook_xml( wp_strip_all_tags( $html ) ) . '</p>';
	}

	$root = $dom->getElementById( 'ewt-root' );
	if ( ! $root ) {
		return '<p>' . ewt_ebook_xml( wp_strip_all_tags( $html ) ) . '</p>';
	}

	$out = '';
	foreach ( $root->childNodes as $node ) {
		$out .= $dom->saveXML( $node );
	}

	// XHTML'de geçersiz olan HTML5 named entity'leri temizle
	$out = str_replace( array( '&nbsp;', '&ndash;', '&mdash;', '&hellip;', '&rsquo;', '&lsquo;', '&ldquo;', '&rdquo;' ),
		array( '&#160;', '&#8211;', '&#8212;', '&#8230;', '&#8217;', '&#8216;', '&#8220;', '&#8221;' ), $out );

	return $out;
}

/* -------------------------------------------------
 * E-POSTA KAYITLARI (lead) — özel tablo
 * ------------------------------------------------- */
function ewt_ebook_leads_table() {
	global $wpdb;
	return $wpdb->prefix . 'ewt_ebook_leads';
}

function ewt_ebook_maybe_create_table() {
	if ( get_option( 'ewt_ebook_db_version' ) === '1.0' ) return;

	global $wpdb;
	$table   = ewt_ebook_leads_table();
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL DEFAULT '',
		book_title text NOT NULL,
		post_count smallint(5) unsigned NOT NULL DEFAULT 0,
		format varchar(10) NOT NULL DEFAULT '',
		ip varchar(45) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY email (email),
		KEY created_at (created_at)
	) $charset;" );

	update_option( 'ewt_ebook_db_version', '1.0' );
}
add_action( 'after_switch_theme', 'ewt_ebook_maybe_create_table' );
add_action( 'admin_init', 'ewt_ebook_maybe_create_table' );

function ewt_ebook_save_lead( $email, $book_title, $count, $format ) {
	global $wpdb;

	// Aynı e-posta son 10 dakikada kaydedildiyse tekrar yazma (çift tıklama koruması).
	$recent = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . ewt_ebook_leads_table() . " WHERE email = %s AND created_at > %s LIMIT 1",
		$email,
		gmdate( 'Y-m-d H:i:s', time() - 600 )
	) );
	if ( $recent ) return;

	$wpdb->insert( ewt_ebook_leads_table(), array(
		'email'      => $email,
		'book_title' => $book_title,
		'post_count' => intval( $count ),
		'format'     => $format,
		'ip'         => ewt_ebook_client_ip(),
		'created_at' => current_time( 'mysql' ),
	), array( '%s', '%s', '%d', '%s', '%s', '%s' ) );
}

/**
 * Hangi konuların e-kitaba en çok eklendiğini sayar (içerik stratejisi için).
 */
function ewt_ebook_bump_stats( $chapters, $format ) {
	$stats = get_option( 'ewt_ebook_stats', array( 'total' => 0, 'pdf' => 0, 'epub' => 0, 'posts' => array() ) );

	$stats['total'] = intval( $stats['total'] ) + 1;
	$stats[ $format ] = intval( isset( $stats[ $format ] ) ? $stats[ $format ] : 0 ) + 1;

	foreach ( $chapters as $ch ) {
		$id = $ch['id'];
		$stats['posts'][ $id ] = intval( isset( $stats['posts'][ $id ] ) ? $stats['posts'][ $id ] : 0 ) + 1;
	}

	// Listeyi kontrol altında tut: en çok seçilen 200 yazı saklanır.
	if ( count( $stats['posts'] ) > 200 ) {
		arsort( $stats['posts'] );
		$stats['posts'] = array_slice( $stats['posts'], 0, 200, true );
	}

	update_option( 'ewt_ebook_stats', $stats, false );
}

/* -------------------------------------------------
 * ADMIN: E-KİTAP KAYITLARI SAYFASI
 * ------------------------------------------------- */
function ewt_ebook_admin_menu() {
	add_theme_page(
		'E-Kitap Kayıtları',
		'E-Kitap Kayıtları',
		'manage_options',
		'ewt-ebook-leads',
		'ewt_ebook_leads_page_html'
	);
}
add_action( 'admin_menu', 'ewt_ebook_admin_menu' );

function ewt_ebook_leads_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	global $wpdb;
	$table = ewt_ebook_leads_table();

	$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	$uniq   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM $table" );
	$rows   = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC LIMIT 200" );
	$stats  = get_option( 'ewt_ebook_stats', array( 'total' => 0, 'pdf' => 0, 'epub' => 0, 'posts' => array() ) );

	$top = isset( $stats['posts'] ) ? $stats['posts'] : array();
	arsort( $top );
	$top = array_slice( $top, 0, 15, true );
	?>
	<div class="wrap">
		<h1>E-Kitap Oluşturucu — Kayıtlar</h1>

		<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0;">
			<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px 22px;min-width:150px;">
				<div style="font-size:26px;font-weight:700;"><?php echo esc_html( intval( $stats['total'] ) ); ?></div>
				<div style="color:#646970;">Toplam e-kitap</div>
			</div>
			<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px 22px;min-width:150px;">
				<div style="font-size:26px;font-weight:700;"><?php echo esc_html( intval( isset( $stats['pdf'] ) ? $stats['pdf'] : 0 ) ); ?> / <?php echo esc_html( intval( isset( $stats['epub'] ) ? $stats['epub'] : 0 ) ); ?></div>
				<div style="color:#646970;">PDF / EPUB</div>
			</div>
			<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px 22px;min-width:150px;">
				<div style="font-size:26px;font-weight:700;"><?php echo esc_html( $uniq ); ?></div>
				<div style="color:#646970;">Tekil e-posta</div>
			</div>
		</div>

		<h2>E-posta Listesi <span style="font-weight:400;color:#646970;">(<?php echo esc_html( $total ); ?> kayıt, son 200 gösteriliyor)</span></h2>

		<p>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ewt_ebook_export_leads' ), 'ewt_ebook_export' ) ); ?>" class="button button-primary">CSV Olarak İndir</a>
		</p>

		<table class="widefat striped">
			<thead><tr><th>E-posta</th><th>Kitap</th><th>Konu</th><th>Format</th><th>Tarih</th></tr></thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="5">Henüz kayıt yok.</td></tr>
			<?php else : foreach ( $rows as $r ) : ?>
				<tr>
					<td><a href="mailto:<?php echo esc_attr( $r->email ); ?>"><?php echo esc_html( $r->email ); ?></a></td>
					<td><?php echo esc_html( $r->book_title ); ?></td>
					<td><?php echo esc_html( $r->post_count ); ?></td>
					<td><?php echo esc_html( strtoupper( $r->format ) ); ?></td>
					<td><?php echo esc_html( $r->created_at ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>

		<h2 style="margin-top:32px;">En Çok Kitaba Eklenen Konular</h2>
		<p class="description">Bu liste, ziyaretçilerin gerçekten değerli bulduğu içerikleri gösterir — yeni içerik ve iç link stratejisi için kullanın.</p>
		<table class="widefat striped" style="max-width:720px;">
			<thead><tr><th>Konu</th><th style="width:120px;">Eklenme</th></tr></thead>
			<tbody>
			<?php if ( empty( $top ) ) : ?>
				<tr><td colspan="2">Henüz veri yok.</td></tr>
			<?php else : foreach ( $top as $pid => $cnt ) :
				$t = get_the_title( $pid );
				if ( ! $t ) continue; ?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( $t ); ?></a></td>
					<td><?php echo esc_html( $cnt ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * CSV dışa aktarma
 */
function ewt_ebook_export_leads() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetkiniz yok.' );
	check_admin_referer( 'ewt_ebook_export' );

	global $wpdb;
	$rows = $wpdb->get_results( "SELECT email, book_title, post_count, format, created_at FROM " . ewt_ebook_leads_table() . " ORDER BY created_at DESC", ARRAY_A );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=ewt-ekitap-kayitlari-' . date_i18n( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // Excel'de Türkçe karakterler için BOM
	fputcsv( $out, array( 'E-posta', 'Kitap Adı', 'Konu Sayısı', 'Format', 'Tarih' ) );
	foreach ( (array) $rows as $r ) {
		fputcsv( $out, $r );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_ewt_ebook_export_leads', 'ewt_ebook_export_leads' );

/* -------------------------------------------------
 * TEMİZLİK CRON'U — 24 saatten eski EPUB dosyalarını siler
 * ------------------------------------------------- */
function ewt_ebook_schedule_cleanup() {
	if ( ! wp_next_scheduled( 'ewt_ebook_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ewt_ebook_cleanup' );
	}
}
add_action( 'init', 'ewt_ebook_schedule_cleanup' );

function ewt_ebook_do_cleanup() {
	$paths = ewt_ebook_upload_dir();
	$files = glob( trailingslashit( $paths['dir'] ) . '*.epub' );
	if ( ! $files ) return;

	$now = time();
	foreach ( $files as $f ) {
		if ( ( $now - filemtime( $f ) ) > EWT_EBOOK_FILE_TTL ) {
			@unlink( $f );
		}
	}
}
add_action( 'ewt_ebook_cleanup', 'ewt_ebook_do_cleanup' );

/**
 * Tema devre dışı bırakılırsa cron'u temizle.
 */
function ewt_ebook_unschedule_cleanup() {
	$ts = wp_next_scheduled( 'ewt_ebook_cleanup' );
	if ( $ts ) wp_unschedule_event( $ts, 'ewt_ebook_cleanup' );
}
add_action( 'switch_theme', 'ewt_ebook_unschedule_cleanup' );
