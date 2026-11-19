<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>InternHub — Platform Program Magang</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    * { font-family: 'Inter', sans-serif; }
    html { scroll-behavior: smooth; }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    @media (prefers-reduced-motion: reduce) { [style*="animation:blink"] { animation: none !important; } }
    .float { animation: float 5s ease-in-out infinite; }
    .card { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
    .card:hover { transform: translateY(-6px); box-shadow: 0 18px 40px rgba(26,92,56,.14); border-color: #bbf7d0; }
    .divisi-chip { transition: all .2s ease; cursor: default; }
    .divisi-chip:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(26,92,56,.15); background:#ecfdf5; border-color:#6ee7b7; }
    input:focus { outline: none; }
    .nav { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
    .section-label {
      display:inline-flex; align-items:center; gap:8px;
      background:#ecfdf5; border:1px solid #d1fae5;
      color:#1a5c38; font-size:11px; font-weight:700;
      text-transform:uppercase; letter-spacing:.12em;
      padding:6px 14px; border-radius:100px;
    }
  </style>
</head>
<body style="background:#f8faf9; color:#1a1a1a; overflow-x:hidden; color-scheme:light;">

{{-- NAVBAR --}}
<nav class="nav" style="position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(255,255,255,.92);border-bottom:1px solid #e5e7eb;height:60px;">

{{-- NOTIF SUKSES pendaftaran --}}
@if(session('success'))
<div style="position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:200;background:#fff;border:1.5px solid #bbf7d0;border-radius:14px;padding:16px 24px;box-shadow:0 8px 32px rgba(0,0,0,.12);max-width:520px;width:90%;display:flex;align-items:flex-start;gap:14px;">
  <div style="width:36px;height:36px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <svg style="width:18px;height:18px;color:#16a34a;" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <div style="flex:1;">
    <p style="font-weight:700;color:#15803d;font-size:14px;margin-bottom:4px;">Pendaftaran Berhasil! 🎉</p>
    <p style="color:#166534;font-size:13px;line-height:1.5;">{{ session('success') }}</p>
    <a href="{{ route('user.login') }}" style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;background:#1a5c38;color:#fff;font-size:13px;font-weight:600;padding:8px 16px;border-radius:8px;text-decoration:none;">
      <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Login Sekarang
    </a>
  </div>
  <button onclick="this.parentNode.remove()" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:18px;padding:0;line-height:1;">×</button>
</div>
@endif
  <div style="max-width:1100px;margin:0 auto;padding:0 20px;height:100%;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:32px;height:32px;border-radius:8px;background:#1a5c38;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:14px;">IH</div>
      <span style="font-weight:700;color:#1a1a1a;font-size:15px;">InternHub</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
      <a href="{{ route('user.register') }}" style="background:#1a5c38;color:#fff;font-size:13px;font-weight:600;padding:8px 18px;border-radius:8px;text-decoration:none;transition:.2s;"
         onmouseover="this.style.background='#145c30'" onmouseout="this.style.background='#1a5c38'">
        Daftar Magang
      </a>
      <a href="{{ route('user.login') }}" style="color:#374151;font-size:13px;font-weight:500;padding:8px 16px;border-radius:8px;border:1px solid #d1d5db;text-decoration:none;transition:.2s;"
         onmouseover="this.style.borderColor='#1a5c38';this.style.color='#1a5c38'" onmouseout="this.style.borderColor='#d1d5db';this.style.color='#374151'">
        Login
      </a>
    </div>
  </div>
</nav>

{{-- HERO --}}
<section style="
  position:relative;
  min-height:100vh;
  display:flex;
  align-items:center;
  padding-top:60px;
  overflow:hidden;
  background:#0a2e1c;
">
  {{-- Background Image --}}
  <div style="
    position:absolute;
    inset:0;
    background-image:url('{{ asset('images/assets/herosec.jpg') }}');
    background-size:cover;
    background-position:center;
    background-repeat:no-repeat;
    z-index:0;
  "></div>

  {{-- Overlay Gradient Tipis (multi-layer) --}}
  <div style="
    position:absolute;
    inset:0;
    background:
      linear-gradient(to bottom,
        rgba(10,46,28,0.55) 0%,
        rgba(26,92,56,0.25) 40%,
        rgba(13,61,37,0.45) 75%,
        rgba(10,46,28,0.85) 100%),
      linear-gradient(135deg,
        rgba(26,92,56,0.35) 0%,
        rgba(10,46,28,0.15) 50%,
        rgba(26,92,56,0.40) 100%);
    z-index:1;
  "></div>

  <div style="
    position:relative;
    z-index:2;
    max-width:1100px;
    margin:0 auto;
    padding:80px 20px;
    display:grid;
    grid-template-columns:1fr;
    gap:48px;
    align-items:center;
  ">
    <div style="text-align:center;">
      <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:100px;padding:6px 16px;margin-bottom:24px;backdrop-filter:blur(8px);">
        <span style="width:8px;height:8px;border-radius:50%;background:#4ade80;animation:blink 1.5s ease-in-out infinite;"></span>
        <span style="color:rgba(255,255,255,.95);font-size:13px;font-weight:500;">Pendaftaran Magang Dibuka</span>
      </div>
      <h1 style="color:#fff;font-size:clamp(32px,5vw,52px);font-weight:800;line-height:1.2;margin-bottom:16px;text-shadow:0 3px 18px rgba(0,0,0,.55), 0 1px 3px rgba(0,0,0,.4);">
        Mulai Karir Impianmu<br>
        <span style="color:#86efac;">Bersama InternHub</span>
      </h1>
      <p style="color:rgba(255,255,255,.9);font-size:17px;line-height:1.7;margin-bottom:32px;max-width:540px;margin-left:auto;margin-right:auto;text-shadow:0 2px 10px rgba(0,0,0,.5);">
        Program magang profesional di berbagai divisi. Dapatkan pengalaman nyata,
        mentoring langsung, dan dokumen resmi yang diakui industri.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('user.register') }}" style="display:inline-flex;align-items:center;gap:8px;background:#4ade80;color:#14532d;font-weight:700;font-size:14px;padding:14px 28px;border-radius:12px;text-decoration:none;box-shadow:0 4px 20px rgba(74,222,128,.35);transition:.2s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(74,222,128,.5)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 20px rgba(74,222,128,.35)'">
          <i class="fas fa-rocket" style="font-size:12px;"></i> Daftar Sekarang
        </a>
        <a href="#ketentuan" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);font-weight:500;font-size:14px;padding:14px 24px;border-radius:12px;text-decoration:none;backdrop-filter:blur(8px);transition:.2s;"
           onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
          <i class="fas fa-info-circle" style="font-size:12px;"></i> Pelajari Lebih
        </a>
      </div>

      {{-- Stats --}}
      @php
        $divisionList = $divisions ?? \App\Models\Division::active()->pluck('name');
        if ($divisionList->isEmpty()) {
            $divisionList = \App\Models\Division::orderBy('name')->pluck('name');
        }
        $divStatLabel = $divisionList->isNotEmpty() ? ($divisionList->count() . '+') : '10+';
      @endphp
      <div style="display:flex;justify-content:center;gap:48px;margin-top:56px;flex-wrap:wrap;">
        @foreach([[$divStatLabel,'Divisi Magang'],['100+','Alumni Magang'],['100%','Dokumen Resmi']] as [$num,$label])
        <div style="text-align:center;">
          <div style="font-size:32px;font-weight:800;color:#fff;text-shadow:0 2px 14px rgba(0,0,0,.6);">{{ $num }}</div>
          <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:2px;text-shadow:0 1px 4px rgba(0,0,0,.4);">{{ $label }}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</section>

{{-- KETENTUAN --}}
<section id="ketentuan" style="padding:96px 20px;background:linear-gradient(180deg,#ffffff 0%,#f8faf9 100%);position:relative;">
  <div style="max-width:1100px;margin:0 auto;">
    <div style="text-align:center;margin-bottom:56px;">
      <div class="section-label" style="margin-bottom:14px;">
        <i class="fas fa-clipboard-list" style="font-size:10px;"></i>
        Ketentuan Program
      </div>
      <h2 style="font-size:clamp(26px,3.5vw,36px);font-weight:800;color:#111;margin-bottom:12px;letter-spacing:-.02em;">Apa yang Perlu Kamu Tahu</h2>
      <p style="color:#6b7280;max-width:520px;margin:0 auto;line-height:1.7;font-size:15px;">Baca ketentuan berikut sebelum mendaftar agar proses magang berjalan lancar.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:22px;">
      @php
        $items = [
          ['fa-calendar-alt','#dcfce7','#15803d','Jadwal Magang','Senin–Sabtu. Shift Pagi (06.30–13.00), Siang (13.00–21.00), atau Middle (09.00–17.00 WIB).'],
          ['fa-clock','#dbeafe','#1d4ed8','Durasi','Minimal 1 bulan, maksimal 6 bulan. Disesuaikan dengan kebutuhan program studi.'],
          ['fa-wallet','#fef9c3','#92400e','Program Unpaid','Bersifat tidak berbayar (unpaid). Fokus pada pengalaman & pembelajaran langsung.'],
          ['fa-laptop','#f3e8ff','#7e22ce','Wajib Laptop','Peserta wajib membawa laptop sendiri beserta alat pendukung yang dibutuhkan.'],
          ['fa-file-alt','#fee2e2','#b91c1c','Dokumen Lengkap','CV, KTP/KTM, surat pengantar kampus dalam format PDF. Maks. 2MB per file.'],
          ['fa-award','#dcfce7','#15803d','Dokumen Resmi','Peserta yang selesai mendapat LOA, SKL, Sertifikat, Surat Penilaian & Membercard.'],
        ];
      @endphp
      @foreach($items as [$icon,$bg,$color,$title,$desc])
      <div class="card" style="background:#fff;border:1px solid #eef2f0;border-radius:18px;padding:28px 24px;box-shadow:0 2px 10px rgba(0,0,0,.04);position:relative;overflow:hidden;">
        <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,{{ $color }},transparent);opacity:.5;"></div>
        <div style="width:48px;height:48px;border-radius:12px;background:{{ $bg }};display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
          <i class="fas {{ $icon }}" style="color:{{ $color }};font-size:17px;"></i>
        </div>
        <h3 style="font-weight:700;color:#111;margin-bottom:8px;font-size:15.5px;">{{ $title }}</h3>
        <p style="font-size:13.5px;color:#6b7280;line-height:1.7;margin:0;">{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- DIVISI --}}
<section style="padding:96px 20px;background:#f0f7f3;position:relative;overflow:hidden;">
  {{-- dekorasi blob --}}
  <div style="position:absolute;top:-80px;right:-80px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(74,222,128,.18),transparent 70%);pointer-events:none;"></div>
  <div style="position:absolute;bottom:-100px;left:-100px;width:320px;height:320px;border-radius:50%;background:radial-gradient(circle,rgba(26,92,56,.12),transparent 70%);pointer-events:none;"></div>

  <div style="max-width:1100px;margin:0 auto;text-align:center;position:relative;z-index:1;">
    <div class="section-label" style="margin-bottom:14px;">
      <i class="fas fa-layer-group" style="font-size:10px;"></i>
      Divisi Tersedia
    </div>
    <h2 style="font-size:clamp(26px,3.5vw,36px);font-weight:800;color:#111;margin-bottom:14px;letter-spacing:-.02em;">Pilih Bidangmu</h2>
    <p style="color:#6b7280;max-width:520px;margin:0 auto 40px;line-height:1.7;font-size:15px;">Temukan divisi yang paling sesuai dengan minat dan keahlianmu.</p>
    <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;">
      @forelse($divisionList as $div)
      <span class="divisi-chip" style="background:#fff;border:1px solid #d1fae5;color:#065f46;font-size:13.5px;font-weight:600;padding:10px 20px;border-radius:100px;box-shadow:0 2px 6px rgba(0,0,0,.05);display:inline-flex;align-items:center;gap:8px;">
        <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
        {{ is_string($div) ? $div : ($div->name ?? $div) }}
      </span>
      @empty
      <p style="color:#6b7280;font-size:14px;">Belum ada divisi yang tersedia.</p>
      @endforelse
    </div>
  </div>
</section>

{{-- FOOTER --}}
<footer style="background:linear-gradient(180deg,#ffffff 0%,#f8faf9 100%);border-top:1px solid #eef2f0;padding:32px 20px;">
  <div style="max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:28px;height:28px;border-radius:8px;background:#1a5c38;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:800;box-shadow:0 2px 8px rgba(26,92,56,.25);">IH</div>
      <span style="font-size:13.5px;font-weight:700;color:#374151;">InternHub</span>
    </div>
    <p style="font-size:12.5px;color:#9ca3af;margin:0;">© {{ date('Y') }} InternHub. Program Magang Profesional Yogyakarta.</p>
    <div style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:#6b7280;">
      <i class="fas fa-map-marker-alt" style="color:#1a5c38;"></i> Yogyakarta, Indonesia
    </div>
  </div>
</footer>

</body>
</html>