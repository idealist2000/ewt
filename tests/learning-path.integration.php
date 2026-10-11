<?php
/** Run with the active 1.14 theme: wp eval-file /path/to/this-file. */
function path_check( $ok, $message ) { if ( ! $ok ) throw new Exception( $message ); echo "PASS: {$message}\n"; }
$GLOBALS['ewt_path_test_created'] = array(); $created_terms = array();
function path_fixture( $type, $level, $audience, $goal, $target = '', $status = 'publish', $password = '' ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => $status, 'post_title' => 'Learning path integration fixture', 'post_content' => 'Fixture', 'post_password' => $password ), true );
	if ( is_wp_error( $id ) ) throw new Exception( $id->get_error_message() );
	$GLOBALS['ewt_path_test_created'][] = $id;
	foreach ( array( 'level' => $level, 'audience' => $audience, 'goal' => $goal, 'target' => $target ) as $key => $value ) if ( $value ) update_post_meta( $id, '_ewt_path_' . $key, $value );
	return $id;
}
try {
	$p = ewt_path_preferences( array( 'ewt_level' => array('c2'), 'ewt_goal' => 'bad', 'ewt_target' => 'toefl-100p', 'ewt_audience' => '<script>', 'ewt_order' => 'bad' ) );
	path_check( $p === array( 'level' => 'a1', 'audience' => 'adult', 'goal' => 'general', 'target' => '', 'order' => 'relevant' ), 'untrusted preferences normalize to safe defaults' );
	$p = ewt_path_preferences( array( 'ewt_level' => 'b2', 'ewt_goal' => 'toefl', 'ewt_target' => 'toefl-100p', 'ewt_audience' => 'postgraduate' ) );
	$exact = path_fixture('ewt_toefl','b2','postgraduate','toefl','toefl-100p');
	$no_target = path_fixture('ewt_toefl','b2','postgraduate','toefl');
	$easy = path_fixture('ewt_toefl','a1','postgraduate','toefl');
	$unknown = path_fixture('ewt_toefl','','postgraduate','toefl');
	$other = path_fixture('ewt_grammar','b2','adult','general');
	$draft = path_fixture('ewt_toefl','b2','postgraduate','toefl','toefl-100p','draft');
	$private = path_fixture('ewt_toefl','b2','postgraduate','toefl','toefl-100p','private');
	$locked = path_fixture('ewt_toefl','b2','postgraduate','toefl','toefl-100p','publish','fixture-password');
	$term = wp_insert_term('B2 integration fixture','ewt_grammar_seviye',array('slug'=>'b2-integration-fixture'));
	if (is_wp_error($term)) throw new Exception($term->get_error_message());
	$created_terms[] = array($term['term_id'],'ewt_grammar_seviye');
	$legacy = path_fixture('ewt_grammar','','','',''); wp_set_object_terms($legacy,array((int)$term['term_id']),'ewt_grammar_seviye');

	$grade_term = wp_insert_term('5. Sınıf integration fixture','ewt_secondary_seviye',array('slug'=>'5-sinif-integration-fixture'));
	if (is_wp_error($grade_term)) throw new Exception($grade_term->get_error_message());
	$created_terms[] = array($grade_term['term_id'],'ewt_secondary_seviye');
	$grade5 = path_fixture('ewt_secondary','b2','','general'); wp_set_object_terms($grade5,array((int)$grade_term['term_id']),'ewt_secondary_seviye');
	$grade12 = path_fixture('ewt_highschool','b2','','general');
	$target_term = term_exists('toefl-100p','ewt_toefl_seviye');
	if (!$target_term) {
	 $target_term = wp_insert_term('TOEFL 100+ fixture','ewt_toefl_seviye',array('slug'=>'toefl-100p'));
	 $created_terms[] = array($target_term['term_id'],'ewt_toefl_seviye');
	}
	$legacy_target = path_fixture('ewt_toefl','b2','postgraduate','',''); wp_set_object_terms($legacy_target,array((int)$target_term['term_id']),'ewt_toefl_seviye');
	$q = ewt_path_query($p); $ids = wp_list_pluck($q->posts,'ID');
	path_check( array_search($exact,$ids,true) < array_search($no_target,$ids,true), 'target improves relevant ranking' );
	path_check( array_search($no_target,$ids,true) < array_search($other,$ids,true), 'goal and audience improve relevant ranking' );
	path_check( ! array_intersect(array($draft,$private,$locked),$ids), 'draft, private and password protected posts are excluded' );

	path_check(array_search($legacy_target,$ids,true) < array_search($no_target,$ids,true), 'legacy exam target tags improve ranking');
	$school = ewt_path_preferences(array('ewt_level'=>'b2','ewt_audience'=>'grade-5','ewt_goal'=>'general'));
	$school_ids=wp_list_pluck(ewt_path_query($school)->posts,'ID');
	path_check(array_search($grade5,$school_ids,true) < array_search($grade12,$school_ids,true), 'legacy class tags favor the selected grade');
	$p['order']='easy'; $q=ewt_path_query($p); $ids=wp_list_pluck($q->posts,'ID');
	path_check( array_search($easy,$ids,true) < array_search($exact,$ids,true) && array_search($unknown,$ids,true) > array_search($exact,$ids,true), 'easy sort orders known levels before unknowns' );
	path_check(ewt_path_post_level($legacy)==='B2','legacy CEFR taxonomy provides a visible level');
	for($i=0;$i<15;$i++) path_fixture('ewt_toefl','b2','postgraduate','toefl','toefl-100p');
	$p['order']='relevant'; $first=ewt_path_query($p,1); $second=ewt_path_query($p,2);
	path_check(count($first->posts)===12 && count($second->posts)>0 && !array_intersect(wp_list_pluck($first->posts,'ID'),wp_list_pluck($second->posts,'ID')), 'SQL pagination returns distinct stable pages');
	$normal = new WP_Query(array('post_type'=>'ewt_toefl','post_status'=>'publish','orderby'=>'ID','order'=>'DESC','posts_per_page'=>1));
	path_check( strpos($normal->request,'_ewt_path_')===false,'ranking filter does not affect unrelated queries');
	$admin = get_user_by('login','ewt_local_admin'); wp_set_current_user($admin->ID);
	$_POST = array('ewt_path_nonce'=>wp_create_nonce('ewt_path_meta'),'ewt_path_level'=>'c1','ewt_path_goal'=>'ielts','ewt_path_target'=>'toefl-100p','ewt_path_audience'=>'graduate');
	do_action('save_post',$other,get_post($other),true);
	path_check(get_post_meta($other,'_ewt_path_level',true)==='c1' && get_post_meta($other,'_ewt_path_target',true)==='', 'editor saves valid fields and rejects target from another exam');
	$_POST['ewt_path_nonce']='invalid'; $_POST['ewt_path_level']='c2'; do_action('save_post',$other,get_post($other),true);
	path_check(get_post_meta($other,'_ewt_path_level',true)==='c1','invalid nonce cannot change classifications');
} finally {
	$_POST=array();
	foreach($GLOBALS['ewt_path_test_created'] as $id) wp_delete_post($id,true);
	foreach($created_terms as $item) wp_delete_term($item[0],$item[1]);
}
