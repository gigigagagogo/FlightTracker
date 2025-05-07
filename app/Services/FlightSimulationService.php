<?php

namespace App\Services;
use App\Models\Flight;
use Carbon\Carbon;
use Log;

class FlightSimulationService{
    public function simulateFlight(int $flightId): array{

        $flight = Flight::with(['departureAirport', 'arrivalAirport'])->findOrFail($flightId);

        $startTime = Carbon::parse($flight->departure_time);
        $endTime = Carbon::parse($flight->arrival_time);
        $totalTime = $startTime->diffInSeconds($endTime, true);
        $progress = $this->getProgress($startTime, $totalTime);

        $startCoords = ['lat' => $flight->departureAirport->latitude, 'lng' => $flight->departureAirport->longitude];
        $endCoords = ['lat' => $flight->arrivalAirport->latitude, 'lng' => $flight->arrivalAirport->longitude];

        $distance = $this->haversineGreatCircleDistance($startCoords, $endCoords);
        $currentPosition = $this->getCoordinates($startCoords, $endCoords, $progress);
        $averageSpeed = $distance / $totalTime * 3.6; // km/h
        $speed = $this->getActualSpeed($progress, $averageSpeed);

        return [
            'lat' => $currentPosition['lat'],
            'lng' => $currentPosition['lng'],
            'velocita' => $speed,
            'stato' => $progress != 1 ? 'In volo' : 'Atterrato',
            'percentuale' => $progress * 100
        ];
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Haversine formula.
     * @param $startCoords
     * @param $endCoords
     * @param int $earthRadius Mean earth radius in [m]
     * @return float|int Distance between points in [m] (same as earthRadius)
     */
    function haversineGreatCircleDistance(
        $startCoords, $endCoords, int $earthRadius = 6371000): float|int
    {
        // convert from degrees to radians
        $latFrom = deg2rad($startCoords['lat']);
        $lonFrom = deg2rad($startCoords['lng']);
        $latTo = deg2rad($endCoords['lat']);
        $lonTo = deg2rad($endCoords['lng']);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    public function getProgress(Carbon $start, float $totalTime): float{
        $now = Carbon::now();

        $elapsedTime = $start->diffInSeconds($now, true);
        $progress = $elapsedTime / $totalTime;

        return max(0, min(1, $progress));
    }

    public function getCoordinates(array $start, array $end, float $progress):array{
        $lat = $start['lat'] + ($end['lat'] - $start['lat']) * $progress;
        $lng = $start['lng'] + ($end['lng'] - $start['lng']) * $progress;

        return [
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
        ];
    }

    public function getActualSpeed(float $progress, float $averageSpeed): float {
        $maxSpeed = $averageSpeed + 30;

        if ($progress < 0.1) {
            $speed = $maxSpeed * pow($progress / 0.1, 3);
        } elseif ($progress < 0.9) {
            $speed = $maxSpeed + rand(-1, 1);
        } else {
            $speed = $maxSpeed * pow((1 - $progress) / 0.1, 3);
        }

        return round($speed);
    }


}
