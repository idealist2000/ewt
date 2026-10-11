<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
	?>
	<article class="ewt-page ewt-container">
		<h1 class="ewt-single-title"><?php the_title(); ?></h1>
		<div class="ewt-single-content"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;
get_footer();
