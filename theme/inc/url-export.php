<?php
/**
 * URL DIŞA AKTARMA (v1.6.2)
 *
 * Sitedeki tüm yayınlanmış içeriklerin URL'lerini kategorize (CPT + seviye) biçimde
 * CSV veya düz TXT olarak dışa aktaran dashboard aracı.
 *
 * - 50.000+ içerik için tasarlandı: veriler 500'lük partiler hâlinde akış (stream)
 *   olarak gönderilir, bellek tüketimi sabit kalır.
 * - Indexleme araçlarının (Google Indexing API scriptleri, SpeedyIndex, IndexNow vb.)
 *   parti limitlerine uymak için "Başlangıç / Maks. URL" alanlarıyla parçalı dışa
 *   aktarım yapılabilir (ör. 0-10000, 10000-20000 ...).
 * - CSV sütunları: url, baslik, icerik_turu, seviye, yayin_tarihi, guncelleme_tarihi
 * - TXT formatı: satır başına yalnızca 1 URL (çoğu indexleme aracının istediği format).
 * - İsteğe bağlı olarak CPT arşiv sayfaları ve seviye (taksonomi) arşiv URL'leri de eklenir.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------
 * MENÜ
 * ------------------------------------------------- */
function ewt_url_export_menu() {
	add_submenu_page(
		'ewt-bulk-content',
		'URL Dışa Aktarma',
		'URL Dışa Aktarma',
		'manage_options',
		'ewt-url-export',
		'ewt_url_export_page_html'
	);
}
add_action( 'admin_menu', 'ewt_url_export_menu', 20 );

/**
 * Dışa aktarılabilir tüm içerik türleri: 15 CPT + normal yazı + sayfa.
 * Dönen dizi: slug => görünen ad
 */
function ewt_url_export_post_types() {
	$types = array();
	foreach ( ewt_cpt_config() as $slug => $data ) {
		$types[ $slug ] = $data[1];
	}
	$types['post'] = 'Normal Blog Yazısı';
	$types['page'] = 'Sayfa';
	return $types;
}

/**
 * Bir içerik türünün seviye taksonomisi adı (varsa).
 */
function ewt_url_export_seviye_tax( $post_type ) {
	$tax = $post_type . '_seviye';
	return taxonomy_exists( $tax ) ? $tax : '';
}

/* -------------------------------------------------
 * SAYFA
 * ------------------------------------------------- */
function ewt_url_export_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$types      = ewt_url_export_post_types();
	$action_url = admin_url( 'admin-post.php' );
	?>
	<div class="wrap">
		<h1>🔗 URL Dışa Aktarma</h1>
		<p>Sitedeki yayınlanmış içeriklerin URL'lerini <strong>kategorize biçimde</strong> (içerik türü + seviye sütunlarıyla) CSV olarak indirin. Indexleme araçlarına göndermek için <strong>TXT (satır başına 1 URL)</strong> formatını da seçebilirsiniz. Büyük siteler için dışa aktarım parça parça yapılabilir.</p>

		<h2 class="title">📊 Mevcut İçerik Sayıları</h2>
		<table class="widefat striped" style="max-width:820px">
			<thead>
				<tr>
					<th>İçerik Türü</th>
					<th style="width:120px">Yayınlanmış</th>
					<th style="width:120px">Taslak</th>
					<th style="width:220px">Hızlı İndir (yayınlananlar)</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$toplam = 0;
			foreach ( $types as $slug => $label ) :
				$counts  = wp_count_posts( $slug );
				$publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
				$draft   = isset( $counts->draft ) ? (int) $counts->draft : 0;
				$toplam += $publish;
				$quick   = wp_nonce_url(
					add_query_arg(
						array(
							'action'    => 'ewt_url_export',
							'post_type' => $slug,
							'format'    => 'csv',
						),
						$action_url
					),
					'ewt_url_export'
				);
				?>
				<tr>
					<td><strong><?php echo esc_html( $label ); ?></strong> <code><?php echo esc_html( $slug ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( $publish ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $draft ) ); ?></td>
					<td>
						<?php if ( $publish > 0 ) : ?>
							<a class="button button-small" href="<?php echo esc_url( $quick ); ?>">⬇ CSV indir</a>
						<?php else : ?>
							<span style="color:#999">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th>TOPLAM</th>
					<th><?php echo esc_html( number_format_i18n( $toplam ) ); ?></th>
					<th colspan="2"></th>
				</tr>
			</tfoot>
		</table>

		<h2 class="title" style="margin-top:32px">⚙️ Filtreli / Parçalı Dışa Aktarım</h2>
		<form method="get" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="ewt_url_export">
			<?php wp_nonce_field( 'ewt_url_export' ); ?>
			<table class="form-table" style="max-width:820px">
				<tr>
					<th>İçerik Türü</th>
					<td>
						<select name="post_type" id="ewt_url_export_pt">
							<option value="all">Tümü (bütün içerik türleri)</option>
							<?php foreach ( $types as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">"Tümü" seçilirse CSV'deki <code>icerik_turu</code> sütunundan filtreleyebilirsiniz.</p>
					</td>
				</tr>
				<tr>
					<th>Seviye (isteğe bağlı)</th>
					<td>
						<input type="text" name="seviye" class="regular-text" placeholder="ör. a1-seviyesi-listening">
						<p class="description">Tek bir içerik türü seçtiyseniz, o türün <strong>seviye terimi slug'ını</strong> yazarak yalnızca o seviyedeki içerikleri alabilirsiniz. Boş = tüm seviyeler.</p>
					</td>
				</tr>
				<tr>
					<th>Tarih Aralığı (isteğe bağlı)</th>
					<td>
						<input type="date" name="date_from"> — <input type="date" name="date_to">
						<p class="description">Yalnızca bu tarihler arasında <strong>yayınlanan</strong> içerikler. Yeni eklediğiniz toplu içerikleri indexe göndermek için idealdir (ör. son 1 hafta).</p>
					</td>
				</tr>
				<tr>
					<th>Parçalama</th>
					<td>
						Başlangıç: <input type="number" name="offset" value="0" min="0" step="1" style="width:110px">
						&nbsp; Maks. URL: <input type="number" name="limit" value="0" min="0" step="1" style="width:110px">
						<p class="description"><strong>0 = sınırsız.</strong> Indexleme aracınızın parti limiti varsa (ör. 10.000), önce Başlangıç=0 / Maks=10000, sonra Başlangıç=10000 / Maks=10000 ... şeklinde indirin.</p>
					</td>
				</tr>
				<tr>
					<th>Format</th>
					<td>
						<label><input type="radio" name="format" value="csv" checked> <strong>CSV</strong> — url, baslik, icerik_turu, seviye, yayin_tarihi, guncelleme_tarihi</label><br>
						<label><input type="radio" name="format" value="txt"> <strong>TXT</strong> — satır başına yalnızca 1 URL (indexleme araçları için)</label>
					</td>
				</tr>
				<tr>
					<th>Arşiv URL'leri</th>
					<td>
						<label><input type="checkbox" name="include_archives" value="1"> CPT arşiv sayfalarını ve seviye (taksonomi) arşiv URL'lerini de ekle</label>
						<p class="description">CSV'de bu satırların <code>icerik_turu</code> sütunu <code>arsiv</code> olur.</p>
					</td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary button-hero">⬇ Dışa Aktar</button></p>
		</form>

		<h2 class="title" style="margin-top:32px">💡 İpuçları</h2>
		<ul style="list-style:disc;padding-left:20px;max-width:820px">
			<li>Dosyalar sunucuda oluşturulmaz; doğrudan tarayıcınıza <strong>akış</strong> olarak iner. 50.000 URL'lik bir dışa aktarım bile belleği şişirmez, yalnızca birkaç saniye-dakika sürebilir.</li>
			<li>Google Search Console'a toplu gönderim için önce <strong>sitemap</strong> zaten en sağlıklı yoldur; bu araç, üçüncü parti hızlı indexleme servisleri ve IndexNow tarzı toplu gönderimler için URL listesi hazırlar.</li>
			<li>CSV, Excel'in Türkçe karakterleri doğru göstermesi için UTF-8 BOM ile iner.</li>
		</ul>
	</div>
	<?php
}

/* -------------------------------------------------
 * DIŞA AKTARIM İŞLEYİCİSİ (STREAMING)
 * ------------------------------------------------- */
function ewt_url_export_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Yetkiniz yok.' );
	}
	check_admin_referer( 'ewt_url_export' );

	$types_all = ewt_url_export_post_types();

	$pt_param   = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'all';
	$format     = ( isset( $_GET['format'] ) && 'txt' === $_GET['format'] ) ? 'txt' : 'csv';
	$seviye     = isset( $_GET['seviye'] ) ? sanitize_title( wp_unslash( $_GET['seviye'] ) ) : '';
	$date_from  = isset( $_GET['date_from'] ) ? preg_replace( '/[^0-9\-]/', '', wp_unslash( $_GET['date_from'] ) ) : '';
	$date_to    = isset( $_GET['date_to'] ) ? preg_replace( '/[^0-9\-]/', '', wp_unslash( $_GET['date_to'] ) ) : '';
	$offset     = isset( $_GET['offset'] ) ? max( 0, (int) $_GET['offset'] ) : 0;
	$limit      = isset( $_GET['limit'] ) ? max( 0, (int) $_GET['limit'] ) : 0; // 0 = sınırsız
	$archives   = ! empty( $_GET['include_archives'] );

	if ( 'all' === $pt_param ) {
		$post_types = array_keys( $types_all );
	} elseif ( isset( $types_all[ $pt_param ] ) ) {
		$post_types = array( $pt_param );
	} else {
		wp_die( 'Geçersiz içerik türü.' );
	}

	// Uzun işlemler için limitleri gevşet.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	wp_raise_memory_limit( 'admin' );

	$filename = 'english-web-urls-' . ( 'all' === $pt_param ? 'tumu' : $pt_param ) . '-' . gmdate( 'Ymd-His' ) . '.' . $format;

	nocache_headers();
	if ( 'csv' === $format ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
	} else {
		header( 'Content-Type: text/plain; charset=utf-8' );
	}
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$out = fopen( 'php://output', 'w' );

	if ( 'csv' === $format ) {
		// Excel'de Türkçe karakterler için UTF-8 BOM.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'url', 'baslik', 'icerik_turu', 'seviye', 'yayin_tarihi', 'guncelleme_tarihi' ) );
	}

	$written   = 0; // dosyaya yazılan satır
	$skipped   = 0; // offset nedeniyle atlanan satır
	$batch     = 500;

	// 1) İsteğe bağlı: arşiv URL'leri (parçalamadan etkilenmesin diye önce ve limit dışında sayılmaz — küçük bir listedir).
	if ( $archives ) {
		foreach ( $post_types as $pt ) {
			if ( 'page' === $pt ) continue;
			$arch = get_post_type_archive_link( $pt );
			if ( $arch ) {
				ewt_url_export_write_row( $out, $format, $arch, ( $types_all[ $pt ] . ' Arşivi' ), 'arsiv', '', '', '' );
			}
			$tax = ewt_url_export_seviye_tax( $pt );
			if ( $tax ) {
				$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $t ) {
						$turl = get_term_link( $t );
						if ( ! is_wp_error( $turl ) ) {
							ewt_url_export_write_row( $out, $format, $turl, $t->name, 'arsiv', $t->slug, '', '' );
						}
					}
				}
			}
		}
	}

	// 2) İçerikler: tür tür, 500'lük partiler hâlinde.
	foreach ( $post_types as $pt ) {
		if ( $limit > 0 && $written >= $limit ) break;

		$tax   = ewt_url_export_seviye_tax( $pt );
		$paged = 0;

		while ( true ) {
			if ( $limit > 0 && $written >= $limit ) break;

			$args = array(
				'post_type'              => $pt,
				'post_status'            => 'publish',
				'posts_per_page'         => $batch,
				'offset'                 => $paged * $batch,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			);

			if ( $seviye && $tax && 'all' !== $pt_param ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => $tax,
						'field'    => 'slug',
						'terms'    => $seviye,
					),
				);
			}
			if ( $date_from || $date_to ) {
				$dq = array( 'inclusive' => true );
				if ( $date_from ) $dq['after']  = $date_from;
				if ( $date_to )   $dq['before'] = $date_to . ' 23:59:59';
				$args['date_query'] = array( $dq );
			}

			$ids = get_posts( $args );
			if ( empty( $ids ) ) break;

			foreach ( $ids as $id ) {
				// Offset: satırları saymadan atla.
				if ( $skipped < $offset ) {
					$skipped++;
					continue;
				}
				if ( $limit > 0 && $written >= $limit ) break 2;

				$sev = '';
				if ( $tax ) {
					$terms = get_the_terms( $id, $tax );
					if ( $terms && ! is_wp_error( $terms ) ) {
						$sev = implode( '|', wp_list_pluck( $terms, 'slug' ) );
					}
				}

				ewt_url_export_write_row(
					$out,
					$format,
					get_permalink( $id ),
					get_the_title( $id ),
					$pt,
					$sev,
					get_the_date( 'Y-m-d', $id ),
					get_the_modified_date( 'Y-m-d', $id )
				);
				$written++;
			}

			// Belleği temiz tut, çıktıyı gönder.
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
			flush();

			if ( count( $ids ) < $batch ) break;
			$paged++;
		}
	}

	fclose( $out );
	exit;
}
add_action( 'admin_post_ewt_url_export', 'ewt_url_export_handle' );

/**
 * Tek satır yaz (CSV veya TXT).
 */
function ewt_url_export_write_row( $out, $format, $url, $title, $type, $seviye, $pub, $mod ) {
	if ( 'txt' === $format ) {
		fwrite( $out, $url . "\n" );
		return;
	}
	fputcsv( $out, array( $url, $title, $type, $seviye, $pub, $mod ) );
}
