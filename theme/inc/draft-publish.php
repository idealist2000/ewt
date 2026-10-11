<?php
/**
 * TASLAK YÖNETİMİ (v1.6.3)
 *
 * Toplu İçerik Üretimi aracının "Yayın Durumu" alanı varsayılan olarak "Taslak"
 * seçilidir (gözden geçirmeden yayınlamayı önlemek için). Bu, hızlı büyüyen
 * sitelerde binlerce içeriğin fark edilmeden draft'ta birikmesine yol açabilir —
 * ve Rank Math sitemap'i yalnızca `publish` durumundaki içerikleri listeler.
 *
 * Bu araç:
 * 1) Her içerik türü için Yayınlanmış / Taslak sayısını gösterir (asıl teşhis).
 * 2) Seçili içerik türündeki (veya tümündeki) taslakları, isteğe bağlı tarih
 *    aralığı / seviye filtresiyle, tarayıcıdan partiler hâlinde (zaman aşımı
 *    olmadan) toplu yayınlar.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------
 * MENÜ
 * ------------------------------------------------- */
function ewt_draft_publish_menu() {
	add_submenu_page(
		'ewt-bulk-content',
		'Taslak Yönetimi',
		'Taslak Yönetimi',
		'manage_options',
		'ewt-draft-publish',
		'ewt_draft_publish_page_html'
	);
}
add_action( 'admin_menu', 'ewt_draft_publish_menu', 21 );

/* -------------------------------------------------
 * SAYFA
 * ------------------------------------------------- */
function ewt_draft_publish_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$types = ewt_url_export_post_types(); // inc/url-export.php içinde tanımlı
	?>
	<div class="wrap">
		<h1>📝 Taslak Yönetimi</h1>
		<p><strong>Neden önemli:</strong> Toplu İçerik Üretimi aracında "Yayın Durumu" varsayılan olarak <em>Taslak</em>'tır. Bu alanı her çalıştırmada elle "Doğrudan Yayınla" yapmadıysanız, üretilen içerikler taslakta kalır — Rank Math sitemap'i yalnızca <strong>yayınlanmış</strong> içerikleri listeler, dolayısıyla bu içerikler Google'a hiç bildirilmez.</p>

		<h2 class="title">📊 Yayınlanmış / Taslak Dağılımı</h2>
		<table class="widefat striped" style="max-width:760px">
			<thead>
				<tr>
					<th>İçerik Türü</th>
					<th style="width:130px">Yayınlanmış</th>
					<th style="width:130px">Taslak</th>
				</tr>
			</thead>
			<tbody id="ewt-draft-counts-body">
			<?php
			$toplam_pub = 0; $toplam_draft = 0;
			foreach ( $types as $slug => $label ) :
				$counts  = wp_count_posts( $slug );
				$publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
				$draft   = isset( $counts->draft ) ? (int) $counts->draft : 0;
				$toplam_pub   += $publish;
				$toplam_draft += $draft;
				?>
				<tr data-pt="<?php echo esc_attr( $slug ); ?>">
					<td><strong><?php echo esc_html( $label ); ?></strong> <code><?php echo esc_html( $slug ); ?></code></td>
					<td class="ewt-pub-count"><?php echo esc_html( number_format_i18n( $publish ) ); ?></td>
					<td class="ewt-draft-count" style="<?php echo $draft > 0 ? 'color:#b32d2e;font-weight:600' : ''; ?>"><?php echo esc_html( number_format_i18n( $draft ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th>TOPLAM</th>
					<th id="ewt-total-pub"><?php echo esc_html( number_format_i18n( $toplam_pub ) ); ?></th>
					<th id="ewt-total-draft"><?php echo esc_html( number_format_i18n( $toplam_draft ) ); ?></th>
				</tr>
			</tfoot>
		</table>

		<h2 class="title" style="margin-top:32px">🚀 Taslakları Toplu Yayınla</h2>
		<p>İçerikleri incelediniz ve kaliteden eminseniz, aşağıdan seçtiğiniz türdeki taslakları tek seferde yayınlayabilirsiniz. İşlem tarayıcıdan partiler hâlinde (varsayılan 200/istek) çalışır, binlerce taslakta bile zaman aşımına takılmaz — sayfayı kapatmadan bekleyin.</p>

		<table class="form-table" style="max-width:760px">
			<tr>
				<th>İçerik Türü</th>
				<td>
					<select id="ewt_dp_post_type">
						<option value="">— Seçiniz —</option>
						<option value="all">Tümü (bütün içerik türleri, sırayla)</option>
						<?php foreach ( $types as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th>Seviye (isteğe bağlı)</th>
				<td>
					<input type="text" id="ewt_dp_seviye" class="regular-text" placeholder="ör. a1-seviyesi-listening">
					<p class="description">Yalnızca tek bir içerik türü seçtiyseniz kullanılır. Boş = tüm seviyeler.</p>
				</td>
			</tr>
			<tr>
				<th>Tarih Aralığı (isteğe bağlı)</th>
				<td>
					<input type="date" id="ewt_dp_date_from"> — <input type="date" id="ewt_dp_date_to">
					<p class="description">Yalnızca bu tarihler arasında oluşturulmuş taslakları yayınlar. Boş = tüm taslaklar.</p>
				</td>
			</tr>
			<tr>
				<th>Parti Boyutu</th>
				<td>
					<input type="number" id="ewt_dp_batch" value="200" min="20" max="500" step="10" style="width:100px">
					<p class="description">Her istekte kaç taslağın yayınlanacağı. Sunucunuz güçlüyse artırabilirsiniz.</p>
				</td>
			</tr>
			<tr>
				<th>Onay</th>
				<td>
					<label><input type="checkbox" id="ewt_dp_confirm"> İçerikleri gözden geçirmeden toplu yayınlayacağımı onaylıyorum.</label>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" class="button button-primary button-hero" id="ewt_dp_start" disabled>▶ Taslakları Yayınla</button>
			<button type="button" class="button button-hero" id="ewt_dp_stop" style="display:none">⏹ Durdur</button>
		</p>

		<div id="ewt_dp_progress_wrap" style="display:none;max-width:760px">
			<div style="background:#e2e4e7;border-radius:4px;overflow:hidden;height:22px">
				<div id="ewt_dp_progress_bar" style="background:#2271b1;height:100%;width:0%;transition:width .2s"></div>
			</div>
			<p id="ewt_dp_progress_text">Başlatılıyor…</p>
		</div>

		<div id="ewt_dp_log" style="max-width:760px;margin-top:12px;font-family:monospace;font-size:12px;max-height:200px;overflow:auto;background:#f6f7f7;border:1px solid #dcdcde;padding:8px;display:none"></div>
	</div>
	<?php
}

/* -------------------------------------------------
 * ORTAK: filtreli WP_Query argümanları
 * ------------------------------------------------- */
function ewt_draft_publish_build_args( $post_type, $data, $per_page ) {
	$args = array(
		'post_type'              => $post_type,
		'post_status'            => 'draft',
		'posts_per_page'         => $per_page,
		'orderby'                => 'date',
		'order'                  => 'ASC',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'suppress_filters'       => true,
	);

	$date_from = isset( $data['date_from'] ) ? preg_replace( '/[^0-9\-]/', '', wp_unslash( $data['date_from'] ) ) : '';
	$date_to   = isset( $data['date_to'] ) ? preg_replace( '/[^0-9\-]/', '', wp_unslash( $data['date_to'] ) ) : '';
	if ( $date_from || $date_to ) {
		$dq = array( 'inclusive' => true );
		if ( $date_from ) $dq['after']  = $date_from;
		if ( $date_to )   $dq['before'] = $date_to . ' 23:59:59';
		$args['date_query'] = array( $dq );
	}

	$seviye = isset( $data['seviye'] ) ? sanitize_title( wp_unslash( $data['seviye'] ) ) : '';
	$tax    = ewt_url_export_seviye_tax( $post_type );
	if ( $seviye && $tax ) {
		$args['tax_query'] = array(
			array( 'taxonomy' => $tax, 'field' => 'slug', 'terms' => $seviye ),
		);
	}

	return $args;
}

/* -------------------------------------------------
 * AJAX: filtrelenmiş taslak sayısını döndür (ilerleme çubuğu için tahmini toplam)
 * ------------------------------------------------- */
function ewt_ajax_count_drafts() {
	check_ajax_referer( 'ewt_bulk_tools', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! post_type_exists( $post_type ) ) wp_send_json_error( 'Geçersiz içerik türü.' );

	$args = ewt_draft_publish_build_args( $post_type, $_POST, -1 );
	$args['no_found_rows'] = false;

	$q = new WP_Query( $args );
	wp_send_json_success( array( 'total' => (int) $q->post_count ) );
}
add_action( 'wp_ajax_ewt_count_drafts', 'ewt_ajax_count_drafts' );

/* -------------------------------------------------
 * AJAX: bir parti taslağı yayınla
 * ------------------------------------------------- */
function ewt_ajax_publish_drafts_batch() {
	check_ajax_referer( 'ewt_bulk_tools', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$batch     = isset( $_POST['batch'] ) ? max( 20, min( 500, intval( $_POST['batch'] ) ) ) : 200;

	if ( ! post_type_exists( $post_type ) ) wp_send_json_error( 'Geçersiz içerik türü.' );

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	$args = ewt_draft_publish_build_args( $post_type, $_POST, $batch );
	$ids  = get_posts( $args );

	$published = 0;
	$failed    = 0;
	foreach ( $ids as $id ) {
		$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $result ) ) {
			$failed++;
		} else {
			$published++;
		}
	}

	wp_send_json_success( array(
		'processed' => count( $ids ),
		'published' => $published,
		'failed'    => $failed,
		'batch'     => $batch,
		'done'      => count( $ids ) < $batch, // bu partide $batch'ten az geldiyse taslak kalmamış demektir
	) );
}
add_action( 'wp_ajax_ewt_publish_drafts_batch', 'ewt_ajax_publish_drafts_batch' );

/* -------------------------------------------------
 * JS + lokalizasyon
 * ------------------------------------------------- */
function ewt_enqueue_draft_publish_js( $hook ) {
	if ( strpos( $hook, 'ewt-draft-publish' ) === false ) return;

	wp_enqueue_script( 'ewt-draft-publish', EWT_URI . '/assets/js/admin-draft-publish.js', array( 'jquery' ), EWT_VERSION, true );

	$types = ewt_url_export_post_types();
	wp_localize_script( 'ewt-draft-publish', 'ewtDraftPublish', array(
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'ewt_bulk_tools' ),
		'postTypes' => array_keys( $types ),
		'labels'    => $types,
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_enqueue_draft_publish_js' );
