# Lite Scholar Theme untuk OJS 3.3

Child theme ringan dari **Default Theme** OJS 3.3.x. Hanya satu berkas CSS kecil (± 3 KB), tanpa JavaScript tambahan, tanpa font eksternal, dan tanpa menyalin template inti OJS.

## Fitur

- Palet monokrom hangat: Grain `#D7CEC7`, Blackboard `#565656`, Oxblood `#76323F`, Tan `#C09F80`.
- **Afiliasi dan negara di bawah nama penulis** pada halaman artikel. Negara diambil dari kolom *Country* pada metadata penulis. Jika afiliasi kosong, hanya negara yang tampil; jika afiliasi sudah berakhir dengan nama negara, tidak digandakan.
- Opsi **Appearance → Theme → Lite Scholar → Author country** untuk menyalakan atau mematikan tampilan negara.
- Nama negara mengikuti bahasa antarmuka OJS.
- Teks Indonesia dan Inggris.

## Instalasi

1. Buat paket: `scripts/package-theme.sh` (hasil: `liteScholar-v1.0.0.tar.gz`).
2. Masuk sebagai **Site Administrator** → **Administration → Hosted Journals → Manage Plugins → Upload a New Plugin**.
3. Unggah paket tersebut. Atau salin folder `liteScholar` ke `plugins/themes/liteScholar` lalu bersihkan cache OJS.
4. Buka **Website → Appearance → Theme**, pilih **Lite Scholar Theme**, simpan.

Tema ini membutuhkan Default Theme (bawaan OJS) tetap terpasang.

## Cara kerja fitur negara

Pada halaman artikel, tema menambahkan nama negara ke nilai afiliasi di memori sebelum template Default Theme dirender. Tidak ada data yang disimpan ke database, dan metadata Google Scholar (`citation_author_institution`) tidak berubah karena dibuat sebelum tahap ini.

## Pengembangan

```bash
php themes/liteScholar/tests/authorCountryTest.php
```

Uji ini berjalan tanpa instalasi OJS.

## Lisensi

GPL-3.0-or-later. Lihat `LICENSE`.
