<?php
/**
 * Custom Post Types & Taxonomies
 *
 * 9 ana eğitim kademesi CPT'si + 5 dil becerisi CPT'si.
 * Her CPT kendi "Seviye" taxonomy'sine sahiptir (A1/A2... veya sınıf düzeyi)
 * böylece siz kendi kategorilerinizi (A1, A2, 5. Sınıf vb.) dashboard'dan açabilirsiniz.
 * Eski normal blog yazıları (post) hiç dokunulmadan aynen devam eder.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tüm CPT tanımlarını tek bir merkezi dizide tutuyoruz.
 * key  => CPT slug (20 karakter WP limitine uygun kısaltılmış)
 * value => [label, plural, icon, menu_position, description]
 */
function ewt_cpt_config() {
	return array(
		// 9 ana eğitim kademesi
		'ewt_preschool' => array( 'İlkokul Öncesi İngilizce', 'İlkokul Öncesi İngilizce', 'dashicons-palmtree', 21 ),
		'ewt_primary'   => array( 'İlkokul İngilizce',         'İlkokul İngilizce',        'dashicons-slides', 22 ),
		'ewt_secondary' => array( 'Ortaokul İngilizce',        'Ortaokul İngilizce',        'dashicons-welcome-learn-more', 23 ),
		'ewt_highschool'=> array( 'Lise İngilizce',            'Lise İngilizce',            'dashicons-welcome-write-blog', 24 ),
		'ewt_ydt'       => array( 'YDT',                       'YDT',                       'dashicons-awards', 25 ),
		'ewt_toefl'     => array( 'TOEFL',                     'TOEFL',                     'dashicons-awards', 26 ),
		'ewt_gre'       => array( 'GRE',                       'GRE',                       'dashicons-awards', 27 ),
		'ewt_yds'       => array( 'YDS',                       'YDS',                       'dashicons-awards', 28 ),
		'ewt_gmat'      => array( 'GMAT',                      'GMAT',                      'dashicons-awards', 29 ),
		// 5 dil becerisi (four skills + grammar/vocabulary talebi -> vocabulary tekrarı birleştirildi)
		'ewt_listening' => array( 'Listening',                 'Listening',                 'dashicons-headphones', 30 ),
		'ewt_speaking'  => array( 'Speaking',                  'Speaking',                  'dashicons-microphone', 31 ),
		'ewt_reading'   => array( 'Reading',                   'Reading',                   'dashicons-book', 32 ),
		'ewt_writing'   => array( 'Writing',                   'Writing',                   'dashicons-edit-large', 33 ),
		'ewt_vocabulary'=> array( 'Vocabulary',                'Vocabulary',                'dashicons-editor-spellcheck', 34 ),
		'ewt_grammar'   => array( 'Grammar',                   'Grammar',                   'dashicons-editor-ol', 35 ),
		// v1.6.4: K12 sonrası (üniversite/lisansüstü/kariyer) 3 yeni CPT
		'ewt_ielts'     => array( 'IELTS',                     'IELTS',                     'dashicons-awards', 36 ),
		'ewt_yokdil'    => array( 'YÖKDİL',                    'YÖKDİL',                    'dashicons-awards', 37 ),
		'ewt_mesleki'   => array( 'Mesleki İngilizce',         'Mesleki İngilizce',         'dashicons-businessman', 38 ),
	);
}

/**
 * CPT'leri kaydet
 */
function ewt_register_cpts() {
	foreach ( ewt_cpt_config() as $slug => $data ) {
		list( $label, $plural, $icon, $pos ) = $data;

		$args = array(
			'labels' => array(
				'name'               => $plural,
				'singular_name'      => $label,
				'add_new'            => 'Yeni Ekle',
				'add_new_item'       => $label . ' - Yeni İçerik Ekle',
				'edit_item'          => $label . ' Düzenle',
				'new_item'           => 'Yeni ' . $label,
				'view_item'          => $label . ' Görüntüle',
				'search_items'       => $label . ' Ara',
				'not_found'          => 'İçerik bulunamadı',
				'menu_name'          => $plural,
				'all_items'          => 'Tüm ' . $plural,
			),
			'public'             => true,
			'has_archive'        => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true, // Gutenberg + AI widget REST erişimi için
			'menu_icon'          => $icon,
			'menu_position'      => $pos,
			'rewrite'            => array( 'slug' => str_replace( 'ewt_', '', $slug ) ),
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'hierarchical'       => false,
			'capability_type'    => 'post',
			'exclude_from_search'=> false,
		);

		register_post_type( $slug, $args );

		// Her CPT için kendine ait "Seviye" taxonomy'si (A1-C2 veya sınıf düzeyi siz tanımlarsınız)
		$tax_slug = $slug . '_seviye';
		register_taxonomy( $tax_slug, array( $slug ), array(
			'labels' => array(
				'name'          => $label . ' Seviyeleri',
				'singular_name' => 'Seviye',
				'search_items'  => 'Seviye Ara',
				'all_items'     => 'Tüm Seviyeler',
				'edit_item'     => 'Seviye Düzenle',
				'add_new_item'  => 'Yeni Seviye Ekle',
				'menu_name'     => 'Seviyeler',
			),
			'hierarchical'      => true, // kategori gibi çalışsın (A1 > A1.1 gibi alt kırılım da açılabilir)
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => str_replace( 'ewt_', '', $slug ) . '-seviye' ),
		) );
	}
}
add_action( 'init', 'ewt_register_cpts' );

/**
 * Arşiv ve taxonomy sayfalarında sıralama: EN ESKİ İÇERİK EN BAŞTA, en yeni en sonda.
 * Böylece öğrenci konuyu ilk yazıdan takip ederek en güncel içeriğe kadar ilerler.
 */
function ewt_order_cpt_archives( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) return;

	$ewt_slugs = array_keys( ewt_cpt_config() );

	if ( is_post_type_archive( $ewt_slugs ) || is_tax( ewt_all_seviye_taxonomies() ) ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'ASC' );
		$query->set( 'posts_per_page', 20 );
	}
}
add_action( 'pre_get_posts', 'ewt_order_cpt_archives' );

/**
 * Tüm "_seviye" taxonomy slug'larını döndürür (pre_get_posts filtresinde kullanılır)
 */
function ewt_all_seviye_taxonomies() {
	$tax = array();
	foreach ( array_keys( ewt_cpt_config() ) as $slug ) {
		$tax[] = $slug . '_seviye';
	}
	return $tax;
}

/**
 * Eski normal blog yazıları (post) tamamen dokunulmadan devam eder.
 * Burada CPT'lere özel hiçbir şey 'post' post_type'ına uygulanmaz.
 */

/**
 * Her CPT için "konu anlatım zorluk derecesi" seçeneklerini döndürür.
 * Bu seçenekler AI içerik widget'larının prompt'larında kullanılır ki
 * üretilen içerik seçilen CPT'ye (yaş grubu / sınav türü) uygun zorlukta olsun.
 */
function ewt_difficulty_options_for_cpt( $post_type ) {
	$cefr_full = array(
		'a1' => 'A1 - Başlangıç',
		'a2' => 'A2 - Temel',
		'b1' => 'B1 - Orta',
		'b2' => 'B2 - Orta Üstü',
		'c1' => 'C1 - İleri',
		'c2' => 'C2 - Uzman',
	);

	$map = array(
		'ewt_preschool' => array(
			'cok-kolay' => 'Çok Kolay (Nesne/Renk/Hayvan Tanıma)',
			'kolay'     => 'Kolay (Tek Kelime + Görsel Eşleştirme)',
			'orta'      => 'Orta (Basit 2-3 Kelimelik İfadeler)',
		),
		'ewt_primary' => array(
			'a1'      => 'A1 - Başlangıç',
			'a1-plus' => 'A1+ - Başlangıç Üstü',
			'a2'      => 'A2 - Temel',
		),
		'ewt_secondary' => array(
			'a2'      => 'A2 - Temel',
			'b1-eksi' => 'B1- - Orta Altı',
			'b1'      => 'B1 - Orta',
		),
		'ewt_highschool' => array(
			'b1'      => 'B1 - Orta',
			'b2'      => 'B2 - Orta Üstü',
			'b2-plus' => 'B2+ - İleri Orta',
		),
		'ewt_ydt' => array(
			'ydt-40-60' => 'Başlangıç (Hedef 40-60 Net)',
			'ydt-60-80' => 'Orta (Hedef 60-80 Net)',
			'ydt-80p'   => 'İleri (Hedef 80+ Net)',
		),
		'ewt_toefl' => array(
			'toefl-60-80'  => 'Başlangıç (Hedef Skor 60-80)',
			'toefl-80-100' => 'Orta (Hedef Skor 80-100)',
			'toefl-100p'   => 'İleri (Hedef Skor 100+)',
		),
		'ewt_gre' => array(
			'gre-temel' => 'Temel Kelime Bilgisi',
			'gre-orta'  => 'Orta Düzey Analitik Okuma',
			'gre-ileri' => 'İleri Düzey Analitik Okuma',
		),
		'ewt_yds' => array(
			'yds-40-60' => 'Başlangıç (Hedef 40-60 Net)',
			'yds-60-80' => 'Orta (Hedef 60-80 Net)',
			'yds-80p'   => 'İleri (Hedef 80+ Net)',
		),
		'ewt_gmat' => array(
			'gmat-temel' => 'Temel Quant/Verbal',
			'gmat-orta'  => 'Orta Düzey',
			'gmat-ileri' => 'İleri Düzey (700+ Hedef)',
		),
		'ewt_ielts' => array(
			'ielts-5-0-5-5' => 'Başlangıç Üstü (Hedef Band 5.0-5.5)',
			'ielts-6-0-6-5' => 'Orta (Hedef Band 6.0-6.5)',
			'ielts-7-0p'    => 'İleri (Hedef Band 7.0+)',
		),
		'ewt_yokdil' => array(
			'yokdil-fen'    => 'Fen Bilimleri',
			'yokdil-saglik' => 'Sağlık Bilimleri',
			'yokdil-sosyal' => 'Sosyal Bilimler',
		),
		'ewt_mesleki' => array(
			'mesleki-business'    => 'Business İngilizcesi',
			'mesleki-hukuk'       => 'Hukuk İngilizcesi',
			'mesleki-tip'         => 'Tıp İngilizcesi',
			'mesleki-muhendislik' => 'Mühendislik İngilizcesi',
			'mesleki-bt'          => 'Bilgi Teknolojileri İngilizcesi',
			'mesleki-kariyer'     => 'İş Görüşmesi ve Kariyer İngilizcesi',
		),
	);

	// Dört beceri + Grammar/Vocabulary: tam CEFR skalası (A1-C2)
	foreach ( array( 'ewt_listening', 'ewt_speaking', 'ewt_reading', 'ewt_writing', 'ewt_vocabulary', 'ewt_grammar' ) as $skill_cpt ) {
		$map[ $skill_cpt ] = $cefr_full;
	}

	if ( isset( $map[ $post_type ] ) ) return $map[ $post_type ];

	return $cefr_full; // normal post veya tanımsız tür için varsayılan
}

/**
 * Tüm CPT'ler (+ post) için zorluk haritasını JS'e aktarılabilir dizi olarak döndürür.
 */
function ewt_all_difficulty_maps() {
	$out = array();
	foreach ( array_merge( array_keys( ewt_cpt_config() ), array( 'post' ) ) as $pt ) {
		$out[ $pt ] = ewt_difficulty_options_for_cpt( $pt );
	}
	return $out;
}

/**
 * Bir zorluk seçeneği dizisini <option> olarak yazdırır.
 */
function ewt_render_difficulty_select_options( $options, $selected = '' ) {
	foreach ( $options as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $selected, $val, false ), esc_html( $label ) );
	}
}

/**
 * Bir CPT'nin "Seviye" taxonomy'sinde SİZİN dashboard'dan önceden oluşturduğunuz
 * gerçek terimleri döndürür (örn. A1, A2, TOEFL 60-80 gibi kendi tanımladığınız kategoriler).
 * Bunlar, ewt_difficulty_options_for_cpt()'teki SABİT zorluk seçenekleriyle KARIŞTIRILMAMALI;
 * o fonksiyon sadece AI promptunun üslubunu ayarlar, bu fonksiyon ise gerçek taxonomy terimlerini okur.
 *
 * @return array [ [ 'slug' => ..., 'name' => ... ], ... ]
 */
function ewt_get_seviye_terms( $post_type ) {
	$tax = $post_type . '_seviye';
	if ( ! taxonomy_exists( $tax ) ) return array();

	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) return array();

	$out = array();
	foreach ( $terms as $term ) {
		$out[] = array( 'slug' => $term->slug, 'name' => $term->name );
	}
	return $out;
}

/**
 * Tüm CPT'ler için ewt_get_seviye_terms() sonucunu tek dizide toplar.
 * JS'e (bulk araçları) aktarılıp CPT seçildiğinde "mevcut seviyeler" anında gösterilir,
 * ekstra bir AJAX isteği gerekmez.
 */
function ewt_all_seviye_terms_map() {
	$out = array();
	foreach ( array_keys( ewt_cpt_config() ) as $slug ) {
		$out[ $slug ] = ewt_get_seviye_terms( $slug );
	}
	return $out;
}

/**
 * v1.6.4: YENİ SEVİYE TERİMLERİNİ OTOMATİK OLUŞTUR
 *
 * 3 yeni CPT (IELTS, YÖKDİL, Mesleki İngilizce) + mevcut Lise ve 4 beceri CPT'sine
 * (Writing/Reading/Listening/Speaking) eklenen K12-sonrası (üniversite/lisansüstü/
 * kariyer) seviye terimlerini, siz Dashboard'dan elle eklemeden hazır olarak kurar.
 * Var olan hiçbir terime dokunmaz, yalnızca eksik olanları ekler; tema güncellemesi
 * sonrası ilk admin sayfa yüklemesinde bir kereye mahsus çalışır.
 */
function ewt_seed_v164_seviye_terms() {
	if ( get_option( 'ewt_seviye_seeded_v164' ) ) return;

	$seed = array(
		'ewt_ielts_seviye' => array(
			'band-5-0-5-5'           => 'Band 5.0 - 5.5',
			'band-6-0-6-5'           => 'Band 6.0 - 6.5',
			'band-7-0-plus'          => 'Band 7.0+',
			'academic-modul'         => 'Academic Modül',
			'general-training-modul'=> 'General Training Modül',
		),
		'ewt_yokdil_seviye' => array(
			'yokdil-fen-bilimleri'    => 'YÖKDİL Fen Bilimleri',
			'yokdil-saglik-bilimleri' => 'YÖKDİL Sağlık Bilimleri',
			'yokdil-sosyal-bilimler'  => 'YÖKDİL Sosyal Bilimler',
		),
		'ewt_mesleki_seviye' => array(
			'business-ingilizcesi'                => 'Business İngilizcesi',
			'hukuk-ingilizcesi'                    => 'Hukuk İngilizcesi',
			'tip-ingilizcesi'                      => 'Tıp İngilizcesi',
			'muhendislik-ingilizcesi'              => 'Mühendislik İngilizcesi',
			'bilgi-teknolojileri-ingilizcesi'      => 'Bilgi Teknolojileri İngilizcesi',
			'is-gorusmesi-ve-kariyer-ingilizcesi'  => 'İş Görüşmesi ve Kariyer İngilizcesi',
		),
		// Mevcut CPT'lere ek seviyeler (var olan terimlere DOKUNULMAZ, sadece ekleniyor)
		'ewt_highschool_seviye' => array(
			'lise-hazirlik-sinifi'         => 'Lise Hazırlık Sınıfı',
			'ib-diploma-ingilizce'         => 'IB Diploma İngilizce',
			'kolej-giris-sinavi-ingilizce' => 'Kolej Giriş Sınavı İngilizce',
		),
		'ewt_writing_seviye'   => array( 'akademik-yazim-tez-makale'         => 'Akademik Yazım (Tez / Makale)' ),
		'ewt_reading_seviye'   => array( 'akademik-okuma-literatur-tarama'   => 'Akademik Okuma (Literatür Tarama)' ),
		'ewt_listening_seviye' => array( 'akademik-dinleme-ders-notu-tutma'  => 'Akademik Dinleme (Ders Notu Tutma)' ),
		'ewt_speaking_seviye'  => array( 'akademik-sunum-ve-seminer'         => 'Akademik Sunum ve Seminer' ),
	);

	$any_missing_tax = false;

	foreach ( $seed as $tax => $terms ) {
		if ( ! taxonomy_exists( $tax ) ) { $any_missing_tax = true; continue; }
		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, $tax ) ) {
				wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
			}
		}
	}

	// Taxonomy'ler henüz register olmadıysa (çok erken bir istekte yakalandıysak)
	// bayrağı işaretleme, bir sonraki yüklemede tekrar denesin.
	if ( ! $any_missing_tax ) {
		update_option( 'ewt_seviye_seeded_v164', 1 );
	}
}
add_action( 'init', 'ewt_seed_v164_seviye_terms', 20 );

