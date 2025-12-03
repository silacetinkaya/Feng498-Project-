<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pricely – Discover Nearby Businesses</title>

    <!-- Leaflet CSS (CDN) -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />

    <style>
        html, body {
            margin: 0;
            height: 100%;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .topbar {
            padding: 10px 16px;
            background: #e53935;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar-title {
            font-weight: 600;
        }

        .topbar-search {
            display: flex;
            gap: 8px;
        }

        .topbar-search input {
            padding: 6px 8px;
            border-radius: 999px;
            border: none;
            min-width: 220px;
        }

        .topbar-search select {
            padding: 6px 8px;
            border-radius: 999px;
            border: none;
        }

        #map {
            flex: 1;
        }
    </style>
</head>
<body>

<div class="page">
    <!-- Basit üst bar -->
    <header class="topbar">
        <div class="topbar-title">Pricely – Explore Businesses</div>
        <div class="topbar-search">
            <input type="text" id="searchInput" placeholder="Search by name…">
            <select id="categoryFilter">
                <option value="">All categories</option>
                <option value="hairdresser">Hairdresser</option>
                <option value="cafe">Café</option>
                <option value="repair">Repair</option>
            </select>
        </div>
    </header>

    <!-- MAP -->
    <div id="map"></div>
</div>

<!-- Leaflet JS (CDN) -->
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin="">
</script>

<script>
// ===============
// FAKE DATA (şimdilik)
// ===============
const businesses = [
    {
        id: 1,
        name: "Red Scissors Kuaför",
        category: "hairdresser",
        lat: 38.386,  // Balçova civarı
        lng: 27.053,
        address: "Balçova, İzmir",
        rating: 4.6
    },
    {
        id: 2,
        name: "Blue Cup Café",
        category: "cafe",
        lat: 38.423,  // Alsancak civarı
        lng: 27.142,
        address: "Alsancak, İzmir",
        rating: 4.2
    },
    {
        id: 3,
        name: "QuickFix Phone Repair",
        category: "repair",
        lat: 38.419,
        lng: 27.134,
        address: "Konak, İzmir",
        rating: 4.8
    }
];

// ===============
// MAP INIT
// ===============
const map = L.map('map').setView([38.41, 27.13], 12); // İzmir

// OpenStreetMap tile'ları:
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Marker'ları tutmak için:
let markers = [];

// popup içeriğini yapan fonksiyon
function createPopupContent(b) {
    return `
        <strong>${b.name}</strong><br>
        Category: ${b.category}<br>
        Rating: ${b.rating ?? "–"} ★<br>
        <small>${b.address}</small><br><br>
        <a href="business_detail.php?id=${b.id}">View details</a>
    `;
}

// haritaya marker ekleyen fonksiyon
function renderMarkers(filteredList) {
    // önce eskileri sil
    markers.forEach(m => map.removeLayer(m));
    markers = [];

    filteredList.forEach(b => {
        const marker = L.marker([b.lat, b.lng]).addTo(map);
        marker.bindPopup(createPopupContent(b));
        markers.push(marker);
    });
}

// ilk render: hepsi
renderMarkers(businesses);

// ===============
// Filter / Search
// ===============
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');

function applyFilters() {
    const text = searchInput.value.toLowerCase();
    const cat  = categoryFilter.value;

    const filtered = businesses.filter(b => {
        const matchesText = b.name.toLowerCase().includes(text);
        const matchesCat  = !cat || b.category === cat;
        return matchesText && matchesCat;
    });

    renderMarkers(filtered);
}

searchInput.addEventListener('input', applyFilters);
categoryFilter.addEventListener('change', applyFilters);

</script>

</body>
</html>
