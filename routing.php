<?php

if (!function_exists('routing_is_enabled')) {
    function routing_is_enabled(array $cfg): bool
    {
        $routing = $cfg['routing'] ?? [];
        return !empty($routing['enabled']) && ($routing['provider'] ?? 'openrouteservice') === 'openrouteservice';
    }
}

if (!function_exists('routing_origin_coordinates')) {
    function routing_origin_coordinates(array $cfg): ?array
    {
        $routing = $cfg['routing'] ?? [];
        $lat = isset($routing['origin_coordinates']['lat']) ? (float)$routing['origin_coordinates']['lat'] : null;
        $lon = isset($routing['origin_coordinates']['lon']) ? (float)$routing['origin_coordinates']['lon'] : null;
        if (!is_finite($lat) || !is_finite($lon)) {
            return null;
        }
        return ['lat' => $lat, 'lon' => $lon];
    }
}

if (!function_exists('routing_cache_dir')) {
    function routing_cache_dir(): string
    {
        $dir = __DIR__ . '/temp/routing_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('routing_cache_ttl')) {
    function routing_cache_ttl(array $cfg): int
    {
        $minutes = isset($cfg['routing']['cache_ttl_minutes']) ? (int)$cfg['routing']['cache_ttl_minutes'] : 720;
        if ($minutes <= 0) {
            $minutes = 720;
        }
        return $minutes * 60;
    }
}

if (!function_exists('routing_cache_key')) {
    function routing_cache_key(array $origin, array $jobs, bool $returnToOrigin): string
    {
        $payload = [
            'origin' => [round($origin['lat'], 6), round($origin['lon'], 6)],
            'jobs' => array_map(function (array $job) {
                return [
                    'id' => (string)$job['id'],
                    'lat' => round((float)$job['lat'], 6),
                    'lon' => round((float)$job['lon'], 6),
                ];
            }, $jobs),
            'return_to_origin' => $returnToOrigin,
        ];
        return hash('sha256', json_encode($payload));
    }
}

if (!function_exists('routing_cache_path')) {
    function routing_cache_path(string $key): string
    {
        return rtrim(routing_cache_dir(), '/\\') . '/' . $key . '.json';
    }
}

if (!function_exists('routing_cache_get')) {
    function routing_cache_get(string $key, array $cfg): ?array
    {
        $path = routing_cache_path($key);
        if (!is_file($path)) {
            return null;
        }
        $ttl = routing_cache_ttl($cfg);
        if ($ttl > 0) {
            $mtime = @filemtime($path);
            if ($mtime !== false && (time() - $mtime) > $ttl) {
                @unlink($path);
                return null;
            }
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('routing_cache_set')) {
    function routing_cache_set(string $key, array $cfg, array $data): void
    {
        $path = routing_cache_path($key);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        @file_put_contents($path, $payload, LOCK_EX);
    }
}

if (!function_exists('routing_log_error')) {
    function routing_log_error(string $message, array $context = []): void
    {
        $logPath = __DIR__ . '/error.log';
        $timestamp = gmdate('c');
        $line = sprintf('[%s] ROUTING %s', $timestamp, $message);
        if (!empty($context)) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $line .= ' ' . $encoded;
            }
        }
        $line .= PHP_EOL;
        @file_put_contents($logPath, $line, FILE_APPEND);
    }
}

if (!function_exists('routing_pause_between_requests')) {
    function routing_pause_between_requests(array $cfg): void
    {
        $ms = isset($cfg['routing']['request_pause_ms']) ? (int)$cfg['routing']['request_pause_ms'] : 220;
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}

if (!function_exists('routing_http_request')) {
    function routing_http_request(string $method, string $path, array $payload, array $cfg, int $timeout = 45): array
    {
        $routing = $cfg['routing'] ?? [];
        $baseUrl = rtrim((string)($routing['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('routing_base_url_missing');
        }
        $apiKey = (string)($routing['api_key'] ?? '');
        if ($apiKey === '') {
            throw new RuntimeException('routing_api_key_missing');
        }
        $url = $baseUrl . '/' . ltrim($path, '/');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('routing_payload_encode_failed');
        }
        $headers = [
            'Accept: application/json',
            'Authorization: ' . $apiKey,
        ];
        if (strtoupper($method) !== 'GET') {
            $headers[] = 'Content-Type: application/json';
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            if (strtoupper($method) !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($ch);
            if ($response === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new RuntimeException('routing_http_error: ' . $err);
            }
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } else {
            $opts = [
                'http' => [
                    'method' => strtoupper($method),
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'content' => strtoupper($method) === 'GET' ? null : $body,
                    'timeout' => $timeout,
                ],
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                $error = error_get_last();
                throw new RuntimeException('routing_http_error: ' . ($error['message'] ?? 'stream error'));
            }
            $status = 200;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $headerLine, $m)) {
                        $status = (int)$m[1];
                        break;
                    }
                }
            }
        }
        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('routing_bad_response');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('routing_http_status_' . $status);
        }
        return $decoded;
    }
}

if (!function_exists('routing_sanitize_geometry')) {
    function routing_sanitize_geometry($geometry)
    {
        if (is_string($geometry) && trim($geometry) !== '') {
            $decoded = json_decode($geometry, true);
            if (is_array($decoded)) {
                $geometry = $decoded;
            }
        }
        if (!is_array($geometry)) {
            return null;
        }
        $type = isset($geometry['type']) ? (string)$geometry['type'] : '';
        $coords = $geometry['coordinates'] ?? null;
        if (!is_array($coords)) {
            return null;
        }
        $limit = 1500;
        $sanitizeLine = static function (array $line) use ($limit): array {
            $out = [];
            foreach ($line as $pair) {
                if (!is_array($pair) || count($pair) < 2) {
                    continue;
                }
                $lon = (float)$pair[0];
                $lat = (float)$pair[1];
                if (!is_finite($lat) || !is_finite($lon)) {
                    continue;
                }
                $out[] = [$lon, $lat];
                if (count($out) >= $limit) {
                    break;
                }
            }
            return $out;
        };
        if ($type === 'LineString') {
            $line = $sanitizeLine($coords);
            if (count($line) < 2) {
                return null;
            }
            return ['type' => 'LineString', 'coordinates' => $line];
        }
        if ($type === 'MultiLineString') {
            $lines = [];
            foreach ($coords as $segment) {
                if (!is_array($segment)) {
                    continue;
                }
                $clean = $sanitizeLine($segment);
                if (count($clean) >= 2) {
                    $lines[] = $clean;
                }
                if (count($lines) >= 12) {
                    break;
                }
            }
            if (!$lines) {
                return null;
            }
            return ['type' => 'MultiLineString', 'coordinates' => $lines];
        }
        return null;
    }
}

if (!function_exists('routing_build_jobs')) {
    function routing_build_jobs(array $items, int $roundId): array
    {
        $jobs = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rid = (int)($item['round'] ?? 0);
            if ($rid !== $roundId) {
                continue;
            }
            $lat = isset($item['lat']) ? (float)$item['lat'] : null;
            $lon = isset($item['lon']) ? (float)$item['lon'] : null;
            if (!is_finite($lat) || !is_finite($lon)) {
                continue;
            }
            if (!empty($item['needs_geocode'])) {
                continue;
            }
            $id = isset($item['id']) ? (string)$item['id'] : '';
            if ($id === '') {
                continue;
            }
            $jobs[] = [
                'id' => $id,
                'lat' => $lat,
                'lon' => $lon,
            ];
        }
        return $jobs;
    }
}

if (!function_exists('routing_nearest_neighbour')) {
    function routing_nearest_neighbour(array $matrix, bool $returnToOrigin): array
    {
        $ids = isset($matrix['ids']) && is_array($matrix['ids']) ? array_values($matrix['ids']) : [];
        $distances = isset($matrix['distances']) && is_array($matrix['distances']) ? $matrix['distances'] : [];
        $durations = isset($matrix['durations']) && is_array($matrix['durations']) ? $matrix['durations'] : [];
        $count = count($ids);
        if ($count <= 1) {
            return [
                'order' => [],
                'distance' => 0.0,
                'duration' => 0.0,
            ];
        }
        $matrixDistances = $distances;
        $matrixDurations = $durations;
        $originIndex = 0;
        $remaining = array_keys($ids);
        array_shift($remaining);
        $order = [];
        $totalDistance = 0.0;
        $totalDuration = 0.0;
        $current = $originIndex;
        while (!empty($remaining)) {
            $bestIndex = null;
            $bestDistance = INF;
            foreach ($remaining as $candidate) {
                $dist = $matrixDistances[$current][$candidate] ?? INF;
                if ($dist < $bestDistance) {
                    $bestDistance = $dist;
                    $bestIndex = $candidate;
                }
            }
            if ($bestIndex === null) {
                break;
            }
            $order[] = $ids[$bestIndex];
            $totalDistance += (float)($matrixDistances[$current][$bestIndex] ?? 0.0);
            $totalDuration += (float)($matrixDurations[$current][$bestIndex] ?? 0.0);
            $current = $bestIndex;
            $remaining = array_values(array_filter($remaining, static function ($value) use ($bestIndex) {
                return $value !== $bestIndex;
            }));
        }
        if ($returnToOrigin && $current !== $originIndex) {
            $totalDistance += (float)($matrixDistances[$current][$originIndex] ?? 0.0);
            $totalDuration += (float)($matrixDurations[$current][$originIndex] ?? 0.0);
        }
        return [
            'order' => $order,
            'distance' => $totalDistance,
            'duration' => $totalDuration,
        ];
    }
}

if (!function_exists('routing_fetch_matrix')) {
    function routing_fetch_matrix(array $jobs, array $origin, array $cfg, bool $returnToOrigin): array
    {
        $routing = $cfg['routing'] ?? [];
        $profile = $routing['profile'] ?? 'driving-car';
        $maxLocations = isset($routing['matrix_max_locations']) ? (int)$routing['matrix_max_locations'] : 40;
        if ($maxLocations < 3) {
            $maxLocations = 40;
        }
        $chunkSize = isset($routing['matrix_chunk_size']) ? (int)$routing['matrix_chunk_size'] : ($maxLocations - 1);
        if ($chunkSize < 1) {
            $chunkSize = $maxLocations - 1;
        }
        if ($chunkSize < 1) {
            $chunkSize = 1;
        }
        if ($chunkSize > $maxLocations - 1) {
            $chunkSize = $maxLocations - 1;
        }
        if ($chunkSize < 1) {
            $chunkSize = 1;
        }
        $locations = [];
        $ids = [];
        $ids[] = null; // origin placeholder
        $locations[] = [(float)$origin['lon'], (float)$origin['lat']];
        foreach ($jobs as $job) {
            $ids[] = (string)$job['id'];
            $locations[] = [(float)$job['lon'], (float)$job['lat']];
        }
        $count = count($locations);
        $distances = array_fill(0, $count, array_fill(0, $count, INF));
        $durations = array_fill(0, $count, array_fill(0, $count, INF));
        $destinations = range(0, $count - 1);
        for ($sourceIndex = 0; $sourceIndex < $count; $sourceIndex++) {
            for ($destStart = 0; $destStart < $count; $destStart += $chunkSize) {
                $destChunk = array_slice($destinations, $destStart, $chunkSize);
                if (empty($destChunk)) {
                    continue;
                }
                $subset = array_values(array_unique(array_merge([$sourceIndex], $destChunk)));
                $subsetLocations = [];
                $indexMap = [];
                foreach ($subset as $pos => $originalIndex) {
                    $subsetLocations[] = $locations[$originalIndex];
                    $indexMap[$originalIndex] = $pos;
                }
                $sourceLocal = [$indexMap[$sourceIndex]];
                $destLocal = [];
                foreach ($destChunk as $destIndex) {
                    $destLocal[] = $indexMap[$destIndex];
                }
                $payload = [
                    'locations' => $subsetLocations,
                    'metrics' => ['distance', 'duration'],
                    'sources' => $sourceLocal,
                    'destinations' => $destLocal,
                ];
                $response = routing_http_request('POST', '/matrix/' . $profile, $payload, $cfg);
                if (!isset($response['distances']) || !is_array($response['distances'])) {
                    throw new RuntimeException('routing_matrix_missing_distances');
                }
                $distanceRow = $response['distances'][0] ?? [];
                $durationRow = $response['durations'][0] ?? [];
                foreach ($destChunk as $idx => $destIndex) {
                    $distances[$sourceIndex][$destIndex] = isset($distanceRow[$idx]) ? (float)$distanceRow[$idx] : INF;
                    $durations[$sourceIndex][$destIndex] = isset($durationRow[$idx]) ? (float)$durationRow[$idx] : INF;
                }
                routing_pause_between_requests($cfg);
            }
            $distances[$sourceIndex][$sourceIndex] = 0.0;
            $durations[$sourceIndex][$sourceIndex] = 0.0;
        }
        return [
            'locations' => $locations,
            'ids' => $ids,
            'distances' => $distances,
            'durations' => $durations,
            'return_to_origin' => $returnToOrigin,
            'profile' => $profile,
        ];
    }
}

if (!function_exists('routing_fetch_directions')) {
    function routing_fetch_directions(array $coordinates, array $cfg, string $profile): array
    {
        $payload = [
            'coordinates' => $coordinates,
            'instructions' => false,
            'geometry_format' => 'geojson',
        ];
        $response = routing_http_request('POST', '/directions/' . $profile, $payload, $cfg);
        $route = $response['routes'][0] ?? null;
        if (!is_array($route)) {
            throw new RuntimeException('routing_directions_missing_route');
        }
        $summary = $route['summary'] ?? [];
        $distance = isset($summary['distance']) ? (float)$summary['distance'] : null;
        $duration = isset($summary['duration']) ? (float)$summary['duration'] : null;
        $geometry = $route['geometry'] ?? null;
        return [
            'distance' => $distance,
            'duration' => $duration,
            'geometry' => routing_sanitize_geometry($geometry),
        ];
    }
}

if (!function_exists('routing_run_optimization')) {
    function routing_run_optimization(array $jobs, array $origin, array $cfg, bool $returnToOrigin): array
    {
        $routing = $cfg['routing'] ?? [];
        $profile = $routing['profile'] ?? 'driving-car';
        $jobPayload = [];
        $jobIdMap = [];
        $counter = 1;
        foreach ($jobs as $job) {
            $jobId = $counter++;
            $jobPayload[] = [
                'id' => $jobId,
                'service' => 0,
                'location' => [(float)$job['lon'], (float)$job['lat']],
            ];
            $jobIdMap[$jobId] = (string)$job['id'];
        }
        $vehicle = [
            'id' => 1,
            'profile' => $profile,
            'start' => [(float)$origin['lon'], (float)$origin['lat']],
        ];
        if ($returnToOrigin) {
            $vehicle['end'] = [(float)$origin['lon'], (float)$origin['lat']];
        }
        $payload = [
            'jobs' => $jobPayload,
            'vehicles' => [$vehicle],
        ];
        $response = routing_http_request('POST', '/optimization', $payload, $cfg, 60);
        $routes = $response['routes'] ?? [];
        if (empty($routes)) {
            throw new RuntimeException('routing_optimization_missing_route');
        }
        $steps = $routes[0]['steps'] ?? [];
        $order = [];
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            if (($step['type'] ?? '') !== 'job') {
                continue;
            }
            $jobId = isset($step['id']) ? (int)$step['id'] : null;
            if ($jobId === null) {
                continue;
            }
            if (isset($jobIdMap[$jobId])) {
                $order[] = $jobIdMap[$jobId];
            }
        }
        if (empty($order) && !empty($jobIdMap)) {
            throw new RuntimeException('routing_optimization_empty_order');
        }
        $coordinates = [];
        $coordinates[] = [(float)$origin['lon'], (float)$origin['lat']];
        foreach ($order as $id) {
            foreach ($jobs as $job) {
                if ((string)$job['id'] === $id) {
                    $coordinates[] = [(float)$job['lon'], (float)$job['lat']];
                    break;
                }
            }
        }
        if ($returnToOrigin) {
            $coordinates[] = [(float)$origin['lon'], (float)$origin['lat']];
        }
        $directions = routing_fetch_directions($coordinates, $cfg, $profile);
        return [
            'order' => $order,
            'distance' => $directions['distance'],
            'duration' => $directions['duration'],
            'geometry' => $directions['geometry'],
            'source' => 'optimization',
        ];
    }
}

if (!function_exists('routing_plan_via_matrix')) {
    function routing_plan_via_matrix(array $jobs, array $origin, array $cfg, bool $returnToOrigin): array
    {
        $matrix = routing_fetch_matrix($jobs, $origin, $cfg, $returnToOrigin);
        $plan = routing_nearest_neighbour([
            'ids' => $matrix['ids'],
            'distances' => $matrix['distances'],
            'durations' => $matrix['durations'],
        ], $returnToOrigin);
        $orderIds = $plan['order'];
        $profile = $matrix['profile'];
        $coordinates = [];
        $coordinates[] = [(float)$origin['lon'], (float)$origin['lat']];
        foreach ($orderIds as $id) {
            foreach ($jobs as $job) {
                if ((string)$job['id'] === $id) {
                    $coordinates[] = [(float)$job['lon'], (float)$job['lat']];
                    break;
                }
            }
        }
        if ($returnToOrigin) {
            $coordinates[] = [(float)$origin['lon'], (float)$origin['lat']];
        }
        $directions = routing_fetch_directions($coordinates, $cfg, $profile);
        $distance = $directions['distance'] ?? $plan['distance'];
        $duration = $directions['duration'] ?? $plan['duration'];
        return [
            'order' => $orderIds,
            'distance' => $distance,
            'duration' => $duration,
            'geometry' => $directions['geometry'],
            'source' => 'matrix',
        ];
    }
}

if (!function_exists('routing_apply_route')) {
    function routing_apply_route(array $items, array $roundMeta, int $roundId, array $cfg): array
    {
        if (!routing_is_enabled($cfg)) {
            throw new RuntimeException('routing_disabled');
        }
        $origin = routing_origin_coordinates($cfg);
        if (!$origin) {
            throw new RuntimeException('routing_origin_missing');
        }
        $jobs = routing_build_jobs($items, $roundId);
        if (empty($jobs)) {
            throw new RuntimeException('routing_no_jobs');
        }
        $returnToOrigin = !empty($cfg['routing']['return_to_origin']);
        $cacheKey = routing_cache_key($origin, $jobs, $returnToOrigin);
        $result = routing_cache_get($cacheKey, $cfg);
        $fromCache = false;
        if (!$result) {
            try {
                $result = routing_run_optimization($jobs, $origin, $cfg, $returnToOrigin);
            } catch (Throwable $e) {
                routing_log_error('Optimization failed', ['error' => $e->getMessage(), 'round' => $roundId]);
                $result = null;
            }
            if (!$result) {
                try {
                    $result = routing_plan_via_matrix($jobs, $origin, $cfg, $returnToOrigin);
                } catch (Throwable $e) {
                    routing_log_error('Matrix fallback failed', ['error' => $e->getMessage(), 'round' => $roundId]);
                    throw $e;
                }
            }
            if ($result) {
                $result['cached_at'] = gmdate('c');
                routing_cache_set($cacheKey, $cfg, $result);
            }
        } else {
            $fromCache = true;
        }
        if (!$result) {
            throw new RuntimeException('routing_no_result');
        }
        $order = array_values(array_filter(array_map('strval', $result['order'] ?? [])));
        if (empty($order)) {
            throw new RuntimeException('routing_empty_order');
        }
        $orderMap = [];
        $pos = 1;
        foreach ($order as $id) {
            if (!isset($orderMap[$id])) {
                $orderMap[$id] = $pos++;
            }
        }
        $updatedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $updatedItems[] = $item;
                continue;
            }
            $rid = (int)($item['round'] ?? 0);
            if ($rid !== $roundId) {
                $updatedItems[] = $item;
                continue;
            }
            $id = isset($item['id']) ? (string)$item['id'] : '';
            $copy = $item;
            if (isset($orderMap[$id])) {
                $copy['utvonal_sorrend'] = $orderMap[$id];
            } else {
                unset($copy['utvonal_sorrend']);
            }
            $updatedItems[] = $copy;
        }
        $roundKey = (string)$roundId;
        $entry = isset($roundMeta[$roundKey]) && is_array($roundMeta[$roundKey]) ? $roundMeta[$roundKey] : [];
        $entry['sort_mode'] = 'route';
        $entry['route_info'] = [
            'distance_m' => isset($result['distance']) ? (float)$result['distance'] : null,
            'duration_s' => isset($result['duration']) ? (float)$result['duration'] : null,
            'geometry' => routing_sanitize_geometry($result['geometry'] ?? null),
            'hash' => $cacheKey,
            'updated_at' => gmdate('c'),
            'source' => $result['source'] ?? null,
            'from_cache' => $fromCache,
        ];
        $entry['route_order'] = $order;
        $roundMeta[$roundKey] = $entry;
        return [$updatedItems, $roundMeta, $order, $entry['route_info']];
    }
}

