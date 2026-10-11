<?php
/**
 * GELİŞMİŞ SİTE İÇİ ARAMA MOTORU
 *
 * 1.000 - 10.000+ içerikte hızlı çalışması için wp_posts üzerinde canlı LIKE/JOIN
 * yapmak yerine, ayrı ve hafif bir "arama indeksi" tablosu kullanılır
 * ( {$wpdb->prefix}ewt_search_index ). Her yazı yayınlandığında/güncellendiğinde
 * bu tabloya otomatik olarak (başlık, özet, düz metin içerik, seviye, CPT etiketi,
 * görsel, link) yazılır. Ziyaretçi arama kutusuna birkaç harf yazdığında REST API
 * üzerinden bu tabloya sorgu atılır — binlerce içerikte bile milisaniyeler içinde
 * cevap döner.
 *
 * Bileşenler:
 * - Tablo oluşturma / sürüm yönetimi
 * - Otomatik indeksleme kancaları (yayınla / güncelle / sil / çöpe taşı / seviye ata)
 * - Toplu "İndeksi Yeniden Oluştur" AJAX motoru (Site Arama admin sayfası)
 * - Canlı arama için REST endpoint: GET /wp-json/ewt/v1/search?q=...
 * - Ön yüz varlıkları (site-search.css/js) + arama kutusu için gerekli veriler
 * - "Tüm sonuçları gör" tam sayfası search.php bu dosyadaki ewt_search_query()'i kullanır
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EWT_SEARCH_DB_VERSION', '1.0.0' );

/* =========================================================
 * 1) TABLO ADI / OLUŞTURMA / SÜRÜM YÖNETİMİ
 * ========================================================= */

function ewt_search_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'ewt_search_index';
}

/**
 * Tabloyu (yoksa) oluşturur, (şeması değiştiyse) günceller.
 * Tema bir eklenti olmadığı için "activation hook" yerine her `init`'te
 * hafif bir sürüm kontrolü yapılır; sürüm eşleşiyorsa hiçbir şey yapmaz.
 */
function ewt_search_maybe_upgrade_db() {
	if ( get_option( 'ewt_search_db_version' ) === EWT_SEARCH_DB_VERSION ) return;

	global $wpdb;
	$table           = ewt_search_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	// dbDelta biçim kurallarına uyulmalı: her sütun kendi satırında,
	// "PRIMARY KEY" ile parantez arasında TAM OLARAK iki boşluk.
	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL,
		post_type VARCHAR(32) NOT NULL DEFAULT '',
		title TEXT NOT NULL,
		excerpt TEXT NOT NULL,
		content_text LONGTEXT NOT NULL,
		seviye VARCHAR(255) NOT NULL DEFAULT '',
		cpt_label VARCHAR(120) NOT NULL DEFAULT '',
		url VARCHAR(500) NOT NULL DEFAULT '',
		thumbnail_url VARCHAR(500) NOT NULL DEFAULT '',
		post_date DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
		updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
		PRIMARY KEY  (id),
		KEY post_type (post_type),
		KEY post_date (post_date)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'ewt_search_db_version', EWT_SEARCH_DB_VERSION );
}
add_action( 'init', 'ewt_search_maybe_upgrade_db', 5 );

/* =========================================================
 * 2) İNDEKSLENECEK İÇERİK TÜRLERİ
 * ========================================================= */

/**
 * Varsayılan olarak TÜM CPT'ler + normal yazılar + sayfalar indekslenir.
 * Site Arama > Ayarlar sayfasından belirli türler hariç bırakılabilir.
 */
function ewt_search_all_possible_post_types() {
	return array_merge( array_keys( ewt_cpt_config() ), array( 'post', 'page' ) );
}

function ewt_search_indexable_post_types() {
	$all   = ewt_search_all_possible_post_types();
	$saved = get_option( 'ewt_search_indexable_types', $all );

	if ( ! is_array( $saved ) || empty( $saved ) ) {
		$saved = $all;
	}

	$types = array_values( array_intersect( $all, $saved ) );
	if ( empty( $types ) ) $types = $all;

	return apply_filters( 'ewt_search_indexable_post_types', $types );
}

/**
 * Bir post_type için insan-okunur etiket (arama sonuçlarında rozet olarak gösterilir).
 */
function ewt_search_cpt_label( $post_type ) {
	if ( $post_type === 'post' ) return 'Blog Yazısı';
	if ( $post_type === 'page' ) return 'Sayfa';

	$cfg = ewt_cpt_config();
	if ( isset( $cfg[ $post_type ] ) ) return $cfg[ $post_type ][1];

	$obj = get_post_type_object( $post_type );
	return $obj ? $obj->labels->singular_name : ucfirst( $post_type );
}

/* =========================================================
 * 3) TEK YAZIYI İNDEKSLEME / SİLME
 * ========================================================= */

function ewt_search_index_post( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

	$post = get_post( $post_id );
	if ( ! $post ) return;

	$indexable_types = ewt_search_indexable_post_types();

	// Tür indekslenebilir listede değilse VEYA yayınlanmamışsa VEYA şifre korumalıysa: indeksten çıkar.
	if ( ! in_array( $post->post_type, $indexable_types, true )
		|| $post->post_status !== 'publish'
		|| ! empty( $post->post_password )
	) {
		ewt_search_delete_post( $post_id );
		return;
	}

	global $wpdb;
	$table = ewt_search_table_name();

	$title = wp_strip_all_tags( get_the_title( $post ) );

	$content_text = strip_shortcodes( $post->post_content );
	$content_text = wp_strip_all_tags( $content_text );
	$content_text = preg_replace( '/\s+/u', ' ', $content_text );
	$content_text = trim( $content_text );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $content_text ) > 10000 ) {
		$content_text = mb_substr( $content_text, 0, 10000 );
	} elseif ( strlen( $content_text ) > 10000 ) {
		$content_text = substr( $content_text, 0, 10000 );
	}

	$excerpt = $post->post_excerpt
		? wp_strip_all_tags( $post->post_excerpt )
		: wp_trim_words( $content_text, 30, '…' );

	$seviye_names = array();
	$tax = $post->post_type . '_seviye';
	if ( taxonomy_exists( $tax ) ) {
		$terms = get_the_terms( $post_id, $tax );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) $seviye_names[] = $t->name;
		}
	}

	$thumb = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'thumbnail' ) : '';

	$data = array(
		'id'            => (int) $post_id,
		'post_type'     => $post->post_type,
		'title'         => $title,
		'excerpt'       => $excerpt,
		'content_text'  => $content_text,
		'seviye'        => implode( ', ', $seviye_names ),
		'cpt_label'     => ewt_search_cpt_label( $post->post_type ),
		'url'           => get_permalink( $post_id ),
		'thumbnail_url' => $thumb,
		'post_date'     => $post->post_date,
		'updated_at'    => current_time( 'mysql' ),
	);

	$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

	$wpdb->replace( $table, $data, $formats );
}

function ewt_search_delete_post( $post_id ) {
	global $wpdb;
	$wpdb->delete( ewt_search_table_name(), array( 'id' => (int) $post_id ), array( '%d' ) );
}

/* -----------------------------------------------------------
 * Kancalar: yayınlama / durum değişikliği / silme / seviye ataması
 * ----------------------------------------------------------- */

/**
 * Tüm durum geçişlerini (taslak→yayın, yayın→taslak, yayın→çöp, zamanlanmış→yayın vb.)
 * tek bir kancadan yakalar; save_post + trash + untrash için ayrı ayrı kanca gerekmez.
 */
function ewt_search_on_transition_status( $new_status, $old_status, $post ) {
	if ( ! $post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) return;

	if ( $new_status === 'publish' ) {
		ewt_search_index_post( $post->ID );
	} elseif ( $old_status === 'publish' && $new_status !== 'publish' ) {
		ewt_search_delete_post( $post->ID );
	}
}
add_action( 'transition_post_status', 'ewt_search_on_transition_status', 10, 3 );

/**
 * Kalıcı silme (çöp kutusundan tamamen sil / wp_delete_post) durumunda indeksten temizle.
 */
add_action( 'before_delete_post', 'ewt_search_delete_post' );

/**
 * "Seviye" taksonomi terimleri çoğu zaman yazı YAYINLANDIKTAN SONRA atanır
 * (örn. Toplu Üretim aracı önce wp_insert_post, sonra wp_set_object_terms çağırır).
 * Bu yüzden seviye ataması/değişikliği de indeksi tazelemeli.
 */
function ewt_search_on_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( substr( $taxonomy, -7 ) !== '_seviye' ) return;
	$post = get_post( $object_id );
	if ( $post && $post->post_status === 'publish' ) {
		ewt_search_index_post( $object_id );
	}
}
add_action( 'set_object_terms', 'ewt_search_on_set_object_terms', 10, 4 );

/* =========================================================
 * 4) TOPLU İNDEKS OLUŞTURMA (admin sayfasındaki AJAX motoru için)
 * ========================================================= */

function ewt_search_count_indexed() {
	global $wpdb;
	$table = ewt_search_table_name();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
}

function ewt_search_count_indexable_total() {
	$types = ewt_search_indexable_post_types();
	$total = 0;
	foreach ( $types as $t ) {
		$counts = wp_count_posts( $t );
		if ( $counts && isset( $counts->publish ) ) {
			$total += (int) $counts->publish;
		}
	}
	return $total;
}

function ewt_search_rebuild_batch( $offset, $batch_size ) {
	$types = ewt_search_indexable_post_types();

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

	foreach ( $query->posts as $pid ) {
		ewt_search_index_post( $pid );
	}

	return array(
		'processed_in_batch' => count( $query->posts ),
		'total'              => (int) $query->found_posts,
	);
}

function ewt_ajax_search_rebuild_batch() {
	check_ajax_referer( 'ewt_search_index', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$offset     = isset( $_POST['offset'] ) ? max( 0, intval( $_POST['offset'] ) ) : 0;
	$batch_size = isset( $_POST['batch_size'] ) ? max( 10, min( 200, intval( $_POST['batch_size'] ) ) ) : 100;

	$result      = ewt_search_rebuild_batch( $offset, $batch_size );
	$next_offset = $offset + $result['processed_in_batch'];
	$done        = ( $result['processed_in_batch'] === 0 ) || ( $next_offset >= $result['total'] );

	wp_send_json_success( array(
		'processed'   => $result['processed_in_batch'],
		'total'       => $result['total'],
		'next_offset' => $next_offset,
		'done'        => $done,
	) );
}
add_action( 'wp_ajax_ewt_search_rebuild_batch', 'ewt_ajax_search_rebuild_batch' );

function ewt_ajax_search_clear_index() {
	check_ajax_referer( 'ewt_search_index', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	global $wpdb;
	$wpdb->query( 'TRUNCATE TABLE ' . ewt_search_table_name() ); // phpcs:ignore -- tablo adı sabit, kullanıcı girdisi değil
	wp_send_json_success();
}
add_action( 'wp_ajax_ewt_search_clear_index', 'ewt_ajax_search_clear_index' );

/* =========================================================
 * 5) ARAMA SORGU MOTORU (hem canlı REST hem tam sonuç sayfası kullanır)
 * ========================================================= */

/**
 * @param string $term  Arama kelimesi.
 * @param array  $args  ['limit' => int, 'offset' => int]
 * @return array { 'rows' => object[], 'total' => int }
 */
function ewt_search_query( $term, $args = array() ) {
	global $wpdb;
	$table = ewt_search_table_name();

	$args = wp_parse_args( $args, array( 'limit' => 8, 'offset' => 0 ) );
	$limit  = max( 1, min( 50, (int) $args['limit'] ) );
	$offset = max( 0, (int) $args['offset'] );

	$term = trim( wp_strip_all_tags( (string) $term ) );
	if ( $term === '' ) {
		return array( 'rows' => array(), 'total' => 0 );
	}

	$like_contains = '%' . $wpdb->esc_like( $term ) . '%';
	$like_prefix   = $wpdb->esc_like( $term ) . '%';
	$like_word     = '% ' . $wpdb->esc_like( $term ) . '%';

	$sql = "
		SELECT id, post_type, title, excerpt, content_text, seviye, cpt_label, url, thumbnail_url, post_date,
			(
				(CASE WHEN title LIKE %s THEN 100
				      WHEN title LIKE %s THEN 70
				      WHEN title LIKE %s THEN 45
				      ELSE 0 END)
				+ (CASE WHEN seviye LIKE %s THEN 20 ELSE 0 END)
				+ (CASE WHEN excerpt LIKE %s THEN 12 ELSE 0 END)
				+ (CASE WHEN content_text LIKE %s THEN 8 ELSE 0 END)
			) AS relevance
		FROM {$table}
		WHERE title LIKE %s OR content_text LIKE %s OR excerpt LIKE %s OR seviye LIKE %s
		ORDER BY relevance DESC, post_date DESC
		LIMIT %d OFFSET %d
	";

	$prepared = $wpdb->prepare( $sql, array(
		$like_prefix, $like_word, $like_contains, // title CASE (prefix / kelime başı / içerir)
		$like_contains,                            // seviye CASE
		$like_contains,                            // excerpt CASE
		$like_contains,                            // content_text CASE
		$like_contains, $like_contains, $like_contains, $like_contains, // WHERE
		$limit, $offset,
	) );

	$rows = $wpdb->get_results( $prepared );

	$count_sql = "
		SELECT COUNT(*) FROM {$table}
		WHERE title LIKE %s OR content_text LIKE %s OR excerpt LIKE %s OR seviye LIKE %s
	";
	$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $like_contains, $like_contains, $like_contains, $like_contains ) );

	return array( 'rows' => $rows, 'total' => $total );
}

/**
 * content_text içinde arama kelimesinin geçtiği yeri bulup etrafından kısa bir alıntı çıkarır.
 * Eşleşme bulunamazsa (örn. sadece seviye alanında eşleşti) $fallback döner.
 */
function ewt_search_build_snippet( $content, $q, $fallback = '', $radius = 60 ) {
	if ( ! $content || $q === '' ) return $fallback;

	$pos = mb_stripos( $content, $q );
	if ( $pos === false ) {
		return $fallback !== '' ? $fallback : wp_trim_words( $content, 20, '…' );
	}

	$start  = max( 0, $pos - $radius );
	$length = mb_strlen( $q ) + ( $radius * 2 );
	$snippet = trim( mb_substr( $content, $start, $length ) );

	$prefix = $start > 0 ? '… ' : '';
	$suffix = ( $start + $length ) < mb_strlen( $content ) ? ' …' : '';

	return $prefix . $snippet . $suffix;
}

function ewt_search_format_row_for_api( $row, $q ) {
	$match_in_title = ( mb_stripos( $row->title, $q ) !== false );
	$snippet        = $match_in_title ? $row->excerpt : ewt_search_build_snippet( $row->content_text, $q, $row->excerpt );

	return array(
		'id'        => (int) $row->id,
		'title'     => $row->title,
		'excerpt'   => $snippet,
		'url'       => $row->url,
		'cpt_label' => $row->cpt_label,
		'seviye'    => $row->seviye,
		'thumbnail' => $row->thumbnail_url,
	);
}

/* =========================================================
 * 6) REST API — CANLI ARAMA (Google tarzı otomatik tamamlama)
 * ========================================================= */

function ewt_search_register_rest_routes() {
	register_rest_route( 'ewt/v1', '/search', array(
		'methods'             => 'GET',
		'callback'            => 'ewt_rest_live_search',
		'permission_callback' => '__return_true',
		'args'                => array(
			'q' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		),
	) );
}
add_action( 'rest_api_init', 'ewt_search_register_rest_routes' );

function ewt_rest_live_search( $request ) {
	$q = $request->get_param( 'q' );
	$q = is_string( $q ) ? trim( $q ) : '';

	$min_chars = max( 1, (int) get_option( 'ewt_search_min_chars', 2 ) );

	if ( $q === '' || mb_strlen( $q ) < $min_chars ) {
		return rest_ensure_response( array( 'results' => array(), 'total' => 0, 'query' => $q ) );
	}

	$result_count = max( 3, (int) get_option( 'ewt_search_result_count', 8 ) );
	$data = ewt_search_query( $q, array( 'limit' => $result_count ) );

	$results = array();
	foreach ( $data['rows'] as $row ) {
		$results[] = ewt_search_format_row_for_api( $row, $q );
	}

	$response = rest_ensure_response( array(
		'results'         => $results,
		'total'           => $data['total'],
		'query'           => $q,
		'search_page_url' => add_query_arg( 's', rawurlencode( $q ), home_url( '/' ) ),
	) );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/* =========================================================
 * 7) ÖN YÜZ VARLIKLARI (arama kutusu CSS/JS — tüm sayfalarda yüklenir)
 * ========================================================= */

function ewt_enqueue_site_search_assets() {
	wp_enqueue_style( 'ewt-site-search', EWT_URI . '/assets/css/site-search.css', array(), EWT_VERSION );
	wp_enqueue_script( 'ewt-site-search', EWT_URI . '/assets/js/site-search.js', array(), EWT_VERSION, true );

	wp_localize_script( 'ewt-site-search', 'ewtSearch', array(
		'restUrl'  => esc_url_raw( rest_url( 'ewt/v1/search' ) ),
		'homeUrl'  => esc_url_raw( home_url( '/' ) ),
		'minChars' => max( 1, (int) get_option( 'ewt_search_min_chars', 2 ) ),
		'i18n'     => array(
			'placeholder'  => 'Ne öğrenmek istersin? Örn: “present perfect”, “a1 kelimeler”…',
			'noResults'    => 'Sonuç bulunamadı. Farklı bir kelime dener misin?',
			'typeMore'     => 'Aramak için en az ' . max( 1, (int) get_option( 'ewt_search_min_chars', 2 ) ) . ' karakter yazın.',
			'viewAll'      => 'Tüm sonuçları gör →',
			'loading'      => 'Aranıyor…',
			'resultsFound' => 'sonuç bulundu',
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'ewt_enqueue_site_search_assets' );

/* =========================================================
 * 8) YÖNETİM PANELİ: "🔍 Site Arama" SAYFASI
 * ========================================================= */

function ewt_search_admin_menu() {
	add_menu_page(
		'Site İçi Arama',
		'🔍 Site Arama',
		'manage_options',
		'ewt-search-index',
		'ewt_search_admin_page_html',
		'dashicons-search',
		58
	);
}
add_action( 'admin_menu', 'ewt_search_admin_menu' );

function ewt_search_sanitize_types( $value ) {
	if ( ! is_array( $value ) ) return array();
	$valid = ewt_search_all_possible_post_types();
	return array_values( array_intersect( $valid, array_map( 'sanitize_text_field', $value ) ) );
}

function ewt_search_register_settings() {
	register_setting( 'ewt_search_settings_group', 'ewt_search_min_chars', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'ewt_search_settings_group', 'ewt_search_result_count', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'ewt_search_settings_group', 'ewt_search_indexable_types', array( 'sanitize_callback' => 'ewt_search_sanitize_types' ) );
}
add_action( 'admin_init', 'ewt_search_register_settings' );

function ewt_search_admin_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$indexed_count   = ewt_search_count_indexed();
	$total_count     = ewt_search_count_indexable_total();
	$indexable_types = ewt_search_indexable_post_types();

	$type_labels = array( 'post' => 'Normal Blog Yazısı', 'page' => 'Sayfa' );
	foreach ( ewt_cpt_config() as $slug => $d ) $type_labels[ $slug ] = $d[1];
	?>
	<div class="wrap">
		<h1>🔍 Site İçi Arama Motoru</h1>
		<p>Ziyaretçiler birkaç harf yazar yazmaz sitenizdeki içerikler arasından anında sonuç gösteren, Google tarzı otomatik tamamlamalı bir arama kutusu artık sitenizin her sayfasında aktif (sağ üstteki 🔍 simgesi). Bu sayfadan indeksi yönetebilir ve arama davranışını ayarlayabilirsiniz.</p>

		<div class="ewt-search-stats-box">
			<div class="ewt-search-stat"><strong><?php echo (int) $indexed_count; ?></strong><span>İndekslenmiş İçerik</span></div>
			<div class="ewt-search-stat"><strong><?php echo (int) $total_count; ?></strong><span>Yayınlanmış Toplam İçerik</span></div>
		</div>

		<?php if ( $indexed_count === 0 ) : ?>
			<div class="notice notice-warning"><p>⚠️ İndeks henüz boş görünüyor. Arama kutusunun çalışması için aşağıdan <strong>“İndeksi Yeniden Oluştur”</strong>a tıklayın (bir kereye mahsus; işlem içerik sayınıza göre birkaç dakika sürebilir).</p></div>
		<?php elseif ( $indexed_count < $total_count ) : ?>
			<div class="notice notice-info"><p>ℹ️ İndekste <?php echo (int) ( $total_count - $indexed_count ); ?> içerik eksik görünüyor. Yeni eklenen/düzenlenen içerikler otomatik indekslenir; eksikler muhtemelen bu özellik eklenmeden önce yayınlanmış yazılardır — “İndeksi Yeniden Oluştur” ile tamamlayabilirsiniz.</p></div>
		<?php endif; ?>

		<h2 class="title">İndeks Yönetimi</h2>
		<p class="description">İlk kurulumdan sonra veya toplu içerik/CSV içe aktarımlarından sonra çalıştırın. Yaklaşık <?php echo (int) $total_count; ?> içerik için birkaç dakika, 10.000 içerikte biraz daha uzun sürebilir — işlem bitene kadar bu sekmede kalın.</p>
		<p>
			<button type="button" class="button button-primary button-hero" id="ewt_search_rebuild_btn">🔄 İndeksi Yeniden Oluştur</button>
			<span id="ewt_search_rebuild_status" style="margin-left:12px;font-weight:600;"></span>
		</p>
		<div class="ewt-search-progress-wrap" id="ewt_search_progress_wrap" style="display:none;">
			<div class="ewt-search-progress-bar" id="ewt_search_progress_bar"></div>
		</div>

		<hr />

		<h2 class="title">Ayarlar</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'ewt_search_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ewt_search_min_chars">Minimum Karakter</label></th>
					<td>
						<input type="number" min="1" max="5" name="ewt_search_min_chars" id="ewt_search_min_chars" value="<?php echo esc_attr( get_option( 'ewt_search_min_chars', 2 ) ); ?>" class="small-text" />
						<p class="description">Ziyaretçi kaç karakter yazınca sonuçlar belirmeye başlasın (önerilen: 2).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ewt_search_result_count">Açılır Listede Gösterilecek Sonuç</label></th>
					<td>
						<input type="number" min="3" max="15" name="ewt_search_result_count" id="ewt_search_result_count" value="<?php echo esc_attr( get_option( 'ewt_search_result_count', 8 ) ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">İndekslenecek İçerik Türleri</th>
					<td>
						<input type="hidden" name="ewt_search_indexable_types[]" value="" />
						<?php foreach ( $type_labels as $slug => $label ) : ?>
							<label style="display:inline-block;min-width:220px;margin:2px 0;">
								<input type="checkbox" name="ewt_search_indexable_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $indexable_types, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">Değişiklik yaptıktan sonra kaydedip yukarıdan indeksi yeniden oluşturun.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Ayarları Kaydet' ); ?>
		</form>
	</div>
	<?php
}

function ewt_search_enqueue_admin_assets( $hook ) {
	if ( strpos( $hook, 'ewt-search-index' ) === false ) return;

	wp_enqueue_script( 'ewt-search-admin', EWT_URI . '/assets/js/admin-search-index.js', array(), EWT_VERSION, true );

	wp_localize_script( 'ewt-search-admin', 'ewtSearchAdmin', array(
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'ewt_search_index' ),
		'batchSize' => 100,
		'i18n'      => array(
			'starting' => 'Başlatılıyor…',
			'done'     => 'Tamamlandı ✅',
			'progress' => 'İndeksleniyor',
			'error'    => 'Bir hata oluştu, lütfen tekrar deneyin.',
			'confirm'  => 'İndeks sıfırdan yeniden oluşturulacak. Devam edilsin mi?',
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_search_enqueue_admin_assets' );
