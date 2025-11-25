<?php
try {
    $host = "localhost";
    $db   = "testdb";
    $user = "postgres";
    $pass = "murat123"; // <-- senin pw.txt'ye yazdığın şifre neyse onu yaz

    $dsn = "pgsql:host=$host;dbname=$db;port=5432";

    $pdo = new PDO($dsn, $user, $pass);

    echo "Bağlantı başarılı<br>";

    $stmt = $pdo->query("SELECT current_database(), version()");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($row);
    echo "</pre>";
$stmt = $pdo->query("SELECT * FROM users");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($rows);
echo "</pre>";

} catch (PDOException $e) {
    echo "Bağlantı hatası: " . $e->getMessage();
}
