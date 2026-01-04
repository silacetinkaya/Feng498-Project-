<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pricely – Discover Nearby Businesses</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

    <style>
        /* RESET */
* {
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    background: #f5f5f5;
}

/* PAGE */
.page {
    display: flex;
    flex-direction: column;
    height: 100vh;
}

/* TOP BAR */
.topbar {
    height: 60px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    background: #ffffff;
    border-bottom: 3px solid #d32f2f; /* KIRMIZI VURGU */
    z-index: 10;
}

.topbar div:first-child {
    font-weight: 700;
    font-size: 16px;
    color: #d32f2f; /* KIRMIZI BAŞLIK */
}

/* INPUTS */
.topbar input,
.topbar select {
    height: 36px;
    padding: 0 10px;
    border-radius: 4px;
    border: 1px solid #ccc;
    background: #fff;
    font-size: 13px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.topbar input {
    width: 200px;
    margin-right: 8px;
}

.topbar input:focus,
.topbar select:focus {
    outline: none;
    border-color: #d32f2f;
    box-shadow: 0 0 0 2px rgba(211,47,47,0.15);
}

/* MAP */
#map {
    flex: 1;
    width: 100%;
    height: calc(100vh - 60px);
    background: #e0e0e0;
}

/* LEAFLET CONTROLS */
.leaflet-control-zoom {
    border: 1px solid #ccc;
    box-shadow: none;
}

.leaflet-control-zoom a {
    background: #ffffff !important;
    color: #d32f2f !important; /* KIRMIZI ICON */
    border-bottom: 1px solid #ddd !important;
    font-weight: bold;
}

.leaflet-control-zoom a:last-child {
    border-bottom: none !important;
}

.leaflet-control-zoom a:hover {
    background: #fdecea !important; /* AÇIK KIRMIZI */
}

/* POPUP */
.leaflet-popup-content-wrapper {
    border-radius: 6px;
    border-top: 4px solid #d32f2f; /* KIRMIZI ŞERİT */
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.leaflet-popup-content {
    font-size: 13px;
    color: #333;
    margin: 10px;
}

.leaflet-popup-content strong {
    color: #d32f2f;
}

.leaflet-popup-tip {
    box-shadow: none;
}
        
    </style>
</head>
<body class="iframe-mode">


<div class="page">

    <!-- TOP BAR -->
    <header class="topbar">
        <div>Pricely – Explore</div>

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
// MAP INIT
const map = L.map('map').setView([38.4192, 27.1287], 12);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);

let markers = [];
let allBusinesses = [];

// Popup
function createPopup(b) {
    return `
        <strong>${b.name}</strong><br>
        Category: ${b.category}<br>
        Rating: ${parseFloat(b.rating).toFixed(1)} ★<br><br>
        <a href="business_detail.php?id=${b.shop_id}"
           style="color:#d32f2f; font-weight:600;">
            View details →
        </a>
    `;
}

// Marker çiz
function renderMarkers(list) {
    markers.forEach(m => map.removeLayer(m));
    markers = [];

    list.forEach(b => {
        if (!b.latitude || !b.longitude) return;

        const m = L.marker([b.latitude, b.longitude]).addTo(map);
        m.bindPopup(createPopup(b));
        markers.push(m);
    });

    if (markers.length) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds(), { padding: [40, 40] });
    }
}

// API
function loadBusinesses() {
    fetch("get_businesses.php")
        .then(r => r.json())
        .then(data => {
            allBusinesses = data;
            applyFilters();
        });
}

// Filters
function applyFilters() {
    const txt = document.getElementById("searchInput").value.toLowerCase();
    const cat = document.getElementById("categoryFilter").value;

    const filtered = allBusinesses.filter(b => {
        const matchTxt = b.name.toLowerCase().includes(txt);
        const matchCat = !cat || b.category === cat;
        return matchTxt && matchCat;
    });

    renderMarkers(filtered);
}

document.getElementById("searchInput").addEventListener("input", applyFilters);
document.getElementById("categoryFilter").addEventListener("change", applyFilters);

// Init
loadBusinesses();
setInterval(loadBusinesses, 5000);
</script>

</body>
</html>
