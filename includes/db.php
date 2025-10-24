<?php
// -Doğrudan tarayıcı erişimini engelle-
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit('403 - Forbidden.');
}

// -Hata gösterimini kapat (prod) fakat logla-
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// -Veritabanı bağlantısı-
try {
    $dbPath = __DIR__ . '/../../../Tripin_database/Tripin.db'; //DB dosyanın yolu

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Detayları loga yaz
    error_log('DB bağlantı hatası: ' . $e->getMessage());
    http_response_code(500);
    exit('Sistem hatası. Lütfen daha sonra tekrar deneyin.');
}
