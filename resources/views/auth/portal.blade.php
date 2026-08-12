@php use App\Support\BrandLogo; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Portal</title>
    <link rel="icon" type="image/jpeg" href="{{ BrandLogo::assetUrl() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary: #15803d;
            --primary-dark: #166534;
            --primary-light: #22c55e;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            color: var(--text);
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 24px;
        }
        .brand { display: flex; align-items: center; gap: 13px; }
        .brand .logo {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .brand .logo img { width: 100%; height: 100%; object-fit: cover; }
        .brand .title { font-size: 15px; font-weight: 800; color: var(--text); line-height: 1.2; }
        .brand .subtitle { font-size: 12px; color: var(--muted); }
        .user-box { display: flex; align-items: center; gap: 12px; }
        .user-box .meta { text-align: right; line-height: 1.25; }
        .user-box .meta .name { font-size: 13px; font-weight: 700; color: var(--text); }
        .user-box .meta .role { font-size: 11px; color: var(--muted); text-transform: capitalize; }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            text-transform: uppercase;
        }
        .logout-btn {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 9px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s ease;
        }
        .logout-btn:hover { background: #fee2e2; }
        .banner {
            background: linear-gradient(120deg, var(--primary-dark), var(--primary) 60%, var(--primary-light));
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .banner::after {
            content: "";
            position: absolute;
            right: -80px;
            top: -80px;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .banner::before {
            content: "";
            position: absolute;
            right: 90px;
            bottom: -120px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .banner-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 38px 24px;
            position: relative;
            z-index: 1;
        }
        .banner .hello { font-size: 13px; font-weight: 600; opacity: 0.9; }
        .banner h1 { font-size: 27px; font-weight: 800; margin-top: 6px; letter-spacing: -0.4px; }
        .banner p { margin-top: 8px; font-size: 14.5px; opacity: 0.92; max-width: 560px; }
        .content {
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            padding: 30px 24px 50px;
            flex: 1;
        }
        .section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .section-head h2 { font-size: 18px; font-weight: 800; color: var(--text); }
        .section-head .line { flex: 1; height: 1px; background: var(--border); }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 20px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            position: relative;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 18px;
            bottom: 18px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--accent, var(--primary));
            opacity: 0;
            transition: opacity 0.18s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.1);
            border-color: transparent;
        }
        .card:hover::before { opacity: 1; }
        .card-top { display: flex; align-items: center; justify-content: space-between; }
        .card .icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 26px;
        }
        .card h3 { font-size: 17px; font-weight: 700; color: var(--text); }
        .card p { font-size: 13.5px; color: var(--muted); line-height: 1.55; }
        .card .go {
            margin-top: auto;
            font-size: 13px;
            font-weight: 700;
            color: var(--accent, var(--primary));
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.18s ease;
        }
        .card:hover .go { gap: 10px; }
        .alert {
            margin-bottom: 14px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-size: 13px;
            padding: 11px 13px;
            border-radius: 10px;
        }
        footer {
            background: var(--primary-dark);
            color: #dcfce7;
            text-align: center;
            padding: 20px;
            font-size: 12.5px;
        }
        @media (max-width: 560px) {
            .banner h1 { font-size: 22px; }
            .user-box .meta { display: none; }
            .brand .subtitle { display: none; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <div class="logo"><img src="{{ BrandLogo::assetUrl() }}" alt="Logo {{ config('app.name') }}"></div>
                <div>
                    <div class="title">{{ config('app.name') }}</div>
                    <div class="subtitle">Portal Layanan Digital</div>
                </div>
            </div>
            <div class="user-box">
                <div class="avatar">{{ \Illuminate\Support\Str::substr($userName, 0, 1) }}</div>
                <div class="meta">
                    <div class="name">{{ $userName }}</div>
                    <div class="role">Pengguna</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <section class="banner">
        <div class="banner-inner">
            <div class="hello">Assalamu'alaikum Warahmatullahi Wabarakatuh</div>
            <h1>Selamat Datang, {{ $userName }}</h1>
            <p>Akses layanan digital {{ config('app.name') }} melalui portal ini.</p>
        </div>
    </section>

    <main class="content">
        @if(session('portal_info'))
            <div class="alert">{{ session('portal_info') }}</div>
        @endif

        <div class="section-head">
            <h2>Layanan Aplikasi</h2>
            <span class="line"></span>
        </div>

        <div class="grid">
            <a class="card" href="{{ route('portal.sikeu') }}" style="--accent:#0ea5e9;">
                <div class="card-top">
                    <div class="icon" style="background:#0ea5e9;"><i class="fa-solid fa-wallet"></i></div>
                </div>
                <h3>SIKEU</h3>
                <p>Sistem Informasi Keuangan.</p>
                <span class="go">Buka aplikasi &rarr;</span>
            </a>
        </div>
    </main>

    <footer>
        &copy; {{ date('Y') }} {{ config('app.name') }} &middot; Semua hak dilindungi
    </footer>
</body>
</html>
