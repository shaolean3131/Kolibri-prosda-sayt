<?php
/*
 * Kolibri — örnek ayar dosyası.
 * Bu dosyayı config.php adıyla kopyalayıp kendi değerlerinizi yazın.
 * config.php git'e eklenmez.
 */

return [
    'app_name'  => 'Колибри',
    'site_url'  => '/',
    'timezone'  => 'Asia/Novokuznetsk', // saat dilimi: çalışma saatleri ve ön siparişler buna göre
    'currency'  => '₽',

    'db' => [
        // 'sqlite' — kurulum gerektirmez, veriler storage/ klasöründe tutulur.
        // 'mysql'  — hosting'deki MySQL/MariaDB veritabanı.
        'driver'   => 'sqlite',
        'path'     => dirname(__DIR__) . '/storage/kolibri.sqlite',

        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'kolibri',
        'user'     => 'root',
        'password' => '',
    ],
];
