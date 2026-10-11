<?php
/** Learning preferences and deterministic, paginated content recommendations. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ewt_path_levels() {
	return array( 'a1' => 'A1', 'a2' => 'A2', 'b1' => 'B1', 'b2' => 'B2', 'c1' => 'C1', 'c2' => 'C2' );
}
function ewt_path_audiences() {
	$out = array( 'preschool' => 'Okul öncesi' );
	for ( $i = 1; $i <= 12; $i++ ) $out[ 'grade-' . $i ] = $i . '. sınıf';
	return $out + array( 'adult' => 'Yetişkin', 'undergraduate' => 'Üniversite öğrencisi (Undergraduate)', 'graduate' => 'Üniversite mezunu (Graduate)', 'postgraduate' => 'Lisansüstü (Postgraduate)' );
}
function ewt_path_goals() {
	return array( 'general' => 'Genel İngilizce', 'toefl' => 'TOEFL', 'ielts' => 'IELTS', 'ydt' => 'YDT', 'yds' => 'YDS', 'yokdil' => 'YÖKDİL', 'gre' => 'GRE', 'gmat' => 'GMAT', 'academic' => 'Akademik eğitim', 'career' => 'İş ve kariyer' );
}
function ewt_path_targets( $goal ) {
	$map = array(
		'toefl' => array( 'toefl-band-4' => '4–4.5 (1–6 ölçeği)', 'toefl-band-5' => '5–5.5 (1–6 ölçeği)', 'toefl-band-6' => '6 (1–6 ölçeği)', 'toefl-60-80' => '60–79 (0–120 ölçeği)', 'toefl-80-100' => '80–99 (0–120 ölçeği)', 'toefl-100p' => '100+ (0–120 ölçeği)' ),
		'ielts' => array( 'ielts-5-0-5-5' => 'Band 5–5.5', 'ielts-6-0-6-5' => 'Band 6–6.5', 'ielts-7-0p' => 'Band 7+' ),
		'ydt' => array( 'ydt-40-60' => '40–59 net', 'ydt-60-80' => '60–80 net' ),
		'yds' => array( 'yds-40-60' => '40–59 puan', 'yds-60-80' => '60–79 puan', 'yds-80p' => '80+ puan' ),
		'yokdil' => array( 'yokdil-fen' => 'Fen bilimleri', 'yokdil-saglik' => 'Sağlık bilimleri', 'yokdil-sosyal' => 'Sosyal bilimler' ),
		'gre' => array( 'gre-temel' => 'Temel hazırlık', 'gre-orta' => 'Orta düzey hazırlık', 'gre-ileri' => 'İleri düzey hazırlık' ),
		'gmat' => array( 'gmat-temel' => 'Temel hazırlık', 'gmat-orta' => 'Orta düzey hazırlık', 'gmat-ileri' => 'İleri düzey hazırlık' ),
		'academic' => array( 'academic-study' => 'Üniversite / lisansüstü eğitime hazırlık', 'academic-reading' => 'Akademik okuma ve yazma' ),
		'career' => array( 'career-interview' => 'İş görüşmesine hazırlık', 'career-work' => 'İş yerinde iletişim' ),
		'general' => array( 'general-daily' => 'Günlük iletişim', 'general-skills' => 'Dil becerilerini geliştirme' ),
	);
	return $map[ $goal ] ?? array();
}
function ewt_path_choice( $value, $choices, $default = '' ) {
	return is_string( $value ) && array_key_exists( $value, $choices ) ? $value : $default;
}
function ewt_path_preferences( $input = null ) {
	$input = is_array( $input ) ? $input : wp_unslash( $_GET );
	$goal = ewt_path_choice( $input['ewt_goal'] ?? '', ewt_path_goals(), 'general' );
	return array(
		'level' => ewt_path_choice( $input['ewt_level'] ?? '', ewt_path_levels(), 'a1' ),
		'audience' => ewt_path_choice( $input['ewt_audience'] ?? '', ewt_path_audiences(), 'adult' ),
		'goal' => $goal,
		'target' => ewt_path_choice( $input['ewt_target'] ?? '', ewt_path_targets( $goal ) ),
		'order' => ewt_path_choice( $input['ewt_order'] ?? '', array( 'relevant' => true, 'easy' => true ), 'relevant' ),
	);
}
function ewt_path_select( $name, $label, $choices, $current, $empty = null ) {
	?><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?><select id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php
	if ( null !== $empty ) echo '<option value="">' . esc_html( $empty ) . '</option>';
	foreach ( $choices as $value => $text ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $text ) . '</option>';
	?></select></label><?php
}
function ewt_path_render_bar() {
	$p = ewt_path_preferences(); ?>
	<section class="ewt-path-bar" aria-labelledby="ewt-path-heading">
		<div class="ewt-container">
			<h2 id="ewt-path-heading">İngilizce öğrenme yolunu seç</h2>
			<form id="ewt-path-form" class="ewt-path-fields" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="hidden" name="ewt_path" value="1">
				<?php
				ewt_path_select( 'ewt_level', 'İngilizce seviyen', ewt_path_levels(), $p['level'] );
				ewt_path_select( 'ewt_audience', 'Sınıf / eğitim grubu', ewt_path_audiences(), $p['audience'] );
				ewt_path_select( 'ewt_goal', 'Amacın / sınavın', ewt_path_goals(), $p['goal'] );
				ewt_path_select( 'ewt_target', 'Hedefin', ewt_path_targets( $p['goal'] ), $p['target'], 'Genel hazırlık' );
				ewt_path_select( 'ewt_order', 'İçerik sıralaması', array( 'relevant' => 'En ilgili içerik önce', 'easy' => 'Kolaydan zora' ), $p['order'] ); ?>
				<button type="submit">İçerikleri keşfet</button>
				<button type="button" id="ewt-path-reset" hidden>Seçimleri sıfırla</button>
			</form>
			<p class="ewt-path-hint">Seviyenden emin değil misin? <a href="<?php echo esc_url( home_url( '/#ewt-placement-test' ) ); ?>">Ana sayfada kısa seviye testini çöz.</a></p>
			<noscript><p>Hedef listesini güncellemek için önce sınavını seçip “İçerikleri keşfet” düğmesine bas.</p></noscript>
		</div>
	</section><?php
}
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'ewt-learning-path', EWT_URI . '/assets/css/learning-path.css', array( 'ewt-main' ), EWT_VERSION );
	wp_enqueue_script( 'ewt-learning-path', EWT_URI . '/assets/js/learning-path.js', array(), EWT_VERSION, true );
	$targets = array();
	foreach ( ewt_path_goals() as $goal => $label ) $targets[ $goal ] = ewt_path_targets( $goal );
	wp_localize_script( 'ewt-learning-path', 'ewtLearningPath', array( 'targets' => $targets, 'levels' => array_keys( ewt_path_levels() ), 'audiences' => array_keys( ewt_path_audiences() ), 'goals' => array_keys( ewt_path_goals() ) ) );
} );
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'ewt_path'; return $vars; } );
add_filter( 'template_include', function ( $template ) {
	$flag = get_query_var( 'ewt_path' );
	if ( ! is_scalar( $flag ) || '1' !== (string) $flag ) return $template;
	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
	return EWT_DIR . '/page-learning-path.php';
}, 100 );
add_filter( 'redirect_canonical', function ( $url ) { return get_query_var( 'ewt_path' ) ? false : $url; } );
add_filter( 'wp_robots', function ( $robots ) {
	if ( get_query_var( 'ewt_path' ) ) { $robots['noindex'] = true; unset( $robots['index'] ); }
	return $robots;
} );
add_filter( 'pre_get_document_title', function ( $title ) { return get_query_var( 'ewt_path' ) ? 'Sana uygun İngilizce içerikler' : $title; } );

/** Existing per-CPT terms remain authoritative when explicit path metadata is absent. */
function ewt_path_term_map() {
	static $map;
	if ( isset( $map ) ) return $map;
	$map = array( 'levels' => array(), 'audiences' => array(), 'targets' => array() );
	$terms = get_terms( array( 'taxonomy' => ewt_all_seviye_taxonomies(), 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) return $map;
	foreach ( $terms as $term ) {
		$text = strtolower( remove_accents( $term->slug . ' ' . $term->name ) );
		if ( preg_match( '/(?:^|[^a-z0-9])(a1|a2|b1|b2|c1|c2)(?:[^a-z0-9]|$)/', $text, $m ) ) $map['levels'][ $m[1] ][] = (int) $term->term_taxonomy_id;
		if ( preg_match( '/(?:^|[^0-9])(1[0-2]|[1-9])[.\s_-]*sinif(?:[^a-z]|$)/', $text, $m ) ) $map['audiences'][ 'grade-' . $m[1] ][] = (int) $term->term_taxonomy_id;
		foreach ( ewt_path_goals() as $goal => $label ) {
			foreach ( ewt_path_targets( $goal ) as $target => $target_label ) {
				if ( $term->slug === $target ) $map['targets'][ $target ][] = (int) $term->term_taxonomy_id;
			}
		}
	}
	return $map;
}
function ewt_path_audience_type( $audience ) {
	if ( preg_match( '/^grade-(\d+)$/', $audience, $m ) ) return (int) $m[1] <= 4 ? 'ewt_primary' : ( (int) $m[1] <= 8 ? 'ewt_secondary' : 'ewt_highschool' );
	return 'preschool' === $audience ? 'ewt_preschool' : '';
}
function ewt_path_goal_types( $goal ) {
	if ( in_array( $goal, array( 'toefl', 'ielts', 'ydt', 'yds', 'yokdil', 'gre', 'gmat' ), true ) ) return array( 'ewt_' . $goal );
	if ( 'career' === $goal ) return array( 'ewt_mesleki' );
	if ( 'academic' === $goal ) return array( 'ewt_reading', 'ewt_writing' );
	return array( 'ewt_listening', 'ewt_speaking', 'ewt_reading', 'ewt_writing', 'ewt_vocabulary', 'ewt_grammar', 'post' );
}
function ewt_path_meta_sql( $key ) {
	global $wpdb;
	return $wpdb->prepare( "COALESCE((SELECT MAX(pm.meta_value) FROM {$wpdb->postmeta} pm WHERE pm.post_id={$wpdb->posts}.ID AND pm.meta_key=%s), '')", '_ewt_path_' . $key );
}
function ewt_path_terms_sql( $ids ) {
	global $wpdb;
	if ( empty( $ids ) ) return '0';
	return "EXISTS(SELECT 1 FROM {$wpdb->term_relationships} tr WHERE tr.object_id={$wpdb->posts}.ID AND tr.term_taxonomy_id IN (" . implode( ',', array_map( 'absint', $ids ) ) . '))';
}
function ewt_path_rank_sql() {
	global $wpdb;
	$map = ewt_path_term_map();
	$explicit = 'CASE ' . ewt_path_meta_sql( 'level' );
	$fallback = 'CASE';
	foreach ( array_keys( ewt_path_levels() ) as $rank => $level ) {
		$explicit .= $wpdb->prepare( ' WHEN %s THEN %d', $level, $rank + 1 );
		$fallback .= ' WHEN ' . ewt_path_terms_sql( $map['levels'][ $level ] ?? array() ) . ' THEN ' . ( $rank + 1 );
	}
	return '(' . $explicit . ' ELSE (' . $fallback . ' ELSE 99 END) END)';
}
function ewt_path_relevance_sql( $p ) {
	global $wpdb;
	$map = ewt_path_term_map();
	$goal_meta = ewt_path_meta_sql( 'goal' );
	$types = implode( ',', array_map( function ( $type ) use ( $wpdb ) { return $wpdb->prepare( '%s', $type ); }, ewt_path_goal_types( $p['goal'] ) ) );
	$goal = $wpdb->prepare( "CASE WHEN {$goal_meta}=%s THEN 60 WHEN {$goal_meta}='' AND {$wpdb->posts}.post_type IN ({$types}) THEN 60 ELSE 0 END", $p['goal'] );
	$aud_meta = ewt_path_meta_sql( 'audience' );
	$aud_term = ewt_path_terms_sql( $map['audiences'][ $p['audience'] ] ?? array() );
	$audience = $wpdb->prepare( "CASE WHEN {$aud_meta}=%s THEN 25 WHEN {$aud_meta}='' AND {$aud_term} THEN 25 WHEN {$aud_meta}='' AND {$wpdb->posts}.post_type=%s THEN 10 ELSE 0 END", $p['audience'], ewt_path_audience_type( $p['audience'] ) );
	$rank = ewt_path_rank_sql();
	$wanted = array_search( $p['level'], array_keys( ewt_path_levels() ), true ) + 1;
	$level = "CASE WHEN {$rank}=99 THEN 0 ELSE GREATEST(0, 20-ABS({$rank}-{$wanted})*5) END";
	$target = '0';
	if ( $p['target'] ) {
		$target_meta = ewt_path_meta_sql( 'target' );
		$target_term = ewt_path_terms_sql( $map['targets'][ $p['target'] ] ?? array() );
		$target = $wpdb->prepare( "CASE WHEN {$target_meta}=%s OR ({$target_meta}='' AND {$target_term}) THEN 35 ELSE 0 END", $p['target'] );
	}
	return "({$goal}+{$audience}+{$level}+{$target})";
}
function ewt_path_query( $p, $page = 1, $per_page = 12 ) {
	global $wpdb;
	$orderby = function ( $sql, $query ) use ( $p, $wpdb ) {
		if ( ! $query->get( 'ewt_path_query' ) ) return $sql;
		$rank = ewt_path_rank_sql();
		$score = ewt_path_relevance_sql( $p );
		return ( 'easy' === $p['order'] ? "{$rank} ASC, {$score} DESC" : "{$score} DESC, {$rank} ASC" ) . ", {$wpdb->posts}.post_date ASC, {$wpdb->posts}.ID ASC";
	};
	add_filter( 'posts_orderby', $orderby, 20, 2 );
	try {
		return new WP_Query( array( 'ewt_path_query' => true, 'post_type' => array_merge( array( 'post' ), array_keys( ewt_cpt_config() ) ), 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => max( 1, min( 100, (int) $per_page ) ), 'paged' => max( 1, (int) $page ), 'ignore_sticky_posts' => true ) );
	} finally {
		remove_filter( 'posts_orderby', $orderby, 20 );
	}
}
function ewt_path_post_level( $id ) {
	$level = ewt_path_choice( get_post_meta( $id, '_ewt_path_level', true ), ewt_path_levels() );
	if ( $level ) return strtoupper( $level );
	$ids = wp_get_object_terms( $id, ewt_all_seviye_taxonomies(), array( 'fields' => 'tt_ids' ) );
	if ( is_wp_error( $ids ) ) return 'Seviye belirtilmemiş';
	$map = ewt_path_term_map();
	foreach ( array_keys( ewt_path_levels() ) as $key ) if ( array_intersect( array_map( 'intval', $ids ), $map['levels'][ $key ] ?? array() ) ) return strtoupper( $key );
	return 'Seviye belirtilmemiş';
}

/** Optional editor metadata; does not rewrite existing content or taxonomy assignments. */
add_action( 'add_meta_boxes', function () {
	foreach ( array_merge( array( 'post' ), array_keys( ewt_cpt_config() ) ) as $type ) add_meta_box( 'ewt-path-meta', 'Öğrenme yolu eşleştirmesi', 'ewt_path_meta_box', $type, 'side' );
} );
function ewt_path_meta_box( $post ) {
	wp_nonce_field( 'ewt_path_meta', 'ewt_path_nonce' );
	ewt_path_select( 'ewt_path_level', 'CEFR seviyesi', ewt_path_levels(), get_post_meta( $post->ID, '_ewt_path_level', true ), 'Mevcut seviye etiketlerini kullan' );
	ewt_path_select( 'ewt_path_audience', 'Eğitim grubu', ewt_path_audiences(), get_post_meta( $post->ID, '_ewt_path_audience', true ), 'Genel / mevcut sınıf etiketleri' );
	$goal = get_post_meta( $post->ID, '_ewt_path_goal', true );
	ewt_path_select( 'ewt_path_goal', 'Amaç', ewt_path_goals(), $goal, 'İçerik türünden belirle' );
	$targets = array();
	foreach ( ewt_path_goals() as $key => $label ) foreach ( ewt_path_targets( $key ) as $code => $text ) $targets[ $code ] = $label . ': ' . $text;
	ewt_path_select( 'ewt_path_target', 'Hedef', $targets, get_post_meta( $post->ID, '_ewt_path_target', true ), 'Genel hazırlık' );
	echo '<p>Hedefi seçerken aynı sınavı Amaç alanında da seçin. Boş alanlar mevcut içerik türü ve seviye etiketlerinden eşleştirilir; bilinmeyen bilgiler tahmin edilmez.</p>';
}
add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['ewt_path_nonce'] ) || ! is_string( $_POST['ewt_path_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ewt_path_nonce'] ) ), 'ewt_path_meta' ) ) return;
	if ( wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) || ! current_user_can( 'edit_post', $id ) ) return;
	if ( ! in_array( get_post_type( $id ), array_merge( array( 'post' ), array_keys( ewt_cpt_config() ) ), true ) ) return;
	$goal = ewt_path_choice( wp_unslash( $_POST['ewt_path_goal'] ?? '' ), ewt_path_goals() );
	$values = array(
		'level' => ewt_path_choice( wp_unslash( $_POST['ewt_path_level'] ?? '' ), ewt_path_levels() ),
		'audience' => ewt_path_choice( wp_unslash( $_POST['ewt_path_audience'] ?? '' ), ewt_path_audiences() ),
		'goal' => $goal,
		'target' => ewt_path_choice( wp_unslash( $_POST['ewt_path_target'] ?? '' ), ewt_path_targets( $goal ) ),
	);
	foreach ( $values as $key => $value ) {
		if ( '' === $value ) delete_post_meta( $id, '_ewt_path_' . $key );
		else update_post_meta( $id, '_ewt_path_' . $key, $value );
	}
} );
