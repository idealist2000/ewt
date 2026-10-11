<?php if (!defined('ABSPATH')) exit; ?>
</main>
<?php $campus=ewt_campus_options(); if($campus['contact_enabled']==='1'): ?>
<div class="ewt-footer-cta-wrap"><div class="ewt-container ewt-footer-cta"><div><h2><?php echo esc_html($campus['cta']); ?></h2><p>Seviyene uygun çalışma planı ve birebir İngilizce desteği.</p></div><?php echo ewt_campus_contacts(); ?></div></div>
<?php endif; ?>
<footer id="ewt-site-footer" class="ewt-site-footer"><div class="ewt-footer-top">
<div class="ewt-footer-col ewt-footer-brand"><?php ewt_campus_logo(); ?><p>2005’ten beri İngilizce öğrenenlerin buluşma noktası. Okul derslerinden uluslararası sınavlara, her adımında yanında.</p><?php echo ewt_campus_contacts(); ?></div>
<div class="ewt-footer-col"><h3>Okul İngilizcesi</h3><ul><?php foreach(array('ewt_primary'=>'İlkokul','ewt_secondary'=>'Ortaokul','ewt_highschool'=>'Lise') as $pt=>$label): ?><li><a href="<?php echo esc_url(get_post_type_archive_link($pt)); ?>"><?php echo esc_html($label); ?></a></li><?php endforeach; ?><li><a href="<?php echo esc_url(home_url('/#ewt-k12')); ?>">1–12. sınıf konu haritası</a></li></ul><?php dynamic_sidebar('footer-1'); ?></div>
<div class="ewt-footer-col"><h3>Her gün bir adım</h3><ul><?php foreach(array('ewt_vocabulary'=>'Vocabulary','ewt_grammar'=>'Grammar','ewt_listening'=>'Listening','ewt_speaking'=>'Speaking') as $pt=>$label): ?><li><a href="<?php echo esc_url(get_post_type_archive_link($pt)); ?>"><?php echo esc_html($label); ?></a></li><?php endforeach; ?></ul><?php dynamic_sidebar('footer-2'); ?></div>
<div class="ewt-footer-col"><h3>Hedefini büyüt</h3><ul><?php foreach(array('ewt_ielts'=>'IELTS','ewt_toefl'=>'TOEFL','ewt_yds'=>'YDS','ewt_yokdil'=>'YÖKDİL') as $pt=>$label): ?><li><a href="<?php echo esc_url(get_post_type_archive_link($pt)); ?>"><?php echo esc_html($label); ?></a></li><?php endforeach; ?></ul><?php if(has_nav_menu('footer'))wp_nav_menu(array('theme_location'=>'footer','container'=>false,'menu_class'=>'ewt-footer-menu'));dynamic_sidebar('footer-3'); ?></div>
</div><div class="ewt-footer-bottom"><p>&copy; <?php echo esc_html(wp_date('Y')); ?> English Web TR · Öğrenmenin mevsimi yok.</p><?php the_privacy_policy_link(); ?><p class="ewt-footer-signature">Mimoza Bilişim AI Otomasyon Ajansı Ürünü</p></div></footer>
<?php get_template_part('template-parts/site-search-overlay');wp_footer(); ?></body></html>
