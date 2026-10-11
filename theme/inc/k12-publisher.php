<?php
if (!defined('ABSPATH')) exit;
function ewt_k12_publisher_panel(){
 ?>
 <section id="ewt-k12-publisher"><h2>K/12 — Tek tıkla yazdır ve yayımla</h2>
 <p>Ünite taslaklarını sırayla tamamlar ve doğrudan <strong>Yayımlandı (publish)</strong> durumuna geçirir. Yazılı içeriği olan taslaklar mevcut metinleriyle yayımlanır; boş üniteler için yapay zekâ içerik üretir. Yayımlanmış, dolu dersler tekrar yazılmaz.</p>
 <p><label for="ewt-k12-grade">Sınıf </label><select id="ewt-k12-grade"><option value="0">Tüm sınıflar (1–12)</option><?php for($g=1;$g<=12;$g++)echo '<option value="'.$g.'">'.$g.'. sınıf</option>'; ?></select>
 <label for="ewt-k12-provider"> Sağlayıcı </label><select id="ewt-k12-provider"><?php foreach(array('youcom'=>'You.com','openai'=>'OpenAI','claude'=>'Claude') as $v=>$n)echo '<option value="'.$v.'" '.selected(ewt_campus_options()['provider'],$v,false).'>'.$n.'</option>'; ?></select></p>
 <p><label><input type="checkbox" id="ewt-k12-missing"> Henüz oluşturulmamış katalog ünitelerini de yazdır ve yayımla</label></p>
 <p><button type="button" class="button button-primary" id="ewt-k12-start">Ünite içeriklerini yazdır ve yayımla</button> <button type="button" class="button" id="ewt-k12-stop" disabled>Bu üniteden sonra duraklat</button></p>
 <p>İşlem bitene kadar bu sekmeyi açık tutun. Sonra tekrar başlatırsanız tamamlanmış üniteler atlanır. API anahtarları English Web Ayarları bölümünden alınır.</p>
 <progress id="ewt-k12-progress" value="0" max="1" style="width:min(100%,650px)"></progress>
 <p id="ewt-k12-status" role="status">Başlatılmadı.</p><ul id="ewt-k12-results" style="max-height:350px;overflow:auto"></ul>
 </section>
 <?php
}
add_action('admin_enqueue_scripts',function($hook){if($hook!=='appearance_page_ewt-campus')return;wp_enqueue_script('ewt-k12-publisher',EWT_URI.'/assets/js/k12-publisher.js',array(),EWT_VERSION,true);wp_localize_script('ewt-k12-publisher','ewtK12',array('url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('ewt_k12_publish')));});
function ewt_k12_post($t){
 $matches=get_posts(array('post_type'=>$t['pt'],'post_status'=>array('publish','draft','pending','future','private','trash'),'meta_key'=>'_ewt_campus_unit','meta_value'=>$t['slug'],'numberposts'=>1));
 return $matches?$matches[0]:get_page_by_path($t['slug'],OBJECT,$t['pt']);
}
function ewt_k12_has_content($post){return $post&&trim(wp_strip_all_tags($post->post_content))!=='';}
function ewt_k12_can_process($post,$missing){
 if(!$post)return $missing;
 return $post->post_status==='draft'||($post->post_status==='publish'&&!ewt_k12_has_content($post));
}
function ewt_k12_request_guard(){check_ajax_referer('ewt_k12_publish','nonce');if(!current_user_can('manage_options')||!current_user_can('publish_posts'))wp_send_json_error('Bu işlem için yayın yetkisi gerekiyor.',403);}
add_action('wp_ajax_ewt_k12_queue',function(){
 ewt_k12_request_guard();$grade=absint($_POST['grade']??0);$missing=!empty($_POST['missing']);$jobs=array();
 foreach(ewt_campus_topics() as $t){if($grade&&$t['grade']!==$grade)continue;$post=ewt_k12_post($t);if(ewt_k12_can_process($post,$missing))$jobs[]=array('slug'=>$t['slug'],'title'=>$t['grade'].'. Sınıf İngilizce '.$t['title']);}
 wp_send_json_success(array('jobs'=>$jobs));
});
/** One unit per request: no empty publication, stable ID, no duplicate completed work. */
function ewt_k12_publish_unit($t,$provider,$missing){
 $lock='ewt_k12_lock_'.md5($t['slug']);$held=(int)get_option($lock,0);if($held&&$held<time()-600)delete_option($lock);
 if(!add_option($lock,time(),'','no'))return new WP_Error('busy','Bu ünite başka bir işlemde hazırlanıyor. Daha sonra yeniden başlatın.');
 try{
  $post=ewt_k12_post($t);
  if(!ewt_k12_can_process($post,$missing))return array('skipped'=>true,'message'=>'Tamamlanmış veya işlem dışı; atlandı.');
  if($post&&!current_user_can('edit_post',$post->ID))return new WP_Error('forbidden','Bu üniteyi düzenleme yetkiniz yok.');
  $title=$t['grade'].'. Sınıf İngilizce '.$t['title'];$content=$post?$post->post_content:'';
  if(!ewt_k12_has_content($post)){
   $key=array('youcom'=>'ewt_youcom_api_key','openai'=>'ewt_openai_api_key','claude'=>'ewt_claude_api_key')[$provider];
   if(!get_option($key))return new WP_Error('no_key','Seçilen sağlayıcının API anahtarı English Web Ayarları bölümünde eksik.');
   $difficulty=$t['grade']<=4?'a1':($t['grade']<=8?'a2':'b1');
   $topic=$title.'. Yalnızca bu sınıf ve üniteye uygun, özgün bir İngilizce ders içeriği hazırla. Türkçe açıklamalar, İngilizce örnekler ve Türkçe karşılıkları, kelime tablosu, aşamalı alıştırmalar ve sonunda cevap anahtarı kullan. Ünite kazanımlarını resmî kaynakla doğrulamadıysan resmî müfredat iddiası kurma. HTML h2/h3 kullan; markdown kod çiti ve h1 ekleme. Konu dışına çıkma.';
   $result=ewt_call_ai_provider($provider,ewt_content_system_prompt($t['pt']),ewt_content_user_prompt($topic,$t['pt'],$difficulty,800,$title),true);
   if(is_wp_error($result))return $result;
   $content=wp_kses_post(preg_replace('/^\s*```(?:html)?\s*|\s*```\s*$/i','',trim($result)));
   if(strlen(trim(wp_strip_all_tags($content)))<200)return new WP_Error('empty','Yeterli ders içeriği üretilemedi; ünite yayımlanmadı.');
  }
  // Re-read after a slow API response: preserve an editor's intervening changes.
  $latest=ewt_k12_post($t);
  if($post&&(!$latest||$latest->ID!==$post->ID||$latest->post_status!==$post->post_status||$latest->post_content!==$post->post_content))return new WP_Error('changed','Ünite işlem sırasında değiştirildi. Yeniden başlatın.');
  if(!$post&&$latest)return new WP_Error('changed','Ünite işlem sırasında oluşturuldu. Yeniden başlatın.');
  $data=array('post_content'=>$content,'post_status'=>'publish');
  if($post)$data['ID']=$post->ID;else $data+=array('post_type'=>$t['pt'],'post_title'=>$title,'post_name'=>$t['slug'],'post_author'=>get_current_user_id(),'meta_input'=>array('_ewt_campus_unit'=>$t['slug']));
  $id=$post?wp_update_post($data,true):wp_insert_post($data,true);if(is_wp_error($id))return $id;
  update_post_meta($id,'_ewt_campus_unit',$t['slug']);$term=ewt_campus_term($t['grade']);if($term)wp_set_object_terms($id,array((int)$term->term_id),$t['pt'].'_seviye',true);
  ewt_apply_seo_fields($id,$title,get_the_title($id),wp_trim_words(wp_strip_all_tags($content),25),false);
  return array('id'=>$id,'url'=>get_permalink($id),'message'=>'İçerik hazırlandı ve yayımlandı.');
 }finally{delete_option($lock);}
}
add_action('wp_ajax_ewt_k12_publish_unit',function(){
 ewt_k12_request_guard();$slug=sanitize_title(wp_unslash($_POST['slug']??''));$topics=ewt_campus_topics();if(!isset($topics[$slug]))wp_send_json_error('Ünite katalogda bulunamadı.',400);
 $provider=sanitize_key($_POST['provider']??'youcom');if(!in_array($provider,array('youcom','openai','claude'),true))wp_send_json_error('Geçersiz sağlayıcı.',400);
 if(function_exists('set_time_limit'))@set_time_limit(300);
 $result=ewt_k12_publish_unit($topics[$slug],$provider,!empty($_POST['missing']));
 if(is_wp_error($result))wp_send_json_error(sanitize_text_field($result->get_error_message()));wp_send_json_success($result);
});
