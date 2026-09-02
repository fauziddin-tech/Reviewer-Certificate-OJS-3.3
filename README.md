<div align="center">
  <img src="assets/logo.png" alt="Reviewer Certificate" width="120">
  <h1>Reviewer Certificate for OJS 3.3</h1>
  <p>Plugin OJS untuk membuat, mengelola, mencari, dan memverifikasi sertifikat reviewer secara langsung dari dashboard.</p>
</div>

## Fitur utama

- Tab **Certificates** terintegrasi dengan halaman **My Queue** dan **Archives**.
- Daftar sertifikat berdasarkan review yang telah diselesaikan.
- Pencarian berdasarkan judul artikel, nama penulis, atau ID submission.
- Paginasi hingga 30 sertifikat per halaman.
- Sertifikat profesional dengan QR code lokal dan token verifikasi yang tidak mudah ditebak.
- Halaman verifikasi sertifikat yang dapat diakses melalui QR code.
- Pengaturan logo jurnal, tanda tangan editor, dan stempel melalui panel plugin.
- Pengaturan ukuran gambar serta nama dan jabatan penandatangan.
- Metadata plugin tersedia dalam bahasa Indonesia dan Inggris.
- Tata letak sertifikat siap cetak atau disimpan sebagai PDF.

## Kompatibilitas

- Open Journal Systems (OJS) 3.3.x
- PHP dan basis data mengikuti persyaratan instalasi OJS
- Browser modern dengan JavaScript aktif

Plugin ini ditujukan untuk OJS 3.3.x dan telah diuji langsung pada OJS 3.3.0.22. Pengujian pada instalasi staging tetap disarankan sebelum digunakan pada jurnal produksi.

## Instalasi melalui OJS

1. Unduh paket rilis dalam format `.tar.gz`.
2. Masuk sebagai **Site Administrator**.
3. Buka **Administration → Hosted Journals → Manage Plugins** atau menu instalasi plugin yang tersedia.
4. Pilih **Upload a New Plugin**, kemudian unggah paket `.tar.gz`.
5. Aktifkan **Reviewer Certificate** pada kategori **Generic Plugins**.

Jika versi lama sudah terpasang, gunakan tombol **Upgrade** dan jangan menghapus folder penyimpanan gambar jurnal.

> **Penting untuk upgrade dari 1.7.0:** QR code lama belum menggunakan token keamanan. Setelah upgrade, reviewer perlu membuka dan menyimpan ulang sertifikat agar memperoleh QR code v1.8.0.

## Instalasi manual

Salin folder plugin ke:

```text
plugins/generic/reviewerCertificate
```

Pastikan struktur akhirnya seperti berikut:

```text
plugins/generic/reviewerCertificate/index.php
plugins/generic/reviewerCertificate/version.xml
plugins/generic/reviewerCertificate/ReviewerCertificatePlugin.inc.php
```

Setelah itu, bersihkan cache OJS dan aktifkan plugin melalui halaman pengelolaan plugin.

## Pengaturan

Pada daftar plugin, buka **Reviewer Certificate → Settings**. Administrator jurnal dapat mengatur:

- logo jurnal;
- tanda tangan editor;
- stempel resmi;
- ukuran tampilan setiap gambar;
- nama penandatangan; dan
- jabatan penandatangan.

Format gambar yang didukung adalah PNG, JPG/JPEG, dan WebP, dengan ukuran maksimum 2 MB dan dimensi maksimum 3000 × 3000 piksel. PNG transparan direkomendasikan untuk tanda tangan dan stempel.

## Penggunaan

Reviewer yang telah menyelesaikan review dapat membuka tab **Certificates** pada dashboard submission. Sertifikat dapat dicari, dibuka, dicetak, atau disimpan sebagai PDF. QR code pada sertifikat mengarah ke halaman verifikasi untuk memastikan keasliannya.

## Keamanan dan privasi

- Halaman sertifikat hanya dapat dibuka oleh reviewer pemilik review yang telah selesai.
- URL verifikasi menggunakan token HMAC khusus jurnal dan tidak dapat ditemukan hanya dengan menebak ID review.
- QR code dibuat secara lokal oleh plugin; URL verifikasi tidak dikirim ke penyedia QR eksternal.
- Halaman sensitif menggunakan kebijakan tanpa cache dan instruksi agar tidak diindeks mesin pencari.
- Halaman verifikasi menampilkan nama reviewer, jurnal, judul naskah, dan bulan penyelesaian kepada orang yang memiliki tautan atau QR code yang valid.

## Pemecahan masalah

- Jika tab **Certificates** belum muncul, pastikan plugin aktif lalu bersihkan cache OJS dan cache browser.
- Jika gambar tidak dapat diunggah, periksa izin tulis direktori `public_files_dir` serta batas `upload_max_filesize` dan `post_max_size` pada PHP.
- Jika halaman pengaturan tidak terbuka, pastikan akun memiliki peran **Journal Manager** atau **Site Administrator**.
- Jika terjadi error 500, periksa log PHP/OJS untuk mengetahui pesan kesalahan yang sebenarnya.

## Versi

Versi saat ini: **1.8.0**

Perubahan setiap versi tersedia pada [CHANGELOG.md](CHANGELOG.md). Informasi pelaporan keamanan tersedia pada [SECURITY.md](SECURITY.md).

## Lisensi

Plugin ini didistribusikan berdasarkan **GNU General Public License v3.0 or later (GPL-3.0-or-later)**. Lihat [LICENSE](LICENSE). Komponen QR pihak ketiga menggunakan lisensi MIT dan dijelaskan pada [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

## Pengembang

Dikembangkan oleh **Mohammad Fauziddin** untuk mendukung pengelolaan dan apresiasi reviewer pada Open Journal Systems.

## Acknowledgements

Plugin ini dikembangkan oleh **Mohammad Fauziddin** dengan bantuan teknis **OpenAI ChatGPT (Codex)** dalam perancangan fitur, penulisan kode, debugging, penyempurnaan antarmuka, peninjauan keamanan, dan dokumentasi.

AI digunakan sebagai alat bantu pengembangan. Seluruh pengujian, penerapan, dan keputusan akhir tetap berada di bawah tanggung jawab pengembang.
