<?php
function ebook_check($ok,$message) { if (!$ok) throw new Exception($message); echo "PASS: $message\n"; }
$ids=array();
try {
 for($i=0;$i<35;$i++) {
  $id=wp_insert_post(array('post_type'=>'ewt_toefl','post_status'=>'publish','post_title'=>'PDF link fixture '.$i,'post_content'=>''),true);
  if(is_wp_error($id)) throw new Exception($id->get_error_message());
  $ids[]=$id;
  foreach(array('level'=>'b2','audience'=>'postgraduate','goal'=>'toefl','target'=>'toefl-100p') as $key=>$value) update_post_meta($id,'_ewt_path_'.$key,$value);
 }
 $input=array('ewt_level'=>'b2','ewt_audience'=>'postgraduate','ewt_goal'=>'toefl','ewt_target'=>'toefl-100p','ewt_order'=>'relevant','ewt_page'=>2);
 $book=ewt_ebook_collect_path($input);
 ebook_check(!is_wp_error($book) && count($book['chapters'])===min(35,ewt_ebook_max_posts()),'export includes the first configured maximum across recommendation pages');
 ebook_check(wp_list_pluck($book['chapters'],'id')===array_slice($ids,0,ewt_ebook_max_posts()),'export preserves recommendation order independently of the current page');
 ebook_check($book['preferences']['audience']==='postgraduate' && $book['preferences']['target']==='toefl-100p','PDF retains education group and exam target');
 ebook_check(!array_filter(wp_list_pluck($book['chapters'],'html')),'link mode never copies lesson bodies');
 ebook_check(is_wp_error(ewt_ebook_collect_posts(array($ids[0]))),'existing full-content mode still skips empty lesson bodies');
 $print=array_merge($book,array('mode'=>'links','site'=>'English Web TR','site_url'=>home_url('/'),'created'=>current_time('mysql')));
 $html=ewt_ebook_render_print_html($print);
 ebook_check(substr_count($html,'class="ewt-link-title"')===count($book['chapters']) && strpos($html,'href="'.esc_url(get_permalink($ids[0])).'"')!==false,'print renderer includes clickable lesson links');
 $print['chapters'][0]['title']='<script>alert(1)</script>';
 ebook_check(strpos(ewt_ebook_render_print_html($print),'<script>alert(1)</script>')===false,'link titles are escaped');
 wp_update_post(array('ID'=>$ids[0],'post_status'=>'private'));
 $recheck=ewt_ebook_collect_posts(wp_list_pluck($book['chapters'],'id'),true);
 ebook_check(!in_array($ids[0],wp_list_pluck($recheck,'id'),true),'revalidation removes lessons made private after creation');
 $legacy=ewt_ebook_render_print_html(array('title'=>'Legacy PDF','chapters'=>array(array('id'=>1,'title'=>'Legacy','html'=>'<p>LEGACY_BODY</p>','permalink'=>home_url('/'),'levels'=>array(),'type_label'=>'Blog')),'created'=>current_time('mysql'),'site'=>'Test','site_url'=>home_url('/')));
 ebook_check(strpos($legacy,'LEGACY_BODY')!==false,'existing full-content PDF renderer remains available');
} finally { foreach($ids as $id) wp_delete_post($id,true); }
