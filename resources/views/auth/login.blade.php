<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Masuk — InternHub</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
    html, body { margin:0; padding:0; }
    body {
      min-height:100vh;
      background-color:#f0f7f3;
      color:#1a1a1a;
      display:flex;
      justify-content:center;
      align-items:center;
      padding:24px 16px;
      overflow-x:hidden;
      position:relative;
    }

    /* === BACKGROUND BASE === */
    .bg-pattern {
      position:fixed;
      inset:0;
      z-index:0;
      pointer-events:none;
      background:
        radial-gradient(circle at 15% 20%, rgba(74,222,128,.16), transparent 42%),
        radial-gradient(circle at 85% 80%, rgba(26,92,56,.12), transparent 45%),
        linear-gradient(180deg, #f8faf9 0%, #e6f4ec 100%);
    }

    /* === PATTERN DARI FILE pattern.png (DIPERBESAR & LEBIH TERLIHAT) === */
    .bg-batik-image {
      position:fixed;
      inset:0;
      z-index:0;
      pointer-events:none;
      opacity:.45;                          /* sebelumnya .12 — sekarang jauh lebih terlihat */
      background-image: url('{{ asset('images/assets/pattern.png') }}');
      background-size: 420px 420px;         /* sebelumnya 180px — sekarang 2.3x lebih besar */
      background-repeat: repeat;
      background-position: center;
      /* fade tepi lebih longgar agar pattern terlihat hampir penuh */
      mask-image: radial-gradient(ellipse at center, #000 55%, transparent 95%);
      -webkit-mask-image: radial-gradient(ellipse at center, #000 55%, transparent 95%);
    }

    /* === TINT HIJAU agar pattern menyatu (opsional, tipis) === */
    .bg-batik-tint {
      position:fixed;
      inset:0;
      z-index:0;
      pointer-events:none;
      background:linear-gradient(180deg,
        rgba(26,92,56,.08) 0%,
        rgba(74,222,128,.04) 50%,
        rgba(15,68,35,.10) 100%);
      mix-blend-mode:multiply;
    }

    /* === BLOB DEKORATIF === */
    .deco-blob {
      position:fixed;
      border-radius:50%;
      pointer-events:none;
      z-index:0;
      filter:blur(6px);
    }

    input:focus { outline: none; }
    .auth-card {
      animation: cardIn .5s cubic-bezier(.2,.8,.2,1) both;
    }
    @keyframes cardIn {
      from { opacity:0; transform: translateY(16px) scale(.98); }
      to   { opacity:1; transform: translateY(0) scale(1); }
    }
    .field-input {
      transition: border-color .18s, box-shadow .18s, background .18s;
    }
    .field-input:focus {
      border-color:#1a5c38 !important;
      box-shadow:0 0 0 4px rgba(26,92,56,.10);
      background:#fff;
    }
    .btn-primary {
      transition: transform .15s, background .2s, box-shadow .2s;
    }
    .btn-primary:hover {
      background:#0f4423;
      box-shadow:0 8px 22px rgba(26,92,56,.28);
      transform: translateY(-1px);
    }
    .btn-primary:active { transform: translateY(0); }
    .brand-link:hover .brand-text { color:#1a5c38; }
    .brand-text { transition: color .2s; }

    /* === TOMBOL KEMBALI (FIXED POJOK KIRI ATAS) === */
    .btn-back {
      position:fixed;
      top:20px;
      left:20px;
      z-index:50;
      display:inline-flex;
      align-items:center;
      gap:8px;
      background:rgba(255,255,255,.85);
      backdrop-filter:blur(10px);
      -webkit-backdrop-filter:blur(10px);
      border:1px solid rgba(209,250,229,.95);
      color:#1a5c38;
      font-size:12.5px;
      font-weight:600;
      padding:9px 16px;
      border-radius:100px;
      text-decoration:none;
      box-shadow:0 4px 16px rgba(26,92,56,.10);
      transition: all .2s ease;
    }
    .btn-back:hover {
      background:#fff;
      border-color:#86efac;
      color:#0f4423;
      box-shadow:0 8px 24px rgba(26,92,56,.20);
      transform: translateX(-3px);
    }
    .btn-back i {
      font-size:11px;
      transition: transform .2s ease;
    }
    .btn-back:hover i {
      transform: translateX(-3px);
    }

    /* Responsif: di layar kecil, tombol mengecil */
    @media (max-width: 480px) {
      .btn-back {
        top:14px;
        left:14px;
        padding:8px 13px;
        font-size:12px;
      }
      .btn-back span { display:none; }
      .btn-back {
        width:36px;
        height:36px;
        justify-content:center;
        padding:0;
      }
      .btn-back i { font-size:13px; }
      .btn-back:hover { transform: none; }
    }

    @media (prefers-reduced-motion: reduce) {
      .auth-card { animation:none; }
    }
  </style>
</head>
<body>

{{-- Tombol Kembali — fixed pojok kiri atas --}}
<a href="{{ route('home') }}" class="btn-back" aria-label="Kembali ke Beranda">
  <i class="fas fa-arrow-left"></i>
  <span>Kembali</span>
</a>

{{-- Background pattern --}}
<div class="bg-pattern"></div>
<div class="bg-batik-image"></div>
<div class="bg-batik-tint"></div>

{{-- Blob dekoratif --}}
<div class="deco-blob" style="width:340px;height:340px;top:-120px;left:-100px;background:radial-gradient(circle,rgba(74,222,128,.24),transparent 70%);"></div>
<div class="deco-blob" style="width:420px;height:420px;bottom:-160px;right:-140px;background:radial-gradient(circle,rgba(26,92,56,.18),transparent 70%);"></div>
<div class="deco-blob" style="width:200px;height:200px;top:42%;right:8%;background:radial-gradient(circle,rgba(134,239,172,.24),transparent 70%);"></div>

<div style="width:100%; max-width:440px; position:relative; z-index:1;">

  {{-- Brand --}}
  <div style="text-align:center; margin-bottom:28px;">
    <a href="{{ route('home') }}" class="brand-link" style="display:inline-flex; align-items:center; gap:10px; text-decoration:none;">
      <div style="width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#1a5c38,#0f4423); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:15px; box-shadow:0 6px 18px rgba(26,92,56,.30);">IH</div>
      <span class="brand-text" style="font-weight:800; color:#111; font-size:19px; letter-spacing:-.02em;">InternHub</span>
    </a>
  </div>

  {{-- Card --}}
  <div class="auth-card" style="background:rgba(255,255,255,.94);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(209,250,229,.9);border-radius:22px;padding:34px 32px;box-shadow:0 16px 48px rgba(26,92,56,.14), 0 2px 8px rgba(0,0,0,.04);position:relative;overflow:hidden;">
    {{-- Aksen gradient atas --}}
    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#1a5c38,#4ade80,#86efac,#1a5c38);"></div>

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
      <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #d1fae5;">
        <i class="fas fa-sign-in-alt" style="color:#1a5c38;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-weight:800;color:#111;font-size:16px;letter-spacing:-.01em;">Masuk</div>
        <div style="color:#9ca3af;font-size:12.5px;">Login ke akun Anda</div>
      </div>
    </div>

    @if(session('error'))
      <div style="display:flex;align-items:flex-start;gap:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:13px;padding:11px 14px;border-radius:10px;margin-bottom:16px;">
        <i class="fas fa-circle-exclamation" style="margin-top:2px;color:#dc2626;"></i>
        <span>{{ session('error') }}</span>
      </div>
    @endif
    @if(session('success'))
      <div style="display:flex;align-items:flex-start;gap:10px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:13px;padding:11px 14px;border-radius:10px;margin-bottom:16px;">
        <i class="fas fa-circle-check" style="margin-top:2px;color:#16a34a;"></i>
        <span>{{ session('success') }}</span>
      </div>
    @endif
    @if(session('status'))
      <div style="display:flex;align-items:flex-start;gap:10px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:13px;padding:11px 14px;border-radius:10px;margin-bottom:16px;">
        <i class="fas fa-circle-info" style="margin-top:2px;color:#16a34a;"></i>
        <span>{{ session('status') }}</span>
      </div>
    @endif

    <form action="{{ route('user.login.submit') }}" method="POST">
      @csrf
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:7px;">Username atau Email</label>
        <div style="position:relative;">
          <i class="fas fa-user" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px;pointer-events:none;"></i>
          <input type="text" name="login" required value="{{ old('login', old('email')) }}" placeholder="Username atau email"
                 class="field-input"
                 style="width:100%;padding:11px 14px 11px 38px;border:1.5px solid #e5e7eb;border-radius:11px;font-size:13.5px;color:#111;background:#fafbfb;">
        </div>
      </div>
      <div style="margin-bottom:18px;">
        <label for="password" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:7px;">Password</label>
        <div style="position:relative;">
          <i class="fas fa-lock" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px;pointer-events:none;"></i>
          <input type="password" id="password" name="password" required placeholder="Password"
                 autocomplete="current-password" autocorrect="off" autocapitalize="none" spellcheck="false"
                 class="field-input"
                 style="width:100%;padding:11px 44px 11px 38px;border:1.5px solid #e5e7eb;border-radius:11px;font-size:13.5px;background:#fafbfb;">
          <button type="button" onclick="togglePasswordVisibility('password', this)" aria-label="Lihat password"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;padding:4px;cursor:pointer;color:#6b7280;display:flex;align-items:center;justify-content:center;border-radius:6px;">
            <svg class="eye-icon" style="width:18px;height:18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="remember" id="remember" style="width:15px;height:15px;accent-color:#1a5c38;cursor:pointer;">
          <label for="remember" style="font-size:13px;color:#6b7280;cursor:pointer;">Ingat saya</label>
        </div>
        <a href="{{ route('password.request') }}" style="font-size:13px;color:#1a5c38;font-weight:600;text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#0f4423'" onmouseout="this.style.color='#1a5c38'">Lupa password?</a>
      </div>
      <button type="submit" class="btn-primary"
              style="width:100%;padding:13px;background:#145c30;color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-sign-in-alt" style="font-size:12px;"></i> Masuk ke Dashboard
      </button>
    </form>

    <div style="margin-top:22px;padding-top:20px;border-top:1px solid #f3f4f6;text-align:center;">
      <p style="font-size:12.5px;color:#9ca3af;margin:0;">Belum punya akun?
        <a href="{{ route('user.register') }}" style="color:#1a5c38;font-weight:700;text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#0f4423'" onmouseout="this.style.color='#1a5c38'">Daftar sekarang</a>
      </p>
    </div>
  </div>

  {{-- Footer kecil --}}
  <p style="text-align:center;font-size:11.5px;color:#9ca3af;margin-top:22px;">© {{ date('Y') }} InternHub — Program Magang Profesional Yogyakarta</p>
</div>

<script>
function togglePasswordVisibility(fieldId, btn) {
  const input = document.getElementById(fieldId);
  if (!input) return;
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Lihat password');
  const icon = btn.querySelector('.eye-icon');
  if (icon) {
    if (isHidden) {
      icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
      icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>';
    }
  }
}
</script>
</body>
</html>