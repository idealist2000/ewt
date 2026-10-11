<?php
/** English Web TR: Mesleki IT B1 ders üretimi. Existing You.com gateway. */
if (!defined('ABSPATH')) exit;

add_action('init', function () {
 register_taxonomy('ewt_mesleki_sektor', array('ewt_mesleki'), array('label'=>'Mesleki sektör','public'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>array('slug'=>'mesleki-sektor')));
});

function ewt_esp_lessons() {
 return array(
  array('slug'=>'it-help-desk','title'=>'IT Help Desk: Responding to Support Requests','topic'=>'Help desk support ticket; describe a user problem, troubleshoot and confirm resolution'),
  array('slug'=>'it-team-meeting','title'=>'IT Team Meetings and Daily Stand-ups','topic'=>'Daily stand-up; explain progress, ask for help, describe blockers politely'),
  array('slug'=>'it-software-bugs','title'=>'Reporting Software Bugs in English','topic'=>'Describe a reproducible software defect, expected vs actual results, prioritize and explain next steps'),
  array('slug'=>'it-cybersecurity','title'=>'Cybersecurity: Safe Workplace Communication','topic'=>'Explain phishing, passwords, multi-factor authentication and safe incident reporting without sensitive technical procedures'),
  array('slug'=>'it-project-update','title'=>'IT Project Updates and Client Emails','topic'=>'Give a project status update; discuss deadlines, scope and write short professional follow-up messages'),
  array('slug'=>'it-hardware','title'=>'Computer Hardware and Peripherals','topic'=>'Explain device specifications, peripherals and common hardware problems'),
  array('slug'=>'it-operating-systems','title'=>'Operating Systems and User Accounts','topic'=>'Discuss Windows Linux and macOS issues and account permissions'),
  array('slug'=>'it-networking','title'=>'Networking Basics for IT Support','topic'=>'Discuss Wi-Fi, routers, connections, bandwidth and troubleshooting'),
  array('slug'=>'it-cloud','title'=>'Cloud Services and Storage','topic'=>'Explain cloud storage, access, syncing and backups to coworkers'),
  array('slug'=>'it-databases','title'=>'Databases and Data Requests','topic'=>'Discuss records, queries, tables and data quality with a colleague'),
  array('slug'=>'it-development','title'=>'Software Development Workflow','topic'=>'Explain coding tasks, versions, code reviews and releases'),
  array('slug'=>'it-version-control','title'=>'Git and Version Control Communication','topic'=>'Explain branches, commits, merge requests and review feedback'),
  array('slug'=>'it-agile','title'=>'Agile and Scrum in English','topic'=>'Discuss sprint planning, backlog priorities and retrospectives'),
  array('slug'=>'it-testing','title'=>'Software Testing and QA','topic'=>'Describe test cases, regression testing and acceptance criteria'),
  array('slug'=>'it-requirements','title'=>'Collecting Software Requirements','topic'=>'Ask clarifying questions and confirm user stories and features'),
  array('slug'=>'it-ui-ux','title'=>'UI and UX Feedback','topic'=>'Give constructive feedback on web and mobile interface usability'),
  array('slug'=>'it-web','title'=>'Web Development Client Discussions','topic'=>'Explain pages, responsive designs, accessibility and deployment'),
  array('slug'=>'it-mobile','title'=>'Mobile App Development','topic'=>'Discuss mobile features, app store releases and crashes'),
  array('slug'=>'it-api','title'=>'API Integration Communication','topic'=>'Explain endpoints, requests, responses and integration requirements'),
  array('slug'=>'it-devops','title'=>'DevOps and Deployment Updates','topic'=>'Discuss environments, deployment schedules and rollbacks safely'),
  array('slug'=>'it-monitoring','title'=>'System Monitoring and Alerts','topic'=>'Explain uptime alerts, performance metrics and escalation'),
  array('slug'=>'it-incidents','title'=>'Handling IT Incidents','topic'=>'Report service interruptions, incident impact and resolution updates'),
  array('slug'=>'it-privacy','title'=>'Data Privacy at Work','topic'=>'Explain personal data handling and workplace privacy responsibilities'),
  array('slug'=>'it-backups','title'=>'Backup and Disaster Recovery','topic'=>'Discuss backup schedules, recovery plans and restoration requests'),
  array('slug'=>'it-ai','title'=>'AI Tools in the IT Workplace','topic'=>'Discuss AI assistants, model limitations and responsible workplace usage'),
  array('slug'=>'it-data-analysis','title'=>'Data Analytics Meetings','topic'=>'Present simple dashboards, trends and data findings'),
  array('slug'=>'it-documentation','title'=>'Writing Technical Documentation','topic'=>'Write clear user instructions and release notes'),
  array('slug'=>'it-ticket-priority','title'=>'Ticket Priorities and SLAs','topic'=>'Set expectations about urgency, response times and service levels'),
  array('slug'=>'it-remote','title'=>'Remote Support Conversations','topic'=>'Guide a colleague through screen sharing and basic troubleshooting'),
  array('slug'=>'it-vendors','title'=>'Talking to IT Vendors','topic'=>'Compare offers, ask about contracts and negotiate service conditions'),
  array('slug'=>'it-budget','title'=>'IT Budget and Procurement','topic'=>'Explain quotes, software licenses and purchasing requests'),
  array('slug'=>'it-interview','title'=>'IT Job Interviews','topic'=>'Discuss IT work experience, projects and technical strengths'),
  array('slug'=>'it-presentation','title'=>'Presenting a Software Demo','topic'=>'Introduce features, demonstrate a product and answer questions'),
  array('slug'=>'it-client-complaints','title'=>'Responding to Client Complaints','topic'=>'Apologize, clarify issues and explain next steps professionally'),
  array('slug'=>'it-remote-team','title'=>'Cross-Cultural Remote Teamwork','topic'=>'Clarify time zones, handoffs and async communication'),
  array('slug'=>'it-accessibility','title'=>'Digital Accessibility Basics','topic'=>'Discuss accessible interfaces and user testing requirements'),
  array('slug'=>'it-ethics','title'=>'Professional Ethics in IT','topic'=>'Discuss responsible disclosure, confidentiality and professional boundaries'),
  array('slug'=>'it-automation','title'=>'Business Process Automation','topic'=>'Explain repetitive workflows, triggers and efficiency gains'),
  array('slug'=>'it-website-security','title'=>'Website Maintenance and Security','topic'=>'Discuss updates, access controls and maintenance plans'),
  array('slug'=>'it-seo','title'=>'Technical SEO for Web Teams','topic'=>'Explain crawling, indexing and page speed to non-technical stakeholders')
 );
}
function ewt_esp_admin_page() {
 add_submenu_page('edit.php?post_type=ewt_mesleki','IT B1 Ders Motoru','IT B1 Ders Motoru','edit_posts','ewt-esp-it','ewt_esp_render_admin');
}
add_action('admin_menu','ewt_esp_admin_page');
function ewt_esp_render_admin() {
 if(!current_user_can('edit_posts')) return;
 $lessons=ewt_esp_lessons();
 echo '<div class="wrap"><h1>Mesleki İngilizce · IT · B1 Ders Motoru</h1><p>Mevcut You.com bağlantısını kullanır. IT müfredatı, yalnızca <strong>taslak</strong> olarak kaydedilir. Her ders en fazla bir API çağrısı yapar. Günlük en fazla 5 çağrı; ücret veya bakiye sorgulaması yapılmaz.</p>';
 if(isset($_GET['ewt_esp_result'])) {
  $msg=get_transient('ewt_esp_notice_'.get_current_user_id());
  if($msg){delete_transient('ewt_esp_notice_'.get_current_user_id());echo '<div class="notice notice-info"><p>'.esc_html($msg).'</p></div>';}
 }
 $used=(int)get_option('ewt_esp_calls_'.wp_date('Ymd'),0);
 echo '<p><strong>Bugünkü API çağrıları:</strong> '.esc_html($used).' / 5 · <strong>Toplam katalog:</strong> '.count($lessons).' ders. Her çalıştırmada günlük kota dolana kadar eksik dersler üretilir.</p><ol>';
 foreach($lessons as $l) echo '<li>'.esc_html($l['title']).'</li>';
 echo '</ol>';
 echo '<p><strong>Mevcut 5 dersin testleri:</strong> Eski sürümde cevap verisi kaybolmuş olabilir. Aşağıdaki onarım, kelimeleri ve diyalogları değiştirmeden yeni test ve cevap anahtarı üretir (ders başına bir API çağrısı).</p>' . '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('ewt_esp_repair','ewt_esp_repair_nonce'); echo '<input type="hidden" name="action" value="ewt_esp_repair"><button class="button" type="submit">Eski Quizleri Onar (Günlük en fazla 5)</button></form>'; echo '<p><strong>Üretim içeriği:</strong> 6–8 mesleki kelime (IPA ve örnek), iki karakterli diyalog, 4 soruluk interaktif test. Öğretmen kontrolü gereklidir.</p>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
 wp_nonce_field('ewt_esp_generate','ewt_esp_nonce');
 echo '<input type="hidden" name="action" value="ewt_esp_generate"><label><input type="checkbox" name="confirmed" value="1" required> En fazla 5 You.com API çağrısı yapılmasını onaylıyorum; gerçek maliyetin sağlayıcıdan kaynaklanabileceğini biliyorum.</label><p><button class="button button-primary">Eksik IT B1 Taslaklarını Üret</button></p></form></div>';
}
function ewt_esp_normalize_json($raw) {
 $raw=trim((string)$raw);
 $data=json_decode($raw,true);
 if(!is_array($data) && preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i',$raw,$m)) $data=json_decode($m[1],true);
 if(!is_array($data) && preg_match('/\{[\s\S]*\}/',$raw,$m)) $data=json_decode($m[0],true);
 if(!is_array($data)) return new WP_Error('esp_json','API geçerli JSON döndürmedi. İçerik kaydedilmedi.');
 if(empty($data['vocabulary']) || !is_array($data['vocabulary']) || count($data['vocabulary']) < 6 || empty($data['dialogue']) || !is_array($data['dialogue']) || count($data['dialogue']) < 4 || empty($data['quiz']) || !is_array($data['quiz']) || count($data['quiz']) < 4) return new WP_Error('esp_shape','API eksik ders şeması döndürdü. İçerik kaydedilmedi.');
 foreach(array_slice($data['quiz'],0,4) as $q) if(!isset($q['question'],$q['options'],$q['correct']) || !is_array($q['options']) || count($q['options'])!==3 || !in_array((int)$q['correct'],array(0,1,2),true)) return new WP_Error('esp_quiz','Test cevap anahtarı geçersiz.');
 return $data;
}
function ewt_esp_render_lesson($data) {
 $html='<div class="ewt-esp-lesson"><p><strong>Level: B1 · Sector: IT</strong></p><h2>Vocabulary &amp; Pronunciation</h2><div class="ewt-esp-vocab">';
 foreach(array_slice($data['vocabulary'],0,8) as $v){
  if(!is_array($v))continue;
  $word=sanitize_text_field($v['word']??'');
  $html.='<article class="ewt-esp-word"><h3>'.esc_html($word).' <button type="button" class="ewt-esp-say" data-word="'.esc_attr($word).'" aria-label="Listen to '.esc_attr($word).'">🔊</button></h3><p>'.esc_html($v['ipa']??'').'</p><p>'.esc_html($v['meaning_tr']??'').'</p><p><em>'.esc_html($v['example']??'').'</em></p></article>';
 }
 $html.='</div><h2>Dialogue: Workplace Scenario</h2>';
 foreach(array_slice($data['dialogue'],0,12) as $line){if(!is_array($line))continue;$html.='<p><strong>'.esc_html($line['speaker']??'').':</strong> '.esc_html($line['line']??'').'</p>';}
 $html.='<h2>Interactive Quiz</h2><div class="ewt-esp-quiz">';
 foreach(array_slice($data['quiz'],0,4) as $i=>$q){
  $html.='<fieldset class="ewt-esp-question" data-answer="'.esc_attr((int)$q['correct']).'"><legend>'.esc_html(($i+1).'. '.($q['question']??'')).'</legend>';
  foreach($q['options'] as $j=>$opt){$html.='<label style="display:block;margin:.5em 0"><input type="radio" name="ewt_esp_q'.esc_attr($i).'" value="'.esc_attr($j).'"> '.esc_html($opt).'</label>';}
  $html.='</fieldset>';
 }
 $html.='<button type="button" class="ewt-esp-check">Check answers</button><p class="ewt-esp-result" role="status" aria-live="polite"></p></div></div>';
 return $html; // All AI strings were escaped individually; do not strip controlled radio inputs / answer attributes.
}
add_shortcode('ewt_esp_lesson',function($atts){
 $id=get_the_ID(); if(!$id || get_post_type($id)!=='ewt_mesleki')return '';
 $html=get_post_meta($id,'_ewt_esp_html',true);
 if(!is_string($html)) return '';
 $quiz=get_post_meta($id,'_ewt_esp_quiz',true);
 if(is_array($quiz) && count($quiz)>=4){
  $parts=explode('<h2>Interactive Quiz</h2>',$html,2);
  $html=$parts[0];
  $html.=ewt_esp_quiz_markup($quiz);
  $html.='</div>';
 }
 return $html;
});
function ewt_esp_existing($slug) {
 $ids=get_posts(array('post_type'=>'ewt_mesleki','post_status'=>array('draft','pending','publish','private'),'numberposts'=>1,'fields'=>'ids','meta_key'=>'_ewt_esp_lesson','meta_value'=>$slug,'suppress_filters'=>true));
 return !empty($ids);
}
add_action('admin_post_ewt_esp_generate',function(){
 if(!current_user_can('edit_posts'))wp_die('Yetkiniz yok.');
 check_admin_referer('ewt_esp_generate','ewt_esp_nonce');
 if(empty($_POST['confirmed']))wp_die('API çağrısı onayı gerekli.');
 if(get_transient('ewt_esp_lock'))wp_die('Üretim zaten sürüyor.');
 set_transient('ewt_esp_lock',1,8*MINUTE_IN_SECONDS);
 $day='ewt_esp_calls_'.wp_date('Ymd');$used=(int)get_option($day,0);$created=0;$errors=array();
 $sector=term_exists('bilisim-it','ewt_mesleki_sektor');
 if(!$sector)$sector=wp_insert_term('Bilişim / IT','ewt_mesleki_sektor',array('slug'=>'bilisim-it'));
 $sector_id=is_wp_error($sector)?0:(int)(is_array($sector)?$sector['term_id']:$sector);
 foreach(ewt_esp_lessons() as $lesson){
  if(ewt_esp_existing($lesson['slug']))continue;
  if($used>=5){$errors[]='Günlük 5 çağrı sınırına ulaşıldı.';break;}
  // Count before invocation, including failures: enforce hard maximum request count.
  $used++;update_option($day,$used,false);
  $system='You are a careful B1 English for Specific Purposes teacher. Generate accurate, age-neutral educational material for Turkish-speaking adult IT professionals. No markdown, no code fences; output only a JSON object.';
  $prompt='Lesson: '.$lesson['title'].'. Scenario: '.$lesson['topic'].'. CEFR B1. Return JSON keys: vocabulary (array of 6 to 8 objects {word,ipa,meaning_tr,example}), dialogue (array of 6 to 10 objects {speaker,line}), quiz (array of exactly 4 objects {question,options:[three English options],correct:zero_based_integer}). Keep language natural and level appropriate, no personal data. Quiz must have one clear correct answer per question. IPA should be conventional and approximate where required. Dialogue speakers should be realistic. Total output under 3200 characters.';
  $raw=ewt_call_youcom_api($system,$prompt,true);
  if(is_wp_error($raw)){$errors[]=$lesson['title'].': '.$raw->get_error_message();continue;}
  $data=ewt_esp_normalize_json($raw);
  if(is_wp_error($data)){$errors[]=$lesson['title'].': '.$data->get_error_message();continue;}
  $post_id=wp_insert_post(array('post_type'=>'ewt_mesleki','post_status'=>'draft','post_title'=>$lesson['title'].' (B1)','post_name'=>$lesson['slug'].'-b1','post_content'=>'[ewt_esp_lesson]','post_excerpt'=>'B1 IT English: vocabulary, pronunciation, workplace dialogue and interactive quiz.'),true);
  if(is_wp_error($post_id)){$errors[]=$lesson['title'].': kaydetme hatası';continue;}
  update_post_meta($post_id,'_ewt_esp_html',ewt_esp_render_lesson($data));update_post_meta($post_id,'_ewt_esp_quiz',array_slice($data['quiz'],0,4));update_post_meta($post_id,'_ewt_esp_lesson',$lesson['slug']);update_post_meta($post_id,'_ewt_esp_level','b1');update_post_meta($post_id,'_ewt_esp_source','youcom');
  if($sector_id)wp_set_object_terms($post_id,array($sector_id),'ewt_mesleki_sektor');
  // Existing level taxonomy means subject category: do not assign B1 incorrectly.
  if(taxonomy_exists('ewt_mesleki_seviye')){$term=get_term_by('slug','bilgi-teknolojileri-ingilizcesi','ewt_mesleki_seviye');if($term)wp_set_object_terms($post_id,array((int)$term->term_id),'ewt_mesleki_seviye');}
  $created++;
 }
 delete_transient('ewt_esp_lock');
 $message=$created.' yeni taslak kaydedildi. Bugünkü API çağrısı: '.$used.'/5. '.implode(' | ',array_slice($errors,0,5));
 set_transient('ewt_esp_notice_'.get_current_user_id(),$message,MINUTE_IN_SECONDS*5);
 wp_safe_redirect(add_query_arg(array('post_type'=>'ewt_mesleki','page'=>'ewt-esp-it','ewt_esp_result'=>'1'),admin_url('edit.php')));exit;
});
add_action('wp_enqueue_scripts',function(){
 if(!is_singular('ewt_mesleki'))return;
 wp_enqueue_script('ewt-esp-interactions',get_template_directory_uri().'/assets/js/esp-interactions.js',array(),EWT_VERSION,true);
 wp_enqueue_style('ewt-esp-interactions',get_template_directory_uri().'/assets/css/esp-interactions.css',array(),EWT_VERSION);
});

/** Render safe, interactive markup from already validated quiz data. */
function ewt_esp_quiz_markup($questions) {
 $html='<h2>Interactive Quiz</h2><div class="ewt-esp-quiz">';
 foreach(array_slice($questions,0,4) as $i=>$q) {
  if(!is_array($q) || !isset($q['question'],$q['correct'],$q['options']) || !is_array($q['options'])) continue;
  $html.='<fieldset class="ewt-esp-question" data-answer="'.esc_attr((int)$q['correct']).'"><legend>'.esc_html(($i+1).'. '.$q['question']).'</legend>';
  foreach(array_slice($q['options'],0,3) as $j=>$opt) {
   $html.='<label class="ewt-esp-choice"><input type="radio" name="ewt_esp_'.esc_attr(get_the_ID()).'_'.esc_attr($i).'" value="'.esc_attr($j).'"> <span>'.esc_html($opt).'</span></label>';
  }
  $html.='<p class="ewt-esp-feedback" aria-live="polite"></p></fieldset>';
 }
 return $html.'<button type="button" class="ewt-esp-check">Check answers</button><button type="button" class="ewt-esp-reset">Try again</button><p class="ewt-esp-result" role="status" aria-live="polite"></p></div>';
}

/** Repair only historic quiz data, preserving existing published/draft lesson text. */
add_action('admin_post_ewt_esp_repair',function(){
 if(!current_user_can('edit_posts')) wp_die('Yetkiniz yok.');
 check_admin_referer('ewt_esp_repair','ewt_esp_repair_nonce');
 if(get_transient('ewt_esp_lock')) wp_die('Başka bir üretim işlemi sürüyor.');
 set_transient('ewt_esp_lock',1,8*MINUTE_IN_SECONDS);
 $day='ewt_esp_calls_'.wp_date('Ymd'); $used=(int)get_option($day,0); $repaired=0; $errors=array();
 $ids=get_posts(array('post_type'=>'ewt_mesleki','post_status'=>array('publish','draft','pending','private'),'numberposts'=>100,'fields'=>'ids','meta_key'=>'_ewt_esp_lesson','suppress_filters'=>true));
 foreach($ids as $id){
  if(is_array(get_post_meta($id,'_ewt_esp_quiz',true)) && count(get_post_meta($id,'_ewt_esp_quiz',true))>=4) continue;
  if($used>=5){$errors[]='Günlük çağrı sınırına ulaşıldı.';break;}
  $html=get_post_meta($id,'_ewt_esp_html',true);
  if(!is_string($html) || strpos($html,'<h2>Interactive Quiz</h2>')===false)continue;
  $used++;update_option($day,$used,false);
  $system='You are an expert B1 English for IT teacher. Output strictly valid JSON, no markdown.';
  $prompt='Create 4 unambiguous multiple choice B1 IT English comprehension/terminology questions on this topic: '.get_the_title($id).'. Return only a JSON object {"quiz":[{"question":"...","options":["...","...","..."],"correct":0}]}. Correct is zero-based 0,1,2. Use exactly 4 questions. No sensitive technical instructions.';
  $raw=ewt_call_youcom_api($system,$prompt,true);
  if(is_wp_error($raw)){$errors[]=get_the_title($id).': API hatası';continue;}
  $json=json_decode(trim((string)$raw),true);
  if(!is_array($json) && preg_match('/\{[\s\S]*\}/',(string)$raw,$match)) $json=json_decode($match[0],true);
  $quiz=is_array($json)?($json['quiz']??null):null;
  if(!is_array($quiz)||count($quiz)!==4){$errors[]=get_the_title($id).': eksik test JSON';continue;}
  $valid=true;
  foreach($quiz as $q){if(!is_array($q)||!isset($q['question'],$q['options'],$q['correct'])||!is_array($q['options'])||count($q['options'])!==3||!in_array((int)$q['correct'],array(0,1,2),true)){$valid=false;break;}}
  if(!$valid){$errors[]=get_the_title($id).': cevap anahtarı geçersiz';continue;}
  update_post_meta($id,'_ewt_esp_quiz',array_map(function($q){return array('question'=>sanitize_text_field($q['question']),'options'=>array_map('sanitize_text_field',$q['options']),'correct'=>(int)$q['correct']);},$quiz));
  $repaired++;
 }
 delete_transient('ewt_esp_lock');
 set_transient('ewt_esp_notice_'.get_current_user_id(),$repaired.' dersin testi onarıldı. Bugünkü toplam API çağrısı: '.$used.'/5. '.implode(' | ',array_slice($errors,0,4)),5*MINUTE_IN_SECONDS);
 wp_safe_redirect(add_query_arg(array('post_type'=>'ewt_mesleki','page'=>'ewt-esp-it','ewt_esp_result'=>'1'),admin_url('edit.php')));exit;
});
