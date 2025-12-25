<?php
require "db_connect.php";

// TÜM BUSINESS'LARI ÇEK
$stmt = $pdo->prepare("
    SELECT 
        b.shop_id,
        b.name,
        b.category,
        b.latitude,
        b.longitude,
        b.description,
        b.tel_no,
        COALESCE(AVG(r.rank), 0) AS rating
    FROM business b
    LEFT JOIN reviews r ON r.business_id = b.shop_id
    GROUP BY b.shop_id
");
$stmt->execute();
$businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pricely – Discover Nearby Businesses</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

    <style>
        html, body {
            margin: 0;
            height: 100%;
            font-family: system-ui;
        }

        .page {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .topbar {
            padding: 10px 16px;
            background: #e53935;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        #map {
            flex: 1;
        }

        .topbar input, .topbar select {
            padding: 6px 10px;
            border-radius: 999px;
            border: none;
        }
    </style>
</head>
<body>

<div class="page">

    <!-- TOP BAR -->
    <header class="topbar">
        <div style="font-weight:700;">Pricely – Explore</div>

        <div>
            <input type="text" id="searchInput" placeholder="Search business...">

            <select id="categoryFilter">
                <option value="">All Categories</option>
                <option value="Repair">Repair</option>
                <option value="Hair Dresser">Hair Dresser</option>
                <option value="Grocery">Grocery</option>
                <option value="Restaurant">Restaurant</option>
                <option value="Cafe">Cafe</option>
                <option value="Kiosk">Kiosk</option>
                <option value="Nail Bar">Nail Bar</option>
                <option value="Pub">Pub</option>
                <option value="Club">Club</option>
                <option value="Bakery">Bakery</option>
                <option value="Flower Shop">Flower Shop</option>
                <option value="Pet-Shop">Pet Shop</option>
                <option value="Gym">Gym</option>
                <option value="Tattoo">Tattoo</option>
            </select>
        </div>
    </header>

    <!-- MAP -->
    <div id="map"></div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// PHP → JS business verisi aktarılıyor
const businesses = <?= json_encode($businesses) ?>;

// MAP INIT
const map = L.map('map').setView([38.4192, 27.1287], 12); // İzmir default

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);

let markers = [];

// Marker popup yapıcı
function createPopup(b) {
    return `
        <strong>${b.name}</strong><br>
        Category: ${b.category}<br>
        Rating: ${b.rating ? b.rating.toFixed(1) : "No reviews"} ★<br>
        <a href="business_detail.php?id=${b.shop_id}">
            View details
        </a>
    `;
}

// Markerları çizdir
function renderMarkers(list) {
    markers.forEach(m => map.removeLayer(m));
    markers = [];

    list.forEach(b => {
        if (!b.latitude || !b.longitude) return;

        let marker = L.marker([b.latitude, b.longitude]).addTo(map);
        marker.bindPopup(createPopup(b));
        markers.push(marker);
    });
}

// İlk çizim
renderMarkers(businesses);

// Filtering
const searchInput = document.getElementById("searchInput");
const categoryFilter = document.getElementById("categoryFilter");

function applyFilters() {
    const txt = searchInput.value.toLowerCase();
    const cat = categoryFilter.value;

    const filtered = businesses.filter(b => {
        const matchTxt = b.name.toLowerCase().includes(txt);
        const matchCat = !cat || b.category === cat;
        return matchTxt && matchCat;
    });

    renderMarkers(filtered);
}

searchInput.addEventListener("input", applyFilters);
categoryFilter.addEventListener("change", applyFilters);

</script>

</body>
</html>
