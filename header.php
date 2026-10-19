<?php
require_once 'config.php';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Hitung jumlah item di keranjang (kalau sudah login)
$cart_count = 0;
$chat_unread_count = 0;
if (!empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total FROM carts WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $cart_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Jumlah pesan dari admin/owner yang belum dibaca buyer ini
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM admin_chat_messages m
        JOIN admin_conversations c ON m.conversation_id = c.id
        WHERE c.buyer_id = ? AND m.sender_type = 'staff' AND m.is_read_by_buyer = 0
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $chat_unread_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Untuk admin/owner: jumlah pesan buyer yang belum dibalas, di seluruh percakapan
$admin_unread_count = 0;
if (!empty($_SESSION['logged_in']) && in_array($_SESSION['role'] ?? '', ['admin', 'owner'], true)) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM admin_chat_messages
        WHERE sender_type = 'buyer' AND is_read_by_staff = 0
    ");
    $stmt->execute();
    $admin_unread_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DailyKicks - Step Into Greatness</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="style.css?v=11">
    <link rel="stylesheet" href="assets/css/account-theme.css?v=1">
    <style>
        /* ===== Fix: cegah overflow horizontal yang bikin konten kegeser & tidak center =====
           Kalau ada elemen di halaman (form, teks panjang, dll) yang sedikit lebih lebar dari
           layar, browser jadi bisa di-scroll ke kanan dan semua isi terlihat "geser"/kepotong
           di sisi kanan. Ini mengunci body supaya tidak pernah scroll ke samping. */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }

        /* ===== Perbaikan logo & tombol auth di navbar (kontras & konsistensi warna) ===== */
        .site-header .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 72px;
            height: 72px;
            padding: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFFDF9, #FFF3E9);
            box-shadow: 0 6px 18px rgba(58, 20, 90, 0.22), inset 0 0 0 1.5px rgba(139, 61, 247, 0.12);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .site-header .logo:hover {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 10px 24px rgba(58, 20, 90, 0.3), inset 0 0 0 1.5px rgba(139, 61, 247, 0.2);
        }
        .site-header .logo img {
            width: 80%;
            height: auto;
            display: block;
            filter: none;
        }

        .nav-actions .btn.btn-sm {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            padding: 9px 22px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
        }
        .nav-actions .btn.btn-sm::after {
            content: "";
            position: absolute;
            top: 0; left: -60%;
            width: 40%; height: 100%;
            background: linear-gradient(115deg, transparent, rgba(255,255,255,0.6), transparent);
            transform: skewX(-20deg);
            transition: left 0.6s ease;
        }
        .nav-actions .btn.btn-sm:hover::after { left: 130%; }

        .nav-actions .btn-outline.btn-sm {
            background: rgba(255, 255, 255, 0.14);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            color: #ffffff;
            backdrop-filter: blur(6px);
        }
        .nav-actions .btn-outline.btn-sm:hover {
            background: rgba(255, 255, 255, 0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(58, 20, 90, 0.18);
        }

        .nav-actions .btn-accent.btn-sm {
            background: #ffffff;
            color: #6D28D9;
            border: none;
            box-shadow: 0 8px 20px rgba(20, 8, 35, 0.22);
        }
        .nav-actions .btn-accent.btn-sm:hover {
            transform: translateY(-2px);
            background: #FFF3E9;
            box-shadow: 0 12px 26px rgba(20, 8, 35, 0.3);
        }

        @media (max-width: 576px) {
            .site-header .logo { width: 60px; height: 60px; }
            .site-header .logo img { width: 80%; height: auto; }
            .nav-actions .btn.btn-sm { padding: 7px 16px; font-size: 0.78rem; }
        }
    </style>
    <link rel="stylesheet" href="animations.css?v=1">
    <style id="dk-bg-effects">
/* =====================================================================
   DailyKicks - background-effects.css  (v2 - versi lebih terlihat)
   Link SETELAH style.css & animations.css.
   ===================================================================== */

/* ---------- 1. BODY ---------- */
body::before {
  content: "";
  position: fixed;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  background:
    radial-gradient(600px 600px at 6% 10%,  rgba(139, 92, 246, 0.35), transparent 70%),
    radial-gradient(560px 560px at 96% 40%, rgba(249, 115, 22, 0.22), transparent 70%),
    radial-gradient(640px 640px at 20% 95%, rgba(168, 85, 247, 0.30), transparent 70%),
    radial-gradient(rgba(167, 120, 255, 0.22) 1.2px, transparent 1.8px) 0 0 / 24px 24px;
}

/* ---------- 2. HEADER: titik + garis diagonal ---------- */
.site-header .nav-row { position: relative; z-index: 2; }
.site-header::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    radial-gradient(rgba(255, 255, 255, 0.32) 1.2px, transparent 1.8px) 0 0 / 18px 18px,
    repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.07) 0 2px, transparent 2px 16px);
  -webkit-mask-image: linear-gradient(90deg, #000 0%, rgba(0,0,0,.25) 35%, rgba(0,0,0,.25) 65%, #000 100%);
          mask-image: linear-gradient(90deg, #000 0%, rgba(0,0,0,.25) 35%, rgba(0,0,0,.25) 65%, #000 100%);
}

/* ---------- 3. HERO ---------- */
body .hero {
  background:
    radial-gradient(rgba(255, 255, 255, 0.16) 1.2px, transparent 1.8px) 0 0 / 26px 26px,
    radial-gradient(circle at 15% 20%, #4a2088 0%, transparent 48%),
    radial-gradient(circle at 85% 45%, rgba(124, 58, 237, 0.5) 0%, transparent 52%),
    var(--dark);
}

/* cincin sonar di belakang foto */
body .hero::after {
  display: block;
  content: "";
  position: absolute;
  inset: 0;
  width: auto; height: auto;
  border-radius: 0;
  filter: none;
  opacity: 1;
  z-index: 0;
  pointer-events: none;
  background: repeating-radial-gradient(
    circle at 76% 46%,
    transparent 0 52px,
    rgba(255, 255, 255, 0.22) 53px 55px
  );
  -webkit-mask-image: radial-gradient(circle at 76% 46%, #000 0%, transparent 68%);
          mask-image: radial-gradient(circle at 76% 46%, #000 0%, transparent 68%);
  animation: dk-rings 6s ease-in-out infinite;
}
@keyframes dk-rings {
  0%, 100% { transform: scale(1);    opacity: 0.8; }
  50%      { transform: scale(1.07); opacity: 1; }
}

/* partikel bercahaya */
.hero-grid::before {
  content: "";
  position: absolute;
  inset: -20px 0;
  z-index: -1;
  pointer-events: none;
  background:
    radial-gradient(circle, #ffb37a 0 3px, rgba(255,179,122,.35) 4px, transparent 9px) 8%  82% / 120px 120px no-repeat,
    radial-gradient(circle, #d6bcff 0 4px, rgba(196,160,255,.35) 5px, transparent 11px) 46% 18% / 140px 140px no-repeat,
    radial-gradient(circle, #ffffff 0 2.5px, rgba(255,255,255,.3) 3.5px, transparent 8px) 90% 70% / 110px 110px no-repeat,
    radial-gradient(circle, #d6bcff 0 3px, rgba(196,160,255,.35) 4px, transparent 9px) 62% 92% / 130px 130px no-repeat,
    radial-gradient(circle, #ffb37a 0 2.5px, rgba(255,179,122,.35) 3.5px, transparent 8px) 30% 6% / 100px 100px no-repeat;
  animation: dk-drift 8s ease-in-out infinite;
}
@keyframes dk-drift {
  0%, 100% { transform: translateY(0); }
  50%      { transform: translateY(-18px); }
}

/* tulisan besar "KICKS" bergaris di belakang foto */
.hero-grid::after {
  content: "KICKS";
  position: absolute;
  right: -10px;
  bottom: -50px;
  z-index: -1;
  pointer-events: none;
  font-size: clamp(110px, 19vw, 270px);
  font-weight: 900;
  line-height: 1;
  letter-spacing: 0.04em;
  color: transparent;
  -webkit-text-stroke: 2px rgba(255, 255, 255, 0.16);
}

.hero-ticker::before {
  content: "";
  position: absolute;
  left: 0; right: 0; top: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgba(167, 120, 255, 0.9), rgba(249, 115, 22, 0.8), transparent);
}

/* ---------- 4. KOLEKSI ---------- */
#koleksi::after {
  content: "";
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    radial-gradient(rgba(255, 255, 255, 0.30) 1.2px, transparent 1.8px) 0 0 / 22px 22px,
    repeating-radial-gradient(circle at 100% 0%, transparent 0 70px, rgba(255, 255, 255, 0.16) 71px 73px),
    repeating-radial-gradient(circle at 0% 100%, transparent 0 70px, rgba(255, 255, 255, 0.14) 71px 73px);
  -webkit-mask-image: linear-gradient(180deg, transparent 0%, #000 10%, #000 90%, transparent 100%);
          mask-image: linear-gradient(180deg, transparent 0%, #000 10%, #000 90%, transparent 100%);
}

/* ---------- 5. PROMO ---------- */
#promo-banner {
  position: relative;
  background:
    radial-gradient(500px 260px at 10% 50%, rgba(139, 92, 246, 0.35), transparent 70%),
    radial-gradient(500px 260px at 90% 50%, rgba(249, 115, 22, 0.28), transparent 70%),
    radial-gradient(rgba(255, 255, 255, 0.10) 1.2px, transparent 1.8px) 0 0 / 24px 24px;
}

/* ---------- 6. Reduced motion & mobile ---------- */
@media (prefers-reduced-motion: reduce) {
  body .hero::after,
  .hero-grid::before { animation: none; }
}
@media (max-width: 576px) {
  .hero-grid::after { font-size: 90px; bottom: -20px; }
  body .hero::after {
    background: repeating-radial-gradient(circle at 70% 78%, transparent 0 40px, rgba(255,255,255,0.2) 41px 43px);
    -webkit-mask-image: radial-gradient(circle at 70% 78%, #000 0%, transparent 62%);
            mask-image: radial-gradient(circle at 70% 78%, #000 0%, transparent 62%);
  }
}


/* ---------- 7. STRIP KATEGORI ---------- */
.cat-strip .cat-card {
  background:
    linear-gradient(90deg, #8B3DF7, #C13FE0, #F97316) top / 100% 3px no-repeat,
    radial-gradient(rgba(139, 61, 247, 0.14) 1.2px, transparent 1.8px) 0 0 / 20px 20px,
    linear-gradient(135deg, #f3edfd 0%, #fdfbff 55%, #fdf1e8 100%);
  border-color: rgba(139, 61, 247, 0.2);
}
.cat-strip .cat-row .cat-item { width: 88px; }
.cat-strip .cat-row .cat-item .cat-swatch { width: 68px; height: 68px; }
.cat-strip .cat-row .cat-item .cat-swatch svg { width: 28px; height: 28px; }

/* hover: lingkaran terisi gradien, ikon putih & bergoyang */
.cat-strip .cat-row .cat-item:hover .cat-swatch {
  background: linear-gradient(135deg, #8B3DF7, #C13FE0);
  box-shadow: 0 14px 26px rgba(139, 61, 247, 0.38);
}
.cat-strip .cat-row .cat-item:hover .cat-swatch svg {
  stroke: #ffffff;
  animation: dk-wiggle 0.6s ease;
}
@keyframes dk-wiggle {
  0%   { rotate: 0deg; }
  25%  { rotate: -12deg; }
  50%  { rotate: 10deg; }
  75%  { rotate: -6deg; }
  100% { rotate: 0deg; }
}

/* kategori aktif: cincin denyut di sekeliling lingkaran */
.cat-strip .cat-item.active .cat-swatch::before {
  content: "";
  position: absolute;
  inset: -5px;
  border-radius: 50%;
  border: 2px solid rgba(139, 61, 247, 0.5);
  pointer-events: none;
  animation: dk-halo 2s ease-out infinite;
}
@keyframes dk-halo {
  0%   { transform: scale(0.92); opacity: 0.9; }
  100% { transform: scale(1.18); opacity: 0; }
}

@media (max-width: 576px) {
  .cat-strip .cat-row .cat-item { width: 66px; }
  .cat-strip .cat-row .cat-item .cat-swatch { width: 54px; height: 54px; }
  .cat-strip .cat-row .cat-item .cat-swatch svg { width: 22px; height: 22px; }
}
@media (prefers-reduced-motion: reduce) {
  .cat-strip .cat-item.active .cat-swatch::before,
  .cat-strip .cat-row .cat-item:hover .cat-swatch svg { animation: none; }
}

/* ---------- 8. CHIP BRAND ---------- */
#koleksi .brand-row .brand-chip {
  padding: 7px 22px 7px 7px;
  font-size: 0.88rem;
  border-color: rgba(255, 255, 255, 0.6);
  box-shadow: 0 6px 16px rgba(30, 10, 70, 0.22);
}
.brand-initial {
  width: 30px;
  height: 30px;
  margin-right: 10px;
  flex-shrink: 0;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 800;
  color: #fff;
  background: linear-gradient(135deg, #8B3DF7, #F97316);
  transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.25s ease, color 0.25s ease;
}
.brand-initial svg { width: 14px; height: 14px; }
.brand-chip:hover .brand-initial { transform: rotate(360deg) scale(1.12); }

/* chip aktif: lencana jadi putih + cincin denyut */
.brand-chip.active .brand-initial { background: rgba(255, 255, 255, 0.95); color: #6D28D9; }
#koleksi .brand-row .brand-chip.active {
  animation: brand-glow 3.5s ease infinite, dk-chip-pulse 2.4s ease-out infinite;
}
@keyframes dk-chip-pulse {
  0%   { box-shadow: 0 8px 22px rgba(139, 61, 247, 0.35), 0 0 0 0 rgba(255, 255, 255, 0.55); }
  70%  { box-shadow: 0 8px 22px rgba(139, 61, 247, 0.35), 0 0 0 10px rgba(255, 255, 255, 0); }
  100% { box-shadow: 0 8px 22px rgba(139, 61, 247, 0.35), 0 0 0 0 rgba(255, 255, 255, 0); }
}

/* judul brand: garis bawah memanjang saat dilihat */
.brands-head p::after { transition: width 0.6s ease; }
.brands:hover .brands-head p::after { width: 90px; }

/* HP: chip jadi satu baris yang bisa digeser */
@media (max-width: 576px) {
  #koleksi .brand-row {
    flex-wrap: nowrap;
    overflow-x: auto;
    scroll-snap-type: x proximity;
    padding: 8px 2px 16px;
    margin-right: -16px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }
  #koleksi .brand-row::-webkit-scrollbar { display: none; }
  #koleksi .brand-row .brand-chip { flex-shrink: 0; scroll-snap-align: start; }
}
@media (prefers-reduced-motion: reduce) {
  #koleksi .brand-row .brand-chip.active { animation: none; }
  .brand-chip:hover .brand-initial { transform: none; }
}

/* ---------- 9. KARTU PRODUK ---------- */
/* kartu & isi dibuat sama tinggi, harga selalu sejajar di dasar */
.grid .card { border-radius: 24px; display: flex; flex-direction: column; }
.grid .card > a { display: flex; flex-direction: column; flex: 1; }
.grid .card .card-body { flex: 1; display: flex; flex-direction: column; padding: 16px 18px 18px; }
/* produk tanpa brand: turunkan nama supaya sejajar dengan kartu lain */
.grid .card .card-body > .card-name:first-child { margin-top: 1.35rem; }

/* area foto: rasio tetap, spotlight, latar putih foto melebur */
.grid .card .card-media {
  height: auto;
  aspect-ratio: 1 / 0.88;
  background:
    radial-gradient(circle at 50% 36%, #ffffff 0%, #f4edff 52%, #e9d8ff 100%);
}
.grid .card .card-media img {
  position: relative;
  z-index: 1;
  padding: 9%;
  object-fit: contain;
  mix-blend-mode: multiply;
}
/* bayangan lembut di bawah sepatu */
.grid .card .card-media::before {
  content: "";
  position: absolute;
  left: 50%;
  bottom: 15%;
  width: 56%;
  height: 16px;
  translate: -50% 0;
  background: radial-gradient(closest-side, rgba(58, 20, 90, 0.28), transparent);
  filter: blur(5px);
  transition: width 0.4s ease, opacity 0.4s ease;
}
.grid .card:hover .card-media::before { width: 42%; opacity: 0.7; }

/* brand: titik gradien kecil di depan nama */
.grid .card .card-brand::before {
  content: "";
  display: inline-block;
  width: 6px;
  height: 6px;
  margin-right: 6px;
  border-radius: 50%;
  vertical-align: middle;
  background: linear-gradient(135deg, #8B3DF7, #F97316);
}

/* baris harga: garis putus-putus + tombol panah */
.grid .card .price-row {
  margin-top: auto;
  padding-top: 12px;
  margin-bottom: 0;
  border-top: 1px dashed rgba(139, 61, 247, 0.22);
}
.grid .card .price-row::after {
  content: "\2192";
  margin-left: auto;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 1rem;
  font-weight: 800;
  color: #6D28D9;
  background: #f3ecff;
  transition: background 0.3s ease, color 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.grid .card:hover .price-row::after {
  color: #fff;
  background: linear-gradient(135deg, #8B3DF7, #F97316);
  transform: translateX(4px);
}

/* hover: bayangan lebih dalam */
.grid .card:hover { box-shadow: 0 26px 50px rgba(30, 10, 70, 0.38); }

@media (max-width: 576px) {
  .grid .card { border-radius: 20px; }
  .grid .card .card-body { padding: 14px 16px 16px; }
}

/* ---------- 10. BANNER GRATIS ONGKIR ---------- */
/* cincin besar di dua pojok, di atas gradien bawaan */
.promo {
  background:
    repeating-radial-gradient(circle at 100% 125%, transparent 0 46px, rgba(255, 255, 255, 0.17) 47px 49px),
    repeating-radial-gradient(circle at 0% -25%,  transparent 0 46px, rgba(255, 255, 255, 0.10) 47px 49px),
    var(--gradient-main);
  box-shadow: 0 24px 60px rgba(124, 58, 237, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.12) inset;
}

/* titik + garis "ngebut" yang bergerak di bagian tengah-kanan (di antara teks dan tombol) */
.promo::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    linear-gradient(90deg, transparent 0, rgba(255, 255, 255, 0.65) 45%, transparent 85%) 0 26% / 240px 2px repeat-x,
    linear-gradient(90deg, transparent 0, rgba(255, 255, 255, 0.5)  45%, transparent 85%) 80px 52% / 240px 2px repeat-x,
    linear-gradient(90deg, transparent 0, rgba(255, 255, 255, 0.6)  45%, transparent 85%) 160px 76% / 240px 2px repeat-x,
    radial-gradient(rgba(255, 255, 255, 0.28) 1.2px, transparent 1.8px) 0 0 / 22px 22px;
  -webkit-mask-image: linear-gradient(90deg, transparent 35%, #000 58%, #000 78%, transparent 94%);
          mask-image: linear-gradient(90deg, transparent 35%, #000 58%, #000 78%, transparent 94%);
  animation: dk-speed 2.8s linear infinite;
}
@keyframes dk-speed {
  from { background-position: 0 26%,     80px 52%,  160px 76%, 0 0; }
  to   { background-position: 240px 26%, 320px 52%, 400px 76%, 0 0; }
}

/* ikon truk: lebih besar + cincin denyut */
.promo .promo-icon {
  width: 84px;
  height: 84px;
  background: rgba(255, 255, 255, 0.2);
  border: 1.5px solid rgba(255, 255, 255, 0.5);
  animation: dk-icon-ring 2.4s ease-out infinite;
}
.promo .promo-icon svg { width: 40px; height: 40px; }
@keyframes dk-icon-ring {
  0%   { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.45); }
  70%  { box-shadow: 0 0 0 18px rgba(255, 255, 255, 0); }
  100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
}

/* lencana kecil di atas judul */
.promo h3 { text-shadow: 0 2px 14px rgba(40, 10, 90, 0.35); }
.promo h3::before {
  content: "PROMO HARI INI";
  display: block;
  width: max-content;
  margin-bottom: 10px;
  padding: 4px 12px;
  font-size: 0.66rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.45);
  text-shadow: none;
}

/* tombol: cincin putih berdenyut mengajak klik */
.promo .btn-accent { animation: dk-btn-pulse 2.6s ease-out infinite; }
.promo .btn-accent:hover { animation-play-state: paused; }
@keyframes dk-btn-pulse {
  0%   { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2), 0 0 0 0 rgba(255, 255, 255, 0.6); }
  70%  { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2), 0 0 0 14px rgba(255, 255, 255, 0); }
  100% { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2), 0 0 0 0 rgba(255, 255, 255, 0); }
}

@media (max-width: 768px) {
  .promo h3::before { margin-left: auto; margin-right: auto; }
  .promo .promo-icon { width: 68px; height: 68px; }
  .promo .promo-icon svg { width: 32px; height: 32px; }
  .promo::before { -webkit-mask-image: none; mask-image: none; opacity: 0.35; }
}
@media (prefers-reduced-motion: reduce) {
  .promo::before, .promo .promo-icon, .promo .btn-accent { animation: none; }
}

/* ---------- 11. FOOTER ---------- */
/* garis atas: gradien mengalir */
.site-footer::before {
  height: 4px;
  background: linear-gradient(90deg, #8B3DF7, #C13FE0, #F97316, #C13FE0, #8B3DF7);
  background-size: 250% 100%;
  animation: dk-flow 6s linear infinite;
}
@keyframes dk-flow {
  from { background-position: 0% 0; }
  to   { background-position: 250% 0; }
}

/* tulisan raksasa DAILYKICKS bergaris di belakang konten, bergeser sangat pelan */
.site-footer .footer-grid { position: relative; }
.site-footer .footer-grid > * { position: relative; z-index: 1; }
.site-footer .footer-grid::after {
  content: "DAILYKICKS";
  position: absolute;
  left: 50%;
  bottom: -70px;
  translate: -50% 0;
  z-index: 0;
  pointer-events: none;
  white-space: nowrap;
  font-size: clamp(70px, 14vw, 200px);
  font-weight: 900;
  line-height: 1;
  letter-spacing: 0.06em;
  color: transparent;
  -webkit-text-stroke: 1.5px rgba(167, 120, 255, 0.16);
  animation: dk-watermark 18s ease-in-out infinite alternate;
}
@keyframes dk-watermark {
  from { translate: -52% 0; }
  to   { translate: -48% 0; }
}

/* partikel bercahaya yang naik turun pelan */
.site-footer .footer-grid::before {
  content: "";
  position: absolute;
  inset: -20px 0;
  z-index: 0;
  pointer-events: none;
  background:
    radial-gradient(circle, #d6bcff 0 2.5px, rgba(196,160,255,.3) 3.5px, transparent 8px) 22% 20% / 120px 120px no-repeat,
    radial-gradient(circle, #ffb37a 0 2.5px, rgba(255,179,122,.3) 3.5px, transparent 8px) 55% 78% / 120px 120px no-repeat,
    radial-gradient(circle, #ffffff 0 2px, rgba(255,255,255,.25) 3px, transparent 7px) 78% 15% / 100px 100px no-repeat,
    radial-gradient(circle, #d6bcff 0 2px, rgba(196,160,255,.3) 3px, transparent 7px) 92% 70% / 100px 100px no-repeat;
  animation: dk-drift 9s ease-in-out infinite;
}

/* logo kecil melayang pelan */
.site-footer .footer-logo > :first-child { animation: dk-bob 3.2s ease-in-out infinite; }
@keyframes dk-bob {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  50%      { transform: translateY(-4px) rotate(-4deg); }
}

/* judul kolom: garis bawah memanjang saat kolom di-hover */
.site-footer .footer-grid h4::after { transition: width 0.35s ease; }
.site-footer .footer-grid > div:hover h4::after { width: 48px; }

/* tautan: geser kanan + panah muncul dari kiri */
.site-footer .footer-grid a:not(:has(svg)) {
  position: relative;
  display: inline-block;
  transition: color 0.2s ease, transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}
.site-footer .footer-grid a:not(:has(svg))::before {
  content: "\2192";
  position: absolute;
  left: -18px;
  opacity: 0;
  color: #F97316;
  transition: opacity 0.2s ease, left 0.25s ease;
}
.site-footer .footer-grid a:not(:has(svg)):hover { transform: translateX(14px); }
.site-footer .footer-grid a:not(:has(svg)):hover::before { opacity: 1; left: -16px; }

/* ikon sosial: terisi gradien, terangkat, bergoyang */
.site-footer .footer-grid a:has(> svg) {
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.25s ease,
              box-shadow 0.25s ease, border-color 0.25s ease;
}
.site-footer .footer-grid a:has(> svg):hover {
  background: linear-gradient(135deg, #8B3DF7, #F97316);
  border-color: transparent;
  color: #fff;
  transform: translateY(-5px) scale(1.12) rotate(-6deg);
  box-shadow: 0 10px 22px rgba(139, 61, 247, 0.5);
}

/* "dibuat dengan cinta": detak jantung */
.site-footer .footer-bottom [class*="heart"] {
  display: inline-block;
  animation: dk-beat 1.4s ease-in-out infinite;
}
@keyframes dk-beat {
  0%, 100% { transform: scale(1); }
  15%      { transform: scale(1.3); }
  30%      { transform: scale(1); }
  45%      { transform: scale(1.2); }
}

@media (max-width: 576px) {
  .site-footer .footer-grid::after { bottom: -30px; font-size: 64px; }
  .site-footer .footer-grid a:not(:has(svg)):hover { transform: none; }
  .site-footer .footer-grid a:not(:has(svg))::before { display: none; }
}
@media (prefers-reduced-motion: reduce) {
  .site-footer::before,
  .site-footer .footer-grid::before,
  .site-footer .footer-grid::after,
  .site-footer .footer-logo > :first-child,
  .site-footer .footer-bottom [class*="heart"] { animation: none; }
}
    </style>
</head>
<body>

<header class="site-header">
    <div class="wrap nav-row">
        <a href="index.php" class="logo"><img src="logo.png" alt="DailyKicks"></a>

        <div class="nav-search">
            <form action="index.php" method="GET">
                <input type="text" name="q" placeholder="Cari sepatu impianmu..." value="<?= sanitize($search ?? '') ?>">
                <button type="submit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                </button>
            </form>
        </div>

        <div class="nav-actions">
            <?php if (!empty($_SESSION['logged_in'])): ?>
                <a href="chat.php" class="icon-btn" id="chatIconBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <?php if ($chat_unread_count > 0): ?>
                        <span class="cart-badge" id="chatUnreadBadge"><?= $chat_unread_count ?></span>
                    <?php else: ?>
                        <span class="cart-badge" id="chatUnreadBadge" style="display:none;">0</span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <a href="cart.php" class="icon-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>

            <?php if (!empty($_SESSION['logged_in'])): ?>
                <div class="user-menu">
                    <?php
                        $full_name  = trim($_SESSION['name'] ?? '');
                        $first_name = $full_name !== '' ? explode(' ', $full_name)[0] : 'Akun';
                        $initial    = mb_strtoupper(mb_substr($first_name, 0, 1));
                    ?>
                    <button class="user-btn" onclick="document.getElementById('userDropdown').classList.toggle('show')" aria-label="Menu akun">
                        <span class="user-avatar"><?= sanitize($initial) ?></span>
                        <span class="user-name-text"><?= sanitize($first_name) ?></span>
                        <svg class="user-caret" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="dropdown-menu" id="userDropdown">
                        <?php if ($_SESSION['role'] === 'owner'): ?>
                            <a href="owner_dashboard.php">Dashboard Toko
                                <span class="cart-badge" id="adminUnreadBadge" style="<?= $admin_unread_count > 0 ? '' : 'display:none;' ?>"><?= $admin_unread_count ?></span>
                            </a>
                            <hr class="dropdown-divider">
                        <?php elseif ($_SESSION['role'] === 'admin'): ?>
                            <a href="admin_dashboard.php">Dashboard Admin
                                <span class="cart-badge" id="adminUnreadBadge" style="<?= $admin_unread_count > 0 ? '' : 'display:none;' ?>"><?= $admin_unread_count ?></span>
                            </a>
                            <hr class="dropdown-divider">
                        <?php endif; ?>
                        <a href="my_orders.php">Pembelian Saya</a>
                        <a href="chat.php">Pesan</a>
                        <a href="wishlist.php">Favorit Saya</a>
                        <a href="profile.php">Profil Saya</a>
                        <a href="addresses.php">Alamat Saya</a>
                        <hr class="dropdown-divider">
                        <a href="logout.php" class="text-danger">Keluar</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline btn-sm">Masuk</a>
                <a href="register.php" class="btn btn-accent btn-sm">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
document.addEventListener('click', function(e) {
    const menu = document.getElementById('userDropdown');
    if (!menu) return;
    const btn = document.querySelector('.user-btn');
    if (btn && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
    }
});

// (Efek shadow navbar saat scroll sekarang diatur di animations.css / animations.js)

// Update badge jumlah pesan belum dibaca secara berkala (tanpa reload halaman)
const chatBadge = document.getElementById('chatUnreadBadge');
if (chatBadge) {
    async function refreshChatUnread() {
        try {
            const res = await fetch('chat_unread_count.php', { cache: 'no-store' });
            const data = await res.json();
            if (data.unread > 0) {
                chatBadge.textContent = data.unread;
                chatBadge.style.display = '';
            } else {
                chatBadge.style.display = 'none';
            }
        } catch (e) { /* diamkan, coba lagi di interval berikutnya */ }
    }
    setInterval(refreshChatUnread, 15000);
    window.addEventListener('focus', refreshChatUnread);
}

// Update badge jumlah pesan buyer belum dibalas untuk admin/owner (di dropdown akun)
const adminBadge = document.getElementById('adminUnreadBadge');
if (adminBadge) {
    async function refreshAdminUnread() {
        try {
            const res = await fetch('admin_unread_count.php', { cache: 'no-store' });
            const data = await res.json();
            if (data.unread > 0) {
                adminBadge.textContent = data.unread;
                adminBadge.style.display = '';
            } else {
                adminBadge.style.display = 'none';
            }
        } catch (e) { /* diamkan, coba lagi di interval berikutnya */ }
    }
    setInterval(refreshAdminUnread, 15000);
    window.addEventListener('focus', refreshAdminUnread);
}
</script>