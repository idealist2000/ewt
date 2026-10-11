<?php
/** Batch existing SEO metadata and markup without changing URLs or rewriting lesson prose. */
if ( ! defined( 'ABSPATH' ) ) exit;
function ewt_seo_maintenance_batch( $cursor = 0, $apply = false ) {
	global $wpdb;
	$types = ewt_seo_supported_post_types();
	$where = "post_type IN (" . implode(',',array_fill(0,count($types),'%s')) . ") AND post_status IN ('publish','draft','pending','future','private') AND ID>%d";
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE {$where} ORDER BY ID ASC LIMIT 20", array_merge($types,array(absint($cursor))) ) );
	$rows=array();$last=absint($cursor);
	foreach($ids as $id){
		$id=(int)$id;$last=$id;
		$fk=ewt_seo_focus($id);$content=get_post_field('post_content',$id);
		$old=get_post_meta($id,'_ewt_seo_title',true) ?: get_post_meta($id,'rank_math_title',true);
		$next=$fk ? ewt_seo_build_title($fk,$old ?: get_the_title($id),$content,get_post_meta($id,'_ewt_path_level',true)) : '';
		$status=$fk ? (is_wp_error($next)?'blocked':'ready') : 'missing';
		$message=is_wp_error($next)?$next->get_error_message():($fk?'':'Önce focus keyword belirleyin.');
		if(!current_user_can('edit_post',$id)){ $status='forbidden';$message='Bu içeriği düzenleme yetkiniz yok.'; }
		if($apply && $status==='ready'){
			$desc=get_post_meta($id,'_ewt_seo_desc',true) ?: get_post_meta($id,'rank_math_description',true);
			$result=ewt_apply_seo_fields($id,$fk,$next,$desc,false);
			$status=isset($result['error'])?'blocked':'updated';$message=$result['error']??'';
			$content=get_post_field('post_content',$id);
		}
		$rows[]=array('id'=>$id,'title'=>get_the_title($id),'fk'=>$fk,'old_title'=>$old,'new_title'=>is_wp_error($next)?'':$next,'density'=>ewt_seo_density($content,$fk),'status'=>$status,'message'=>$message);
	}
	$more=(bool)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE {$where} LIMIT 1",array_merge($types,array($last))));
	return array('rows'=>$rows,'cursor'=>$last,'more'=>$more);
}
add_action('admin_menu',function(){add_theme_page('SEO İçerik Kontrolü','SEO İçerik Kontrolü','manage_options','ewt-seo-maintenance','ewt_seo_maintenance_page');});
function ewt_seo_maintenance_page(){
	if(!current_user_can('manage_options'))return;
	?><div class="wrap"><h1>SEO İçerik Kontrolü</h1>
	<p>Mevcut içerikleri 20’şerli partilerle analiz eder. Güncelleme; Rank Math/tema başlığı, görsel alt metinleri ve dış kaynak bağlantılarını düzenler. Mevcut URL’ler korunur. Focus keyword eksikse veya 55 karakterden uzunsa içerik atlanır.</p>
	<p>Anahtar kelime yoğunluğu hedefi %1,9’dur. Mevcut ders metni otomatik yeniden yazılmaz; ölçülen oran ve doğal düzenleme için önerilen kullanım sayısı gösterilir. Yeni AI içeriklerinde %1,9 talimatı kullanılır. Kesin oran, içerik uzunluğuna ve metnin doğal kullanımına bağlıdır.</p>
	<div id="ewt-seo-maintenance"><button type="button" class="button" data-seo="preview">İlk partiyi analiz et</button> <button type="button" class="button" data-seo="next" disabled>Sonraki partiyi analiz et</button> <button type="button" class="button button-primary" data-seo="apply" disabled>Görünen partiyi güncelle</button> <button type="button" class="button" data-seo="all">Tüm içerikleri güncelle</button> <button type="button" class="button" data-seo="stop" hidden>Bu partiden sonra dur</button>
	<p data-seo="status" role="status" aria-live="polite"></p><div data-seo="rows"></div></div></div><?php
}
add_action('admin_enqueue_scripts',function($hook){
	if($hook!=='appearance_page_ewt-seo-maintenance')return;
	wp_enqueue_script('ewt-seo-maintenance',EWT_URI.'/assets/js/seo-maintenance.js',array(),EWT_VERSION,true);
	wp_localize_script('ewt-seo-maintenance','ewtSeoMaintenance',array('url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('ewt_seo_maintenance')));
});
add_action('wp_ajax_ewt_seo_maintenance',function(){
	if(!current_user_can('manage_options'))wp_send_json_error(array('message'=>'Yetkiniz yok.'),403);
	if(!isset($_POST['nonce']) || !is_string($_POST['nonce']) || !check_ajax_referer('ewt_seo_maintenance','nonce',false))wp_send_json_error(array('message'=>'Sayfayı yenileyin.'),403);
	$mode=isset($_POST['mode']) && is_string($_POST['mode'])?$_POST['mode']:'';
	if(!in_array($mode,array('preview','apply'),true))wp_send_json_error(array('message'=>'Geçersiz işlem.'),400);
	$cursor=isset($_POST['cursor']) && is_scalar($_POST['cursor'])?absint($_POST['cursor']):0;
	wp_send_json_success(ewt_seo_maintenance_batch($cursor,$mode==='apply'));
});
add_action('add_meta_boxes',function(){foreach(ewt_seo_supported_post_types() as $type)add_meta_box('ewt-seo-density','Focus Keyword Yoğunluğu','ewt_seo_density_box',$type,'side');});
function ewt_seo_density_box($post){
	$fk=ewt_seo_focus($post->ID);
	if(!$fk){echo '<p>Yoğunluğu ölçmek için focus keyword belirleyip kaydedin.</p>';return;}
	$data=ewt_seo_density($post->post_content,$fk);
	echo '<p><strong>'.esc_html($data['percent']).'%</strong> · Hedef %1,9</p><p>'.esc_html($data['occurrences'].' kullanım / '.$data['words'].' kelime. Bu uzunlukta yaklaşık '.$data['suggested_occurrences'].' doğal kullanım hedeflenir.').'</p><p>Ders metni, başlık ve giriş arasında doğal biçimde dağıtın; tekrar cümleleri eklemeyin. Ölçüm son kaydedilen metne dayanır.</p>';
}
