<?php
/**
 * SEO META BOX (Yoast / Rank Math tarzı)
 *
 * - Focus Keyword (FK) girilir
 * - Slug otomatik olarak FK'dan üretilir (siz elle değiştirmediyseniz)
 * - Page Title FK ile BAŞLAR, maksimum 55 karakter (canlı sayaç + sunucu tarafı kesme)
 * - Meta Description FK ile BAŞLAR, maksimum 130 karakter (canlı sayaç + sunucu tarafı kesme)
 * - Yoast SEO ve/veya Rank Math aktifse, aynı değerler o eklentilerin kendi meta alanlarına da yazılır
 *   (böylece frontend çıktısını zaten yönetmekte olan eklentiyle çakışma olmaz).
 * - Hiçbiri aktif değilse bu widget kendi <title> ve meta description çıktısını verir.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EWT_SEO_TITLE_MAX', 55 );
define( 'EWT_SEO_DESC_MAX', 130 );

function ewt_seo_supported_post_types() {
	$types = array_keys( ewt_cpt_config() );
	$types[] = 'post';
	$types[] = 'page';
	return $types;
}

function ewt_add_seo_meta_box() {
	foreach ( ewt_seo_supported_post_types() as $pt ) {
		add_meta_box( 'ewt_seo_box', 'SEO Ayarları (FK / Title / Meta Açıklama)', 'ewt_seo_meta_box_html', $pt, 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'ewt_add_seo_meta_box' );

function ewt_seo_meta_box_html( $post ) {
	wp_nonce_field( 'ewt_seo_save', 'ewt_seo_nonce' );
	$fk    = get_post_meta( $post->ID, '_ewt_focus_keyword', true );
	$title = get_post_meta( $post->ID, '_ewt_seo_title', true );
	$desc  = get_post_meta( $post->ID, '_ewt_seo_desc', true );
	?>
	<div class="ewt-seo-box">
		<p>
			<label><strong>Focus Keyword (FK)</strong></label><br />
			<input type="text" id="ewt_focus_keyword" name="ewt_focus_keyword" class="widefat" value="<?php echo esc_attr( $fk ); ?>" placeholder="örn: a1 seviyesi ingilizce kelimeler" />
			<span class="description">Slug bu alandan otomatik türetilir. Page Title ve Meta Açıklama FK ile başlamalıdır.</span>
		</p>
		<p>
			<label><strong>Page Title</strong> (<span id="ewt_title_count">0</span>/<?php echo EWT_SEO_TITLE_MAX; ?>)</label><br />
			<input type="text" id="ewt_seo_title" name="ewt_seo_title" class="widefat" maxlength="<?php echo EWT_SEO_TITLE_MAX; ?>" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label><strong>Meta Açıklama</strong> (<span id="ewt_desc_count">0</span>/<?php echo EWT_SEO_DESC_MAX; ?>)</label><br />
			<textarea id="ewt_seo_desc" name="ewt_seo_desc" class="widefat" rows="3" maxlength="<?php echo EWT_SEO_DESC_MAX; ?>"><?php echo esc_textarea( $desc ); ?></textarea>
		</p>
		<p class="description">Not: Yoast SEO veya Rank Math etkinse bu değerler o eklentilerin title/description alanlarına da otomatik yazılır.</p>
	</div>
	<?php
}

function ewt_enqueue_seo_admin_js( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
	wp_enqueue_script( 'ewt-seo-admin', EWT_URI . '/assets/js/admin-seo-widget.js', array( 'jquery' ), EWT_VERSION, true );
	wp_localize_script( 'ewt-seo-admin', 'ewtSeo', array(
		'titleMax' => EWT_SEO_TITLE_MAX,
		'descMax'  => EWT_SEO_DESC_MAX,
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_enqueue_seo_admin_js' );

/**
 * Focus keyword + başlık + açıklamayı bir posta uygular: limit kesme, FK ile başlatma,
 * slug üretimi, Yoast/Rank Math senkronu. Hem meta box save'i hem de toplu üretim aracı bunu kullanır.
 */
function ewt_apply_seo_fields( $post_id, $fk, $title = '', $desc = '', $force_slug = false ) {
	$fk    = sanitize_text_field( $fk );
	$title = sanitize_text_field( $title );
	$desc  = sanitize_textarea_field( $desc );

	if ( ! $title ) $title = $fk;
	if ( ! $desc )  $desc  = $fk;

	$title = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, EWT_SEO_TITLE_MAX ) : substr( $title, 0, EWT_SEO_TITLE_MAX );
	$desc  = function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, EWT_SEO_DESC_MAX ) : substr( $desc, 0, EWT_SEO_DESC_MAX );

	if ( $fk ) {
		if ( stripos( $title, $fk ) !== 0 ) {
			$title = mb_substr( $fk . ' - ' . $title, 0, EWT_SEO_TITLE_MAX );
		}
		if ( stripos( $desc, $fk ) !== 0 ) {
			$desc = mb_substr( $fk . '. ' . $desc, 0, EWT_SEO_DESC_MAX );
		}
	}

	update_post_meta( $post_id, '_ewt_focus_keyword', $fk );
	update_post_meta( $post_id, '_ewt_seo_title', $title );
	update_post_meta( $post_id, '_ewt_seo_desc', $desc );

	if ( $fk && $force_slug ) {
		$auto_slug = sanitize_title( $fk );
		wp_update_post( array( 'ID' => $post_id, 'post_name' => $auto_slug ) );
		update_post_meta( $post_id, '_ewt_slug_from_fk', '1' );
	}

	if ( $title ) update_post_meta( $post_id, '_yoast_wpseo_title', $title );
	if ( $desc )  update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
	if ( $fk )    update_post_meta( $post_id, '_yoast_wpseo_focuskw', $fk );

	if ( $title ) update_post_meta( $post_id, 'rank_math_title', $title );
	if ( $desc )  update_post_meta( $post_id, 'rank_math_description', $desc );
	if ( $fk )    update_post_meta( $post_id, 'rank_math_focus_keyword', $fk );

	return array( 'title' => $title, 'desc' => $desc );
}

function ewt_save_seo_meta_box( $post_id ) {
	if ( ! isset( $_POST['ewt_seo_nonce'] ) || ! wp_verify_nonce( $_POST['ewt_seo_nonce'], 'ewt_seo_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$fk    = isset( $_POST['ewt_focus_keyword'] ) ? sanitize_text_field( $_POST['ewt_focus_keyword'] ) : '';
	$title = isset( $_POST['ewt_seo_title'] ) ? sanitize_text_field( $_POST['ewt_seo_title'] ) : '';
	$desc  = isset( $_POST['ewt_seo_desc'] ) ? sanitize_textarea_field( $_POST['ewt_seo_desc'] ) : '';

	// Slug'ı FK'dan otomatik üret (kullanıcı manuel slug girmediyse / hâlâ auto-draft ise)
	$post = get_post( $post_id );
	$force_slug = $fk && $post && ( $post->post_name === '' || strpos( $post->post_name, 'auto-draft' ) !== false || get_post_meta( $post_id, '_ewt_slug_from_fk', true ) === '1' );

	if ( $force_slug ) remove_action( 'save_post', 'ewt_save_seo_meta_box' ); // sonsuz döngü önle
	ewt_apply_seo_fields( $post_id, $fk, $title, $desc, $force_slug );
	if ( $force_slug ) add_action( 'save_post', 'ewt_save_seo_meta_box' );
}
add_action( 'save_post', 'ewt_save_seo_meta_box' );

/**
 * Yoast/Rank Math yüklü DEĞİLSE, bu widget'ın kendi title/description çıktısını ver.
 */
function ewt_output_seo_meta_tags() {
	if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) return; // eklenti varsa çıktı ona bırakılır

	if ( is_singular() ) {
		global $post;
		$title = get_post_meta( $post->ID, '_ewt_seo_title', true );
		$desc  = get_post_meta( $post->ID, '_ewt_seo_desc', true );
		if ( $desc ) {
			echo "\n" . '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
		if ( $title ) {
			add_filter( 'pre_get_document_title', function() use ( $title ) { return $title; } );
		}
	}
}
add_action( 'wp_head', 'ewt_output_seo_meta_tags', 1 );
