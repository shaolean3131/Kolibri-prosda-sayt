# Колибри — kendi siteniz ve yönetim paneliniz

kolibri-flowers.ru için, hazır bir platforma aylık ödeme yapmadan kendi
sunucunuzda çalışan PHP sitesi ve yönetim paneli.

## Durum

| Bölüm | Durum |
|---|---|
| Admin: giriş / ilk kurulum | hazır |
| Admin: iskelet (üst bar, yan menü, mobil menü, animasyonlar) | hazır |
| Admin: Дашборд (grafikler, dönem seçimi, CSV indirme) | hazır |
| Admin: Каталог → Основное (kategori, ürün, fotoğraf, etiket, sıralama, arama) | hazır |
| Admin: Клиенты (filtreler, gizli telefon, CSV) | hazır |
| Admin: Акции и скидки (baner, süre, tür, promosyon kodu, ayarlar) | hazır |
| Admin: Настройки (Главные, Формы оплаты, Предзаказы, Время работы, Юр. информация, Сниппеты) | hazır |
| Admin: Точки и зоны доставки (harita) | vitrinle birlikte |
| Admin: diğer menü bölümleri | ekran görüntüleri bekleniyor |
| Vitrin (müşteri tarafı) + «ana ekrana ekle» (PWA) | sırada |

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

Nginx kullanıyorsanız `config/`, `storage/`, `admin/core/`, `admin/pages/`,
`admin/tools/` klasörlerine dışarıdan erişimi kapatın:

```nginx
location ~ ^/(config|storage|admin/(core|pages|tools))/ { deny all; }
```

## Yerelde deneme

```bash
php -S 127.0.0.1:8080
# tarayıcıda: http://127.0.0.1:8080/admin/

# grafikleri görmek için deneme verisi (canlı veritabanında ÇALIŞTIRMAYIN):
php admin/tools/seed-demo.php
```

## Yapı

```
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
