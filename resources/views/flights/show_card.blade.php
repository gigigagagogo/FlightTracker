<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Monitoraggio Volo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/flights/show_card.css') }}" rel="stylesheet">
</head>
<body>
<div class="container flight-monitor mt-5">
    <h2 class="text-center mb-4">Monitoraggio Volo</h2>

    <div class="card shadow-sm p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-4 text-center">
                <img src="/{{ $flight->airplaneModel->image_path }}" alt="{{ $flight->airplaneModel->name }}"
                     class="airplane-image mb-3">
                <h5>{{ $flight->airplaneModel->name }}</h5>
            </div>

            <div class="col-md-8">
                <div class="info-block mb-3">
                    <strong>Partenza:</strong> {{ $flight->departureAirport->city }}
                    – {{ \Carbon\Carbon::parse($flight->departure_time)->format('d/m/Y H:i') }}<br>
                    <strong>Arrivo:</strong> {{ $flight->arrivalAirport->city }}
                    – {{ \Carbon\Carbon::parse($flight->arrival_time)->format('d/m/Y H:i') }}
                </div>

                <div class="info-block mb-2">
                    <strong>Coordinate attuali:</strong> <span id="current-coordinates">-- / --</span><br>
                    <strong>Velocità attuale:</strong> <span id="current-speed">-- km/h</span>
                </div>
            </div>
        </div>
    </div>
    <div id="map" style="height: 500px; width: 100%;"></div>

    <div class="text-center">
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">← Torna alla ricerca</a>
    </div>
</div>

<script>
    let map;
    /** @type {google.maps.OverlayView} */
    let overlay;
    let updates = -1;

    async function initMap() {

        class RotatableOverlay extends google.maps.OverlayView {
            constructor(position, imageUrl, angle) {
                super();
                this.position = position;
                this.imageUrl = imageUrl;
                this.angle = angle;
                this.div = null;
            }

            onAdd() {
                this.div = document.createElement('div');
                this.div.style.position = 'absolute';
                this.div.style.zIndex = '-1';
                this.div.innerHTML = `<img src="${this.imageUrl}" style="transform: rotate(${this.angle}deg); width:40px;" alt="plane">`;

                /** @type {google.maps.MapPanes} */
                const panes = this.getPanes();
                panes.overlayImage.appendChild(this.div);
            }

            draw() {
                const point = this.getProjection().fromLatLngToDivPixel(this.position);
                if (point && this.div) {
                    this.div.style.left = (point.x - 20) + 'px';
                    this.div.style.top = (point.y - 20) + 'px';
                }
            }

            onRemove() {
                if (this.div) {
                    this.div.remove();
                    this.div = null;
                }
            }

            setPosition(position) {
                this.position = position;
                this.draw();
            }

        }

        // Posizione iniziale temporanea
        const iniziale = new google.maps.LatLng(0, 0);

        // Crea mappa
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 5,
            center: iniziale,
        });

        const start_latitude = {{ $flight->departureAirport->latitude }};
        const start_longitude = {{ $flight->departureAirport->longitude }};
        const end_latitude = {{ $flight->arrivalAirport->latitude }};
        const end_longitude = {{ $flight->arrivalAirport->longitude }};

        let startPoint = new google.maps.LatLng(start_latitude, start_longitude);
        let endPoint = new google.maps.LatLng(end_latitude, end_longitude);


        const {spherical} = await google.maps.importLibrary("geometry");

        // Calcolo dell'angolo di direzione
        const heading = spherical.computeHeading(startPoint, endPoint);
        // Adatto l'angolo all'icona dell'aereo
        const iconHeading = -45 + heading

        // Creo overlay dell'aereo
        overlay = new RotatableOverlay(
            iniziale,
            '/images/plane-map-icon.svg',
            iconHeading
        );

        overlay.setMap(map);

        // Disegno la rotta
        const partenza = new google.maps.LatLng({{ $flight->departureAirport->latitude }}, {{ $flight->departureAirport->longitude }});
        const arrivo = new google.maps.LatLng({{ $flight->arrivalAirport->latitude }}, {{ $flight->arrivalAirport->longitude }});

        new google.maps.Polyline({
            path: [partenza, arrivo],
            geodesic: false,
            strokeColor: "#000",
            strokeOpacity: 0,
            strokeWeight: 2,
            zIndex: 1,
            icons: [{
                icon: {
                    path: 'M 0,-1 0,1', // tratteggio
                    strokeOpacity: 1,
                    scale: 4
                },
                offset: '0',
                repeat: '20px'
            }],
            map: map
        });

        // Avvia aggiornamenti
        const posizioneIniziale = await aggiornaVolo();
        if (posizioneIniziale) {
            map.panTo(posizioneIniziale);
        }
        setInterval(aggiornaVolo, 250);
    }

    async function aggiornaVolo() {
        try {
            const res = await fetch("{{ url('/api/simulazione-volo/' . $flight->id) }}");
            const data = await res.json();
            updates++;
            const nuovaPosizione = new google.maps.LatLng(data.lat, data.lng);

            overlay.setPosition(nuovaPosizione);

            // Aggiorno l'immagine in diretta ma i dati solo ogni 20 tick (5s)
            if (data.percentuale < 10 || data.percentuale > 90 || updates % 20 === 0) {
                document.getElementById("current-speed").innerText =
                    `${parseInt(data.velocita)} km/h`;
            }

            if (updates % 20 === 0) {
                document.getElementById("current-coordinates").innerText =
                    `${nuovaPosizione.lat().toFixed(4)} / ${nuovaPosizione.lng().toFixed(4)}`;
            }

            return nuovaPosizione;
        } catch (err) {
            console.error("Errore durante la richiesta:", err);
            return null;
        }
    }

</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API') }}&libraries=geometry&callback=initMap&loading=async"
    async defer></script>

</body>
</html>
