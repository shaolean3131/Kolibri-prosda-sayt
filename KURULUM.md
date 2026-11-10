# Kurulum rehberi — adım adım

Bu rehber, siteyi Foodpicasso'dan kendi hostinginize taşımak için yazıldı.
Teknik bilgi gerekmez; her adımı sırayla yapın.

> **Önemli:** Foodpicasso'yu hemen kapatmayın. Yeni siteyi önce bir **deneme
> adresinde** kurun (ör. `new.kolibri-flowers.ru`). Her şey çalışınca alan adını
> yeni siteye çevirin, Foodpicasso'yu ondan sonra iptal edin.

---

## 1. Hosting alın

Rusya'daki bir hosting firması önerilir, örneğin **Beget**, **Timeweb** veya **REG.RU**.
Bunun iki sebebi var: site müşterilere hızlı açılır, ve Rusya'da müşteri verilerinin
(isim, telefon) Rusya'da saklanması kanunen istenir.

En ucuz «виртуальный хостинг» paketi yeterlidir. Pakette şunlar olmalı:

- **PHP 8.1 veya daha yeni** bir sürüm.
- Ücretsiz **SSL sertifikası** (Let's Encrypt). Adres `https://` ile açılmalı; Telegram Mini App ve
  «ana ekrana ekle» bunsuz çalışmaz.
- Dışarıya bağlantı izni. Sunucunun `api.telegram.org` adresine ulaşabilmesi gerekir.
  Emin değilseniz hosting desteğine sorun: «Можно ли с хостинга обращаться к api.telegram.org?»

## 2. Deneme adresini açın

Hosting panelinde **поддомен** (alt alan adı) bölümünden `new.kolibri-flowers.ru` oluşturun.

Alan adı şu an Foodpicasso'ya bağlıysa bunu yapmak için alan adını nereden aldığınızı
bilmeniz gerekir; aşağıdaki **9. adıma** bakın. Alternatif olarak hostingin verdiği ücretsiz
teknik adresle de (ör. `kolibri.beget.tech`) deneme yapabilirsiniz.

Ardından bu adres için **SSL**'i açın: panelde «SSL-сертификаты» → «Let's Encrypt».

## 3. Dosyaları yükleyin

1. GitHub'da depoyu açın, dal seçicisinden `claude/kolibri-flowers-design-kg7uv9` dalını seçin.
2. **Code → Download ZIP** ile dosyaları indirin.
3. Hosting panelinin **Файловый менеджер** bölümünde, deneme adresinin klasörüne
   (genelde `new.kolibri-flowers.ru/public_html`) ZIP'i yükleyin ve **Распаковать** deyin.
   Dosyalar doğrudan bu klasörde olmalı: `index.php`, `admin/`, `tg/`…
   Eğer ZIP içinden ayrı bir alt klasör çıktıysa, içindekileri bir üst klasöre taşıyın.
4. `config/config.sample.php` dosyasını kopyalayıp adını `config/config.php` yapın.
   İçini değiştirmenize gerek yok, varsayılan veritabanı ayarı yeterli.

## 4. Yönetici hesabını oluşturun

Dosyalar yüklenir yüklenmez `https://new.kolibri-flowers.ru/admin/` adresini açın.
İlk açılışta **mağaza sahibi hesabı** oluşturulur: adınızı, e-postanızı ve güçlü bir şifre girin.

Bu adımı geciktirmeyin. Bu ekranı ilk açan kişi sahip olur; ilk hesaptan sonra ekran kapanır.

> `admin/tools/seed-demo.php` (deneme verisi) dosyasını gerçek sitede **çalıştırmayın**.

## 5. Paneli doldurun

**Настройки** menüsünden şu bölümleri doldurun:

- **Главные**: «Заведение активно» açık olsun; sipariş sonrası mesajı yazın.
- **Время работы**: iki dükkân 24 saat açık, her gün için «круглосуточно» seçin.
- **Точки и зоны доставки**:
  - İki gel-al noktasını girin: Тореза 42а/1 ve Советской Армии 2а/2. Koordinatları Yandex
    Haritalar'daki kartta bulabilirsiniz.
  - Teslimat ücretini, «бесплатно от» tutarını ve teslimat koşulları metnini girin.
- **Формы оплаты**: nakit, kapıda kart, СБП havalesi (havale için numara ve banka bilgisi).
- **Еще → Юридическая информация**: müşterilerin göreceği telefon numarası ve şirket bilgileri.

Sonra diğer bölümleri doldurun:

- **Каталог**: önce kategorileri, sonra ürünleri fotoğraflarıyla girin.
- **Акции и скидки** (isteğe bağlı): kampanyalar ve promosyon kodları.

## 6. Sipariş bildirimleri için Telegram botu

1. Telegram'da **@BotFather** sohbetini açın ve `/newbot` yazın. Bota bir isim verin
   (ör. «Колибри — студия цветов») ve `kolibri_flowers_bot` gibi bir kullanıcı adı seçin.
2. BotFather'ın gönderdiği **token**'ı kopyalayın ve panelde **Настройки → Еще → Уведомления →
   «Токен бота»** alanına yapıştırın.
3. Kendi botunuza `/start` yazın. Siparişleri bir çalışan grubunda görmek istiyorsanız
   botu o gruba da ekleyin.
4. Panelde **«Найти чаты»** düğmesine basın, çıkan sohbetleri seçin, sonra
   **«Отправить тестовое сообщение»**. Mesaj Telegram'a geldiyse bu adım tamam.

> Bu adımı 7. adımdan **önce** yapın.

## 7. Telegram Mini App'i bağlayın

1. Panelde **Настройки → Еще → Telegram Mini App** sekmesini açın.
2. **Дизайн приложения**: Ботаника, Ночной сад veya Пастель. Alttaki küçük resimlere tıklayarak
   önce önizlemeye bakabilirsiniz.
3. **Адрес сайта**: `https://new.kolibri-flowers.ru` (başında https olmalı).
4. **«Подключить к боту»** düğmesine basın. «Готово» mesajı gelmeli.
5. Telefonda botu açın. Yazı kutusunun solunda **«Заказать букет»** düğmesi görünmeli.
   Basınca mağaza Telegram'ın içinde açılır.

İsteğe bağlı olarak @BotFather'da botu güzelleştirebilirsiniz:

- `/setuserpic`: logonuz.
- `/setdescription`: «Свежие букеты с доставкой по Новокузнецку».
- `/setabouttext`: adres ve telefon.

## 8. Deneme siparişleri verin

Şu dört yoldan birer deneme siparişi verin:

- [ ] Bilgisayardan site
- [ ] Telefondan site
- [ ] Telefonda «ana ekrana ekle» ile kurulan uygulama
- [ ] Telegram içinden (iPhone ve Android'de ayrı ayrı denerseniz daha iyi)

Her siparişte şunları kontrol edin:

- Sipariş panelde **Заказы** listesine düştü mü?
- Telegram'a bildirim geldi mi?
- Telegram'dan verilen siparişte durumu «Принят» yapınca müşteriye (yani size) bot mesajı geldi mi?

Deneme siparişlerini sonra «Отмена» durumuna alın.

## 9. Asıl alan adını yeni siteye çevirin

Her şey tamamsa:

1. **Alan adı kimde?** `kolibri-flowers.ru`'yu kim satın aldı: siz mi (ör. REG.RU, Beget,
   nic.ru), Foodpicasso mu? Bunu öğrenin. Foodpicasso adına kayıtlıysa önce sizin adınıza
   devredilmesini isteyin. **Bu en önemli adım; Foodpicasso'yu iptal etmeden önce yapın.**
2. Hosting panelinde `kolibri-flowers.ru` alan adını sitenize bağlayın ve SSL'ini açın.
3. Alan adını aldığınız yerde DNS ayarını değiştirin. Hosting size ya iki «NS-сервер» adresi
   ya da bir IP adresi (A kaydı) verir; onu girin. Değişikliğin her yere yayılması birkaç saati
   bulabilir.
4. Panelde **Telegram Mini App → Адрес сайта** alanını `https://kolibri-flowers.ru` yapın ve
   **«Подключить к боту»** düğmesine **tekrar** basın.
5. Site yeni adreste sorunsuz açılıyorsa Foodpicasso aboneliğini iptal edebilirsiniz.

## 10. Yedekleme

- Tüm veriler (ürünler, siparişler, müşteriler, ayarlar) tek bir dosyada:
  `storage/kolibri.sqlite`. Fotoğraflar `uploads/` klasöründe.
- Hostingin otomatik yedeklemesini açın ve ayda bir bu dosya ile klasörü bilgisayarınıza indirin.

---

### Takılırsanız

Hata mesajının veya ekranın fotoğrafını Claude'a gönderin. Yerel Claude Code oturumunda
hosting bilgilerinizle (FTP/SSH) birlikte kurulumu adım adım beraber de yapabilirsiniz.
