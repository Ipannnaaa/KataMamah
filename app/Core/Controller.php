<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Induk semua controller: pembungkus singkat untuk view, JSON, dan redirect.
 */
abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = [], string $layout = 'public'): Response
    {
        return Response::html(View::render($view, $data, $layout));
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $data
     */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function success(string $message, string $to): Response
    {
        Session::flash('success', $message);

        return $this->redirect($to);
    }

    /**
     * Redirect yang lewat layar "Mengalihkan…" dulu.
     *
     * Tujuannya disimpan di sesi, bukan di query string, supaya tidak bisa
     * dipakai orang lain untuk melempar pengunjung ke alamat luar. Pola
     * POST -> redirect -> GET tetap utuh: /mengalihkan adalah GET biasa,
     * jadi memuat ulang di sana tidak pernah mengirim formulir dua kali.
     */
    protected function redirectVia(string $to, string $judul, string $ket = ''): Response
    {
        Session::flash('_alih', [
            'to' => $to,
            'judul' => $judul,
            'ket' => $ket,
        ]);

        return $this->redirect('/mengalihkan');
    }

    /**
     * Aksi yang gagal tetapi bukan soal isian form — misalnya transisi status
     * yang tidak sah atau antrean milik petugas lain. Pesannya harus terlihat
     * sebagai flash, bukan disimpan sebagai error per field yang tak dirender.
     */
    protected function failure(string $message, string $to): Response
    {
        Session::flash('error', $message);

        return $this->redirect($to);
    }

    /**
     * Kembali ke halaman form dengan pesan error dan isian sebelumnya.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    protected function backWithErrors(string $to, array $errors, array $old = []): Response
    {
        Session::flash('_errors', $errors);
        Session::flash('_old', array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $old));
        Session::flash('error', 'Periksa kembali isian yang ditandai.');

        return $this->redirect($to);
    }

    protected function abort(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], $status);
        }

        return Response::html(
            View::render('errors/status', [
                'status' => $status,
                'message' => $message,
                'title' => error_info($status)['judul'],
            ], 'public'),
            $status
        );
    }
}
