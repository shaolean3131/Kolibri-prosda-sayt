# Kolibri — proje devir notu (Claude için)

Bu dosya, projeye yeni başlayan bir Claude oturumunun (ör. yerel Claude Code)
her şeyi baştan sormadan devam edebilmesi için yazıldı. Önce bunu oku.

## Kullanıcı ve iletişim

- Kullanıcı **Türkçe** konuşuyor → cevaplar Türkçe, kısa ve anlaşılır olsun
  (teknik terimleri gerekirse açıkla, kullanıcı geliştirici değil).
- Site ve admin paneli **Rusça** (müşteriler Rusya'da). Arayüz metinleri Rusça kalmalı.
- Dükkân: **«Колибри — студия цветов»**, Novokuznetsk (Rusya), çiçekçi.
  - Adresler: **Новокузнецк, Тореза 42а/1** ve **Новокузнецк, Советской Армии 2а/2**, ikisi de круглосуточно (24 saat).
  - Canlı site: https://kolibri-flowers.ru/ (şu an Foodpicasso adlı ücretli SaaS'ta).
  - 2GIS sayfaları: https://2gis.ru/novokuznetsk/firm/70000001078846605 ve https://2gis.ru/novokuznetsk/firm/70000001110444041
- **Amaç:** Foodpicasso'ya ödeme yapmayı bırakıp aynı sistemi kendi PHP sunucusunda çalıştırmak.
- Kullanıcının kararları:
  - Online ödeme **yok** (nakit / kartla teslimde / СБП havalesi).
  - Eski verileri aktarma **gerekmiyor** (ürünler panelden elle girilecek).
  - Müşteri girişi (SMS) **sonra** yapılacak.
  - Siparişler **Telegram botuna** gelmeli (yapıldı).
  - «Ana ekrana ekle» ile **gerçek uygulama hissi** çok önemli (yapıldı, aşağıya bak).
  - Animasyonlar ve mobil kullanım kolaylığı onun için en önemli şeyler.
- Kullanıcının bilgisayarında fotoğraflar ve logo var:
  `C:\Users\shaolean\Desktop\Desktop\Kolibri` — logo dosyası **Screenshot_2**.
  Önceki (bulut) oturum buna erişemedi; **yerel oturumda bu klasörü kullanabilirsin.**

## Depo ve dal

- GitHub: `shaolean3131/Kolibri-prosda-sayt`
- Çalışma dalı: **`claude/kolibri-flowers-design-kg7uv9`** (tüm iş burada; main henüz boş olabilir).
- Commit mesajları İngilizce, açıklayıcı yazıldı.

## Mimari (framework yok, düz PHP 8.1+)

```
index.php            Vitrin: katalog, ürün penceresi, sepet paneli, sipariş formu
page.php             İçerik sayfaları: ?p=promotions | privacy | terms
api/cart.php         Sepeti sunucuda fiyatlar (promosyon, teslimat, limitler)
api/order.php        Siparişi kaydeder, cevaptan SONRA Telegram'a yollar
site/layout.php      Vitrin şablonları: head, header, footer, ikonlar, kolibri SVG
assets/site.css|js   Vitrin stil ve davranışı (tek dosya, vanilla JS)
assets/icons, assets/splash   PWA ikonları ve iPhone açılış görselleri (Chromium ile üretildi)
manifest.webmanifest, sw.js, offline.html   PWA

admin/index.php      Panel yönlendirici (?p=dashboard|catalog|clients|orders|promotions|settings)
admin/login.php      Giriş + ilk açılışta sahip hesabı oluşturma
admin/core/          Paylaşılan çekirdek (vitrin de bunu kullanır!)
  bootstrap.php      config, oturum (KOLIBRI_SITE tanımlıysa vitrin oturumu), db(), auth, CSRF,
                     setting()/save_setting(), store_image(), UserError
  schema.php         Tablolar; SCHEMA_VERSION değişince eksik tablo/sütunlar otomatik eklenir
  catalog.php        Ürün/kategori yardımcıları, ürün "extra" bölümleri (JSON)
  orders.php         Fiyatlama, promosyon motoru, place_order(), çalışma saati, Telegram
  promotions.php, clients.php, filters.php (ortak filtre çipleri), settings.php (ayar şeması),
  menu.php (yan menü), layout.php (panel kabuğu), icons.php, stats.php (dashboard)
admin/pages/*.php    Ekran görünümleri;  admin/api/*.php  JSON uç noktaları (POST + CSRF)
admin/assets/js/ui.js  Ortak UI: api(), toast, modal, confirm, dropzone (tarayıcıda resim küçültme)
admin/tools/seed-demo.php  Sadece CLI: deneme verisi (canlı veritabanında ÇALIŞTIRMA)
config/config.sample.php   config.php olarak kopyalanır (git'e girmez). Varsayılan SQLite.
storage/  SQLite veritabanı;  uploads/  yüklenen fotoğraflar (script çalıştırma kapalı)
designs/  3 animasyonlu tasarım konsepti (aşağıya bak)
```

Önemli kurallar / tuzaklar:
- Saat dilimi **Asia/Novokuznetsk** (UTC+7). Ön sipariş saatleri tarayıcı saatine değil,
  sunucunun verdiği dükkân saatine (`config.now`) göre hesaplanıyor — bunu bozma.
- SQLite'ta PDO tüm parametreleri string bağlar: `COUNT(...) >= '3'` hiç eşleşmez.
  Sayısal filtreler `filter_range_sql()` ile doğrulanıp satır içine yazılıyor.
- Panel ve vitrin oturum çerezleri ayrı (`kolibri_admin` / `kolibri_site`).
- Tüm yazan API'ler `api_guard()` (admin + POST + CSRF) kullanıyor; vitrin API'leri `csrf_valid()`.
- Sipariş güvenliği: fiyatlar her zaman sunucuda yeniden hesaplanır, honeypot alanı,
  telefon başına 5 / IP başına 20 sipariş / 10 dk sınırı.
- Vitrin fiyat biçimi binlik ayraçsız: `2500 ₽` (orijinal site böyle). Panel bazı yerlerde ayraçlı.
- Arayüz metinleri Rusça; kod yorumları İngilizce ve az.

## Yapılanlar (özet)

1. **Admin paneli** — Foodpicasso ekran görüntülerine göre birebir: Дашборд (SVG grafikler),
   Каталог → Основное (kategori/ürün, fotoğraf, etiketler, açılır ayar bölümleri, sıralama, Ctrl K arama),
   Клиенты (filtreler, gizli telefon, CSV), Заказы (filtreler, sipariş penceresi, durum adımları,
   20 sn'de bir yeni sipariş kontrolü + ses), Акции и скидки (baner, süre, tür, promosyon kodları,
   ayarlar), Настройки (Главные, Формы оплаты, Предзаказы, Точки и зоны доставки, Время работы,
   Уведомления/Telegram, Юр. информация, Сниппеты — yazdıkça kaydeder).
   Foodpicasso'ya özel şeyler (Биллинг, Vincento, Posterix, kilitler) bilerek çıkarıldı.
2. **Vitrin** — orijinal sitenin düzeni: cam efektli başlık (kaydırınca kategori şeridine döner),
   ürün penceresi, «Мой заказ» paneli, Доставка/Самовывоз penceresi (Yandex harita iframe),
   promosyon kodu, ön sipariş, başarı ekranı (yaprak animasyonu), arama, iletişim.
3. **PWA / uygulama modu** (`html.app` sınıfı): alt sekme çubuğu, Android geri tuşu pencereyi kapatır
   (history katmanları — `site.js` "Layers" bölümü), iPhone açılış görselleri (11 boyut), aşağı çekip
   yenileme, zoom/seçim kapalı, çevrimdışı açılış (sw.js v2), kısayollar, iPhone kurulum talimatı.
4. **Telegram** — her yeni sipariş HTML mesajı olarak gider; Настройки → Еще → Уведомления'dan kurulur.
5. **Tasarım konseptleri** (`designs/`, sadece statik tasarım, siteye bağlı değil):
   - `1-botanika.html` — editoryal, krem + serif, yatay galeri, kelime kelime koyulaşan metin
   - `2-nochnoy-sad.html` — marka lacivert/krem, kendini çizen ve kaydırınca uçan kolibri
   - `3-pastel.html` — pastel bento, tam ekrana büyüyen fotoğraf, üst üste binen kartlar
   - Hepsinde mobil menü, alttan çıkan «Заказать букет» butonu, `img/logo.png` logo yuvası.
   - Fotoğraf yuvaları: `designs/img/1.jpg … 10.jpg` (yoksa yumuşak yer tutucu görünür).
     Numaraların anlamı `designs/img/README.txt` içinde.

## Sıradaki işler (kullanıcının son isteği)

1. **Kullanıcının yerel klasöründeki fotoğrafları ve logoyu tasarımlara yerleştir:**
   `C:\Users\shaolean\Desktop\Desktop\Kolibri` → uygun olanları `designs/img/` içine
   `logo.png`, `1.jpg … 10.jpg` olarak kopyala (büyükse ~1600 px'e küçült).
   Logodaki kolibri ikonunu kullanarak, benim çizdiğim çizgi kolibrilerin
   (açılış animasyonu, «Ночной сад»daki büyük ve uçan kolibri) yerine koymayı değerlendir.
2. Kullanıcı 3 konseptten birini (veya karışımını) seçince, onu **gerçek vitrine** dönüştür:
   mevcut katalog / sepet / sipariş / PWA mantığını (`index.php`, `assets/site.js`) koruyarak.
3. Tasarımlarda örnek olan rakam/yorumları gerçek bilgilerle değiştir (2GIS yorumları vb.).
4. Daha sonra: Стоп-листы, Отзывы, Пуш-рассылка (PWA web push önerildi),
   Программа лояльности, müşteri SMS girişi, Yandex API anahtarıyla teslimat bölgesi çizimi.

## Yerelde çalıştırma (Windows)

```bash
git clone https://github.com/shaolean3131/Kolibri-prosda-sayt.git
cd Kolibri-prosda-sayt
git checkout claude/kolibri-flowers-design-kg7uv9
php -S 127.0.0.1:8080          # PHP 8.1+ gerekli (pdo_sqlite açık olmalı)
# vitrin:  http://127.0.0.1:8080/
# panel:   http://127.0.0.1:8080/admin/   (ilk açılışta sahip hesabı oluşturulur)
# tasarımlar: designs/index.html dosyasını tarayıcıda açmak yeterli
php admin/tools/seed-demo.php  # isteğe bağlı deneme verisi (sadece test için)
```

Kontrol: PHP dosyaları için `php -l`, JS için tarayıcı konsolu. Önceki oturum her
değişikliği Playwright ile masaüstü (1600px) ve telefon (390px) ekran görüntüsü alarak doğruladı;
aynı yaklaşımı sürdür.
