<?php
require 'db.php';

// Şimdilik owner_id sabit.
// Login bitince: $_SESSION['user_id'] olarak değiştirirsin.
$ownerId = 1;

// Mevcut işletme bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM business WHERE owner_id = :id LIMIT 1");
$stmt->execute(['id' => $ownerId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business) {
    die("Bu kullanıcıya ait işletme bulunamadı.");
}

// FORM KAYDEDİLDİ Mİ?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'];
    $address = $_POST['address'];
    $tel = $_POST['tel_no'];
    $desc = $_POST['description'];
    $hours = $_POST['business_hours'];

    $update = $pdo->prepare("
        UPDATE business
        SET name = :name,
            address = :address,
            tel_no = :tel,
            description = :desc,
            business_hours = :hours
        WHERE shop_id = :id
    ");

    $update->execute([
        'name' => $name,
        'address' => $address,
        'tel' => $tel,
        'desc' => $desc,
        'hours' => $hours,
        'id' => $business['shop_id']
    ]);

    $success = "İşletme bilgileri güncellendi!";
}

// Tekrar güncel halini getir
$stmt = $pdo->prepare("SELECT * FROM business WHERE shop_id = :id");
$stmt->execute(['id' => $business['shop_id']]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İşletme Ayarları</title>
    <style>
        body { font-family: Arial; margin: 30px; max-width: 600px; }
        input, textarea { width: 100%; padding: 8px; margin: 6px 0; }
        label { font-weight: bold; }
        button { padding: 10px 20px; margin-top: 10px; cursor: pointer; }
        .msg { background: #d4edda; padding: 10px; margin-bottom: 10px; }
    </style>
</head>
<body>

<h1>İşletme Bilgileri</h1>

<?php if (!empty($success)): ?>
    <div class="msg"><?= $success ?></div>
<?php endif; ?>

<form method="POST">

    <label>İşletme Adı</label>
    <input type="text" name="name" value="<?= $business['name'] ?>">

    <label>Adres</label>
    <input type="text" name="address" value="<?= $business['address'] ?>">

    <label>Telefon</label>
    <input type="text" name="tel_no" value="<?= $business['tel_no'] ?>">

    <label>Açıklama</label>
    <textarea name="description" rows="3"><?= $business['description'] ?></textarea>

    <label>Çalışma Saatleri</label>
    <textarea name="business_hours" rows="2"><?= $business['business_hours'] ?></textarea>

    <button type="submit">Kaydet</button>
</form>

</body>
</html>
