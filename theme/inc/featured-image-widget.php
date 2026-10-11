<?php
/**
 * ÖNE ÇIKAN GÖRSEL WIDGET'I (tekil) + toplu araç için kullanılan paylaşımlı fonksiyonlar
 *
 * İki sağlayıcı desteklenir:
 * - Pexels: stok fotoğraf araması (hızlı, ücretsiz, sorgu = başlık/prompt)
 * - OpenAI: AI ile özgün görsel üretimi (gpt-image-1)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function ewt_add_featured_image_box() {
	foreach ( array_merge( array_keys( ewt_cpt_config() ), array( 'post' ) ) as $pt ) {
		add_meta_box( 'ewt_ai_image_box', '🖼️ Otomatik Öne Çıkan Görsel', 'ewt_ai_image_box_html', $pt, 'side', 'default' );
	}
}
add_action( 'add_meta_boxes', 'ewt_add_featured_image_box' );

function ewt_ai_image_box_html( $post ) {
	wp_nonce_field( 'ewt_ai_image_generate', 'ewt_ai_image_nonce' );
	$default_provider = get_option( 'ewt_default_image_provider', 'pexels' );
	?>
	<div class="ewt-ai-image-box">
		<p>
			<label for="ewt_ai_image_provider"><strong>Sağlayıcı</strong></label>
			<select id="ewt_ai_image_provider" class="widefat">
				<option value="pexels" <?php selected( $default_provider, 'pexels' ); ?>>Pexels (stok fotoğraf)</option>
				<option value="openai" <?php selected( $default_provider, 'openai' ); ?>>OpenAI (AI üretimi)</option>
			</select>
		</p>
		<p>
			<label for="ewt_ai_image_prompt"><strong>Arama Sorgusu / Görsel Talimatı</strong></label>
			<textarea id="ewt_ai_image_prompt" class="widefat" rows="3" placeholder="Boş bırakılırsa yazı başlığından otomatik oluşturulur"></textarea>
		</p>
		<p>
			<label>
				<input type="checkbox" id="ewt_ai_image_child_object" value="1" />
				👶 Çocuklar için nesne görseli (izole obje, beyaz arkaplan, flashcard stili)
			</label>
			<span class="description">Okul öncesi/ilkokul kelime içerikleri için işaretleyin: sorgu, temiz/izole obje fotoğrafı aramasına dönüştürülür.</span>
		</p>
		<p>
			<button type="button" class="button button-primary widefat" id="ewt_ai_image_btn">Görsel Bul/Üret ve Ata</button>
		</p>
		<div id="ewt_ai_image_preview"></div>
		<p id="ewt_ai_image_status" style="display:none;"></p>
	</div>
	<?php
}

/**
 * Bir sorgudan Pexels'te fotoğraf arar, ilk sonucun binary verisini döndürür.
 * Başarısızsa WP_Error döner.
 */
function ewt_fetch_pexels_image( $query ) {
 $api_key=get_option('ewt_pexels_api_key');
 if(!$api_key)return new WP_Error('no_key','Pexels API anahtarı ayarlanmamış.');
 $query=strtolower(remove_accents(wp_strip_all_tags($query)));
 $map=array('meyve'=>'fruit','hayvan'=>'animal','renk'=>'color','aile'=>'family','okul'=>'school','sinif'=>'classroom','yemek'=>'food','hava'=>'weather','meslek'=>'occupation','seyahat'=>'travel','spor'=>'sport','muzik'=>'music','kitap'=>'book','vucut'=>'body','kedi'=>'cat','kopek'=>'dog','elma'=>'apple');
 foreach($map as $tr=>$en)$query=str_replace($tr,$en,$query);
 $query=preg_replace('/\b(ingilizce|english|sinif|unit|unite|konu|anlatimi|ornekleri|ile|icin|ve|the|and|in|of|a|an|to|isolated|object|white|background|simple|photo)\b/',' ',$query);
 $terms=array_values(array_unique(array_filter(preg_split('/[^a-z]+/',$query),function($v){return strlen($v)>2;})));
 if(!$terms)return new WP_Error('no_subject','Somut bir İngilizce görsel konusu girin.');
 $response=wp_remote_get(add_query_arg(array('query'=>implode(' ',array_slice($terms,0,6)),'per_page'=>24,'orientation'=>'landscape','locale'=>'en-US'),'https://api.pexels.com/v1/search'),array('timeout'=>30,'headers'=>array('Authorization'=>$api_key)));
 if(is_wp_error($response))return $response;
 if(wp_remote_retrieve_response_code($response)!==200)return new WP_Error('pexels_http','Pexels araması başarısız.');
 $body=json_decode(wp_remote_retrieve_body($response),true);$best=null;$best_score=0;
 foreach(($body['photos']??array()) as $photo){
  $alt=strtolower($photo['alt']??'');if(!$alt||preg_match('/\b(nude|naked|lingerie|weapon|gun|blood|beer|wine|cigarette)\b/',$alt))continue;
  $score=0;foreach($terms as $term)if(preg_match('/\b'.preg_quote($term,'/').'(?:s|es)?\b/',$alt))$score++;
  $needed=count($terms)>1?2:1;if($score<$needed)continue;
  if($score>$best_score){$best_score=$score;$best=$photo;}
 }
 if(!$best)return new WP_Error('irrelevant','Konuyla yeterince eşleşen fotoğraf bulunamadı; ilgisiz görsel eklenmedi. Daha somut İngilizce arama sözcükleri deneyin.');
 $url=$best['src']['large2x']??($best['src']['large']??'');if(wp_parse_url($url,PHP_URL_HOST)!=='images.pexels.com')return new WP_Error('invalid_host','Görsel adresi doğrulanamadı.');
 $image=wp_safe_remote_get($url,array('timeout'=>45,'limit_response_size'=>10*1024*1024));if(is_wp_error($image))return $image;
 $data=wp_remote_retrieve_body($image);$info=@getimagesizefromstring($data);
 if(wp_remote_retrieve_response_code($image)!==200||!$info||!in_array($info['mime'],array('image/jpeg','image/png','image/webp'),true))return new WP_Error('invalid_image','Geçerli fotoğraf alınamadı.');
 return array('data'=>$data,'credit'=>$best['photographer']??'','alt'=>$best['alt']??'');
}

/**
 * OpenAI ile görsel üretir (gpt-image-1), binary veri döndürür.
 */
function ewt_generate_openai_image( $prompt ) {
	$api_key = get_option( 'ewt_openai_api_key' );
	if ( ! $api_key ) return new WP_Error( 'no_key', 'OpenAI API anahtarı ayarlanmamış. Görünüm > English Web Ayarları\'ndan ekleyin.' );

	$response = wp_remote_post( 'https://api.openai.com/v1/images/generations', array(
		'timeout' => 90,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'body' => wp_json_encode( array(
			'model'  => 'gpt-image-1',
			'prompt' => $prompt,
			'size'   => '1024x1024',
			'n'      => 1,
		) ),
	) );

	if ( is_wp_error( $response ) ) return $response;
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! empty( $body['data'][0]['b64_json'] ) ) {
		return array( 'data' => base64_decode( $body['data'][0]['b64_json'] ), 'credit' => '' );
	}
	if ( ! empty( $body['data'][0]['url'] ) ) {
		$img_response = wp_remote_get( $body['data'][0]['url'], array( 'timeout' => 60 ) );
		if ( is_wp_error( $img_response ) ) return $img_response;
		return array( 'data' => wp_remote_retrieve_body( $img_response ), 'credit' => '' );
	}
	return new WP_Error( 'api_error', 'Görsel üretilemedi: ' . wp_remote_retrieve_body( $response ) );
}

/**
 * Bir posta, verilen sağlayıcı ve sorgu/prompt ile öne çıkan görsel atar.
 * Hem tekil widget hem de toplu araç bu fonksiyonu kullanır.
 *
 * $child_object = true ise: sorgu/prompt, çocuklara yönelik içerikler (okul öncesi/ilkokul
 * kelime kartları vb.) için izole, temiz, beyaz arkaplanlı "nesne/hayvan/renk" fotoğrafı
 * aramasına/üretimine dönüştürülür.
 */
function ewt_assign_featured_image( $post_id, $provider, $query, $child_object = false ) {
	if ( ! $query ) {
		$query = get_the_title( $post_id );
	}

	if ( $provider === 'openai' ) {
		if ( $child_object ) {
			$full_prompt = 'Basit, renkli, flashcard tarzı bir eğitim illüstrasyonu: "' . $query . '" nesnesi/hayvanı/rengi. Beyaz arkaplan üzerinde tek bir obje, net kontur çizgileri, yazı veya metin içermesin, filigran olmasın, okul öncesi/ilkokul çocukları için kelime kartı tasarımına uygun temiz vektör tarzı bir görsel olsun.';
		} else {
			$full_prompt = 'Modern, açık renkli, minimalist bir eğitim/İngilizce öğrenme blog görseli: ' . $query . '. Metin veya yazı içermesin, temiz ve profesyonel bir kapak görseli olsun.';
		}
		$result = ewt_generate_openai_image( $full_prompt );
	} else {
		$search_query = $child_object
			? $query . ' isolated object white background simple photo'
			: $query;
		$result = ewt_fetch_pexels_image( $search_query );
	}

	if ( is_wp_error( $result ) ) return $result;

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload_dir = wp_upload_dir();
	$filename   = 'ewt-featured-' . $post_id . '-' . time() . '.jpg';
	$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;
	file_put_contents( $file_path, $result['data'] );

	$attachment_id = media_handle_sideload( array( 'name' => $filename, 'tmp_name' => $file_path ), $post_id, get_the_title( $post_id ) );

	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $file_path );
		return $attachment_id;
	}

	if ( ! empty( $result['credit'] ) ) {
		update_post_meta( $attachment_id, '_ewt_photo_credit', sanitize_text_field( $result['credit'] ) );
	}

	update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $result['alt'] ?? get_the_title($post_id) ) );
	set_post_thumbnail( $post_id, $attachment_id );

	return array(
		'attachment_id' => $attachment_id,
		'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'medium' ),
	);
}

/**
 * Pexels'ten bir fotoğraf çeker ve medya kütüphanesine EKLENTİ (attachment) olarak yükler,
 * ama öne çıkan görsel olarak ATAMAZ — sadece içerik gövdesine gömülecek bir <img> için kullanılır.
 * ewt_assign_featured_image()'daki sideload mantığının aynısını kullanır, tek fark set_post_thumbnail() çağrılmaması.
 *
 * @return array|WP_Error [ 'url' => büyük boy görsel URL'i, 'credit' => fotoğrafçı adı ] ya da WP_Error
 */
function ewt_pexels_sideload_inline( $query, $post_id, $child_object = false ) {
	$search_query = $child_object ? $query . ' isolated object white background simple photo' : $query;
	$result = ewt_fetch_pexels_image( $search_query );
	if ( is_wp_error( $result ) ) return $result;

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload_dir = wp_upload_dir();
	$filename   = 'ewt-inline-' . $post_id . '-' . uniqid() . '.jpg';
	$file_path  = trailingslashit( $upload_dir['path'] ) . $filename;
	file_put_contents( $file_path, $result['data'] );

	$attachment_id = media_handle_sideload( array( 'name' => $filename, 'tmp_name' => $file_path ), $post_id, $query );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $file_path );
		return $attachment_id;
	}

	if ( ! empty( $result['credit'] ) ) {
		update_post_meta( $attachment_id, '_ewt_photo_credit', sanitize_text_field( $result['credit'] ) );
	}

	return array(
		'url'    => wp_get_attachment_image_url( $attachment_id, 'large' ),
		'credit' => isset( $result['credit'] ) ? $result['credit'] : '',
	);
}

/**
 * Üretilen HTML içeriğin <h2> başlıklarının hemen ARDINA, konuyla alakalı Pexels görselleri gömer.
 * Featured image'a EK olarak çalışır, onu değiştirmez. Sadece Pexels kullanılır (OpenAI ile üretim
 * pahalı/gereksiz olduğu için gövde-içi görsellerde tercih edilmez).
 *
 * Sorgu, ilgili bölümün <h2> metninden türetilir (o bölümle DOĞRUDAN alakalı olsun diye);
 * başlık çok kısa/anlamsızsa (örn. "Giriş") genel konuya (fallback_query) geri döner.
 *
 * @param string $content       Ham (henüz wpautop'tan geçmiş) HTML içerik
 * @param int    $post_id       Görsellerin ekleneceği yazı ID'si (medya kütüphanesi ilişkilendirmesi için)
 * @param string $fallback_query Başlık yetersizse kullanılacak genel arama sorgusu (genelde focus keyword)
 * @param int    $count         Eklenecek maksimum görsel sayısı (varsayılan 2)
 * @param bool   $child_object  Çocuklara yönelik izole-obje modu
 * @return string Görseller gömülmüş içerik (hata olursa içerik değişmeden döner, sessizce atlanır)
 */
function ewt_insert_inline_pexels_images( $content, $post_id, $fallback_query, $count = 2, $child_object = false ) {
	$count = max( 0, min( 4, intval( $count ) ) );
	if ( $count < 1 || ! $content ) return $content;
	if ( ! get_option( 'ewt_pexels_api_key' ) ) return $content; // anahtar yoksa sessizce atla

	if ( ! preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE ) || empty( $matches[0] ) ) {
		return $content; // H2 yoksa gömülecek doğal bir yer yok
	}

	$headings = $matches[0];
	$total_h2 = count( $headings );
	// Görselleri içerik boyunca yay: ilk H2'yi atla (genelde giriş), sonrasındakilere dengeli dağıt
	$candidate_indexes = $total_h2 > 1 ? range( 1, $total_h2 - 1 ) : range( 0, $total_h2 - 1 );
	if ( empty( $candidate_indexes ) ) $candidate_indexes = array( 0 );

	$step = max( 1, floor( count( $candidate_indexes ) / $count ) );
	$chosen = array();
	for ( $i = 0; $i < count( $candidate_indexes ) && count( $chosen ) < $count; $i += $step ) {
		$chosen[] = $candidate_indexes[ $i ];
	}

	// Eklemeler string uzunluğunu kaydıracağı için, ORİJİNAL offset'lere göre SONDAN BAŞA doğru işle
	rsort( $chosen );

	foreach ( $chosen as $idx ) {
		$heading_text = trim( wp_strip_all_tags( $matches[1][ $idx ][0] ) );
		$query = ( function_exists( 'mb_strlen' ) ? mb_strlen( $heading_text ) : strlen( $heading_text ) ) >= 4 ? $heading_text : $fallback_query;

		$img = ewt_pexels_sideload_inline( $query, $post_id, $child_object );
		if ( is_wp_error( $img ) ) continue; // bu görsel bulunamadıysa sessizce atla, diğerlerine devam

		$figure  = '<figure class="ewt-inline-image">';
		$figure .= '<img src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $heading_text ) . '" loading="lazy" />';
		if ( ! empty( $img['credit'] ) ) {
			$figure .= '<figcaption>Fotoğraf: ' . esc_html( $img['credit'] ) . ' / <a href="https://www.pexels.com" target="_blank" rel="nofollow noopener">Pexels</a></figcaption>';
		}
		$figure .= '</figure>';

		$insert_at = $matches[0][ $idx ][1] + strlen( $matches[0][ $idx ][0] );
		$content   = substr( $content, 0, $insert_at ) . $figure . substr( $content, $insert_at );
	}

	return $content;
}

function ewt_ajax_generate_featured_image() {
	check_ajax_referer( 'ewt_ai_image_generate', 'nonce' );
	if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'Yetkiniz yok.' );

	$post_id      = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$prompt       = isset( $_POST['prompt'] ) ? sanitize_textarea_field( $_POST['prompt'] ) : '';
	$provider     = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : 'pexels';
	$child_object = ! empty( $_POST['child_object'] );

	if ( ! $post_id ) wp_send_json_error( 'Geçersiz yazı.' );

	$result = ewt_assign_featured_image( $post_id, $provider, $prompt, $child_object );
	if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_ewt_generate_featured_image', 'ewt_ajax_generate_featured_image' );

function ewt_enqueue_image_admin_js( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
	wp_enqueue_script( 'ewt-image-admin', EWT_URI . '/assets/js/admin-ai-widget.js', array( 'jquery' ), EWT_VERSION, true );
	wp_localize_script( 'ewt-image-admin', 'ewtImage', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ewt_ai_image_generate' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'ewt_enqueue_image_admin_js' );
