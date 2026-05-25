<?php
// تأمين بسيط لمنع أي شخص من تشغيل الملف
if (!isset($_GET['token']) || $_GET['token'] !== 'fleet1234') {
    die('Unauthorized');
}

echo "<h2>تفريغ كاش السيرفر</h2>";

// 1. تفريغ OPcache
if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo $result ? "✅ OPcache cleared successfully!<br>" : "❌ Failed to clear OPcache!<br>";
} else {
    echo "⚠️ OPcache is not enabled or opcache_reset() is disabled.<br>";
}

// 2. تفريغ كاش لارافيل الأساسي بحذف الملفات يدوياً (لضمان تجاوز أي مشاكل في Artisan)
$cachePath = dirname(__DIR__) . '/bootstrap/cache';
$files = ['config.php', 'routes-v7.php', 'packages.php', 'services.php', 'events.php'];
foreach ($files as $file) {
    $path = $cachePath . '/' . $file;
    if (file_exists($path)) {
        @unlink($path);
        echo "✅ Deleted $file<br>";
    }
}

// 3. محاولة تشغيل Artisan كخطوة أخيرة
try {
    $output = @shell_exec('cd ' . dirname(__DIR__) . ' && php artisan optimize:clear 2>&1');
    if ($output) {
        echo "✅ <b>Artisan Output:</b><br><pre>" . htmlspecialchars($output) . "</pre>";
    }
} catch (Exception $e) {
    echo "⚠️ Artisan command failed (shell_exec might be disabled).<br>";
}

echo "<h3>✅ تمت العملية بالكامل! يمكنك الآن فحص النظام.</h3>";
