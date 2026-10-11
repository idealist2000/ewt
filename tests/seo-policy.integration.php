<?php
function seo_check($ok,$message){if(!$ok)throw new Exception($message);echo "PASS: $message\n";}
$ids=array();$attachments=array();
try {
 seo_check(ewt_seo_case('ingilizce ışık IELTS YÖKDİL a1')==='İngilizce Işık IELTS YÖKDİL A1','Turkish title case preserves dotless I and exam acronyms');
 $content='<h2>Giriş</h2><p>Present perfect kullanımını öğren.</p><h2>Örnekler</h2><p>I have finished my work.</p>';
 $title=ewt_seo_build_title('present perfect','present perfect',$content);
 seo_check($title==='Present Perfect - 2 Bölüm','title uses the actual number of H2 sections');
 seo_check(ewt_seo_build_title('present perfect',$title,$content)===$title,'title normalization is repeatable');
 seo_check(ewt_seo_build_title('a1 ingilizce','', '<p>Temel ders</p>','a1')==='A1 İngilizce - Kolay Anlatım','easy level permits a positive sentiment phrase');
 seo_check(ewt_seo_build_title('ingilizce hatalar','', '<p>Sık yapılan hatalar.</p>')==='İngilizce Hatalar - Yaygın Hatalar','error content permits a negative sentiment phrase');
 seo_check(ewt_seo_build_title('academic reading','', '<p>Academic texts.</p>')==='Academic Reading - Rehber','neutral content falls back to a power word');
 $long='ileri ingilizce kelimeler ve okuma becerileri';$bounded=ewt_seo_build_title($long,$long.' detaylı örnekler ile öğrenme',$content);
 seo_check(mb_strlen($bounded)<=55 && mb_strpos(ewt_seo_lower($bounded),ewt_seo_lower($long))===0,'long titles keep the complete focus prefix within 55 characters');
 seo_check(is_wp_error(ewt_seo_build_title(str_repeat('ö',56),'',$content)),'impossible full-focus titles are rejected without truncating the keyword');
 $density=ewt_seo_density('<p>'.str_repeat('kelime ',98).'focus keyword</p>','focus keyword');
 seo_check($density['words']===100 && $density['occurrences']===1 && $density['percent']===1.0,'phrase density uses occurrences divided by visible word count');
 seo_check(ewt_seo_density('<p>bir iki</p><p>üç dört</p>','bir')['words']===4,'adjacent blocks retain separate word boundaries');
 $raw='<!-- wp:paragraph --><p>Giriş metni <a href="https://example.org/test" rel="nofollow">Kaynak</a> <a href="'.home_url('/').'">Site</a><a href="mailto:a@example.test">Mail</a></p><!-- /wp:paragraph --><img src="/a.png" alt="Öğrenci"><img src="/b.png" alt="" role="presentation">';
 $prepared=ewt_seo_prepare_content($raw,'present perfect');
 seo_check(strpos($prepared,'Öğrenci — present perfect')!==false && strpos($prepared,'role="presentation"')!==false,'descriptive image alts include the focus keyword');
 seo_check(substr_count($prepared,'target="_blank"')===7 && strpos($prepared,'nofollow noopener noreferrer')!==false,'existing external links and six learning sources open safely in new tabs');
 seo_check(strpos($prepared,'<!-- wp:paragraph -->')!==false && strpos($prepared,'<!-- wp:html -->')!==false,'Gutenberg comments are retained and resource markup uses an HTML block');
 seo_check(ewt_seo_prepare_content($prepared,'present perfect')===$prepared,'resource and alt updates are idempotent');
 foreach(array('wikipedia.org','medium.com','britishcouncil.org','bbc.co.uk','purdue.edu','state.gov') as $host)seo_check(strpos($prepared,$host)!==false,'resource domain included: '.$host);
 seo_check(strpos(ewt_content_user_prompt('Konu','ewt_grammar','b2',500,'present perfect'),'%1,9')!==false,'AI prompts carry the density target with the real focus keyword');
 $id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_title'=>'SEO policy integration fixture','post_content'=>$content),true);$ids[]=$id;
 $attachment=wp_insert_attachment(array('post_title'=>'Test image','post_mime_type'=>'image/png','post_status'=>'inherit','post_parent'=>$id));$attachments[]=$attachment;
 update_post_meta($attachment,'_wp_attachment_image_alt','English student');
 update_post_meta($id,'rank_math_focus_keyword','present perfect,secondary keyword');
 ewt_apply_seo_fields($id,'present perfect','present perfect','Present perfect ders açıklaması',false);
 seo_check(get_post_meta($id,'rank_math_title',true)===$title && get_post_meta($id,'_ewt_seo_title',true)===$title,'Rank Math and theme receive the same normalized title');
 seo_check(get_post_meta($id,'rank_math_focus_keyword',true)==='present perfect,secondary keyword','secondary Rank Math keywords are preserved');
 seo_check(strpos(get_post_meta($attachment,'_wp_attachment_image_alt',true),'present perfect')!==false,'owned attachment alt metadata is updated');
 $body=get_post_field('post_content',$id);
 seo_check(substr_count($body,'data-ewt-seo-resources')===1 && strpos($body,'I have finished my work.')!==false,'saved content receives one resources block without rewriting lesson prose');
 ewt_apply_seo_fields($id,str_repeat('ö',56),'invalid','',false);
 seo_check(get_post_meta($id,'rank_math_title',true)===$title && get_post_meta($id,'_ewt_seo_warning',true)!=='','oversized keywords preserve existing SEO data and report the blocker');
 $context=ewt_pexels_search_context('Present perfect İngilizce gramer');
 seo_check(strpos($context['query'],'english grammar lesson')!==false,'Pexels query stays within specific English teaching context');
 $photos=array(array('id'=>1,'alt'=>'Beautiful English countryside'),array('id'=>2,'alt'=>'English grammar teacher in classroom with workbook'));
 seo_check(ewt_pexels_pick_photo($photos,$context)['id']===2,'irrelevant country pictures lose to matching education photos');
 seo_check(ewt_pexels_pick_photo(array($photos[0]),$context)===null,'unrelated stock images are skipped');
 $child=ewt_pexels_search_context('elma',true);
 seo_check($child['child'] && strpos($child['query'],'apple')!==false,'existing child flashcard object mode remains supported');
 $key_filter=function(){return 'mock-test-only';};add_filter('pre_option_ewt_pexels_api_key',$key_filter);
 $mock=function($pre,$args,$url){
  if(strpos($url,'https://api.pexels.com/v1/search')===0){parse_str(wp_parse_url($url,PHP_URL_QUERY),$query);if(strpos($query['query'],'english grammar lesson')!==0)throw new Exception('unexpected Pexels query');return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode(array('photos'=>array(array('alt'=>'English grammar teacher in classroom with workbook','src'=>array('large'=>'https://images.pexels.com/test.png'),'photographer'=>'Fixture')))));}
  if($url==='https://images.pexels.com/test.png')return array('response'=>array('code'=>200),'headers'=>array(),'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3foAAAAASUVORK5CYII='));
  return $pre;
 };add_filter('pre_http_request',$mock,10,3);
 try{$result=ewt_fetch_pexels_image('Present perfect grammar');seo_check(!is_wp_error($result)&&!empty($result['data']),'mocked Pexels pipeline validates matching metadata and image bytes');}
 finally{remove_filter('pre_http_request',$mock,10);remove_filter('pre_option_ewt_pexels_api_key',$key_filter);}
 $admin=get_user_by('login','ewt_local_admin');wp_set_current_user($admin->ID);
 // This local fixture has the highest current ID; the cursor avoids applying to other posts.
 ewt_apply_seo_fields($id,'present perfect','present perfect','',false);
 $batch=ewt_seo_maintenance_batch($id-1,true);
 seo_check(count($batch['rows'])===1 && $batch['rows'][0]['status']==='updated','existing-content batch applies the shared SEO policy');
}finally{foreach($attachments as $image_id)wp_delete_attachment($image_id,true);foreach($ids as $id)wp_delete_post($id,true);}
