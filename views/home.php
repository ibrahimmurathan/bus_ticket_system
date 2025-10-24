<?php
session_start();
require_once __DIR__ . '/../includes/db.php'; // DB bağlantısı ($db)

/* ---------- Helper fonksiyonlar ---------- */
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$messages = [];

/* ---------- POST: Bilet Alma ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_seat'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $trip_id = $_POST['trip_id'] ?? '';
    $seat_number = isset($_POST['seat_number']) ? (int)$_POST['seat_number'] : 0;
    $gender = $_POST['gender'] ?? '';
    $user_id = $_SESSION['user_id'];

    if (empty($trip_id) || $seat_number < 1 || $seat_number > 40 || !in_array($gender, ['male','female'])) {
        $messages[] = ['type'=>'danger','text'=>'Geçersiz talep. Lütfen tekrar deneyin.'];
    } else {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id, price FROM Trips WHERE id = :id");
            $stmt->execute([':id'=>$trip_id]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trip) throw new Exception("Sefer bulunamadı.");

            $stmt = $db->prepare("
                SELECT bs.id 
                FROM Booked_Seats bs
                JOIN Tickets t ON bs.ticket_id = t.id
                WHERE t.trip_id = :trip_id AND bs.seat_number = :seat_number
                LIMIT 1
            ");
            $stmt->execute([':trip_id'=>$trip_id, ':seat_number'=>$seat_number]);
            if ($stmt->fetch()) throw new Exception("Seçtiğiniz koltuk dolu.");

            $insertTicket = $db->prepare("
                INSERT INTO Tickets (status, total_price, trip_id, user_id, passenger_gender)
                VALUES ('active', :total_price, :trip_id, :user_id, :passenger_gender)
            ");
            $insertTicket->execute([
                ':total_price' => $trip['price'],
                ':trip_id' => $trip_id,
                ':user_id' => $user_id,
                ':passenger_gender' => $gender
            ]);
            $ticket_id = $db->lastInsertId();

            $insertSeat = $db->prepare("
                INSERT INTO Booked_Seats (seat_number, ticket_id) VALUES (:seat_number, :ticket_id)
            ");
            $insertSeat->execute([
                ':seat_number' => $seat_number,
                ':ticket_id' => $ticket_id
            ]);

            $db->commit();
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?trip_id=" . urlencode($trip_id) . "&show_seats=1&booked=1");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $messages[] = ['type'=>'danger','text'=>'Rezervasyon hatası: ' . h($e->getMessage())];
        }
    }
}

/* ---------- Filtreleme ---------- */
$filter_from = $_GET['from'] ?? '';
$filter_to = $_GET['to'] ?? '';
$filter_date = $_GET['date'] ?? '';

$where = [];
$params = [];
if (!empty($filter_from)) { $where[] = 'departure_city = :from'; $params[':from']=$filter_from; }
if (!empty($filter_to)) { $where[] = 'destination_city = :to'; $params[':to']=$filter_to; }
if (!empty($filter_date)) { $where[] = "date(departure_time) = :date"; $params[':date']=$filter_date; }
$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT t.*, bc.company_name, bc.logo_path
    FROM Trips t
    LEFT JOIN Bus_Company bc ON t.company_id = bc.id
    $where_sql
    ORDER BY departure_time ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Dropdown şehirler ---------- */
$departureCities = $db->query("SELECT DISTINCT departure_city FROM Trips ORDER BY departure_city ASC")->fetchAll(PDO::FETCH_COLUMN);
$destinationCities = $db->query("SELECT DISTINCT destination_city FROM Trips ORDER BY destination_city ASC")->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Modal Trip ve koltuklar ---------- */
$modalTrip = null;
$bookedSeats = [];
if (isset($_GET['show_seats']) && isset($_GET['trip_id'])) {
    $trip_id = $_GET['trip_id'];
    $s = $db->prepare("SELECT t.*, bc.company_name, bc.logo_path FROM Trips t LEFT JOIN Bus_Company bc ON t.company_id = bc.id WHERE t.id = :id");
    $s->execute([':id'=>$trip_id]);
    $modalTrip = $s->fetch(PDO::FETCH_ASSOC);

    if ($modalTrip) {
        $q = $db->prepare("
            SELECT bs.seat_number, t.passenger_gender, bs.ticket_id
            FROM Booked_Seats bs
            JOIN Tickets t ON bs.ticket_id = t.id
            WHERE t.trip_id = :trip_id
        ");
        $q->execute([':trip_id'=>$trip_id]);
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $bookedSeats[(int)$r['seat_number']] = [
                'gender' => $r['passenger_gender'],
                'ticket_id' => $r['ticket_id']
            ];
        }
    } else {
        $messages[] = ['type'=>'warning','text'=>'Sefer bulunamadı.'];
    }
}
?>

<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Otobüs Seferleri</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.bg-pink { background-color: #e83e8c !important; }
.seat { width:56px;height:56px;display:inline-flex;align-items:center;justify-content:center;margin:6px;border-radius:6px;cursor:pointer;border:1px solid #ccc; }
.seat.disabled { cursor:not-allowed; opacity:0.95; }
.seat-row { display:flex; align-items:center; justify-content:center; flex-wrap:nowrap; }
.seat-legend .seat { width:36px;height:36px;margin:4px; }
@media (max-width:576px) { .seat { width:44px;height:44px;margin:4px;font-size:12px; } }
</style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm">
<div class="container">
    <a class="navbar-brand fw-bold" href="#">Otobüs Seferleri</a>
    <div class="d-flex align-items-center">

    <?php if(isset($_SESSION['user_id'])): ?>
        <?php
        // Role session'da yoksa DB'den al
        if(!isset($_SESSION['role'])) {
            $stmtRole = $db->prepare("SELECT role FROM User WHERE id = :id LIMIT 1");
            $stmtRole->execute([':id'=>$_SESSION['user_id']]);
            $_SESSION['role'] = $stmtRole->fetchColumn() ?: 'user';
        }

        // Role'e göre panel butonu
        if($_SESSION['role'] === 'company'): ?>
            <a href="../company/company_dashboard.php" class="btn btn-outline-success me-2">Firma Paneli</a>
        <?php elseif($_SESSION['role'] === 'admin'): ?>
            <a href="../admin/admin_dashboard.php" class="btn btn-outline-danger me-2">Admin Paneli</a>
        <?php endif; ?>

        <!-- Dropdown -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?= h($_SESSION['user_name'] ?? 'Kullanıcı') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><a class="dropdown-item" href="profile.php">Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
            </ul>
        </div>

    <?php else: ?>
        <a href="login.php" class="btn btn-outline-primary">Giriş Yap</a>
    <?php endif; ?>

    </div>
</div>
</nav>


<div class="container my-4">
<?php foreach($messages as $m): ?>
<div class="alert alert-<?= h($m['type']) ?>"><?= h($m['text']) ?></div>
<?php endforeach; ?>


<!-- Filtre form -->
<div class="card mb-4 shadow-sm">
<div class="card-body">
<form method="get" class="row g-2 align-items-end">
<div class="col-md-3">
<label class="form-label">Kalkış (From)</label>
<select class="form-select" name="from">
<option value="">Tümü</option>
<?php foreach($departureCities as $c): ?>
<option value="<?= h($c) ?>" <?= $filter_from === $c ? 'selected' : '' ?>><?= h($c) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-3">
<label class="form-label">Varış (To)</label>
<select class="form-select" name="to">
<option value="">Tümü</option>
<?php foreach($destinationCities as $c): ?>
<option value="<?= h($c) ?>" <?= $filter_to === $c ? 'selected' : '' ?>><?= h($c) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-3">
<label class="form-label">Tarih</label>
<input type="date" class="form-control" name="date" value="<?= h($filter_date) ?>">
</div>
<div class="col-md-3">
<button type="submit" class="btn btn-primary w-100">Filtrele</button>
</div>
</form>
</div>
</div>

<!-- Trips kartlar -->
<div class="row g-3">
<?php foreach($trips as $trip): ?>
<div class="col-md-6 col-lg-4">
<div class="card shadow-sm h-100">
<img src="/<?= h($trip['logo_path']) ?>" class="card-img-top" alt="<?= h($trip['company_name'] ?? '') ?>" style="height:140px;object-fit:contain;">
<div class="card-body">
<h5 class="card-title"><?= h($trip['departure_city']) ?> → <?= h($trip['destination_city']) ?></h5>
<p class="card-text mb-1"><strong>Firma:</strong> <?= h($trip['company_name'] ?? '-') ?></p>
<p class="card-text mb-1"><strong>Fiyat:</strong> <?= h($trip['price']) ?> ₺</p>
<p class="card-text mb-1"><strong>Kalkış:</strong> <?= h($trip['departure_time']) ?></p>
<p class="card-text mb-1"><strong>Varış:</strong> <?= h($trip['arrival_time']) ?></p>
<a href="?trip_id=<?= h($trip['id']) ?>&show_seats=1" class="btn btn-outline-primary w-100">Koltukları Gör</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>

</div>

<!-- Koltuk modal -->
<?php if($modalTrip): ?>
<div class="modal fade" id="seatModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><?= h($modalTrip['departure_city']) ?> → <?= h($modalTrip['destination_city']) ?> Koltuklar</h5>
<a href="home.php" class="btn-close"></a>
</div>
<div class="modal-body">
<form method="post">
<input type="hidden" name="trip_id" value="<?= h($modalTrip['id']) ?>">
<div class="mb-3">
<p>Koltuk seçiniz (2+2 düzen, 40 koltuk):</p>
<div class="d-flex flex-wrap justify-content-center">
<?php
for($i=1;$i<=40;$i++){
    $seatClass='seat';
    $disabled='';
    $color='';
    if(isset($bookedSeats[$i])){
        $gender = $bookedSeats[$i]['gender'];
        $color = $gender==='female' ? 'bg-pink text-white' : 'bg-primary text-white';
        $disabled='disabled';
    }
    echo "<div class='$seatClass $color $disabled' data-seat='$i'>{$i}</div>";
    if($i%4==0) echo "<div class='w-100'></div>";
}
?>
</div>
<input type="hidden" name="seat_number" id="selectedSeat">
</div>
<div class="mb-3">
<label class="form-label">Cinsiyet</label>
<select class="form-select" name="gender" id="genderSelect" required>
<option value="">Seçiniz</option>
<option value="male">Erkek</option>
<option value="female">Kadın</option>
</select>
</div>
<button type="submit" name="book_seat" class="btn btn-success w-100">Bileti Al</button>
</form>
</div>
</div>
</div>
</div>

<script>
var seatModal = new bootstrap.Modal(document.getElementById('seatModal'));
seatModal.show();

document.querySelectorAll('.seat').forEach(function(s){
    s.addEventListener('click', function(){
        if(this.classList.contains('disabled')) return;
        document.querySelectorAll('.seat').forEach(el=>el.classList.remove('bg-secondary'));
        this.classList.add('bg-secondary');
        document.getElementById('selectedSeat').value=this.dataset.seat;
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>