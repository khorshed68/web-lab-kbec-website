<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
startSession();
$__isLoggedIn   = isLoggedIn();
$__memberName   = $_SESSION['member_name']   ?? '';
$__memberCode   = $_SESSION['member_code']   ?? '';
$__memberRole   = $_SESSION['member_role']   ?? 'member';
$__memberAvatar = $_SESSION['member_avatar'] ?? null;
$__csrf         = csrfToken();

// ── Load dynamic DB content ────────────────────────────────
try {
    $__db   = getDB();
    $__sRows= $__db->query("SELECT setting_key,setting_value FROM site_settings")->fetchAll();
    $__S    = [];
    foreach ($__sRows as $r) $__S[$r['setting_key']] = $r['setting_value'];
    $S = fn(string $k, string $d='') => htmlspecialchars($__S[$k] ?? $d, ENT_QUOTES);

    $__announcements = $__db->query("SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
    $__team          = $__db->query("SELECT * FROM team_members WHERE is_active=1 ORDER BY position_order ASC, name ASC")->fetchAll();
    $__sponsors      = $__db->query("SELECT * FROM sponsors WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
    $__gallery       = $__db->query("SELECT * FROM gallery ORDER BY sort_order ASC, created_at DESC LIMIT 24")->fetchAll();
    $__opportunities = $__db->query("SELECT * FROM opportunities WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
} catch (Throwable $e) {
    $__S=[]; $S=fn($k,$d='')=>htmlspecialchars($d,ENT_QUOTES);
    $__announcements=$__team=$__sponsors=$__gallery=$__opportunities=[];
}

$sponsorsList = array_filter($__sponsors, fn($s) => $s['category'] !== 'Partner');
$partnersList = array_filter($__sponsors, fn($s) => $s['category'] === 'Partner');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="<?= htmlspecialchars($__csrf, ENT_QUOTES) ?>"/>
  <title>BEC — Business & Entrepreneurship Club</title>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;900&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      /* ─── Modern Blue Palette (Default) ─── */
      --bg: #F8F9FA;
      --bg-secondary: #FFFFFF;
      --text: #1A1A1A;
      --text-muted: rgba(26, 26, 26, 0.6);
      --accent: #0066CC;
      --accent-light: #0080FF;
      --accent-dim: rgba(0, 102, 204, 0.12);
      
      /* ─── Legacy Color Palette (Optional Alt) ─── */
      --gold: #C9A84C;
      --gold-light: #E8C97A;
      --gold-dim: rgba(201,168,76,0.15);
      --dark: #F8F9FA;
      --dark-2: #FFFFFF;
      --surface: rgba(0, 102, 204, 0.04);
      
      /* ─── Layout ─── */
      --nav-h: 76px;
    }

    html { scroll-behavior: smooth; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Outfit', sans-serif;
      overflow-x: hidden;
    }

    /* ── PRINT FRIENDLY ── */
    @media print {
      * { color: #000 !important; background: white !important; }
      body { background: white; color: #000; }
      .no-print { display: none !important; }
      #loader { display: none !important; }
      nav { display: none !important; }
      .hamburger { display: none !important; }
      .mobile-menu { display: none !important; }
      .scroll-hint { display: none !important; }
      .marquee-strip { background: white; border: 1px solid #333; color: #000; }
      .marquee-item { color: #000; }
      .marquee-dot { background: #333; }
      section { page-break-inside: avoid; }
      a { color: #0066CC; text-decoration: underline; }
      button, .btn-primary, .btn-ghost { border-color: #000 !important; color: #000 !important; background: white !important; }
    }

    /* ── LOADER ── */
    #loader {
      position: fixed; inset: 0; z-index: 9999;
      background: var(--bg);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 20px;
      transition: opacity 0.6s ease, visibility 0.6s ease;
    }
    #loader.hidden { opacity: 0; visibility: hidden; }
    .loader-ring {
      width: 64px; height: 64px;
      border: 2px solid var(--accent-dim);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    .loader-text {
      font-family: 'Cinzel', serif;
      font-size: 14px;
      letter-spacing: 0.46em;
      color: var(--accent-light);
      text-transform: uppercase;
      font-weight: 700;
      text-shadow: 0 0 10px rgba(0,102,204,0.42), 0 0 22px rgba(0,102,204,0.22);
      background: linear-gradient(90deg, #0066CC 0%, #0080FF 50%, #0066CC 100%);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      background-size: 200% auto;
      animation: loader-shine 2.6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes loader-shine {
      0% { background-position: 0% 50%; }
      100% { background-position: 200% 50%; }
    }

    /* ── NAVBAR ── */
    nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      height: var(--nav-h);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 48px;
      transition: background 0.4s ease, backdrop-filter 0.4s ease;
      background: var(--bg-secondary);
    }
    nav.scrolled {
      background: rgba(248, 249, 250, 0.95);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(0, 102, 204, 0.12);
    }
    .nav-logo {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none;
    }
    .nav-logo-icon {
      width: 42px; height: 42px;
      border: 1.5px solid var(--accent);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
      background: white;
    }
    .nav-logo-icon img {
      width: 100%; height: 100%;
      object-fit: cover;
      display: block;
    }
    .nav-logo-text {
      font-family: 'Cinzel', serif;
      font-size: 13px;
      color: var(--text);
      letter-spacing: 0.1em;
      line-height: 1.3;
    }
    .nav-logo-text span { color: var(--accent); display: block; font-size: 10px; letter-spacing: 0.25em; }
    .nav-links {
      display: flex; align-items: center; gap: 36px;
      list-style: none;
    }
    .nav-links a {
      text-decoration: none; color: var(--text-muted);
      font-size: 12px; font-weight: 500;
      letter-spacing: 0.2em; text-transform: uppercase;
      transition: color 0.3s;
      position: relative;
    }
    .nav-links a::after {
      content: ''; position: absolute; bottom: -4px; left: 0; right: 0;
      height: 1px; background: var(--accent);
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.3s ease;
    }
    .nav-links a:hover { color: var(--accent); }
    .nav-links a:hover::after { transform: scaleX(1); }
    .nav-cta {
      padding: 10px 24px;
      border: 1px solid var(--accent);
      color: var(--accent) !important;
      border-radius: 2px;
      font-weight: 500 !important;
      transition: background 0.3s, color 0.3s !important;
      white-space: nowrap;
    }
    .nav-cta::after { display: none !important; }
    .nav-cta:hover { background: var(--accent) !important; color: white !important; }
    .nav-cta-admin {
      border-color: #c9a84c;
      color: #c9a84c !important;
    }
    .nav-cta-admin:hover {
      background: #c9a84c !important;
      color: #060810 !important;
    }
    .nav-cta-announcements {
      border-color: #27ae60;
      color: #27ae60 !important;
    }
    .nav-cta-announcements:hover {
      background: #27ae60 !important;
      color: #fff !important;
    }

    /* Mobile hamburger */
    .hamburger {
      display: none; flex-direction: column; gap: 5px;
      cursor: pointer; padding: 4px;
    }
    .hamburger span {
      display: block; width: 24px; height: 1.5px;
      background: var(--accent);
      transition: transform 0.3s, opacity 0.3s;
    }
    .hamburger.open span:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

    /* Mobile menu */
    .mobile-menu {
      display: none; position: fixed;
      top: var(--nav-h); left: 0; right: 0;
      background: rgba(248, 249, 250, 0.97);
      backdrop-filter: blur(20px);
      padding: 32px 48px;
      flex-direction: column; gap: 24px;
      border-bottom: 1px solid var(--accent-dim);
      z-index: 99;
      transform: translateY(-20px); opacity: 0;
      transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .mobile-menu.open { transform: translateY(0); opacity: 1; }
    .mobile-menu a {
      text-decoration: none; color: var(--text-muted);
      font-size: 13px; letter-spacing: 0.2em;
      text-transform: uppercase;
      padding: 8px 0; border-bottom: 1px solid var(--accent-dim);
      transition: color 0.3s;
    }
    .mobile-menu a:hover { color: var(--accent); }

    /* ── HERO ── */
    #hero {
      position: relative;
      min-height: 100vh;
      display: flex; flex-direction: column;
      justify-content: flex-end;
      padding: 0 48px 80px;
      overflow: hidden;
      background: linear-gradient(135deg, #F8F9FA 0%, #E8EFFE 100%);
    }

    /* Layered background */
    .hero-bg {
      position: absolute; inset: 0; z-index: 0;
    }
    .hero-bg-img {
      position: absolute; inset: 0;
      background:
        url('https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1600&q=80') center/cover no-repeat;
      opacity: 0.65;
    }
    .hero-bg-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(
        135deg,
        rgba(248, 249, 250, 0.35) 0%,
        rgba(248, 249, 250, 0.25) 50%,
        rgba(232, 239, 254, 0.15) 100%
      );
    }
    .hero-bg-overlay2 {
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(248, 249, 250, 0.3) 0%, transparent 60%);
    }

    /* Grain texture */
    .grain {
      position: absolute; inset: 0; z-index: 1; opacity: 0.04;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
      background-size: 180px;
      pointer-events: none;
    }

    /* Accent geometric lines */
    .hero-lines {
      position: absolute; inset: 0; z-index: 1;
      pointer-events: none; overflow: hidden;
    }
    .hero-lines svg { width: 100%; height: 100%; }

    /* Floating orb */
    .hero-orb {
      position: absolute; right: 8%; top: 20%;
      width: 420px; height: 420px;
      border-radius: 50%;
      background: radial-gradient(circle at 40% 40%, rgba(0, 102, 204, 0.15) 0%, transparent 70%);
      animation: orb-pulse 6s ease-in-out infinite;
      z-index: 1;
    }
    @keyframes orb-pulse {
      0%, 100% { transform: scale(1) translateY(0); opacity: 0.7; }
      50% { transform: scale(1.08) translateY(-20px); opacity: 1; }
    }

    /* Hero content */
    .hero-content {
      position: relative; z-index: 2;
      max-width: 820px;
    }

    .hero-badge {
      display: inline-flex; align-items: center; gap: 10px;
      margin-bottom: 28px;
      opacity: 0; transform: translateY(20px);
      animation: fadeUp 0.8s ease 0.4s forwards;
    }
    .hero-badge-line {
      width: 40px; height: 1px; background: var(--accent);
    }
    .hero-badge-text {
      font-size: 11px; font-weight: 500; letter-spacing: 0.35em;
      text-transform: uppercase; color: var(--accent);
    }

    .hero-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(50px, 7.5vw, 102px);
      font-weight: 900;
      line-height: 1.0;
      letter-spacing: -0.01em;
      margin-bottom: 24px;
      opacity: 0; transform: translateY(30px);
      animation: fadeUp 0.9s ease 0.6s forwards;
      color: var(--text);
    }
    .hero-title .line-gold { color: var(--accent); }
    .hero-title .line-dim { color: var(--text-muted); font-size: 0.55em; display: block; letter-spacing: 0.15em; font-weight: 700; }

    .hero-desc {
      font-size: clamp(14px, 1.5vw, 17px);
      font-weight: 700;
      line-height: 1.75;
      color: rgba(26, 26, 26, 0.92);
      max-width: 520px;
      margin-bottom: 48px;
      opacity: 0; transform: translateY(20px);
      animation: fadeUp 0.8s ease 0.8s forwards;
      text-shadow: 0 1px 2px rgba(255, 255, 255, 0.45);
    }

    .hero-actions {
      display: flex; align-items: center; gap: 20px;
      flex-wrap: wrap;
      opacity: 0; transform: translateY(20px);
      animation: fadeUp 0.8s ease 1s forwards;
    }

    .btn-primary {
      padding: 16px 40px;
      background: var(--accent);
      color: white;
      font-family: 'Outfit', sans-serif;
      font-size: 12px; font-weight: 600;
      letter-spacing: 0.2em; text-transform: uppercase;
      text-decoration: none;
      border-radius: 2px;
      position: relative; overflow: hidden;
      border: none;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .btn-primary::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.2) 50%, transparent 100%);
      transform: translateX(-100%);
      transition: transform 0.5s ease;
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0, 102, 204, 0.35); }
    .btn-primary:hover::before { transform: translateX(100%); }

    .btn-ghost {
      padding: 16px 40px;
      border: 1px solid rgba(0, 102, 204, 0.4);
      color: var(--accent);
      font-size: 12px; font-weight: 500;
      letter-spacing: 0.2em; text-transform: uppercase;
      text-decoration: none;
      background: transparent;
      border-radius: 2px;
      display: flex; align-items: center; gap: 10px;
      cursor: pointer;
      transition: border-color 0.3s, color 0.3s, transform 0.3s;
    }
    .btn-ghost:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-2px); }
    .btn-ghost svg { transition: transform 0.3s; }
    .btn-ghost:hover svg { transform: translateX(4px); }

    /* Stats strip */
    .hero-stats {
      position: absolute; right: 48px; bottom: 80px;
      z-index: 2;
      display: flex; flex-direction: column; gap: 28px;
      opacity: 0;
      animation: fadeIn 1s ease 1.4s forwards;
    }
    .stat-item { text-align: right; }
    .stat-num {
      font-family: 'Cinzel', serif;
      font-size: 32px; font-weight: 700;
      color: var(--accent);
      line-height: 1;
    }
    .stat-label {
      font-size: 10px; letter-spacing: 0.25em;
      text-transform: uppercase; color: var(--text-muted);
      margin-top: 4px;
    }
    .stat-divider {
      width: 1px; height: 1px;
      background: var(--accent-dim);
      align-self: flex-end;
    }

    /* Scroll indicator */
    .scroll-hint {
      position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%);
      z-index: 2; display: flex; flex-direction: column;
      align-items: center; gap: 10px;
      opacity: 0; animation: fadeIn 1s ease 1.6s forwards;
    }
    .scroll-hint span {
      font-size: 12px;
      letter-spacing: 0.28em;
      line-height: 1;
      text-transform: uppercase;
      color: var(--text-muted);
      font-weight: 600;
    }
    .scroll-line {
      width: 2px; height: 56px;
      background: linear-gradient(to bottom, var(--accent), transparent);
      animation: scrollLine 2s ease-in-out infinite;
    }
    @keyframes scrollLine {
      0% { transform: scaleY(0); transform-origin: top; }
      50% { transform: scaleY(1); transform-origin: top; }
      51% { transform: scaleY(1); transform-origin: bottom; }
      100% { transform: scaleY(0); transform-origin: bottom; }
    }

    /* ── MARQUEE STRIP ── */
    .marquee-strip {
      background: var(--accent);
      padding: 13px 0;
      overflow: hidden;
      position: relative; z-index: 3;
    }
    .marquee-track {
      display: flex; gap: 0;
      animation: marquee 22s linear infinite;
      width: max-content;
    }
    .marquee-item {
      display: flex; align-items: center; gap: 16px;
      padding: 0 32px; white-space: nowrap;
      font-size: 11px; font-weight: 600;
      letter-spacing: 0.25em; text-transform: uppercase;
      color: white;
    }
    .marquee-dot { width: 4px; height: 4px; border-radius: 50%; background: rgba(255, 255, 255, 0.4); opacity: 0.4; }
    @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }

    /* ── SPONSORS ── */
    #sponsors {
      padding: 110px 48px 110px;
      background: var(--bg);
      border-top: 1px solid rgba(0, 102, 204, 0.06);
      border-bottom: 1px solid rgba(0, 102, 204, 0.06);
    }
    .sponsors-wrap {
      max-width: 1180px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 32px;
      align-items: start;
    }
    .sponsors-head {
      max-width: 640px;
      margin: 0 auto 6px;
      text-align: left;
    }
    .sponsors-eyebrow { color: var(--accent); text-transform: uppercase; letter-spacing: 0.32em; font-size: 11px; font-weight:600; margin-bottom: 8px; display:inline-block; }
    .sponsors-title { font-family: 'Cinzel', serif; font-size: clamp(36px, 5.2vw, 56px); margin-bottom: 16px; color: var(--text); }
    .sponsors-desc { color: var(--text-muted); max-width: 640px; font-size: 15px; line-height: 1.8; }

    .sponsors-grid {
      margin-top: 18px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 28px;
      max-width: 1080px;
      margin-left: auto; margin-right: auto;
    }
    /* Partners tiles */
    .partner-tile {
      border: 1px solid rgba(0, 102, 204, 0.10);
      border-radius: 14px;
      padding: 28px 18px;
      background: #ffffff;
      display: flex; flex-direction: column; align-items: center; gap: 18px;
      min-height: 140px;
      color: rgba(26, 26, 26, 0.6);
      transition: transform 0.22s ease, border-color 0.18s ease, background 0.22s ease, box-shadow 0.22s ease;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }
    .partner-tile:hover {
      transform: translateY(-6px);
      border-color: rgba(0, 102, 204, 0.28);
      background: rgba(0, 102, 204, 0.08);
      color: var(--accent);
    }

    /* ── Colorful Partner Tiles ── */
    #partners .partner-tile {
      border-top: 4px solid transparent;
      position: relative;
      overflow: hidden;
    }
    #partners .partner-tile::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 100%;
      opacity: 0;
      transition: opacity 0.3s ease;
      pointer-events: none;
      z-index: 0;
    }
    #partners .partner-tile:hover::before { opacity: 1; }
    #partners .partner-tile > * { position: relative; z-index: 1; }

    /* 1 — BUET BIZ CLUB — Coral/Red */
    #partners .partner-tile:nth-child(1) {
      border-top-color: #ef4444;
      background: linear-gradient(135deg, rgba(239,68,68,0.04), rgba(239,68,68,0.01));
      color: #b91c1c;
    }
    #partners .partner-tile:nth-child(1) .partner-name { color: #dc2626; }
    #partners .partner-tile:nth-child(1)::before { background: radial-gradient(circle at 50% 0%, rgba(239,68,68,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(1):hover { border-color: #ef4444; box-shadow: 0 8px 30px rgba(239,68,68,0.18); transform: translateY(-8px) scale(1.02); }

    /* 2 — DU BIZ SOCIETY — Indigo */
    #partners .partner-tile:nth-child(2) {
      border-top-color: #6366f1;
      background: linear-gradient(135deg, rgba(99,102,241,0.04), rgba(99,102,241,0.01));
      color: #4338ca;
    }
    #partners .partner-tile:nth-child(2) .partner-name { color: #4f46e5; }
    #partners .partner-tile:nth-child(2)::before { background: radial-gradient(circle at 50% 0%, rgba(99,102,241,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(2):hover { border-color: #6366f1; box-shadow: 0 8px 30px rgba(99,102,241,0.18); transform: translateY(-8px) scale(1.02); }

    /* 3 — BRAC BEC — Emerald */
    #partners .partner-tile:nth-child(3) {
      border-top-color: #10b981;
      background: linear-gradient(135deg, rgba(16,185,129,0.04), rgba(16,185,129,0.01));
      color: #047857;
    }
    #partners .partner-tile:nth-child(3) .partner-name { color: #059669; }
    #partners .partner-tile:nth-child(3)::before { background: radial-gradient(circle at 50% 0%, rgba(16,185,129,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(3):hover { border-color: #10b981; box-shadow: 0 8px 30px rgba(16,185,129,0.18); transform: translateY(-8px) scale(1.02); }

    /* 4 — STARTUP BD — Amber */
    #partners .partner-tile:nth-child(4) {
      border-top-color: #f59e0b;
      background: linear-gradient(135deg, rgba(245,158,11,0.04), rgba(245,158,11,0.01));
      color: #b45309;
    }
    #partners .partner-tile:nth-child(4) .partner-name { color: #d97706; }
    #partners .partner-tile:nth-child(4)::before { background: radial-gradient(circle at 50% 0%, rgba(245,158,11,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(4):hover { border-color: #f59e0b; box-shadow: 0 8px 30px rgba(245,158,11,0.18); transform: translateY(-8px) scale(1.02); }

    /* 5 — VENTURE BD — Violet */
    #partners .partner-tile:nth-child(5) {
      border-top-color: #8b5cf6;
      background: linear-gradient(135deg, rgba(139,92,246,0.04), rgba(139,92,246,0.01));
      color: #6d28d9;
    }
    #partners .partner-tile:nth-child(5) .partner-name { color: #7c3aed; }
    #partners .partner-tile:nth-child(5)::before { background: radial-gradient(circle at 50% 0%, rgba(139,92,246,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(5):hover { border-color: #8b5cf6; box-shadow: 0 8px 30px rgba(139,92,246,0.18); transform: translateY(-8px) scale(1.02); }

    /* 6 — TECHHIVE BD — Teal */
    #partners .partner-tile:nth-child(6) {
      border-top-color: #14b8a6;
      background: linear-gradient(135deg, rgba(20,184,166,0.04), rgba(20,184,166,0.01));
      color: #0f766e;
    }
    #partners .partner-tile:nth-child(6) .partner-name { color: #0d9488; }
    #partners .partner-tile:nth-child(6)::before { background: radial-gradient(circle at 50% 0%, rgba(20,184,166,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(6):hover { border-color: #14b8a6; box-shadow: 0 8px 30px rgba(20,184,166,0.18); transform: translateY(-8px) scale(1.02); }

    /* 7 — CFA SOCIETY BD — Rose */
    #partners .partner-tile:nth-child(7) {
      border-top-color: #f43f5e;
      background: linear-gradient(135deg, rgba(244,63,94,0.04), rgba(244,63,94,0.01));
      color: #be123c;
    }
    #partners .partner-tile:nth-child(7) .partner-name { color: #e11d48; }
    #partners .partner-tile:nth-child(7)::before { background: radial-gradient(circle at 50% 0%, rgba(244,63,94,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(7):hover { border-color: #f43f5e; box-shadow: 0 8px 30px rgba(244,63,94,0.18); transform: translateY(-8px) scale(1.02); }

    /* 8 — YOUTHCO BD — Sky Blue */
    #partners .partner-tile:nth-child(8) {
      border-top-color: #0ea5e9;
      background: linear-gradient(135deg, rgba(14,165,233,0.04), rgba(14,165,233,0.01));
      color: #0369a1;
    }
    #partners .partner-tile:nth-child(8) .partner-name { color: #0284c7; }
    #partners .partner-tile:nth-child(8)::before { background: radial-gradient(circle at 50% 0%, rgba(14,165,233,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(8):hover { border-color: #0ea5e9; box-shadow: 0 8px 30px rgba(14,165,233,0.18); transform: translateY(-8px) scale(1.02); }

    /* 9 — IDEAHIVE — Orange */
    #partners .partner-tile:nth-child(9) {
      border-top-color: #f97316;
      background: linear-gradient(135deg, rgba(249,115,22,0.04), rgba(249,115,22,0.01));
      color: #c2410c;
    }
    #partners .partner-tile:nth-child(9) .partner-name { color: #ea580c; }
    #partners .partner-tile:nth-child(9)::before { background: radial-gradient(circle at 50% 0%, rgba(249,115,22,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(9):hover { border-color: #f97316; box-shadow: 0 8px 30px rgba(249,115,22,0.18); transform: translateY(-8px) scale(1.02); }

    /* 10 — KUET CSE CLUB — Fuchsia */
    #partners .partner-tile:nth-child(10) {
      border-top-color: #d946ef;
      background: linear-gradient(135deg, rgba(217,70,239,0.04), rgba(217,70,239,0.01));
      color: #a21caf;
    }
    #partners .partner-tile:nth-child(10) .partner-name { color: #c026d3; }
    #partners .partner-tile:nth-child(10)::before { background: radial-gradient(circle at 50% 0%, rgba(217,70,239,0.12), transparent 70%); }
    #partners .partner-tile:nth-child(10):hover { border-color: #d946ef; box-shadow: 0 8px 30px rgba(217,70,239,0.18); transform: translateY(-8px) scale(1.02); }
    .partner-icon { opacity: 1; width: 96px; height: 96px; display:block; object-fit: contain; }
    .partner-name { font-size: 13px; letter-spacing: 0.12em; text-transform: uppercase; font-weight:700; color: rgba(26, 26, 26, 0.56); }
    .sponsor-card {
      border: 1px solid rgba(0, 102, 204, 0.14);
      border-radius: 12px;
      padding: 40px 20px;
      background: rgba(0, 102, 204, 0.02);
      display: flex; flex-direction: column; align-items: center; gap: 18px;
      transition: transform 0.28s ease, border-color 0.22s ease, box-shadow 0.28s ease;
      color: rgba(26, 26, 26, 0.42);
      text-align: center;
    }
    .sponsor-card svg { width: 40px; height: 40px; opacity: 0.5; color: var(--text); }
    .sponsor-card .sponsor-name { font-size: 12px; letter-spacing: 0.28em; text-transform: uppercase; font-weight:600; color: rgba(26, 26, 26, 0.44); }
    .sponsor-card:hover { transform: translateY(-6px); border-color: rgba(0, 102, 204, 0.28); box-shadow: 0 14px 30px rgba(0, 102, 204, 0.15); }
    .sponsor-card.active { background: rgba(0, 102, 204, 0.08); border-color: rgba(0, 102, 204, 0.34); color: var(--accent); }
    .sponsor-card.active svg { opacity: 1; filter: drop-shadow(0 6px 10px rgba(0, 102, 204, 0.08)); }

    @media (max-width: 1000px) {
      .sponsors-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; }
      #sponsors { padding: 88px 24px; }
      .partner-tile { min-height: 120px; padding: 20px 12px; }
      .partner-icon { width: 72px; height: 72px; }
      .partner-name { font-size: 12px; }
    }
    @media (max-width: 520px) {
      .sponsors-grid { grid-template-columns: 1fr; }
      .sponsors-head { text-align: center; }
      .sponsor-card { padding: 22px 14px; }
    }

    /* ── OUR STORY ── */
    #about {
      position: relative;
      padding: 110px 48px 120px;
      background:
        radial-gradient(circle at 82% 22%, rgba(0, 102, 204, 0.1), transparent 38%),
        radial-gradient(circle at 22% 75%, rgba(0, 102, 204, 0.06), transparent 45%),
        var(--bg);
      overflow: hidden;
    }
    #about::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(0, 102, 204, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 102, 204, 0.05) 1px, transparent 1px);
      background-size: 80px 80px;
      opacity: 0.2;
      pointer-events: none;
    }
    .about-wrap {
      position: relative;
      z-index: 1;
      max-width: 1080px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 360px;
      gap: 64px;
      align-items: start;
    }
    .about-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 0.3em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 22px;
    }
    .about-eyebrow::before {
      content: '';
      width: 42px;
      height: 1px;
      background: var(--accent);
    }
    .about-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(40px, 4.8vw, 64px);
      line-height: 1.06;
      margin-bottom: 20px;
      max-width: 620px;
      color: var(--text);
    }
    .about-divider {
      width: 38px;
      height: 2px;
      background: var(--accent);
      margin-bottom: 24px;
    }
    .about-copy {
      max-width: 600px;
      color: var(--text-muted);
      font-size: 16px;
      line-height: 1.9;
      margin-bottom: 16px;
    }
    .about-grid {
      margin-top: 30px;
      max-width: 720px;
      display: grid;
      grid-template-columns: repeat(2, minmax(210px, 1fr));
      gap: 14px;
    }
    .about-card {
      border: 1px solid rgba(0, 102, 204, 0.22);
      border-radius: 12px;
      padding: 18px 16px 16px;
      background: rgba(0, 102, 204, 0.03);
      transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
    }
    .about-card:hover {
      border-color: rgba(0, 102, 204, 0.5);
      transform: translateY(-3px);
      box-shadow: 0 16px 28px rgba(0, 102, 204, 0.12);
    }
    .about-icon {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--accent-light);
      margin-bottom: 10px;
      background: rgba(0, 102, 204, 0.14);
    }
    .about-card h3 {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--text);
    }
    .about-card p {
      font-size: 14px;
      line-height: 1.6;
      color: var(--text-muted);
    }
    .about-panel {
      position: relative;
      min-height: 540px;
      border-radius: 16px;
      border: 1px solid rgba(0, 102, 204, 0.2);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      background:
        radial-gradient(circle at 28% 22%, rgba(0, 102, 204, 0.15), transparent 48%),
        linear-gradient(165deg, rgba(0, 102, 204, 0.02), rgba(0, 102, 204, 0.005));
      box-shadow: inset 0 1px 0 rgba(0, 102, 204, 0.03), 0 30px 60px rgba(0, 0, 0, 0.08);
    }
    .about-panel img {
      width: 100%;
      max-height: 60%;
      object-fit: cover;
      display: block;
    }
    .about-panel-copy {
      margin-top: auto;
      width: 100%;
      padding: 20px 14px 26px;
      text-align: center;
      color: var(--text);
      background: linear-gradient(to bottom, rgba(248, 249, 250, 0.05), rgba(248, 249, 250, 0.34));
    }
    .about-panel-copy .panel-kicker {
      display: block;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 0.22em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 8px;
    }
    .about-panel-copy h3 {
      font-family: 'Cinzel', serif;
      font-size: clamp(20px, 2vw, 30px);
      line-height: 1.08;
      letter-spacing: 0;
      margin-bottom: 6px;
      max-width: 100%;
      word-break: normal;
      overflow-wrap: anywhere;
      text-wrap: balance;
    }
    .about-panel-copy p {
      font-size: 12px;
      line-height: 1.6;
      color: var(--text-muted);
      max-width: 220px;
      margin: 0 auto;
    }

    /* ═══════════════════════════════════════════════════════
       UPCOMING EVENTS — Calendar + Milestones Panel
    ════════════════════════════════════════════════════════ */
    #events {
      padding: 100px 48px 120px;
      background:
        radial-gradient(circle at 8% 20%, rgba(0,102,204,.05) 0%, transparent 46%),
        radial-gradient(circle at 90% 80%, rgba(0,102,204,.04) 0%, transparent 40%),
        #f5f7fa;
    }
    .events-wrap { max-width: 1220px; margin: 0 auto; }
    .events-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 24px;
      margin-bottom: 40px;
    }
    .events-eyebrow {
      text-transform: uppercase;
      letter-spacing: .22em;
      font-size: 10px;
      font-weight: 600;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .events-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(36px, 4.2vw, 58px);
      line-height: 1.08;
      margin-bottom: 14px;
      color: var(--text);
    }
    .events-subtitle {
      max-width: 520px;
      color: var(--text-muted);
      font-size: 15px;
      line-height: 1.8;
    }
    .events-actions {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 10px;
    }
    .events-count {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid rgba(0, 102, 204, 0.24);
      border-radius: 999px;
      padding: 7px 12px;
      color: rgba(26, 26, 26, 0.78);
      font-size: 11px;
      letter-spacing: 0.04em;
      background: rgba(0, 102, 204, 0.08);
      white-space: nowrap;
    }
    .events-count strong {
      color: var(--accent-light);
      font-family: 'Cinzel', serif;
      font-size: 16px;
      line-height: 1;
    }
    .events-cta {
      text-decoration: none;
      color: var(--accent-light);
      font-size: 11px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      border: 1px solid rgba(0, 102, 204, 0.32);
      border-radius: 999px;
      padding: 8px 14px;
      transition: background 0.25s ease, color 0.25s ease, border-color 0.25s ease;
    }
    .events-cta:hover {
      background: var(--accent);
      color: white;
      border-color: var(--accent);
    }
    .events-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.12fr) minmax(360px, 1fr);
      gap: 20px;
      align-items: start;
    }
    .calendar-panel,
    .deadlines-panel,
    .event-registration-panel {
      border: 1px solid rgba(0, 102, 204, 0.18);
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(12px);
      box-shadow: 0 20px 40px rgba(0, 102, 204, 0.08);
    }
    .calendar-panel {
      padding: 18px;
      overflow: visible;
    }
    .calendar-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 18px;
    }
    .calendar-nav {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      border: 1px solid rgba(0, 102, 204, 0.22);
      background: rgba(0, 102, 204, 0.06);
      color: var(--accent-light);
      font-size: 18px;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
    }
    .calendar-nav:hover {
      transform: translateY(-1px);
      background: var(--accent);
      color: white;
    }
    .calendar-toolbar-copy {
      text-align: center;
    }
    .calendar-kicker,
    .deadlines-kicker,
    .spotlight-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-transform: uppercase;
      letter-spacing: 0.26em;
      font-size: 10px;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .calendar-month-title,
    .deadlines-panel h3,
    .deadline-spotlight h4 {
      font-family: 'Cinzel', serif;
      color: var(--text);
      line-height: 1.1;
    }
    .calendar-month-title {
      font-size: clamp(22px, 2.5vw, 30px);
    }
    .calendar-weekdays,
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 10px;
    }
    .calendar-weekdays {
      margin-bottom: 10px;
    }
    .calendar-weekday {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 0.18em;
      color: rgba(26, 26, 26, 0.48);
      text-align: center;
      padding: 4px 0;
    }
    .calendar-cell,
    .calendar-empty {
      min-height: 108px;
      border-radius: 14px;
    }
    .calendar-cell {
      position: relative;
      border: 1px solid rgba(0, 102, 204, 0.14);
      background: rgba(255, 255, 255, 0.72);
      padding: 8px;
      text-align: left;
      color: var(--text);
      cursor: pointer;
      overflow: hidden;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .calendar-cell:hover {
      transform: translateY(-2px);
      border-color: rgba(0, 102, 204, 0.3);
      box-shadow: 0 14px 24px rgba(0, 102, 204, 0.08);
    }
    .calendar-cell.is-selected {
      border-color: rgba(0, 102, 204, 0.6);
      box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1), 0 16px 30px rgba(0, 102, 204, 0.12);
    }
    .calendar-cell.is-today::before {
      content: '';
      position: absolute;
      inset: 10px 10px auto auto;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 0 5px rgba(0, 102, 204, 0.12);
    }
    .calendar-cell.is-past {
      opacity: 0.72;
    }
    .calendar-day-number {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 999px;
      background: rgba(0, 102, 204, 0.06);
      color: var(--accent-light);
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 10px;
    }
    .calendar-cell.is-today .calendar-day-number {
      background: var(--accent);
      color: white;
    }
    .calendar-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    .calendar-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border-radius: 999px;
      padding: 4px 8px;
      font-size: 9px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 600;
      line-height: 1;
    }
    .calendar-badge.event {
      background: rgba(0, 102, 204, 0.1);
      color: var(--accent-light);
      border: 1px solid rgba(0, 102, 204, 0.16);
    }
    .calendar-badge.deadline {
      background: rgba(201, 168, 76, 0.14);
      color: #8a6c12;
      border: 1px solid rgba(201, 168, 76, 0.24);
    }
    .calendar-empty {
      border: 1px dashed rgba(0, 102, 204, 0.08);
      background: rgba(255, 255, 255, 0.35);
    }
    .deadlines-panel {
      padding: 26px;
      position: sticky;
      top: calc(var(--nav-h) + 18px);
    }
    .deadlines-panel h3 {
      font-size: clamp(24px, 2.4vw, 30px);
      margin-bottom: 10px;
    }
    .deadlines-panel p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.7;
    }
    .deadline-list {
      display: grid;
      gap: 12px;
      margin: 18px 0 20px;
    }
    .deadline-card {
      border: 1px solid rgba(0, 102, 204, 0.14);
      border-radius: 14px;
      padding: 14px;
      background: rgba(255, 255, 255, 0.9);
    }
    .deadline-card-top {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: flex-start;
      margin-bottom: 10px;
    }
    .deadline-card h4 {
      font-family: 'Cinzel', serif;
      font-size: 18px;
      line-height: 1.15;
      color: var(--text);
      margin-bottom: 4px;
    }
    .deadline-card .deadline-meta {
      color: rgba(26, 26, 26, 0.58);
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .deadline-card .deadline-date {
      border: 1px solid rgba(201, 168, 76, 0.25);
      color: #8a6c12;
      background: rgba(201, 168, 76, 0.12);
      border-radius: 999px;
      padding: 5px 9px;
      font-size: 9px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      white-space: nowrap;
      line-height: 1;
    }
    .deadline-card p {
      margin-bottom: 12px;
    }
    .deadline-card .event-register-btn {
      width: 100%;
      justify-content: center;
      text-align: center;
    }
    .deadline-spotlight {
      margin-top: 16px;
      padding-top: 18px;
      border-top: 1px solid rgba(0, 102, 204, 0.12);
    }
    .deadline-spotlight h4 {
      font-size: 22px;
      margin-bottom: 8px;
    }
    .deadline-spotlight-list {
      display: grid;
      gap: 10px;
      margin-top: 12px;
    }
    .deadline-spotlight-item {
      border-left: 2px solid rgba(0, 102, 204, 0.18);
      padding-left: 12px;
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.65;
    }
    .event-register-btn {
      border: 1px solid rgba(0, 102, 204, 0.38);
      border-radius: 999px;
      background: rgba(0, 102, 204, 0.1);
      color: var(--accent-light);
      font-size: 10px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      font-weight: 600;
      padding: 8px 12px;
      cursor: pointer;
      transition: background 0.25s ease, color 0.25s ease, border-color 0.25s ease;
    }
    .event-register-btn:hover {
      background: var(--accent);
      color: white;
      border-color: var(--accent);
    }
    .event-registration-panel {
      margin-top: 24px;
      padding: 22px;
    }
    .event-registration-panel[hidden] {
      display: none;
    }
    .event-registration-panel h3 {
      font-family: 'Cinzel', serif;
      font-size: clamp(22px, 2vw, 30px);
      margin-bottom: 8px;
      color: var(--text);
    }
    .event-registration-panel p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.6;
      margin-bottom: 14px;
    }
    .event-registration-form {
      display: grid;
      gap: 14px;
    }
    .event-registration-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(220px, 1fr));
      gap: 12px;
    }
    .event-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .event-field label {
      color: rgba(26, 26, 26, 0.72);
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 500;
    }
    .event-field input,
    .event-field select,
    .event-field textarea {
      width: 100%;
      border: 1px solid rgba(0, 102, 204, 0.24);
      border-radius: 8px;
      background: white;
      color: var(--text);
      padding: 12px;
      font-family: 'Outfit', sans-serif;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .event-field textarea {
      min-height: 96px;
      resize: vertical;
    }
    .event-field input:focus,
    .event-field select:focus,
    .event-field textarea:focus {
      border-color: rgba(0, 102, 204, 0.62);
      box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.12);
    }
    .event-registration-actions {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 4px;
    }
    .event-submit {
      padding: 12px 24px;
      border-radius: 8px;
      border: 1px solid var(--accent);
      background: var(--accent);
      color: white;
      font-size: 11px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .event-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(0, 102, 204, 0.34);
    }
    .event-form-status {
      color: rgba(26, 26, 26, 0.58);
      font-size: 13px;
      line-height: 1.6;
      min-height: 1.4em;
    }
    .event-form-status.error { color: #d32f2f; }
    .event-form-status.success { color: #388e3c; }
    .event-ticket-panel {
      margin-top: 22px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) 320px;
      gap: 18px;
      align-items: center;
      padding: 18px;
      border: 1px solid rgba(0, 102, 204, 0.2);
      border-radius: 18px;
      background: linear-gradient(180deg, rgba(0, 102, 204, 0.05), rgba(255,255,255,0.92));
    }
    .event-ticket-panel[hidden] { display: none; }
    .event-ticket-copy h4 {
      font-family: 'Cinzel', serif;
      font-size: clamp(22px, 2vw, 30px);
      color: var(--text);
      margin-bottom: 8px;
      line-height: 1.1;
    }
    .event-ticket-copy p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.65;
      margin-bottom: 8px;
    }
    .event-ticket-qr-wrap {
      justify-self: end;
      width: 100%;
      max-width: 320px;
      padding: 12px;
      border-radius: 16px;
      border: 1px solid rgba(0, 102, 204, 0.16);
      background: white;
      box-shadow: 0 18px 30px rgba(0, 102, 204, 0.08);
    }
    .event-ticket-qr-wrap img {
      width: 100%;
      height: auto;
      display: block;
    }
    .event-ticket-actions {
      grid-column: 1 / -1;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
    }
    .event-ticket-actions .event-register-btn {
      padding: 12px 18px;
    }
    .member-ticket-list {
      display: grid;
      gap: 12px;
      margin-top: 16px;
    }
    .member-ticket-card {
      border: 1px solid rgba(0, 102, 204, 0.14);
      border-radius: 14px;
      padding: 14px;
      background: rgba(255, 255, 255, 0.92);
      display: grid;
      gap: 10px;
    }
    .member-ticket-card h4 {
      font-family: 'Cinzel', serif;
      font-size: 18px;
      line-height: 1.15;
      color: var(--text);
    }
    .member-ticket-meta {
      color: var(--text-muted);
      font-size: 12px;
      line-height: 1.6;
    }
    .member-ticket-status {
      display: inline-flex;
      align-items: center;
      width: fit-content;
      gap: 8px;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 10px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      background: rgba(0, 102, 204, 0.08);
      color: var(--accent-light);
    }
    .member-ticket-status.attended {
      background: rgba(46, 125, 50, 0.1);
      color: #2e7d32;
    }

    @media (max-width: 600px) {
      .calendar-badge {
        padding: 0 !important;
        width: 8px !important;
        height: 8px !important;
        border-radius: 50% !important;
        font-size: 0 !important;
        color: transparent !important;
        overflow: hidden !important;
        border: none !important;
      }
      .calendar-cell,
      .calendar-empty {
        min-height: 48px !important;
        padding: 4px !important;
        border-radius: 8px !important;
      }
      .calendar-day-number {
        width: 22px !important;
        height: 22px !important;
        font-size: 10px !important;
        margin-bottom: 2px !important;
      }
      .calendar-badges {
        gap: 3px !important;
        justify-content: center !important;
      }
      .calendar-cell.is-today::before {
        inset: 4px 4px auto auto !important;
        width: 6px !important;
        height: 6px !important;
      }
    }

    /* ── OPPORTUNITY BOARD ── */
    #opportunities {
      position: relative;
      padding: 108px 48px 120px;
      background:
        radial-gradient(circle at 14% 10%, rgba(0, 102, 204, 0.08), transparent 42%),
        radial-gradient(circle at 82% 78%, rgba(201, 168, 76, 0.08), transparent 38%),
        var(--bg-secondary);
      border-top: 1px solid rgba(0, 102, 204, 0.1);
    }
    .opportunities-wrap {
      max-width: 1180px;
      margin: 0 auto;
    }
    .opportunities-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 24px;
      margin-bottom: 28px;
    }
    .opportunities-head-main {
      max-width: 700px;
    }
    .opportunities-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 0.3em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 14px;
    }
    .opportunities-eyebrow::before {
      content: '';
      width: 36px;
      height: 1px;
      background: var(--accent);
    }
    .opportunities-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(34px, 4vw, 56px);
      line-height: 1.08;
      margin-bottom: 14px;
      color: var(--text);
    }
    .opportunities-subtitle {
      color: var(--text-muted);
      font-size: 15px;
      line-height: 1.8;
      max-width: 620px;
    }
    .opportunities-stats {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: flex-end;
    }
    .opportunity-pill {
      border: 1px solid rgba(0, 102, 204, 0.18);
      background: rgba(0, 102, 204, 0.06);
      color: var(--accent-light);
      border-radius: 999px;
      padding: 8px 12px;
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .opportunities-board {
      display: grid;
      grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.78fr);
      gap: 24px;
      align-items: start;
    }
    .opportunity-panel,
    .opportunity-sidebar {
      border: 1px solid rgba(0, 102, 204, 0.16);
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.84);
      backdrop-filter: blur(12px);
      box-shadow: 0 18px 36px rgba(0, 102, 204, 0.06);
    }
    .opportunity-panel {
      padding: 22px;
    }
    .opportunity-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 18px;
    }
    .opportunity-filter {
      border: 1px solid rgba(0, 102, 204, 0.18);
      border-radius: 999px;
      padding: 9px 14px;
      background: rgba(255, 255, 255, 0.92);
      color: rgba(26, 26, 26, 0.72);
      font-size: 10px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }
    .opportunity-filter:hover,
    .opportunity-filter.is-active {
      background: var(--accent);
      color: white;
      border-color: var(--accent);
      transform: translateY(-1px);
    }
    .opportunity-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }
    .opportunity-card {
      position: relative;
      border: 1px solid rgba(0, 102, 204, 0.14);
      border-radius: 16px;
      padding: 18px;
      background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248, 251, 255, 0.96));
      overflow: hidden;
      transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    }
    .opportunity-card:hover {
      transform: translateY(-3px);
      border-color: rgba(0, 102, 204, 0.3);
      box-shadow: 0 14px 28px rgba(0, 102, 204, 0.08);
    }
    .opportunity-card::before {
      content: '';
      position: absolute;
      inset: 0 auto 0 0;
      width: 3px;
      background: linear-gradient(180deg, var(--accent-light), rgba(0, 102, 204, 0.12));
    }
    .opportunity-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 12px;
    }
    .opportunity-type {
      display: inline-flex;
      align-items: center;
      border: 1px solid rgba(0, 102, 204, 0.2);
      border-radius: 999px;
      padding: 4px 10px;
      font-size: 9px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--accent-light);
      background: rgba(0, 102, 204, 0.08);
      white-space: nowrap;
    }
    .opportunity-deadline {
      border: 1px solid rgba(201, 168, 76, 0.24);
      background: rgba(201, 168, 76, 0.12);
      color: #8a6c12;
      border-radius: 999px;
      padding: 4px 10px;
      font-size: 9px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      white-space: nowrap;
      line-height: 1;
    }
    .opportunity-card h3 {
      font-family: 'Cinzel', serif;
      font-size: 22px;
      line-height: 1.14;
      margin-bottom: 10px;
      color: var(--text);
    }
    .opportunity-card p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.72;
      margin-bottom: 12px;
    }
    .opportunity-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
    }
    .opportunity-meta span {
      border: 1px solid rgba(0, 102, 204, 0.12);
      background: rgba(0, 102, 204, 0.04);
      color: rgba(26, 26, 26, 0.7);
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 10px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .opportunity-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .opportunity-link,
    .opportunity-submit {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      border: 1px solid rgba(0, 102, 204, 0.28);
      padding: 9px 14px;
      font-size: 10px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }
    .opportunity-link {
      background: rgba(0, 102, 204, 0.08);
      color: var(--accent-light);
    }
    .opportunity-submit {
      width: 100%;
      background: var(--accent);
      color: white;
      border-color: var(--accent);
    }
    .opportunity-link:hover,
    .opportunity-submit:hover {
      transform: translateY(-1px);
      background: var(--accent-light);
      color: white;
      border-color: var(--accent-light);
    }
    .opportunity-sidebar {
      padding: 22px;
      position: sticky;
      top: calc(var(--nav-h) + 18px);
    }
    .opportunity-sidebar h3,
    .opportunity-sidebar h4 {
      font-family: 'Cinzel', serif;
      color: var(--text);
      line-height: 1.12;
    }
    .opportunity-sidebar h3 {
      font-size: clamp(24px, 2.4vw, 32px);
      margin-bottom: 10px;
    }
    .opportunity-sidebar p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.7;
    }
    .sidebar-block {
      margin-top: 18px;
      padding-top: 18px;
      border-top: 1px solid rgba(0, 102, 204, 0.12);
    }
    .sidebar-block h4 {
      font-size: 20px;
      margin-bottom: 8px;
    }
    .sidebar-list {
      display: grid;
      gap: 10px;
      margin-top: 14px;
    }
    .sidebar-list-item {
      border-left: 2px solid rgba(0, 102, 204, 0.18);
      padding-left: 12px;
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.6;
    }
    .sidebar-tip {
      margin-top: 14px;
      padding: 14px;
      border-radius: 14px;
      border: 1px solid rgba(201, 168, 76, 0.2);
      background: rgba(201, 168, 76, 0.08);
      color: rgba(26, 26, 26, 0.72);
      font-size: 12px;
      line-height: 1.7;
    }

    /* ── TEAM / COMMITTEE ── */
    #team {
      position: relative;
      padding: 108px 48px 120px;
      background: #0a0d14;
      border-top: 1px solid rgba(0, 102, 204, 0.1);
    }
    #team::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 60% 40% at 10% 10%, rgba(30, 80, 200, 0.18), transparent 55%),
        radial-gradient(ellipse 50% 40% at 90% 80%, rgba(201, 168, 76, 0.1), transparent 50%);
      pointer-events: none;
    }
    .team-wrap {
      max-width: 1280px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
    .team-head {
      max-width: 760px;
      margin: 0 auto 56px;
      text-align: center;
    }
    .team-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: #c9a84c;
      text-transform: uppercase;
      letter-spacing: 0.3em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 14px;
    }
    .team-eyebrow::before {
      content: '';
      width: 36px;
      height: 1px;
      background: #c9a84c;
    }
    .team-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(34px, 4vw, 56px);
      line-height: 1.08;
      margin-bottom: 14px;
      color: #ffffff;
    }
    .team-subtitle {
      color: rgba(255,255,255,0.55);
      font-size: 15px;
      line-height: 1.8;
      max-width: 640px;
      margin: 0 auto 0;
    }
    /* Position rows container */
    .team-hierarchy {
      display: flex;
      flex-direction: column;
      gap: 0;
    }
    /* Each position row */
    .position-row {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 40px 0 10px;
      position: relative;
    }
    .position-row:not(:last-child)::after {
      content: '';
      display: block;
      width: 1px;
      height: 40px;
      background: linear-gradient(to bottom, rgba(201,168,76,0.5), transparent);
      margin: 0 auto;
    }
    /* Position label above the cards in each row */
    .position-label {
      font-size: 9px;
      letter-spacing: 0.28em;
      text-transform: uppercase;
      color: #c9a84c;
      font-weight: 700;
      margin-bottom: 24px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    .position-label::before,
    .position-label::after {
      content: '';
      width: 28px;
      height: 1px;
      background: #c9a84c;
      opacity: 0.5;
    }
    /* Cards in a row – flexbox layout centered */
    .position-cards {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 14px;
      width: 100%;
      max-width: 1280px;
    }
    /* Individual member card - dark cinematic style */
    .member-card {
      position: relative;
      width: 100%;
      max-width: 168px;
      flex-shrink: 0;
      background: linear-gradient(160deg, #141926 0%, #0d1320 60%, #0a1018 100%);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 14px;
      overflow: hidden;
      cursor: pointer;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
      box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    }
    .member-card:hover {
      transform: translateY(-6px) scale(1.02);
      border-color: rgba(201,168,76,0.4);
      box-shadow: 0 24px 56px rgba(0,0,0,0.7), 0 0 0 1px rgba(201,168,76,0.12);
    }
    /* Photo area */
    .member-photo-wrap {
      width: 100%;
      aspect-ratio: 3/4;
      position: relative;
      overflow: hidden;
      background: linear-gradient(160deg, #1a2340 0%, #101828 100%);
    }
    .member-photo-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      filter: grayscale(100%) brightness(0.85);
      transition: filter 0.35s ease, transform 0.35s ease;
    }
    .member-card:hover .member-photo-wrap img {
      filter: grayscale(40%) brightness(1);
      transform: scale(1.04);
    }
    /* Avatar fallback */
    .member-avatar-dark {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(145deg, #1e2d50, #111827);
      font-family: 'Cinzel', serif;
      font-size: 30px;
      font-weight: 700;
      color: rgba(201,168,76,0.6);
      letter-spacing: 0.05em;
    }
    /* Gradient overlay at bottom of photo */
    .member-photo-wrap::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 55%;
      background: linear-gradient(to top, rgba(10,13,20,1) 0%, rgba(10,13,20,0.7) 40%, transparent 100%);
      pointer-events: none;
    }
    /* Name + role overlay at bottom of photo */
    .member-info-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 14px 10px 12px;
      z-index: 2;
    }
    .member-info-overlay .member-role-label {
      font-size: 6.5px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #c9a84c;
      font-weight: 700;
      display: block;
      margin-bottom: 4px;
    }
    .member-info-overlay h3 {
      font-family: 'Cinzel', serif;
      font-size: 11px;
      font-weight: 700;
      color: #ffffff;
      line-height: 1.25;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    /* Bottom action strip */
    .member-card-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 7px 10px 9px;
      gap: 6px;
      border-top: 1px solid rgba(255,255,255,0.06);
    }
    .member-view-btn {
      font-size: 7px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      font-weight: 700;
      color: #c9a84c;
      background: transparent;
      border: 1px solid rgba(201,168,76,0.3);
      border-radius: 999px;
      padding: 5px 8px;
      cursor: pointer;
      transition: background 0.2s ease, color 0.2s ease;
      white-space: nowrap;
    }
    .member-view-btn:hover {
      background: #c9a84c;
      color: #0a0d14;
      border-color: #c9a84c;
    }
    .member-socials {
      display: flex;
      gap: 4px;
    }
    .member-socials a {
      width: 22px;
      height: 22px;
      border-radius: 5px;
      border: 1px solid rgba(255,255,255,0.1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      color: rgba(255,255,255,0.5);
      font-size: 8px;
      font-weight: 700;
      background: rgba(255,255,255,0.04);
      transition: border-color 0.2s, color 0.2s;
    }
    .member-socials a:hover {
      border-color: rgba(201,168,76,0.5);
      color: #c9a84c;
    }

    .team-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(5, 8, 16, 0.72);
      backdrop-filter: blur(6px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1300;
      padding: 16px;
    }
    .team-modal {
      width: min(820px, 100%);
      max-height: 92vh;
      overflow: auto;
      background: linear-gradient(155deg, #141926, #0a0d14);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 40px 100px rgba(0,0,0,0.8), 0 0 0 1px rgba(201,168,76,0.15);
      border: 1px solid rgba(255,255,255,0.07);
      position: relative;
    }
    .team-modal::before {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      top: 0;
      height: 3px;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
      background: linear-gradient(90deg, #c9a84c, #0066cc, #c9a84c);
    }
    .team-modal-grid {
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 28px;
      align-items: start;
    }
    .team-modal-avatar {
      width: 220px;
      height: 260px;
      border-radius: 16px;
      background: linear-gradient(145deg, #1e2d50, #111827);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Cinzel', serif;
      font-size: 56px;
      color: rgba(201,168,76,0.6);
      border: 1px solid rgba(201,168,76,0.15);
      overflow: hidden;
    }
    .team-modal-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
    }
    .team-modal h3 {
      font-family: 'Cinzel', serif;
      font-size: clamp(24px, 3vw, 38px);
      margin-bottom: 8px;
      color: #ffffff;
    }
    .team-modal .modal-role {
      display: inline-flex;
      margin-bottom: 14px;
      background: rgba(201,168,76,0.1);
      border: 1px solid rgba(201,168,76,0.3);
      color: #c9a84c;
      border-radius: 999px;
      padding: 5px 12px;
      font-size: 9px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      font-weight: 700;
    }
    .team-modal p {
      color: rgba(255,255,255,0.6);
      font-size: 14px;
      line-height: 1.8;
      margin-bottom: 14px;
    }
    .team-modal-links {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .team-modal-links a {
      text-decoration: none;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 999px;
      padding: 9px 16px;
      font-size: 10px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.6);
      background: rgba(255,255,255,0.04);
      transition: border-color 0.2s, color 0.2s, background 0.2s;
    }
    .team-modal-links a:hover {
      background: rgba(201,168,76,0.15);
      color: #c9a84c;
      border-color: rgba(201,168,76,0.4);
    }
    .team-modal .close-btn {
      position: absolute;
      right: 18px;
      top: 14px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,0.1);
      background: rgba(255,255,255,0.05);
      font-size: 20px;
      cursor: pointer;
      color: rgba(255,255,255,0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s, color 0.2s;
    }
    .team-modal .close-btn:hover {
      background: rgba(201,168,76,0.15);
      color: #c9a84c;
    }

    /* ── RESOURCE HUB ── */
    #resources {
      position: relative;
      padding: 108px 48px 120px;
      background:
        radial-gradient(circle at 18% 18%, rgba(0, 102, 204, 0.08), transparent 40%),
        radial-gradient(circle at 84% 78%, rgba(201, 168, 76, 0.08), transparent 36%),
        var(--bg-secondary);
      border-top: 1px solid rgba(0, 102, 204, 0.1);
    }
    .resources-wrap {
      max-width: 1180px;
      margin: 0 auto;
    }
    .resources-head {
      text-align: center;
      max-width: 720px;
      margin: 0 auto 34px;
    }
    .resources-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 0.3em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 14px;
    }
    .resources-eyebrow::before {
      content: '';
      width: 36px;
      height: 1px;
      background: var(--accent);
    }
    .resources-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(34px, 4vw, 56px);
      line-height: 1.08;
      margin-bottom: 14px;
      color: var(--text);
    }
    .resources-subtitle {
      color: var(--text-muted);
      font-size: 15px;
      line-height: 1.8;
      max-width: 640px;
      margin: 0 auto;
    }
    .resources-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
    }
    .resource-card {
      border: 1px solid rgba(0, 102, 204, 0.16);
      border-radius: 18px;
      padding: 22px;
      background: rgba(255, 255, 255, 0.86);
      backdrop-filter: blur(10px);
      box-shadow: 0 18px 32px rgba(0, 102, 204, 0.06);
      transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
    }
    .resource-card:hover {
      transform: translateY(-3px);
      border-color: rgba(0, 102, 204, 0.32);
      box-shadow: 0 18px 34px rgba(0, 102, 204, 0.1);
    }
    .resource-icon {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--accent-light);
      background: rgba(0, 102, 204, 0.1);
      border: 1px solid rgba(0, 102, 204, 0.2);
      margin-bottom: 16px;
    }
    .resource-card h3 {
      font-family: 'Cinzel', serif;
      font-size: 24px;
      line-height: 1.12;
      margin-bottom: 10px;
      color: var(--text);
    }
    .resource-card p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.75;
      margin-bottom: 16px;
    }
    .resource-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 16px;
    }
    .resource-tags span {
      border: 1px solid rgba(0, 102, 204, 0.12);
      background: rgba(0, 102, 204, 0.05);
      color: rgba(26, 26, 26, 0.68);
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 10px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .resource-card .opportunity-actions {
      margin-top: auto;
    }

    /* ── JOIN US ── */
    #join {
      position: relative;
      padding: 108px 48px 118px;
      background:
        radial-gradient(circle at 18% 12%, rgba(0, 102, 204, 0.09), transparent 42%),
        radial-gradient(circle at 82% 78%, rgba(0, 102, 204, 0.07), transparent 38%),
        var(--bg);
      border-top: 1px solid rgba(0, 102, 204, 0.12);
      border-bottom: 1px solid rgba(0, 102, 204, 0.08);
    }
    .join-wrap {
      max-width: 1020px;
      margin: 0 auto;
    }
    .join-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 0.3em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 14px;
    }
    .join-eyebrow::before {
      content: '';
      width: 36px;
      height: 1px;
      background: var(--accent);
    }
    .join-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(34px, 4.4vw, 56px);
      line-height: 1.08;
      max-width: 680px;
      margin-bottom: 14px;
      color: var(--text);
    }
    .join-copy {
      max-width: 640px;
      color: var(--text-muted);
      font-size: 15px;
      line-height: 1.8;
      margin-bottom: 30px;
    }
    .join-cards {
      display: grid;
      grid-template-columns: repeat(2, minmax(240px, 1fr));
      gap: 18px;
      margin-bottom: 28px;
    }
    .join-card {
      border: 1px solid rgba(0, 102, 204, 0.2);
      border-radius: 14px;
      padding: 22px 20px 18px;
      background: rgba(0, 102, 204, 0.03);
      transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
    }
    .join-card:hover {
      border-color: rgba(0, 102, 204, 0.42);
      transform: translateY(-2px);
      box-shadow: 0 14px 28px rgba(0, 102, 204, 0.12);
    }
    .join-card h3 {
      font-family: 'Cinzel', serif;
      font-size: clamp(20px, 2vw, 28px);
      margin-bottom: 8px;
      color: var(--text);
    }
    .join-card p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.72;
    }
    .join-card-icon {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      margin-bottom: 10px;
      border: 1px solid rgba(0, 102, 204, 0.28);
      background: rgba(0, 102, 204, 0.1);
      color: var(--accent-light);
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .register-trigger {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 14px 24px;
      border-radius: 999px;
      border: 1px solid rgba(0, 102, 204, 0.45);
      background: rgba(0, 102, 204, 0.12);
      color: var(--accent-light);
      font-size: 11px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.25s ease, color 0.25s ease, border-color 0.25s ease;
    }
    .register-trigger:hover {
      background: var(--accent);
      color: white;
      border-color: var(--accent);
    }
    .registration-panel {
      margin-top: 26px;
      border: 1px solid rgba(0, 102, 204, 0.25);
      border-radius: 14px;
      padding: 22px;
      background: rgba(0, 102, 204, 0.03);
    }
    .registration-panel[hidden] {
      display: none;
    }
    .registration-head {
      margin-bottom: 14px;
    }
    .registration-head h3 {
      font-family: 'Cinzel', serif;
      font-size: clamp(22px, 2vw, 30px);
      margin-bottom: 8px;
      color: var(--text);
    }
    .registration-head p {
      color: var(--text-muted);
      font-size: 13px;
      line-height: 1.6;
    }
    .registration-form {
      display: grid;
      gap: 14px;
    }
    .registration-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(220px, 1fr));
      gap: 12px;
    }
    .registration-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .registration-field label {
      color: rgba(26, 26, 26, 0.72);
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 500;
    }
    .registration-field input,
    .registration-field select,
    .registration-field textarea {
      width: 100%;
      border: 1px solid rgba(0, 102, 204, 0.24);
      border-radius: 8px;
      background: white;
      color: var(--text);
      padding: 12px;
      font-family: 'Outfit', sans-serif;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .registration-field textarea {
      min-height: 106px;
      resize: vertical;
    }
    .registration-field input:focus,
    .registration-field select:focus,
    .registration-field textarea:focus {
      border-color: rgba(0, 102, 204, 0.62);
      box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.12);
    }
    .registration-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      margin-top: 4px;
    }
    .register-submit {
      padding: 12px 24px;
      border-radius: 8px;
      border: 1px solid var(--accent);
      background: var(--accent);
      color: white;
      font-size: 11px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .register-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(0, 102, 204, 0.34);
    }
    .form-status {
      color: rgba(26, 26, 26, 0.58);
      font-size: 13px;
      line-height: 1.6;
      min-height: 1.4em;
    }
    .form-status.error { color: #d32f2f; }
    .form-status.success { color: #388e3c; }

    /* ── EVENTS GALLERY ── */
    #gallery {
      position: relative;
      padding: 110px 48px 118px;
      background:
        radial-gradient(circle at 20% 30%, rgba(0, 102, 204, 0.07), transparent 35%),
        radial-gradient(circle at 82% 70%, rgba(0, 102, 204, 0.05), transparent 40%),
        var(--bg);
      border-top: 1px solid rgba(0, 102, 204, 0.1);
      border-bottom: 1px solid rgba(0, 102, 204, 0.08);
    }
    .gallery-wrap {
      max-width: 1280px;
      margin: 0 auto;
    }
    .gallery-head {
      margin-bottom: 60px;
      text-align: center;
    }
    .gallery-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 0.3em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 14px;
    }
    .gallery-eyebrow::before {
      content: '';
      width: 36px;
      height: 1px;
      background: var(--accent);
    }
    .gallery-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(40px, 5.2vw, 64px);
      line-height: 1.08;
      margin-bottom: 16px;
      color: var(--text);
    }
    .gallery-subtitle {
      font-size: 15px;
      line-height: 1.7;
      color: var(--text-muted);
      max-width: 520px;
      margin: 0 auto;
    }
    .gallery-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
      grid-auto-rows: 280px;
    }
    .gallery-item {
      position: relative;
      border: 1px solid rgba(0, 102, 204, 0.2);
      border-radius: 12px;
      overflow: hidden;
      background: rgba(0, 102, 204, 0.03);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .gallery-item:hover {
      border-color: rgba(0, 102, 204, 0.5);
      box-shadow: 0 12px 32px rgba(0, 102, 204, 0.15);
      transform: translateY(-4px);
    }
    .gallery-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }
    .gallery-item:hover img {
      transform: scale(1.05);
    }
    .gallery-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 102, 204, 0.08);
      color: rgba(0, 102, 204, 0.35);
      flex-direction: column;
      gap: 12px;
      font-size: 14px;
    }
    .gallery-placeholder svg {
      width: 40px;
      height: 40px;
      opacity: 0.4;
    }
    .gallery-item.large {
      grid-column: span 2;
      grid-row: span 2;
    }
    @media (max-width: 900px) {
      #gallery { padding: 88px 24px 94px; }
      .gallery-grid { gap: 18px; }
      .gallery-item, .gallery-item.large {
        grid-column: span 1;
        grid-row: span 1;
      }
      .gallery-title { font-size: clamp(32px, 7vw, 48px); }
    }
    @media (max-width: 480px) {
      #gallery { padding: 68px 16px 78px; }
      .gallery-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
      .gallery-title { font-size: 28px; margin-bottom: 12px; }
      .gallery-subtitle { font-size: 13px; }
    }

    /* ── AWARDS & MILESTONES ── */
    #achievements {
      position: relative;
      padding: 110px 48px 118px;
      background:
        radial-gradient(circle at 78% 20%, rgba(0, 102, 204, 0.08), transparent 38%),
        radial-gradient(circle at 16% 82%, rgba(0, 102, 204, 0.06), transparent 42%),
        var(--bg);
      border-top: 1px solid rgba(0, 102, 204, 0.1);
      border-bottom: 1px solid rgba(0, 102, 204, 0.08);
    }
    .achievements-wrap {
      max-width: 1020px;
      margin: 0 auto;
    }
    .achievements-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--accent);
      text-transform: uppercase;
      letter-spacing: 0.3em;
      font-size: 10px;
      font-weight: 600;
      margin-bottom: 14px;
    }
    .achievements-eyebrow::before {
      content: '';
      width: 36px;
      height: 1px;
      background: var(--accent);
    }
    .achievements-title {
      font-family: 'Cinzel', serif;
      font-size: clamp(34px, 4.4vw, 56px);
      line-height: 1.08;
      margin-bottom: 12px;
      max-width: 620px;
      color: var(--text);
    }
    .achievements-divider {
      width: 38px;
      height: 2px;
      background: var(--accent);
      margin-bottom: 30px;
      opacity: 0.9;
    }
    .achievements-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.18fr) minmax(0, 1fr);
      gap: 34px;
      align-items: start;
    }
    .milestone-list {
      display: grid;
      gap: 16px;
    }
    .milestone-card {
      border: 1px solid rgba(0, 102, 204, 0.2);
      border-radius: 12px;
      padding: 16px 18px;
      background: rgba(0, 102, 204, 0.03);
      display: flex;
      align-items: center;
      gap: 14px;
      transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
    }
    .milestone-card:hover {
      border-color: rgba(0, 102, 204, 0.44);
      transform: translateY(-2px);
      box-shadow: 0 14px 26px rgba(0, 102, 204, 0.12);
    }
    .milestone-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      border: 1px solid rgba(0, 102, 204, 0.25);
      background: rgba(0, 102, 204, 0.1);
      color: var(--accent-light);
      flex: 0 0 auto;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .milestone-content h3 {
      font-size: 19px;
      line-height: 1.3;
      margin-bottom: 4px;
      color: var(--text);
      font-family: 'Outfit', sans-serif;
      font-weight: 600;
    }
    .milestone-content p {
      font-size: 13px;
      line-height: 1.6;
      color: var(--text-muted);
    }
    .legacy-stats {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
      padding-top: 40px;
    }
    .legacy-stat {
      border: 1px solid rgba(0, 102, 204, 0.22);
      border-radius: 12px;
      background: rgba(0, 102, 204, 0.03);
      min-height: 106px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 12px;
    }
    .legacy-num {
      font-family: 'Cinzel', serif;
      font-size: clamp(30px, 3.8vw, 46px);
      color: var(--accent);
      line-height: 1;
      margin-bottom: 8px;
      letter-spacing: 0.01em;
    }
    .legacy-label {
      color: var(--text-muted);
      font-size: 12px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }
    /* ── ANIMATIONS ── */
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
      to { opacity: 1; }
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      nav { padding: 0 24px; }
      .nav-links { display: none; }
      .hamburger { display: flex; }
      .mobile-menu { display: flex; padding: 24px; }
      #hero { padding: 0 24px 120px; }
      .hero-stats { display: none; }
      .hero-title { font-size: clamp(42px, 10vw, 68px); }
      #about { padding: 86px 24px 90px; }
      .about-wrap {
        grid-template-columns: 1fr;
        gap: 34px;
      }
      .about-panel {
        min-height: 420px;
      }
      #events { padding: 88px 24px 94px; }
      .events-head {
        flex-direction: column;
        align-items: flex-start;
        margin-bottom: 28px;
      }
      .events-actions {
        flex-direction: row;
        align-items: center;
      }
      .events-layout {
        grid-template-columns: 1fr;
      }
      .calendar-panel { padding-right: 0; }
      .event-registration-grid { grid-template-columns: 1fr; }
      #achievements { padding: 88px 24px 94px; }
      .achievements-grid {
        grid-template-columns: 1fr;
        gap: 18px;
      }
      #opportunities { padding: 88px 24px 94px; }
      .opportunities-head {
        flex-direction: column;
        align-items: flex-start;
      }
      .opportunities-board {
        grid-template-columns: 1fr;
      }
      .opportunity-grid {
        grid-template-columns: 1fr;
      }
      .opportunity-sidebar {
        position: static;
      }
      #resources { padding: 88px 24px 94px; }
      .resources-grid { grid-template-columns: 1fr; }
      .legacy-stats {
        padding-top: 4px;
      }
      #team { padding: 88px 24px 94px; }
      .position-cards { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; }
      .team-modal-grid { grid-template-columns: 1fr; }
      .team-modal-avatar { width: 100%; height: 200px; font-size: 40px; }
      #join { padding: 88px 24px 94px; }
      .join-cards { grid-template-columns: 1fr; }
      .registration-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
      .btn-primary, .btn-ghost { padding: 14px 28px; width: 100%; text-align: center; justify-content: center; }
      .hero-actions { flex-direction: column; }
      .about-title { font-size: clamp(32px, 10vw, 44px); }
      .about-copy { font-size: 15px; line-height: 1.8; }
      .about-grid { grid-template-columns: 1fr; }
      .about-panel { min-height: 360px; }
      .events-title { font-size: clamp(30px, 9vw, 40px); }
      .events-head { gap: 16px; margin-bottom: 24px; }
      .events-actions {
        width: 100%;
        justify-content: space-between;
      }
      .events-count {
        padding: 6px 10px;
        font-size: 10px;
      }
      .events-count strong { font-size: 14px; }
      .events-cta {
        padding: 7px 12px;
        font-size: 10px;
      }
      .event-register-btn {
        width: 100%;
        justify-content: center;
      }
      .event-registration-panel { padding: 16px; }
      .achievements-title { font-size: clamp(30px, 9vw, 42px); }
      .milestone-card {
        padding: 14px;
        align-items: flex-start;
      }
      .milestone-icon {
        width: 40px;
        height: 40px;
      }
      .milestone-content h3 { font-size: 17px; }
      .legacy-stats { grid-template-columns: 1fr; }
      .register-trigger {
        width: 100%;
        justify-content: center;
      }
      .registration-panel { padding: 16px; }
      .opportunities-title { font-size: clamp(30px, 9vw, 42px); }
      .opportunities-board { gap: 18px; }
      .opportunity-panel, .opportunity-sidebar { padding: 16px; }
      .opportunity-card h3 { font-size: 19px; }
      .resource-card { padding: 18px; }
      .resource-card h3 { font-size: 21px; }
      .member-actions { flex-direction: column; align-items: stretch; }
      .member-socials { justify-content: center; }
      .member-view-btn { width: 100%; }
    }
    /* Footer styles */
    .site-footer {
      background: linear-gradient(180deg, rgba(248, 249, 250, 0.98), rgba(248, 249, 250, 0.96));
      border-top: 1px solid rgba(0, 102, 204, 0.06);
      color: rgba(26, 26, 26, 0.84);
      padding: 56px 48px 26px;
      margin-top: 30px;
    }
    .footer-wrap {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 320px 1fr 1fr 280px;
      gap: 28px;
      align-items: start;
    }
    .footer-brand { display:flex; flex-direction:column; gap:14px; }
    .footer-desc { color: rgba(26, 26, 26, 0.56); font-size:13px; line-height:1.7; max-width:320px; }
    .footer-socials { display:flex; gap:10px; }
    .footer-socials .social { display:inline-flex; width:34px; height:34px; align-items:center; justify-content:center; border:1px solid rgba(0, 102, 204, 0.14); border-radius:8px; color:var(--accent); text-decoration:none; font-weight:700; }
    .footer-col h4 { color: var(--accent); font-size:12px; letter-spacing:0.18em; text-transform:uppercase; margin-bottom:12px; }
    .footer-col ul { list-style:none; padding:0; margin:0; display:grid; gap:8px; }
    .footer-col a { color: rgba(26, 26, 26, 0.6); text-decoration:none; font-size:13px; }
    .footer-col.contact p { color: rgba(26, 26, 26, 0.6); font-size:13px; margin:6px 0; }
    .footer-bottom { margin-top:28px; text-align:center; color: rgba(26, 26, 26, 0.36); font-size:13px; padding-top:18px; border-top:1px solid rgba(0, 102, 204, 0.04); }

    @media (max-width: 900px) {
      .footer-wrap { grid-template-columns: 1fr; }
      .footer-bottom { text-align:left; }
    }

    /* Announcements Modal Backdrop */
    .ann-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(5, 8, 16, 0.72);
      backdrop-filter: blur(6px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1400; /* overlay team modal */
      padding: 16px;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }
    .ann-modal-backdrop.open {
      opacity: 1;
      pointer-events: auto;
    }
    .ann-modal {
      width: min(640px, 100%);
      max-height: 85vh;
      overflow: auto;
      background: linear-gradient(155deg, #141926, #0a0d14);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 40px 100px rgba(0,0,0,0.8), 0 0 0 1px rgba(201,168,76,0.15);
      border: 1px solid rgba(255,255,255,0.07);
      position: relative;
      transform: translateY(-20px);
      transition: transform 0.3s ease;
    }
    .ann-modal-backdrop.open .ann-modal {
      transform: translateY(0);
    }
    .ann-modal::before {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      top: 0;
      height: 3px;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
      background: linear-gradient(90deg, #27ae60, #0066cc, #27ae60);
    }
    .ann-modal h3 {
      font-family: 'Cinzel', serif;
      font-size: clamp(22px, 2.5vw, 30px);
      margin-bottom: 20px;
      color: #ffffff;
      text-align: center;
    }
    .ann-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .ann-item {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 12px;
      padding: 18px;
      position: relative;
      text-align: left;
    }
    .ann-item-type {
      display: inline-block;
      font-size: 9px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 999px;
      margin-bottom: 10px;
    }
    .ann-item-type.info { background: rgba(52, 152, 219, 0.12); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.25); }
    .ann-item-type.warning { background: rgba(243, 156, 18, 0.12); color: #f39c12; border: 1px solid rgba(243, 156, 18, 0.25); }
    .ann-item-type.success { background: rgba(39, 174, 96, 0.12); color: #27ae60; border: 1px solid rgba(39, 174, 96, 0.25); }
    .ann-item-type.urgent { background: rgba(231, 76, 60, 0.12); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.25); }

    .ann-item h4 {
      font-size: 16px;
      font-weight: 600;
      color: #ffffff;
      margin-bottom: 6px;
    }
    .ann-item p {
      color: rgba(255, 255, 255, 0.6);
      font-size: 13.5px;
      line-height: 1.6;
      margin-bottom: 12px;
    }
    .ann-item-date {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.35);
    }
    .ann-item-cta {
      display: inline-block;
      text-decoration: none;
      background: var(--accent);
      color: #fff;
      font-size: 11px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 600;
      padding: 7px 15px;
      border-radius: 6px;
      margin-top: 4px;
      transition: opacity 0.2s;
    }
    .ann-item-cta:hover {
      opacity: 0.9;
      color: #fff !important;
    }
    .ann-modal .close-btn {
      position: absolute;
      right: 18px;
      top: 14px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,0.1);
      background: rgba(255,255,255,0.05);
      font-size: 20px;
      cursor: pointer;
      color: rgba(255,255,255,0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s, color 0.2s;
    }
    .ann-modal .close-btn:hover {
      background: rgba(39, 174, 96, 0.15);
      color: #27ae60;
    }
  </style>
</head>
<body>

<!-- LOADER -->
<div id="loader">
  <div class="loader-ring"></div>
  <p class="loader-text">KBEC loading</p>
</div>

<!-- NAVBAR -->
<nav id="navbar">
  <a href="#" class="nav-logo">
    <div class="nav-logo-icon">
      <!-- Club logo image (replace file if logo asset name changes) -->
      <img src="club-logo.png" alt="KUET BEC Logo" />
    </div>
    <div class="nav-logo-text">
      Business & Entrepreneurship Club
      <span>KUET</span>
    </div>
  </a>

  <ul class="nav-links">
    <li><a href="#about">About</a></li>
    <li><a href="#events">Events</a></li>
    <li><a href="#achievements">Achievements</a></li>
    <li><a href="#team">Team</a></li>
    <li><a href="#gallery">Gallery</a></li>
    <li><a href="#resources">Resources</a></li>
    <li><a href="#sponsors">Sponsors</a></li>
    <li><a href="login.php">Members</a></li>
    <li><a href="#" class="nav-cta nav-cta-announcements" id="navAnnBtn">New Announcements</a></li>
    <li><a href="admin/login.php" class="nav-cta nav-cta-admin">Admin</a></li>
    <li><a href="register.php" class="nav-cta">Join Us</a></li>
  </ul>

  <div class="hamburger" id="hamburger">
    <span></span><span></span><span></span>
  </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
  <a href="#about">About</a>
  <a href="#events">Events</a>
  <a href="#achievements">Achievements</a>
  <a href="#team">Team</a>
  <a href="#gallery">Gallery</a>
  <a href="#resources">Resources</a>
  <a href="#sponsors">Sponsors</a>
  <a href="login.php">Members</a>
  <a href="#" id="mobileNavAnnBtn">New Announcements</a>
  <a href="admin/login.php">Admin</a>
  <a href="register.php">Join Us</a>
</div>

<!-- PHP Announcements Bar -->
<?php if (!empty($__announcements)): ?>
<?php foreach ($__announcements as $__ann):
  $__annClr = ['info'=>'#3498db','warning'=>'#f39c12','success'=>'#27ae60','urgent'=>'#e74c3c'][$__ann['type']] ?? '#3498db';
?>
<div style="background:<?= $__annClr ?>18;border-bottom:2px solid <?= $__annClr ?>40;padding:9px 20px;display:flex;align-items:center;justify-content:center;gap:12px;font-size:.84rem;flex-wrap:wrap;font-family:'Outfit',sans-serif;position:relative;z-index:50">
  <span style="background:<?= $__annClr ?>;color:#fff;padding:2px 10px;border-radius:999px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em"><?= htmlspecialchars($__ann['type'],ENT_QUOTES) ?></span>
  <b style="color:<?= $__annClr ?>"><?= htmlspecialchars($__ann['title'],ENT_QUOTES) ?></b>
  <?php if ($__ann['body']): ?><span style="color:rgba(0,0,0,.55);font-size:.79rem">— <?= htmlspecialchars(substr($__ann['body'],0,120),ENT_QUOTES) ?></span><?php endif; ?>
  <?php if ($__ann['link'] && $__ann['link_label']): ?><a href="<?= htmlspecialchars($__ann['link'],ENT_QUOTES) ?>" target="_blank" style="background:<?= $__annClr ?>;color:#fff;padding:3px 13px;border-radius:999px;font-size:.72rem;font-weight:700;text-decoration:none"><?= htmlspecialchars($__ann['link_label'],ENT_QUOTES) ?> →</a><?php endif; ?>
  <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:rgba(0,0,0,.4);font-size:1.1rem;position:absolute;right:16px;top:50%;transform:translateY(-50%)" title="Dismiss">✕</button>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- HERO -->

<section id="hero">
  <!-- Layered BG -->
  <div class="hero-bg">
    <div class="hero-bg-img"></div>
    <div class="hero-bg-overlay"></div>
    <div class="hero-bg-overlay2"></div>
  </div>

  <!-- Grain -->
  <div class="grain"></div>

  <!-- Geometric SVG lines -->
  <div class="hero-lines">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg">
      <line x1="1100" y1="0" x2="1440" y2="400" stroke="rgba(201,168,76,0.07)" stroke-width="1"/>
      <line x1="900" y1="0" x2="1440" y2="600" stroke="rgba(201,168,76,0.05)" stroke-width="1"/>
      <line x1="1200" y1="0" x2="800" y2="900" stroke="rgba(201,168,76,0.04)" stroke-width="1"/>
      <rect x="1050" y="80" width="200" height="200" stroke="rgba(201,168,76,0.06)" stroke-width="1" fill="none"/>
      <circle cx="1250" cy="200" r="120" stroke="rgba(201,168,76,0.05)" stroke-width="1" fill="none"/>
    </svg>
  </div>

  <!-- Orb -->
  <div class="hero-orb"></div>

  <!-- Main Content -->
  <div class="hero-content">
    <!-- title customized to show KUET before the main club name -->
    <h1 class="hero-title">
      <span class="line-gold">KUET</span> <span class="line-dim">Business &</span>
      Entrepreneurship<br/>
      <span class="line-gold">Club</span>
    </h1>

    <p class="hero-desc">
      The premier business and entrepreneurship club of Khulna University of Engineering &amp; Technology. Empowering future innovators, leaders, and changemakers through education, competitions, and real-world experience.
    </p>

    <div class="hero-actions">
      <a href="#events" class="btn-primary">Explore Events</a>
      <a href="#about" class="btn-ghost">
        Learn More
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
    </div>
  </div>

  <!-- Stats (desktop) -->
  <div class="hero-stats">
    <div class="stat-item">
      <div class="stat-num" data-target="500">0</div>
      <div class="stat-label">Members</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="118">0</div>
      <div class="stat-label">Events</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="25">0</div>
      <div class="stat-label">Partners</div>
    </div>
  </div>

  <!-- Scroll hint -->
  <div class="scroll-hint">
    <span>Scroll</span>
    <div class="scroll-line"></div>
  </div>
</section>

<!-- OUR STORY -->
<section id="about">
  <div class="about-wrap">
    <div class="about-content">
      <p class="about-eyebrow">Our Story</p>
      <h2 class="about-title">Cultivating Leaders, Shaping Futures</h2>
      <div class="about-divider"></div>

      <p class="about-copy">
        The Business &amp; Entrepreneurship Club at KUET is a student-driven organization dedicated to fostering business acumen, entrepreneurial mindset, and professional excellence among engineering students.
      </p>
      <p class="about-copy">
        Since our founding, we have organized 100+ events, connected students with industry leaders, and launched numerous successful student ventures that continue to make an impact beyond campus walls.
      </p>

      <div class="about-grid">
        <article class="about-card">
          <div class="about-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
              <path d="M12 3c2.8 0 5 2.2 5 5 0 1.8-.9 3.3-2.3 4.2-.6.4-1 .9-1.2 1.5l-.5 1.3h-2l-.5-1.3c-.2-.6-.6-1.1-1.2-1.5C7.9 11.3 7 9.8 7 8c0-2.8 2.2-5 5-5Zm-2 14h4M9.5 20h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3>Innovation</h3>
          <p>Driving creative thinking and novel solutions to real-world business challenges.</p>
        </article>

        <article class="about-card">
          <div class="about-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
              <path d="M4 12h4l2 2 4-4 2 2h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M14 8V5m0 0l2 2m-2-2-2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3>Networking</h3>
          <p>Connecting students with mentors, alumni, and industry professionals.</p>
        </article>

        <article class="about-card">
          <div class="about-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
              <path d="M4 16V8m5 8V4m5 12v-6m5 6v-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3>Growth</h3>
          <p>Enabling personal and professional development through curated programs.</p>
        </article>

        <article class="about-card">
          <div class="about-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
              <path d="M12 4l2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.3-4.5 2.3.9-5L4.8 9.2l5-.7L12 4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3>Excellence</h3>
          <p>Pursuing the highest standards in everything we do, together.</p>
        </article>
      </div>
    </div>

    <div class="about-panel">
      <img src="club-logo.png" alt="KUET Business and Entrepreneurship Club logo" />
      <div class="about-panel-copy">
        <span class="panel-kicker">KUET</span>
        <h3>Business &amp;<br />Entrepreneurship<br />Club</h3>
        <p>Building future leaders through ideas, innovation, and action.</p>
      </div>
    </div>

  </div>
</section>

<!-- UPCOMING EVENTS -->
<section id="events">
  <div class="events-wrap">
    <div class="events-head">
      <div class="events-head-main">
        <p class="events-eyebrow">What's Next</p>
        <h2 class="events-title">Upcoming Events</h2>
        <p class="events-subtitle">
          Track this month’s club schedule, see the next deadlines at a glance, and jump straight into registration from any event card.
        </p>
      </div>
      <div class="events-actions">
        <div class="events-count"><strong id="eventCountValue">4</strong> Upcoming Deadlines</div>
        <a href="#eventRegistrationPanel" class="events-cta">Join and Register</a>
      </div>
    </div>

    <div class="events-layout">
      <!-- LEFT: Calendar -->
      <div class="calendar-panel">
        <div class="calendar-toolbar">
          <button type="button" class="calendar-nav" id="calendarPrevBtn" aria-label="Previous month">&#8249;</button>
          <div class="calendar-toolbar-copy">
            <span class="calendar-kicker">Monthly View</span>
            <h3 class="calendar-month-title" id="calendarMonthLabel">June 2026</h3>
          </div>
          <button type="button" class="calendar-nav" id="calendarNextBtn" aria-label="Next month">&#8250;</button>
        </div>

        <div class="calendar-weekdays" aria-hidden="true">
          <div class="calendar-weekday">Sun</div>
          <div class="calendar-weekday">Mon</div>
          <div class="calendar-weekday">Tue</div>
          <div class="calendar-weekday">Wed</div>
          <div class="calendar-weekday">Thu</div>
          <div class="calendar-weekday">Fri</div>
          <div class="calendar-weekday">Sat</div>
        </div>

        <div class="calendar-grid" id="calendarGrid" aria-live="polite"></div>
      </div>

      <!-- RIGHT: Next Milestones -->
      <aside class="deadlines-panel">
        <span class="deadlines-kicker">Upcoming Deadlines</span>
        <h3>Next Milestones</h3>
        <p>Registration cutoffs and event dates are highlighted here so members can plan ahead.</p>

        <div class="deadline-list" id="deadlineList"></div>

        <div class="deadline-spotlight" id="deadlineSpotlight">
          <span class="spotlight-label">Selected Date</span>
          <h4 id="selectedDateLabel">June 12, 2026</h4>
          <div class="deadline-spotlight-list" id="selectedDateEvents">
            <div class="deadline-spotlight-item">No events or deadlines fall on this date. Pick another day to inspect the calendar.</div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Event Registration Form (opens below) -->
    <div class="event-registration-panel" id="eventRegistrationPanel" hidden>
      <h3>Event Registration</h3>
      <p>Select your event and submit your information to reserve your seat.</p>

      <form id="eventRegistrationForm" class="event-registration-form" novalidate>
        <div class="event-registration-grid">
          <div class="event-field">
            <label for="eventSelect">Event</label>
            <select id="eventSelect" name="event" required>
              <option value="">Select your event</option>
              <option value="NEXUS National Case Challenge 2026">NEXUS National Case Challenge 2026</option>
              <option value="InnovateTech Fest 2026">InnovateTech Fest 2026</option>
              <option value="TDExKUET Ideas &amp; Leadership Session">TDExKUET Ideas &amp; Leadership Session</option>
              <option value="KBEC Entrepreneurship Summit 2026">KBEC Entrepreneurship Summit 2026</option>
            </select>
          </div>
          <div class="event-field">
            <label for="eventRegName">Full Name</label>
            <input type="text" id="eventRegName" name="name" required />
          </div>
          <div class="event-field">
            <label for="eventRegEmail">Email</label>
            <input type="email" id="eventRegEmail" name="email" required />
          </div>
          <div class="event-field">
            <label for="eventRegPhone">Phone Number</label>
            <input type="tel" id="eventRegPhone" name="phone" required />
          </div>
          <div class="event-field">
            <label for="eventRegDept">Department</label>
            <input type="text" id="eventRegDept" name="department" list="departmentSuggestions" required />
          </div>
          <div class="event-field">
            <label for="eventRegBatch">Batch</label>
            <input type="text" id="eventRegBatch" name="batch" required />
          </div>
        </div>
        <div class="event-field">
          <label for="eventRegNote">Message (Optional)</label>
          <textarea id="eventRegNote" name="note" placeholder="Anything you'd like the organizers to know"></textarea>
        </div>
        <div class="event-registration-actions">
          <button type="submit" class="event-submit">Submit Registration</button>
          <p class="event-form-status" id="eventFormStatus" aria-live="polite"></p>
        </div>
      </form>

      <div class="event-ticket-panel" id="eventTicketPanel" hidden>
        <div class="event-ticket-copy">
          <p class="spotlight-label">Digital Ticket</p>
          <h4 id="eventTicketTitle">Ticket</h4>
          <p id="eventTicketMeta"></p>
          <p id="eventTicketStatus"></p>
        </div>
        <div class="event-ticket-qr-wrap">
          <img id="eventTicketQr" alt="Event ticket QR code" />
        </div>
        <div class="event-ticket-actions">
          <button type="button" class="event-submit" id="copyTicketLinkBtn">Copy Check-in Link</button>
          <button type="button" class="event-register-btn" id="closeTicketPanelBtn">Close Ticket</button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- EVENTS GALLERY -->
<section id="gallery">
  <div class="gallery-wrap">
    <div class="gallery-head">
      <p class="gallery-eyebrow">Photo Memories</p>
      <h2 class="gallery-title">Events Gallery</h2>
      <p class="gallery-subtitle">
        Relive the moments from our past events, competitions, and memorable gatherings. Check back often for new photos from upcoming events.
      </p>
    </div>

    <div class="gallery-grid">
      <?php foreach ($__gallery as $img): ?>
        <article class="gallery-item<?= $img['category'] === 'large' ? ' large' : '' ?>">
          <img src="<?= htmlspecialchars($img['image_path'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($img['caption'] ?? 'Event gallery photo', ENT_QUOTES) ?>" loading="lazy" />
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- AWARDS & MILESTONES -->
<section id="achievements">
  <div class="achievements-wrap">
    <p class="achievements-eyebrow">Our Legacy</p>
    <h2 class="achievements-title">Awards &amp; Milestones</h2>
    <div class="achievements-divider"></div>

    <div class="achievements-grid">
      <div class="milestone-list">
        <article class="milestone-card">
          <div class="milestone-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M8 20h8M9 17h6m-6-2c-2.5-1.2-4-3.5-4-6.5V5h14v3.5c0 3-1.5 5.3-4 6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 7H3a2 2 0 0 0 2 2M19 7h2a2 2 0 0 1-2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="milestone-content">
            <h3>Best Club Award 2025</h3>
            <p>KUET Annual Club Excellence Awards - Gold Category</p>
          </div>
        </article>

        <article class="milestone-card">
          <div class="milestone-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="m12 3 2.6 5.4L20 9.2l-4 3.9.9 5.6-4.9-2.6-4.9 2.6.9-5.6-4-3.9 5.4-.8L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="milestone-content">
            <h3>National Case Competition Champions</h3>
            <p>Dhaka University Business Fest 2024 - 1st Place</p>
          </div>
        </article>

        <article class="milestone-card">
          <div class="milestone-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/>
              <path d="M8 21h8l-1-6H9l-1 6Zm-4-8 3-2m14 2-3-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="milestone-content">
            <h3>Youth Entrepreneurship Award</h3>
            <p>Bangladesh Young Entrepreneur Forum 2024</p>
          </div>
        </article>

        <article class="milestone-card">
          <div class="milestone-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="m12 4 2.2 4.4 4.8.7-3.5 3.4.8 4.9-4.3-2.3-4.3 2.3.8-4.9-3.5-3.4 4.8-.7L12 4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="milestone-content">
            <h3>Best Student Club - Engineering Category</h3>
            <p>Inter-University Club Awards, Chittagong 2023</p>
          </div>
        </article>
      </div>

      <div class="achievement-photo-inline">
        <img src="assets/club-fair.jpg" alt="KBEC club fair photo" loading="lazy" />
      </div>

      <div class="legacy-stats">
        <article class="legacy-stat">
          <div class="legacy-num" data-target="500">0</div>
          <p class="legacy-label">Active Members</p>
        </article>
        <article class="legacy-stat">
          <div class="legacy-num" data-target="120">0</div>
          <p class="legacy-label">Events Hosted</p>
        </article>
        <article class="legacy-stat">
          <div class="legacy-num" data-target="15">0</div>
          <p class="legacy-label">Awards Won</p>
        </article>
        <article class="legacy-stat">
          <div class="legacy-num" data-target="30">0</div>
          <p class="legacy-label">Partner Brands</p>
        </article>
      </div>
    </div>
  </div>
</section>

<!-- OPPORTUNITY BOARD -->
<section id="opportunities">
  <div class="opportunities-wrap">
    <div class="opportunities-head">
      <div class="opportunities-head-main">
        <p class="opportunities-eyebrow">Student-Focused Features</p>
        <h2 class="opportunities-title">Internship &amp; Opportunity Board</h2>
        <p class="opportunities-subtitle">A curated board for students to find internships, startup hiring, case competitions, and scholarships in one place.</p>
      </div>
      <div class="opportunities-stats" aria-label="Opportunity categories">
        <span class="opportunity-pill">Internships</span>
        <span class="opportunity-pill">Startup Hiring</span>
        <span class="opportunity-pill">Case Competitions</span>
        <span class="opportunity-pill">Scholarships</span>
      </div>
    </div>

    <div class="opportunities-board">
      <div class="opportunity-panel">
        <div class="opportunity-filters" id="opportunityFilters" aria-label="Filter opportunities">
          <button type="button" class="opportunity-filter is-active" data-filter="all">All</button>
          <button type="button" class="opportunity-filter" data-filter="internship">Internships</button>
          <button type="button" class="opportunity-filter" data-filter="startup">Startup Hiring</button>
          <button type="button" class="opportunity-filter" data-filter="competition">Case Competitions</button>
          <button type="button" class="opportunity-filter" data-filter="scholarship">Scholarships</button>
        </div>

        <div class="opportunity-grid">
          <?php foreach ($__opportunities as $o): 
            $catLabel = [
                'internship' => 'Internship',
                'startup' => 'Startup Hiring',
                'competition' => 'Case Competition',
                'scholarship' => 'Scholarship'
            ][$o['category']] ?? 'Opportunity';
          ?>
            <article class="opportunity-card" data-category="<?= htmlspecialchars($o['category'], ENT_QUOTES) ?>">
              <div class="opportunity-top">
                <span class="opportunity-type"><?= htmlspecialchars($catLabel, ENT_QUOTES) ?></span>
                <span class="opportunity-deadline"><?= htmlspecialchars($o['deadline'], ENT_QUOTES) ?></span>
              </div>
              <h3><?= htmlspecialchars($o['title'], ENT_QUOTES) ?></h3>
              <p><?= htmlspecialchars($o['description'], ENT_QUOTES) ?></p>
              <div class="opportunity-meta">
                <span><?= htmlspecialchars($o['meta_1'], ENT_QUOTES) ?></span>
                <span><?= htmlspecialchars($o['meta_2'], ENT_QUOTES) ?></span>
                <span><?= htmlspecialchars($o['meta_3'], ENT_QUOTES) ?></span>
              </div>
              <div class="opportunity-actions">
                <?php if ($o['category'] === 'competition'): ?>
                  <a class="opportunity-link" href="#events">See Events</a>
                <?php endif; ?>
                <a class="opportunity-link" href="<?= htmlspecialchars($o['link'], ENT_QUOTES) ?>">Apply</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <aside class="opportunity-sidebar">
        <p class="opportunities-eyebrow">For Students</p>
        <h3>How to use the board</h3>
        <p>Filter by opportunity type, open the listing that fits your goals, and apply directly or share it with the club team.</p>
        <div class="sidebar-block">
          <h4>Quick Posting Guide</h4>
          <div class="sidebar-list">
            <div class="sidebar-list-item">Internships for summer roles, industry placements, or remote work.</div>
            <div class="sidebar-list-item">Startup hiring for founders looking for student talent.</div>
            <div class="sidebar-list-item">Case competitions with team-based registration deadlines.</div>
            <div class="sidebar-list-item">Scholarships with eligibility, deadline, and application links.</div>
          </div>
          <div class="sidebar-tip">Tip: keep posts short, include the deadline, and mention whether it is paid, remote, or team-based.</div>
        </div>
      </aside>
    </div>
  </div>
</section>

<!-- RESOURCE HUB -->
<section id="resources">
  <div class="resources-wrap">
    <div class="resources-head">
      <p class="resources-eyebrow">Resource Hub</p>
      <h2 class="resources-title">Tools, Templates &amp; Guides</h2>
      <p class="resources-subtitle">A focused collection of materials to help members prepare better decks, build stronger business documents, and launch new ideas faster.</p>
    </div>

    <div class="resources-grid">
      <article class="resource-card">
        <div class="resource-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M6 4h9l3 3v13H6V4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9 12h6M9 16h6M9 8h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Presentation Slides</h3>
        <p>Polished deck structures for pitches, case presentations, workshop recaps, and club showcase sessions.</p>
        <div class="resource-tags"><span>Pitch decks</span><span>Templates</span><span>Editable</span></div>
        <div class="opportunity-actions"><a class="opportunity-link" href="mailto:bec@kuet.ac.bd?subject=Resource%20Request%20-%20Presentation%20Slides">Request Access</a></div>
      </article>

      <article class="resource-card">
        <div class="resource-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M7 4h10v16H7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Business Templates</h3>
        <p>Ready-to-use formats for proposals, budgets, market research notes, and team planning documents.</p>
        <div class="resource-tags"><span>Proposals</span><span>Reports</span><span>Planning</span></div>
        <div class="opportunity-actions"><a class="opportunity-link" href="mailto:bec@kuet.ac.bd?subject=Resource%20Request%20-%20Business%20Templates">Request Access</a></div>
      </article>

      <article class="resource-card">
        <div class="resource-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M12 3v18M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h3>Startup Guides</h3>
        <p>Practical guides that cover idea validation, launching a product, and organizing a student-led startup.</p>
        <div class="resource-tags"><span>Launch</span><span>Validation</span><span>Founders</span></div>
        <div class="opportunity-actions"><a class="opportunity-link" href="mailto:bec@kuet.ac.bd?subject=Resource%20Request%20-%20Startup%20Guides">Request Access</a></div>
      </article>
    </div>
  </div>
</section>

<!-- TEAM / COMMITTEE -->
<section id="team">
  <div class="team-wrap">
    <div class="team-head">
      <p class="team-eyebrow">Executive Committee 2026–27</p>
      <h2 class="team-title">Meet the Team</h2>
      <p class="team-subtitle">
        The leadership driving KBEC forward — organized by position, from President to every dedicated secretary.
      </p>
    </div>

    <!-- Hierarchy rows, rendered by JS from TEAM_DATA -->
    <div class="team-hierarchy" id="teamHierarchy"></div>
  </div>
</section>

  <!-- SPONSORS -->
<section id="sponsors">
  <div class="sponsors-wrap">
      <div class="sponsors-head" style="text-align:center; max-width:880px; margin: 0 auto 18px;">
        <p class="sponsors-eyebrow">Supporters</p>
        <h2 class="sponsors-title">Our Sponsors</h2>
        <div class="about-divider" style="margin-bottom:18px; width:30px;"></div>
        <p class="sponsors-desc">We are grateful for the generous support of our sponsors who make our events and programs possible.</p>
      </div>

      <div class="sponsors-grid" aria-label="Sponsors">
        <?php foreach ($sponsorsList as $sp): ?>
          <?php if ($sp['website']): ?>
            <a href="<?= htmlspecialchars($sp['website'], ENT_QUOTES) ?>" target="_blank" class="partner-tile" style="text-decoration: none; color: inherit;">
          <?php else: ?>
            <div class="partner-tile">
          <?php endif; ?>

            <?php if ($sp['logo']): ?>
              <img class="partner-icon" src="<?= htmlspecialchars($sp['logo'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($sp['name'], ENT_QUOTES) ?> logo">
            <?php else: ?>
              <div class="partner-icon d-flex align-items-center justify-content-center" style="font-size:2.8rem; background: rgba(0, 102, 204, 0.05); width: 96px; height: 96px; border-radius: 12px; margin: 0 auto 12px;">🏢</div>
            <?php endif; ?>
            <div class="partner-name"><?= htmlspecialchars($sp['name'], ENT_QUOTES) ?></div>

          <?php if ($sp['website']): ?>
            </a>
          <?php else: ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
  </div>
</section>

  <!-- PARTNERS -->
<section id="partners">
  <div class="sponsors-wrap">
      <div class="sponsors-head" style="text-align:center; max-width:880px; margin: 0 auto 18px;">
        <p class="sponsors-eyebrow">Collaborators</p>
        <h2 class="sponsors-title">Our Partners</h2>
        <div class="about-divider" style="margin-bottom:18px; width:30px;"></div>
        <p class="sponsors-desc">We collaborate with student groups, organizations, and industry partners to deliver impactful programs and events.</p>
      </div>

      <div class="sponsors-grid" aria-label="Partners">
        <?php foreach ($partnersList as $pt): ?>
          <?php if ($pt['website']): ?>
            <a href="<?= htmlspecialchars($pt['website'], ENT_QUOTES) ?>" target="_blank" class="partner-tile" style="text-decoration: none; color: inherit;">
          <?php else: ?>
            <div class="partner-tile">
          <?php endif; ?>

            <?php if ($pt['logo']): ?>
              <img class="partner-icon" src="<?= htmlspecialchars($pt['logo'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($pt['name'], ENT_QUOTES) ?> logo">
            <?php else: ?>
              <svg class="partner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="margin: 0 auto 12px; display: block; color: var(--accent);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <?php endif; ?>
            <div class="partner-name"><?= htmlspecialchars($pt['name'], ENT_QUOTES) ?></div>

          <?php if ($pt['website']): ?>
            </a>
          <?php else: ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
  </div>
</section>

  <!-- FOOTER -->
  <footer class="site-footer" role="contentinfo">
    <div class="footer-wrap">
      <div class="footer-brand">
        <div class="nav-logo-icon" style="width:48px;height:48px;border-radius:8px;">
          <img src="club-logo.png" alt="KUET BEC Logo" style="width:100%;height:100%;object-fit:cover;" />
        </div>
        <p class="footer-desc">Empowering the next generation of business leaders, innovators, and entrepreneurs at Khulna University of Engineering &amp; Technology since 2010.</p>
        <div class="footer-socials">
          <a href="#" aria-label="Facebook" class="social">f</a>
          <a href="#" aria-label="Instagram" class="social">ig</a>
          <a href="#" aria-label="LinkedIn" class="social">in</a>
          <a href="#" aria-label="YouTube" class="social">yt</a>
        </div>
      </div>

      <div class="footer-col">
        <h4>Navigation</h4>
        <ul>
          <li><a href="#about">About Us</a></li>
          <li><a href="#events">Events</a></li>
          <li><a href="#achievements">Achievements</a></li>
          <li><a href="#gallery">Gallery</a></li>
          <li><a href="#sponsors">Sponsors</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Programs</h4>
        <ul>
          <li>Mentorship</li>
          <li>Workshops</li>
          <li>Case Competitions</li>
          <li>Startup Incubator</li>
          <li>Annual Summit</li>
        </ul>
      </div>

      <div class="footer-col contact">
        <h4>Contact</h4>
        <p>Khulna University of Engineering &amp; Technology, Khulna-9203, Bangladesh</p>
        <p><a href="mailto:bec@kuet.ac.bd">bec@kuet.ac.bd</a></p>
        <p>+880 1700-000000</p>
      </div>
    </div>
    <div class="footer-bottom">© 2026 Business & Entrepreneurship Club, KUET. All rights reserved.</div>
  </footer>
<div class="marquee-strip">
  <div class="marquee-track" id="marqueeTrack">
    <!-- filled by JS -->
  </div>
</div>

<script>
  // ── LOADER ──
  // Wait for full page load, then fade out the loader for a smoother first impression.
  window.addEventListener('load', () => {
    setTimeout(() => {
      document.getElementById('loader').classList.add('hidden');
    }, 900);
  });

  // ── NAVBAR SCROLL ──
  const navbar = document.getElementById('navbar');
  // Add a glassy background once user scrolls down so links stay readable on the hero image.
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  });

  // ── HAMBURGER ──
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  // Toggle both icon animation and mobile menu visibility with one click.
  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('open');
    mobileMenu.classList.toggle('open');
  });
  // Close mobile menu automatically after selecting any menu item.
  mobileMenu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      hamburger.classList.remove('open');
      mobileMenu.classList.remove('open');
    });
  });

  // ── MARQUEE ──
  const items = ['Case Competition', 'Tech Fest', 'Workshop Series', 'Networking Night', 'Best Club 2026', 'Innovation Summit', 'Pitch Battle'];
  const track = document.getElementById('marqueeTrack');
  // Duplicate the list several times so the CSS marquee can loop continuously without gaps.
  const doubled = [...items, ...items, ...items, ...items];
  doubled.forEach(t => {
    track.innerHTML += `<span class="marquee-item">${t}<span class="marquee-dot"></span></span>`;
  });

  // ── ANIMATED COUNTERS ──
  // Counts from 0 to target using an ease-out curve for a polished stat reveal.
  function animateCounter(el, target, duration = 1800) {
    let start = null;
    const step = ts => {
      if (!start) start = ts;
      const progress = Math.min((ts - start) / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.floor(ease * target) + (progress === 1 ? '+' : '');
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        // Start each counter only when it becomes visible on screen.
        const target = parseInt(e.target.dataset.target);
        animateCounter(e.target, target);
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('[data-target]').forEach(el => observer.observe(el));

  // ── JOIN REGISTRATION ──
  const openRegistrationBtn = document.getElementById('openRegistrationBtn');
  const registrationPanel = document.getElementById('registrationPanel');
  const registrationForm = document.getElementById('registrationForm');
  const formStatus = document.getElementById('formStatus');

  // Expand or collapse the registration panel from the Join Us CTA button.
  if (openRegistrationBtn && registrationPanel && registrationForm) {
    openRegistrationBtn.addEventListener('click', () => {
      const isHidden = registrationPanel.hasAttribute('hidden');
      if (isHidden) {
        registrationPanel.removeAttribute('hidden');
        openRegistrationBtn.setAttribute('aria-expanded', 'true');
        openRegistrationBtn.textContent = 'Close Registration';
        if (formStatus) {
          formStatus.textContent = '';
          formStatus.className = 'form-status';
        }
        registrationForm.querySelector('input, select, textarea').focus();
      } else {
        registrationPanel.setAttribute('hidden', '');
        openRegistrationBtn.setAttribute('aria-expanded', 'false');
        openRegistrationBtn.textContent = 'Register Now';
      }
    });
  }

  // Validate required fields and show immediate confirmation feedback after submit.
  if (registrationForm) {
    registrationForm.addEventListener('submit', e => {
      e.preventDefault();

      if (!registrationForm.checkValidity()) {
        registrationForm.reportValidity();
        if (formStatus) {
          formStatus.textContent = 'Please complete all required fields before submitting.';
          formStatus.className = 'form-status error';
        }
        return;
      }

      const data = new FormData(registrationForm);
      const name = (data.get('name') || '').toString().trim();

      if (formStatus) {
        formStatus.textContent = `Thanks${name ? `, ${name}` : ''}! Your registration has been submitted.`;
        formStatus.className = 'form-status success';
      }
      registrationForm.reset();
    });
  }

  // ── UPCOMING EVENTS CALENDAR ──
  const EVENT_SEED = [
    {
      id: 'nexus-case-challenge-2026',
      title: 'NEXUS National Case Challenge 2026',
      type: 'Case Competition',
      start: '2026-05-16',
      end: '2026-05-17',
      deadline: '2026-05-12',
      venue: 'KUET Auditorium',
      summary: 'Inter-university business case competition focused on strategy, analysis, and presentation.',
      capacity: 180
    },
    {
      id: 'innovate-tech-fest-2026',
      title: 'InnovateTech Fest 2026',
      type: 'Tech Fest',
      start: '2026-06-05',
      end: '2026-06-07',
      deadline: '2026-06-01',
      venue: 'KUET Campus',
      summary: 'A three-day festival of startup pitches, hackathons, product showcases, and founder talks.',
      capacity: 240
    },
    {
      id: 'tdexkuet-ideas-leadership-session',
      title: 'TDExKUET Ideas & Leadership Session',
      type: 'Talk',
      start: '2026-07-10',
      end: '2026-07-10',
      deadline: '2026-07-04',
      venue: 'ECE Building, Room 204',
      summary: 'A curated leadership session featuring inspiring speakers and practical ideas for students.',
      capacity: 120
    },
    {
      id: 'kbec-entrepreneurship-summit-2026',
      title: 'KBEC Entrepreneurship Summit 2026',
      type: 'Summit',
      start: '2026-08-20',
      end: '2026-08-21',
      deadline: '2026-08-13',
      venue: 'KUET Gymnasium',
      summary: 'Our flagship summit bringing together entrepreneurs, investors, and thought leaders.',
      capacity: 300
    }
  ];

  let EVENT_DATA = [...EVENT_SEED];
  let MEMBER_EVENT_TICKETS = [];

  const calendarGrid = document.getElementById('calendarGrid');
  const calendarMonthLabel = document.getElementById('calendarMonthLabel');
  const calendarPrevBtn = document.getElementById('calendarPrevBtn');
  const calendarNextBtn = document.getElementById('calendarNextBtn');
  const deadlineList = document.getElementById('deadlineList');
  const selectedDateLabel = document.getElementById('selectedDateLabel');
  const selectedDateEvents = document.getElementById('selectedDateEvents');
  const eventCountValue = document.getElementById('eventCountValue');
  const eventRegistrationPanel = document.getElementById('eventRegistrationPanel');
  const eventRegistrationForm = document.getElementById('eventRegistrationForm');
  const eventFormStatus = document.getElementById('eventFormStatus');
  const eventSelect = document.getElementById('eventSelect');
  const eventTicketPanel = document.getElementById('eventTicketPanel');
  const eventTicketTitle = document.getElementById('eventTicketTitle');
  const eventTicketMeta = document.getElementById('eventTicketMeta');
  const eventTicketStatus = document.getElementById('eventTicketStatus');
  const eventTicketQr = document.getElementById('eventTicketQr');
  const copyTicketLinkBtn = document.getElementById('copyTicketLinkBtn');
  const closeTicketPanelBtn = document.getElementById('closeTicketPanelBtn');
  const memberTicketList = document.getElementById('memberTicketList');

  const calendarState = {
    today: new Date(),
    view: new Date(),
    selected: new Date()
  };
  calendarState.today.setHours(12, 0, 0, 0);
  calendarState.view = new Date(calendarState.today.getFullYear(), calendarState.today.getMonth(), 1);
  calendarState.selected = new Date(calendarState.today);

  function toNoonDate(value) {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day, 12, 0, 0, 0);
  }

  function isSameDay(left, right) {
    return left.getFullYear() === right.getFullYear()
      && left.getMonth() === right.getMonth()
      && left.getDate() === right.getDate();
  }

  function isBeforeDay(left, right) {
    const compareLeft = new Date(left.getFullYear(), left.getMonth(), left.getDate());
    const compareRight = new Date(right.getFullYear(), right.getMonth(), right.getDate());
    return compareLeft < compareRight;
  }

  function formatLongDate(date) {
    return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function formatShortDate(date) {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }

  function getEventStatus(eventItem, registeredCount) {
    const deadlineDate = toNoonDate(eventItem.deadline);
    const today = new Date();
    today.setHours(12, 0, 0, 0);
    const capacity = Number(eventItem.capacity || 0);
    const remainingSeats = Math.max(capacity - registeredCount, 0);
    const registrationClosed = isBeforeDay(deadlineDate, today) || isBeforeDay(toNoonDate(eventItem.end), today);

    return {
      remainingSeats,
      registeredCount,
      isFull: remainingSeats <= 0,
      registrationClosed,
      status: remainingSeats <= 0 ? 'Full' : (registrationClosed ? 'Closed' : 'Open')
    };
  }

  function getEventByKey(eventKey) {
    return EVENT_DATA.find(item => item.id === eventKey || item.title === eventKey) || null;
  }

  function buildTicketLabel(ticket) {
    if (!ticket) return 'No ticket yet';
    return ticket.attendedAt ? 'Attended' : 'Booked';
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: {
        ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
        ...(options.headers || {})
      }
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok) {
      const error = new Error(payload.message || 'Request failed');
      error.status = response.status;
      error.payload = payload;
      throw error;
    }

    return payload;
  }

  function setEventTicketPanel(ticket) {
    if (!ticket) {
      eventTicketPanel.setAttribute('hidden', '');
      eventTicketQr.removeAttribute('src');
      eventTicketQr.removeAttribute('data-check-in-url');
      return;
    }

    eventTicketPanel.removeAttribute('hidden');
    eventTicketTitle.textContent = ticket.title;
    eventTicketMeta.textContent = `${ticket.ticketCode} • ${ticket.type} • ${ticket.venue}`;
    eventTicketStatus.textContent = `${buildTicketLabel(ticket)} • ${ticket.attendedAt ? `Checked in ${new Date(ticket.attendedAt).toLocaleString()}` : 'Show this QR at the venue.'}`;
    eventTicketQr.src = ticket.qrDataUrl;
    eventTicketQr.alt = `${ticket.title} ticket QR code`;
    eventTicketQr.dataset.checkInUrl = ticket.checkInUrl || '';
    eventTicketPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function renderMemberTickets() {
    if (!memberTicketList) return;

    if (!MEMBER_EVENT_TICKETS.length) {
      memberTicketList.innerHTML = '<p class="member-dashboard-copy">No event tickets yet. Register for an event and your ticket will appear here.</p>';
      return;
    }

    memberTicketList.innerHTML = MEMBER_EVENT_TICKETS.map(ticket => `
      <article class="member-ticket-card">
        <div>
          <p class="members-eyebrow">Event Ticket</p>
          <h4>${ticket.title}</h4>
        </div>
        <p class="member-ticket-meta">${ticket.ticketCode} • ${ticket.type} • ${ticket.venue}</p>
        <p class="member-ticket-meta">${ticket.start} - ${ticket.end}</p>
        <span class="member-ticket-status${ticket.attendedAt ? ' attended' : ''}">${ticket.attendedAt ? 'Attendance Marked' : 'Awaiting Check-in'}</span>
        <button type="button" class="event-register-btn" data-open-ticket="${ticket.ticketCode}">View QR Ticket</button>
      </article>
    `).join('');
  }

  async function loadEventCatalog() {
    try {
      const payload = await requestJson('/kbec/api/events.php');
      EVENT_DATA = Array.isArray(payload.events) && payload.events.length 
        ? payload.events.map(ev => ({
            id: ev.id,
            title: ev.title,
            type: ev.type,
            start: ev.event_date_start || ev.start,
            end: ev.event_date_end || ev.end,
            deadline: ev.registration_deadline || ev.deadline,
            venue: ev.location || ev.venue,
            summary: ev.description || ev.summary,
            capacity: Number(ev.capacity),
            remainingSeats: typeof ev.remaining_seats === 'number' ? Number(ev.remaining_seats) : undefined,
            registeredCount: typeof ev.registered_count === 'number' ? Number(ev.registered_count) : undefined,
            status: ev.status
          }))
        : [...EVENT_SEED];
      MEMBER_EVENT_TICKETS = Array.isArray(payload.myTickets) 
        ? payload.myTickets.map(t => ({
            id: t.id,
            ticketCode: t.ticket_code,
            ticketToken: t.ticket_token,
            note: t.note,
            registeredAt: t.registered_at,
            attendedAt: t.attended_at,
            title: t.event_title,
            slug: t.slug,
            type: t.type,
            venue: t.location || t.venue,
            start: t.event_date_start || t.start,
            end: t.event_date_end || t.end
          }))
        : [];
    } catch (error) {
      console.error('API load error, falling back to seed:', error);
      EVENT_DATA = [...EVENT_SEED];
      MEMBER_EVENT_TICKETS = [];
    }

    try {
      populateEventSelect();
      renderCalendar();
      renderMemberTickets();
    } catch (error) {
      console.error('Calendar rendering crashed:', error);
      if (calendarGrid) {
        calendarGrid.innerHTML = `<div style="grid-column: 1 / -1; padding: 20px; color: red; background: #ffebee; border-radius: 8px; font-family: sans-serif; font-size: 14px; line-height: 1.5;"><strong>Calendar Render Error:</strong> ${error.message}<br><small style="display:block; margin-top: 10px; font-family: monospace; white-space: pre-wrap;">${error.stack}</small></div>`;
      }
    }
  }

  async function loadMemberProfileIntoEventForm() {
    try {
      const result = await requestJson('/kbec/api/me.php');
      const member = result.member || {};
      const fields = {
        name: document.getElementById('eventRegName'),
        email: document.getElementById('eventRegEmail'),
        phone: document.getElementById('eventRegPhone'),
        department: document.getElementById('eventRegDept'),
        batch: document.getElementById('eventRegBatch')
      };

      if (fields.name) fields.name.value = member.name || '';
      if (fields.email) fields.email.value = member.email || '';
      if (fields.phone) fields.phone.value = member.phone || '';
      if (fields.department) fields.department.value = member.department || '';
      if (fields.batch) fields.batch.value = member.batch || '';
    } catch (error) {
      const fields = [
        document.getElementById('eventRegName'),
        document.getElementById('eventRegEmail'),
        document.getElementById('eventRegPhone'),
        document.getElementById('eventRegDept'),
        document.getElementById('eventRegBatch')
      ];
      fields.forEach(field => {
        if (field) field.value = '';
      });
    }
  }

  window.addEventListener('kbec:member-changed', () => {
    loadMemberProfileIntoEventForm();
    loadEventCatalog();
  });

  function getEventDateRange(eventItem) {
    return {
      start: toNoonDate(eventItem.start),
      end: toNoonDate(eventItem.end),
      deadline: toNoonDate(eventItem.deadline)
    };
  }

  function getEventsForDay(date) {
    return EVENT_DATA.filter(eventItem => {
      const range = getEventDateRange(eventItem);
      return date >= range.start && date <= range.end;
    });
  }

  function getDeadlinesForDay(date) {
    return EVENT_DATA.filter(eventItem => isSameDay(date, getEventDateRange(eventItem).deadline));
  }

  function getUpcomingDeadlines() {
    return EVENT_DATA
      .map(eventItem => ({ ...eventItem, deadlineDate: getEventDateRange(eventItem).deadline }))
      .filter(eventItem => !isBeforeDay(eventItem.deadlineDate, calendarState.today))
      .sort((left, right) => left.deadlineDate - right.deadlineDate);
  }

  function populateEventSelect() {
    eventSelect.innerHTML = '<option value="">Select your event</option>';
    EVENT_DATA.forEach(eventItem => {
      const option = document.createElement('option');
      option.value = eventItem.id || eventItem.title;
      option.textContent = `${eventItem.title} (${eventItem.status || 'Open'}${typeof eventItem.remainingSeats === 'number' ? `, ${eventItem.remainingSeats} seats left` : ''})`;
      eventSelect.appendChild(option);
    });
  }

  function openEventRegistration(eventName = '') {
    eventRegistrationPanel.removeAttribute('hidden');
    if (eventName) {
      const selectedEvent = getEventByKey(eventName);
      eventSelect.value = selectedEvent ? selectedEvent.id : eventName;
    }
    eventFormStatus.textContent = '';
    eventFormStatus.className = 'event-form-status';
    eventRegistrationPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => {
      eventRegistrationForm.querySelector('input, select, textarea')?.focus();
    }, 120);
  }

  function renderDeadlineList() {
    const upcoming = getUpcomingDeadlines().slice(0, 4);
    eventCountValue.textContent = String(upcoming.length);

    if (!upcoming.length) {
      deadlineList.innerHTML = '<p style="color:rgba(0,0,0,.45);font-size:13px;padding:8px 0">No upcoming deadlines scheduled.</p>';
      return;
    }

    deadlineList.innerHTML = upcoming.map(eventItem => `
      <article class="deadline-card">
        <div class="deadline-card-top">
          <div>
            <p class="deadline-card-type">${eventItem.type}</p>
            <h4>${eventItem.title}</h4>
          </div>
          <span class="deadline-date-chip">${formatShortDate(eventItem.deadlineDate)}</span>
        </div>
        <p class="deadline-card-desc">${eventItem.summary}</p>
        <p class="deadline-card-meta">Event dates: ${formatLongDate(toNoonDate(eventItem.start))}${eventItem.start !== eventItem.end ? ` \u2013 ${formatLongDate(toNoonDate(eventItem.end))}` : ''}<br>Venue: ${eventItem.venue}<br>Seats: ${typeof eventItem.remainingSeats === 'number' ? eventItem.remainingSeats : '-'} left of ${eventItem.capacity || '-'}</p>
        <button type="button" class="deadline-register-btn" data-event-title="${eventItem.id || eventItem.title}"${eventItem.status === 'Full' || eventItem.status === 'Closed' ? ' disabled' : ''}>${eventItem.status === 'Full' ? 'Sold Out' : (eventItem.status === 'Closed' ? 'Registration Closed' : 'Register for This Event')}</button>
      </article>
    `).join('');
  }

  function renderSelectedDatePanel() {
    selectedDateLabel.textContent = formatLongDate(calendarState.selected);
    const events = getEventsForDay(calendarState.selected);
    const deadlines = getDeadlinesForDay(calendarState.selected);

    if (!events.length && !deadlines.length) {
      selectedDateEvents.innerHTML = '<div class="deadline-spotlight-item">No events or deadlines fall on this date. Pick another day to inspect the calendar.</div>';
      return;
    }

    selectedDateEvents.innerHTML = '';

    events.forEach(eventItem => {
      const item = document.createElement('div');
      item.className = 'deadline-spotlight-item';
      item.innerHTML = `<strong>${eventItem.title}</strong><br>${eventItem.type} | ${eventItem.venue}<br>${formatLongDate(toNoonDate(eventItem.start))}${eventItem.start !== eventItem.end ? ` - ${formatLongDate(toNoonDate(eventItem.end))}` : ''}<br><button type="button" class="event-register-btn" data-event-title="${eventItem.id || eventItem.title}" style="margin-top:10px"${eventItem.status === 'Full' || eventItem.status === 'Closed' ? ' disabled' : ''}>${eventItem.status === 'Full' ? 'Sold Out' : (eventItem.status === 'Closed' ? 'Closed' : 'Register')}</button>`;
      selectedDateEvents.appendChild(item);
    });

    deadlines.forEach(eventItem => {
      const item = document.createElement('div');
      item.className = 'deadline-spotlight-item';
      item.innerHTML = `<strong>Deadline: ${eventItem.title}</strong><br>${eventItem.type} | ${formatLongDate(getEventDateRange(eventItem).deadline)}<br>Venue: ${eventItem.venue}`;
      selectedDateEvents.appendChild(item);
    });
  }

  function renderCalendar() {
    const year = calendarState.view.getFullYear();
    const month = calendarState.view.getMonth();
    const firstDay = new Date(year, month, 1, 12, 0, 0, 0);
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    calendarMonthLabel.textContent = firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const cells = [];
    for (let index = 0; index < firstDay.getDay(); index += 1) {
      cells.push('<div class="calendar-empty" aria-hidden="true"></div>');
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      const current = new Date(year, month, day, 12, 0, 0, 0);
      const events = getEventsForDay(current);
      const deadlines = getDeadlinesForDay(current);
      const badges = [];

      if (events.length) {
        badges.push(`<span class="calendar-badge event">${events.length} event${events.length > 1 ? 's' : ''}</span>`);
      }

      if (deadlines.length) {
        badges.push(`<span class="calendar-badge deadline">${deadlines.length} deadline${deadlines.length > 1 ? 's' : ''}</span>`);
      }

      const classes = ['calendar-cell'];
      if (isSameDay(current, calendarState.today)) {
        classes.push('is-today');
      }
      if (isSameDay(current, calendarState.selected)) {
        classes.push('is-selected');
      }
      if (isBeforeDay(current, calendarState.today)) {
        classes.push('is-past');
      }

      cells.push(`
        <button type="button" class="${classes.join(' ')}" data-date="${current.toISOString().slice(0, 10)}">
          <span class="calendar-day-number">${day}</span>
          <div class="calendar-badges">${badges.join('')}</div>
        </button>
      `);
    }

    calendarGrid.innerHTML = cells.join('');
    renderDeadlineList();
    renderSelectedDatePanel();
  }

  calendarPrevBtn.addEventListener('click', () => {
    calendarState.view = new Date(calendarState.view.getFullYear(), calendarState.view.getMonth() - 1, 1);
    renderCalendar();
  });

  calendarNextBtn.addEventListener('click', () => {
    calendarState.view = new Date(calendarState.view.getFullYear(), calendarState.view.getMonth() + 1, 1);
    renderCalendar();
  });

  calendarGrid.addEventListener('click', event => {
    const cell = event.target.closest('.calendar-cell');
    if (!cell) {
      return;
    }

    calendarState.selected = toNoonDate(cell.dataset.date);
    renderCalendar();
  });

  document.addEventListener('click', event => {
    const button = event.target.closest('.event-register-btn, .deadline-register-btn');
    if (!button) {
      return;
    }

    openEventRegistration(button.dataset.eventTitle || '');
  });

  eventRegistrationForm.addEventListener('submit', event => {
    event.preventDefault();

    if (!eventRegistrationForm.checkValidity()) {
      eventRegistrationForm.reportValidity();
      eventFormStatus.textContent = 'Please complete all required fields before submitting.';
      eventFormStatus.className = 'event-form-status error';
      return;
    }

    const data = new FormData(eventRegistrationForm);
    const eventId = (data.get('event') || '').toString().trim();
    const name = (data.get('name') || '').toString().trim();
    const email = (data.get('email') || '').toString().trim();
    const phone = (data.get('phone') || '').toString().trim();
    const department = (data.get('department') || '').toString().trim();
    const batch = (data.get('batch') || '').toString().trim();
    const note = (data.get('note') || '').toString().trim();

    eventFormStatus.textContent = 'Registering your seat...';
    eventFormStatus.className = 'event-form-status';

    requestJson('/kbec/api/register_event.php', {
      method: 'POST',
      body: JSON.stringify({
        event_id: eventId,
        name: name,
        email: email,
        phone: phone,
        department: department,
        batch: batch,
        note: note
      })
    })
      .then(result => {
        const ticket = result.ticket;
        eventFormStatus.innerHTML = `${result.message || 'Registration confirmed.'} ${ticket ? '<a href="#eventTicketPanel">View ticket</a>' : ''}`;
        eventFormStatus.className = 'event-form-status success';
        eventRegistrationForm.reset();
        if (ticket) {
          setEventTicketPanel(ticket);
          if (MEMBER_EVENT_TICKETS.find(item => item.ticketCode === ticket.ticketCode)) {
            MEMBER_EVENT_TICKETS = MEMBER_EVENT_TICKETS.map(item => item.ticketCode === ticket.ticketCode ? ticket : item);
          } else {
            MEMBER_EVENT_TICKETS.unshift(ticket);
          }
          renderMemberTickets();
          loadEventCatalog().catch(() => {});
        }
      })
      .catch(error => {
        eventFormStatus.textContent = error.message || 'Registration failed.';
        eventFormStatus.className = 'event-form-status error';
      });
  });

  if (closeTicketPanelBtn) {
    closeTicketPanelBtn.addEventListener('click', () => setEventTicketPanel(null));
  }

  if (copyTicketLinkBtn) {
    copyTicketLinkBtn.addEventListener('click', async () => {
      const checkInUrl = eventTicketQr.dataset.checkInUrl || '';
      if (!checkInUrl) return;
      try {
        await navigator.clipboard.writeText(checkInUrl);
        eventFormStatus.textContent = 'Check-in link copied to clipboard.';
        eventFormStatus.className = 'event-form-status success';
      } catch (error) {
        eventFormStatus.textContent = 'Could not copy the link. Use the QR code instead.';
        eventFormStatus.className = 'event-form-status error';
      }
    });
  }

  document.addEventListener('click', event => {
    const ticketButton = event.target.closest('[data-open-ticket]');
    if (!ticketButton) return;
    const ticket = MEMBER_EVENT_TICKETS.find(item => item.ticketCode === ticketButton.dataset.openTicket);
    if (ticket) setEventTicketPanel(ticket);
  });

  console.log("Calendar JS initializing...");
  if (calendarGrid) {
    calendarGrid.innerHTML = '<div style="grid-column: 1 / -1; padding: 20px; color: var(--accent-light); font-size: 14px; text-align: center;">Loading events and schedule...</div>';
  }
  loadMemberProfileIntoEventForm();
  loadEventCatalog();

  // ── OPPORTUNITY BOARD FILTERS ──
  const opportunityFilters = document.getElementById('opportunityFilters');
  const opportunityCards = Array.from(document.querySelectorAll('.opportunity-card'));
  if (opportunityFilters) {
    opportunityFilters.addEventListener('click', event => {
      const button = event.target.closest('.opportunity-filter');
      if (!button) {
        return;
      }

      const filter = button.dataset.filter;
      opportunityFilters.querySelectorAll('.opportunity-filter').forEach(filterButton => {
        filterButton.classList.toggle('is-active', filterButton === button);
      });

      opportunityCards.forEach(card => {
        card.hidden = !(filter === 'all' || card.dataset.category === filter);
      });
    });
  }

  // ── TEAM / COMMITTEE – HIERARCHY LAYOUT ──

  // Position hierarchy in order (top to bottom)
  const POSITION_HIERARCHY = [
    'President',
    'General Secretary',
    'Senior Executive Vice President',
    'Senior Vice President',
    'Vice President (Internal Affairs)',
    'Vice President (External Affairs)',
    'Vice President (Operations)',
    'Vice President (Marketing & Branding)',
    'Treasurer',
    'Organizing Secretary',
    'Joint Secretary',
    'Secretary of Human Resources',
    'Secretary of Public Relations',
    'Secretary of Operations',
    'Assistant General Secretary',
    'Assistant Organizing Secretary',
    'Assistant Joint Secretary',
    'Assistant Operations Secretary',
    'Assistant Secretary of Corporate Alliance',
    'Assistant Secretary of Public Relations',
    'Assistant Secretary of Human Resource',
    'Assistant Secretary of Finance',
    'Assistant Secretary of Logistics',
  ];
  // Team member data – loaded dynamically from the database
  <?php
  $bioLookup = [
      'Nawaf Md Ittamum' => 'Leads club strategy, partnerships, and committee coordination with a focus on student-led innovation.',
      'Hisham Hafiz' => 'Coordinates committee communication, member onboarding, and internal documentation.',
      'Naich Naznafi' => 'Bridges senior leadership with operational teams to drive club-wide execution.',
      'Prince Al Mahmood Aalif' => 'Oversees cross-departmental coordination and supports the President in strategic initiatives.',
      'Awsaf Sinan' => 'Supports the executive leadership in driving club-wide strategic programs.',
      'Arka Braja Prasad Nath' => 'Manages internal committee relations, HR, and member welfare.',
      'Farhan Anjum Plabon' => 'Handles external partnerships, sponsorships, and inter-club relations.',
      'Zihad Hossen Sazal' => 'Oversees event logistics, operational planning, and execution.',
      'Tasnim Isha' => 'Leads visual identity, campaigns, and brand communications.',
      'Rhydita Farnaz' => 'Manages club finances, budgets, and financial reporting.',
      'Abdul Jaher' => 'Plans and coordinates club events, activities, and organizational logistics.'
  ];
  $mappedTeam = array_map(function($m) use ($bioLookup) {
      return [
          'name' => $m['name'],
          'position' => $m['position'],
          'department' => $m['department'] . ($m['batch'] ? ' (' . $m['batch'] . ')' : ''),
          'bio' => $bioLookup[$m['name']] ?? ($m['position'] . ' of Business & Entrepreneurship Club, KUET.'),
          'photo' => $m['image'] ? $m['image'] : null,
          'instagram' => '#',
          'facebook' => '#',
          'linkedin' => $m['linkedin'] ?: '#'
      ];
  }, $__team);
  ?>
  const TEAM_DATA = <?= json_encode($mappedTeam) ?>;

  // Build hierarchy container
  const teamHierarchy = document.getElementById('teamHierarchy');

  function getInitials(name) {
    return (name || '').split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase() || 'KB';
  }

  function buildMemberCard(member) {
    const initials = getInitials(member.name);
    const card = document.createElement('article');
    card.className = 'member-card';
    card.dataset.name = member.name;
    card.dataset.role = member.position;
    card.dataset.departmentLabel = member.department;
    card.dataset.bio = member.bio;
    card.dataset.instagram = member.instagram || '#';
    card.dataset.facebook = member.facebook || '#';
    card.dataset.linkedin = member.linkedin || '#';
    if (member.photo) card.dataset.photo = member.photo;

    card.innerHTML = `
      <div class="member-photo-wrap">
        ${member.photo
          ? `<img src="${member.photo}" alt="${member.name}" loading="lazy" />`
          : `<div class="member-avatar-dark">${initials}</div>`
        }
        <div class="member-info-overlay">
          <span class="member-role-label">${member.position}</span>
          <h3>${member.name}</h3>
        </div>
      </div>
      <div class="member-card-footer">
        <button type="button" class="member-view-btn">View Profile</button>
        <div class="member-socials">
          <a href="${member.instagram || '#'}" aria-label="Instagram" target="_blank" rel="noopener">ig</a>
          <a href="${member.facebook || '#'}" aria-label="Facebook" target="_blank" rel="noopener">fb</a>
          <a href="${member.linkedin || '#'}" aria-label="LinkedIn" target="_blank" rel="noopener">in</a>
        </div>
      </div>
    `;
    return card;
  }

  // Group members by position preserving hierarchy order
  function renderHierarchy() {
    teamHierarchy.innerHTML = '';
    const grouped = {};
    TEAM_DATA.forEach(member => {
      if (!grouped[member.position]) grouped[member.position] = [];
      grouped[member.position].push(member);
    });

    POSITION_HIERARCHY.forEach(position => {
      const members = grouped[position];
      if (!members || members.length === 0) return; // skip positions with no members

      const row = document.createElement('div');
      row.className = 'position-row';

      const label = document.createElement('div');
      label.className = 'position-label';
      label.textContent = position;
      row.appendChild(label);

      const cards = document.createElement('div');
      cards.className = 'position-cards';

      members.forEach(member => {
        cards.appendChild(buildMemberCard(member));
      });

      row.appendChild(cards);
      teamHierarchy.appendChild(row);
    });
  }

  renderHierarchy();

  // ── TEAM MODAL ──
  const teamModal = document.createElement('div');
  teamModal.id = 'teamModal';
  teamModal.hidden = true;
  teamModal.setAttribute('aria-hidden', 'true');
  teamModal.innerHTML = `
    <div class="team-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="teamModalTitle">
      <div class="team-modal" id="teamDialog">
        <button type="button" class="close-btn" id="closeTeamModalBtn" aria-label="Close team profile">×</button>
        <div class="team-modal-grid">
          <div class="team-modal-avatar" id="teamModalAvatar">KB</div>
          <div>
            <span class="modal-role" id="teamModalRole">Role</span>
            <h3 id="teamModalTitle">Member Name</h3>
            <p id="teamModalDept" style="color:rgba(201,168,76,0.7);font-size:13px;margin-bottom:12px;"></p>
            <p id="teamModalBio">Bio</p>
            <div class="team-modal-links">
              <a href="#" id="teamModalInstagram" target="_blank" rel="noopener">Instagram</a>
              <a href="#" id="teamModalFacebook" target="_blank" rel="noopener">Facebook</a>
              <a href="#" id="teamModalLinkedIn" target="_blank" rel="noopener">LinkedIn</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(teamModal);

  const closeTeamModalBtn = document.getElementById('closeTeamModalBtn');
  const teamModalAvatar = document.getElementById('teamModalAvatar');
  const teamModalRole = document.getElementById('teamModalRole');
  const teamModalTitle = document.getElementById('teamModalTitle');
  const teamModalDept = document.getElementById('teamModalDept');
  const teamModalBio = document.getElementById('teamModalBio');
  const teamModalInstagram = document.getElementById('teamModalInstagram');
  const teamModalFacebook = document.getElementById('teamModalFacebook');
  const teamModalLinkedIn = document.getElementById('teamModalLinkedIn');

  function openTeamModal(card) {
    const name = card.dataset.name || '';
    const role = card.dataset.role || '';
    const department = card.dataset.departmentLabel || '';
    const bio = card.dataset.bio || '';
    const initials = getInitials(name);
    const photo = card.dataset.photo || '';

    if (photo) {
      teamModalAvatar.innerHTML = `<img src="${photo}" alt="${name}" />`;
    } else {
      teamModalAvatar.textContent = initials;
    }
    teamModalRole.textContent = role;
    teamModalTitle.textContent = name;
    teamModalDept.textContent = department;
    teamModalBio.textContent = bio;
    teamModalInstagram.href = card.dataset.instagram || '#';
    teamModalFacebook.href = card.dataset.facebook || '#';
    teamModalLinkedIn.href = card.dataset.linkedin || '#';

    teamModal.removeAttribute('hidden');
    teamModal.setAttribute('aria-hidden', 'false');
    setTimeout(() => closeTeamModalBtn.focus(), 50);
  }

  function closeTeamModal() {
    teamModal.setAttribute('hidden', '');
    teamModal.setAttribute('aria-hidden', 'true');
  }

  document.addEventListener('click', event => {
    const viewButton = event.target.closest('.member-view-btn');
    if (viewButton) {
      const card = viewButton.closest('.member-card');
      if (card) openTeamModal(card);
      return;
    }
    if (event.target.classList.contains('team-modal-backdrop')) {
      closeTeamModal();
    }
  });

  closeTeamModalBtn.addEventListener('click', closeTeamModal);

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !teamModal.hasAttribute('hidden')) {
      closeTeamModal();
    }
  });

</script>
<!-- Suggestion / Complaint modal + floating button -->
<style id="feedback-styles">
  .feedback-fab { position: fixed; right: 24px; bottom: 36px; z-index: 1400; background: var(--accent); color: #fff; border: none; border-radius: 20px; width:80px; height:80px; display:flex; align-items:center; justify-content:center; font-size:28px; box-shadow:0 18px 40px rgba(2,6,23,0.22); cursor:pointer; transition: transform 0.18s ease, box-shadow 0.18s ease; }
  .feedback-fab:hover{ transform: translateY(-4px) scale(1.03); box-shadow:0 28px 60px rgba(2,6,23,0.28); }
  .feedback-fab:focus { outline: 4px solid rgba(0,123,255,0.18); }
  .feedback-modal-backdrop { position: fixed; inset:0; background: rgba(10,12,16,0.48); display:flex; align-items:center; justify-content:center; z-index:1250; }
  .feedback-modal { width: min(980px, 94%); max-height: 92vh; overflow:auto; background:#fff; border-radius:12px; padding:26px; box-shadow:0 30px 80px rgba(15,23,42,0.18); border: 1px solid rgba(2,6,23,0.04); position:relative; }
  .feedback-modal::before{ content:''; position:absolute; left:0; right:0; top:0; height:6px; border-top-left-radius:12px; border-top-right-radius:12px; background: linear-gradient(90deg, var(--accent), #0b6fb8); }
  .feedback-modal h3 { margin:8px 0 8px 0; font-size:30px; letter-spacing:0.02em; color: #0b2540 }
  .feedback-modal h3 .icon{ display:inline-flex; width:38px; height:38px; align-items:center; justify-content:center; background:linear-gradient(135deg,#0b6fb8,#06a6c5); color:#fff; border-radius:8px; font-size:18px; box-shadow:0 8px 24px rgba(11,111,184,0.14) }
  .feedback-row { display:grid; grid-template-columns: repeat(2, 1fr); gap:16px; align-items:start; }
  .feedback-field { margin:8px 0; display:flex; flex-direction:column; }
  .feedback-field label{ font-size:15px; color:#374151; margin-bottom:8px; font-weight:700 }
  .feedback-field label .req { color: #d14343; margin-left:6px; }
  .feedback-field input[type="text"], .feedback-field input[type="email"], .feedback-field select, .feedback-field textarea, .feedback-field input[type="file"] { border:1px solid #e6eef6; padding:14px 16px; border-radius:8px; font-size:15px; background:#fbfdff; box-shadow: inset 0 1px 0 rgba(255,255,255,0.6); }
  .feedback-field input:focus, .feedback-field textarea:focus, .feedback-field select:focus { outline: none; border-color: rgba(11,111,184,0.95); box-shadow: 0 12px 36px rgba(11,111,184,0.12); }
  .feedback-field input[type="text"]::placeholder, .feedback-field input[type="email"]::placeholder, .feedback-field textarea::placeholder { color:#9aa6b2 }
  .feedback-field textarea { min-height:180px; resize:vertical; font-size:15px }
  .file-input-wrapper{ display:flex; gap:12px; align-items:center }
  .file-input-wrapper input[type=file]{ display:none }
  .file-label{ background:#fff; border:1px solid #d1dbe6; padding:8px 12px; border-radius:8px; cursor:pointer; color:#0b3b66; font-weight:600 }
  .file-chosen{ color:#6b7280; font-size:14px }
  .custom-checkbox{ display:flex; gap:10px; align-items:center; cursor:pointer }
  .custom-checkbox input{ width:18px; height:18px; accent-color:#0b6fb8 }
  .feedback-actions { display:flex; gap:12px; justify-content:flex-end; margin-top:18px; }
  .feedback-error { color:#b32; font-size:13px; margin-top:6px; }
  .feedback-note { font-size:13px; color: #6b7280; margin-top:8px; }
  .feedback-modal .close-btn{ position:absolute; right:18px; top:14px; width:34px; height:34px; border-radius:50%; border:none; background:transparent; font-size:18px }
  .feedback-actions button{ padding:10px 14px; border-radius:8px; border:1px solid transparent; }
  #cancelFeedbackBtn{ background:#fff; border:1px solid #d1dbe6; color:#1f2937 }
  #submitFeedbackBtn{ background: linear-gradient(90deg, #0b6fb8, #06a6c5); color:#fff; border:none; font-weight:700; box-shadow:0 14px 36px rgba(11,111,184,0.16); padding:14px 20px; font-size:15px }
  #submitFeedbackBtn:disabled{ opacity:0.6 }
  .feedback-note.privacy{ font-size:12px; color:#6b7280; margin-top:10px }
  @media (max-width:600px){ .feedback-row{flex-direction:column} .feedback-modal{padding:14px} }
</style>

<button class="feedback-fab" id="openFeedbackBtn" aria-label="Open feedback form">✉</button>

<div id="feedbackModal" hidden aria-hidden="true">
  <div class="feedback-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="feedbackTitle">
    <div class="feedback-modal" id="feedbackDialog">
      <button id="closeFeedbackBtn" class="close-btn" aria-label="Close feedback form">×</button>
      <h3 id="feedbackTitle"><span class="icon" aria-hidden="true">✉</span>Suggestion / Complaint</h3>
      <p class="feedback-note">We welcome your feedback. Submissions will be visible to club admins only.</p>
      <form id="feedbackForm">
        <div class="feedback-row">
          <div class="feedback-field" style="flex:1">
            <label>Type <span style="color:#b32">*</span>
              <select name="type" required aria-required="true">
                <option value="">Select type</option>
                <option value="Suggestion">Suggestion</option>
                <option value="Complaint">Complaint</option>
              </select>
            </label>
            <span class="feedback-error" data-for="type"></span>
          </div>
          <div class="feedback-field" style="flex:1">
            <label>Name
              <input name="name" type="text" placeholder="Your name (optional)">
            </label>
            <span class="feedback-error" data-for="name"></span>
          </div>
        </div>

        <div class="feedback-row">
          <div class="feedback-field" style="flex:1">
            <label>Email
              <input name="email" type="email" placeholder="you@example.com">
            </label>
            <span class="feedback-error" data-for="email"></span>
          </div>
          <div class="feedback-field" style="flex:1">
            <label>Subject <span style="color:#b32">*</span>
              <input name="subject" type="text" maxlength="120" required aria-required="true" placeholder="Short summary">
            </label>
            <span class="feedback-error" data-for="subject"></span>
          </div>
        </div>

        <div class="feedback-field">
          <label>Message <span style="color:#b32">*</span>
            <textarea name="message" minlength="10" required aria-required="true" placeholder="Tell us more..."></textarea>
          </label>
          <span class="feedback-error" data-for="message"></span>
        </div>

        <div class="feedback-row">
          <div class="feedback-field" style="flex:1">
            <label>Attachment (jpg/png/pdf, max 3MB)</label>
            <div class="file-input-wrapper">
              <input id="attachment" name="attachment" type="file" accept="image/jpeg,image/png,application/pdf">
              <label for="attachment" class="file-label">Choose file</label>
              <span class="file-chosen">No file chosen</span>
            </div>
            <span class="feedback-error" data-for="attachment"></span>
          </div>
          <div class="feedback-field" style="flex:1">
            <label class="custom-checkbox"><input type="checkbox" name="consent" required><span>I consent to my submission being viewed by club admins.</span> <span style="color:#b32">*</span></label>
            <span class="feedback-error" data-for="consent"></span>
          </div>
        </div>

        <div class="feedback-actions">
          <div id="feedbackStatus" role="status" aria-live="polite" style="align-self:center"></div>
          <button type="button" id="cancelFeedbackBtn">Cancel</button>
          <button type="submit" id="submitFeedbackBtn" style="background:var(--accent);color:#fff;border:none;padding:10px 14px;border-radius:6px;">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 2L11 13" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 2l-7 20  -2-8-8-2 20-10z" stroke="#fff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/></svg>
            <span>Send</span>
          </button>
        </div>
      </form>
      <p class="feedback-note" style="margin-top:8px">Privacy: Submissions are visible only to club administrators and will be used to improve club activities.</p>
    </div>
  </div>
</div>

<script>
  (function(){
    const openBtn = document.getElementById('openFeedbackBtn');
    const modal = document.getElementById('feedbackModal');
    const closeBtn = document.getElementById('closeFeedbackBtn');
    const cancelBtn = document.getElementById('cancelFeedbackBtn');
    const form = document.getElementById('feedbackForm');
    const status = document.getElementById('feedbackStatus');
    const submitBtn = document.getElementById('submitFeedbackBtn');
    const lastKey = 'kbec_last_feedback_time';

    function openModal(){ modal.removeAttribute('hidden'); modal.querySelector('.feedback-modal-backdrop').focus?.(); modal.setAttribute('aria-hidden','false'); setTimeout(()=> form.querySelector('[name=type]').focus(),120); }
    function closeModal(){ modal.setAttribute('hidden',''); modal.setAttribute('aria-hidden','true'); status.textContent=''; clearErrors(); form.reset(); }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (e)=>{ if(e.target.classList.contains('feedback-modal-backdrop')) closeModal(); });

    function clearErrors(){ modal.querySelectorAll('.feedback-error').forEach(el=>el.textContent=''); }

    function showError(field, msg){ const el = modal.querySelector('.feedback-error[data-for="'+field+'"]'); if(el) el.textContent = msg; }

    function validateForm(){ clearErrors(); let ok=true; const data = new FormData(form); const type = data.get('type'); const subject = (data.get('subject')||'').toString().trim(); const message = (data.get('message')||'').toString().trim(); const email = (data.get('email')||'').toString().trim(); const consent = data.get('consent'); if(!type){ showError('type','Please choose Suggestion or Complaint'); ok=false; } if(!subject || subject.length>120){ showError('subject','Subject is required (max 120 chars)'); ok=false; } if(!message || message.length<10){ showError('message','Message must be at least 10 characters'); ok=false; } if(email){ const re=/^[^\s@]+@[^\s@]+\.[^\s@]+$/; if(!re.test(email)){ showError('email','Please enter a valid email'); ok=false; } } if(!consent){ showError('consent','Consent is required'); ok=false; }
      const file = form.querySelector('input[name=attachment]').files[0]; if(file){ const allowed=['image/jpeg','image/png','application/pdf']; if(!allowed.includes(file.type)){ showError('attachment','Allowed types: jpg, png, pdf'); ok=false; } if(file.size>3*1024*1024){ showError('attachment','File must be 3MB or smaller'); ok=false; } }
      // client rate-limit
      const last = localStorage.getItem(lastKey); if(last && (Date.now()-Number(last) < 60*1000)){ showError('type','Please wait a bit before sending another submission'); ok=false; }
      return ok;
    }

    form.addEventListener('submit', async e=>{
      e.preventDefault(); if(!validateForm()) return; submitBtn.disabled=true; submitBtn.textContent='Sending...'; status.textContent='Sending…';
      try{
        const payload = new FormData(form);
        const resp = await fetch('/kbec/api/feedback.php', { method:'POST', body: payload });
        if(!resp.ok){ const err = await resp.json().catch(()=>({message:'Server error'})); throw new Error(err.message||'Server error'); }
        const result = await resp.json(); status.textContent = 'Thanks — your feedback has been submitted.'; status.style.color = 'green'; localStorage.setItem(lastKey, String(Date.now())); form.reset(); setTimeout(()=>{ closeModal(); }, 1400);
      }catch(err){ status.textContent = err.message||'Submission failed'; status.style.color = '#b32'; }
      finally{ submitBtn.disabled=false; submitBtn.textContent='Send'; }
    });
    // show chosen filename
    const attachmentEl = document.getElementById('attachment');
    const fileChosen = document.querySelector('.file-chosen');
    if(attachmentEl){
      attachmentEl.addEventListener('change', ()=>{
        const f = attachmentEl.files[0];
        fileChosen.textContent = f ? f.name : 'No file chosen';
      });
    }
  })();
</script>
<script>
  (function () {
    const membersSection = document.getElementById('members');
    if (!membersSection) {
      return;
    }

    const tabButtons = Array.from(document.querySelectorAll('.member-tab'));
    const signupForm = document.getElementById('memberSignupForm');
    const loginForm = document.getElementById('memberLoginForm');
    const profileForm = document.getElementById('memberProfileForm');
    const signupStatus = document.getElementById('memberSignupStatus');
    const loginStatus = document.getElementById('memberLoginStatus');
    const profileStatus = document.getElementById('memberProfileStatus');
    const resendVerificationBtn = document.getElementById('resendVerificationBtn');
    const memberLogoutBtn = document.getElementById('memberLogoutBtn');
    const dashboardEmpty = document.getElementById('memberDashboardEmpty');
    const dashboardView = document.getElementById('memberDashboardView');
    const dashboardName = document.getElementById('dashboardName');
    const dashboardMeta = document.getElementById('dashboardMeta');
    const dashboardMemberCode = document.getElementById('dashboardMemberCode');
    const dashboardVerification = document.getElementById('dashboardVerification');
    const dashboardEmail = document.getElementById('dashboardEmail');
    const dashboardJoined = document.getElementById('dashboardJoined');
    const profileFields = {
      name: document.getElementById('profileName'),
      phone: document.getElementById('profilePhone'),
      department: document.getElementById('profileDepartment'),
      batch: document.getElementById('profileBatch'),
      interest: document.getElementById('profileInterest'),
      bio: document.getElementById('profileBio')
    };
    const loginEmailField = document.getElementById('loginEmail');

    function showStatus(target, message, type = '') {
      if (!target) return;
      target.className = `member-status${type ? ` ${type}` : ''}`;
      target.innerHTML = message || '';
    }

    function switchTab(name) {
      tabButtons.forEach(button => {
        const isActive = button.dataset.memberTab === name;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      signupForm.hidden = name !== 'signup';
      loginForm.hidden = name !== 'login';
      signupForm.classList.toggle('is-active', name === 'signup');
      loginForm.classList.toggle('is-active', name === 'login');
    }

    function formatDate(value) {
      if (!value) return '-';
      return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function renderDashboard(member) {
      dashboardEmpty.hidden = true;
      dashboardView.hidden = false;

      dashboardName.textContent = member.name || 'Member';
      dashboardMeta.textContent = `${member.department || 'Department not set'} • Batch ${member.batch || '-'}`;
      dashboardMemberCode.textContent = member.memberCode || '-';
      dashboardVerification.textContent = member.verified ? 'Verified' : 'Pending';
      dashboardEmail.textContent = member.email || '-';
      dashboardJoined.textContent = formatDate(member.createdAt);

      profileFields.name.value = member.name || '';
      profileFields.phone.value = member.phone || '';
      profileFields.department.value = member.department || '';
      profileFields.batch.value = member.batch || '';
      profileFields.interest.value = member.interest || '';
      profileFields.bio.value = member.bio || '';
      window.dispatchEvent(new Event('kbec:member-changed'));
    }

    function clearDashboard(message) {
      dashboardView.hidden = true;
      dashboardEmpty.hidden = false;
      dashboardMeta.textContent = message || 'Your verified member profile is ready.';
      dashboardVerification.textContent = '-';
      dashboardMemberCode.textContent = '-';
      dashboardEmail.textContent = '-';
      dashboardJoined.textContent = '-';
      profileForm.reset();
      window.dispatchEvent(new Event('kbec:member-changed'));
    }

    async function requestJson(url, options = {}) {
      const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
          ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
          ...(options.headers || {})
        }
      });

      let payload = {};
      try {
        payload = await response.json();
      } catch (error) {
        payload = {};
      }

      if (!response.ok) {
        const error = new Error(payload.message || 'Request failed');
        error.payload = payload;
        error.status = response.status;
        throw error;
      }

      return payload;
    }

    function scrollToMembers() {
      membersSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function loadMemberSession() {
      try {
        const result = await requestJson('/kbec/api/me.php');
        renderDashboard(result.member);
      } catch (error) {
        clearDashboard();
      }
    }

    async function verifyFromUrl() {
      const url = new URL(window.location.href);
      const token = url.searchParams.get('verify');
      if (!token) return false;
      // Auto-verify is enabled — token-based verification is not needed.
      // Just clean the URL and reload the member session.
      url.searchParams.delete('verify');
      window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
      await loadMemberSession();
      return true;
    }

    tabButtons.forEach(button => {
      button.addEventListener('click', () => switchTab(button.dataset.memberTab));
    });

    signupForm.addEventListener('submit', async event => {
      event.preventDefault();
      showStatus(signupStatus, '', '');

      if (!signupForm.checkValidity()) {
        signupForm.reportValidity();
        showStatus(signupStatus, 'Please complete all required fields.', 'error');
        return;
      }

      const password = document.getElementById('memberPassword').value;
      const confirmPassword = document.getElementById('memberConfirmPassword').value;
      const email = document.getElementById('memberEmail').value.trim().toLowerCase();

      if (password !== confirmPassword) {
        showStatus(signupStatus, 'Passwords do not match.', 'error');
        return;
      }

      if (!email.endsWith('@kuet.ac.bd')) {
        showStatus(signupStatus, 'Use your KUET email address ending in @kuet.ac.bd.', 'error');
        return;
      }

      try {
        const result = await requestJson('/kbec/api/member_register.php', {
          method: 'POST',
          body: JSON.stringify({
            name: document.getElementById('memberName').value,
            studentId: document.getElementById('memberStudentId').value,
            email,
            department: document.getElementById('memberDepartment').value,
            batch: document.getElementById('memberBatch').value,
            phone: document.getElementById('memberPhone').value,
            interest: document.getElementById('memberInterest').value,
            password,
            bio: document.getElementById('memberBio').value
          })
        });

        const verificationLink = result.verificationLink ? ` <a href="${result.verificationLink}">Verify now</a>` : '';
        showStatus(signupStatus, `${result.message || 'Account created.'}${verificationLink}`, 'success');
        loginEmailField.value = email;
        switchTab('login');
        signupForm.reset();
        scrollToMembers();
      } catch (error) {
        showStatus(signupStatus, error.message || 'Registration failed.', 'error');
      }
    });

    loginForm.addEventListener('submit', async event => {
      event.preventDefault();
      showStatus(loginStatus, '', '');

      if (!loginForm.checkValidity()) {
        loginForm.reportValidity();
        showStatus(loginStatus, 'Enter both email and password.', 'error');
        return;
      }

      try {
        const result = await requestJson('/kbec/api/member_login.php', {
          method: 'POST',
          body: JSON.stringify({
            email: loginEmailField.value,
            password: document.getElementById('loginPassword').value
          })
        });

        renderDashboard(result.member);
        showStatus(loginStatus, 'Login successful.', 'success');
        scrollToMembers();
      } catch (error) {
        if (error.status === 403 && error.payload && error.payload.verificationRequired) {
          showStatus(loginStatus, `${error.message}${error.payload.verificationLink ? ` <a href="${error.payload.verificationLink}">Open verification link</a>` : ''}`, 'error');
        } else {
          showStatus(loginStatus, error.message || 'Login failed.', 'error');
        }
      }
    });

    resendVerificationBtn.addEventListener('click', async () => {
      const email = loginEmailField.value.trim();
      if (!email) {
        showStatus(loginStatus, 'Enter your KUET email first.', 'error');
        return;
      }
      // Auto-verify is enabled on this system.
      showStatus(loginStatus, 'This system uses auto-verification. Your account should already be active. Try logging in.', 'success');
    });

    profileForm.addEventListener('submit', async event => {
      event.preventDefault();
      showStatus(profileStatus, '', '');

      if (!profileForm.checkValidity()) {
        profileForm.reportValidity();
        showStatus(profileStatus, 'Complete the required profile fields.', 'error');
        return;
      }

      try {
        const result = await requestJson('/kbec/api/update_profile.php', {
          method: 'PUT',
          body: JSON.stringify({
            name: profileFields.name.value,
            phone: profileFields.phone.value,
            department: profileFields.department.value,
            batch: profileFields.batch.value,
            interest: profileFields.interest.value,
            bio: profileFields.bio.value
          })
        });

        renderDashboard(result.member);
        showStatus(profileStatus, result.message || 'Profile updated.', 'success');
      } catch (error) {
        showStatus(profileStatus, error.message || 'Profile update failed.', 'error');
      }
    });

    memberLogoutBtn.addEventListener('click', async () => {
      try {
        await requestJson('/kbec/api/member_logout.php', { method: 'POST' });
      } catch (error) {
        // Local UI still resets so the user can sign in again.
      }

      clearDashboard('You are logged out.');
      showStatus(loginStatus, 'Logged out successfully.', 'success');
      switchTab('login');
      scrollToMembers();
    });

    verifyFromUrl().then(verified => {
      if (!verified) {
        loadMemberSession();
      }
    });
  })();
</script>
<!-- Announcements Modal -->
<div class="ann-modal-backdrop" id="announcementsModal" role="dialog" aria-modal="true" aria-labelledby="annModalTitle">
  <div class="ann-modal">
    <button type="button" class="close-btn" id="closeAnnModalBtn" aria-label="Close announcements">×</button>
    <h3 id="annModalTitle">Announcements</h3>
    <div class="ann-list">
      <?php if (empty($__announcements)): ?>
        <p style="text-align:center; color:rgba(255,255,255,0.4); padding:20px 0;">No active announcements at the moment. Check back later!</p>
      <?php else: ?>
        <?php foreach ($__announcements as $ann): ?>
          <div class="ann-item">
            <span class="ann-item-type <?= htmlspecialchars($ann['type'], ENT_QUOTES) ?>"><?= htmlspecialchars($ann['type'], ENT_QUOTES) ?></span>
            <h4><?= htmlspecialchars($ann['title'], ENT_QUOTES) ?></h4>
            <?php if ($ann['body']): ?>
              <p><?= htmlspecialchars($ann['body'], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="ann-item-date"><i class="bi bi-clock me-1"></i><?= date('M j, Y', strtotime($ann['created_at'])) ?></span>
              <?php if ($ann['link'] && $ann['link_label']): ?>
                <a href="<?= htmlspecialchars($ann['link'], ENT_QUOTES) ?>" target="_blank" class="ann-item-cta"><?= htmlspecialchars($ann['link_label'], ENT_QUOTES) ?> →</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  (function() {
    const annModal = document.getElementById('announcementsModal');
    const closeAnnModalBtn = document.getElementById('closeAnnModalBtn');
    const navAnnBtn = document.getElementById('navAnnBtn');
    const mobileNavAnnBtn = document.getElementById('mobileNavAnnBtn');

    function openAnnModal(e) {
      if (e) e.preventDefault();
      if (annModal) {
        annModal.classList.add('open');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeAnnModal() {
      if (annModal) {
        annModal.classList.remove('open');
        document.body.style.overflow = '';
      }
    }

    if (navAnnBtn) navAnnBtn.addEventListener('click', openAnnModal);
    if (mobileNavAnnBtn) mobileNavAnnBtn.addEventListener('click', openAnnModal);
    if (closeAnnModalBtn) closeAnnModalBtn.addEventListener('click', closeAnnModal);

    if (annModal) {
      annModal.addEventListener('click', e => {
        if (e.target === annModal) closeAnnModal();
      });
    }
  })();
</script>
</body>
</html>
