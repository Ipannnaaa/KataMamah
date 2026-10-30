<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ikon per kategori kantor layanan.
 *
 * Disimpan di satu tempat supaya pin di peta, kartu pratinjau, dan daftar
 * kantor memakai gambar yang sama. Semua ikon digambar dalam kotak 24×24
 * dengan garis (stroke), jadi warnanya mengikuti `currentColor`.
 */
final class Icons
{
    /** @var array<string, string> */
    private const PATHS = [
        // Gedung pemerintahan: atap pelana, tiang, dan tiang bendera.
        'pemerintah' => '<path d="M12 3l9 5H3l9-5z"/><path d="M5 8v10M9.5 8v10M14.5 8v10M19 8v10"/><path d="M3 18h18M2 21h20"/><path d="M12 3V1"/>',

        // Kartu identitas untuk Dukcapil.
        'kartu' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><circle cx="8.5" cy="11" r="2.2"/><path d="M5 16.2c.8-1.4 2-2.1 3.5-2.1s2.7.7 3.5 2.1"/><path d="M15 10h4M15 13.5h4"/>',

        // Palang kesehatan di atas gedung.
        'kesehatan' => '<path d="M4 20V8.5l8-5.5 8 5.5V20"/><path d="M3 20h18"/><path d="M12 9.5v6M9 12.5h6"/>',

        // Rumah sakit: gedung bertingkat dengan palang.
        'rumah-sakit' => '<path d="M4 21V6a2 2 0 012-2h12a2 2 0 012 2v15"/><path d="M2.5 21h19"/><path d="M12 7v5M9.5 9.5h5"/><path d="M8 15h2.5M13.5 15h2.5M8 18h2.5M13.5 18h2.5"/>',

        // Perisai polisi dengan bintang.
        'polisi' => '<path d="M12 2.5l7.5 3v6c0 4.6-3.1 8.2-7.5 10-4.4-1.8-7.5-5.4-7.5-10v-6l7.5-3z"/><path d="M12 8l1.3 2.7 2.9.4-2.1 2 .5 2.9-2.6-1.4-2.6 1.4.5-2.9-2.1-2 2.9-.4L12 8z"/>',

        // Pemadam kebakaran: nyala api.
        'pemadam' => '<path d="M12 2.5s5.5 4.4 5.5 9.4a5.5 5.5 0 01-11 0c0-2 1-3.6 2-4.8.3 1.2 1 2 2 2.3-.4-2.6.5-5 1.5-6.9z"/><path d="M12 21.5v-2"/><path d="M9.5 13.8c0 1.4 1.1 2.6 2.5 2.6s2.5-1.2 2.5-2.6c0-1.3-1-2.3-1.6-3-.5.9-1.4 1.4-2.2 1.6-.7.4-1.2 1-1.2 1.4z"/>',

        // Kendaraan untuk Samsat.
        'kendaraan' => '<path d="M4.5 16.5v2a1 1 0 01-1 1h-1a1 1 0 01-1-1v-2"/><path d="M22.5 16.5v2a1 1 0 01-1 1h-1a1 1 0 01-1-1v-2"/><path d="M2 16.5v-4l2-5a2 2 0 011.9-1.4h12.2A2 2 0 0120 7.5l2 5v4z"/><path d="M4.5 12.5h15"/><circle cx="6.5" cy="15" r="1"/><circle cx="17.5" cy="15" r="1"/>',

        // Tenda pasar.
        'pasar' => '<path d="M3 9.5h18l-2-5H5l-2 5z"/><path d="M4.5 9.5V20h15V9.5"/><path d="M3 9.5a2.2 2.2 0 004.5 0 2.2 2.2 0 004.5 0 2.2 2.2 0 004.5 0 2.2 2.2 0 004.5 0"/><path d="M9.5 20v-5h5v5"/>',

        // Dokumen berstempel untuk perizinan.
        'izin' => '<path d="M14 2.5H6.5a2 2 0 00-2 2v15a2 2 0 002 2h11a2 2 0 002-2V8l-6-5.5z"/><path d="M13.5 2.5V8h6"/><circle cx="12" cy="14.5" r="2.6"/><path d="M12 17.1v3l-1.6-1-1.6 1"/>',

        // Topi wisuda untuk pendidikan.
        'sekolah' => '<path d="M12 4L2 8.5l10 4.5 10-4.5L12 4z"/><path d="M5.5 10.5V16c0 1.7 2.9 3 6.5 3s6.5-1.3 6.5-3v-5.5"/><path d="M22 8.5V14"/>',

        // Gedung umum.
        'gedung' => '<path d="M4 21V5.5a2 2 0 012-2h7a2 2 0 012 2V21"/><path d="M15 21V10h3a2 2 0 012 2v9"/><path d="M2.5 21h19"/><path d="M7.5 8h4M7.5 12h4M7.5 16h4"/>',

        // Timbangan keadilan untuk pengadilan.
        'timbangan' => '<path d="M12 4v16"/><path d="M8 20h8"/><path d="M4.5 8h15"/><path d="M12 5l7.5 3M12 5L4.5 8"/><path d="M4.5 8L2 14a2.5 2.5 0 005 0L4.5 8z"/><path d="M19.5 8L17 14a2.5 2.5 0 005 0l-2.5-6z"/>',

        // Timbangan dengan bulan sabit untuk pengadilan agama.
        'timbangan-agama' => '<path d="M12 7v13"/><path d="M8.5 20h7"/><path d="M5 10h14"/><path d="M5 10l-2.2 5a2.2 2.2 0 004.4 0L5 10z"/><path d="M19 10l-2.2 5a2.2 2.2 0 004.4 0L19 10z"/><path d="M13.7 3.2a2.3 2.3 0 10-.2 3.4 2.8 2.8 0 01.2-3.4z"/>',

        // Perisai dengan pedang untuk kejaksaan.
        'perisai-pedang' => '<path d="M12 2.5l7.5 3v6c0 4.6-3.1 8.2-7.5 10-4.4-1.8-7.5-5.4-7.5-10v-6l7.5-3z"/><path d="M12 7v9"/><path d="M9.5 10h5"/>',

        // Dua cincin untuk KUA.
        'cincin' => '<circle cx="9" cy="14.5" r="4.6"/><circle cx="15" cy="14.5" r="4.6"/><path d="M9 9.9V7M15 9.9V7"/><path d="M7 5.5h4M13 5.5h4"/>',

        // Perisai dengan hati untuk BPJS.
        'jaminan' => '<path d="M12 2.5l7.5 3v6c0 4.6-3.1 8.2-7.5 10-4.4-1.8-7.5-5.4-7.5-10v-6l7.5-3z"/><path d="M12 16s-3.2-2.1-3.2-4.2A1.9 1.9 0 0112 10.4a1.9 1.9 0 013.2 1.4C15.2 13.9 12 16 12 16z"/>',

        // Petak tanah untuk pertanahan.
        'bidang-tanah' => '<path d="M3 7.5l6-3 6 3 6-3v12l-6 3-6-3-6 3z"/><path d="M9 4.5v12M15 7.5v12"/>',

        // Buku terbuka untuk perpustakaan.
        'buku' => '<path d="M3 5.5A2.5 2.5 0 015.5 3H11v16H5.5A2.5 2.5 0 003 21.5z"/><path d="M21 5.5A2.5 2.5 0 0018.5 3H13v16h5.5a2.5 2.5 0 012.5 2.5z"/><path d="M11 19h2"/>',

        // Amplop untuk kantor pos.
        'amplop' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3.5 7l8.5 5.8L20.5 7"/>',

        // Tetes air untuk PDAM.
        'air' => '<path d="M12 3s6 6.3 6 10.4a6 6 0 11-12 0C6 9.3 12 3 12 3z"/><path d="M9.6 13.8A2.4 2.4 0 0012 16.2"/>',

        // Bus untuk terminal.
        'bus' => '<rect x="4" y="4" width="16" height="13" rx="3"/><path d="M4 10.5h16"/><path d="M7.5 17v2.5M16.5 17v2.5"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/>',

        // Kerucut penanda untuk Satpol PP.
        'kerucut' => '<path d="M12 3l5.8 15.5H6.2z"/><path d="M9.2 11h5.6M7.8 15h8.4"/><path d="M3.5 20.5h17"/>',
    ];

    /** @var array<string, string> */
    private const MAP = [
        'kecamatan' => 'pemerintah',
        'kelurahan' => 'pemerintah',
        'dinas' => 'pemerintah',
        'dukcapil' => 'kartu',
        'puskesmas' => 'kesehatan',
        'rumah_sakit' => 'rumah-sakit',
        'polsek' => 'polisi',
        'pemadam' => 'pemadam',
        'samsat' => 'kendaraan',
        'satpol_pp' => 'kerucut',
        'pengadilan' => 'timbangan',
        'pengadilan_agama' => 'timbangan-agama',
        'kejaksaan' => 'perisai-pedang',
        'kua' => 'cincin',
        'bpjs' => 'jaminan',
        'pertanahan' => 'bidang-tanah',
        'perpustakaan' => 'buku',
        'pos' => 'amplop',
        'pdam' => 'air',
        'terminal' => 'bus',
        'pasar' => 'pasar',
        'perizinan' => 'izin',
        'sekolah' => 'sekolah',
        'lainnya' => 'gedung',
    ];

    /**
     * SVG lengkap untuk satu kategori.
     */
    public static function svg(string $category, int $size = 22, string $extraClass = ''): string
    {
        $key = self::MAP[$category] ?? 'gedung';
        $paths = self::PATHS[$key] ?? self::PATHS['gedung'];

        return '<svg class="icon ' . $extraClass . '" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '"'
            . ' fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }
}
