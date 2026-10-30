<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/**
 * Menyusun scene peta isometrik dua tingkat.
 *
 * Tingkat 1 — Kabupaten Tuban sebagai satu pulau. Siluetnya memakai batas
 * wilayah sungguhan dari config/map.php, diproyeksikan isometrik lalu
 * diperbesar otomatis agar memenuhi kanvas. Pulau digambar berlapis supaya
 * terasa tebal, dan dibagi menjadi bidang-bidang kecamatan.
 *
 * Batas kecamatan dihitung sebagai poligon Thiessen (Voronoi) dari titik
 * pusat tiap kecamatan, dipotong garis pantai. Penanda kecamatan diletakkan
 * di tengah bidangnya, bukan di titik pusat mentah.
 *
 * Tingkat 2 — kecamatan terpilih diperbesar dan dipusatkan, lalu kantor
 * layanannya muncul melingkar. Jari-jari lingkaran menyesuaikan luas bidang,
 * dan setiap pin ditarik masuk bila jatuh di luar garis wilayah.
 */
final class MapScene
{
    /**
     * @param array<int, array<string, mixed>> $districts
     * @param array<int, array<string, mixed>> $locations
     * @param array<int, array<int, string>> $servicesByLocation
     * @return array<string, mixed>
     */
    public static function build(
        array $districts,
        array $locations,
        array $servicesByLocation = [],
        ?string $activeSlug = null
    ): array {
        // Perhitungannya berat (poligon, sebaran titik), tetapi hasilnya hanya
        // berubah bila data wilayah, kantor, atau pengaturan peta berubah.
        // Config ikut dihitung supaya menyetel config/map.php langsung terlihat
        // tanpa perlu mengosongkan cache manual.
        $key = 'scene:' . md5(serialize([
            $districts,
            $locations,
            $servicesByLocation,
            $activeSlug,
            config('map'),
            config('kecamatan'),
            config('desa'),
        ]));

        return Cache::remember($key, static fn (): array => self::compute(
            $districts,
            $locations,
            $servicesByLocation,
            $activeSlug
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $districts
     * @param array<int, array<string, mixed>> $locations
     * @param array<int, array<int, string>> $servicesByLocation
     * @return array<string, mixed>
     */
    private static function compute(
        array $districts,
        array $locations,
        array $servicesByLocation,
        ?string $activeSlug
    ): array {
        $config = (array) config('map', []);

        $scene = [
            'width' => (int) ($config['width'] ?? 1440),
            'height' => (int) ($config['height'] ?? 800),
            'island' => [],
            'districts' => [],
            'landmarks' => [],
        ];

        if ($districts === []) {
            return $scene;
        }

        $outline = (array) ($config['outline'] ?? []);
        $bounds = self::bounds($districts, $outline);

        $shore = [];

        foreach ($outline as $point) {
            $shore[] = self::project((float) $point[0], (float) $point[1], $bounds, $config);
        }

        // Penghalusan dilakukan setelah fit, dalam satuan piksel kanvas.

        $sites = [];

        foreach (array_values($districts) as $district) {
            $sites[(string) $district['slug']] = self::project(
                (float) $district['center_lng'],
                (float) $district['center_lat'],
                $bounds,
                $config
            );
        }

        // Batas asli tiap kecamatan bila tersedia; kalau tidak, nanti dipakai
        // poligon Thiessen sebagai cadangan.
        $real = [];

        foreach ((array) config('kecamatan', []) as $slug => $ring) {
            $points = [];

            foreach ((array) $ring as $point) {
                $points[] = self::project((float) $point[0], (float) $point[1], $bounds, $config);
            }

            if (count($points) >= 3) {
                $real[(string) $slug] = $points;
            }
        }

        $fit = self::fit($shore, $scene['width'], $scene['height'], $config);
        $shore = array_map($fit, $shore);
        $sites = array_map($fit, $sites);
        $real = array_map(static fn (array $ring): array => array_map($fit, $ring), $real);

        // Garis pantai untuk digambar: duri-duri kecil (meander sungai di
        // selatan) dibuang dulu, lalu sudutnya dibulatkan. Bentuk keseluruhan
        // tetap bentuk Tuban, hanya tepinya yang lebih enak dilihat.
        $drawn = self::chaikin(
            self::relax(
                self::simplifyPixels($shore, (float) ($config['shore_simplify'] ?? 7)),
                (int) ($config['shore_relax'] ?? 0),
                (float) ($config['shore_relax_strength'] ?? 0.5)
            ),
            (int) ($config['shore_smooth'] ?? 2)
        );

        $scene['island'] = self::island($drawn, (float) ($config['land_depth'] ?? 58));

        $cells = $real === [] ? self::voronoi($sites, $shore) : $real;

        // Kecamatan yang batas aslinya belum ada tetap dapat bidang perkiraan.
        $missing = array_diff(array_keys($sites), array_keys($cells));

        if ($missing !== [] && $real !== []) {
            foreach (self::voronoi($sites, $shore) as $slug => $cell) {
                if (in_array($slug, $missing, true)) {
                    $cells[$slug] = $cell;
                }
            }
        }

        $byDistrict = [];

        foreach ($locations as $location) {
            $byDistrict[(int) $location['district_id']][] = $location;
        }

        $ringX = (float) ($config['ring_x'] ?? 128);
        $ringY = (float) ($config['ring_y'] ?? 62);
        $villageSource = (array) config('desa', []);

        foreach (array_values($districts) as $district) {
            $slug = (string) $district['slug'];
            $cell = $cells[$slug] ?? [];

            // Penanda kecamatan duduk di tengah bidangnya.
            $center = self::cellCenter($cell, $sites[$slug]);
            [$x, $y] = $center;

            $items = $byDistrict[(int) $district['id']] ?? [];

            // Perbesaran menyesuaikan luas bidang: kecamatan sempit seperti
            // kota diperbesar lebih jauh supaya kantornya tidak bertumpuk.
            $zoom = self::zoomFor($cell, $scene['width'], $scene['height'], $config);

            // Lapis ketiga: batas desa/kelurahan di dalam kecamatan ini.
            $villages = [];

            foreach ((array) ($villageSource[$slug] ?? []) as $village) {
                $ring = [];

                foreach ((array) ($village['p'] ?? []) as $point) {
                    $ring[] = $fit(self::project((float) $point[0], (float) $point[1], $bounds, $config));
                }

                if (count($ring) < 3) {
                    continue;
                }

                $label = self::cellCenter($ring, $ring[0]);

                $villages[] = [
                    'name' => (string) ($village['n'] ?? ''),
                    'cell' => self::polygon($ring),
                    'lx' => round($label[0], 1),
                    'ly' => round($label[1], 1),
                ];
            }

            $scene['districts'][] = [
                'zoom' => $zoom,
                'villages' => $villages,
                'id' => (int) $district['id'],
                'name' => (string) $district['name'],
                'slug' => $slug,
                'summary' => (string) ($district['summary'] ?? ''),
                'places' => (int) ($district['places'] ?? count($items)),
                'queues_today' => (int) ($district['queues_today'] ?? 0),
                'active' => $activeSlug === $slug,
                'x' => round($x, 1),
                'y' => round($y, 1),
                'px' => round(($x / $scene['width']) * 100, 3),
                'py' => round(($y / $scene['height']) * 100, 3),
                'cell' => self::polygon($cell),
            ];

            usort($items, static fn (array $a, array $b): int => (float) $a['lng'] <=> (float) $b['lng']);

            $count = count($items);

            // Lingkaran kantor dikecilkan agar muat di dalam bidang kecamatan.
            $room = $cell === [] ? $ringX : self::distanceToEdges($center, $cell);
            $factor = max(0.14, min(1.0, ($room * 0.74) / max(1.0, $ringX)));

            // Bidang sempit atau memanjang tidak muat dilingkari. Untuk itu
            // titiknya disebar mengikuti bentuk bidang, bukan dibuat melingkar.
            $spots = ($factor < 0.6 || $count > 8) && $cell !== []
                ? self::spreadInside($cell, $count)
                : [];

            foreach (array_values($items) as $i => $location) {
                if (isset($spots[$i])) {
                    $spot = $spots[$i];
                } else {
                    $spot = self::ringSlot($x, $y, $i, $count, $ringX * $factor, $ringY * $factor);
                    $spot = self::pullInside($spot, $center, $cell);
                }

                $locationId = (int) $location['id'];
                $category = (string) $location['category'];

                $scene['landmarks'][] = [
                    'id' => $locationId,
                    'name' => (string) $location['name'],
                    'slug' => (string) $location['slug'],
                    'category' => $category,
                    'category_label' => (string) config('map.category_labels.' . $category, 'Layanan'),
                    'icon' => Icons::svg($category, 20),
                    'district_slug' => $slug,
                    'district_name' => (string) ($location['district_name'] ?? $district['name']),
                    'short_desc' => (string) ($location['short_desc'] ?? ''),
                    'address' => (string) ($location['address'] ?? ''),
                    'photo' => PhotoService::url((string) ($location['cover_image'] ?? '')) ?? '',
                    'quota_left' => (int) ($location['quota_left'] ?? 0),
                    'color' => self::categoryColor($category),
                    'dense' => $count > 9,
                    'services' => array_slice($servicesByLocation[$locationId] ?? [], 0, 5),
                    'service_count' => count($servicesByLocation[$locationId] ?? []),
                    'maps_url' => self::mapsUrl((float) $location['lat'], (float) $location['lng']),
                    'url' => url('/lokasi/' . $location['slug']),
                    'px' => round(($spot[0] / $scene['width']) * 100, 3),
                    'py' => round(($spot[1] / $scene['height']) * 100, 3),
                    'y' => round($spot[1], 1),
                ];
            }
        }

        usort($scene['districts'], static fn (array $a, array $b): int => $a['y'] <=> $b['y']);
        usort($scene['landmarks'], static fn (array $a, array $b): int => $a['y'] <=> $b['y']);

        return $scene;
    }

    public static function mapsUrl(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='
            . rawurlencode(number_format($lat, 6, '.', '') . ',' . number_format($lng, 6, '.', ''));
    }

    public static function categoryColor(string $category): string
    {
        $colors = (array) config('map.category_colors', []);

        return (string) ($colors[$category] ?? ($colors['lainnya'] ?? '#7E8C87'));
    }

    /* ------------------------------------------------------------- pulau */

    /**
     * @param array<int, array{0: float, 1: float}> $shore
     * @return array<string, mixed>
     */
    private static function island(array $shore, float $depth): array
    {
        if (count($shore) < 3) {
            return [];
        }

        $xs = array_column($shore, 0);
        $ys = array_column($shore, 1);

        return [
            'shallow' => self::polygon(self::offset($shore, 42)),
            'foam' => self::polygon(self::offset($shore, 18)),
            'shadow' => [
                'cx' => round((min($xs) + max($xs)) / 2, 1),
                'cy' => round(max($ys) + $depth * 0.7, 1),
                'rx' => round((max($xs) - min($xs)) / 2 * 0.94, 1),
                'ry' => round($depth * 0.46, 1),
            ],
            'rock' => self::polygon(self::shift($shore, $depth)),
            'soil' => self::polygon(self::shift($shore, $depth * 0.55)),
            'sand' => self::polygon(self::offset($shore, 9)),
            'top' => self::polygon($shore),
        ];
    }

    /**
     * Melebarkan poligon ke arah luar mengikuti normal tiap sudut.
     *
     * Berbeda dengan pemekaran radial dari titik tengah, cara ini tetap benar
     * pada bentuk berlekuk seperti garis pantai sungguhan: lebar tepinya
     * seragam di mana pun.
     *
     * @param array<int, array{0: float, 1: float}> $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function offset(array $points, float $distance): array
    {
        $n = count($points);

        if ($n < 3) {
            return $points;
        }

        // Arah putaran menentukan sisi mana yang "luar".
        $sign = self::signedArea($points) > 0 ? 1.0 : -1.0;

        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $prev = $points[($i - 1 + $n) % $n];
            $curr = $points[$i];
            $next = $points[($i + 1) % $n];

            $n1 = self::normal($prev, $curr, $sign);
            $n2 = self::normal($curr, $next, $sign);

            $bx = $n1[0] + $n2[0];
            $by = $n1[1] + $n2[1];
            $len = sqrt($bx * $bx + $by * $by);

            if ($len < 1e-9) {
                $out[] = $curr;

                continue;
            }

            $bx /= $len;
            $by /= $len;

            // Batasi sudut lancip agar tidak memanjang tak wajar.
            $miter = min(2.6, 1 / max(0.38, $bx * $n1[0] + $by * $n1[1]));

            $out[] = [$curr[0] + $bx * $distance * $miter, $curr[1] + $by * $distance * $miter];
        }

        return $out;
    }

    /**
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     * @return array{0: float, 1: float}
     */
    private static function normal(array $a, array $b, float $sign): array
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $len = sqrt($dx * $dx + $dy * $dy);

        if ($len < 1e-9) {
            return [0.0, 0.0];
        }

        return [($dy / $len) * $sign, (-$dx / $len) * $sign];
    }

    /**
     * @param array<int, array{0: float, 1: float}> $points
     */
    private static function signedArea(array $points): float
    {
        $sum = 0.0;
        $n = count($points);

        for ($i = 0; $i < $n; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $n];
            $sum += $a[0] * $b[1] - $b[0] * $a[1];
        }

        return $sum / 2;
    }

    /**
     * Pelemasan Laplacian: tiap titik ditarik ke tengah-tengah dua tetangganya.
     *
     * Berbeda dengan Chaikin yang hanya membulatkan sudut, cara ini juga
     * melandaikan lekukan besar — teluk dan tanjung ikut melunak, sehingga
     * siluetnya terasa membulat, bukan sekadar tumpul di sudut. Bentuk
     * mengerut setiap putaran, jadi di akhir dikembalikan ke rentang semula.
     *
     * @param array<int, array{0: float, 1: float}> $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function relax(array $points, int $iterations, float $strength): array
    {
        $n = count($points);

        if ($iterations < 1 || $n < 6) {
            return $points;
        }

        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        $before = [min($xs), max($xs), min($ys), max($ys)];

        for ($it = 0; $it < $iterations; $it++) {
            $next = [];

            for ($i = 0; $i < $n; $i++) {
                $prev = $points[($i - 1 + $n) % $n];
                $curr = $points[$i];
                $after = $points[($i + 1) % $n];

                $next[] = [
                    $curr[0] + $strength * (($prev[0] + $after[0]) / 2 - $curr[0]),
                    $curr[1] + $strength * (($prev[1] + $after[1]) / 2 - $curr[1]),
                ];
            }

            $points = $next;
        }

        // Kembalikan ukurannya agar pulau tidak menyusut dari bidang kecamatan.
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        $spanX = max(1e-6, max($xs) - min($xs));
        $spanY = max(1e-6, max($ys) - min($ys));

        $scaleX = ($before[1] - $before[0]) / $spanX;
        $scaleY = ($before[3] - $before[2]) / $spanY;

        $cx = (min($xs) + max($xs)) / 2;
        $cy = (min($ys) + max($ys)) / 2;
        $tx = ($before[0] + $before[1]) / 2;
        $ty = ($before[2] + $before[3]) / 2;

        return array_map(
            static fn (array $p): array => [
                $tx + ($p[0] - $cx) * $scaleX,
                $ty + ($p[1] - $cy) * $scaleY,
            ],
            $points
        );
    }

    /**
     * Douglas–Peucker dalam satuan piksel kanvas, untuk membuang tonjolan
     * kecil yang pada ukuran tampil hanya terbaca sebagai duri.
     *
     * @param array<int, array{0: float, 1: float}> $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function simplifyPixels(array $points, float $tolerance): array
    {
        if ($tolerance <= 0 || count($points) < 8) {
            return $points;
        }

        // Poligon tertutup: disederhanakan sebagai dua lintasan agar titik
        // awal dan akhirnya tidak ikut tergeser.
        $n = count($points);
        $half = intdiv($n, 2);

        $a = self::douglasPeucker(array_slice($points, 0, $half + 1), $tolerance);
        $b = self::douglasPeucker(array_merge(array_slice($points, $half), [$points[0]]), $tolerance);

        $merged = array_merge(array_slice($a, 0, -1), array_slice($b, 0, -1));

        return count($merged) >= 6 ? $merged : $points;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function douglasPeucker(array $points, float $tolerance): array
    {
        $count = count($points);

        if ($count < 3) {
            return $points;
        }

        $first = $points[0];
        $last = $points[$count - 1];
        $maxDist = 0.0;
        $index = 0;

        for ($i = 1; $i < $count - 1; $i++) {
            $d = self::pointToSegment($points[$i], $first, $last);

            if ($d > $maxDist) {
                $maxDist = $d;
                $index = $i;
            }
        }

        if ($maxDist <= $tolerance) {
            return [$first, $last];
        }

        return array_merge(
            array_slice(self::douglasPeucker(array_slice($points, 0, $index + 1), $tolerance), 0, -1),
            self::douglasPeucker(array_slice($points, $index), $tolerance)
        );
    }

    /**
     * @param array{0: float, 1: float} $p
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private static function pointToSegment(array $p, array $a, array $b): float
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $len2 = $dx * $dx + $dy * $dy;

        if ($len2 < 1e-12) {
            return sqrt(($p[0] - $a[0]) ** 2 + ($p[1] - $a[1]) ** 2);
        }

        $t = max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $len2));

        return sqrt(($p[0] - ($a[0] + $t * $dx)) ** 2 + ($p[1] - ($a[1] + $t * $dy)) ** 2);
    }

    /**
     * @param array<int, array{0: float, 1: float}> $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function chaikin(array $points, int $iterations = 1): array
    {
        if (count($points) < 3) {
            return $points;
        }

        for ($it = 0; $it < $iterations; $it++) {
            $out = [];
            $n = count($points);

            for ($i = 0; $i < $n; $i++) {
                $a = $points[$i];
                $b = $points[($i + 1) % $n];

                $out[] = [$a[0] * 0.75 + $b[0] * 0.25, $a[1] * 0.75 + $b[1] * 0.25];
                $out[] = [$a[0] * 0.25 + $b[0] * 0.75, $a[1] * 0.25 + $b[1] * 0.75];
            }

            $points = $out;
        }

        return $points;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $shore
     * @param array<string, mixed> $config
     * @return callable(array{0: float, 1: float}): array{0: float, 1: float}
     */
    private static function fit(array $shore, int $width, int $height, array $config): callable
    {
        $pad = (float) ($config['fit_padding'] ?? 54);
        $depth = (float) ($config['land_depth'] ?? 58);

        if ($shore === []) {
            return static fn (array $p): array => $p;
        }

        $xs = array_column($shore, 0);
        $ys = array_column($shore, 1);

        $w = max(1.0, max($xs) - min($xs));
        $h = max(1.0, max($ys) - min($ys));

        $scale = min(($width - $pad * 2) / $w, ($height - $pad * 2 - $depth) / $h);

        $cx = (min($xs) + max($xs)) / 2;
        $cy = (min($ys) + max($ys)) / 2;
        $tx = $width / 2;
        $ty = ($height - $depth) / 2;

        return static fn (array $p): array => [
            $tx + ($p[0] - $cx) * $scale,
            $ty + ($p[1] - $cy) * $scale,
        ];
    }

    /* --------------------------------------------------------- kecamatan */

    /**
     * @param array<string, array{0: float, 1: float}> $sites
     * @param array<int, array{0: float, 1: float}> $shore
     * @return array<string, array<int, array{0: float, 1: float}>>
     */
    private static function voronoi(array $sites, array $shore): array
    {
        $cells = [];

        foreach ($sites as $slug => $site) {
            $cell = $shore;

            foreach ($sites as $otherSlug => $other) {
                if ($otherSlug === $slug || $cell === []) {
                    continue;
                }

                $cell = self::clipHalfPlane($cell, $site, $other);
            }

            $cells[$slug] = $cell;
        }

        return $cells;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $polygon
     * @param array{0: float, 1: float} $site
     * @param array{0: float, 1: float} $other
     * @return array<int, array{0: float, 1: float}>
     */
    private static function clipHalfPlane(array $polygon, array $site, array $other): array
    {
        $dx = $other[0] - $site[0];
        $dy = $other[1] - $site[1];
        $mx = ($site[0] + $other[0]) / 2;
        $my = ($site[1] + $other[1]) / 2;

        $side = static fn (array $p): float => ($p[0] - $mx) * $dx + ($p[1] - $my) * $dy;

        $out = [];
        $count = count($polygon);

        for ($i = 0; $i < $count; $i++) {
            $a = $polygon[$i];
            $b = $polygon[($i + 1) % $count];

            $da = $side($a);
            $db = $side($b);

            if ($da <= 0) {
                $out[] = $a;
            }

            if (($da < 0 && $db > 0) || ($da > 0 && $db < 0)) {
                $t = $da / ($da - $db);
                $out[] = [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t];
            }
        }

        return $out;
    }

    /**
     * Tingkat perbesaran agar bidang kecamatan mengisi sebagian besar layar.
     *
     * @param array<int, array{0: float, 1: float}> $cell
     * @param array<string, mixed> $config
     */
    private static function zoomFor(array $cell, int $width, int $height, array $config): float
    {
        $default = (float) ($config['zoom_scale'] ?? 2.2);

        if (count($cell) < 3) {
            return $default;
        }

        $xs = array_column($cell, 0);
        $ys = array_column($cell, 1);

        $w = max(1.0, max($xs) - min($xs));
        $h = max(1.0, max($ys) - min($ys));

        $target = (float) ($config['zoom_fill'] ?? 0.74);
        $zoom = min(($width * $target) / $w, ($height * $target) / $h);

        return round(max(1.4, min((float) ($config['zoom_max'] ?? 6.0), $zoom)), 2);
    }

    /**
     * Titik tengah bidang kecamatan. Memakai titik berat luasan; bila jatuh
     * di luar bidang (bisa terjadi pada bentuk berlekuk), kembali ke titik
     * pusat kecamatan yang pasti berada di dalam.
     *
     * @param array<int, array{0: float, 1: float}> $cell
     * @param array{0: float, 1: float} $fallback
     * @return array{0: float, 1: float}
     */
    private static function cellCenter(array $cell, array $fallback): array
    {
        if (count($cell) < 3) {
            return $fallback;
        }

        $area = 0.0;
        $cx = 0.0;
        $cy = 0.0;
        $n = count($cell);

        for ($i = 0; $i < $n; $i++) {
            $a = $cell[$i];
            $b = $cell[($i + 1) % $n];
            $cross = $a[0] * $b[1] - $b[0] * $a[1];

            $area += $cross;
            $cx += ($a[0] + $b[0]) * $cross;
            $cy += ($a[1] + $b[1]) * $cross;
        }

        if (abs($area) < 1e-9) {
            return $fallback;
        }

        $center = [$cx / (3 * $area), $cy / (3 * $area)];

        if (self::contains($center, $cell)) {
            return $center;
        }

        // Bidang berlekuk: titik beratnya bisa jatuh di luar. Ambil titik
        // paling dalam, dan baru pakai titik pusat data sebagai cadangan.
        $deep = self::spreadInside($cell, 1);

        if ($deep !== []) {
            return $deep[0];
        }

        return $fallback;
    }

    /**
     * Menyebar sejumlah titik di dalam bidang, sejauh mungkin satu sama lain.
     *
     * Dipakai untuk kecamatan yang bidangnya sempit atau memanjang, yang tidak
     * mungkin ditata melingkar. Caranya: ambil titik-titik calon pada kisi di
     * dalam bidang, lalu pilih berulang kali titik yang paling jauh dari semua
     * titik yang sudah dipilih. Hasilnya menyebar rata mengikuti bentuk apa pun
     * dan dijamin tidak melewati garis wilayah.
     *
     * @param array<int, array{0: float, 1: float}> $cell
     * @return array<int, array{0: float, 1: float}>
     */
    private static function spreadInside(array $cell, int $count): array
    {
        if ($count < 1 || count($cell) < 3) {
            return [];
        }

        $xs = array_column($cell, 0);
        $ys = array_column($cell, 1);

        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        $steps = 30;
        $stepX = max(1.0, ($maxX - $minX) / $steps);
        $stepY = max(1.0, ($maxY - $minY) / $steps);

        $candidates = [];

        for ($gx = $minX + $stepX / 2; $gx <= $maxX; $gx += $stepX) {
            for ($gy = $minY + $stepY / 2; $gy <= $maxY; $gy += $stepY) {
                $p = [$gx, $gy];

                if (self::contains($p, $cell)) {
                    $candidates[] = $p;
                }
            }
        }

        if (count($candidates) < $count) {
            return [];
        }

        // Jauhkan dari tepi bila masih ada cukup pilihan.
        $margin = min($stepX, $stepY) * 0.9;
        $roomy = array_values(array_filter(
            $candidates,
            static fn (array $p): bool => self::distanceToEdges($p, $cell) >= $margin
        ));

        if (count($roomy) >= $count) {
            $candidates = $roomy;
        }

        // Mulai dari titik paling "dalam", lalu tambahkan yang terjauh.
        $picked = [];
        $best = 0;
        $bestD = -INF;

        foreach ($candidates as $i => $p) {
            $d = self::distanceToEdges($p, $cell);

            if ($d > $bestD) {
                $bestD = $d;
                $best = $i;
            }
        }

        $picked[] = $candidates[$best];

        while (count($picked) < $count) {
            $far = null;
            $farD = -INF;

            foreach ($candidates as $p) {
                $nearest = INF;

                foreach ($picked as $q) {
                    $d = ($p[0] - $q[0]) ** 2 + ($p[1] - $q[1]) ** 2;

                    if ($d < $nearest) {
                        $nearest = $d;
                    }
                }

                if ($nearest > $farD) {
                    $farD = $nearest;
                    $far = $p;
                }
            }

            if ($far === null) {
                break;
            }

            $picked[] = $far;
        }

        // Urutkan barat ke timur agar sejalan dengan urutan daftar kantor.
        usort($picked, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $picked;
    }

    /**
     * Menarik titik ke dalam bidang bila ia melewati garis wilayah.
     *
     * @param array{0: float, 1: float} $point
     * @param array{0: float, 1: float} $center
     * @param array<int, array{0: float, 1: float}> $cell
     * @return array{0: float, 1: float}
     */
    private static function pullInside(array $point, array $center, array $cell): array
    {
        if (count($cell) < 3 || self::contains($point, $cell)) {
            return $point;
        }

        $lo = 0.0;
        $hi = 1.0;

        for ($i = 0; $i < 18; $i++) {
            $mid = ($lo + $hi) / 2;
            $probe = [
                $center[0] + ($point[0] - $center[0]) * $mid,
                $center[1] + ($point[1] - $center[1]) * $mid,
            ];

            if (self::contains($probe, $cell)) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        // Sisakan sedikit jarak dari garis supaya pin tidak menempel di tepi.
        $t = $lo * 0.88;

        return [
            $center[0] + ($point[0] - $center[0]) * $t,
            $center[1] + ($point[1] - $center[1]) * $t,
        ];
    }

    /**
     * @param array{0: float, 1: float} $p
     * @param array<int, array{0: float, 1: float}> $poly
     */
    private static function contains(array $p, array $poly): bool
    {
        $inside = false;
        $n = count($poly);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = $poly[$i][1];
            $yj = $poly[$j][1];

            if (($yi > $p[1]) !== ($yj > $p[1])) {
                $x = ($poly[$j][0] - $poly[$i][0]) * ($p[1] - $yi) / (($yj - $yi) ?: 1e-12) + $poly[$i][0];

                if ($p[0] < $x) {
                    $inside = !$inside;
                }
            }
        }

        return $inside;
    }

    /**
     * Jarak terpendek dari sebuah titik ke tepi bidang.
     *
     * @param array{0: float, 1: float} $p
     * @param array<int, array{0: float, 1: float}> $poly
     */
    private static function distanceToEdges(array $p, array $poly): float
    {
        $best = INF;
        $n = count($poly);

        for ($i = 0; $i < $n; $i++) {
            $a = $poly[$i];
            $b = $poly[($i + 1) % $n];

            $dx = $b[0] - $a[0];
            $dy = $b[1] - $a[1];
            $len2 = $dx * $dx + $dy * $dy;

            $t = $len2 > 0
                ? max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $len2))
                : 0.0;

            $qx = $a[0] + $t * $dx;
            $qy = $a[1] + $t * $dy;
            $d = sqrt(($p[0] - $qx) ** 2 + ($p[1] - $qy) ** 2);

            if ($d < $best) {
                $best = $d;
            }
        }

        return $best === INF ? 0.0 : $best;
    }

    /* ----------------------------------------------------------- bantuan */

    /**
     * @param array<int, array{0: float, 1: float}> $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function shift(array $points, float $dy): array
    {
        return array_map(static fn (array $p): array => [$p[0], $p[1] + $dy], $points);
    }

    /**
     * @param array<int, array{0: float, 1: float}> $points
     */
    private static function polygon(array $points): string
    {
        if (count($points) < 3) {
            return '';
        }

        return implode(' ', array_map(
            static fn (array $p): string => sprintf('%.1f,%.1f', $p[0], $p[1]),
            $points
        ));
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function ringSlot(float $cx, float $cy, int $index, int $total, float $rx, float $ry): array
    {
        if ($total === 1) {
            return [$cx, $cy];
        }

        $hasCenter = $total > 6;

        if ($hasCenter && $index === 0) {
            return [$cx, $cy];
        }

        $i = $hasCenter ? $index - 1 : $index;
        $around = $hasCenter ? $total - 1 : $total;

        $inner = min($around, 8);

        if ($i < $inner) {
            $step = (2 * M_PI) / $inner;
            $angle = -M_PI / 2 + $step * $i;
            $scale = 1.0;
        } else {
            $outer = max(1, $around - $inner);
            $step = (2 * M_PI) / $outer;
            $angle = -M_PI / 2 + $step * ($i - $inner + 0.5);
            $scale = 1.55;
        }

        return [$cx + cos($angle) * $rx * $scale, $cy + sin($angle) * $ry * $scale];
    }

    /**
     * @param array<int, array<string, mixed>> $districts
     * @param array<int, array<int, float>> $outline
     * @return array{min_lng: float, max_lng: float, min_lat: float, max_lat: float}
     */
    private static function bounds(array $districts, array $outline): array
    {
        $lngs = array_map(static fn (array $d): float => (float) $d['center_lng'], $districts);
        $lats = array_map(static fn (array $d): float => (float) $d['center_lat'], $districts);

        foreach ($outline as $point) {
            $lngs[] = (float) $point[0];
            $lats[] = (float) $point[1];
        }

        return [
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
            'min_lat' => min($lats),
            'max_lat' => max($lats),
        ];
    }

    /**
     * @param array{min_lng: float, max_lng: float, min_lat: float, max_lat: float} $bounds
     * @param array<string, mixed> $config
     * @return array{0: float, 1: float}
     */
    private static function project(float $lng, float $lat, array $bounds, array $config): array
    {
        $spanLng = max(0.0001, $bounds['max_lng'] - $bounds['min_lng']);
        $spanLat = max(0.0001, $bounds['max_lat'] - $bounds['min_lat']);

        $u = ($lng - $bounds['min_lng']) / $spanLng;
        $v = ($bounds['max_lat'] - $lat) / $spanLat;

        return [
            ($u - $v) * (float) ($config['spread_x'] ?? 560),
            ($u + $v) * (float) ($config['spread_y'] ?? 200),
        ];
    }
}
