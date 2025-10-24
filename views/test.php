<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otobüs Koltuk Düzeni</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
        }

        .cinema-layout-container {
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #555;
        }

        .box {
            width: 15px;
            height: 15px;
            margin-right: 5px;
            border-radius: 3px;
        }

        /* Renkleri ayarlıyoruz */
        .box.available, .seat-box.available { background-color: #555; }
        .box.selected, .seat-box.selected { background-color: #37b7d6; }
        .box.occupied, .seat-box.occupied { background-color: #ccc; }

        /* Yeni yatay düzen için stil */
        .seat-container-wrapper {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .seat-row {
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        
        .seat-row-group {
            display: flex;
            gap: 40px; /* Ortadaki geniş koridor boşluğu */
        }

        .seat-box {
            width: 30px;
            height: 40px;
            background-color: #555;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .seat-box:not(.occupied):hover {
            transform: scale(1.1);
        }

        .summary-area {
            margin-top: 20px;
            text-align: center;
        }

        .summary-text {
            font-size: 18px;
            color: #333;
        }

        .next-button {
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            background-color: #3f51b5;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .next-button:hover {
            background-color: #303f9f;
        }
    </style>
</head>
<body>

<?php
// PHP kodları burada başlar
$seat_data = [];
// 40 koltuk için veri oluşturma
for ($i = 1; $i <= 40; $i++) {
    // Örnek doluluk durumu: bazı koltuklar dolu, diğerleri boş
    $status = ($i % 3 == 0) ? 'occupied' : 'available';
    $seat_data[$i] = ['status' => $status];
}

function createSeat($seatNumber, $status) {
    return '<div class="seat-box ' . $status . '" data-seat-number="' . $seatNumber . '"></div>';
}
?>

<div class="cinema-layout-container">
    <div class="legend">
        <div class="legend-item"><span class="box occupied"></span> Occupied</div>
        <div class="legend-item"><span class="box selected"></span> Selected</div>
        <div class="legend-item"><span class="box available"></span> Available</div>
    </div>
    
    <div class="seat-container-wrapper">
        <?php
        $rows_per_group = 4; // Her bir grupta 4 koltuk var
        $total_rows = 40;
        $seats_per_row_group = 4; // her bir yatay grupta 4 koltuk
        $total_groups = $total_rows / $seats_per_row_group;

        for ($i = 0; $i < $total_groups; $i++) {
            echo '<div class="seat-row-group">';
            
            // Sol taraftaki ikili grup
            echo '<div class="seat-row">';
            for ($j = 1; $j <= 2; $j++) {
                $seat_number = ($i * $seats_per_row_group) + $j;
                echo createSeat($seat_number, $seat_data[$seat_number]['status']);
            }
            echo '</div>';

            // Sağ taraftaki ikili grup
            echo '<div class="seat-row">';
            for ($j = 3; $j <= 4; $j++) {
                $seat_number = ($i * $seats_per_row_group) + $j;
                echo createSeat($seat_number, $seat_data[$seat_number]['status']);
            }
            echo '</div>';

            echo '</div>';
        }
        ?>
    </div>
    
    <div class="summary-area">
        <p class="summary-text">You have selected <span id="selected-seats-count">0</span> seats for a price of RM<span id="total-price">0</span></p>
    </div>
    <button class="next-button">Next</button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const seats = document.querySelectorAll('.seat-box');
        const selectedSeats = new Set();
        const seatsCountSpan = document.getElementById('selected-seats-count');
        const totalPriceSpan = document.getElementById('total-price');
        const pricePerSeat = 15; // Fiyatı buradan ayarlayabilirsiniz

        seats.forEach(seat => {
            seat.addEventListener('click', () => {
                const seatNumber = seat.dataset.seatNumber;

                if (seat.classList.contains('occupied')) {
                    return;
                }

                if (selectedSeats.has(seatNumber)) {
                    selectedSeats.delete(seatNumber);
                    seat.classList.remove('selected');
                } else {
                    selectedSeats.add(seatNumber);
                    seat.classList.add('selected');
                }

                seatsCountSpan.textContent = selectedSeats.size;
                totalPriceSpan.textContent = selectedSeats.size * pricePerSeat;
            });
        });
    });
</script>

</body>
</html>