# Колибри — kendi siteniz ve yönetim paneliniz

kolibri-flowers.ru için, hazır bir platforma aylık ödeme yapmadan kendi
sunucunuzda çalışan PHP sitesi ve yönetim paneli.

## Durum

| Bölüm | Durum |
|---|---|
| **Vitrin**: katalog, ürün penceresi, sepet, promosyon kodu, sipariş formu | hazır |
| **Vitrin**: teslimat / gel-al seçimi, harita, arama, iletişim, Акции sayfası | hazır |
| **Uygulama** (PWA): «ana ekrana ekle», çevrimdışı sayfa | hazır |
| Admin: giriş, iskelet, Дашборд | hazır |
| Admin: Каталог, Клиенты, Заказы, Акции и скидки | hazır |
| Admin: Настройки (Главные, Формы оплаты, Предзаказы, Точки и зоны доставки, Время работы, Уведомления, Юр. информация, Сниппеты) | hazır |
| Yeni sipariş → Telegram mesajı + panelde sesli bildirim | hazır |
| Müşteri girişi (SMS), teslimat bölgesi çizimi, diğer menü bölümleri | sonra |

## Gereksinimler

- PHP 8.1+ (`pdo_sqlite` veya `pdo_mysql`, `mbstring`)
- Apache (`.htaccess` dosyaları hazır) veya Nginx

## Kurulum

1. Dosyaları hosting'e yükleyin.
2. `config/config.sample.php` dosyasını `config/config.php` adıyla kopyalayın
   ve gerekiyorsa MySQL bilgilerini girin. Hiçbir şey değiştirmezseniz
   SQLite kullanılır (ayrı veritabanı kurmaya gerek yok).
3. **Hemen** `https://siteniz/admin/` adresini açın: ilk açılışta mağaza
   sahibinin hesabı oluşturulur. İlk hesap oluşturulduktan sonra bu ekran
   kapanır.

Nginx kullanıyorsanız `config/`, `storage/`, `site/`, `admin/core/`,
`admin/pages/`, `admin/tools/` klasörlerine dışarıdan erişimi kapatın ve
`uploads/` içinde PHP çalıştırmayın:

```nginx
location ~ ^/(config|storage|site|admin/(core|pages|tools))/ { deny all; }
location ~ ^/uploads/.*\.php$ { deny all; }
```

## Telegram bildirimleri

1. Telegram'da **@BotFather** → `/newbot` ile bir bot oluşturun, token'ı kopyalayın.
2. Panel → Настройки → Еще → **Уведомления** → «Токен бота» alanına yapıştırın.
3. Botunuza `/start` yazın (veya botu çalışanların grubuna ekleyin).
4. «Найти чаты» → çıkan sohbeti seçin → «Отправить тестовое сообщение».

Artık her yeni sipariş anında Telegram'a gelir. Sunucunun `api.telegram.org`
adresine erişebilmesi gerekir (Rusya'daki bazı hostinglerde kapalı olabilir;
o durumda hosting desteğine sorun).

## Uygulama (ana ekrana ekle)

Site HTTPS üzerinde çalıştığında telefonda ana ekrana eklenip gerçek bir
uygulama gibi açılır:

- adres çubuğu yok, kendi ikonu ve açılış ekranı var (iPhone'un 11 ekran
  boyutu için ayrı açılış görseli: `assets/splash/`);
- altta sekme çubuğu: Каталог · Поиск · Акции · Корзина · Контакты
  (sepette ürün sayısı rozeti);
- Android'in «geri» tuşu/hareketi siteden çıkmaz, açık pencereyi kapatır;
- aşağı çekince yenilenir (kolibri animasyonu), zoom/metin seçme yok,
  iPhone'da forma dokununca ekran büyümez;
- internet yokken de açılır (son görülen katalog), bağlantı yoksa uyarı çıkar;
- simgeye uzun basınca kısayollar: «Корзина», «Акции».

Kurulum: Android/Chrome'da alttaki kartta «Установить»; iPhone'da kart
«Как?» ile adım adım talimat açar (Поделиться → На экран «Домой»).
Uygulamadan gelen siparişler panelde «Приложение» olarak işaretlenir.

İkon ve açılış görselleri `assets/icons/` ve `assets/splash/` içinde;
logonuz değişirse bunlar yeniden üretilmelidir.

## Saat dilimi

`config/config.php` içindeki `timezone` değeri (varsayılan `Asia/Novokuznetsk`)
çalışma saatleri, ön sipariş saatleri ve grafikler için kullanılır.

## Yerelde deneme

```bash
php -S 127.0.0.1:8080
# tarayıcıda: http://127.0.0.1:8080/admin/

# grafikleri görmek için deneme verisi (canlı veritabanında ÇALIŞTIRMAYIN):
php admin/tools/seed-demo.php
```

## Yapı

```
index.php            vitrin (katalog, sepet, sipariş)
page.php             Акции, gizlilik politikası, kullanım koşulları
api/                 vitrinin API'si (sepet hesabı, sipariş)
site/                vitrin şablon parçaları
assets/              vitrin css/js, uygulama ikonları
manifest.webmanifest, sw.js, offline.html   uygulama (PWA) dosyaları
admin/
  index.php          sayfa yönlendirici (?p=dashboard, ?p=orders ...)
  login.php          giriş + ilk kurulum
  api/stats.php      Дашборд grafik verisi (JSON)
  core/              ayarlar, veritabanı, menü, ikonlar, sayfa şablonu
  pages/             ekranlar
  assets/            css, js (grafikler), logo
config/              ayar dosyası
storage/             SQLite veritabanı
uploads/             yüklenen fotoğraflar (ürün, kategori, baner)
```

Veritabanı tabloları ilk açılışta otomatik oluşur; yeni sürüm yüklendiğinde
eksik tablo/sütunlar kendiliğinden eklenir (`admin/core/schema.php`).

Yan menü `admin/core/menu.php` dosyasından yönetilir. Yeni bir ekran eklemek
için `admin/pages/` içine dosya koyup `admin/index.php` içindeki `$views`
listesine ekleyin.
