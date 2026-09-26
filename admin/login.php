<?php
require __DIR__ . '/core/bootstrap.php';

if (current_admin() !== null) {
    redirect('index.php');
}

$setup  = !admins_exist();
$error  = '';
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name']  = trim((string) ($_POST['name'] ?? ''));
    $values['email'] = trim((string) ($_POST['email'] ?? ''));
    $password        = (string) ($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $error = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } elseif ($setup) {
        if ($values['name'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Укажите имя и корректный e-mail.';
        } elseif (mb_strlen($password) < 8) {
            $error = 'Пароль должен быть не короче 8 символов.';
        } elseif ($password !== (string) ($_POST['password_confirm'] ?? '')) {
            $error = 'Пароли не совпадают.';
        } else {
            create_admin($values['name'], $values['email'], $password);
            attempt_login($values['email'], $password);
            redirect('index.php');
        }
    } elseif (attempt_login($values['email'], $password)) {
        redirect('index.php');
    } else {
        usleep(random_int(300000, 700000)); // slow down password guessing
        $error = 'Неверный e-mail или пароль.';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $setup ? 'Создание администратора' : 'Вход' ?> — <?= e(config('app_name')) ?></title>
    <link rel="icon" href="<?= e(asset('img/kolibri-mark.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth">
<main class="auth__wrap">
    <div class="wordmark wordmark--center">
        <span class="wordmark__name">КОЛИБРИ</span>
        <span class="wordmark__sub">СТУДИЯ&nbsp;ЦВЕТОВ</span>
    </div>

    <form class="auth-card<?= $error ? ' has-error' : '' ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <img class="auth-card__logo" src="<?= e(asset('img/kolibri-mark.svg')) ?>" alt="" width="56" height="56">
        <h1 class="auth-card__title"><?= $setup ? 'Создание администратора' : 'Вход в панель' ?></h1>
        <p class="auth-card__text">
            <?= $setup
                ? 'Это первый запуск. Создайте учётную запись владельца магазина.'
                : 'Войдите, чтобы управлять магазином «' . e(config('app_name')) . '».' ?>
        </p>

        <?php if ($error): ?>
            <div class="alert" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($setup): ?>
            <label class="field">
                <span class="field__label">Имя</span>
                <input class="input" type="text" name="name" value="<?= e($values['name']) ?>" autocomplete="name" required>
            </label>
        <?php endif; ?>
        <label class="field">
            <span class="field__label">E-mail</span>
            <input class="input" type="email" name="email" value="<?= e($values['email']) ?>" autocomplete="username" required autofocus>
        </label>
        <label class="field">
            <span class="field__label">Пароль</span>
            <input class="input" type="password" name="password" autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" required>
        </label>
        <?php if ($setup): ?>
            <label class="field">
                <span class="field__label">Повторите пароль</span>
                <input class="input" type="password" name="password_confirm" autocomplete="new-password" required>
            </label>
        <?php endif; ?>

        <button class="btn btn--primary btn--block" type="submit"><?= $setup ? 'Создать и войти' : 'Войти' ?></button>
    </form>
</main>
</body>
</html>
