# English Web TR — Özel WordPress Teması

## Footer düzeltmesi (v1.15.1)

Ana sayfa sonunda yanlışlıkla görünen güncelleme açıklaması kaldırıldı. Mimoza Bilişim imzası footer’ın alt bölümünde ortalandı.

## Tıklamayla chatbot ve SEO kuralları (v1.15.0)

Yatay “Sorunuz var mı?” düğmesi chatbotu yalnızca tıklanınca açar. Soru/örnek, sesle yazma ve mevcut iletişim seçenekleri korunur. SEO başlıkları tam focus keyword ile başlar, kelimeler büyük harfle başlar ve 55 karakteri aşmaz; gerçek bölüm sayısı, uygun duygu ifadesi veya “Rehber” kullanılır. Çok uzun FK uyarıyla atlanır.

Görünüm → SEO İçerik Kontrolü ile mevcut Rank Math/tema kayıtlarını analiz edip güncelleyin. Mevcut URL'ler korunur. Yoğunluk %1,9 hedefi olarak ölçülür; mevcut metin zorla yeniden yazılmaz. Ders/yazılarda alt metinlere FK ve dışa açılan altı ek okuma kaynağı eklenir. Pexels İngilizce öğretimine özel sorgu ve açıklama eşleşmesi kullanır; uygun fotoğraf yoksa görsel atlanır. Tam kullanım ve doğrulama ayrıntıları dağıtım deposunun README dosyasındadır.

## Öneri sonuçlarından bağlantılı PDF (v1.14.1)

Öneri sayfasındaki “PDF e-kitabımı oluştur” düğmesi, seçtiğiniz sıralamadaki ilk içeriklerin başlık ve tıklanabilir bağlantılarından bir çalışma listesi hazırlar. Varsayılan sınır mevcut e-kitap ayarına göre 30’dur. Seviye, eğitim grubu ve hedef korunur. Açılan görünümde “PDF olarak kaydet / Yazdır” düğmesine basıp tarayıcınızda PDF olarak kaydedin. Ders metinleri bu yeni bağlantı moduna dahil edilmez. Mevcut tam içerikli PDF/EPUB oluşturucu kullanılmaya devam eder.

## Öğrenme yolu ve seviye testi (v1.14.0)

Tüm tema sayfalarında header altında A1–C2, sınıf/eğitim grubu, sınav/amaç, hedef ve sıralama seçimi bulunur. Ana sayfadaki 30 soruluk kısa dil bilgisi/okuma testi sonucu bu seçimleri kullanarak önerilere aktarılır. Bu bir resmî CEFR belgesi veya sınav skoru tahmini değildir. İçerik düzenleyicisinde “Öğrenme yolu eşleştirmesi” kutusuyla kesin seviye, eğitim grubu, amaç ve hedef atanabilir; boş alanlarda mevcut içerik türleri ve seviye etiketleri kullanılır. Etiketsiz içeriklerin seviyesi tahmin edilmez. Tam kullanım ve geliştirme belgeleri dağıtım deposunun README dosyasındadır.


## Kurulum
1. Bu klasörü zip'leyin (zaten `englishweb-theme.zip` olarak hazırlandı).
2. WordPress Dashboard > Görünüm > Temalar > Yeni Ekle > Tema Yükle ile zip'i yükleyin, etkinleştirin.
3. **Görünüm > Menüler**: Yeni bir menü oluşturun, "Ana Mega Menü" konumuna atayın.
   - Üst seviye öğeler = ana menü (Listening, TOEFL, vb.)
   - 2. seviye alt öğeler = tıklanabilir link (görsel olarak sütun başlığı gibi görünür) — menünüzü sadece 2 seviyeli kurduysanız bu yeterlidir
   - 3. seviye alt öğeler (opsiyonel) = üstündeki 2. seviye linkin altında ek bağlantı listesi
   - Her menü öğesinde "Dashicon adı" ve "Öne çıkan" alanlarını göremiyorsanız ekranın sağ üstündeki "Ekran Seçenekleri"nden ilgili kutuyu açın.
4. **Görünüm > Özelleştir > Site Kimliği**: Logonuzu yükleyin (custom-logo desteği hazır).
5. **Görünüm > English Web Ayarları**: You.com / Claude / OpenAI API anahtarlarınızı ve özel ders telefon numaranızı girin.

## Konuyu Konuşarak Pekiştir (v1.13.0)

Yayımlanmış, şifresiz ders ve blog yazılarının sonunda otomatik görünür. Öğrenci Başla düğmesine basınca dersin başlığı, sunucudaki içeriği ve mevcut seviye etiketlerine uygun İngilizce bir konuşma sorusu üretilir. Öğrenci mikrofonla İngilizce cevap verir veya cevabı yazar; metni kontrol edip gönderince Türkçe geri bildirim, düzeltilmiş İngilizce cümle, örnek cevap ve cevaba bağlı devam sorusu gelir. Çalışma üç sorudur; aynı soruyu tekrar deneme ve örneği sesli dinleme desteklenir.

- Mevcut You.com anahtarı ve seçili Research/Express modu kullanılır. Research kısa yanıtlar için lite çalışır. Yanıt süresi 20 saniye ile sınırlıdır; bağlantı veya yanıt biçimi hatalarında öğrencinin metni silinmez. Otomatik API tekrar denemesi yoktur.
- Bir çalışma normalde 4 API çağrısı kullanır (ilk soru + 3 değerlendirme). İlk soru 24 saat önbelleğe alınır; öğrenci cevapları ve kişisel geri bildirimler tema tarafından kalıcı olarak saklanmaz. IP başına yardım balonuyla ortak 10 denemelik sınır vardır; son API denemesinden bir saat sonra sayaç sona erer. Tekrar değerlendirmeler de kotadan sayılır. You.com hesabının kredi ve fiyatlandırması geçerlidir.
- Ses tanıma için HTTPS, mikrofon izni ve Web Speech Recognition destekleyen bir tarayıcı gerekir. Tarayıcı ses işleme için kendi çevrimiçi hizmetini kullanabilir. Ham ses WordPress veya You.com'a gönderilmez; onaylanan metin ve ders bağlamı You.com'a gider. Tarayıcı desteği yoksa yazılı cevapla devam edilir. Sesli okuma cihazdaki İngilizce seslerin bulunmasına bağlıdır.
- Geri bildirim metin üzerinden konuya uygunluk, dil bilgisi ve kelime kullanımını değerlendirir. Telaffuz doğruluğu veya ses üzerinden akıcılık puanı değildir. Otomatik geri bildirim hata yapabilir.
- Güncellemeden sonra önbelleği temizleyip bir dersin sonundaki bölümü deneyin. Elementor ile single.php yerine özel bir tekil şablon kullanılıyorsa bu otomatik yerleşim o şablonda görünmeyebilir.

## Kayıtlı You.com moduyla uyumluluk (v1.12.3)

Ders yardımcısı artık `ewt_youcom_mode` ayarını kullanır: Research seçiliyse Research, Express seçiliyse Express çağrılır. Önceki sürümlerde yardımcı Express'i zorunlu kullanıyordu. Kısa yardım yanıtlarında Research eforu `lite`, istek süresi 20 saniye, yanıt hedefi en fazla 250 kelimedir; diğer içerik üreticilerinin efor ve süre ayarları korunur. Otomatik sağlayıcı değişimi veya tekrar çağrı yapılmaz. Modlar farklı önbellek anahtarları kullanır. Uzun Research istekleri hâlâ süre sınırına ulaşabilir; başarılı yönetim bağlantı testi, tüm ders yanıtlarının bu sürede tamamlanacağını garanti etmez.

## Hosting hata yanıtı uyumluluğu (v1.12.2)

Yardımcının yakalayabildiği uygulama hataları HTTP 200 içinde `success:false`, `code` ve `http_status` alanlarıyla gönderilir. Böylece 4xx/5xx JSON gövdelerini kendi HTML hata sayfasıyla değiştiren hostinglerde asıl açıklama korunur. Bu değişiklik başarısız işlemi başarılı saymaz; nonce ve kota denetimleri devam eder. Gerçek proxy/PHP kesintileri yine HTTP 502 olarak görünebilir ve hosting günlüğü gerektirir. You.com zaman aşımı ayrıca EWT-YOUCOM-TIMEOUT koduyla belirtilir.

Bağlantı testi seçili You.com modunu test eder. v1.12.3 itibarıyla yardımcı da aynı mod seçimini kullanır. Güncelleme sonrası tema sürümünün 1.12.3 olduğunu doğrulayın ve önbellekleri temizleyin.

## Ders yardımcısı yanıt düzeltmesi (v1.12.1)

- Yardımcının You.com Express isteği en fazla 20 saniye bekler; diğer içerik üretim araçlarının bekleme süresi korunur. Tarayıcı 30 saniyede beklemeyi sonlandırır.
- JSON yerine HTML/boş yanıt dönen sunucularda HTTP kodu gösterilir; zaman aşımı, erişim engeli, API kredi/istek sınırı ve PHP sunucu hataları ayrılır. Ham sunucu çıktısı ziyaretçiye gösterilmez.
- Hatalı/boş API yanıtları denetlenir; başarısız API denemeleri de yardımcının saatlik sınırına dahil edilir. Otomatik tekrar denemesi yapılmaz.
- Güncellemeden sonra WordPress, CDN ve tarayıcı önbelleğini temizleyin. Sorun sürerse ekrandaki HTTP/EWT kodunu ve hosting PHP hata günlüğünü inceleyin. Bu değişiklik hosting erişim kurallarını veya You.com kredi bakiyesini değiştirmez.

## Bağlama Duyarlı Ders Yardımcısı (v1.12.0)

Tekil ders ve blog içeriklerinde, ziyaretçi bir paragrafta beklenen okuma süresinden biraz daha uzun kaldığında sohbet balonu otomatik olarak yardım teklif eder. Ziyaretçi:

- O paragraftaki anlatım için dört yeni İngilizce örnek ve Türkçe karşılıklarını isteyebilir.
- Sorununu yazabilir veya desteklenen tarayıcılarda mikrofonla yazıya dönüştürebilir.
- Paragraf ve ders başlığı bağlamında kısa bir öğretmen yanıtı alabilir.

Yanıtlar her zaman **Görünüm > English Web Ayarları** bölümünde kayıtlı You.com anahtarıyla, sunucu tarafında Express API üzerinden üretilir; anahtar tarayıcıya gönderilmez. Aynı istekler 24 saat önbelleğe alınır ve her IP için önbelleğe girmeyen en fazla 10 saatlik çağrıya izin verilir. Ses dosyası siteye yüklenmez; tarayıcı yalnızca dönüştürülen soru metnini forma aktarır. Mikrofon özelliği olmayan tarayıcılarda yazılı soru kullanılmaya devam eder.

## Custom Post Type'lar
Aşağıdaki 15 CPT otomatik olarak oluşur, her biri kendi "Seviye" taksonomisine sahiptir
(siz A1/A2/... veya 1.Sınıf/2.Sınıf gibi terimleri Dashboard'dan eklersiniz):

- İlkokul Öncesi İngilizce, İlkokul İngilizce, Ortaokul İngilizce, Lise İngilizce
- YDT, TOEFL, GRE, YDS, GMAT
- Listening, Speaking, Reading, **Writing**, Vocabulary, Grammar

Eski normal blog yazılarınız (Yazılar / `post`) hiç değişmeden aynı şekilde çalışmaya devam eder.

Arşiv ve seviye sayfalarında içerikler **en eskiden en yeniye** sıralanır (öğrenci konuyu ilk yazıdan takip edebilsin diye).

## AI İçerik Widget'ı
Her yazı/CPT düzenleme ekranında sağda "🤖 AI İçerik Oluştur" kutusu bulunur:
- Sağlayıcı seçin (You.com, Claude, OpenAI), zorluk derecesi ve hedef kelime sayısını ayarlayın, talimat yazın, "İçerik Üret"e tıklayın.
- Üretilen metin doğrudan editöre (Gutenberg veya Classic) eklenir.

**You.com entegrasyonu (v1.1 düzeltmesi):** İlk sürümdeki You.com endpoint'i tahmine dayalıydı ve gerçek API ile eşleşmiyordu ("algılanmadı" hatasının sebebi buydu). Şimdi gerçek You.com API'leri kullanılıyor:
- **Research API** (`POST https://api.you.com/v1/research`, header `X-API-Key`) — çok adımlı, kaynak destekli, en kaliteli sonuç.
- **Express API** (`POST https://api.you.com/v1/ai/search`, header `X-API-Key`) — arama + LLM cevabı tek çağrıda, daha hızlı/düşük maliyetli.
- Görünüm > English Web Ayarları'ndan mod (Research/Express) ve Research Effort (Lite/Standard/Exhaustive) seçilebilir.
- Her API anahtarının yanında artık bir **"✓ Bağlantıyı Test Et"** butonu var; anahtarı kaydetmeden de (alana yazdıktan sonra) test edebilirsiniz. You.com, Claude, OpenAI ve Pexels için ayrı ayrı çalışır.

Ayrıca ilk sürümde Claude çağrılarında geçersiz bir model adı (`claude-sonnet-4-6`) kullanılıyordu; bu `claude-sonnet-5` olarak düzeltildi.

**Atıf/kaynak numaralandırma temizliği (v1.2 düzeltmesi):** You.com Research/Express gibi "kaynak destekli" API'ler cümle sonlarına `[[5, 3]]` veya `[1, 2]` gibi kaynak referans işaretleri ekleyebilir. Artık `ewt_strip_ai_citations()` fonksiyonu, dört sağlayıcının (You.com Research, You.com Express, Claude, OpenAI) döndürdüğü her metinden bu işaretleri otomatik temizler; markdown linkleri (`[metin](url)`) etkilenmez. Bu temizlik, tekil AI içerik widget'ında da toplu içerik üretiminde de otomatik geçerlidir (ikisi de aynı sağlayıcı fonksiyonlarını kullanır).

## Otomatik Öne Çıkan Görsel
"🖼️ Otomatik Öne Çıkan Görsel" kutusu, OpenAI'nin görsel üretim API'sini kullanarak (gpt-image-1) bir görsel üretir ve otomatik olarak öne çıkan görsel yapar. Görsel üretimi şu an yalnızca OpenAI anahtarınız girildiğinde çalışır (Claude ve You.com bu yazı itibarıyla genel amaçlı görsel üretim API'si sunmuyor).

## SEO Widget'ı
Her içerikte "SEO Ayarları" kutusu: Focus Keyword (FK) girildiğinde:
- Slug otomatik olarak FK'dan üretilir,
- Page Title FK ile başlamak zorundadır, 55 karakteri geçemez,
- Meta Açıklama FK ile başlamak zorundadır, 130 karakteri geçemez,
- Değerler otomatik olarak Yoast SEO ve Rank Math meta alanlarına da senkronize edilir (biri aktifse), hiçbiri aktif değilse tema kendi `<title>` ve meta description çıktısını verir.

## Ön Yüz Materyal Üretici — `[ewt_generator]` Kısa Kodu (v1.2.0)
Ziyaretçilerin (öğrenci/veli/öğretmen) konu ve seviye seçerek anında **Çalışma Kağıdı, Sınav, Ders Planı veya Quiz** üretebildiği, herhangi bir sayfaya/Elementor Kısa Kod widget'ına eklenebilen ücretsiz araç.

**Temel kullanım:**
```
[ewt_generator]
```
Bu, 4 materyal türünün tümünü ve genel A1-C2 seviye skalasını gösteren bir form basar.

**Parametreler (hepsi isteğe bağlı):**
- `cpt="ewt_toefl"` — Seviye listesini o CPT'nin zorluk skalasına göre gösterir (örn. TOEFL sayfasına koyarsanız "Hedef Skor 60-80/80-100/100+" seçenekleri çıkar; boş bırakılırsa tam A1-C2 skalası kullanılır). Herhangi bir CPT slug'ı kullanılabilir (`ewt_primary`, `ewt_secondary`, `ewt_toefl`, vb. — bkz. `inc/cpt-taxonomies.php` içindeki `ewt_cpt_config()`).
- `types="worksheet,quiz"` — Sadece belirtilen materyal türlerini gösterir. Geçerli değerler: `worksheet`, `exam`, `lesson_plan`, `quiz`. Boş/geçersizse tümü gösterilir.
- `title="TOEFL Pratik Materyali Oluştur"` — Formun başlığını özelleştirir.

**Örnek:** TOEFL arşiv sayfasına sadece sınav ve quiz üretebilen, TOEFL skorlarına göre seviyelendirilmiş bir araç koymak için:
```
[ewt_generator cpt="ewt_toefl" types="exam,quiz" title="TOEFL Pratik Sınavı Oluştur"]
```

**Nasıl çalışır / maliyet ve güvenlik:**
- AI sağlayıcısı ziyaretçiye sorulmaz — Ayarlar'da seçtiğiniz "Varsayılan AI Sağlayıcı" kullanılır (maliyet kontrolü sizde kalır).
- **Görünüm > English Web Ayarları > Ön Yüz Materyal Üretici** bölümünden: aracı tek tuşla kapatabilir, IP başına saatlik üretim limitini (varsayılan 6) değiştirebilirsiniz.
- Aynı konu+seviye+tür 7 gün içinde tekrar istenirse API'ye gitmeden anında önbellekten döner (maliyet tasarrufu).
- Basit bot koruması (gizli alan + minimum doldurma süresi) ve HTML çıkışının sunucu tarafında temizlenmesi (`wp_kses_post`) dahildir.
- Üretilen çalışma kağıdı/sınav sonunda "Cevap Anahtarı" varsa, öğrenciler istemeden görmesin diye otomatik olarak katlanır/gizlenir (tıklayınca açılır).
- Quiz türü interaktif olarak render edilir (seçilen şıkkı işaretle, "Puanla" butonuna bas, doğru/yanlış anında renklenir).
- Sonuç panelinde "🖨️ Yazdır / PDF Kaydet" butonu, tarayıcının yazdırma diyaloğunu (Farklı Kaydet > PDF) açar; sadece üretilen materyal yazdırılır, site menüsü/formu gizlenir.
- Her sonucun altında, ayarladığınız telefon numarasına giden bir "WhatsApp'tan Özel Ders Sor" CTA'sı otomatik eklenir — bu araç aynı zamanda ozeldersal.tr/özel ders için bir lead-üretim kanalıdır.

## Toplu (Bulk) Araçlar — Dashboard'da "AI Araçları" menüsü
Sol menüde 🦸 **AI Araçları** başlığı altında iki sayfa bulunur:

1. **Toplu İçerik Üretimi (v2)**:
   - Akış artık **"önce CPT seç"** mantığındadır: en üstte hedef içerik türünü seçersiniz, altında o türün Yazılar menüsünden **önceden oluşturduğunuz gerçek "Seviye" terimleri** bilgi amaçlı chip olarak listelenir.
   - **Yöntem A (Basit Liste):** Bir kutuya alt alta Focus Keyword listesi yapıştırırsınız (1000+ satır desteklenir); tüm satırlar seçtiğiniz CPT'yi kullanır.
   - **Yöntem B (CSV — "nokta atışı"):** `keyword,cpt,seviye,kelime_sayisi` sütunlu bir CSV yüklersiniz; her satır kendi CPT'sini ve kelime sayısını ayrı ayrı belirtebilir.
   - **Otomatik Seviye Tespiti:** `seviye` hücresini (veya Basit Liste'de tamamen) boş bırakırsanız, AI o CPT'nin **gerçek, dashboard'da oluşturulmuş** seviye terimleri arasından anahtar kelimeye en uygun olanı seçer ve yazıyı otomatik o terime atar (bkz. `ewt_content_user_prompt_auto_seviye()` / `ewt_extract_seviye_marker()` — `inc/ai-content-widget.php`). Bir CPT için henüz hiç seviye terimi oluşturmadıysanız, sistem sayfadaki sabit "Seviye Boşsa Yedek Zorluk Derecesi" ayarına otomatik döner (eski davranışla aynı).
   - Seviye hücresini elle doldurursanız (gerçek bir terim adı/slug'ı ya da sabit zorluk kodu), o değer doğrudan kullanılır; gerçek bir terimle eşleşirse yazı o terime de atanır.
   - **Gövde İçi Ek Görsel (Pexels):** Featured image'a **ek olarak**, üretilen içerikteki `<h2>` başlıklarının hemen altına 0-4 adet, o bölümün konusuna göre alakalı Pexels görseli otomatik gömülür (fotoğrafçı atıfıyla; bkz. `ewt_insert_inline_pexels_images()` — `inc/featured-image-widget.php`).
   - **Eşzamanlı işlem:** İşlemler artık tek tek değil, ayarlanabilir sayıda (varsayılan 4) istek **aynı anda** gönderilerek yürütülür; bu, 1000 satırlık bir listeyi eski sürüme kıyasla kat kat hızlandırır. Bağlantı hatası olan istekler 1 kez otomatik yeniden denenir. "Durdur" butonuyla iş her an durdurulabilir, tamamlanan satırlar "Sonuçları CSV İndir" ile dışa aktarılabilir.
2. **Toplu Görsel Atama**: Seçtiğiniz içerik türünde öne çıkan görseli olmayan yazıları tarar, ardından her biri için (yazı başlığına göre) Pexels'ten stok fotoğraf arar veya OpenAI ile özgün görsel üretir ve otomatik olarak öne çıkan görsel yapar. Bu araç da aynı **eşzamanlı işlem motorunu** kullanır (ayarlanabilir eşzamanlılık + CSV export). **"👶 Çocuklar için nesne görseli" modu** işaretlenirse, arama/üretim sorgusu okul öncesi/ilkokul kelime kartları için uygun, izole/beyaz arkaplanlı, net obje-hayvan-renk fotoğrafına dönüştürülür.

**Not:** Pexels için ücretsiz bir API anahtarı [pexels.com/api](https://www.pexels.com/api/) adresinden alınabilir ve Görünüm > English Web Ayarları'na girilir. Görsel için başka bir stok fotoğraf sağlayıcısı (Unsplash, Pixabay vb.) eklemek isterseniz `inc/featured-image-widget.php` içindeki `ewt_fetch_pexels_image()` fonksiyonunun yanına benzer bir fonksiyon ekleyip `ewt_assign_featured_image()` içindeki switch mantığına dahil etmeniz yeterlidir.

## Tekil Yazı Editöründe Zorluk Derecesi, Kelime Sayısı ve Çocuk-Nesne Görseli
Bulk araçlardaki bu üç özellik, tekil yazı editöründeki widget'larda da mevcuttur:
- **🤖 AI İçerik Oluştur** kutusunda artık düzenlemekte olduğunuz CPT'ye özel bir "Konu Anlatım Zorluk Derecesi" seçimi ve "Hedef Kelime Sayısı" alanı bulunur.
- **🖼️ Otomatik Öne Çıkan Görsel** kutusunda "👶 Çocuklar için nesne görseli" onay kutusu bulunur; işaretlendiğinde arama/üretim sorgusu izole obje fotoğrafına dönüştürülür.

## Blog Bölümünü Anasayfadan Ayırma (Kenar Çubuklu)
Artık standart WordPress mekanizmasıyla, özel tasarımlı anasayfanızı normal blog listesinden (kenar çubuklu) tamamen ayırabilirsiniz:

1. **Ayarlar > Okuma**'ya gidin.
2. "Anasayfanızda gösterilecek içerik" için **"Statik bir sayfa"**'yı seçin.
3. **Anasayfa**: Herhangi bir sayfayı seçin (içeriği önemli değil — tema, site anasayfasında her zaman özel tasarımlı `front-page.php`'yi gösterir).
4. **Yazılar Sayfası**: Ayrı bir sayfa oluşturup (örn. "Blog") onu seçin. Bu sayfa artık **kenar çubuklu** blog listesi (`home.php`) olarak görünür.
5. **Görünüm > Widget'lar > "Blog Kenar Çubuğu"** alanına istediğiniz widget'ları (Ara, Son Yazılar, Kategoriler, reklam, vb.) ekleyin. Widget eklemezseniz, tema otomatik olarak bir arama kutusu + son yazılar + kategoriler + özel ders CTA'sı gösterir.

Bu kenar çubuğu **sadece normal blog bölümünde** (blog listesi, tekil blog yazıları, kategori/etiket/yazar/tarih arşivleri) görünür. Ders (CPT) sayfaları ve onların Seviye arşivleri bilinçli olarak **tam genişlik** kalır — böylece öğrenciler ders içeriğinde dikkat dağıtıcı bir kenar çubuğu görmez.

## İçindekiler (TOC) — Her Yazıda Otomatik
Her tekil yazı/ders sayfasında (2 veya daha fazla `<h2>`/`<h3>` başlığı varsa), içerik başlamadan önce otomatik olarak açılır/kapanır bir "📑 İçindekiler" kutusu belirir. Başlıklara otomatik olarak çapa (anchor) ID'leri eklenir ve TOC'taki her madde ilgili başlığa atlar. Kurulum gerektirmez; AI içerik widget'ları `<h2>`/`<h3>` kullandığı için üretilen içerikler otomatik olarak TOC alır. 2'den az başlığı olan kısa içeriklerde TOC gösterilmez (gereksiz olur).

## Mega Menü Düzeltmesi (v1.3)
Önceki sürümde, mega menüde **2. seviye** menü öğeleri (yani menünüzü sadece 2 seviyeli kurduysanız — çoğu kullanım şekli budur) yanlışlıkla tıklanamaz bir `<h4>` başlık olarak render ediliyordu; öğenin gerçek linki tamamen görmezden geliniyordu. Bu düzeltildi: artık 2. seviye öğeler her zaman gerçek, tıklanabilir bir `<a>` linkidir (aynı zamanda görsel olarak sütun başlığı gibi görünür). Menünüzde 3. seviye eklediyseniz (Top > Sütun Başlığı > Link), sütun başlığı hem kendi linkine gider hem de altında bağlantı listesini gösterir.

## Gelişmiş Site İçi Arama Motoru (v1.4)
Header'da sağ üstte beliren 🔍 ikonuna tıklandığında (veya `Ctrl/Cmd + K` kısayoluyla) açılan, ziyaretçi birkaç harf yazar yazmaz sonuçları anında dolduran Google/Algolia tarzı bir arama kutusu.

**Neden ayrı bir indeks tablosu?** 1.000-10.000 arası içerikte, her tuş vuruşunda doğrudan `wp_posts` üzerinde `LIKE` araması yapmak zamanla yavaşlar ve alaka düzeyine göre sıralama yapamaz. Bunun yerine tema, kendi hafif tablosunu (`{prefix}ewt_search_index`) tutar; başlık, düz metin içerik, özet, Seviye adı, CPT etiketi ve öne çıkan görsel burada önceden hazır bekler. Bir yazı yayınlandığında/düzenlendiğinde/silindiğinde ya da Seviye ataması değiştiğinde bu tablo otomatik güncellenir — elle bir şey yapmanız gerekmez.

**Nasıl çalışır:**
- Ön yüzde: `assets/js/site-search.js` her tuş vuruşunda (250ms gecikmeli) `GET /wp-json/ewt/v1/search?q=...` REST endpoint'ine sorar, gelen sonuçları (görsel, başlık, CPT/Seviye rozeti, eşleşen kelimenin **kalın** vurgulandığı kısa alıntı ile) açılır listede gösterir. Ok tuşları + Enter ile klavyeden gezinilebilir.
- "Tüm sonuçları gör" bağlantısı veya doğrudan `?s=kelime` ile ziyaret edilen tam sonuç sayfası (`search.php`) da **aynı** indeksi ve aynı alaka sıralamasını kullanır — açılır listeyle tam sayfa arasında tutarlı sonuçlar sağlar.
- Sıralama: başlığın **başında** geçen eşleşmeler en üstte, başlıkta kelime başında geçenler ikinci, başlıkta herhangi bir yerde/Seviye adında geçenler üçüncü, sadece içerik metninde geçenler en sonda çıkar.

**Yönetim (sol menüde "🔍 Site Arama"):**
- İndekslenmiş/toplam içerik sayısı istatistiği.
- **"İndeksi Yeniden Oluştur"** butonu: tabloyu temizleyip tüm yayınlanmış içeriği 100'erlik gruplar halinde yeniden tarar (zaman aşımı olmadan 10.000 içeriği bile işleyebilir); ilk kurulumdan sonra veya toplu içe aktarımlardan sonra bir kez çalıştırmanız yeterlidir.
- Ayarlar: minimum karakter sayısı (varsayılan 2), açılır listede gösterilecek sonuç sayısı (varsayılan 8) ve hangi içerik türlerinin (CPT/Yazı/Sayfa) indeksleneceği.

**Genişletme notu:** İçerik sayınız ileride 50.000'i aştığında (şu anki 1.000-10.000 hedefinin çok üzerinde), bu tablo tabanlı yaklaşım yerine Algolia/Elasticsearch gibi harici bir arama servisine geçmek isteyebilirsiniz; ancak mevcut ve öngörülen ölçeğinizde bu çözüm rahatlıkla milisaniyeler içinde yanıt verir.

## İç/Dış Bağlantı Yöneticisi — Link Juice Motoru (v1.5)
Her içeriğe otomatik olarak **en az bir iç bağlantı**, alakalı **yüksek otoriteli dış kaynaklara** bağlantı ve — en önemlisi — **https://english.web.tr/ingilizce-ozel-ders/** sayfanıza sürekli link juice akıtan bir sistem. Tüm bağlantılar sıradan `<a>` etiketi olarak post_content'e (veya Elementor kullanıyorsanız ilgili Metin Editörü widget'ına) yazılır; bu yüzden Gutenberg'de veya Elementor'da açtığınızda tamamen normal, elle düzenlenebilir/silinebilir metin/link olarak görünür.

**Nasıl çalışır:**
- Sol menüde **"🔗 Bağlantı Yöneticisi"** altında üç sayfa: **Bağlantı Kuralları**, **Toplu Uygula**, **Ayarlar**.
- **Bağlantı Kuralları**: Her kural = bir grup çapa metin varyasyonu (Türkçe İ/I/ı/i farkları otomatik eşleşir) + hedef URL + (opsiyonel) hangi içerik türlerinde geçerli olacağı. İlk kurulumda otomatik tohumlanır: 1 adet "İngilizce Özel Ders" kuralı + 10 adet güvenilir dış kaynak (Cambridge Dictionary, Oxford Learner's Dictionaries, British Council, BBC Learning English, Purdue OWL, ETS TOEFL/GRE, ÖSYM, mba.com, Merriam-Webster). Tamamı düzenlenebilir/silinebilir/eklenebilir — PR/DR değerleri canlı sorgulanmaz, bunlar herkesçe bilinen köklü eğitim otoriteleridir.
- Ayrıca site içi arama indeksini (`wp_ewt_search_index`) kullanarak, içerikte GEÇEN başka içerik başlıklarını **otomatik** iç bağlantı adayı olarak bulur — elle kural yazmanıza gerek kalmadan.
- Yazı düzenleme ekranında **"🔗 İç/Dış Bağlantı Önerileri"** meta kutusu: önce "Analiz Et" ile önerileri görün, istediklerinizi işaretleyip "İçeriğe Ekle"ye basın. "Hepsini Doğrudan Uygula" ile tek tıkla da uygulayabilirsiniz.
- **Toplu Uygula** sayfasından, seçtiğiniz içerik türlerindeki (veya tüm) yayınlanmış içeriklere tek seferde uygulayabilirsiniz — önce "Önizleme" modunda ne kadar bağlantı ekleneceğini görebilirsiniz.
- Doğal bir çapa metin bulunamazsa (örn. çok kısa bir içerikte), özel ders sayfasına yine de link verilmesi için içerik sonuna kısa bir cümle + link otomatik eklenir (Ayarlar'dan kapatılabilir/metni değiştirilebilir).
- **Güvenlik:** Değişiklikler güvenli bir DOM ayrıştırıcı ile yapılır (mevcut linkler, başlıklar, kod blokları asla dokunulmaz) ve her değişiklik sonrası basit bir metin uzunluğu kontrolü yapılır; bir şeyler ters giderse o içerik dokunulmadan bırakılır — yayınlanmış, Google'da sıralanan sayfalarınız risk altına girmez.
- **Yayınlandığında otomatik uygula** ayarı varsayılan **kapalıdır**; isterseniz Ayarlar'dan açabilirsiniz.

### Site Geneli Çapraz Linkleme (v1.5.1) — Konu/Seviye/CPT Bazlı Akıllı Eşleştirme
Otomatik iç bağlantı keşfi artık hedef yazının **tam başlığının birebir metinde geçmesini** şart koşmuyor — bu, önceki sürümde çoğu alakalı içeriğin birbirine hiç bağlanmamasına neden oluyordu. Yeni mantık:

1. **Aday havuzu daraltılır**: önce aynı içerik türünden (CPT) yazılara, sonra (bu yazının bir Seviyesi varsa) **farklı CPT'den ama aynı Seviye'deki** yazılara bakılır — örn. bir A1 Vocabulary yazısı, diğer A1 Vocabulary yazılarına VE A1 Grammar/Listening/Reading yazılarına aday olarak bakar; alakasız bir C1 GRE yazısına bakmaz.
2. **Üç çapa varyasyonu denenir**: (a) SEO meta kutusundaki odak anahtar kelime, (b) başlıktan "a1/b2/pdf/test/quiz/9.sınıf" gibi seviye-format dolgusu kırpılmış **çekirdek konu ifadesi**, (c) tam başlık. Bunlardan hangisi mevcut içerikte doğal olarak geçiyorsa o kullanılır.
3. Sonuç: "B2 Seviyesi İngilizce Kelimeler" başlıklı bir yazı, sadece "ingilizce kelimeler" ifadesi geçen "A1 Seviyesi İngilizce Kelimeler" yazısına da otomatik bağlanabilir — başlıklar birebir aynı olmasa bile.
4. Alaka sıralaması: **aynı CPT + aynı Seviye** > aynı Seviye (farklı CPT) > aynı CPT (farklı/yok Seviye) — en alakalı adaylar önce denenir.

Bu özellik, "Otomatik İç Bağlantı Keşfi" ayarı zaten açıksa (varsayılan: açık) ek bir kurulum gerektirmeden çalışır.

### Toplu Kural Ekle — CSV Yapıştırarak (v1.5.2)
**Bağlantı Kuralları** sayfasında, tek tek kural eklemek yerine birden fazla kuralı (örn. onlarca dış kaynak sitesi ya da iç hedef sayfa) **tek seferde** ekleyebilirsiniz. Sayfanın altındaki "📥 Toplu Kural Ekle" kutusuna, her satırı bir kural olacak şekilde şu sütun sırasıyla yapıştırın:

```
tür, etiket, çapa metinleri, hedef url, kapsam, nofollow, yeni_sekme, öncelik
```

- **çapa metinleri** ve **kapsam** hücrelerinde birden fazla değeri **`|`** (dikey çizgi) ile ayırın (örn. `ewt_toefl|ewt_gre`).
- **kapsam**'da hem CPT slug'ı (`ewt_toefl`) hem görünen ad (`TOEFL`) kabul edilir.
- Başlık satırı (`tür,etiket,...`) eklerseniz otomatik atlanır — istemezseniz hiç eklemeyebilirsiniz.
- Etiketi, hedef URL'si veya çapa metni boş/geçersiz olan satırlar sessizce atlanır; ekleme sonunda "X kural eklendi, Y satır atlandı" şeklinde özet gösterilir.

## Dinleme &amp; Konuşma Laboratuvarı (v1.6.0) — [ewt_listening_lab]
Ziyaretçinin/öğrencinin/öğretmenin 4 adımda tam bir dinleme-konuşma çalışması yaptığı, herkese açık interaktif araç:

1. **Oluştur** — Seviye (A1-C2) + konu seçilir; AI, seviyeye uygun uzunlukta (A1: ~70-100, C2: ~300-350 kelime) özgün bir İngilizce dinleme parçası + anlama testi + konuşma çalışmasını **tek API çağrısında** üretir.
2. **🎧 Dinle** — Parça, tarayıcının **ücretsiz Web Speech API**'siyle sesli okunur (ek API maliyeti YOKTUR). Oynat/duraklat/baştan, 4 kademeli hız (0.7x-1.15x), dinleme sayacı. Metin başlangıçta bulanıktır ("Metni Göster" ile açılır) — gerçek dinleme pratiği için.
3. **📝 Test** — Sadece parçadan cevaplanabilen çoktan seçmeli anlama testi (soru sayısı Ayarlar'dan 3-10 arası), Türkçe açıklamalı anında puanlama. Puanlamadan sonra konuşma adımı açılır.
4. **🎤 Konuş** — Isınma soruları, tartışma soruları, rol yapma senaryosu ve parçadan birebir alınmış **shadowing cümleleri** (her cümlenin yanında 🔊 dinleme butonu). Ayrıca **mikrofonla kendini kaydet &amp; dinle** özelliği (MediaRecorder — kayıt cihazda kalır, hiçbir sunucuya gönderilmez, maks. 3 dk).

**Otomatik yerleştirme:** Listening ve Speaking derslerinin (CPT) tekil sayfalarında içeriğin altına otomatik eklenir. Dersin seviyesi taxonomy teriminden (örn. `a1-seviyesi-listening` → A1) otomatik algılanıp önceden seçili gelir; ders başlığı konu alanına önerilir. İçerikte elle `[ewt_listening_lab]` varsa ikinci kez eklenmez. **Görünüm > English Web Ayarları**'ndan hem tamamen hem de sadece otomatik yerleştirme ayrı ayrı kapatılabilir.

**Kısa kod kullanımı (istediğiniz her yerde — sayfa, yazı, Elementor "Kısa Kod" widget'ı):**

```
[ewt_listening_lab]
[ewt_listening_lab level="b1" topic="daily routines"]
[ewt_listening_lab mode="speaking" title="Konuşma Pratiği Başlat"]
```

- `level` — a1, a2, b1, b2, c1, c2 (önceden seçili gelir; boşsa ziyaretçi seçer)
- `topic` — konu alanını önceden doldurur
- `mode` — `listening` (varsayılan) veya `speaking` (başlık/vurgu değişir, akış aynıdır)
- `title` — kutu başlığını özelleştirir

**Güvenlik &amp; maliyet:** Ön Yüz Materyal Üretici ile **ortak saatlik IP limiti**, honeypot + minimum doldurma süresi, nonce, aynı seviye+konu için 7 günlük önbellek. AI çıktısı sunucuda doğrulanıp temizlenir ve tarayıcıda yalnızca düz metin olarak (`textContent`) basılır. Sesli okuma ve mikrofon kaydı tamamen tarayıcıda çalıştığı için **ek API maliyeti yoktur** — tek maliyet, adım 1'deki üretim çağrısıdır.

### Ana Sayfa Entegrasyonu (v1.6.1)
Laboratuvar, ana sayfada (front-page.php) **"Eğitim Kademeleri" kartlarının hemen altında** kendi bölümüyle yer alır; hero'daki **"🎧 Ücretsiz Dinleme Pratiği Yap"** butonu bu bölüme (`#ewt-lab-home`) kaydırır. Ayarlar'dan laboratuvar kapatılırsa ana sayfadaki bölüm de otomatik gizlenir (boş kutu veya "kullanılamıyor" mesajı görünmez). Not: Ana sayfadaki saatlik üretim limiti diğer yerlerle ortaktır — ana sayfa trafiği yüksek olduğu için limiti Ayarlar'dan ihtiyaca göre ayarlayabilirsiniz.

## Chatbot
Sağ altta sabit bir chatbot ikonu belirir. Kural tabanlıdır (ekstra API maliyeti yoktur) ve "Özel ders almak istiyorum" seçeneğinde ziyaretçiyi **Görünüm > English Web Ayarları**'nda tanımladığınız telefon numarasına (arama + WhatsApp) yönlendirir.

## Sorumluluk / Yapılacaklar
- İlk kurulumda gerçek bir `screenshot.png` (880x660) eklemeniz önerilir (tema listesinde görünür, işlevsel değildir).
- AI API çağrıları sunucu tarafında (`admin-ajax.php`) yapılır; anahtarlar tarayıcıya hiçbir zaman gönderilmez.
- Üretilen tüm AI içerik ve görselleri yayınlamadan önce pedagojik ve SEO açısından gözden geçirin.

## URL Dışa Aktarma (v1.6.2)
**AI Araçları > URL Dışa Aktarma** menüsünden, sitedeki yayınlanmış tüm içeriklerin URL'lerini kategorize biçimde dışa aktarabilirsiniz. Toplu üretilen binlerce içeriği hızlı indexleme araçlarına göndermek için tasarlandı.

- **İçerik sayısı özeti:** Sayfanın üstünde her içerik türünün yayınlanmış/taslak sayıları ve tek tıkla "CSV indir" butonu bulunur.
- **CSV sütunları:** `url, baslik, icerik_turu, seviye, yayin_tarihi, guncelleme_tarihi` — seviye sütununda ilgili taksonomi terim slug'ları `|` ile ayrılır. Excel uyumu için UTF-8 BOM ile iner.
- **TXT formatı:** Satır başına yalnızca 1 URL — Google Indexing API scriptleri, SpeedyIndex, IndexNow tarzı araçların beklediği format.
- **Filtreler:** İçerik türü (tümü veya tek CPT), seviye slug'ı, yayın tarih aralığı (ör. yalnızca son 1 haftada eklenenler).
- **Parçalama:** "Başlangıç / Maks. URL" alanlarıyla 10.000'lik partiler hâlinde indirebilirsiniz (indexleme araçlarının parti limitleri için).
- **Arşiv URL'leri:** İsteğe bağlı olarak CPT arşivleri + seviye arşiv sayfaları da eklenir (`icerik_turu = arsiv`).
- **Performans:** Dosya sunucuda oluşturulmaz, 500'lük partiler hâlinde tarayıcıya akış olarak gönderilir; 50.000+ URL'de bile bellek sabit kalır.

## Taslak Yönetimi (v1.6.3)
**AI Araçları > Taslak Yönetimi** menüsü, Toplu İçerik Üretimi'nin "Yayın Durumu" alanı varsayılan olarak **Taslak** olduğu için ortaya çıkabilecek bir riski çözer: binlerce içerik üretilip hiç yayınlanmadan draft'ta birikebilir — ve Rank Math sitemap'i yalnızca `publish` durumundaki içerikleri listeler, dolayısıyla bu içerikler Google'a hiç bildirilmez.

- **Teşhis tablosu:** Her içerik türü için Yayınlanmış / Taslak sayısını yan yana gösterir, taslak sayısı 0'dan büyükse kırmızı vurgulanır.
- **Toplu yayınlama:** Seçilen içerik türündeki (veya "Tümü" ile sırayla tüm türlerdeki) taslakları, isteğe bağlı tarih aralığı ve seviye filtresiyle tek tıkla yayınlar.
- **Zaman aşımı korumalı:** İşlem tarayıcıdan partiler hâlinde (varsayılan 200/istek, ayarlanabilir) çalışır; binlerce taslakta bile sunucu zaman aşımına takılmaz. Canlı ilerleme çubuğu ve log gösterir, istenirse durdurulabilir.
- **Kazara toplu yayın riskine karşı** onay kutusu zorunludur.
- **İleriye dönük:** Toplu İçerik Üretimi'nde her çalıştırmada "Yayın Durumu" alanını elle "Doğrudan Yayınla" seçerek bu birikimi baştan önleyebilirsiniz; varsayılan "Taslak" bilinçli bir güvenlik seçimidir (önce gözden geçirme), değiştirilmedi.

## Yeni CPT'ler ve Seviyeler (v1.6.4)
K12 sonrası (üniversite/lisansüstü/kariyer) içerik boşluklarını kapatmak için 3 yeni CPT eklendi:

- **IELTS** (`ewt_ielts`)
- **YÖKDİL** (`ewt_yokdil`)
- **Mesleki İngilizce** (`ewt_mesleki`) — Business/Hukuk/Tıp/Mühendislik/BT İngilizcesi + iş görüşmesi/kariyer

Bu 3 yeni CPT'nin ve mevcut **Lise** ile **Writing/Reading/Listening/Speaking** CPT'lerine eklenen K12-sonrası seviye terimleri **otomatik olarak** kurulur — tema güncellendikten sonra ilk admin sayfa yüklemesinde, eksik olan terimler sessizce oluşturulur (var olan hiçbir terime dokunulmaz). Elle CSV import etmenize gerek yok.

Tam liste, referans amaçlı `yeni-seviye-terimleri.csv` dosyasında da mevcut (teslim edilen dosyalar arasında).

**Kurulumdan sonra unutmayın:** Yeni CPT'ler rewrite kurallarını değiştirdiği için **Ayarlar > Kalıcı Bağlantılar > Değişiklikleri Kaydet** yapın (hiçbir şeyi değiştirmeden kaydetmeniz yeterli — rewrite cache'i yeniler). Ayrıca yeni CPT'leri **Rank Math > Sitemap Ayarları**'nda "Include in Sitemap" ile açmayı ve mega menüye eklemeyi unutmayın.
