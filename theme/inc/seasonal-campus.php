<?php
/** Seasonal identity, K12 catalogue, contact controls and daily editorial queue. */
if (!defined('ABSPATH')) exit;
function ewt_campus_defaults() {
 static $topics=null; if($topics===null)$topics=file_get_contents(EWT_DIR.'/inc/k12-topics.txt');
 return array('whatsapp'=>'','email'=>'','cta'=>'İngilizce hedefin için birlikte çalışalım','contact_enabled'=>'1','season'=>'auto','days'=>30,'events'=>'2026-09-14|2026-09-14|Okullar açılıyor
2026-11-16|2026-11-20|Birinci dönem ara tatili
2027-01-25|2027-02-05|Yarıyıl tatili
2027-02-08|2027-02-08|İkinci dönem başlıyor
2027-03-08|2027-03-12|İkinci dönem ara tatili
2027-03-08T13:00|2027-03-11|Ramazan Bayramı
2027-05-15T13:00|2027-05-19|Kurban Bayramı
2027-06-25T16:00|2027-06-25|Yaz tatili başlıyor','topics'=>$topics,'queue'=>'','daily'=>'0','hour'=>'09:00','provider'=>'youcom','status'=>'publish','archive_url'=>'','archive_image'=>'');
}
function ewt_campus_options() { return wp_parse_args((array)get_option('ewt_campus',array()),ewt_campus_defaults()); }
function ewt_campus_season() { $s=ewt_campus_options()['season']; if($s!=='auto')return $s; $m=(int)wp_date('n',null,new DateTimeZone('Europe/Istanbul'));return $m>=3&&$m<=5?'spring':($m>=6&&$m<=8?'summer':($m>=9&&$m<=11?'autumn':'winter')); }
function ewt_campus_logo() { echo '<a class="ewt-campus-logo" href="'.esc_url(home_url('/')).'" aria-label="English Web TR ana sayfa">'; readfile(EWT_DIR.'/assets/images/logo.svg'); echo '</a>'; }
add_filter('body_class',function($c){$c[]='ewt-season-'.ewt_campus_season();return $c;});
add_action('wp_enqueue_scripts',function(){
 wp_enqueue_style('ewt-campus',EWT_URI.'/assets/css/campus.css',array('ewt-main'),EWT_VERSION);
 wp_enqueue_script('ewt-campus',EWT_URI.'/assets/js/campus.js',array(),EWT_VERSION,true);
 wp_localize_script('ewt-campus','ewtCampus',array('season'=>ewt_campus_options()['season'],'uri'=>EWT_URI));
},30);
add_action('wp_head',function(){echo '<link id="ewt-season-icon" rel="icon" type="image/svg+xml" href="'.esc_url(EWT_URI.'/assets/images/favicon-'.ewt_campus_season().'.svg').'">';},100);
function ewt_campus_wa() {$o=ewt_campus_options();$n=preg_replace('/\D/','',$o['whatsapp']?:get_option('ewt_tutoring_phone',''));if(strlen($n)===11&&$n[0]==='0')$n='9'.$n;elseif(strlen($n)===10)$n='90'.$n;return $n;}
function ewt_campus_contacts() {
 $o=ewt_campus_options();$p=get_option('ewt_tutoring_phone','');$w=ewt_campus_wa();$e=$o['email'];$out='<div class="ewt-campus-contacts">';
 if($p)$out.='<a href="tel:'.esc_attr(preg_replace('/[^+0-9]/','',$p)).'">'.esc_html($p).'</a>';
 if($w)$out.='<a href="https://wa.me/'.esc_attr($w).'" target="_blank" rel="noopener">WhatsApp</a>';
 if($e)$out.='<a href="mailto:'.esc_attr($e).'">'.esc_html($e).'</a>';
 return $out.'</div>';
}
add_shortcode('ewt_contact',function(){return ewt_campus_contacts();});
add_shortcode('ewt_cta',function(){ $o=ewt_campus_options();return '<aside class="ewt-campus-cta"><h2>'.esc_html($o['cta']).'</h2>'.ewt_campus_contacts().'</aside>'; });
function ewt_campus_pt($g){return $g<=4?'ewt_primary':($g<=8?'ewt_secondary':'ewt_highschool');}
function ewt_campus_topics(){
 $out=array();foreach(preg_split('/\R/',ewt_campus_options()['topics']) as $line){$v=explode('|',$line,2);if(count($v)!==2)continue;$g=(int)$v[0];$t=trim($v[1]);if($g<1||$g>12||!$t)continue;$slug=sanitize_title($g.'-sinif-ingilizce-'.$t);$out[$slug]=array('grade'=>$g,'title'=>$t,'slug'=>$slug,'pt'=>ewt_campus_pt($g));}return $out;
}
function ewt_campus_term($g){$tax=ewt_campus_pt($g).'_seviye';$slug=$g.'-sinif';$term=get_term_by('slug',$slug,$tax);return $term&&!is_wp_error($term)?$term:null;}
add_action('init',function(){if(get_option('ewt_campus_terms_v170'))return;$ok=true;for($g=1;$g<=12;$g++){if(!ewt_campus_term($g)){ $r=wp_insert_term($g.'. Sınıf',ewt_campus_pt($g).'_seviye',array('slug'=>$g.'-sinif'));if(is_wp_error($r))$ok=false;}}if($ok)update_option('ewt_campus_terms_v170',1);},25);
function ewt_campus_topic_post($t){
 static $index=null;if($index===null){$index=array();$args=array('post_type'=>array('ewt_primary','ewt_secondary','ewt_highschool'),'post_status'=>'publish','posts_per_page'=>-1,'no_found_rows'=>true);$by_slug=get_posts(array_merge($args,array('post_name__in'=>array_keys(ewt_campus_topics()))));$by_meta=get_posts(array_merge($args,array('meta_key'=>'_ewt_campus_unit','meta_compare'=>'EXISTS')));foreach(array_merge($by_slug,$by_meta) as $p){$index[$p->post_type.':'.$p->post_name]=$p;$key=get_post_meta($p->ID,'_ewt_campus_unit',true);if($key)$index[$p->post_type.':'.$key]=$p;}}
 return $index[$t['pt'].':'.$t['slug']]??null;
}
function ewt_campus_topic_url($t){$p=ewt_campus_topic_post($t);return $p?get_permalink($p):add_query_arg('ewt_unit',$t['slug'],home_url('/'));}
add_filter('query_vars',function($v){$v[]='ewt_unit';return $v;});
add_action('template_redirect',function(){
 $slug=get_query_var('ewt_unit');if(!$slug)return;$all=ewt_campus_topics();if(!isset($all[$slug])){global $wp_query;$wp_query->set_404();status_header(404);return;}
 $t=$all[$slug];$p=ewt_campus_topic_post($t);if($p){wp_safe_redirect(get_permalink($p),301);exit;}
 status_header(200);nocache_headers();get_header();echo '<article class="ewt-container ewt-campus-unit"><p>'.esc_html($t['grade']).'. SINIF • İNGİLİZCE</p><h1>'.esc_html($t['title']).'</h1><p>Bu konunun ders içeriği henüz yayımlanmadı. Sınıfındaki diğer dersleri keşfedebilir, kelime ve dinleme çalışmalarıyla devam edebilirsin.</p>';
 $term=ewt_campus_term($t['grade']);if($term){$url=get_term_link($term);if(!is_wp_error($url))echo '<a class="ewt-cta-button" href="'.esc_url($url).'">Sınıfın derslerine git</a>';}
 if(current_user_can('edit_posts'))echo '<p><a href="'.esc_url(admin_url('themes.php?page=ewt-campus')).'">Bu ünite için içerik hazırla</a></p>';
 echo '</article>';get_footer();exit;
});
add_filter('wp_robots',function($r){if(get_query_var('ewt_unit'))$r['noindex']=true;return $r;});
add_filter('pre_get_document_title',function($s){$a=ewt_campus_topics();$k=get_query_var('ewt_unit');return isset($a[$k])?$a[$k]['grade'].'. Sınıf İngilizce '.$a[$k]['title'].' | English Web TR':$s;});
function ewt_campus_k12(){
 $a=ewt_campus_topics();echo '<section id="ewt-k12" class="ewt-container ewt-campus-section"><p class="ewt-campus-eyebrow">KÜÇÜK ADIMLAR, BÜYÜK HEDEFLER</p><h2>1’den 12’ye İngilizce yol haritan</h2><p>Sınıfını seç, çalışmak istediğin konuyla başla.</p><div class="ewt-campus-grades">';
 for($g=1;$g<=12;$g++){echo '<details class="ewt-campus-grade"><summary><span>'.esc_html($g).'. sınıf</span><small>'.($g===1?'İngilizceye hazırlık':($g<=4?'İlkokul':($g<=8?'Ortaokul':'Lise'))).'</small></summary><ul>';foreach($a as $t){if($t['grade']!==$g)continue;echo '<li><a href="'.esc_url(ewt_campus_topic_url($t)).'">'.esc_html($t['title']).'</a></li>';}echo '</ul></details>';}
 echo '</div></section>';
}
function ewt_campus_events(){
 $events=array();$y=(int)wp_date('Y',null,new DateTimeZone('Europe/Istanbul'));
 foreach(array($y,$y+1) as $year){foreach(array('01-01'=>'Yılbaşı','04-23'=>'Ulusal Egemenlik ve Çocuk Bayramı','05-01'=>'Emek ve Dayanışma Günü','05-19'=>'Atatürk’ü Anma, Gençlik ve Spor Bayramı','07-15'=>'Demokrasi ve Millî Birlik Günü','08-30'=>'Zafer Bayramı','10-29'=>'Cumhuriyet Bayramı') as $d=>$name){$start=$d==='10-29'?$year.'-10-28T13:00:00+03:00':$year.'-'.$d.'T00:00:00+03:00';$events[]=array('name'=>$name,'start'=>$start,'end'=>$year.'-'.$d.'T23:59:59+03:00');}}
 foreach(preg_split('/\R/',ewt_campus_options()['events']) as $line){$v=array_map('trim',explode('|',$line));if(count($v)<3)continue;$a=ewt_campus_date($v[0]);$b=ewt_campus_date($v[1],true);if($a&&$b&&$b>=$a)$events[]=array('name'=>$v[2],'start'=>$a->format('c'),'end'=>$b->format('c'));}
 return $events;
}
function ewt_campus_date($s,$end=false){if(!preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2})?$/',$s))return false;$format=strlen($s)===10?'!Y-m-d':'!Y-m-d\TH:i';$d=DateTimeImmutable::createFromFormat($format,$s,new DateTimeZone('Europe/Istanbul'));$e=DateTimeImmutable::getLastErrors();if(!$d||($e&&($e['warning_count']||$e['error_count'])))return false;if($end&&strlen($s)===10)$d=$d->setTime(23,59,59);return $d;}
function ewt_campus_countdown(){
 $pts=array('ewt_primary','ewt_secondary','ewt_highschool','ewt_preschool');$tax=array_map(function($x){return $x.'_seviye';},$pts);
 if(!is_front_page()&&!is_singular($pts)&&!is_post_type_archive($pts)&&!is_tax($tax)&&!get_query_var('ewt_unit'))return;
 echo '<aside class="ewt-container ewt-campus-countdown" hidden data-days="'.esc_attr(ewt_campus_options()['days']).'" data-events="'.esc_attr(wp_json_encode(ewt_campus_events())).'"><span class="ewt-event-name"></span><strong class="ewt-event-time"></strong></aside>';
}
function ewt_campus_nostalgia(){
 $o=ewt_campus_options();echo '<section class="ewt-container ewt-campus-section ewt-nostalgia"><div><p class="ewt-campus-eyebrow">DÜNDEN BUGÜNE</p><h2>2005’ten beri aynı heyecan</h2><p>English Web TR’nin geçmişine bir yolculuk.</p><a href="'.esc_url($o['archive_url']?:'https://web.archive.org/web/2005*/http://english.web.tr/').'" target="_blank" rel="noopener">Internet Archive’da geçmişi keşfet ↗</a></div>';
 if($o['archive_image'])echo '<img src="'.esc_url($o['archive_image']).'" alt="English Web TR arşiv ekran görüntüsü" loading="lazy">';
 echo '</section>';
}
add_action('admin_menu',function(){add_theme_page('Kampüs ve Otomasyon','Kampüs ve Otomasyon','manage_options','ewt-campus','ewt_campus_admin');});
function ewt_campus_admin(){
 if(!current_user_can('manage_options'))return;$o=ewt_campus_options();
 echo '<div class="wrap"><h1>English Web TR — Kampüs ve Otomasyon</h1>';if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Ayarlar kaydedildi.</p></div>';
 echo '<p>Telefon tüm mevcut CTA’larla ortaktır. WhatsApp boşsa telefon kullanılır. Kısa kodlar: <code>[ewt_contact]</code> ve <code>[ewt_cta]</code>.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('ewt_campus_save');echo '<input type="hidden" name="action" value="ewt_campus_save"><table class="form-table">';
 $fields=array('phone'=>'Telefon','whatsapp'=>'WhatsApp','email'=>'E-posta','cta'=>'CTA başlığı','days'=>'Sayaç kaç gün önce görünsün?','hour'=>'Günlük yayın saati (İstanbul)','archive_url'=>'Doğrulanmış Wayback kayıt bağlantısı','archive_image'=>'Arşiv ekran görüntüsü URL’si (Medya Kütüphanesi)');
 foreach($fields as $k=>$label){$val=$k==='phone'?get_option('ewt_tutoring_phone',''):$o[$k];$type=$k==='days'?'number':($k==='hour'?'time':($k==='email'?'email':'text'));echo '<tr><th><label for="campus-'.$k.'">'.esc_html($label).'</label></th><td><input class="regular-text" id="campus-'.$k.'" name="'.$k.'" type="'.$type.'" value="'.esc_attr($val).'"></td></tr>';}
 foreach(array('season'=>array('auto'=>'Otomatik mevsim','autumn'=>'Sonbahar — yaprak sarısı','winter'=>'Kış — beyaz','spring'=>'İlkbahar — yeşil','summer'=>'Yaz — açık sarı'),'contact_enabled'=>array('1'=>'Alt CTA ve sohbet açık','0'=>'Alt CTA ve sohbet kapalı'),'daily'=>array('0'=>'Günlük blog kapalı','1'=>'Günlük blog açık'),'provider'=>array('youcom'=>'You.com','openai'=>'OpenAI','claude'=>'Claude'),'status'=>array('publish'=>'Doğrudan yayımla','draft'=>'Taslak oluştur')) as $k=>$choices){echo '<tr><th>'.esc_html($k).'</th><td><select name="'.$k.'">';foreach($choices as $v=>$label)echo '<option value="'.$v.'" '.selected($o[$k],$v,false).'>'.esc_html($label).'</option>';echo '</select></td></tr>';}
 foreach(array('events'=>'Etkinlikler: başlangıç|bitiş|ad (YYYY-AA-GG veya YYYY-AA-GGT13:00). Okul açılışı, ara tatil ve bayram tarihlerini buradan düzenleyin.','topics'=>'Sınıf ve konu kataloğu: sınıf|ünite adı. Bu başlangıç kataloğu destekleyici konular içerir; onaylı yıllık müfredat değildir. Kullandığınız ders kitabına göre düzenleyin. 1. sınıf İngilizceye hazırlıktır.','queue'=>'Günlük blog kuyruğu: her satıra bir başlık. Tamamlanan başlıklar yeniden üretilmez.') as $k=>$label){echo '<tr><th><label for="campus-'.$k.'">'.esc_html($label).'</label></th><td><textarea id="campus-'.$k.'" name="'.$k.'" rows="10" class="large-text">'.esc_textarea($o[$k]).'</textarea></td></tr>';}
 echo '</table>';submit_button('Ayarları kaydet');echo '</form><p>Günlük görev: '.esc_html(get_option('ewt_campus_log','Henüz çalışmadı.')).'</p><p>WP-Cron ziyaretlerle tetiklenir. Kesin saat için sunucuda wp-cron.php çağrısı planlayın. API anahtarları: Görünüm → English Web Ayarları.</p>';ewt_k12_publisher_panel();echo '</div>';
}
add_action('admin_post_ewt_campus_save',function(){
 if(!current_user_can('manage_options'))wp_die('Yetkiniz yok.');check_admin_referer('ewt_campus_save');$old=ewt_campus_options();$o=$old;
 foreach(ewt_campus_defaults() as $k=>$v){$raw=isset($_POST[$k])?wp_unslash($_POST[$k]):$v;$o[$k]=in_array($k,array('topics','events','queue'),true)?sanitize_textarea_field($raw):sanitize_text_field($raw);}
 $o['email']=sanitize_email($o['email']);$o['archive_image']=esc_url_raw($o['archive_image']);$o['archive_url']=esc_url_raw($o['archive_url']);$o['days']=max(1,min(180,(int)$o['days']));
 foreach(array('season'=>array('auto','autumn','winter','spring','summer'),'provider'=>array('youcom','openai','claude'),'status'=>array('draft','publish'),'daily'=>array('0','1'),'contact_enabled'=>array('0','1')) as $k=>$allowed)if(!in_array($o[$k],$allowed,true))$o[$k]=ewt_campus_defaults()[$k];
 if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$o['hour']))$o['hour']='09:00';
 $valid=array();foreach(preg_split('/\R/',$o['events']) as $line){$v=array_map('trim',explode('|',$line));if(count($v)===3&&ewt_campus_date($v[0])&&ewt_campus_date($v[1],true)&&ewt_campus_date($v[1],true)>=ewt_campus_date($v[0]))$valid[]=implode('|',$v);elseif(trim($line))wp_die('Etkinlik tarihi geçersiz: '.esc_html($line).' — geri dönüp düzeltin.');}$o['events']=implode("\n",$valid);
 update_option('ewt_tutoring_phone',sanitize_text_field(wp_unslash($_POST['phone']??'')));update_option('ewt_campus',$o);update_option('ewt_campus_author',get_current_user_id());wp_clear_scheduled_hook('ewt_campus_daily');ewt_campus_schedule();wp_safe_redirect(admin_url('themes.php?page=ewt-campus&saved=1'));exit;
});
function ewt_campus_schedule(){if(ewt_campus_options()['daily']!=='1'||wp_next_scheduled('ewt_campus_daily'))return;$o=ewt_campus_options();$at=new DateTimeImmutable('today '.$o['hour'],new DateTimeZone('Europe/Istanbul'));if($at->getTimestamp()<=time())$at=$at->modify('+1 day');wp_schedule_event($at->getTimestamp(),'daily','ewt_campus_daily');}
add_action('init','ewt_campus_schedule',30);
add_action('switch_theme',function(){wp_clear_scheduled_hook('ewt_campus_daily');});
add_action('ewt_campus_daily',function(){
 $o=ewt_campus_options();if($o['daily']!=='1')return;$today=wp_date('Y-m-d',null,new DateTimeZone('Europe/Istanbul'));if(get_option('ewt_campus_last_day')===$today)return;
 if((int)get_option('ewt_campus_lock')<time()-900)delete_option('ewt_campus_lock');if(!add_option('ewt_campus_lock',time(),'','no'))return;
 try{
 $done=(array)get_option('ewt_campus_done',array());$topic='';$key='';foreach(preg_split('/\R/',$o['queue']) as $line){$line=trim($line);if($line&&!isset($done[hash('sha256',$line)])){$topic=$line;$key=hash('sha256',$line);break;}}
 if(!$topic){update_option('ewt_campus_log','Kuyruk tamamlandı veya boş.');return;}
 $found=get_posts(array('post_type'=>'post','post_status'=>array('publish','draft','pending','future','private','trash'),'meta_key'=>'_ewt_queue_key','meta_value'=>$key,'numberposts'=>1));
 if($found){$done[$key]=$found[0]->ID;update_option('ewt_campus_done',$done);return;}
 $result=ewt_call_ai_provider($o['provider'],ewt_content_system_prompt('post'),ewt_content_user_prompt($topic,'post','a2',700,$topic));if(is_wp_error($result)){update_option('ewt_campus_log',wp_date('c').' — İçerik API isteği başarısız. Anahtar ve kotayı kontrol edin.');return;}
 $content=wp_kses_post($result);if(strlen(wp_strip_all_tags($content))<200){update_option('ewt_campus_log','Yanıt çok kısa; kuyruk korunarak atlandı.');return;}
 $id=wp_insert_post(array('post_type'=>'post','post_status'=>$o['status'],'post_title'=>$topic,'post_content'=>$content,'post_author'=>(int)get_option('ewt_campus_author',0),'meta_input'=>array('_ewt_queue_key'=>$key)),true);if(is_wp_error($id)){update_option('ewt_campus_log','Yazı kaydedilemedi.');return;}
 $done[$key]=$id;update_option('ewt_campus_done',$done);update_option('ewt_campus_last_day',$today);
 ewt_apply_seo_fields($id,$topic,$topic,wp_trim_words(wp_strip_all_tags($content),25),true);
 if(get_option('ewt_pexels_api_key'))ewt_assign_featured_image($id,'pexels',$topic);
 update_option('ewt_campus_log',wp_date('c').' — '.$topic.' ('.$o['status'].', #'.$id.')');
 }finally{delete_option('ewt_campus_lock');}
});
