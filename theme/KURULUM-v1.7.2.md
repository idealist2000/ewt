# English Web TR 1.7.2

## Kurulum
1. Mevcut tema ve veritabanı yedeğinizi alın.
2. Görünüm → Temalar → Yeni Ekle → Tema Yükle üzerinden ZIP dosyasını yükleyin; mevcut temayı yenisiyle değiştirin.
3. Görünüm → Kampüs ve Otomasyon sayfasını açın.
4. Telefon, WhatsApp ve e-posta bilgilerinizi kaydedin. Telefon mevcut English Web Ayarları ile ortak alandır. WhatsApp bağımsızdır; boşsa telefon kullanılır.
5. Önbellek/CDN kullanıyorsanız önbelleği temizleyin. Yeni logo temaya gömülüdür; eski özel logonun yerine görünür.

## Mevsimler
İstanbul tarihine göre 1 Mart ilkbahar, 1 Haziran yaz, 1 Eylül sonbahar, 1 Aralık kış. Sonbahar düşen yaprak sarısı, kış beyaz, ilkbahar yeşil, yaz açık sarıdır. Logonun vurgusu, favicon ve site renkleri beraber değişir. Panelden mevsim elle de seçilebilir. Metin kontrastı için koyu yazı rengi kullanılır. JavaScript önbellekte kalan sayfalarda da mevsimi günceller.

## K/12
1–12. sınıflar ana sayfada açılabilir kartlardır. Sınıf numarası ile konu adından slug türetilir. Yayımlanmış içerik varsa gerçek kalıcı bağlantı kullanılır. İçerik yoksa 404 yerine noindex bir konu karşılama sayfası açılır.

“Kampüs ve Otomasyon → Ünite içeriklerini yazdır ve yayımla” düğmesine bir kez basmanız yeterlidir. Mevcut katalog ünite taslakları sırayla işlenir. Boş ders metinleri seçilen sağlayıcıya yazdırılır, mevcut yazılı taslaklar metinleri korunarak doğrudan publish durumuna geçirilir. Mevcut yazının ID ve bağlantısı korunur. Dolu ve yayımlanmış yazılar tekrar işlenmez.

Tüm sınıflar veya tek sınıf seçilebilir. Henüz oluşturulmamış üniteleri de yazdırmak için ilgili kutuyu işaretleyin. Çöpte, özel, bekleyen veya ileri tarihli kayıtlar otomatik olarak değiştirilmez. Silinmiş üniteleri geri getirmek istiyorsanız önce WordPress çöp kutusundan kendiniz geri yükleyin.

İşlem sırasında sekmeyi açık tutun. İlerleme ve her ünitenin yayın bağlantısı görünür. Duraklat düğmesi mevcut üniteden sonra durur. Tekrar başlatmak tamamlananları atlayarak kalanları işler. API anahtarı eksikse işlem duraklar. API hatası veya çok kısa yanıt alan ünite yayımlanmaz; hata listelenir. Bu işlem günlük blogdaki taslak/yayın seçeneğinden bağımsız olarak daima publish kullanır. Sunucu istek zaman sınırı uzun API yanıtlarına izin vermelidir.

K/12 üretiminde sınıfa uygun Türkçe açıklamalar, İngilizce örnekler, kelime tablosu, alıştırmalar ve cevap anahtarı istenir. Üretilen HTML güvenli etiketlerle kaydedilir. Sınıf taksonomisi ve SEO alanları atanır. Başka bir düzenleyici işlem sırasında yazıyı değiştirirse üzerine yazılmaz; yeniden başlatma uyarısı gösterilir.

Katalog eski ünite başlıkları, güncel Maarif tema başlıkları ve destekleyici konuları bir arada kapsar; belirli bir ders kitabının onaylı yıllık planı değildir. Sınıf|konu alanından kullanılan programa göre düzenlenebilir. 1. sınıf İngilizceye hazırlık/erken öğrenme alanıdır. 12. sınıf güncel Maarif program sayfası başlık döndürmediği için mevcut yaygın konu başlıkları korunmuştur.

## Pexels
İlk fotoğrafı koşulsuz alma kaldırıldı. 24 adayın açıklamaları konu sözcükleriyle karşılaştırılır; eşleşme yetersizse fotoğraf atlanır. HTTP, alan adı ve gerçek görüntü verisi doğrulanır. Bu, fotoğrafın piksel içeriğini anlayan bir görsel model değildir; son editoryal gözden geçirme faydalıdır. Eski yazılardaki görseller otomatik silinmez. Soyut dilbilgisi başlıklarında fotoğraf bulunmaması normaldir. Gerekirse somut İngilizce sorgu girin.

## İletişim ve footer
Telefon tüm eski telefon CTA alanlarında aynı seçeneği kullanır. WhatsApp ve e-posta footer/CTA alanlarında gösterilir. [ewt_contact] ve [ewt_cta] kısa kodları yazı ve sayfalarda kullanılabilir. Alt CTA ve sohbet görünürlüğü panelden değişir; üstteki özel ders bağlantıları temanın mevcut davranışını korur. Footer dört sütun, kontrast uyumlu logo, sınıf bağlantıları, beceriler ve sınavlar içerir; eski footer widget alanları korunur.

## Günlük blog
API anahtarını English Web Ayarları’nda tanımlayın. Kampüs ve Otomasyon’da her satıra bir konu girin; sağlayıcıyı, saati ve yayımla/taslak seçeneğini belirleyip günlük blogu açın. Bu seçenek başlangıçta kapalıdır ve kuyruk boştur. Her İstanbul takvim gününde en fazla bir yazı oluşturulur; tamamlanan başlık tekrar üretilmez. API hatasında başlık kuyrukta kalır; sonraki günlük çalışmada tekrar denenir. Son durum panelde görünür. Anahtar veya kota olmadan içerik üretimi çalışmaz.

WP-Cron site ziyaretleriyle tetiklenir; ziyaret olmayan saatte yayın gecikebilir. Kesin zamanlama için barındırma panelinde wp-cron.php çağrısı planlayın. Günlük ayarları kaydeden yönetici otomatik yazıların yazarı olur. Rank Math başlık ve odak kelime alanları mevcut SEO fonksiyonuyla doldurulur.

## Sayaçlar
Ana sayfa, okul öncesi/ilkokul/ortaokul/lise tekil sayfa ve arşivleri, seviye sayfaları ve yeni ünite karşılama sayfalarında gösterilir. Varsayılan yakınlık 30 gündür. En yakın veya devam eden etkinlik gösterilir; süre dolunca sonrakine geçer. 2026–2027 okul takvimi ile 2027 Ramazan/Kurban tarihleri eklenmiştir. Sabit ulusal tatiller yıllık hesaplanır. Yeni öğretim yılı ve sonraki yılların dini bayram tarihlerini panelde güncelleyin; okul ve dini bayram tarihleri sonraki yıla otomatik kopyalanmaz. Arife öğleden sonraları ve tatilin son günü hesaba katılır.

## Nostalji — tamamlanamayan bölüm
Internet Archive bu çalışma sırasında zaman aşımı ve HTTP 429 hatası verdi. 2005 ve sonrasındaki ilk başarılı kaydın tarihi doğrulanamadı, gerçek ekran görüntüsü alınamadı. Nostalji bölümü ve arşiv bağlantısı hazır; ekran görüntüsü eklenmiş değildir. Doğrulanmış kayıt bağlantısını ve Medya Kütüphanesi’ne yüklenen ekran görüntüsü URL’sini Kampüs ve Otomasyon alanına ekleyin. Bir görüntü eklenmedikçe bölüm kırık resim göstermeden çalışır.

## Kaynaklar
- Okul takvimi: https://cicekhatunanaokulu.meb.k12.tr/icerikler/20262027egitimogretimyilimebcalismatakvimi_17573317.html
- Diyanet 2027 resmî tatiller: https://vakithesaplama.diyanet.gov.tr/icerik.php?icerik=159
- Temel eğitim Maarif programı: https://tymm.meb.gov.tr/ogretim-programlari/ders/ingilizce-dersi-temel-egitim
- Lise Maarif programı: https://tymm.meb.gov.tr/ogretim-programlari/ders/ingilizce-dersi-9-12

## Doğrulama kapsamı
PHP sözdizimi ve izole PHP 8.3 testleri: tarih, saat dilimi, sınıf kataloğu, WhatsApp normalizasyonu, günlük zamanlama ve tek yayın koruması kontrol edildi. Canlı WordPress veritabanı ve gerçek API anahtarlarıyla uçtan uca test yapılmadı.

Masaüstü ve mobil bileşen önizlemelerinde taşma kontrolü yapıldı. Tarayıcıda mevsim düzeltmesi, sayaç hesabı ve süresi dolan etkinliğin gizlenmesi ayrıca doğrulandı. Önizleme canlı WordPress kurulumu değildir.

1.7.2 kontrolü: izole PHP testlerinde yeni içerik üretimi/yayın, mevcut taslağın aynı ID ile yayını, tekrarlı işlemin atlanması, API/boş yanıt hataları, mevcut metnin korunması ve eşzamanlı işlem kilidi doğrulandı. Tarayıcı testinde tek tıklama ile kuyruğun sıralı işlenmesi ve sonuçların gösterilmesi doğrulandı. Gerçek API ile canlı WordPress testi yapılmadı.
