<?php
/** CSV seviye/kategori çözümleme. Dört sütunlu içe aktarma biçimini korur. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ewt_csv_ielts_categories() {
	return array(
		'band-5-0-5-5' => 'Band 5.0 - 5.5',
		'band-6-0-6-5' => 'Band 6.0 - 6.5',
		'band-7-0-plus' => 'Band 7.0+',
		'academic-modul' => 'Academic Modül',
		'general-training-modul' => 'General Training Modül',
		'ielts-genel-bilgiler' => 'IELTS Genel Bilgiler',
		'ielts-hazirlik' => 'IELTS Hazırlık',
		'ielts-puanlama' => 'IELTS Puanlama',
		'ielts-listening' => 'IELTS Listening',
		'ielts-reading' => 'IELTS Reading',
		'ielts-writing' => 'IELTS Writing',
		'ielts-speaking' => 'IELTS Speaking',
		'ielts-vocabulary' => 'IELTS Vocabulary',
		'ielts-grammar' => 'IELTS Grammar',
		'ielts-alistirmalar' => 'IELTS Alıştırmalar',
	);
}

/** Konu başlığından tek kategori seçer; yapay zekâ yanıtına bağlı değildir. */
function ewt_csv_ielts_category_for_keyword( $keyword ) {
	$key = sanitize_title( $keyword );
	if ( preg_match( '/(?:band-([5-8])(?:-[05])?|ielts-([5-8])(?:-[05])?-hedefi)(?:-|$)/', $key, $m ) ) {
		$band = ! empty( $m[1] ) ? (int) $m[1] : (int) $m[2];
		return $band >= 7 ? 'band-7-0-plus' : ( $band === 6 ? 'band-6-0-6-5' : 'band-5-0-5-5' );
	}
	if ( strpos( $key, 'academic-ve-general-training' ) !== false ) return 'ielts-genel-bilgiler';
	if ( preg_match( '/general-training|general-writing/', $key ) ) return 'general-training-modul';
	if ( preg_match( '/ielts-academic|writing-task-1/', $key ) ) return 'academic-modul';
	if ( preg_match( '/puan|band-score|overall|degerlendirme|task-achievement|task-response|coherence-and-cohesion|lexical-resource|grammatical-range|fluency-and-coherence|pronunciation-degerlendirmesi/', $key ) ) return 'ielts-puanlama';
	if ( preg_match( '/listening/', $key ) ) return 'ielts-listening';
	if ( preg_match( '/speaking/', $key ) ) return 'ielts-speaking';
	if ( preg_match( '/reading/', $key ) ) return 'ielts-reading';
	if ( preg_match( '/writing|essay|mektup|letter|grafik|chart|overview|topic-sentence|thesis-statement|harita-karsilastirma|surec-diyagrami|tablo-yorumlama/', $key ) ) return 'ielts-writing';
	if ( preg_match( '/hazirlik|hazirlan|calisma|calismaya|calisanlar-icin|zaman-yonetimi/', $key ) ) return 'ielts-hazirlik';
	if ( preg_match( '/alistirma|testi|mini-test|ornek-sinav|deneme-sinavi/', $key ) ) return 'ielts-alistirmalar';
	if ( preg_match( '/gramer|grammar|tense|present-|past-|gelecek-zaman|passive-voice|modal-verbs|conditionals|clauses|sentences?|gerund|infinitive|articles|prepositions|subject-verb|nouns|comparatives|superlatives|quantifiers|hedging|nominalisation|noktalama/', $key ) ) return 'ielts-grammar';
	if ( preg_match( '/kelime|vocabulary|collocation|phrasal|baglac|ifadeleri|paraphrase|akademik-fiil|akademik-sifat|akademik-zarf/', $key ) ) return 'ielts-vocabulary';
	return 'ielts-genel-bilgiler';
}

/** Mevcut terimi yeniden kullanır; yalnızca yetkili kullanıcı için eksik terimi oluşturur. */
function ewt_csv_ensure_category( $post_type, $value ) {
	$taxonomy = $post_type . '_seviye';
	$tax = get_taxonomy( $taxonomy );
	if ( ! $tax || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
		return new WP_Error( 'ewt_csv_taxonomy', 'Bu içerik türünde seviye/kategori alanı bulunmuyor.' );
	}
	if ( ! current_user_can( $tax->cap->assign_terms ) ) return new WP_Error( 'ewt_csv_assign', 'Kategori atama yetkiniz yok.' );
	$value = trim( sanitize_text_field( $value ) );
	$slug = sanitize_title( $value );
	if ( $slug === '' || strlen( $slug ) > 200 || mb_strlen( $value ) > 200 || preg_match( '/[|,;>]/u', $value ) ) {
		return new WP_Error( 'ewt_csv_category', 'Seviye alanına tek bir kategori adı veya kodu yazın (en fazla 200 karakter).' );
	}
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term ) $term = get_term_by( 'name', $value, $taxonomy );
	if ( ! $term ) {
		if ( ! current_user_can( $tax->cap->manage_terms ) ) return new WP_Error( 'ewt_csv_create', 'Yeni kategori oluşturma yetkiniz yok.' );
		$names = $post_type === 'ewt_ielts' ? ewt_csv_ielts_categories() : array();
		$name = isset( $names[$slug] ) ? $names[$slug] : $value;
		$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			// Paralel CSV satırlarından biri aynı terimi önce oluşturmuş olabilir.
			if ( $result->get_error_code() !== 'term_exists' ) return $result;
			$term = get_term( (int) $result->get_error_data(), $taxonomy );
		} else {
			$term = get_term( (int) $result['term_id'], $taxonomy );
		}
	}
	if ( ! $term || is_wp_error( $term ) ) return new WP_Error( 'ewt_csv_term', 'Kategori oluşturulamadı veya okunamadı.' );
	return array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name );
}
