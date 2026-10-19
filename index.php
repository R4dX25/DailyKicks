<?php
require_once 'header.php';

// Filter
$search   = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$brand    = isset($_GET['brand']) ? sanitize($_GET['brand']) : '';

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");

// Pilih ikon SVG berdasarkan nama kategori, biar tiap kategori keliatan beda
function category_icon_svg($name) {
    $name = mb_strtolower($name);
    if (strpos($name, 'run') !== false || strpos($name, 'lari') !== false) {
        // ikon jejak kaki (running)
        return '<ellipse cx="9" cy="7" rx="2.2" ry="3"/><ellipse cx="9" cy="15" rx="2.6" ry="3.6"/><ellipse cx="16" cy="10" rx="2" ry="2.8"/><ellipse cx="16" cy="17" rx="2.4" ry="3.2"/>';
    }
    if (strpos($name, 'basket') !== false) {
        // ikon bola basket
        return '<circle cx="12" cy="12" r="8"/><path d="M4 12h16"/><path d="M12 4v16"/><path d="M6.5 6.5c2 2 2 9 0 11"/><path d="M17.5 6.5c-2 2-2 9 0 11"/>';
    }
    if (strpos($name, 'train') !== false || strpos($name, 'gym') !== false || strpos($name, 'fitness') !== false) {
        // ikon dumbbell
        return '<rect x="2" y="9" width="3" height="6" rx="1"/><rect x="19" y="9" width="3" height="6" rx="1"/><rect x="6" y="10.5" width="3" height="3" rx="0.5"/><rect x="15" y="10.5" width="3" height="3" rx="0.5"/><path d="M9 12h6"/>';
    }
    if (strpos($name, 'boot') !== false) {
        // ikon sepatu boots (bagian betis tinggi)
        return '<path d="M6 3h6v7l6.5 3a2.5 2.5 0 0 1 1.5 2.3V19H6V3Z"/><path d="M6 15h14"/><path d="M6 7h6"/>';
    }
    if (strpos($name, 'slip') !== false) {
        // ikon slip-on (rendah, tanpa tali)
        return '<path d="M3 15.5V13c0-1.3 1-2 2.5-2H9l2.5 2c1 .8 2.5 1 5 1.5 2.5.5 4.5 1.3 4.5 2.5v.5H3Z"/><path d="M3 15.5V18h18v-2.5"/><path d="M9 11c.4-1.4 1.8-2.4 3.5-2.4"/>';
    }
    // default: sepatu casual/lifestyle
    return '<path d="M4 16h13a3 3 0 0 0 3-3v-1l-4-3-3 1-4-4-5 2v8Z"/><path d="M4 16v3h16v-2"/>';
}

// Daftar brand unik yang sudah pernah diinput (untuk chip filter)
$brand_list = $conn->query("SELECT brand_name AS brand FROM brands ORDER BY brand_name ASC")->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT p.*
        FROM products p
        WHERE p.status = 'aktif'
          AND EXISTS (
            SELECT 1 FROM product_variants v
            WHERE v.product_id = p.id AND v.stock > 0
        )";
$params = [];
$types  = "";

if (!empty($search)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%" . $search . "%";
    $types   .= "s";
}
if ($category > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
    $types   .= "i";
}
if (!empty($brand)) {
    $sql .= " AND p.brand = ?";
    $params[] = $brand;
    $types   .= "s";
}
$sql .= " ORDER BY p.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Ambil daftar produk yang sudah difavoritkan user yang sedang login
$wishlist_ids = [];
if (!empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $wl = $conn->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
    $wl->bind_param("i", $uid);
    $wl->execute();
    $wlResult = $wl->get_result();
    while ($row = $wlResult->fetch_assoc()) {
        $wishlist_ids[] = (int) $row['product_id'];
    }
    $wl->close();
}

// Foto untuk slideshow hero (dari Unsplash, bebas dipakai)
$hero_slides = [
    ['src' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80', 'alt' => 'Sepatu lari merah'],
    ['src' => 'https://images.unsplash.com/photo-1496579538151-212636d0b01c?auto=format&fit=crop&w=800&q=80', 'alt' => 'Sepatu lari warna-warni'],
    ['src' => 'https://images.unsplash.com/photo-1562613521-6b5293e5b0ea?auto=format&fit=crop&w=800&q=80', 'alt' => 'Sepatu Nike multicolor'],
];

// Pilihan foto slideshow hero:
//   false = pakai foto di atas (Unsplash)
//   true  = pakai foto produk terbaru toko (nama + harga tampil di kartu)
$hero_pakai_produk = false;

$hero_products = [];
$hero_q = !$hero_pakai_produk ? false : $conn->query("SELECT p.id, p.name, p.price, p.image
                        FROM products p
                        WHERE p.status = 'aktif'
                          AND p.image IS NOT NULL AND p.image <> ''
                          AND EXISTS (
                              SELECT 1 FROM product_variants v
                              WHERE v.product_id = p.id AND v.stock > 0
                          )
                        ORDER BY p.id DESC
                        LIMIT 8");
if ($hero_q) {
    while ($row = $hero_q->fetch_assoc()) {
        if (file_exists('uploads/products/' . $row['image'])) {
            $hero_products[] = $row;
        }
        if (count($hero_products) >= 4) break;
    }
}
$use_products = $hero_pakai_produk && count($hero_products) > 0;
$hero_count   = $use_products ? count($hero_products) : count($hero_slides);
?>

<main>
    <style>
        .chat-quick-btn {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 2;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(58,20,90,0.18);
            color: #6D28D9;
            text-decoration: none;
            transition: transform 0.15s ease, background 0.15s ease, color 0.15s ease;
        }
        .chat-quick-btn:hover {
            background: linear-gradient(135deg, #6D28D9, #F97316);
            color: #fff;
            transform: translateY(-1px);
        }
        .chat-quick-btn svg { width: 17px; height: 17px; }
        .card { position: relative; }

        @media (max-width: 576px) {
            .chat-quick-btn { width: 30px; height: 30px; top: 8px; left: 8px; }
            .chat-quick-btn svg { width: 15px; height: 15px; }
        }

        /* ===== Hero ===== */
        .hero {
            position: relative;
            overflow: hidden;
            /* cahaya ungu lembut saja, tanpa noda oranye/cokelat */
            background:
                radial-gradient(circle at 15% 20%, #3b1a6b 0%, transparent 45%),
                radial-gradient(circle at 85% 45%, rgba(109, 40, 217, 0.32) 0%, transparent 50%),
                var(--dark);
        }
        .hero::before { animation: none; opacity: 0.35; }
        .hero::after { display: none; }

        .hero-grid { position: relative; z-index: 1; }

        /* judul satu warna, tidak berkilau */
        .hero-title-gradient { color: #FFE7D1; }

        .hero-badges-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .hero-badges-row .hero-eyebrow { margin-bottom: 0; }

        .hero-trust-row {
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.88);
            font-size: 0.86rem;
            font-weight: 600;
        }
        .hero-trust-item svg { width: 17px; height: 17px; flex-shrink: 0; color: #FFB37A; }

        /* ----- Foto/produk di kanan ----- */
        .hero-display { position: relative; z-index: 1; }
        /* satu cahaya ungu lembut yang diam di belakang foto */
        .hero-display::before {
            content: "";
            position: absolute;
            inset: 8% -6%;
            background: radial-gradient(closest-side, rgba(139, 61, 247, 0.5), transparent 75%);
            filter: blur(24px);
            z-index: -1;
            pointer-events: none;
        }
        .hero-stage { position: relative; width: 100%; max-width: 460px; }

        /* satu-satunya gerakan di hero: kartu produk melayang pelan */
        .hero-slideshow {
            rotate: -6deg;
            animation: hero-float 4.5s ease-in-out infinite;
        }
        @keyframes hero-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .hero-slide {
            position: absolute;
            inset: 0;
            display: block;
            border-radius: 22px;
            overflow: hidden;
            background: linear-gradient(145deg, #ffffff 0%, #f3ecff 55%, #ffe9d6 100%);
            box-shadow: 0 25px 60px rgba(109, 40, 217, 0.45);
            opacity: 0;
            z-index: 0;
            pointer-events: none;
            transition: opacity 0.7s ease;
        }
        /* foto lama tetap terlihat sampai foto baru selesai muncul (tidak ada tumpukan pucat) */
        .hero-slide.prev { opacity: 1; z-index: 1; transition: none; }
        .hero-slide.active { opacity: 1; z-index: 2; pointer-events: auto; }
        .hero-photo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 7%;
            mix-blend-mode: multiply; /* latar putih foto produk menyatu dengan kartu */
        }
        .hero-slide.is-stock .hero-photo { object-fit: cover; padding: 0; mix-blend-mode: normal; }

        /* kartu info produk (nama + harga), berganti mengikuti foto */
        .hero-floating-card {
            position: absolute;
            bottom: -16px;
            left: -16px;
            z-index: 3;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 16px;
            padding: 10px 10px 10px 16px;
            box-shadow: 0 16px 34px rgba(20, 8, 35, 0.3);
            opacity: 0;
            translate: 0 8px;
            pointer-events: none;
            transition: opacity 0.5s ease, translate 0.5s ease;
        }
        .hero-floating-card.active { opacity: 1; translate: 0 0; pointer-events: auto; }
        .hero-floating-card-text { min-width: 0; }
        .hero-floating-card-text strong {
            display: block;
            max-width: 170px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.85rem;
            color: #241938;
        }
        .hero-floating-card-text span {
            display: block;
            font-size: 0.85rem;
            font-weight: 800;
            color: #6D28D9;
        }
        .hero-floating-card-icon {
            width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #8B3DF7, #F97316);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(139, 61, 247, 0.4);
            transition: transform 0.25s ease;
        }
        .hero-floating-card-icon svg { width: 16px; height: 16px; }
        .hero-floating-card:hover .hero-floating-card-icon { transform: translateX(3px); }

        /* titik penanda slide */
        .hero-dots {
            position: absolute;
            left: 50%;
            bottom: -34px;
            translate: -50% 0;
            display: flex;
            gap: 8px;
            z-index: 4;
        }
        .hero-dots button {
            width: 8px; height: 8px;
            padding: 0;
            border: none;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.35);
            cursor: pointer;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .hero-dots button:hover { background: rgba(255, 255, 255, 0.6); }
        .hero-dots button.active { width: 22px; background: linear-gradient(90deg, #8B3DF7, #F97316); }
        .hero-dots button:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }

        /* ----- Tombol ----- */
        .hero-ctas .btn {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .hero-ctas .btn::after {
            content: "";
            position: absolute;
            top: 0; left: -60%;
            width: 40%; height: 100%;
            background: linear-gradient(115deg, transparent, rgba(255,255,255,0.55), transparent);
            transform: skewX(-20deg);
            transition: left 0.6s ease;
        }
        .hero-ctas .btn:hover::after { left: 130%; }
        .hero-ctas .btn-accent {
            box-shadow: 0 12px 26px rgba(249, 115, 22, 0.32);
        }
        .hero-ctas .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(139, 61, 247, 0.38);
        }
        /* "Lihat Brand": putih supaya terbaca di latar gelap */
        .hero-ctas .btn-outline {
            border-color: rgba(255, 255, 255, 0.75);
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }
        .hero-ctas .btn-outline:hover { border-color: transparent; }

        /* ===== Running ticker (teks berjalan) ===== */
        .hero-ticker {
            position: relative;
            z-index: 1;
            margin-top: 44px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.14);
            overflow: hidden;
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
            mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
        }
        .hero-ticker-track {
            display: flex;
            width: max-content;
            gap: 48px;
            animation: ticker-scroll 40s linear infinite;
        }
        .hero-ticker:hover .hero-ticker-track { animation-play-state: paused; }
        .hero-ticker-item {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            font-size: 0.92rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.92);
        }
        .hero-ticker-item svg { width: 16px; height: 16px; flex-shrink: 0; color: #FFB37A; }
        @keyframes ticker-scroll {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }

        @media (max-width: 576px) {
            .hero-stage { max-width: 320px; }
            .hero-floating-card { left: -8px; bottom: -14px; padding: 8px 8px 8px 14px; gap: 10px; }
            .hero-floating-card-text strong { font-size: 0.78rem; max-width: 130px; }
            .hero-floating-card-text span { font-size: 0.78rem; }
            .hero-floating-card-icon { width: 30px; height: 30px; }
            .hero-trust-row { gap: 14px; }
            .hero-ticker-item { font-size: 0.82rem; }
            .hero-ticker-track { gap: 32px; }
        }

        /* ===== Brand filter enhancements ===== */
        .brands { position: relative; }

        .brands-head p {
            position: relative;
            display: inline-block;
            font-weight: 800;
            letter-spacing: 0.04em;
            padding-bottom: 10px;
        }
        .brands-head p::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 46px;
            height: 4px;
            border-radius: 999px;
            background: linear-gradient(90deg, #8B3DF7, #F97316);
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .brand-chip {
            position: relative;
            display: inline-flex;
            align-items: center;
            padding: 10px 22px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
            color: #4B2E83;
            background: #ffffff;
            border: 1px solid rgba(139, 61, 247, 0.18);
            box-shadow: 0 2px 8px rgba(58, 20, 90, 0.06);
            text-decoration: none;
            overflow: hidden;
            isolation: isolate;
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease, color 0.25s ease, border-color 0.25s ease;
        }

        /* halo lembut yang muncul saat hover */
        .brand-chip::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(135deg, #8B3DF7, #F97316);
            opacity: 0;
            z-index: -2;
            transition: opacity 0.3s ease;
        }

        /* efek shine yang menyapu saat hover */
        .brand-chip::after {
            content: "";
            position: absolute;
            top: 0;
            left: -60%;
            width: 40%;
            height: 100%;
            background: linear-gradient(115deg, transparent, rgba(255,255,255,0.55), transparent);
            transform: skewX(-20deg);
            z-index: -1;
            transition: left 0.6s ease;
        }

        .brand-chip:hover {
            color: #6D28D9;
            border-color: rgba(139, 61, 247, 0.4);
            transform: translateY(-3px);
            box-shadow: 0 10px 22px rgba(139, 61, 247, 0.22);
        }
        .brand-chip:hover::after { left: 130%; }

        .brand-chip:active { transform: translateY(-1px) scale(0.97); }

        .brand-chip.active {
            color: #ffffff;
            border-color: transparent;
            background: linear-gradient(135deg, #8B3DF7 0%, #C13FE0 50%, #F97316 100%);
            background-size: 200% 200%;
            animation: brand-glow 3.5s ease infinite;
            box-shadow: 0 8px 22px rgba(139, 61, 247, 0.35);
        }
        .brand-chip.active::before { opacity: 0; }
        .brand-chip.active::after {
            background: linear-gradient(115deg, transparent, rgba(255,255,255,0.45), transparent);
        }

        @keyframes brand-glow {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* transisi halus saat daftar produk berganti karena filter brand */
        #product-results { transition: opacity 0.25s ease; }

        @media (max-width: 576px) {
            .brand-row { gap: 8px; }
            .brand-chip { padding: 8px 16px; font-size: 0.8rem; }
        }

        /* ===== Category strip enhancements ===== */
        .cat-row {
            display: flex;
            align-items: flex-start;
            gap: 26px;
            flex-wrap: wrap;
        }

        .cat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            width: 78px;
            text-align: center;
        }

        .cat-swatch {
            position: relative;
            width: 62px;
            height: 62px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #F3ECFE, #FBEAF6);
            box-shadow: inset 0 0 0 1px rgba(139, 61, 247, 0.1);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                        background 0.3s ease,
                        box-shadow 0.3s ease;
        }
        .cat-swatch svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: #8B3DF7;
            stroke-width: 1.7;
            transition: stroke 0.3s ease, transform 0.3s ease;
        }

        .cat-item span {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6B6478;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.25s ease;
        }
        .cat-item span::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: 0;
            width: 0;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, #8B3DF7, #F97316);
            transform: translateX(-50%);
            transition: width 0.25s ease;
        }

        .cat-item:hover .cat-swatch {
            transform: translateY(-4px) scale(1.06);
            box-shadow: 0 10px 20px rgba(139, 61, 247, 0.22);
        }
        .cat-item:hover .cat-swatch svg { stroke: #6D28D9; }
        .cat-item:hover span { color: #4B2E83; }

        .cat-item.active .cat-swatch {
            background: linear-gradient(135deg, #8B3DF7 0%, #C13FE0 55%, #F97316 100%);
            background-size: 180% 180%;
            animation: brand-glow 4s ease infinite;
            box-shadow: 0 10px 22px rgba(139, 61, 247, 0.35);
        }
        .cat-item.active .cat-swatch svg { stroke: #ffffff; }
        .cat-item.active .cat-swatch::after {
            content: "";
            position: absolute;
            bottom: -1px;
            right: -1px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #F97316;
            border: 2.5px solid #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .cat-item.active span { color: #6D28D9; font-weight: 800; }
        .cat-item.active span::after { width: 100%; }

        @media (max-width: 576px) {
            .cat-row { gap: 16px; }
            .cat-item { width: 64px; }
            .cat-swatch { width: 52px; height: 52px; }
            .cat-swatch svg { width: 20px; height: 20px; }
            .cat-item span { font-size: 0.72rem; }
        }

        /* ===== Product card enhancements ===== */
        .card {
            background: #ffffff;
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid rgba(139, 61, 247, 0.08);
            box-shadow: 0 4px 14px rgba(58, 20, 90, 0.06);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1),
                        box-shadow 0.35s ease,
                        border-color 0.35s ease;
        }
        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(58, 20, 90, 0.18);
            border-color: rgba(139, 61, 247, 0.22);
        }

        .card-media {
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 50% 40%, #fdf3ec 0%, #f7f0fb 60%, #f3edfa 100%);
        }
        .card-media img {
            transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .card:hover .card-media img {
            transform: scale(1.08) rotate(-1deg);
        }

        .card-body { padding: 16px 18px 18px; }

        .card-brand {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            background: linear-gradient(90deg, #8B3DF7, #F97316);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 4px;
        }

        .card-name {
            font-weight: 700;
            color: #241938;
            line-height: 1.35;
        }

        .price-row { margin-top: 8px; }
        .price-now {
            font-weight: 800;
            font-size: 1.05rem;
            background: linear-gradient(90deg, #8B3DF7, #C13FE0);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* wishlist heart, top-right */
        .wishlist {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 2;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.92);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(58,20,90,0.14);
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .wishlist svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: #C13FE0;
            stroke-width: 1.8;
            transition: fill 0.2s ease, stroke 0.2s ease, transform 0.25s ease;
        }
        .wishlist:hover { transform: scale(1.08); }
        .wishlist.active {
            background: linear-gradient(135deg, #F97316, #C13FE0);
        }
        .wishlist.active svg {
            fill: #ffffff;
            stroke: #ffffff;
            animation: heart-pop 0.35s ease;
        }
        @keyframes heart-pop {
            0%   { transform: scale(0.7); }
            60%  { transform: scale(1.25); }
            100% { transform: scale(1); }
        }

        @media (max-width: 576px) {
            .card { border-radius: 18px; }
            .card-body { padding: 12px 14px 14px; }
        }
    </style>

    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <div class="hero-badges-row">
                    <div class="hero-eyebrow">New Arrival</div>
                </div>
                <h1 class="hero-title"><span class="hero-title-gradient">Step Into<br>Greatness</span></h1>
                <p class="hero-desc">Temukan koleksi sepatu lari dan kasual terbaru dengan kenyamanan maksimal untuk aktivitasmu sepanjang hari.</p>
                <div class="hero-ctas">
                    <a href="#koleksi" class="btn btn-accent">Belanja Sekarang</a>
                    <a href="#brands" class="btn btn-outline">Lihat Brand</a>
                </div>
                <div class="hero-trust-row">
                    <div class="hero-trust-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
                        Original 100%
                    </div>
                    <div class="hero-trust-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>
                        Retur mudah 7 hari
                    </div>
                </div>
            </div>
            <div class="hero-display">
                <div class="hero-stage">
                    <div class="hero-slideshow" id="heroSlideshow">
                        <?php if ($use_products): ?>
                            <?php foreach ($hero_products as $i => $hp): ?>
                                <a href="product_detail.php?id=<?= (int) $hp['id'] ?>" class="hero-slide <?= $i === 0 ? 'active' : '' ?>" aria-label="<?= sanitize($hp['name']) ?>"<?= $i === 0 ? '' : ' tabindex="-1"' ?>>
                                    <img src="uploads/products/<?= sanitize($hp['image']) ?>" alt="<?= sanitize($hp['name']) ?>" class="hero-photo">
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($hero_slides as $i => $slide): ?>
                                <div class="hero-slide is-stock <?= $i === 0 ? 'active' : '' ?>">
                                    <img src="<?= $slide['src'] ?>" alt="<?= sanitize($slide['alt']) ?>" class="hero-photo">
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($use_products): ?>
                        <?php foreach ($hero_products as $i => $hp): ?>
                            <a href="product_detail.php?id=<?= (int) $hp['id'] ?>" class="hero-floating-card <?= $i === 0 ? 'active' : '' ?>"<?= $i === 0 ? '' : ' tabindex="-1"' ?>>
                                <div class="hero-floating-card-text">
                                    <strong><?= sanitize($hp['name']) ?></strong>
                                    <span>Rp <?= number_format($hp['price'], 0, ',', '.') ?></span>
                                </div>
                                <div class="hero-floating-card-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <a href="#koleksi" class="hero-floating-card active">
                            <div class="hero-floating-card-text">
                                <strong>Koleksi Terbaru</strong>
                                <span>Lihat semua produk</span>
                            </div>
                            <div class="hero-floating-card-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?php if ($hero_count > 1): ?>
                        <div class="hero-dots" aria-label="Pilih slide">
                            <?php for ($i = 0; $i < $hero_count; $i++): ?>
                                <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="hero-ticker">
            <div class="hero-ticker-track">
                <?php
                $ticker_items = [
                    'Gratis Ongkir Se-Indonesia',
                    'Pembayaran Aman',
                    'Rating 4.8/5 dari 2.000+ Pembeli',
                ];
                // diulang beberapa kali supaya putarannya tidak putus di layar lebar
                for ($r = 0; $r < 8; $r++):
                    foreach ($ticker_items as $ti):
                ?>
                    <span class="hero-ticker-item">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.6 7.1.6-5.4 4.7 1.6 7-6.2-3.7L5.8 21l1.6-7L2 9.2l7.1-.6L12 2z"/></svg>
                        <?= sanitize($ti) ?>
                    </span>
                <?php
                    endforeach;
                endfor;
                ?>
            </div>
        </div>
    </section>

    <?php if ($hero_count > 1): ?>
    <script>
    (function () {
        var root   = document.getElementById('heroSlideshow');
        if (!root) return;
        var slides = root.querySelectorAll('.hero-slide');
        var cards  = document.querySelectorAll('.hero-stage .hero-floating-card');
        var dots   = document.querySelectorAll('.hero-dots button');
        var syncCards = cards.length === slides.length;   // kartu info ikut berganti kalau jumlahnya sama
        var stage  = root.parentNode;
        var current = 0, timer = null;

        function show(next) {
            if (next === current) return;
            var prev = slides[current];
            prev.classList.remove('active');
            prev.classList.add('prev');
            prev.setAttribute('tabindex', '-1');
            slides[next].classList.add('active');
            slides[next].removeAttribute('tabindex');
            setTimeout(function () { prev.classList.remove('prev'); }, 750);

            if (syncCards) {
                cards[current].classList.remove('active');
                cards[current].setAttribute('tabindex', '-1');
                cards[next].classList.add('active');
                cards[next].removeAttribute('tabindex');
            }
            if (dots[current]) dots[current].classList.remove('active');
            if (dots[next]) dots[next].classList.add('active');
            current = next;
        }

        function stop()  { if (timer) { clearInterval(timer); timer = null; } }
        function start() {
            stop();
            timer = setInterval(function () { show((current + 1) % slides.length); }, 4200);
        }

        dots.forEach(function (d) {
            d.addEventListener('click', function () {
                show(parseInt(d.getAttribute('data-slide'), 10));
                start();
            });
        });
        stage.addEventListener('mouseenter', stop);
        stage.addEventListener('mouseleave', start);
        start();
    })();
    </script>
    <?php endif; ?>

    <section class="cat-strip">
        <div class="wrap">
            <div class="cat-card">
                <div class="cat-row">
                    <a href="index.php" class="cat-item <?= $category === 0 ? 'active' : '' ?>">
                        <div class="cat-swatch">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                                <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                                <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                                <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                            </svg>
                        </div>
                        <span>Semua</span>
                    </a>
                    <?php if ($categories && $categories->num_rows > 0): ?>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <?php $isActiveCat = $category === (int) $cat['id']; ?>
                            <a href="index.php?cat=<?= $cat['id'] ?>" class="cat-item <?= $isActiveCat ? 'active' : '' ?>">
                                <div class="cat-swatch">
                                    <svg viewBox="0 0 24 24"><?= category_icon_svg($cat['category_name']) ?></svg>
                                </div>
                                <span><?= sanitize($cat['category_name']) ?></span>
                            </a>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="koleksi" class="section">
        <div class="wrap">

            <div class="brands" id="brands">
                <div class="brands-head"><p>Pilih Berdasarkan Brand</p></div>
                <div class="brand-row">
                    <a href="index.php" class="brand-chip <?= empty($brand) ? 'active' : '' ?>"><span class="brand-initial all"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="6" height="6" rx="1.2"/><rect x="14" y="4" width="6" height="6" rx="1.2"/><rect x="4" y="14" width="6" height="6" rx="1.2"/><rect x="14" y="14" width="6" height="6" rx="1.2"/></svg></span>Semua</a>
                    <?php foreach ($brand_list as $b): ?>
                        <a href="index.php?brand=<?= urlencode($b['brand']) ?>" class="brand-chip <?= ($brand === $b['brand']) ? 'active' : '' ?>">
                            <span class="brand-initial"><?= sanitize(mb_strtoupper(mb_substr($b['brand'], 0, 1))) ?></span><?= sanitize($b['brand']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="product-results">
                <div class="section-head">
                    <h2><?= !empty($search) ? 'Hasil pencarian: "' . sanitize($search) . '"' : 'Koleksi Terbaru' ?></h2>
                </div>

                <div class="grid">
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($p = $products->fetch_assoc()): ?>
                            <div class="card">
                                <button class="wishlist <?= in_array((int) $p['id'], $wishlist_ids) ? 'active' : '' ?>" type="button" data-id="<?= $p['id'] ?>">
                                    <svg viewBox="0 0 24 24"><path d="M20.8 4.6c-1.7-1.5-4.4-1.4-6 .2L12 7.6l-2.8-2.8c-1.6-1.6-4.3-1.7-6-.2-1.9 1.7-2 4.6-.2 6.4l8.3 8.3a1 1 0 0 0 1.4 0l8.3-8.3c1.8-1.8 1.7-4.7-.2-6.4Z"/></svg>
                                </button>
                                <?php if (!empty($_SESSION['logged_in'])): ?>
                                    <a href="chat.php?product_id=<?= (int) $p['id'] ?>" class="chat-quick-btn" title="Chat Penjual" onclick="event.stopPropagation();">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                    </a>
                                <?php endif; ?>
                                <a href="product_detail.php?id=<?= $p['id'] ?>">
                                    <div class="card-media">
                                        <?php if (!empty($p['image']) && file_exists('uploads/products/' . $p['image'])): ?>
                                            <img src="uploads/products/<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($p['brand'])): ?>
                                            <div class="card-brand"><?= sanitize(strtoupper($p['brand'])) ?></div>
                                        <?php endif; ?>
                                        <h3 class="card-name"><?= sanitize($p['name']) ?></h3>
                                        <div class="price-row">
                                            <span class="price-now">Rp <?= number_format($p['price'], 0, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>Produk tidak ditemukan.</h3>
                            <p>Coba kata kunci, kategori, atau brand lain.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>

    <section class="section" id="promo-banner">
        <div class="wrap">
            <div class="promo">
                <div class="promo-icon">
                    <svg viewBox="0 0 24 24"><path d="M2 7h11v9H2z"/><path d="M13 10h4l4 3v3h-8z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg>
                </div>
                <div>
                    <h3>Gratis Ongkir<br>Se-Indonesia</h3>
                    <p>Belanja tanpa mikir ongkos kirim. Berlaku untuk semua pesanan tanpa minimum belanja, khusus hari ini.</p>
                </div>
                <a href="index.php#koleksi" class="btn btn-accent">Belanja Sekarang</a>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    var resultsEl = document.getElementById('product-results');
    if (!resultsEl) return;

    function loadProducts(url, clickedEl) {
        resultsEl.style.opacity = '0.5';

        var queryString = url.indexOf('?') !== -1 ? url.split('?')[1] : '';
        fetch('ajax_products.php' + (queryString ? '?' + queryString : ''))
            .then(function (res) {
                if (!res.ok) throw new Error('Gagal memuat produk');
                return res.text();
            })
            .then(function (html) {
                resultsEl.innerHTML = html;
                resultsEl.style.opacity = '1';
                window.history.pushState({}, '', url);
                if (clickedEl) updateActiveStates(clickedEl);
                bindWithinResults();
            })
            .catch(function () {
                // Fallback: kalau AJAX gagal, tetap jalan normal (reload)
                window.location.href = url;
            });
    }

    function updateActiveStates(clickedEl) {
        var isCategory = clickedEl.classList.contains('cat-item');
        var group = isCategory ? '.cat-item' : '.brand-chip';
        document.querySelectorAll(group).forEach(function (el) {
            el.classList.remove('active');
        });
        clickedEl.classList.add('active');
    }

    function bindFilterLinks() {
        document.querySelectorAll('.cat-item, .brand-chip').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                loadProducts(this.getAttribute('href'), this);
            });
        });
    }

    // Toggle favorite/wishlist via AJAX
    function bindWishlistButtons() {
        document.querySelectorAll('.wishlist').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var btnEl = this;
                var productId = btnEl.getAttribute('data-id');
                if (btnEl.dataset.loading === '1') return;
                btnEl.dataset.loading = '1';

                fetch('ajax_wishlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + encodeURIComponent(productId)
                })
                    .then(function (res) {
                        if (res.status === 401) {
                            window.location.href = 'login.php';
                            return null;
                        }
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data) return;
                        if (data.success) {
                            btnEl.classList.toggle('active', data.status === 'added');
                        }
                    })
                    .catch(function () {
                        // gagal diam-diam, biar tidak ganggu UX; bisa ditambah toast error kalau perlu
                    })
                    .finally(function () {
                        btnEl.dataset.loading = '0';
                    });
            });
        });
    }

    // Placeholder jika nanti ada elemen interaktif baru di dalam hasil (mis. wishlist)
    function bindWithinResults() {
        bindWishlistButtons();
    }

    bindFilterLinks();
    bindWishlistButtons();

    // Dukung tombol back/forward browser
    window.addEventListener('popstate', function () {
        location.reload();
    });
})();
</script>

<?php require_once 'footer.php'; ?>