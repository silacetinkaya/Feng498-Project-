let map;
let markers = [];

function initMap(divId) {
    map = L.map(divId).setView([38.4192, 27.1287], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    loadBusinesses();
}

function loadBusinesses() {
    fetch("get_businesses.php")
        .then(r => r.json())
        .then(data => {
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            data.forEach(b => {
                const m = L.marker([b.latitude, b.longitude]).addTo(map);
                m.bindPopup(`
                    <strong>${b.name}</strong><br>
                    ${b.category}<br>
                    ⭐ ${parseFloat(b.rating).toFixed(1)}
                `);
                markers.push(m);
            });

            if (markers.length) {
                const group = L.featureGroup(markers);
                map.fitBounds(group.getBounds());
            }
        });
}
