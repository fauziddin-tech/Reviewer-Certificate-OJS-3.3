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
- Panel statistik publik reviewer dan editor berdasarkan negara.
- Peta dunia responsif, grafik peringkat negara, dan ringkasan jumlah kontribusi.
- Normalisasi negara memakai kode ISO dari profil OJS agar variasi penulisan tidak menggandakan data.
- Ambang privasi per negara serta pilihan posisi dan jenis statistik yang ditampilkan.
- Pengaturan terbuka sebagai pop-up di halaman plugin, tanpa berpindah dari dashboard OJS.
- Menu **Upgrade** menjelaskan versi terpasang dan jalur paket resmi langsung dari dashboard.

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

## Upgrade

OJS membatasi penggantian berkas plugin kepada akun **Site Administrator**. Jika masuk sebagai Site Administrator, menu **Upgrade** bawaan OJS akan menampilkan formulir unggah paket `.tar.gz`. Jika masuk sebagai **Journal Manager**, plugin menampilkan pop-up Upgrade berisi versi terpasang, tautan rilis resmi, dan petunjuk yang harus diteruskan kepada Site Administrator. Pembatasan ini tidak dilewati karena upgrade mengubah berkas pada server.

## Pengaturan

Pada daftar plugin, buka **Reviewer Certificate → Settings**. Administrator jurnal dapat mengatur:

- logo jurnal;
- tanda tangan editor;
- stempel resmi;
- ukuran tampilan setiap gambar;
- nama penandatangan; dan
- jabatan penandatangan.
- aktivasi panel statistik publik;
- judul dan posisi panel pada beranda;
- statistik reviewer dan/atau editor; serta
- ambang privasi minimum 1–10 orang untuk setiap peran pada setiap negara (nilai rekomendasi: 2).

Format gambar yang didukung adalah PNG, JPG/JPEG, dan WebP, dengan ukuran maksimum 2 MB dan dimensi maksimum 3000 × 3000 piksel. PNG transparan direkomendasikan untuk tanda tangan dan stempel.

## Penggunaan

Reviewer yang telah menyelesaikan review dapat membuka tab **Certificates** pada dashboard submission. Sertifikat dapat dicari, dibuka, dicetak, atau disimpan sebagai PDF. QR code pada sertifikat mengarah ke halaman verifikasi untuk memastikan keasliannya.

Data negara dibaca dari pilihan **Country/Negara** pada **Profile → Contact** di OJS. Plugin tidak menebak teks negara yang tidak valid. Journal Manager dapat melihat jumlah profil reviewer dan editor yang belum memiliki kode negara valid pada halaman pengaturan plugin.

Panel publik dinonaktifkan secara bawaan setelah upgrade. Untuk menampilkannya, buka pengaturan plugin, aktifkan **Public reviewer and editor map**, pilih statistik yang diperlukan, lalu simpan. Panel hanya menampilkan agregat negara dan tidak memublikasikan nama, email, atau identitas pengguna.

## Keamanan dan privasi

- Halaman sertifikat hanya dapat dibuka oleh reviewer pemilik review yang telah selesai.
- URL verifikasi menggunakan token HMAC khusus jurnal dan tidak dapat ditemukan hanya dengan menebak ID review.
- QR code dibuat secara lokal oleh plugin; URL verifikasi tidak dikirim ke penyedia QR eksternal.
- Halaman sensitif menggunakan kebijakan tanpa cache dan instruksi agar tidak diindeks mesin pencari.
- Halaman verifikasi menampilkan nama reviewer, jurnal, judul naskah, dan bulan penyelesaian kepada orang yang memiliki tautan atau QR code yang valid.
- Panel peta publik hanya menggunakan statistik agregat. Negara tidak ditampilkan jika jumlah reviewer atau editor yang tidak nol berada di bawah ambang privasi untuk peran tersebut.
- Peta dunia diproses di browser dari aset lokal dan tidak mengirim data pengguna ke layanan pemetaan eksternal.

## Pemecahan masalah

- Jika tab **Certificates** belum muncul, pastikan plugin aktif lalu bersihkan cache OJS dan cache browser.
- Jika gambar tidak dapat diunggah, periksa izin tulis direktori `public_files_dir` serta batas `upload_max_filesize` dan `post_max_size` pada PHP.
- Jika halaman pengaturan tidak terbuka, pastikan akun memiliki peran **Journal Manager** atau **Site Administrator**.
- Jika terjadi error 500, periksa log PHP/OJS untuk mengetahui pesan kesalahan yang sebenarnya.

## Versi

Versi saat ini: **1.9.0**

Perubahan setiap versi tersedia pada [CHANGELOG.md](CHANGELOG.md). Informasi pelaporan keamanan tersedia pada [SECURITY.md](SECURITY.md).

## Lisensi

Plugin ini didistribusikan berdasarkan **GNU General Public License v3.0 or later (GPL-3.0-or-later)**. Lihat [LICENSE](LICENSE). Komponen QR pihak ketiga menggunakan lisensi MIT dan dijelaskan pada [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

## Pengembang

Dikembangkan oleh **Mohammad Fauziddin** untuk mendukung pengelolaan dan apresiasi reviewer pada Open Journal Systems.

## Acknowledgements

Plugin ini dikembangkan oleh **Mohammad Fauziddin** dengan bantuan teknis **OpenAI ChatGPT (Codex)** dalam perancangan fitur, penulisan kode, debugging, penyempurnaan antarmuka, peninjauan keamanan, dan dokumentasi.

AI digunakan sebagai alat bantu pengembangan. Seluruh pengujian, penerapan, dan keputusan akhir tetap berada di bawah tanggung jawab pengembang.
