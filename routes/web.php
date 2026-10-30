<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;

$router = new Router();

$router->middleware('csrf', [CsrfMiddleware::class, 'handle']);
$router->middleware('auth', [AuthMiddleware::class, 'handle']);
$router->middleware('role', [RoleMiddleware::class, 'handle']);

/* ------------------------------------------------------------------ publik */

$router->get('/', 'LandingController@index');
$router->get('/peta', 'HomeController@index');
$router->get('/kecamatan/{slug}', 'HomeController@district');
$router->get('/lokasi/{slug}', 'LocationController@show');
$router->get('/tentang', 'PageController@about');
$router->get('/mengalihkan', 'RedirectController@show');
$router->get('/cari', 'SearchController@page');

$router->form('/cek-antrean', 'TrackController@index', ['csrf']);
$router->form('/masuk', 'AuthController@login', ['csrf']);
$router->form('/daftar', 'AuthController@register', ['csrf']);
$router->post('/keluar', 'AuthController@logout', ['csrf', 'auth']);

// Verifikasi email sengaja tanpa middleware auth: akun yang belum
// terverifikasi tidak bisa masuk, jadi pemiliknya tidak punya sesi untuk
// membuka halaman ini atau meminta tautan baru.
$router->get('/verifikasi', 'VerificationController@notice');
$router->post('/verifikasi/kirim-ulang', 'VerificationController@resend', ['csrf']);
$router->get('/verifikasi/{token}', 'VerificationController@confirm');

// Ambil antrean tidak memerlukan akun: warga yang belum masuk mengisi
// identitasnya langsung di formulir dan menerima kode pemesanan.
$router->form('/lokasi/{slug}/daftar', 'QueueController@create', ['csrf']);

// Aduan fasilitas sengaja tanpa middleware auth: yang menemukan data
// salah biasanya pengunjung pertama kali, bukan pemilik akun.
$router->post('/lokasi/{slug}/aduan', 'ReportController@store', ['csrf']);

/* --------------------------------------------------------- pengguna (warga) */

$pengguna = ['csrf', 'auth', 'role:user'];

$router->get('/akun', 'AccountController@index', $pengguna);
$router->get('/akun/antrean', 'AccountController@queues', $pengguna);
$router->get('/akun/antrean/{code}', 'AccountController@show', $pengguna);
$router->get('/akun/antrean/{code}/chat', 'AccountController@chat', $pengguna);
$router->post('/akun/antrean/{code}/batal', 'AccountController@cancel', $pengguna);
$router->get('/akun/rekap', 'AccountController@recap', $pengguna);
$router->form('/akun/profil', 'AccountController@profile', $pengguna);

/* ------------------------------------------------------------------ petugas */

$petugas = ['csrf', 'auth', 'role:officer'];

$router->get('/petugas', 'OfficerController@index', $petugas);
$router->post('/petugas/antrean/{code}/terima', 'OfficerController@claim', $petugas);
$router->post('/petugas/antrean/{code}/status', 'OfficerController@status', $petugas);
$router->post('/petugas/antrean/{code}/catatan', 'OfficerController@note', $petugas);
$router->get('/petugas/antrean/{code}/chat', 'OfficerController@chat', $petugas);
$router->get('/petugas/rekap', 'OfficerController@recap', $petugas);

/* -------------------------------------------------------------------- admin */

$admin = ['csrf', 'auth', 'role:admin,superadmin'];

$router->get('/admin', 'AdminController@index', $admin);
$router->get('/admin/landmark', 'AdminController@landmarks', $admin);
$router->post('/admin/landmark/simpan', 'AdminController@saveLandmark', $admin);
$router->post('/admin/landmark/{id}/nonaktif', 'AdminController@toggleLandmark', $admin);
$router->get('/admin/landmark/{id}/deskripsi', 'AdminController@landmarkServices', $admin);
$router->post('/admin/landmark/{id}/foto', 'AdminController@savePhoto', $admin);
$router->post('/admin/layanan/simpan', 'AdminController@saveService', $admin);
$router->post('/admin/layanan/{id}/hapus', 'AdminController@removeService', $admin);
$router->get('/admin/antrean', 'AdminController@queues', $admin);
$router->get('/admin/antrean/{code}', 'AdminController@queue', $admin);
$router->post('/admin/antrean/{code}/assign', 'AdminController@assign', $admin);
$router->get('/admin/pembayaran', 'AdminController@payments', $admin);
$router->post('/admin/pembayaran/{id}/lunas', 'AdminController@markPaid', $admin);
$router->get('/admin/rekap', 'AdminController@recap', $admin);
$router->get('/admin/rekap/export', 'AdminController@export', $admin);
$router->get('/admin/aduan', 'AdminController@reports', $admin);
$router->post('/admin/aduan/{id}/status', 'AdminController@updateReport', $admin);
$router->get('/admin/petugas', 'AdminController@officers', $admin);

/* ------------------------------------------------------------------- berkas */

// Berkas unggahan disajikan lewat controller, bukan dari folder publik.
$router->get('/berkas/{id}', 'FileController@show', ['auth']);

/* ---------------------------------------------------------- API internal JSON */

$router->get('/api/search', 'SearchController@suggest');
$router->get('/api/map/landmarks', 'ApiController@landmarks');
$router->get('/api/landmark/{id}', 'ApiController@landmark');
$router->get('/api/queue/{code}/status', 'ApiController@queueStatus');
$router->get('/api/chat/{queue}', 'ApiController@messages', ['auth']);
$router->post('/api/chat/{queue}', 'ApiController@send', ['csrf', 'auth']);
$router->get('/api/stats', 'ApiController@stats', ['auth', 'role:officer,admin,superadmin']);

return $router;
