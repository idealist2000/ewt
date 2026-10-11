# English Web TR

Güncel geliştirme sürümü: **1.14.1**. WordPress’e yüklenebilir paket: `englishweb-v1.14.1.zip`. Düzenlenebilir tema kaynakları `theme/` klasöründedir; önceki ZIP sürümleri korunmuştur.

## 1.14.1 — Öneri sonuçlarından bağlantılı PDF e-kitap

Öneri sayfasında **PDF e-kitabımı oluştur** düğmesi bulunur. Mevcut e-kitap oluşturucunun nonce, saatlik kullanım sınırı, kapatma ayarı ve 12 saatlik yazdırma bağlantısı kullanılır. Seçilen sıradaki ilk öneriler PDF’ye alınır; varsayılan sınır 30, Görünüm → English Web Ayarları’ndaki mevcut e-kitap konu sınırından değiştirilebilir (en fazla 100). PDF oluşturma mevcut sonuç sayfasının numarasından bağımsızdır; her zaman listenin başından başlar. Seviye, eğitim grubu, sınav/amaç ve hedef PDF’de gösterilir.

PDF yalnızca içerik başlıklarını ve tıklanabilir site bağlantılarını içerir; ders gövdeleri aktarılmaz. Açılan görünümde **PDF olarak kaydet / Yazdır** düğmesine basıp tarayıcıdaki **PDF olarak kaydet** seçeneğini kullanın. Otomatik dosya indirme değildir; mevcut PDF yöntemiyle aynı yazdırma akışını kullanır. Pencere engellenirse sonuç alanındaki **PDF görünümünü aç** bağlantısı kullanılabilir. JavaScript gerekir; üyelik veya e-posta gerekmez. E-kitap özelliği tema ayarlarından kapalıysa bu düğme de görünmez. Yazdırma bağlantısı açıldığında içeriklerin hâlâ yayınlanmış ve şifresiz olduğu tekrar kontrol edilir.

`tests/learning-path-ebook.integration.php` bağlantı modu, sıralama/sınır, eski tam içerik modu ve erişim kontrolünü; `tests/learning-path-ebook.browser.cjs` gerçek PDF çıktısı ve tıklanabilir PDF bağlantıları dahil tarayıcı akışını kontrol eder. Testler yalnızca yerel geliştirme WordPress’i içindir. Gerçek PDF ayrıca bir PDF ayrıştırıcısıyla doğrulandı.

## 1.14.0 — Öğrenme yolu ve kısa seviye testi

Header’ın hemen altında, temanın bütün sayfalarında CEFR seviyesi (A1–C2), okul öncesi / 1–12. sınıf / yetişkin / undergraduate / graduate / postgraduate, amaç veya sınav, hedef ve sıralama seçilir. TOEFL, IELTS, YDT, YDS, YÖKDİL, GRE, GMAT, genel İngilizce, akademik eğitim ve kariyer desteklenir. Sınav değiştiğinde hedef seçenekleri yenilenir. Seçimler aynı tarayıcıda hatırlanır; “Seçimleri sıfırla” bunları temizler. JavaScript olmadan temel öneri formu çalışır; hedefleri yenilemek için önce sınavı seçip formu gönderin.

“İçerikleri keşfet”, yayınlanmış ve şifresiz ders/blog içeriklerini 12’şerli sayfalar hâlinde gösterir. Sayfa içerikleri silmez veya yayın durumlarını değiştirmez. İlgi sıralaması amaç eşleşmesi (60), hedef (35), eğitim grubu (25; yalnızca okul kademesi eşleşiyorsa 10) ve CEFR yakınlığına (en fazla 20) dayanır. Kolaydan zora sıralamada bilinen seviyeler A1→C2 gelir; aynı seviyede ilgi puanı kullanılır. Seviyesi bilinmeyenler sondadır. Daha az ilgili içerikler de listenin devamında görülebilir. Hedef puan sınav başarısı tahmini veya garanti değildir; TOEFL’ın iki puan ölçeği ayrı seçeneklerdir, aralarında otomatik dönüşüm yapılmaz.

### İçerikleri eşleştirme

Yazı veya ders düzenleyicisindeki **Öğrenme yolu eşleştirmesi** kutusunda CEFR seviyesi, eğitim grubu, amaç ve hedef belirlenebilir. Hedef seçerken aynı sınavı Amaç alanında seçin. Örneğin B2 / Postgraduate / TOEFL / 100+.

Alanlar boş bırakılırsa mevcut CPT ve `_seviye` taksonomileri kullanılır. CEFR etiketleri (örn. `b2-seviyesi-reading`), sınıf adları (örn. `5. Sınıf`) ve tanımlı hedef kodlarına birebir uyan etiketler (örn. `toefl-100p`) okunur. Etiketler ve içerikler otomatik değiştirilmez. Yaş/sınıftan CEFR seviyesi veya sınav skorundan dil seviyesi tahmin edilmez. Yetişkin, graduate ve postgraduate için kesin eğitim grubu eşleşmesi gerekiyorsa bu kutuyu doldurun. Yanlış sınava ait hedefler kaydedilmez.

### Ana sayfa testi

Test yalnızca ana sayfada görünür. Her CEFR seviyesinde 5 soru, toplam 30 soru vardır. Dil bilgisi, kelime ve kısa okuma değerlendirilir; dinleme, konuşma veya yazma üretimi ölçülmez. Bu bir standartlaştırılmış CEFR testi, sertifika ya da TOEFL/YDT/YDS denemesi değildir. Geniş yaş grupları için başlangıç önerisi verir; eğitsel soru kalibrasyonu ve kapsamlı seviye değerlendirmesi ayrıca yapılmalıdır.

Başlangıç önerisi, A1’den kesintisiz biçimde her blokta en az 4/5 doğru elde edilen son seviyedir. A1 bloğu tamamlanmazsa A1 temelleri önerilir. İleri sorulardaki tesadüfi doğrular temel blokları atlatmaz. “Bilmiyorum”, geri gitme, yeniden başlama ve cevap açıklamaları desteklenir. Sonuçtaki düğme CEFR seçimini günceller; eğitim grubu, amaç ve hedef korunarak içerik önerilerine geçilir. Cevaplar sunucuya veya bir AI sağlayıcısına gönderilmez ve kaydedilmez; soru/cevap anahtarı tarayıcıda görünür olduğundan gözetimli sınav için kullanılmamalıdır.

## Kurulum

Önce mevcut WordPress dosya ve veritabanı yedeğinizi alın. Görünüm → Temalar → Yeni Ekle → Tema Yükle üzerinden `englishweb-v1.14.1.zip` paketini yükleyin ve English Web TR temasını etkinleştirin. Bu paket `englishweb/` tema dizini içerir. Mevcut kurulumunuz farklı bir tema klasör adı kullanıyorsa canlı siteye uygulamadan önce bir test sitesinde doğrulayın. Menü konumları ve tema ayarlarını kontrol edin, önbellekleri temizleyin. Yeni öneri sayfası `?ewt_path=1` ile çalışır; ek sayfa oluşturma veya yeni rewrite kuralı gerektirmez.

## Geliştirme ve doğrulama

Cloud ortamında WordPress/PHP 8.3 ve MariaDB Docker ile çalışır. Hazırlanmış yerel kurulum `/workspace/ewt-local` altındadır; `start.sh` servisleri başlatır, `wp.sh` WP-CLI çalıştırır. Tema bu ortamda `/workspace/ewt-local/theme` klasöründen bağlanır. Kaynak değişikliklerini bu klasöre uygulayın; parolaları veya `runtime.env` dosyasını depoya eklemeyin. Dış AI API’leri bu yeni özellik için gerekli değildir.

- `node --test tests/placement-score.test.cjs`: soru bankası kapsamı, seviye eşikleri ve hatalı/eksik cevaplar.
- `tests/learning-path.integration.php`: etkin 1.14.0 temasıyla `wp eval-file /path/to/learning-path.integration.php`; geçici test içerikleri oluşturur ve sonunda kaldırır. `ewt_local_admin` adlı yerel test yöneticisini kullanır; üretim sitesinde çalıştırmayın.
- `node tests/learning-path.browser.cjs`: Playwright ve Chromium ile yerel 8080 portunda seçimler, test akışı, sonuç aktarımı ve mobil görünüm. Bu kontrol hazırlanmış “development-speaking-smoke-test” örnek yazısını kullanır.
- Tema PHP dosyalarında `php -l`, JavaScript dosyalarında `node --check`.

Mevcut AI, dinleme/konuşma laboratuvarı ve diğer özelliklerin ayrıntıları `theme/README.md` içindedir.
