<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

/* ---------- Role ve Company Kontrol ---------- */
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'company') {
    die("Bu sayfaya erişim yetkiniz yok.");
}

$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $db->prepare("
    SELECT bc.id AS company_id, bc.company_name
    FROM User u
    JOIN Bus_Company bc ON u.company_id = bc.id
    WHERE u.id = :user_id
    LIMIT 1
");
$stmt->execute([':user_id' => $user_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$company) die("Firma bilgisi bulunamadı veya yetkiniz yok.");

$company_id = $company['company_id'];

/* ---------- Firma Seferlerini Çek ---------- */
$stmt = $db->prepare("SELECT * FROM Trips WHERE company_id = :company_id ORDER BY departure_time ASC");
$stmt->execute([':company_id' => $company_id]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Firma Kuponlarını Çek ---------- */
$stmt = $db->prepare("SELECT * FROM Coupons WHERE company_id = :company_id ORDER BY created_at DESC");
$stmt->execute([':company_id' => $company_id]);
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Firma Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm">
<div class="container">
<a class="navbar-brand fw-bold" href="#">Firma Paneli - <?= htmlspecialchars($company['company_name']) ?></a>
<div class="d-flex align-items-center">
<div class="dropdown">
<a class="btn btn-outline-secondary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
<?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
</a>
<ul class="dropdown-menu dropdown-menu-end">
<li><a class="dropdown-item" href="../views/home.php">Ana Sayfa</a></li>
<li><a class="dropdown-item" href="profile.php">Profil</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="../logout.php">Çıkış Yap</a></li>
</ul>
</div>
</div>
</div>
</nav>

<div class="container my-4">
<h3>Firma Seferleri</h3>
<div class="row g-3 mb-4">
<?php foreach($trips as $trip): ?>
<div class="col-md-6 col-lg-4">
<div class="card shadow-sm h-100">
<div class="card-body">
<h5 class="card-title"><?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['destination_city']) ?></h5>
<p class="card-text mb-1"><strong>Fiyat:</strong> <?= htmlspecialchars($trip['price']) ?> ₺</p>
<p class="card-text mb-1"><strong>Kalkış:</strong> <?= htmlspecialchars($trip['departure_time']) ?></p>
<p class="card-text mb-1"><strong>Varış:</strong> <?= htmlspecialchars($trip['arrival_time']) ?></p>
<a href="manage_trips.php?edit=<?= htmlspecialchars($trip['id']) ?>" class="btn btn-outline-primary w-100">Düzenle</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>

<h3>Firma Kuponları</h3>
<table class="table table-striped">
<thead>
<tr>
<th>Kod</th>
<th>İndirim (%)</th>
<th>Kullanım Limiti</th>
<th>Bitiş Tarihi</th>
</tr>
</thead>
<tbody>
<?php foreach($coupons as $c): ?>
<tr>
<td><?= htmlspecialchars($c['code']) ?></td>
<td><?= htmlspecialchars($c['discount']) ?></td>
<td><?= htmlspecialchars($c['usage_limit']) ?></td>
<td><?= htmlspecialchars($c['expire_date']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
