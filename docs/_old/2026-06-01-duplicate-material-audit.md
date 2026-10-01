# Audit Duplikasi Bahan Baku Resep Produk dan Formula Component

Tanggal audit: 2026-06-01
Database: `db_finance`

## Ringkasan

- Resep produk yang memiliki bahan baku dobel: `1` produk.
- Formula component yang memiliki bahan baku dobel: `55` component unik.
- Total group duplikasi di formula component: `60` group material, terdiri dari:
  - `46` group: `GULA PASIR KITCHEN`
  - `9` group: `AIR`
  - `3` group: `FRESH MILK`
  - `2` group: `AYAM UTUH`

Catatan penting:

- Duplikasi pada resep produk sangat sedikit dan ada `1` kasus yang tampak sebagai exact duplicate.
- Duplikasi pada formula component bersifat sistemik dan dominan berasal dari split item profile untuk material dasar yang sama.
- Beberapa nama component muncul lebih dari sekali karena memang ada `component_id` berbeda dengan nama sama, misalnya `NUGET` (`72` dan `125`).
- Pada pasangan item air, `mst_item.id = 189` menyimpan `item_name` dengan akhiran `CRLF` (`0D0A`), sehingga output terminal terlihat rusak. Item pasangannya `220` bersih.

## Temuan Resep Produk

### Exact duplicate yang terdeteksi

1. Produk `155 - MIE AYAM NAMUA`
   - Material: `BAWANG PUTIH GORENG`
   - Item: `BAWANG PUTIH GORENG` (`item_id = 27`)
   - Jumlah line: `2`
   - Total qty gabungan: `8.0000`
   - Row id: `2847, 2850`
   - Kedua line berada pada division `KITCHEN` dan `ingredient_role = MAIN`

Temuan ini terlihat sebagai exact duplicate yang paling aman dijadikan kandidat cleanup lebih dulu.

## Temuan Formula Component

### Pola 1: GULA PASIR KITCHEN

Pola dominan berasal dari item ganda untuk material yang sama:

- `item_id = 70` -> `GULA PASIR KITCHEN`
- `item_id = 177` -> `GULA KITCHEN`

Affected component-material groups: `46`

Daftar component:

- `64 - ACAR PICKLED`
- `141 - ADONAN BANANA OVALTINE`
- `16 - BARBEQUE SAUCE`
- `43 - BASE GEDE`
- `13 - BOLOGNESE`
- `95 - BROTH BETAWI`
- `96 - BROTH IGA`
- `97 - BROTH SOYU`
- `99 - BROTH TORI PAITAN`
- `15 - BUMBU BAKAR MADU`
- `47 - BUMBU IRENG MADURA`
- `19 - BUMBU MERAH PASTE TERASI`
- `90 - DUCK BONELESS JUMBO`
- `89 - DUCK BONELESS SMALL`
- `30 - FILL PISANG CARAMEL`
- `42 - HONEY MUSTARD`
- `49 - HONEY SAUCE`
- `46 - KALIO PASTE`
- `23 - KUAH IGA`
- `73 - MAGIC POWDER SHORT RIBS`
- `55 - MARINASI AYAM BAKAR`
- `69 - MARINASI BEBEK IRENG`
- `66 - MARINASI SONGKEM`
- `56 - MIX SAUCE FRIED RICE & NOODLES`
- `24 - MIX SEASONING POWDER`
- `72 - NUGET`
- `125 - NUGET`
- `61 - PANNACOTTA`
- `83 - QUARTER CHICKEN GRILL`
- `130 - QUARTER CHICKEN STEAMED`
- `104 - QUARTER DUCK FRIED`
- `131 - QUARTER DUCK STEAMED`
- `34 - RED GINGER PICKLED`
- `41 - SALTED CARAMEL`
- `51 - SAMBAL DABU-DABU`
- `35 - SARI SUSHI`
- `17 - SAUCE BANGKOK`
- `68 - SESAME DRESSING`
- `71 - SOTO BETAWI`
- `59 - SOYU RAMEN BROTH`
- `48 - TAR-TAR DRESSING`
- `14 - TERIYAKI SAUCE`
- `40 - THAI DRESSING`
- `65 - TOMYUM PASTE`
- `31 - TONGSENG PASTE`
- `32 - TORI PAITAN RAMEN BROTH`

### Pola 2: AIR

Pola ini muncul pada item profile ganda untuk material `AIR`:

- `item_id = 189` -> `AIR MINERAL GALON` dengan akhiran `CRLF` di `item_name`
- `item_id = 220` -> `AIR MINERAL GALON`

Affected component-material groups: `9`

Daftar component:

- `1 - SIMPLE SYRUP`
- `4 - ESPRESSO BLEND`
- `5 - LEMON JUICE`
- `6 - COLD BREW`
- `7 - ADU RAMU`
- `9 - BASED LEMONGRASS`
- `10 - JELLY CHOCOLATE BASE`
- `11 - JELLY LYCHEE BASE`
- `76 - INFUSE CINNAMON`

### Pola 3: FRESH MILK

Pola ini muncul pada item profile:

- `item_id = 194` -> `FRESH MILK`
- `item_id = 221` -> `FRESH MILK UHT`

Affected component-material groups: `3`

Daftar component:

- `72 - NUGET`
- `125 - NUGET`
- `61 - PANNACOTTA`

### Pola 4: AYAM UTUH

Pola ini muncul pada item profile:

- `item_id = 12` -> `AYAM UTUH`
- `item_id = 14` -> `AYAM PAHA PASAR`

Affected component-material groups: `2`

Daftar component:

- `83 - QUARTER CHICKEN GRILL`
- `130 - QUARTER CHICKEN STEAMED`

## Rekomendasi Cleanup

Urutan aman untuk pembersihan:

1. Bersihkan exact duplicate di resep produk `MIE AYAM NAMUA` karena line-nya identik.
2. Tentukan item kanonik untuk tiap material split yang paling banyak dipakai:
   - `GULA PASIR KITCHEN` vs `GULA KITCHEN`
   - `AIR MINERAL GALON` (`189` vs `220`)
   - `FRESH MILK` vs `FRESH MILK UHT`
   - `AYAM UTUH` vs `AYAM PAHA PASAR`
3. Setelah item kanonik dipilih, merge line formula per component dengan menjumlahkan qty ke item yang dipertahankan.
4. Untuk pasangan air, rapikan dulu `mst_item.id = 189` agar `item_name` tidak mengandung `CRLF`.

## Catatan Operasional

- Audit ini hanya membaca data, tidak melakukan perubahan schema maupun delete/update data.
- Belum ada SQL cleanup yang dijalankan. Jika cleanup akan dilakukan di server, siapkan SQL terpisah per pola agar bisa direview manual sebelum eksekusi.