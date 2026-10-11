<?php
/**
 * İÇ VE DIŞ BAĞLANTI YÖNETİCİSİ (Link Juice Motoru)
 *
 * AMAÇ
 * - Her içeriğe en az bir İÇ bağlantı (sitenizdeki başka alakalı içeriğe) eklenmesini sağlar.
 * - Yüksek otoriteli (Cambridge, Oxford, British Council, ETS, Purdue OWL, ÖSYM vb.) DIŞ
 *   kaynaklara alakalı çapa metinler üzerinden bağlantı verir.
 * - HER içeriğin, doğal bir çapa metin üzerinden https://english.web.tr/ingilizce-ozel-ders/
 *   sayfasına (yani "özel ders" sayfanıza) bağlantı vermesini garanti eder — böylece bu sayfaya
 *   site genelinde sürekli link juice akar. Doğal bir eşleşme bulunamazsa, içeriğin sonuna kısa
 *   ve doğal bir "daha fazla bilgi için özel ders" cümlesi + linki otomatik eklenir (ayarlardan
 *   kapatılabilir).
 * - Eklenen TÜM bağlantılar normal HTML <a> etiketi olarak post_content'e (veya Elementor
 *   kullanılıyorsa _elementor_data JSON'ına) yazılır. Bu yüzden Gutenberg'de veya Elementor'da
 *   açtığınızda tamamen sıradan, elle düzenlenebilir/silinebilir metin/link olarak görünür —
 *   hiçbir özel shortcode veya blok kullanılmaz.
 *
 * NASIL ÇALIŞIR (özet)
 * 1) {$wpdb->prefix}ewt_link_rules tablosunda siz "kural" tanımlarsınız: bir grup çapa metin
 *    varyasyonu + hedef URL + (opsiyonel) hangi CPT'lerde geçerli olacağı + nofollow/yeni sekme.
 *    Kurulumda "İngilizce Özel Ders" kuralı ve 10 adet güvenilir dış kaynak kuralı otomatik
 *    tohumlanır; Bağlantı Yöneticisi > Bağlantı Kuralları sayfasından tamamen düzenlenebilir/
 *    silinebilir/eklenebilir.
 * 2) Site içi arama motorunun indeks tablosunu (wp_ewt_search_index — zaten var olan altyapı)
 *    kullanarak, KONU/SEVİYE/İÇERİK TÜRÜNE göre alakalı başka içerikleri otomatik iç bağlantı
 *    adayı olarak bulur. Hedef içeriğin TAM başlığının birebir geçmesi şart değildir: odak
 *    anahtar kelime, ya da başlıktan seviye/format dolgusu (a1/b2/pdf/test/quiz vb.) kırpılmış
 *    "çekirdek ifade" de çapa metni olarak denenir — böylece örn. "B2 Seviyesi İngilizce
 *    Kelimeler" yazan bir içerik, sadece "ingilizce kelimeler" ifadesi geçen A1 seviyesindeki
 *    bir yazıya da doğal olarak bağlanabilir. Aday havuzu önce aynı CPT ve/veya aynı Seviye
 *    taksonomi terimine sahip içeriklerle daraltılır (site genelinde rastgele değil, GERÇEKTEN
 *    alakalı çapraz linkleme).
 * 3) Bir içerik işlendiğinde: önce "özel ders" kuralı, sonra elle tanımlı iç bağlantılar, sonra
 *    otomatik keşfedilen iç bağlantılar, sonra dış otorite bağlantıları — içerikte GEÇEN ilk
 *    uygun çapa metnini bulup <a> ile sarar. Türkçe büyük/küçük harf (İ/I/ı/i) farkları da
 *    hesaba katılır.
 * 4) Değişiklik, güvenli bir DOM ayrıştırıcı ile yapılır: sadece düz metin parçaları sarmalanır;
 *    mevcut linkler, başlıklar (h1-h6), kod blokları asla dokunulmaz. İşlem sonrası basit bir
 *    güvenlik kontrolü (metin uzunluğu karşılaştırması) yapılır; bir şeyler ters giderse o
 *    içerik DOKUNULMADAN bırakılır.
 * 5) Hem normal yazı içeriği (post_content / Gutenberg) HEM DE Elementor ile oluşturulmuş
 *    sayfalar/yazılar (_elementor_data içindeki Metin Editörü widget'ları) desteklenir.
 *
 * KONTROL SİZDE
 * - Yazı düzenleme ekranındaki "🔗 İç/Dış Bağlantı Önerileri" kutusundan, ÖNCE analiz edip
 *   önerileri görebilir, istediklerinizi seçip SONRA içeriğe ekleyebilirsiniz.
 * - "Toplu Uygula" sayfasından tüm siteye (veya seçtiğiniz içerik türlerine) tek seferde
 *   uygulayabilir, önce "Önizleme" modunda ne olacağını görebilirsiniz.
 * - "Yayınlandığında otomatik uygula" ayarı varsayılan KAPALIDIR; isterseniz açabilirsiniz.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EWT_LINK_DB_VERSION', '1.0.0' );
define( 'EWT_LINK_DEFAULT_OZEL_DERS_URL', 'https://english.web.tr/ingilizce-ozel-ders/' );

/* =========================================================
 * 1) TABLO OLUŞTURMA / SÜRÜM YÖNETİMİ + VARSAYILAN KURAL TOHUMLAMA
 * ========================================================= */

function ewt_link_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'ewt_link_rules';
}

function ewt_link_maybe_upgrade_db() {
	if ( get_option( 'ewt_link_db_version' ) === EWT_LINK_DB_VERSION ) return;

	global $wpdb;
	$table           = ewt_link_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		rule_type VARCHAR(20) NOT NULL DEFAULT 'internal',
		label VARCHAR(190) NOT NULL DEFAULT '',
		anchors TEXT NOT NULL,
		target_url VARCHAR(500) NOT NULL DEFAULT '',
		cpt_scope VARCHAR(255) NOT NULL DEFAULT '',
		nofollow TINYINT(1) NOT NULL DEFAULT 0,
		new_tab TINYINT(1) NOT NULL DEFAULT 1,
		max_per_post SMALLINT NOT NULL DEFAULT 1,
		priority SMALLINT NOT NULL DEFAULT 10,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
		updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
		PRIMARY KEY  (id),
		KEY rule_type (rule_type),
		KEY is_active (is_active)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'ewt_link_db_version', EWT_LINK_DB_VERSION );
	ewt_link_seed_default_rules();
}
add_action( 'init', 'ewt_link_maybe_upgrade_db', 5 );

/**
 * İlk kurulumda: "özel ders" kuralı + 10 güvenilir dış kaynak kuralı otomatik eklenir.
 * Sadece BİR KEZ çalışır (ewt_link_rules_seeded bayrağı ile). Sonrasında bu kurallar da
 * dahil olmak üzere HER ŞEY, Bağlantı Kuralları sayfasından tamamen düzenlenebilir/silinebilir.
 */
function ewt_link_seed_default_rules() {
	if ( get_option( 'ewt_link_rules_seeded' ) === '1' ) return;

	global $wpdb;
	$table = ewt_link_table_name();
	$now   = current_time( 'mysql' );

	$rows = array();

	// Zorunlu: özel ders sayfasına doğal çapa metinlerle link juice
	$rows[] = array(
		'rule_type'    => 'ozel_ders',
		'label'        => 'İngilizce Özel Ders (Ana Yönlendirme)',
		'anchors'      => "ingilizce özel ders\nözel ingilizce dersi\nbirebir ingilizce dersi\nonline ingilizce özel ders\ningilizce özel ders almak\ningilizce öğretmeninden özel ders",
		'target_url'   => EWT_LINK_DEFAULT_OZEL_DERS_URL,
		'cpt_scope'    => '',
		'nofollow'     => 0,
		'new_tab'      => 0,
		'max_per_post' => 1,
		'priority'     => 5,
	);

	// Güvenilir, yüksek otoriteli dış eğitim kaynakları (PR/DR canlı sorgulanmıyor —
	// bunlar herkesçe bilinen köklü eğitim otoriteleridir; istediğiniz gibi düzenleyin/silin/ekleyin)
	$ext = array(
		array( 'Cambridge Dictionary', "cambridge dictionary\ncambridge sözlük\ncambridge online sözlük", 'https://dictionary.cambridge.org/', 'ewt_vocabulary,ewt_grammar,ewt_primary,ewt_secondary,ewt_highschool,ewt_yds,ewt_ydt,ewt_toefl,ewt_gre,ewt_gmat,post', 20 ),
		array( "Oxford Learner's Dictionaries", "oxford learner's dictionaries\noxford sözlük\noxford dictionaries", 'https://www.oxfordlearnersdictionaries.com/', 'ewt_vocabulary,ewt_grammar,ewt_primary,ewt_secondary,ewt_highschool,post', 21 ),
		array( 'British Council LearnEnglish', "british council learnenglish\nbritish council", 'https://learnenglish.britishcouncil.org/', 'ewt_listening,ewt_speaking,ewt_grammar,ewt_writing,ewt_reading,post', 22 ),
		array( 'BBC Learning English', 'bbc learning english', 'https://www.bbc.co.uk/learningenglish', 'ewt_listening,ewt_speaking', 23 ),
		array( 'Purdue OWL', 'purdue owl', 'https://owl.purdue.edu/', 'ewt_grammar,ewt_writing,ewt_gre,ewt_gmat,post', 24 ),
		array( 'ETS - Resmi TOEFL', "ets toefl\ntoefl resmi sitesi", 'https://www.ets.org/toefl.html', 'ewt_toefl', 25 ),
		array( 'ETS - Resmi GRE', "ets gre\ngre resmi sitesi", 'https://www.ets.org/gre.html', 'ewt_gre', 26 ),
		array( 'ÖSYM Resmi Sitesi', "ösym\nösym resmi sitesi", 'https://www.osym.gov.tr/', 'ewt_yds,ewt_ydt', 27 ),
		array( 'mba.com (Resmi GMAT)', "mba.com\ngmac", 'https://www.mba.com/', 'ewt_gmat', 28 ),
		array( 'Merriam-Webster', "merriam-webster\nmerriam webster sözlük", 'https://www.merriam-webster.com/', 'post', 29 ),
	);
	foreach ( $ext as $e ) {
		$rows[] = array(
			'rule_type'    => 'external',
			'label'        => $e[0],
			'anchors'      => $e[1],
			'target_url'   => $e[2],
			'cpt_scope'    => $e[3],
			'nofollow'     => 1,
			'new_tab'      => 1,
			'max_per_post' => 1,
			'priority'     => $e[4],
		);
	}

	foreach ( $rows as $r ) {
		$data = array_merge( $r, array( 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( $table, $data, array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ) );
	}

	update_option( 'ewt_link_rules_seeded', '1' );
}

/* =========================================================
 * 2) YARDIMCI FONKSİYONLAR: AYARLAR, TÜRKÇE EŞLEŞTİRME, DOM, GÜVENLİK
 * ========================================================= */

function ewt_link_all_supported_post_types() {
	$types   = array_keys( ewt_cpt_config() );
	$types[] = 'post';
	$types[] = 'page';
	return $types;
}

function ewt_link_default_cta_template() {
	return 'Bu konuda daha fazla pratik yapmak ve birebir geri bildirim almak isterseniz {anchor} seçeneğini değerlendirebilirsiniz.';
}

function ewt_link_get_settings() {
	return array(
		'max_internal'          => (int) get_option( 'ewt_link_max_internal', 2 ),
		'max_external'          => (int) get_option( 'ewt_link_max_external', 2 ),
		'force_ozel_ders'       => get_option( 'ewt_link_force_ozel_ders', '1' ) === '1',
		'ozel_ders_url'         => get_option( 'ewt_link_ozel_ders_url', EWT_LINK_DEFAULT_OZEL_DERS_URL ),
		'cta_template'          => get_option( 'ewt_link_cta_template', ewt_link_default_cta_template() ),
		'auto_internal_enabled' => get_option( 'ewt_link_auto_internal_enabled', '1' ) === '1',
		'auto_on_publish'       => get_option( 'ewt_link_auto_on_publish', '0' ) === '1',
		'min_auto_title_len'    => (int) get_option( 'ewt_link_min_auto_title_len', 8 ),
	);
}

/**
 * Bir çapa metnini, Türkçe I/İ/ı/i farklarını da doğru eşleyen, kelime sınırlarına
 * duyarlı (Unicode) bir regex kalıbına çevirir. PCRE'nin standart /i modu Türkçe'deki
 * noktalı/noktasız I harflerini doğru katlamadığı için bu harfler özel olarak
 * karakter sınıfına çevrilir; diğer tüm harfler /iu modu ile normal katlanır.
 */
function ewt_link_build_pattern( $phrase ) {
	$phrase = trim( (string) $phrase );
	if ( $phrase === '' ) return '';

	$chars = preg_split( '//u', $phrase, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $chars ) return '';

	$out = '';
	foreach ( $chars as $ch ) {
		if ( in_array( $ch, array( 'I', 'ı', 'İ', 'i' ), true ) ) {
			$out .= '[Iıİi]';
		} else {
			$out .= preg_quote( $ch, '/' );
		}
	}
	return '/(?<![\p{L}\p{N}_])' . $out . '(?![\p{L}\p{N}_])/iu';
}

function ewt_link_parse_anchors( $anchors_text ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $anchors_text );
	$out   = array();
	foreach ( (array) $lines as $l ) {
		$l = trim( $l );
		if ( $l !== '' ) $out[] = $l;
	}
	return $out;
}

function ewt_link_rule_matches_cpt( $scope, $post_type ) {
	$scope = trim( (string) $scope );
	if ( $scope === '' ) return true;
	$list = array_map( 'trim', explode( ',', $scope ) );
	return in_array( $post_type, $list, true );
}

/**
 * Ham HTML'i, UTF-8/Türkçe karakterleri ve Gutenberg blok yorumlarını (<!-- wp:... -->)
 * bozmadan bir DOMDocument'e yükler. LIBXML_HTML_NOIMPLIED + NODEFDTD, DOMDocument'ın
 * otomatik <html><body> sarmalayıcısı eklemesini engeller; '<?xml encoding="UTF-8">' hilesi
 * ise libxml'in HTML5/Türkçe karakterleri hatalı algılamasını (mojibake) önler.
 */
function ewt_link_html_to_dom( $html ) {
	$dom  = new DOMDocument( '1.0', 'UTF-8' );
	$prev = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	foreach ( iterator_to_array( $dom->childNodes ) as $node ) {
		if ( $node->nodeType === XML_PI_NODE ) $dom->removeChild( $node );
	}
	return $dom;
}

function ewt_link_dom_to_html( DOMDocument $dom ) {
	$parts = array();
	foreach ( $dom->childNodes as $node ) $parts[] = $dom->saveHTML( $node );
	return implode( "\n", $parts );
}

/**
 * İşlem SONRASI görünür metin uzunluğunun, işlem ÖNCESİ ile makul bir oranda kalıp
 * kalmadığını kontrol eder. DOM ayrıştırma bir şekilde içeriği bozar/kısaltırsa (beklenmez
 * ama olabilir), bu fonksiyon false döner ve o parça DOKUNULMADAN bırakılır — yayınlanmış,
 * Google'da sıralanan sayfalarınız asla riske atılmaz.
 */
function ewt_link_content_safety_ok( $original_html, $new_html ) {
	$orig_plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $original_html ) ) );
	$new_plain  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $new_html ) ) );

	$orig_len = function_exists( 'mb_strlen' ) ? mb_strlen( $orig_plain ) : strlen( $orig_plain );
	$new_len  = function_exists( 'mb_strlen' ) ? mb_strlen( $new_plain ) : strlen( $new_plain );

	if ( $orig_len === 0 ) return true;
	$ratio = $new_len / $orig_len;
	return ( $ratio >= 0.95 && $ratio <= 3.0 );
}

/* =========================================================
 * 3) KURAL KAYNAKLARI: DB KURALLARI + OTOMATİK İÇ BAĞLANTI KEŞFİ
 * ========================================================= */

function ewt_link_get_db_rules( $type, $post_type ) {
	global $wpdb;
	$table = ewt_link_table_name();
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE rule_type = %s AND is_active = 1 ORDER BY priority ASC", $type ) );
	if ( ! $rows ) return array();

	$out = array();
	foreach ( $rows as $r ) {
		if ( ewt_link_rule_matches_cpt( $r->cpt_scope, $post_type ) ) $out[] = $r;
	}
	return $out;
}

function ewt_link_db_rule_to_queue_item( $r ) {
	$rel_parts = array();
	if ( ! empty( $r->nofollow ) ) $rel_parts[] = 'nofollow';
	if ( ! empty( $r->new_tab ) )  $rel_parts[] = 'noopener';

	return array(
		'key'          => 'rule-' . $r->id,
		'type'         => $r->rule_type, // 'internal' | 'external' | 'ozel_ders'
		'label'        => $r->label,
		'anchors'      => ewt_link_parse_anchors( $r->anchors ),
		'url'          => $r->target_url,
		'href'         => esc_url( $r->target_url ),
		'rel'          => implode( ' ', $rel_parts ),
		'target_attr'  => ! empty( $r->new_tab ) ? '_blank' : '',
		'priority'     => (int) $r->priority,
	);
}

/**
 * Bir yazının kendi "Seviye" taksonomi terimlerini (A1, A2, Başlangıç, İleri vb. — isim olarak)
 * döndürür. Site içi arama indeksindeki 'seviye' kolonuyla aynı formatta (terim adı) olması
 * için doğrudan taksonomiden okunur (indeks güncel olmasa bile doğru sonuç verir).
 */
function ewt_link_get_own_seviye_names( $post_id, $post_type ) {
	$tax = $post_type . '_seviye';
	if ( ! taxonomy_exists( $tax ) ) return array();
	$terms = get_the_terms( $post_id, $tax );
	if ( ! $terms || is_wp_error( $terms ) ) return array();
	return wp_list_pluck( $terms, 'name' );
}

/**
 * Tek bir kelime için, Türkçe İ/I/ı/i farklarını hesaba katan bir karakter-sınıfı regex parçası üretir
 * (ewt_link_build_pattern ile aynı mantık, ama tam ifade değil tek kelime için — kırpma işleminde kullanılır).
 */
function ewt_link_build_word_char_pattern( $word ) {
	$chars = preg_split( '//u', (string) $word, -1, PREG_SPLIT_NO_EMPTY );
	$out   = '';
	foreach ( (array) $chars as $ch ) {
		$out .= in_array( $ch, array( 'I', 'ı', 'İ', 'i' ), true ) ? '[Iıİi]' : preg_quote( $ch, '/' );
	}
	return $out;
}

/**
 * Bir başlıktan/çapa adayından "seviye/format dolgu kelimelerini" (a1, b2, seviyesi, pdf, test,
 * quiz, "9. sınıf" vb.) baştan ve sondan kırparak, ASIL KONUYU temsil eden daha kısa ve daha genel
 * bir "çekirdek ifade" çıkarır. Örn: "A1 Seviyesi İngilizce Kelimeler PDF" -> "İngilizce Kelimeler".
 * Bu sayede farklı seviye/format etiketine sahip ama AYNI KONUYU işleyen içerikler (örn. A1 ve B2
 * kelime listeleri, ya da aynı konunun test/pdf/quiz varyasyonları) birbirine doğal olarak
 * bağlanabilir — çapa metninin, hedef içeriğin TAM başlığıyla birebir aynı olması şartı aranmaz.
 */
function ewt_link_strip_seviye_filler( $phrase ) {
	$phrase = trim( (string) $phrase );
	if ( $phrase === '' ) return '';

	static $fillers = array(
		'a1', 'a2', 'b1', 'b2', 'c1', 'c2', 'ydt', 'yds', 'toefl', 'gre', 'gmat',
		'seviyesi', 'seviyeli', 'seviye', 'pdf', 'test', 'testi', 'testler', 'testleri',
		'quiz', 'sınav', 'sınavı', 'sınavları', 'alıştırma', 'alıştırmalar', 'alıştırmaları',
		'örnek', 'örnekler', 'örnekleri', 'çöz', 'çözme', 'çözümlü',
	);

	$guard = 0;
	do {
		$changed = false;
		$guard++;

		// "9 sınıf" / "9. sınıf" / "10.sınıf" gibi sınıf kalıplarını baştan/sondan sil
		$new = preg_replace( '/^\d{1,2}\.?\s*s[ıi]n[ıi]f\s+/iu', '', $phrase );
		if ( $new !== null && $new !== $phrase ) { $phrase = trim( $new ); $changed = true; continue; }
		$new = preg_replace( '/\s+\d{1,2}\.?\s*s[ıi]n[ıi]f$/iu', '', $phrase );
		if ( $new !== null && $new !== $phrase ) { $phrase = trim( $new ); $changed = true; continue; }

		foreach ( $fillers as $f ) {
			$wp = ewt_link_build_word_char_pattern( $f );

			$new = preg_replace( '/^' . $wp . '\s+/iu', '', $phrase, 1 );
			if ( $new !== null && $new !== $phrase ) { $phrase = trim( $new ); $changed = true; break; }

			$new = preg_replace( '/\s+' . $wp . '$/iu', '', $phrase, 1 );
			if ( $new !== null && $new !== $phrase ) { $phrase = trim( $new ); $changed = true; break; }
		}
	} while ( $changed && $guard < 8 );

	return trim( $phrase );
}

/**
 * Bir aday içerik için denenecek çapa metin varyasyonlarını sırasıyla üretir:
 * 1) Odak anahtar kelime (varsa, SEO meta kutusundan — _ewt_focus_keyword) — genelde en doğal/kısa ifade
 * 2) "Çekirdek ifade" (başlıktan seviye/format dolgusu kırpılmış hali) — konu bazlı eşleşmeyi sağlar
 * 3) Tam başlık (eski davranış, birebir başlık geçen içerikler için hâlâ çalışır)
 */
function ewt_link_extract_anchor_variants_for_candidate( $title, $focus_keyword ) {
	$variants = array();

	$fk = trim( (string) $focus_keyword );
	if ( $fk !== '' && mb_strlen( $fk ) >= 6 ) $variants[] = $fk;

	$title = trim( (string) $title );
	$core  = ewt_link_strip_seviye_filler( $title );
	$words = $core === '' ? array() : preg_split( '/\s+/u', $core, -1, PREG_SPLIT_NO_EMPTY );
	// Tek kelimeye inen çok genel ifadeleri (örn. sadece "İngilizce") çapa olarak KULLANMA —
	// gürültülü/alakasız eşleşmelere yol açar.
	if ( $core !== '' && $core !== $title && count( $words ) >= 2 && mb_strlen( $core ) >= 8 ) {
		$variants[] = $core;
	}

	if ( $title !== '' ) $variants[] = $title;

	// Tekilleştir (büyük/küçük harften bağımsız)
	$seen = array();
	$out  = array();
	foreach ( $variants as $v ) {
		$k = mb_strtolower( $v );
		if ( isset( $seen[ $k ] ) ) continue;
		$seen[ $k ] = true;
		$out[]      = $v;
	}
	return $out;
}

/**
 * Site içi arama indeks tablosunu (wp_ewt_search_index — zaten mevcut altyapı) kullanarak,
 * KONU/SEVİYE/İÇERİK TÜRÜNE göre alakalı içerikleri otomatik iç bağlantı adayı olarak bulur.
 *
 * Eski davranıştan farkı: artık hedef içeriğin TAM başlığının, kelimesi kelimesine mevcut
 * içerikte geçmesi ŞART DEĞİL. Bunun yerine:
 *  - Önce aday havuzu, AYNI İÇERİK TÜRÜNDEN (CPT) ve/veya AYNI SEVİYE'den (Seviye taksonomi
 *    terimi ortak olan) içeriklerle daraltılır — bu, "alakalı" tanımını büyük ölçüde güçlendirir
 *    (örn. bir A1 Grammar yazısı, diğer A1 Grammar yazılarına VE diğer A1 Vocabulary/Listening
 *    yazılarına aday olarak bakar; alakasız bir C1 GRE yazısına bakmaz).
 *  - Sonra her aday için üç çapa varyasyonu denenir: odak anahtar kelime, seviye/format dolgusu
 *    kırpılmış "çekirdek ifade" ve tam başlık — bunlardan HANGİSİ mevcut içerikte doğal olarak
 *    geçiyorsa o kullanılır. Böylece "A1 Seviyesi İngilizce Kelimeler" başlıklı bir yazı,
 *    başka bir A1 içeriğinde sadece "ingilizce kelimeler" ifadesi geçtiğinde de bağlanabilir.
 */
function ewt_link_get_auto_internal_candidates( $post_id, $plain_text, $settings ) {
	if ( ! function_exists( 'ewt_search_table_name' ) ) return array();

	global $wpdb;
	$table   = ewt_search_table_name();
	$min_len = max( 3, (int) $settings['min_auto_title_len'] );
	$needed  = max( 1, (int) $settings['max_internal'] ) + 3;

	$post       = get_post( $post_id );
	$post_type  = $post ? $post->post_type : '';
	$own_seviye = ewt_link_get_own_seviye_names( $post_id, $post_type );

	// --- Aday havuzu ---
	$pool = array();

	// A) Aynı içerik türü (CPT) — seviyesi/formatı ne olursa olsun en temel alaka grubu
	$rows_a = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, post_type, title, url, seviye FROM {$table}
		 WHERE id != %d AND post_type = %s AND CHAR_LENGTH(title) >= %d
		 ORDER BY id DESC LIMIT 500",
		$post_id, $post_type, $min_len
	) );
	foreach ( (array) $rows_a as $r ) $pool[ (int) $r->id ] = $r;

	// B) Farklı CPT ama AYNI SEVİYE (örn. A1 Vocabulary <-> A1 Grammar <-> A1 Listening) —
	// sadece bu içeriğin bir Seviye ataması varsa anlamlıdır.
	if ( ! empty( $own_seviye ) ) {
		$rows_b = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, post_type, title, url, seviye FROM {$table}
			 WHERE id != %d AND post_type != %s AND seviye != '' AND CHAR_LENGTH(title) >= %d
			 ORDER BY id DESC LIMIT 500",
			$post_id, $post_type, $min_len
		) );
		foreach ( (array) $rows_b as $r ) if ( ! isset( $pool[ (int) $r->id ] ) ) $pool[ (int) $r->id ] = $r;
	}

	if ( empty( $pool ) ) return array();

	// Odak anahtar kelimeleri tek sorguda çek (_ewt_focus_keyword — SEO meta kutusu tarafından dolduruluyor)
	$ids    = array_keys( $pool );
	$fk_map = array();
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$fk_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ewt_focus_keyword' AND post_id IN ({$placeholders})",
		$ids
	) );
	foreach ( (array) $fk_rows as $fr ) $fk_map[ (int) $fr->post_id ] = $fr->meta_value;

	// --- Alaka skoru: aynı CPT + aynı seviye > aynı seviye > aynı CPT > diğer ---
	$scored = array();
	foreach ( $pool as $id => $r ) {
		$row_seviye = array_filter( array_map( 'trim', explode( ',', (string) $r->seviye ) ) );
		$overlap    = ! empty( $own_seviye ) && array_intersect( $own_seviye, $row_seviye );
		$same_cpt   = ( $r->post_type === $post_type );

		if ( $same_cpt && $overlap )    $score = 3;
		elseif ( $overlap )             $score = 2;
		elseif ( $same_cpt )            $score = 1;
		else                            $score = 0;

		$scored[] = array( 'row' => $r, 'score' => $score, 'fk' => isset( $fk_map[ $id ] ) ? $fk_map[ $id ] : '' );
	}
	usort( $scored, function ( $a, $b ) { return $b['score'] <=> $a['score']; } );

	// Performans için sadece en alakalı ilk ~150 aday, (biraz daha maliyetli) çapa metni
	// eşleştirme testine sokulur.
	$scored = array_slice( $scored, 0, 150 );

	$out = array();
	foreach ( $scored as $s ) {
		if ( count( $out ) >= $needed ) break;

		$r        = $s['row'];
		$variants = ewt_link_extract_anchor_variants_for_candidate( $r->title, $s['fk'] );

		$matched = array();
		foreach ( $variants as $v ) {
			$pattern = ewt_link_build_pattern( $v );
			if ( $pattern !== '' && preg_match( $pattern, $plain_text ) ) $matched[] = $v;
		}
		if ( empty( $matched ) ) continue;

		$out[] = array(
			'key'         => 'auto-' . md5( $r->url ),
			'type'        => 'internal',
			'label'       => 'Otomatik (konu/seviye eşleşmesi): ' . $r->title,
			'anchors'     => $matched,
			'url'         => $r->url,
			'href'        => esc_url( $r->url ),
			'rel'         => '',
			'target_attr' => '',
			'priority'    => 50,
		);
	}
	return $out;
}

/* =========================================================
 * 4) ÇEKİRDEK: BİR KURALI DOM'A UYGULAMA + FRAGMENT İŞLEME
 * ========================================================= */

/**
 * Verilen çapa metin varyasyonlarından ($anchor_variants) içerikte GEÇEN ilk uygun olanı
 * bulur ve o metin parçasını <a href="$href"> ile sarar. Sadece düz metin düğümleri
 * (mevcut linkler, script/style/code/pre, başlıklar h1-h6 HARİÇ) taranır.
 *
 * @return array|false Başarılıysa array('anchor'=>..,'variant'=>..), yoksa false.
 */
function ewt_link_try_apply_rule_to_dom( DOMDocument $dom, array $anchor_variants, $href, $rel, $target_attr ) {
	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query(
		'//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) '
		. 'and not(ancestor::code) and not(ancestor::pre) and not(ancestor::h1) and not(ancestor::h2) '
		. 'and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6)]'
	);

	foreach ( $nodes as $node ) {
		$text = $node->nodeValue;
		if ( trim( $text ) === '' ) continue;

		foreach ( $anchor_variants as $variant ) {
			$pattern = ewt_link_build_pattern( $variant );
			if ( $pattern === '' ) continue;

			if ( preg_match( $pattern, $text, $m, PREG_OFFSET_CAPTURE ) ) {
				$matched_text = $m[0][0];
				$offset       = $m[0][1];
				$length       = strlen( $matched_text );

				$before = substr( $text, 0, $offset );
				$after  = substr( $text, $offset + $length );

				$doc = $node->ownerDocument;
				$a   = $doc->createElement( 'a' );
				$a->appendChild( $doc->createTextNode( $matched_text ) );
				$a->setAttribute( 'href', $href );
				if ( $rel )         $a->setAttribute( 'rel', $rel );
				if ( $target_attr ) $a->setAttribute( 'target', $target_attr );

				$parent = $node->parentNode;
				$parent->insertBefore( $doc->createTextNode( $before ), $node );
				$parent->insertBefore( $a, $node );
				$parent->insertBefore( $doc->createTextNode( $after ), $node );
				$parent->removeChild( $node );

				return array( 'anchor' => $matched_text, 'variant' => $variant );
			}
		}
	}
	return false;
}

/**
 * Bir kural kuyruğunu TEK bir DOM (tek bir HTML parçası) üzerinde dener. Uygulanan kurallar
 * kuyruktan silinir (bir sonraki parçada tekrar denenmesin diye) ve sayaçlar/başvurulan
 * hedefler güncellenir. $rule_queue, $counts ve $applied_targets referansla geçilir çünkü
 * Elementor'da birden fazla metin widget'ı (=birden fazla DOM) art arda işlenir ve durum
 * hepsi arasında paylaşılmalıdır.
 */
function ewt_link_process_one_fragment_dom( DOMDocument $dom, array &$rule_queue, array &$counts, array $limits, array &$applied_targets, array &$report, $fragment_key ) {
	foreach ( $rule_queue as $idx => $rule ) {
		if ( $rule['type'] === 'internal' && $counts['internal'] >= $limits['internal'] ) continue;
		if ( $rule['type'] === 'external' && $counts['external'] >= $limits['external'] ) continue;
		if ( $rule['type'] === 'ozel_ders' && $counts['ozel_ders'] >= 1 ) continue;

		if ( in_array( $rule['url'], $applied_targets, true ) ) {
			unset( $rule_queue[ $idx ] );
			continue;
		}

		$res = ewt_link_try_apply_rule_to_dom( $dom, $rule['anchors'], $rule['href'], $rule['rel'], $rule['target_attr'] );
		if ( $res ) {
			$counter_key            = $rule['type'] === 'ozel_ders' ? 'ozel_ders' : $rule['type'];
			$counts[ $counter_key ] = ( $counts[ $counter_key ] ?? 0 ) + 1;
			$applied_targets[]      = $rule['url'];

			$report[] = array(
				'key'      => $rule['key'],
				'type'     => $rule['type'],
				'label'    => $rule['label'],
				'anchor'   => $res['anchor'],
				'url'      => $rule['url'],
				'fragment' => $fragment_key,
			);
			unset( $rule_queue[ $idx ] );
		}
	}
}

function ewt_link_fragments_contain_url( array $fragments, $url ) {
	$needle = untrailingslashit( (string) $url );
	if ( $needle === '' ) return false;
	foreach ( $fragments as $html ) {
		if ( stripos( $html, $needle ) !== false ) return true;
	}
	return false;
}

/* =========================================================
 * 5) ELEMENTOR DESTEĞİ (_elementor_data JSON ağacı)
 * ========================================================= */

function ewt_link_is_elementor_post( $post_id ) {
	$mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
	$data = get_post_meta( $post_id, '_elementor_data', true );
	return ( $mode === 'builder' && ! empty( $data ) );
}

function ewt_link_collect_elementor_text_fields( array $elements, array &$fragments, $path_prefix ) {
	foreach ( $elements as $i => $el ) {
		$path = $path_prefix === '' ? (string) $i : $path_prefix . '.' . $i;

		if ( isset( $el['widgetType'] ) && in_array( $el['widgetType'], array( 'text-editor', 'theme-post-content' ), true )
			&& isset( $el['settings']['editor'] ) && is_string( $el['settings']['editor'] ) ) {
			$fragments[ $path . '::editor' ] = $el['settings']['editor'];
		}

		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			ewt_link_collect_elementor_text_fields( $el['elements'], $fragments, $path );
		}
	}
}

function ewt_link_get_elementor_editable_texts( $post_id ) {
	$raw  = get_post_meta( $post_id, '_elementor_data', true );
	$tree = json_decode( $raw, true );
	if ( ! is_array( $tree ) ) $tree = array();

	$fragments = array();
	ewt_link_collect_elementor_text_fields( $tree, $fragments, '' );
	return array( 'tree' => $tree, 'fragments' => $fragments );
}

/**
 * "0.2.1::editor" gibi bir yol anahtarını kullanarak, ağaçtaki (referansla geçilen) ilgili
 * elemanın 'settings' alanına yeni HTML'i yazar.
 */
function ewt_link_write_elementor_fragment( array &$tree, $path_key, $new_html ) {
	$parts = explode( '::', $path_key, 2 );
	$path  = $parts[0];
	$field = isset( $parts[1] ) ? $parts[1] : 'editor';

	$indexes = array_map( 'intval', explode( '.', $path ) );
	$ref     = &$tree;
	$last    = count( $indexes ) - 1;

	foreach ( $indexes as $depth => $idx ) {
		if ( ! isset( $ref[ $idx ] ) ) return false;
		if ( $depth === $last ) {
			if ( ! isset( $ref[ $idx ]['settings'] ) || ! is_array( $ref[ $idx ]['settings'] ) ) {
				$ref[ $idx ]['settings'] = array();
			}
			$ref[ $idx ]['settings'][ $field ] = $new_html;
			return true;
		}
		if ( ! isset( $ref[ $idx ]['elements'] ) || ! is_array( $ref[ $idx ]['elements'] ) ) return false;
		$ref = &$ref[ $idx ]['elements'];
	}
	return false;
}

/**
 * Zorla eklenecek "özel ders" CTA'sı için Elementor uyumlu section > column > text-editor
 * widget ağacı üretir (kök seviyeye çıplak bir "widget" eklemek Elementor'da doğru
 * render edilmeyebileceğinden, evrensel olarak desteklenen section/column sarmalayıcı kullanılır).
 */
function ewt_link_build_cta_widget_tree( $html ) {
	$mk_id = function () { return substr( md5( uniqid( '', true ) ), 0, 7 ); };
	return array(
		'id'       => $mk_id(),
		'elType'   => 'section',
		'isInner'  => false,
		'settings' => array(),
		'elements' => array(
			array(
				'id'       => $mk_id(),
				'elType'   => 'column',
				'isInner'  => false,
				'settings' => array( '_column_size' => 100 ),
				'elements' => array(
					array(
						'id'         => $mk_id(),
						'elType'     => 'widget',
						'isInner'    => false,
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => $html ),
						'elements'   => array(),
					),
				),
			),
		),
	);
}

function ewt_link_save_elementor_tree( $post_id, array $tree ) {
	$json = wp_json_encode( $tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( $json === false ) return false;

	update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
	delete_post_meta( $post_id, '_elementor_css' ); // ön yüz CSS'i bir sonraki görüntülemede otomatik yeniden üretilir

	if ( class_exists( '\Elementor\Plugin' ) ) {
		try {
			if ( isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		} catch ( \Throwable $e ) {
			// Kritik değil — sayfa bir sonraki görüntülemede zaten yeniden derlenir.
		}
	}
	return true;
}

/* =========================================================
 * 6) ANA ORKESTRATÖR: BİR YAZIYI İŞLE
 * ========================================================= */

/**
 * @param int   $post_id
 * @param array $args { 'dry_run' => bool, 'only_keys' => string[]|null }
 * @return array Rapor: post_id, title, applied[], ozel_ders_linked, saved, is_elementor
 */
function ewt_link_process_post( $post_id, $args = array() ) {
	$args = wp_parse_args( $args, array( 'dry_run' => false, 'only_keys' => null ) );

	$post = get_post( $post_id );
	if ( ! $post ) return array( 'error' => 'Yazı bulunamadı.' );

	$settings      = ewt_link_get_settings();
	$ozel_ders_url = $settings['ozel_ders_url'];
	$self_url      = untrailingslashit( get_permalink( $post_id ) );
	$is_self_ozel  = $self_url === untrailingslashit( $ozel_ders_url );

	$is_elementor = ewt_link_is_elementor_post( $post_id );
	$elementor_tree = array();

	if ( $is_elementor ) {
		$data           = ewt_link_get_elementor_editable_texts( $post_id );
		$fragments      = $data['fragments'];
		$elementor_tree = $data['tree'];
		if ( empty( $fragments ) ) $fragments = array();
	} else {
		$fragments = array( 'post_content' => $post->post_content );
	}

	$plain_text = '';
	foreach ( $fragments as $html ) $plain_text .= ' ' . wp_strip_all_tags( $html );

	// --- Kural kuyruğu ---
	$rule_queue = array();
	if ( ! $is_self_ozel ) {
		foreach ( ewt_link_get_db_rules( 'ozel_ders', $post->post_type ) as $r ) $rule_queue[] = ewt_link_db_rule_to_queue_item( $r );
	}
	foreach ( ewt_link_get_db_rules( 'internal', $post->post_type ) as $r ) $rule_queue[] = ewt_link_db_rule_to_queue_item( $r );
	if ( $settings['auto_internal_enabled'] ) {
		foreach ( ewt_link_get_auto_internal_candidates( $post_id, $plain_text, $settings ) as $c ) $rule_queue[] = $c;
	}
	foreach ( ewt_link_get_db_rules( 'external', $post->post_type ) as $r ) $rule_queue[] = ewt_link_db_rule_to_queue_item( $r );

	usort( $rule_queue, function ( $a, $b ) { return $a['priority'] <=> $b['priority']; } );

	if ( is_array( $args['only_keys'] ) ) {
		$only = $args['only_keys'];
		$rule_queue = array_values( array_filter( $rule_queue, function ( $r ) use ( $only ) {
			return in_array( $r['key'], $only, true );
		} ) );
	}

	// Kendi permalink'ine asla link verme
	$rule_queue = array_values( array_filter( $rule_queue, function ( $r ) use ( $self_url ) {
		return untrailingslashit( $r['url'] ) !== $self_url;
	} ) );

	// --- Fragmentleri işle ---
	$counts          = array( 'internal' => 0, 'external' => 0, 'ozel_ders' => 0 );
	$limits          = array( 'internal' => max( 0, (int) $settings['max_internal'] ), 'external' => max( 0, (int) $settings['max_external'] ) );
	$applied_targets = array();
	$report          = array();
	$new_fragments   = array();
	$safety_failed   = false;

	foreach ( $fragments as $key => $html ) {
		if ( empty( $rule_queue ) ) { $new_fragments[ $key ] = $html; continue; }

		// Bu fragment için değişiklik güvenlik kontrolünden geçemezse TÜM etkilerini
		// (sayaçlar, rapor satırları, tüketilen kurallar, hedef listesi) geri almak için
		// işlemden ÖNCEKİ durumu yedekliyoruz.
		$counts_before          = $counts;
		$applied_targets_before = $applied_targets;
		$queue_before           = $rule_queue;
		$report_len_before      = count( $report );

		try {
			$dom = ewt_link_html_to_dom( $html );
			ewt_link_process_one_fragment_dom( $dom, $rule_queue, $counts, $limits, $applied_targets, $report, $key );
			$new_html = ewt_link_dom_to_html( $dom );
		} catch ( \Throwable $e ) {
			$new_html = $html;
		}

		if ( ! ewt_link_content_safety_ok( $html, $new_html ) ) {
			$new_fragments[ $key ] = $html;
			$safety_failed         = true;
			// Rollback: bu fragmentte hiçbir şey uygulanmamış gibi davran.
			$counts          = $counts_before;
			$applied_targets = $applied_targets_before;
			$rule_queue      = $queue_before;
			array_splice( $report, $report_len_before );
		} else {
			$new_fragments[ $key ] = $new_html;
		}
	}

	// --- Zorunlu "özel ders" CTA (doğal eşleşme bulunamadıysa) ---
	$ozel_ders_present = ( $counts['ozel_ders'] > 0 ) || ewt_link_fragments_contain_url( $new_fragments, $ozel_ders_url );
	$cta_key           = 'cta-ozel-ders';
	$cta_allowed       = ( $args['only_keys'] === null ) || in_array( $cta_key, (array) $args['only_keys'], true );

	if ( ! $is_self_ozel && ! $ozel_ders_present && $settings['force_ozel_ders'] && $cta_allowed ) {
		$anchor_text = 'ingilizce özel ders';
		$ozel_rules  = ewt_link_get_db_rules( 'ozel_ders', $post->post_type );
		if ( ! empty( $ozel_rules ) ) {
			$anchor_list = ewt_link_parse_anchors( $ozel_rules[0]->anchors );
			if ( ! empty( $anchor_list ) ) $anchor_text = $anchor_list[0];
		}

		$escaped_template = esc_html( $settings['cta_template'] );
		$anchor_html      = '<a href="' . esc_url( $ozel_ders_url ) . '">' . esc_html( $anchor_text ) . '</a>';
		$cta_html         = '<p>' . str_replace( '{anchor}', $anchor_html, $escaped_template ) . '</p>';

		if ( $is_elementor ) {
			$elementor_tree[] = ewt_link_build_cta_widget_tree( $cta_html );
			$cta_fragment_key = 'cta-widget';
		} else {
			$new_fragments['post_content'] = ( $new_fragments['post_content'] ?? $post->post_content ) . "\n" . $cta_html;
			$cta_fragment_key = 'post_content';
		}

		$report[] = array(
			'key'      => $cta_key,
			'type'     => 'ozel_ders',
			'label'    => 'Otomatik CTA (doğal eşleşme bulunamadı)',
			'anchor'   => $anchor_text,
			'url'      => $ozel_ders_url,
			'fragment' => $cta_fragment_key,
		);
		$ozel_ders_present = true;
	}

	// --- Kaydet ---
	$saved = false;
	if ( ! $args['dry_run'] ) {
		if ( $is_elementor ) {
			ewt_link_save_elementor_tree( $post_id, $elementor_tree );
		} elseif ( isset( $new_fragments['post_content'] ) ) {
			remove_action( 'save_post', 'ewt_link_on_save_post_maybe_auto', 20 );
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_fragments['post_content'] ) );
			add_action( 'save_post', 'ewt_link_on_save_post_maybe_auto', 20, 3 );
		}
		$saved = true;
	}

	return array(
		'post_id'           => $post_id,
		'title'             => get_the_title( $post_id ),
		'applied'           => $report,
		'ozel_ders_linked'  => $ozel_ders_present,
		'safety_failed'     => $safety_failed,
		'saved'             => $saved,
		'is_elementor'      => $is_elementor,
	);
}

/**
 * Ayar açıksa, yayınlama/güncelleme anında otomatik uygular. Varsayılan KAPALIDIR.
 */
function ewt_link_on_save_post_maybe_auto( $post_id, $post = null, $update = null ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

	$settings = ewt_link_get_settings();
	if ( ! $settings['auto_on_publish'] ) return;

	$p = get_post( $post_id );
	if ( ! $p || $p->post_status !== 'publish' ) return;
	if ( ! in_array( $p->post_type, ewt_link_all_supported_post_types(), true ) ) return;

	ewt_link_process_post( $post_id, array( 'dry_run' => false ) );
}
add_action( 'save_post', 'ewt_link_on_save_post_maybe_auto', 20, 3 );

/* =========================================================
 * 7) META BOX (Yazı Düzenleme Ekranı — Gutenberg/Klasik Editör)
 * ========================================================= */

function ewt_link_add_meta_box() {
	foreach ( ewt_link_all_supported_post_types() as $pt ) {
		add_meta_box( 'ewt_link_box', '🔗 İç/Dış Bağlantı Önerileri (Link Juice)', 'ewt_link_meta_box_html', $pt, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'ewt_link_add_meta_box' );

function ewt_link_meta_box_html( $post ) {
	wp_nonce_field( 'ewt_link_post_box', 'ewt_link_box_nonce' );
	?>
	<div class="ewt-link-box" id="ewt_link_box" data-post-id="<?php echo (int) $post->ID; ?>">
		<p class="description">
			Bu içerikte otomatik eklenebilecek iç bağlantı, dış otorite bağlantısı ve
			<strong>ingilizce-ozel-ders</strong> sayfasına link juice fırsatlarını tarayın.
			Önce <strong>Analiz Et</strong>'e tıklayın, önerilenlerden istediğinizi işaretleyip
			içeriğe ekleyin. Eklenen bağlantılar tamamen sıradan metin/link olarak kalır — elle
			istediğiniz gibi düzenleyebilir veya silebilirsiniz.
		</p>
		<p>
			<button type="button" class="button button-primary" id="ewt_link_analyze_btn">🔎 Bağlantıları Analiz Et</button>
			<button type="button" class="button" id="ewt_link_quick_apply_btn">⚡ Hepsini Doğrudan Uygula</button>
			<span id="ewt_link_status" style="margin-left:10px;font-weight:600;"></span>
		</p>
		<div id="ewt_link_results"></div>
	</div>
	<?php
}

/* =========================================================
 * 8) AJAX: TEK YAZI ANALİZ / UYGULA
 * ========================================================= */

function ewt_ajax_link_analyze_post() {
	check_ajax_referer( 'ewt_link_post_box', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$report = ewt_link_process_post( $post_id, array( 'dry_run' => true ) );
	wp_send_json_success( $report );
}
add_action( 'wp_ajax_ewt_link_analyze_post', 'ewt_ajax_link_analyze_post' );

function ewt_ajax_link_apply_post() {
	check_ajax_referer( 'ewt_link_post_box', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$only_keys = null;
	if ( isset( $_POST['keys'] ) && is_array( $_POST['keys'] ) ) {
		$only_keys = array_map( 'sanitize_text_field', wp_unslash( $_POST['keys'] ) );
	}

	$report = ewt_link_process_post( $post_id, array( 'dry_run' => false, 'only_keys' => $only_keys ) );
	wp_send_json_success( $report );
}
add_action( 'wp_ajax_ewt_link_apply_post', 'ewt_ajax_link_apply_post' );

/* =========================================================
 * 9) AJAX: TOPLU UYGULAMA MOTORU
 * ========================================================= */

function ewt_ajax_link_bulk_batch() {
	check_ajax_referer( 'ewt_link_bulk', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$offset     = isset( $_POST['offset'] ) ? max( 0, intval( $_POST['offset'] ) ) : 0;
	$batch_size = isset( $_POST['batch_size'] ) ? max( 5, min( 50, intval( $_POST['batch_size'] ) ) ) : 20;
	$dry_run    = empty( $_POST['apply'] );

	$all_types = ewt_link_all_supported_post_types();
	$types     = ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) )
		: $all_types;
	$types = array_values( array_intersect( $all_types, $types ) );
	if ( empty( $types ) ) $types = $all_types;

	$query = new WP_Query( array(
		'post_type'           => $types,
		'post_status'         => 'publish',
		'posts_per_page'      => $batch_size,
		'offset'              => $offset,
		'orderby'             => 'ID',
		'order'               => 'ASC',
		'fields'              => 'ids',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => false,
	) );

	$results = array();
	foreach ( $query->posts as $pid ) {
		$r = ewt_link_process_post( $pid, array( 'dry_run' => $dry_run ) );

		$details = array();
		if ( ! empty( $r['applied'] ) && is_array( $r['applied'] ) ) {
			foreach ( $r['applied'] as $a ) {
				$details[] = array(
					'type'   => isset( $a['type'] ) ? $a['type'] : 'internal',
					'anchor' => isset( $a['anchor'] ) ? $a['anchor'] : '',
					'url'    => isset( $a['url'] ) ? $a['url'] : '',
				);
			}
		}

		$results[] = array(
			'title'         => isset( $r['title'] ) ? $r['title'] : get_the_title( $pid ),
			'url'           => get_permalink( $pid ),
			'edit_url'      => get_edit_post_link( $pid, 'raw' ),
			'applied_count' => count( $details ),
			'ozel_ders'     => ! empty( $r['ozel_ders_linked'] ),
			'details'       => $details,
		);
	}

	$next_offset = $offset + count( $query->posts );
	$done        = ( count( $query->posts ) === 0 ) || ( $next_offset >= $query->found_posts );

	wp_send_json_success( array(
		'processed'   => count( $query->posts ),
		'total'       => (int) $query->found_posts,
		'next_offset' => $next_offset,
		'done'        => $done,
		'results'     => $results,
	) );
}
add_action( 'wp_ajax_ewt_link_bulk_batch', 'ewt_ajax_link_bulk_batch' );

/* =========================================================
 * 10) YÖNETİM PANELİ: MENÜ + SAYFALAR
 * ========================================================= */

function ewt_link_admin_menu() {
	add_menu_page(
		'Bağlantı Yöneticisi',
		'🔗 Bağlantı Yöneticisi',
		'manage_options',
		'ewt-link-rules',
		'ewt_link_rules_page_html',
		'dashicons-admin-links',
		60
	);
	$hook_rules = add_submenu_page( 'ewt-link-rules', 'Bağlantı Kuralları', 'Bağlantı Kuralları', 'manage_options', 'ewt-link-rules', 'ewt_link_rules_page_html' );
	add_submenu_page( 'ewt-link-rules', 'Toplu Uygula', 'Toplu Uygula', 'manage_options', 'ewt-link-bulk', 'ewt_link_bulk_page_html' );
	add_submenu_page( 'ewt-link-rules', 'Ayarlar', 'Ayarlar', 'manage_options', 'ewt-link-settings', 'ewt_link_settings_page_html' );

	// POST/silme işlemleri sayfa çıktısı BAŞLAMADAN (header'lar gönderilmeden önce) işlenmeli,
	// aksi halde wp_safe_redirect() "headers already sent" hatası verir.
	add_action( 'load-' . $hook_rules, 'ewt_link_rules_handle_post' );
}
add_action( 'admin_menu', 'ewt_link_admin_menu' );

function ewt_link_admin_enqueue( $hook ) {
	$is_editor   = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
	$is_own_page = strpos( $hook, 'ewt-link' ) !== false;
	if ( ! $is_editor && ! $is_own_page ) return;

	wp_enqueue_script( 'ewt-link-admin', EWT_URI . '/assets/js/admin-link-manager.js', array(), EWT_VERSION, true );
	wp_localize_script( 'ewt-link-admin', 'ewtLinkAdmin', array(
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'postNonce' => wp_create_nonce( 'ewt_link_post_box' ),
		'bulkNonce' => wp_create_nonce( 'ewt_link_bulk' ),
		'i18n'      => array(
			'analyzing'       => 'Analiz ediliyor…',
			'applying'        => 'Ekleniyor…',
			'noMatches'       => 'Bu içerik için uygun bir eşleşme bulunamadı.',
			'applied'         => 'Seçilen bağlantılar içeriğe eklendi. Güncel hâli görmek için sayfayı yenileyin.',
			'error'           => 'Bir hata oluştu, lütfen tekrar deneyin.',
			'confirmBulkApply'=> 'Seçilen içerik türlerindeki TÜM yayınlanmış içerikler kalıcı olarak güncellenecek. Devam edilsin mi?',
			'starting'        => 'Başlatılıyor…',
			'done'            => 'Tamamlandı ✅',
			'confirmQuick'    => 'Bu yazı için bulunan TÜM bağlantı önerileri (önizlemesiz) doğrudan içeriğe eklenecek. Devam edilsin mi?',
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_link_admin_enqueue' );

/* ---------------------------------------------------------
 * 10a) BAĞLANTI KURALLARI (CRUD) SAYFASI
 * --------------------------------------------------------- */

function ewt_link_rules_handle_post() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	if ( isset( $_GET['ewt_link_delete'] ) ) {
		$id = absint( $_GET['ewt_link_delete'] );
		if ( $id && check_admin_referer( 'ewt_link_delete_' . $id ) ) {
			global $wpdb;
			$wpdb->delete( ewt_link_table_name(), array( 'id' => $id ), array( '%d' ) );
			wp_safe_redirect( add_query_arg( array( 'page' => 'ewt-link-rules', 'ewt_link_msg' => 'deleted' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	if ( isset( $_POST['ewt_link_rule_action'] ) && $_POST['ewt_link_rule_action'] === 'bulk_import' ) {
		check_admin_referer( 'ewt_link_rule_bulk_import' );

		$raw = isset( $_POST['bulk_csv'] ) ? wp_unslash( $_POST['bulk_csv'] ) : '';
		$res = ewt_link_bulk_import_rules_from_csv( $raw );

		wp_safe_redirect( add_query_arg( array(
			'page'             => 'ewt-link-rules',
			'ewt_link_msg'     => 'bulk_imported',
			'ewt_link_added'   => $res['added'],
			'ewt_link_skipped' => $res['skipped'],
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( isset( $_POST['ewt_link_rule_action'] ) && $_POST['ewt_link_rule_action'] === 'save' ) {
		check_admin_referer( 'ewt_link_rule_save' );

		global $wpdb;
		$table = ewt_link_table_name();
		$now   = current_time( 'mysql' );

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$type    = isset( $_POST['rule_type'] ) ? sanitize_text_field( $_POST['rule_type'] ) : 'internal';
		if ( ! in_array( $type, array( 'internal', 'external', 'ozel_ders' ), true ) ) $type = 'internal';

		$cpt_scope = '';
		if ( isset( $_POST['cpt_scope'] ) && is_array( $_POST['cpt_scope'] ) ) {
			$cpt_scope = implode( ',', array_map( 'sanitize_text_field', wp_unslash( $_POST['cpt_scope'] ) ) );
		}

		$data = array(
			'rule_type'    => $type,
			'label'        => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'anchors'      => sanitize_textarea_field( wp_unslash( $_POST['anchors'] ?? '' ) ),
			'target_url'   => esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) ),
			'cpt_scope'    => $cpt_scope,
			'nofollow'     => ! empty( $_POST['nofollow'] ) ? 1 : 0,
			'new_tab'      => ! empty( $_POST['new_tab'] ) ? 1 : 0,
			'max_per_post' => max( 1, absint( $_POST['max_per_post'] ?? 1 ) ),
			'priority'     => max( 1, absint( $_POST['priority'] ?? 10 ) ),
			'is_active'    => ! empty( $_POST['is_active'] ) ? 1 : 0,
			'updated_at'   => $now,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s' );

		if ( $rule_id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $rule_id ), $formats, array( '%d' ) );
			$msg = 'updated';
		} else {
			$data['created_at'] = $now;
			$formats[]          = '%s';
			$wpdb->insert( $table, $data, $formats );
			$msg = 'created';
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'ewt-link-rules', 'ewt_link_msg' => $msg ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

function ewt_link_cpt_scope_choices() {
	$out = array( 'post' => 'Normal Blog Yazısı', 'page' => 'Sayfa' );
	foreach ( ewt_cpt_config() as $slug => $d ) $out[ $slug ] = $d[1];
	return $out;
}

/**
 * Bir "kapsam" hücresindeki tek bir değeri (CPT slug'ı VEYA görünen adı, örn. "TOEFL" ya da
 * "ewt_toefl") gerçek CPT slug'ına çevirir. Eşleşme bulunamazsa, girilen değeri OLDUĞU GİBİ
 * bırakır (zararsızdır — ewt_link_rule_matches_cpt basitçe hiçbir içerikte eşleşmez).
 */
function ewt_link_normalize_scope_token( $token, array $choices ) {
	$token = trim( (string) $token );
	if ( $token === '' ) return '';
	if ( isset( $choices[ $token ] ) ) return $token; // zaten geçerli bir slug

	foreach ( $choices as $slug => $label ) {
		if ( mb_strtolower( $label ) === mb_strtolower( $token ) ) return $slug;
	}
	return $token;
}

/**
 * CSV metnini (satır bazlı, virgülle ayrılmış, tırnaklı hücreleri destekler) birden fazla
 * bağlantı kuralına ayrıştırıp tek seferde veritabanına ekler.
 *
 * Sütun sırası: rule_type, label, anchors, target_url, cpt_scope, nofollow, new_tab, priority
 * - anchors ve cpt_scope hücreleri içinde BİRDEN FAZLA değer "|" (dikey çizgi) ile ayrılır.
 * - rule_type: internal/iç, external/dış, ozel_ders/özel ders (Türkçe eşanlamlılar kabul edilir).
 * - nofollow / new_tab: 1 veya 0 (boşsa: nofollow=0, new_tab=1).
 * - priority: sayı (boşsa 10).
 * - Başlık satırı varsa (ilk hücre "tur"/"tür"/"rule_type"/"etiket"/"label" gibi bilinen bir
 *   kelimeyse) otomatik atlanır.
 * - Etiketi veya hedef URL'si boş/geçersiz olan satırlar atlanır (sayılır, eklenmez).
 *
 * @return array { 'added' => int, 'skipped' => int }
 */
function ewt_link_bulk_import_rules_from_csv( $raw_text ) {
	$raw_text = (string) $raw_text;
	$lines    = preg_split( '/\r\n|\r|\n/', $raw_text );
	$lines    = array_values( array_filter( $lines, function ( $l ) { return trim( $l ) !== ''; } ) );
	if ( empty( $lines ) ) return array( 'added' => 0, 'skipped' => 0 );

	// Başlık satırı tespiti (ilk satırın ilk hücresi bilinen bir başlık kelimesiyse atla)
	$first_cols   = str_getcsv( $lines[0] );
	$first_cell   = mb_strtolower( trim( (string) ( $first_cols[0] ?? '' ) ) );
	$header_words = array( 'tur', 'tür', 'rule_type', 'tip', 'type' );
	if ( in_array( $first_cell, $header_words, true ) ) {
		array_shift( $lines );
	}

	$type_aliases = array(
		'internal' => 'internal', 'iç' => 'internal', 'ic' => 'internal', 'iç bağlantı' => 'internal',
		'external' => 'external', 'dış' => 'external', 'dis' => 'external', 'dış otorite' => 'external',
		'ozel_ders' => 'ozel_ders', 'özel ders' => 'ozel_ders', 'ozelders' => 'ozel_ders', 'özel_ders' => 'ozel_ders',
	);
	$scope_choices = ewt_link_cpt_scope_choices();

	global $wpdb;
	$table = ewt_link_table_name();
	$now   = current_time( 'mysql' );

	$added   = 0;
	$skipped = 0;

	foreach ( $lines as $line ) {
		if ( trim( $line ) === '' ) continue;
		$cols = str_getcsv( $line );

		$type_raw  = mb_strtolower( trim( (string) ( $cols[0] ?? '' ) ) );
		$label     = trim( (string) ( $cols[1] ?? '' ) );
		$anchors   = trim( (string) ( $cols[2] ?? '' ) );
		$target    = trim( (string) ( $cols[3] ?? '' ) );
		$scope_raw = trim( (string) ( $cols[4] ?? '' ) );
		$nofollow  = isset( $cols[5] ) ? ( trim( $cols[5] ) === '1' ? 1 : 0 ) : 0;
		$new_tab   = isset( $cols[6] ) && $cols[6] !== '' ? ( trim( $cols[6] ) === '1' ? 1 : 0 ) : 1;
		$priority  = isset( $cols[7] ) && trim( $cols[7] ) !== '' ? max( 1, absint( $cols[7] ) ) : 10;

		$target_url = esc_url_raw( $target );
		if ( $label === '' || $target_url === '' || $anchors === '' ) { $skipped++; continue; }

		$rule_type = isset( $type_aliases[ $type_raw ] ) ? $type_aliases[ $type_raw ] : 'internal';

		$anchors_lines = array_filter( array_map( 'trim', explode( '|', $anchors ) ) );
		if ( empty( $anchors_lines ) ) { $skipped++; continue; }
		$anchors_db = implode( "\n", $anchors_lines );

		$scope_tokens = array_filter( array_map( 'trim', explode( '|', $scope_raw ) ) );
		$scope_tokens = array_map( function ( $t ) use ( $scope_choices ) { return ewt_link_normalize_scope_token( $t, $scope_choices ); }, $scope_tokens );
		$cpt_scope    = implode( ',', array_filter( $scope_tokens ) );

		$data = array(
			'rule_type'    => $rule_type,
			'label'        => sanitize_text_field( $label ),
			'anchors'      => sanitize_textarea_field( $anchors_db ),
			'target_url'   => $target_url,
			'cpt_scope'    => $cpt_scope,
			'nofollow'     => $nofollow,
			'new_tab'      => $new_tab,
			'max_per_post' => 1,
			'priority'     => $priority,
			'is_active'    => 1,
			'created_at'   => $now,
			'updated_at'   => $now,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' );

		$ok = $wpdb->insert( $table, $data, $formats );
		if ( $ok ) $added++; else $skipped++;
	}

	return array( 'added' => $added, 'skipped' => $skipped );
}

function ewt_link_rules_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	global $wpdb;
	$table = ewt_link_table_name();

	$editing = null;
	if ( isset( $_GET['edit'] ) ) {
		$edit_id = absint( $_GET['edit'] );
		if ( $edit_id ) $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) );
	}

	$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY rule_type ASC, priority ASC" );
	$scope_choices = ewt_link_cpt_scope_choices();

	$type_labels = array( 'ozel_ders' => '⭐ Özel Ders (Zorunlu)', 'internal' => '🔗 İç Bağlantı', 'external' => '🌍 Dış Otorite' );

	if ( isset( $_GET['ewt_link_msg'] ) ) {
		$msg_map = array( 'created' => 'Kural eklendi.', 'updated' => 'Kural güncellendi.', 'deleted' => 'Kural silindi.' );
		$m = sanitize_text_field( $_GET['ewt_link_msg'] );
		if ( $m === 'bulk_imported' ) {
			$added   = isset( $_GET['ewt_link_added'] ) ? absint( $_GET['ewt_link_added'] ) : 0;
			$skipped = isset( $_GET['ewt_link_skipped'] ) ? absint( $_GET['ewt_link_skipped'] ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>✅ ' . esc_html( $added ) . ' kural eklendi.'
				. ( $skipped > 0 ? ' ⚠️ ' . esc_html( $skipped ) . ' satır atlandı (etiket/hedef URL/çapa metni eksik ya da geçersiz).' : '' )
				. '</p></div>';
		} elseif ( isset( $msg_map[ $m ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>✅ ' . esc_html( $msg_map[ $m ] ) . '</p></div>';
		}
	}
	?>
	<div class="wrap">
		<h1>🔗 Bağlantı Kuralları</h1>
		<p>Her kural bir grup çapa metin varyasyonu + hedef bir URL'dir. İçerikte bu çapa metinlerinden biri GEÇERSE, ilk geçtiği yerde otomatik olarak bağlantıya çevrilir. <strong>⭐ Özel Ders</strong> tipi kural, her içeriğin özel ders sayfanıza en az bir kez link vermesini garanti eder (doğal eşleşme yoksa içerik sonuna kısa bir cümle eklenir).</p>

		<table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th style="width:110px;">Tür</th>
					<th>Etiket</th>
					<th>Çapa Metinleri</th>
					<th>Hedef URL</th>
					<th>Kapsam</th>
					<th style="width:70px;">Aktif</th>
					<th style="width:140px;">İşlem</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="7">Henüz kural yok.</td></tr>
			<?php else : foreach ( $rows as $r ) :
				$anchors_preview = implode( ', ', array_slice( ewt_link_parse_anchors( $r->anchors ), 0, 3 ) );
				$scope_preview   = $r->cpt_scope === '' ? 'Tüm türler' : implode( ', ', array_map( function ( $s ) use ( $scope_choices ) { return $scope_choices[ trim( $s ) ] ?? trim( $s ); }, explode( ',', $r->cpt_scope ) ) );
				$del_url = wp_nonce_url( add_query_arg( array( 'page' => 'ewt-link-rules', 'ewt_link_delete' => $r->id ), admin_url( 'admin.php' ) ), 'ewt_link_delete_' . $r->id );
				$edit_url = add_query_arg( array( 'page' => 'ewt-link-rules', 'edit' => $r->id ), admin_url( 'admin.php' ) );
				?>
				<tr>
					<td><?php echo esc_html( $type_labels[ $r->rule_type ] ?? $r->rule_type ); ?></td>
					<td><strong><?php echo esc_html( $r->label ); ?></strong></td>
					<td><?php echo esc_html( $anchors_preview ); ?><?php echo count( ewt_link_parse_anchors( $r->anchors ) ) > 3 ? '…' : ''; ?></td>
					<td><a href="<?php echo esc_url( $r->target_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $r->target_url, PHP_URL_HOST ) ); ?></a></td>
					<td><?php echo esc_html( $scope_preview ); ?></td>
					<td><?php echo $r->is_active ? '✅' : '—'; ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Düzenle</a>
						<a href="<?php echo esc_url( $del_url ); ?>" class="button button-small" onclick="return confirm('Bu kural silinsin mi?');">Sil</a>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>

		<h2 class="title" style="margin-top:28px;"><?php echo $editing ? 'Kuralı Düzenle' : 'Yeni Kural Ekle'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ewt-link-rules' ) ); ?>">
			<?php wp_nonce_field( 'ewt_link_rule_save' ); ?>
			<input type="hidden" name="ewt_link_rule_action" value="save" />
			<input type="hidden" name="rule_id" value="<?php echo esc_attr( $editing->id ?? 0 ); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="rule_type">Kural Türü</label></th>
					<td>
						<select name="rule_type" id="rule_type">
							<option value="internal" <?php selected( $editing->rule_type ?? '', 'internal' ); ?>>🔗 İç Bağlantı (sitenizdeki bir sayfaya)</option>
							<option value="external" <?php selected( $editing->rule_type ?? '', 'external' ); ?>>🌍 Dış Otorite Bağlantısı</option>
							<option value="ozel_ders" <?php selected( $editing->rule_type ?? '', 'ozel_ders' ); ?>>⭐ Özel Ders (zorunlu link juice hedefi)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="label">Etiket (sadece siz göreceksiniz)</label></th>
					<td><input type="text" class="regular-text" name="label" id="label" value="<?php echo esc_attr( $editing->label ?? '' ); ?>" placeholder="örn. Cambridge Dictionary" /></td>
				</tr>
				<tr>
					<th><label for="anchors">Çapa Metinleri</label></th>
					<td>
						<textarea name="anchors" id="anchors" class="large-text" rows="5" placeholder="her satıra bir varyasyon&#10;örn: ingilizce özel ders&#10;özel ingilizce dersi"><?php echo esc_textarea( $editing->anchors ?? '' ); ?></textarea>
						<p class="description">Her satıra bir varyasyon yazın. İçerikte GEÇEN ilk varyasyon otomatik linke çevrilir. Türkçe İ/I/ı/i farkları otomatik eşleşir.</p>
					</td>
				</tr>
				<tr>
					<th><label for="target_url">Hedef URL</label></th>
					<td><input type="url" class="regular-text" name="target_url" id="target_url" value="<?php echo esc_attr( $editing->target_url ?? '' ); ?>" placeholder="https://…" /></td>
				</tr>
				<tr>
					<th>Kapsam (İçerik Türleri)</th>
					<td>
						<?php $selected_scope = $editing ? array_map( 'trim', explode( ',', $editing->cpt_scope ) ) : array(); ?>
						<?php foreach ( $scope_choices as $slug => $lbl ) : ?>
							<label style="display:inline-block;min-width:220px;margin:2px 0;">
								<input type="checkbox" name="cpt_scope[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_scope, true ) ); ?> />
								<?php echo esc_html( $lbl ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">Hiçbiri işaretlenmezse bu kural TÜM içerik türlerinde geçerli olur.</p>
					</td>
				</tr>
				<tr>
					<th>Seçenekler</th>
					<td>
						<label><input type="checkbox" name="nofollow" value="1" <?php checked( ! empty( $editing->nofollow ) ); ?> /> nofollow (genelde dış bağlantılar için önerilir)</label><br />
						<label><input type="checkbox" name="new_tab" value="1" <?php checked( $editing ? ! empty( $editing->new_tab ) : true ); ?> /> Yeni sekmede aç</label><br />
						<label><input type="checkbox" name="is_active" value="1" <?php checked( $editing ? ! empty( $editing->is_active ) : true ); ?> /> Aktif</label>
					</td>
				</tr>
				<tr>
					<th><label for="priority">Öncelik</label></th>
					<td><input type="number" min="1" max="999" name="priority" id="priority" value="<?php echo esc_attr( $editing->priority ?? 10 ); ?>" class="small-text" /> <span class="description">Düşük sayı önce denenir.</span></td>
				</tr>
				<tr>
					<th><label for="max_per_post">Yazı Başına Maks.</label></th>
					<td>
						<input type="number" min="1" max="5" name="max_per_post" id="max_per_post" value="<?php echo esc_attr( $editing->max_per_post ?? 1 ); ?>" class="small-text" />
						<p class="description">Not: Şu anki sürümde her kural, bir yazı içinde en fazla 1 kez uygulanır (bu alan ileriye dönük saklanır).</p>
					</td>
				</tr>
			</table>
			<?php submit_button( $editing ? 'Kuralı Güncelle' : 'Kuralı Ekle' ); ?>
			<?php if ( $editing ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=ewt-link-rules' ) ); ?>" class="button">İptal</a><?php endif; ?>
		</form>

		<h2 class="title" style="margin-top:32px;">📥 Toplu Kural Ekle (CSV Yapıştır)</h2>
		<p>Tek tek eklemek yerine, birden fazla kuralı aşağıya <strong>tek seferde yapıştırıp</strong> ekleyebilirsiniz. Her satır bir kural olur. Sütun sırası:</p>
		<p><code>tür, etiket, çapa metinleri, hedef url, kapsam, nofollow, yeni_sekme, öncelik</code></p>
		<ul style="list-style:disc;margin-left:22px;">
			<li><strong>tür</strong>: <code>internal</code> (iç), <code>external</code> (dış), <code>ozel_ders</code> (özel ders) — boşsa <code>internal</code> kabul edilir.</li>
			<li><strong>çapa metinleri</strong> ve <strong>kapsam</strong>: birden fazla değeri <strong>|</strong> (dikey çizgi) ile ayırın. Kapsamda hem CPT slug'ı (<code>ewt_toefl</code>) hem görünen ad (<code>TOEFL</code>) kabul edilir; boş bırakılırsa TÜM içerik türlerinde geçerli olur.</li>
			<li><strong>nofollow</strong> / <strong>yeni_sekme</strong>: <code>1</code> veya <code>0</code> (boşsa nofollow=0, yeni_sekme=1).</li>
			<li><strong>öncelik</strong>: sayı, boşsa 10. Etiket, hedef URL veya çapa metni boş olan satırlar atlanır.</li>
		</ul>
		<p>Örnek:</p>
		<pre style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 14px;overflow:auto;">external,Merriam-Webster (GRE),merriam-webster sözlük|merriam webster,https://www.merriam-webster.com/,ewt_gre|ewt_gmat,1,1,25
internal,Present Perfect Konu Anlatımı,present perfect konu anlatımı|present perfect tense,https://english.web.tr/present-perfect-konu-anlatimi/,ewt_grammar,0,0,15</pre>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ewt-link-rules' ) ); ?>">
			<?php wp_nonce_field( 'ewt_link_rule_bulk_import' ); ?>
			<input type="hidden" name="ewt_link_rule_action" value="bulk_import" />
			<textarea name="bulk_csv" class="large-text code" rows="8" placeholder="tür,etiket,çapa metinleri,hedef url,kapsam,nofollow,yeni_sekme,öncelik"></textarea>
			<?php submit_button( '📥 Toplu Ekle' ); ?>
		</form>
	</div>
	<?php
}

/* ---------------------------------------------------------
 * 10b) AYARLAR SAYFASI
 * --------------------------------------------------------- */

function ewt_link_register_settings() {
	register_setting( 'ewt_link_settings_group', 'ewt_link_max_internal', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'ewt_link_settings_group', 'ewt_link_max_external', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'ewt_link_settings_group', 'ewt_link_force_ozel_ders', array( 'sanitize_callback' => 'ewt_sanitize_bool_01' ) );
	register_setting( 'ewt_link_settings_group', 'ewt_link_ozel_ders_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	register_setting( 'ewt_link_settings_group', 'ewt_link_cta_template', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
	register_setting( 'ewt_link_settings_group', 'ewt_link_auto_internal_enabled', array( 'sanitize_callback' => 'ewt_sanitize_bool_01' ) );
	register_setting( 'ewt_link_settings_group', 'ewt_link_auto_on_publish', array( 'sanitize_callback' => 'ewt_sanitize_bool_01' ) );
	register_setting( 'ewt_link_settings_group', 'ewt_link_min_auto_title_len', array( 'sanitize_callback' => 'absint' ) );
}
add_action( 'admin_init', 'ewt_link_register_settings' );

function ewt_link_settings_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$s = ewt_link_get_settings();
	?>
	<div class="wrap">
		<h1>🔗 Bağlantı Yöneticisi — Ayarlar</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'ewt_link_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ewt_link_max_internal">Yazı Başına Maks. İç Bağlantı</label></th>
					<td><input type="number" min="0" max="10" name="ewt_link_max_internal" id="ewt_link_max_internal" value="<?php echo esc_attr( $s['max_internal'] ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th><label for="ewt_link_max_external">Yazı Başına Maks. Dış Bağlantı</label></th>
					<td><input type="number" min="0" max="10" name="ewt_link_max_external" id="ewt_link_max_external" value="<?php echo esc_attr( $s['max_external'] ); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th>Otomatik İç Bağlantı Keşfi (Konu/Seviye/CPT Bazlı)</th>
					<td>
						<label><input type="checkbox" name="ewt_link_auto_internal_enabled" value="1" <?php checked( $s['auto_internal_enabled'] ); ?> /> Aynı içerik türü ve/veya aynı Seviye'ye sahip alakalı yazıları bulup, odak anahtar kelime / konu ifadesi / başlık üzerinden otomatik iç bağlantı öner</label>
						<p class="description">Artık hedef yazının TAM başlığının birebir geçmesi şart değil — örn. "B2 Seviyesi İngilizce Kelimeler" içeriği, sadece "ingilizce kelimeler" ifadesi geçen bir A1 yazısına da bağlanabilir. Minimum başlık uzunluğu (gürültülü/çok genel eşleşmeleri önlemek için):
							<input type="number" min="3" max="60" name="ewt_link_min_auto_title_len" value="<?php echo esc_attr( $s['min_auto_title_len'] ); ?>" class="small-text" /> karakter
						</p>
					</td>
				</tr>
				<tr>
					<th>Özel Ders Sayfasına Zorunlu Link Juice</th>
					<td>
						<label><input type="checkbox" name="ewt_link_force_ozel_ders" value="1" <?php checked( $s['force_ozel_ders'] ); ?> /> Doğal bir çapa metin bulunamazsa, içerik sonuna otomatik bir cümle + link ekle</label>
						<p class="description">
							Hedef URL: <input type="url" class="regular-text" name="ewt_link_ozel_ders_url" value="<?php echo esc_attr( $s['ozel_ders_url'] ); ?>" /><br />
							Otomatik cümle şablonu (<code>{anchor}</code> = çapa metni ile değiştirilir):<br />
							<textarea name="ewt_link_cta_template" class="large-text" rows="2"><?php echo esc_textarea( $s['cta_template'] ); ?></textarea>
						</p>
					</td>
				</tr>
				<tr>
					<th>Yayınlandığında Otomatik Uygula</th>
					<td>
						<label><input type="checkbox" name="ewt_link_auto_on_publish" value="1" <?php checked( $s['auto_on_publish'] ); ?> /> Bir yazı yayınlandığında/güncellendiğinde bağlantıları otomatik uygula</label>
						<p class="description">⚠️ Varsayılan kapalıdır. Açarsanız, her kayıt sırasında içerik otomatik değişir — önce "Toplu Uygula" sayfasından önizleme yapmanızı öneririz.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Ayarları Kaydet' ); ?>
		</form>
	</div>
	<?php
}

/* ---------------------------------------------------------
 * 10c) TOPLU UYGULA SAYFASI
 * --------------------------------------------------------- */

function ewt_link_bulk_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$scope_choices = ewt_link_cpt_scope_choices();
	?>
	<div class="wrap">
		<h1>🔗 Toplu Bağlantı Uygulama</h1>
		<p>Mevcut (yayınlanmış) içeriklerinizin tamamına veya seçtiğiniz içerik türlerine bağlantı kurallarını tek seferde uygulayın. Önce <strong>Önizleme</strong> modunda ne kadar bağlantı ekleneceğini görün, sonra <strong>Uygula</strong>'ya geçin. Her yazının altındaki satırı açarak, o yazıya <strong>hangi çapa metni üzerinden hangi hedefe</strong> link verileceğini tek tek inceleyebilirsiniz.</p>

		<h2 class="title">İçerik Türleri</h2>
		<div class="ewt-seviye-chips" style="margin-bottom:14px;">
			<label style="display:inline-block;min-width:220px;margin:2px 0;"><input type="checkbox" class="ewt-link-bulk-type" value="__all__" checked /> <strong>Tümü</strong></label><br />
			<?php foreach ( $scope_choices as $slug => $lbl ) : ?>
				<label style="display:inline-block;min-width:220px;margin:2px 0;"><input type="checkbox" class="ewt-link-bulk-type" value="<?php echo esc_attr( $slug ); ?>" /> <?php echo esc_html( $lbl ); ?></label>
			<?php endforeach; ?>
		</div>

		<p>
			<button type="button" class="button button-hero" id="ewt_link_bulk_preview_btn">👁️ Önizleme Yap</button>
			<button type="button" class="button button-primary button-hero" id="ewt_link_bulk_apply_btn">🚀 Uygula (Kalıcı)</button>
			<span id="ewt_link_bulk_status" style="margin-left:12px;font-weight:600;"></span>
		</p>
		<div class="ewt-search-progress-wrap" id="ewt_link_bulk_progress_wrap" style="display:none;">
			<div class="ewt-search-progress-bar" id="ewt_link_bulk_progress_bar"></div>
		</div>

		<div id="ewt_link_bulk_log" style="margin-top:16px;max-height:420px;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;"></div>
	</div>
	<?php
}
