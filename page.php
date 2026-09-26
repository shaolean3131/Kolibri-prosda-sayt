<?php
define('KOLIBRI_SITE', true);
require __DIR__ . '/admin/core/bootstrap.php';
require_once __DIR__ . '/admin/core/orders.php';
require __DIR__ . '/site/layout.php';

$pages = [
    'promotions' => 'Акции',
    'privacy'    => 'Политика конфиденциальности',
    'terms'      => 'Пользовательское соглашение',
];
$slug = is_string($_GET['p'] ?? null) && isset($pages[$_GET['p']]) ? $_GET['p'] : null;
if ($slug === null) {
    http_response_code(404);
    $slug = '';
}
$title = $pages[$slug] ?? 'Страница не найдена';

site_head($title . ' — ' . config('app_name', 'Колибри'));
?>
<div class="page">
    <?php site_header(); ?>
    <main class="container">
        <article class="content">
            <h1><?= e($title) ?></h1>

            <?php if ($slug === 'promotions'):
                $now = date('Y-m-d H:i:s');
                $stmt = db()->prepare('SELECT * FROM promotions WHERE is_active = 1 AND (ends_at IS NULL OR ends_at >= ?) ORDER BY sort, id DESC');
                $stmt->execute([$now]);
                $promos = $stmt->fetchAll(); ?>
                <?php if (!$promos): ?>
                    <p class="content__text">Сейчас акций нет — загляните позже 🌸</p>
                <?php endif; ?>
                <div class="promo-cards">
                    <?php foreach ($promos as $n => $promo):
                        $banner = $promo['banner_desktop'] ?: ($promo['banner_mobile'] ?: $promo['banner_app']); ?>
                        <section class="promo-card" id="promo-<?= (int) $promo['id'] ?>" style="animation-delay: <?= $n * 0.08 ?>s">
                            <?php if ($banner): ?><img src="<?= e(upload_url($banner)) ?>" alt="" loading="lazy"><?php endif; ?>
                            <div class="promo-card__body">
                                <h2><?= e($promo['name']) ?></h2>
                                <?php if ((string) $promo['description'] !== ''): ?><p><?= e($promo['description']) ?></p><?php endif; ?>
                                <div class="promo-card__meta"><?= e(promo_period_label($promo) === 'Бессрочный' ? 'Бессрочная акция' : 'Срок: ' . promo_period_label($promo)) ?></div>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($slug === 'privacy' || $slug === 'terms'):
                $text = trim((string) setting($slug === 'privacy' ? 'legal_privacy' : 'legal_terms', '')); ?>
                <div class="content__text"><?= $text !== '' ? e($text) : 'Текст скоро появится.' ?></div>

            <?php else: ?>
                <p class="content__text">Такой страницы нет. <a href="./">Вернуться в каталог</a></p>
            <?php endif; ?>
        </article>
    </main>
    <?php site_footer(); ?>
</div>
<?php site_contacts_modal(pickup_points()); ?>
<script>
// header menu and contacts window on content pages
document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-dd-toggle]');
    document.querySelectorAll('[data-dd].is-open').forEach(function (d) { if (!t || d !== t.closest('[data-dd]')) { d.classList.remove('is-open'); } });
    if (t) { t.closest('[data-dd]').classList.toggle('is-open'); return; }
    var open = e.target.closest('[data-open]');
    if (open) {
        var name = open.getAttribute('data-open');
        if (name !== 'contacts') { location.href = './'; return; }
        document.querySelector('[data-smodal="contacts"]').classList.add('is-open');
    }
    if (e.target.closest('[data-close]')) { e.target.closest('.smodal').classList.remove('is-open'); }
});
window.addEventListener('scroll', function () { document.querySelector('[data-hdr]').classList.toggle('is-scrolled', scrollY > 8); }, { passive: true });
document.querySelectorAll('[data-cart-open]').forEach(function (b) {
    try { var n = JSON.parse(localStorage.getItem('kolibri.cart') || '[]').length; if (n) { b.classList.add('has-items'); } } catch (err) {}
    b.addEventListener('click', function () { location.href = './'; });
});
</script>
<?= setting('snippet_body', '') ?>
</body>
</html>
