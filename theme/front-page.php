<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$cpts = ewt_cpt_config();
?>
<section class="ewt-hero">
	<div class="ewt-hero-blob ewt-hero-blob-1" aria-hidden="true"></div>
	<div class="ewt-hero-blob ewt-hero-blob-2" aria-hidden="true"></div>
	<div class="ewt-container ewt-hero-inner">
		<span class="ewt-hero-eyebrow">🎓 2005'ten beri Türk öğrencilerin yanında</span>
		<h1>Seviyene ve hedefine uygun İngilizce öğrenme yolculuğun burada başlıyor</h1>
		<p>İlkokul öncesinden GMAT'a, dört dil becerisinden grammar &amp; vocabulary'e — A1'den C2'ye kadar her seviyede özgün içerik.</p>
		<div class="ewt-hero-actions">
			<a class="ewt-cta-button" href="tel:<?php echo esc_attr( get_option( 'ewt_tutoring_phone', '+90 530 571 40 67' ) ); ?>">📞 Özel Ders Al</a>
			<a class="ewt-cta-button ewt-cta-outline" href="#ewt-lab-home">🎧 Ücretsiz Dinleme Pratiği Yap</a>
			<a class="ewt-cta-button ewt-cta-outline" href="#ewt-cpt-grid">Kademeleri Keşfet ↓</a>
		</div>
	</div>
</section>

<?php ewt_placement_render(); ?>

<!-- 21. YIL KUTLAMA BÖLÜMÜ BAŞLANGICI -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<style>
	.ewt-anniversary-wrap {
		margin: 40px auto;
		padding: 0 20px;
	}
	#kutlama-alani {
		font-family: inherit;
		background: linear-gradient(135deg, #dfa928 0%, #c48b17 100%); /* Güz Sarısı Tonları */
		color: #ffffff;
		padding: 30px;
		border-radius: 15px;
		box-shadow: 0 10px 30px rgba(223, 169, 40, 0.2);
		text-align: center;
		max-width: 700px;
		margin: 0 auto;
		border: 2px solid #b8860b;
		position: relative;
		overflow: hidden;
	}
	#kutlama-alani h3 {
		margin-top: 0;
		color: #fff;
		font-size: 1.5rem;
		margin-bottom: 20px;
	}
	#geri-sayim {
		display: flex;
		justify-content: center;
		gap: 15px;
		flex-wrap: wrap;
	}
	.zaman-kutusu {
		background: rgba(0, 0, 0, 0.15);
		padding: 15px 20px;
		border-radius: 10px;
		min-width: 80px;
		box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
	}
	.zaman-kutusu span {
		font-size: 2.5em;
		font-weight: 800;
		display: block;
		line-height: 1;
	}
	.etiket {
		font-size: 0.85em;
		text-transform: uppercase;
		letter-spacing: 1px;
		margin-top: 8px;
		opacity: 0.9;
	}
	#yildonumu-mesaji {
		display: none;
		font-size: 2.5em;
		font-weight: bold;
		cursor: pointer;
		padding: 20px 10px;
		text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
		transition: transform 0.3s ease;
	}
	#yildonumu-mesaji:hover {
		transform: scale(1.05);
	}
</style>

<section class="ewt-anniversary-wrap ewt-container">
	<div id="kutlama-alani">
		<!-- Geri Sayım Görünümü -->
		<div id="geri-sayim-alani">
			<h3>English Web TR 21. Yaşına Giriyor! 🎉</h3>
			<div id="geri-sayim">
				<div class="zaman-kutusu"><span id="gun">0</span><div class="etiket">Gün</div></div>
				<div class="zaman-kutusu"><span id="saat">00</span><div class="etiket">Saat</div></div>
				<div class="zaman-kutusu"><span id="dakika">00</span><div class="etiket">Dakika</div></div>
				<div class="zaman-kutusu"><span id="saniye">00</span><div class="etiket">Saniye</div></div>
			</div>
		</div>

		<!-- 23 Ekim'de Çıkacak Mesaj -->
		<div id="yildonumu-mesaji">
			English Web TR 21 Yaşında! 🥳
		</div>
	</div>
</section>

<script>
	document.addEventListener("DOMContentLoaded", function() {
		// Hedef Tarih: 23 Ekim 2026, Saat 00:00:00
		const hedefTarih = new Date("October 23, 2026 00:00:00").getTime();
		const ertesiGun = new Date("October 24, 2026 00:00:00").getTime();

		const geriSayimAlani = document.getElementById("geri-sayim-alani");
		const mesajKutusu = document.getElementById("yildonumu-mesaji");

		let ilkDakikaKonfetisi;
		let hoverKonfetisi;

		function konfetiPatlat() {
			confetti({
				particleCount: 80,
				spread: 80,
				origin: { y: 0.6 },
				colors: ['#dfa928', '#ffffff', '#ffeb3b', '#e67e22'] 
			});
		}

		function zamaniGuncelle() {
			const suAn = new Date().getTime();

			// 23 Ekim 2026 gününün içindeysek
			if (suAn >= hedefTarih && suAn < ertesiGun) {
				geriSayimAlani.style.display = "none";
				mesajKutusu.style.display = "block";

				// İlk 1 dakika konfeti şöleni
				if (suAn < hedefTarih + 60000) {
					if (!ilkDakikaKonfetisi) {
						ilkDakikaKonfetisi = setInterval(konfetiPatlat, 800);
						setTimeout(() => {
							clearInterval(ilkDakikaKonfetisi);
						}, hedefTarih + 60000 - suAn);
					}
				}
			} 
			// Geri sayım devam ediyorsa
			else if (suAn < hedefTarih) {
				geriSayimAlani.style.display = "block";
				mesajKutusu.style.display = "none";

				const kalanZaman = hedefTarih - suAn;
				
				const gun = Math.floor(kalanZaman / (1000 * 60 * 60 * 24));
				const saat = Math.floor((kalanZaman % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
				const dakika = Math.floor((kalanZaman % (1000 * 60 * 60)) / (1000 * 60));
				const saniye = Math.floor((kalanZaman % (1000 * 60)) / 1000);

				document.getElementById("gun").innerText = gun;
				document.getElementById("saat").innerText = saat.toString().padStart(2, '0');
				document.getElementById("dakika").innerText = dakika.toString().padStart(2, '0');
				document.getElementById("saniye").innerText = saniye.toString().padStart(2, '0');
			} else {
				// 24 Ekim ve sonrası için alanı tamamen gizle
				document.getElementById("kutlama-alani").style.display = "none";
			}
		}

		setInterval(zamaniGuncelle, 1000);
		zamaniGuncelle();

		mesajKutusu.addEventListener("mouseenter", () => {
			hoverKonfetisi = setInterval(konfetiPatlat, 400); 
		});

		mesajKutusu.addEventListener("mouseleave", () => {
			clearInterval(hoverKonfetisi);
		});
	});
</script>
<!-- 21. YIL KUTLAMA BÖLÜMÜ BİTİŞİ -->

<section class="ewt-stats-strip">
	<div class="ewt-container ewt-stats-inner">
		<div class="ewt-stat"><strong>2005</strong><span>Kuruluş Yılı</span></div>
		<div class="ewt-stat"><strong><?php echo count( $cpts ); ?></strong><span>İçerik Kategorisi</span></div>
		<div class="ewt-stat"><strong>A1–C2</strong><span>Tüm Seviyeler</span></div>
		<div class="ewt-stat"><strong>20+</strong><span>Yıllık Öğretmenlik Deneyimi</span></div>
	</div>
</section>

<?php ewt_campus_k12(); ?>

<section id="ewt-cpt-grid" class="ewt-container ewt-cpt-grid-wrap">
	<h2 class="ewt-section-title">Eğitim Kademeleri &amp; Sınavlar</h2>
	<p class="ewt-section-subtitle">İhtiyacına en yakın kategoriden başla, içerikler eskiden yeniye sıralı — kolayca takip et.</p>
	<div class="ewt-cpt-grid">
		<?php foreach ( $cpts as $slug => $data ) :
			list( $label, $plural, $icon ) = $data;
			$link = get_post_type_archive_link( $slug );
			if ( ! $link ) continue;
			?>
			<a class="ewt-cpt-card" href="<?php echo esc_url( $link ); ?>">
				<span class="ewt-cpt-card-icon dashicons <?php echo esc_attr( $icon ); ?>"></span>
				<span class="ewt-cpt-card-label"><?php echo esc_html( $label ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php if ( get_option( 'ewt_lab_enabled', '1' ) === '1' ) : ?>
<section id="ewt-lab-home" class="ewt-home-lab-wrap">
	<div class="ewt-container">
		<h2 class="ewt-section-title">🎧 Dinleme &amp; Konuşma Laboratuvarı</h2>
		<p class="ewt-section-subtitle">Üye olmadan, tamamen ücretsiz: seviyeni ve konunu seç; sana özel bir dinleme parçası oluşturalım. Dinle, anlama testini çöz, ardından konuşma ve telaffuz (shadowing) çalışmasıyla pekiştir.</p>
		<?php echo do_shortcode( '[ewt_listening_lab title="Hemen Dene: Dinle → Test Çöz → Konuş"]' ); ?>
	</div>
</section>
<?php endif; ?>

<?php ewt_ebook_home_section(); ?>

<section class="ewt-why-wrap">
	<div class="ewt-container">
		<h2 class="ewt-section-title">Neden English Web TR?</h2>
		<div class="ewt-why-grid">
			<div class="ewt-why-card">
				<span class="ewt-why-icon">🧩</span>
				<h3>Seviye Bazlı Yapı</h3>
				<p>Her kademe ve beceri kendi kategorisinde; A1'den C2'ye net bir yol haritası izle.</p>
			</div>
			<div class="ewt-why-card">
				<span class="ewt-why-icon">🎧</span>
				<h3>Dört Beceri + Grammar/Vocabulary</h3>
				<p>Listening, Speaking, Reading, Writing, Vocabulary ve Grammar için ayrı, derinlemesine içerikler.</p>
			</div>
			<div class="ewt-why-card">
				<span class="ewt-why-icon">🏆</span>
				<h3>Sınav Odaklı Hazırlık</h3>
				<p>TOEFL, GRE, YDS ve GMAT için özel olarak tasarlanmış stratejiler ve pratik materyaller.</p>
			</div>
			<div class="ewt-why-card">
				<span class="ewt-why-icon">👨‍🏫</span>
				<h3>20+ Yıllık Deneyim</h3>
				<p>Future Classroom Lab ve Flipped Classroom sertifikalı, modern öğretim yaklaşımıyla birebir destek.</p>
			</div>
		</div>
	</div>
</section>

<section class="ewt-container ewt-recent-wrap">
	<h2 class="ewt-section-title">Blog'dan Son Yazılar</h2>
	<div class="ewt-post-grid">
		<?php
		$recent = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 6 ) );
		while ( $recent->have_posts() ) : $recent->the_post();
			get_template_part( 'template-parts/content-cpt' );
		endwhile;
		wp_reset_postdata();
		?>
	</div>
</section>

<?php ewt_campus_nostalgia(); get_footer(); ?>
