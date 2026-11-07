---
name: Presensio
description: Sistem informasi manajemen sekolah tunggal utilitarian dan presisi operasional
colors:
    primary: 'oklch(0.205 0 0)'
    primary-foreground: 'oklch(0.985 0 0)'
    background: 'oklch(1 0 0)'
    foreground: 'oklch(0.145 0 0)'
    muted: 'oklch(0.97 0 0)'
    muted-foreground: 'oklch(0.556 0 0)'
    secondary: 'oklch(0.97 0 0)'
    secondary-foreground: 'oklch(0.205 0 0)'
    border: 'oklch(0.922 0 0)'
    input: 'oklch(0.922 0 0)'
    destructive: 'oklch(0.577 0.245 27.325)'
    destructive-foreground: 'oklch(0.577 0.245 27.325)'
    ring: 'oklch(0.87 0 0)'
    card: 'oklch(1 0 0)'
    card-foreground: 'oklch(0.145 0 0)'
typography:
    display:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '1.875rem'
        fontWeight: 600
        lineHeight: 1.2
        letterSpacing: '-0.025em'
    headline:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '1.5rem'
        fontWeight: 600
        lineHeight: 1.25
        letterSpacing: '-0.02em'
    title:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '1.25rem'
        fontWeight: 600
        lineHeight: 1.3
        letterSpacing: '-0.02em'
    body:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '0.875rem'
        fontWeight: 400
        lineHeight: 1.5
        letterSpacing: '0em'
    label:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '0.75rem'
        fontWeight: 500
        lineHeight: 1.4
        letterSpacing: '0.01em'
rounded:
    sm: '6px'
    md: '8px'
    lg: '10px'
    xl: '12px'
spacing:
    xs: '4px'
    sm: '8px'
    md: '16px'
    lg: '24px'
    xl: '32px'
components:
    button-primary:
        backgroundColor: '{colors.primary}'
        textColor: '{colors.primary-foreground}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
    button-outline:
        backgroundColor: 'transparent'
        textColor: '{colors.foreground}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
    badge:
        backgroundColor: '{colors.secondary}'
        textColor: '{colors.secondary-foreground}'
        rounded: '{rounded.md}'
        padding: '2px 8px'
---

# Design System: Presensio

## Overview

**Creative North Star: "The Operational Ledger"**

Presensio adalah instrumen kerja institusi pendidikan yang mengutamakan kecepatan pemrosesan, densitas informasi teratur, dan ketepatan status di atas dekorasi permukaan. Sebagai sistem tunggal yang menangani presensi gerbang waktu-nyata, penilaian Kurikulum Merdeka berjenjang, dan pembukuan double-entry keuangan sekolah, antarmuka dirancang untuk meminimalkan beban kognitif operator, guru, kasir, dan wali murid.

Filosofi estetika bertumpu pada kejelasan tipografi sans-serif modern (`Instrument Sans`), kontras fungsional tinggi, garis batas tegas tanpa gradien lunak, serta hierarki yang dapat dipindai seketika (glanceable status). Seluruh komponen mendukung mode terang dan gelap secara alami melalui token semantik OKLCH.

**Key Characteristics:**

- **High Data Density:** Tabel dan form dioptimalkan untuk entri data cepat dan pembacaan multi-kolom yang efisien.
- **Semantic Certainty:** Status kehadiran, jurnal berimbang, dan persetujuan izin menggunakan warna fungsional yang eksplisit dan konsisten.
- **Quiet Scaffolding:** Bingkai aplikasi, sidebar, dan header tidak bersaing memperebutkan perhatian pengguna terhadap konten kerja.

## Colors

Palet warna Presensio dibangun di atas kanvas monokromatik netral berbobot profesional dengan aksen semantik yang terkendali.

### Primary

- **Deep Obsidian / Pure Light** (`oklch(0.205 0 0)` / `.dark: oklch(0.985 0 0)`): Digunakan untuk tombol tindakan primer, elemen aktif sidebar, dan fokus navigasi penting.

### Neutral

- **Background:** `oklch(1 0 0)` (Light) / `oklch(0.145 0 0)` (Dark) — kanvas utama halaman.
- **Card & Surface:** `oklch(1 0 0)` (Light) / `oklch(0.145 0 0)` (Dark) — bidang kontainer data.
- **Muted Surface:** `oklch(0.97 0 0)` (Light) / `oklch(0.269 0 0)` (Dark) — latar belakang header tabel dan baris non-aktif.
- **Border & Dividers:** `oklch(0.922 0 0)` (Light) / `oklch(0.269 0 0)` (Dark) — garis pemisah struktural.

### Semantic Accents

- **Destructive:** `oklch(0.577 0.245 27.325)` — status tidak hadir, pembatalan, selisih jurnal, dan tindakan hapus permanen.
- **Success:** Emerald tone (`emerald-600` / `.dark: emerald-400`) — status hadir, jurnal seimbang, dan verifikasi lunas.
- **Warning:** Amber tone (`amber-600` / `.dark: amber-400`) — status terlambat, tagihan menunggu verifikasi, dan draf belum diposting.

### Named Rules

**The Functional Reserve Rule.** Warna aksen dan warna status semantik hanya boleh muncul pada indikator status, badge, atau tombol tindakan destruktif. Maksimal 10% luas tampilan yang boleh memiliki warna aksen.

## Typography

**Display / Sans Font:** `Instrument Sans`, ui-sans-serif, system-ui, sans-serif
**Monospace Font:** ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace (untuk kode akun, nomor induk, nominal rupiah, dan timestamp).

**Character:** Tipografi geometris bersih dengan tracking sedikit rapat pada ukuran besar untuk kesan institusi modern yang presisi.

### Hierarchy

- **Display / Page Title** (SemiBold 600, `1.25rem` / `20px`, tracking `-0.02em`, line-height `1.3`): Judul utama halaman (tag `<h1>` via `Heading`).
- **Headline / Section Header** (SemiBold 600, `1.125rem` / `18px`, line-height `1.4`): Judul modul atau judul modal dialog.
- **Title / Card Title** (SemiBold 600, `1rem` / `16px`, line-height `1.4`): Judul kartu metrik atau pengelompokan tabel.
- **Body** (Regular 400, `0.875rem` / `14px`, line-height `1.5`): Teks isi, baris tabel data, deskripsi input.
- **Label / Micro** (Medium 500, `0.75rem` / `12px`, line-height `1.4`): Label form, badge status, teks keterangan sekunder.

## Layout

Aplikasi menggunakan layout fluid responsif di dalam `AppSidebarLayout` dengan aturan ritme vertikal dan horizontal:

- **Root Page Rhythm:** Container halaman menggunakan padding `p-4` (atau `p-4 md:p-6` pada detail membaca) dengan pemisah blok `space-y-6`.
- **Constraint Width:**
    - Halaman tabel manajemen utama (`students`, `classes`, `attendance`, `fees`) mengisi lebar penuh (fluid 100%) dengan overflow-x horizontal.
    - Halaman form input terpusat dibatasi pada `max-w-3xl` atau `max-w-4xl mx-auto` untuk kenyamanan entri mata.
- **Header Flex Pattern:** Baris judul diletakkan di `<div className="flex flex-wrap items-center justify-between gap-4">` tanpa margin bawah berlebih dari elemen judul.

## Elevation & Depth

Presensio menganut prinsip **Tonal & Border Layering** (Flat-By-Default). Kedalaman tidak dibangun menggunakan drop-shadow tebal, melainkan melalui garis tepi halus (`border-border`), kontras latar belakang antar-lapisan (`bg-background` vs `bg-muted/50`), dan radius sudut konsisten.

### Shadow Vocabulary

- **Subtle Surface** (`shadow-xs` / `shadow-sm`): Diterapkan pada kartu metrik dashboard dan modal dialog yang melayang.
- **Hover Row Highlight:** Baris tabel interaktif menggunakan perubahan warna latar `hover:bg-muted/50` tanpa bayangan.

## Shapes

- **Radius Scale:**
    - `sm` (`6px`): Tombol kecil, badge status, tag kecil.
    - `md` (`8px`): Input field, tombol standar, card internal.
    - `lg` (`10px`): Card kontainer utama, container tabel bergaris tepi.
    - `xl` (`12px`): Modal dialog dan flyout container.
- **Pill Shapes:** Hanya diizinkan pada badge status berdimensi mikro (`rounded-full`), tidak untuk tombol tindakan utama.

## Components

### Buttons

- **Shape:** Rounded-md (`8px`).
- **Primary:** Background `bg-primary text-primary-foreground` dengan hover halus `hover:bg-primary/90`.
- **Outline:** Background transparan dengan border `border-border` dan hover `hover:bg-accent`.
- **Destructive:** Background transparan `variant="ghost"` dengan warna teks `text-destructive hover:bg-destructive/10`.

### Tables

- **Container:** `<div className="overflow-x-auto rounded-lg border border-border">`.
- **Header:** `<thead className="bg-muted/50 text-muted-foreground text-xs font-medium">`.
- **Rows:** `<tr className="border-t border-border hover:bg-muted/50 transition-colors">`.
- **Cells:** Padding seragam `px-4 py-3 text-sm`.

### Status Badges

- **Shape:** Rounded-md (`px-2 py-0.5 text-xs font-medium`).
- **Variants:** `default` (netral pekat), `secondary` (abu-abu tenang), `destructive` (merah), `outline` (garis tepi), `success` (hijau terang / emerald), `warning` (amber).

### Form Inputs

- **Inputs:** Tinggi `h-9`, border `border-input`, fokus cincin `focus-visible:ring-ring/50 focus-visible:ring-[3px]`.
- **Selects:** Standardisasi menggunakan komponen `@/components/ui/select` dengan menu melayang bersudut teratur.

## Do's and Don'ts

### Do:

- **Do** selalu menyediakan tag semantik `<h1>` pada setiap halaman (melalui komponen `Heading` terstandarisasi).
- **Do** mendeklarasikan breadcrumbs melalui hook `setLayoutProps({ breadcrumbs: [...] })` dengan teks yang dilokalisasi via `t(...)`.
- **Do** menyertakan status hover `hover:bg-muted/50` pada setiap baris tabel data interaktif.
- **Do** menjaga tombol batal modal menggunakan `variant="outline"` dan tombol konfirmasi destruktif menggunakan `variant="destructive"`.

### Don'ts:

- **Don't** menempelkan properti statis `PageName.layout = { breadcrumbs: [...] }` karena diabaikan oleh Inertia v3 layout resolver.
- **Don't** memberikan class margin keras `mb-8` pada komponen `Heading` saat berada di dalam container flex `items-center justify-between`.
- **Don't** menggunakan warna teks hardcoded (seperti `text-green-700` atau `text-amber-600`) tanpa penanganan kontras mode gelap.
- **Don't** menggunakan tag native `<select>` dengan class styling manual yang disalin acak; gunakan komponen terstandarisasi.
