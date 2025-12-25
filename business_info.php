<?php
require 'db_connect.php';

// Business ID
$businessId = $business['shop_id'];

// Adresi DB'den çek
$stmt = $pdo->prepare("SELECT * FROM address WHERE business_id = :bid LIMIT 1");
$stmt->execute(['bid' => $businessId]);
$addr = $stmt->fetch(PDO::FETCH_ASSOC);

// Adres yoksa boş değerler oluştur
if (!$addr) {
    $addr = [
        'city'         => '',
        'district'     => '',
        'neighbourhood'=> '',
        'country'      => 'Turkey',
        'address'      => ''
    ];
}

// Business kategorileri
$categories = [
    "Repair","Hair Dresser","Grocery","Restaurant","Cafe",
    "Kiosk","Nail Bar","Pub","Club","Bakery",
    "Flower Shop","Pet-Shop","Gym","Tattoo"
];
?>

<h2>Business Information</h2>

<form method="POST">
    <input type="hidden" name="update_business" value="1">
    <input type="hidden" name="lat" id="latInput">
    <input type="hidden" name="lng" id="lngInput">

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">

        <!-- SOL TARAF -->
        <div>

            <label>Business Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($business['name']) ?>" required>

            <label>Category</label>
            <select name="category" required>
                <option value="">Select...</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c ?>" <?= ($business['category'] == $c ? 'selected':'') ?>>
                        <?= $c ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Phone</label>
            <input type="text" name="tel_no" value="<?= htmlspecialchars($business['tel_no']) ?>">

            <label>Description</label>
            <textarea name="description"><?= htmlspecialchars($business['description']) ?></textarea>

            <!-- Şehir -->
            <label>City</label>
            <select id="citySelect" name="city" required>
                <option value="">Select city...</option>
            </select>

            <!-- İlçe -->
            <label>District</label>
            <select id="districtSelect" name="district" required>
                <option value="">Select district...</option>
            </select>

            <!-- Mahalle -->
            <label>Neighbourhood</label>
            <select id="neighbourhoodSelect" name="neighbourhood" required>
                <option value="">Select neighbourhood...</option>
            </select>

            <!-- Tam adres -->
            <label>Full Address</label>
            <textarea name="full_address" required><?= htmlspecialchars($addr['address']); ?></textarea>
        </div>

        <!-- SAĞ TARAF: HARİTA -->
        <div>
            <h4>Select Business Location</h4>
            <p>Click OR drag the marker to set your business location.</p>

            <div id="map" style="height:300px; border-radius:10px;"></div>
        </div>
    </div>

    <button type="submit" style="margin-top:20px;">Save Changes</button>
</form>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ------------------------
// 1) HARİTA BAŞLATMA
// ------------------------

let defaultLat = <?= $business['latitude'] ?: "38.4192" ?>;
let defaultLng = <?= $business['longitude'] ?: "27.1287" ?>;

const map = L.map('map').setView([defaultLat, defaultLng], 12);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);

let marker = L.marker([defaultLat, defaultLng], {draggable:true}).addTo(map);

// Form inputlara ilk değerleri yaz
document.getElementById("latInput").value = defaultLat;
document.getElementById("lngInput").value = defaultLng;

// Harita tıklandı → marker taşı
map.on("click", function(e){
    marker.setLatLng(e.latlng);
    document.getElementById("latInput").value = e.latlng.lat;
    document.getElementById("lngInput").value = e.latlng.lng;
});

// Marker sürüklendi → form değerlerini güncelle
marker.on("dragend", function(){
    let pos = marker.getLatLng();
    document.getElementById("latInput").value = pos.lat;
    document.getElementById("lngInput").value = pos.lng;
});

// ------------------------
// 2) TÜRKİYE JSON DROPDOWN
// ------------------------

let turkeyData = [];
let citySelect = document.getElementById("citySelect");
let districtSelect = document.getElementById("districtSelect");
let neighbourhoodSelect = document.getElementById("neighbourhoodSelect");

// JSON YÜKLE
fetch("turkey.json")
    .then(res => res.json())
    .then(data => {
        turkeyData = data;

        // Şehirleri yükle
        data.forEach(province => {
            let opt = document.createElement("option");
            opt.value = province.Province;
            opt.textContent = province.Province;

            if ("<?= $addr['city'] ?>" === province.Province)
                opt.selected = true;

            citySelect.appendChild(opt);
        });

        // İlçe & mahalle doldur (DB önceden seçiliyse)
        if ("<?= $addr['city'] ?>") {
            fillDistricts("<?= $addr['city'] ?>");
            districtSelect.value = "<?= $addr['district'] ?>";
        }
        if ("<?= $addr['district'] ?>") {
            fillNeighbourhoods("<?= $addr['city'] ?>", "<?= $addr['district'] ?>");
            neighbourhoodSelect.value = "<?= $addr['neighbourhood'] ?>";
        }
    });

// ŞEHİR → İLÇE
function fillDistricts(cityName) {
    districtSelect.innerHTML = '<option value="">Select district...</option>';
    neighbourhoodSelect.innerHTML = '<option value="">Select neighbourhood...</option>';

    let city = turkeyData.find(c => c.Province === cityName);
    if (!city) return;

    city.Districts.forEach(dist => {
        let opt = document.createElement("option");
        opt.value = dist.District;
        opt.textContent = dist.District;
        districtSelect.appendChild(opt);
    });
}

// İLÇE → MAHALLE
function fillNeighbourhoods(cityName, districtName) {
    neighbourhoodSelect.innerHTML = '<option value="">Select neighbourhood...</option>';

    let city = turkeyData.find(c => c.Province === cityName);
    if (!city) return;

    let district = city.Districts.find(d => d.District === districtName);
    if (!district) return;

    district.Towns.forEach(town => {
        town.Neighbourhoods.forEach(n => {
            let opt = document.createElement("option");
            opt.value = n;
            opt.textContent = n;
            neighbourhoodSelect.appendChild(opt);
        });
    });
}

// ------------------------
// DROPDOWN DEĞİŞİNCE ZOOM
// ------------------------

citySelect.addEventListener("change", () => {
    fillDistricts(citySelect.value);

    let city = turkeyData.find(c => c.Province === citySelect.value);
    if (city) {
        let [lat, lng] = city.Coordinates.split(",").map(Number);
        map.setView([lat, lng], 9);
        marker.setLatLng([lat, lng]);
    }
});

districtSelect.addEventListener("change", () => {
    fillNeighbourhoods(citySelect.value, districtSelect.value);

    let city = turkeyData.find(c => c.Province === citySelect.value);
    let district = city?.Districts.find(d => d.District === districtSelect.value);

    if (district) {
        let [lat, lng] = district.Coordinates.split(",").map(Number);
        map.setView([lat, lng], 12);
        marker.setLatLng([lat, lng]);
    }
});
</script>
