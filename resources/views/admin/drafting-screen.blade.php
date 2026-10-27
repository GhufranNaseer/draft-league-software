<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>PTPL 2026 - Live Draft Screen</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=Bebas+Neue&display=swap" rel="stylesheet">
<style>
  html, body {
    margin: 0;
    padding: 0;
    width: 100vw;
    height: 100vh;
    min-height: 100dvh;
    background: #0b2a14;
    overflow: hidden;
    user-select: none;
  }

  /* warm-up reveal gate */
  #stage { opacity: .01; }
  #stage.ready { opacity: 1; transition: opacity .35s ease-out; }
  #stage:not(.ready) *, #stage:not(.ready) *::before, #stage:not(.ready) *::after { animation-play-state: paused !important; }

  /* 1920x1080 stage, scaled proportionally to fit any screen */
  #stage {
    position: absolute; left: 0; top: 0;
    width: 1920px; height: 1080px;
    transform-origin: 0 0;
    overflow: hidden;
    background:
      radial-gradient(1000px 520px at 6% 100%, rgba(135,118,45,.50), transparent 70%),
      radial-gradient(900px 500px at 88% 100%, rgba(0,22,8,.62), transparent 72%),
      radial-gradient(600px 400px at 100% 0%, rgba(0,50,12,.28), transparent 70%),
      linear-gradient(180deg, rgb(36,92,44) 0%, rgb(38,90,43) 38%, rgb(48,68,38) 70%, rgb(52,56,32) 100%);
    font-family: 'Bebas Neue', 'Barlow Condensed', Impact, 'Arial Narrow', sans-serif;
  }

  /* background texture + vignette */
  .tex { position: absolute; top: 0; height: 1080px; background-repeat: repeat; background-position: 0 0; will-change: transform; backface-visibility: hidden; }
  #texF { left: -512px;  width: 2432px; }
  #texB { left: -1024px; width: 2944px; }
  #texS { left: -700px;  width: 2620px; }
  .tex.fine {
    opacity: .8; mix-blend-mode: soft-light;
    background-size: 512px 512px;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='512' height='512'><filter id='n' x='0' y='0' width='100%' height='100%'><feTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' seed='4' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 0.25  0 0 0 0 0.75  0 0 0 0 0.25  0 0 0 1.6 -0.55'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
  }
  .tex.blotch {
    opacity: 1; mix-blend-mode: multiply;
    background-size: 1024px 1024px;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='1024' height='1024'><filter id='n' x='0' y='0' width='100%' height='100%'><feTurbulence type='fractalNoise' baseFrequency='0.006 0.03' numOctaves='4' seed='9' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 0.55  0 0 0 0 0.85  0 0 0 0 0.55  0 0 0 2.0 -0.75'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
  }
  .tex.streak {
    opacity: .1; mix-blend-mode: overlay;
    background-size: 700px 1080px;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='700' height='1080'><filter id='n' x='0' y='0' width='100%' height='100%'><feTurbulence type='fractalNoise' baseFrequency='0.22 0.004' numOctaves='3' seed='15' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 0.7  0 0 0 0 1  0 0 0 0 0.7  0 0 0 2.2 -0.95'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
  }
  .vignette {
    position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse at 55% 45%, rgba(0,0,0,0) 60%, rgba(0,20,8,.22) 100%);
  }
  #sparks { position: absolute; inset: 0; width: 1920px; height: 1080px; }

  /* rotating neon star: ONLY shown in Card View (player detail), hidden during Countdown */
  #starSvg {
    position: absolute; left: 955px; top: -218.4px; width: 1500px; height: 1500px;
    overflow: visible; transform-origin: 50% 50%; will-change: transform; backface-visibility: hidden; z-index: 2;
    display: none;
  }
  #stage.mode-card #starSvg { display: block !important; }
  #stage.mode-countdown #starSvg { display: none !important; }

  #starSvg .glow-yellow { filter: drop-shadow(0 0 7px rgba(206,240,70,.35)); }
  #starSvg .glow-white  { filter: drop-shadow(0 0 7px rgba(255,255,255,.95)) drop-shadow(0 0 16px rgba(255,255,255,.6)); }
  #starSvg .glow-red    { filter: drop-shadow(0 0 5px rgba(255,30,60,.7)); }

  /* left purple side bar */
  #side {
    position: absolute; left: 0; top: 0; width: 70px; height: 1080px;
    background: #611aa4; overflow: hidden;
    box-shadow: 4px 0 14px rgba(0,0,0,.35);
    z-index: 10;
  }
  #sideTrack { position: absolute; left: 0; top: 0; width: 70px; will-change: transform; }
  .sitem {
    position: relative; width: 70px; display: flex; align-items: flex-start; justify-content: center;
    color: #fff;
  }
  .sitem .txt {
    writing-mode: vertical-rl; transform: rotate(180deg);
    font-family: 'Barlow Condensed', 'Bebas Neue', Impact, sans-serif;
    font-weight: 700; font-size: 58px; letter-spacing: 1px; line-height: 70px;
    white-space: nowrap;
  }
  .sitem.draft { height: 160px; }
  .sitem.dchev { height: 105px; }
  .sitem.ptpl  { height: 255px; }
  .sitem.ptpl.b { height: 245px; }
  .sitem.uchev { height: 130px; }
  .sitem.uchev.last { height: 185px; }
  .sitem svg { display: block; }

  /* View container management */
  .view-mode {
    position: absolute; inset: 0; width: 1920px; height: 1080px; z-index: 5;
  }
  .view-mode.is-hidden {
    opacity: 0 !important;
    pointer-events: none !important;
    display: none !important;
  }
  .view-mode.is-active {
    opacity: 1 !important;
    pointer-events: auto !important;
  }
  #countdownView.view-mode.is-active {
    display: flex !important;
  }
  #cardView.view-mode.is-active {
    display: block !important;
  }

  /* =========================================================================
     COUNTDOWN VIEW (ptpl-draft-countdown.html)
     Horizontally and Vertically Centered on the 1920x1080 Stage
     ========================================================================= */
  #countdownView {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
  }
  .timer {
    position: absolute; inset: 0; width: 1920px; height: 1080px; z-index: 6;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    pointer-events: none;
  }
  .timer::before {
    content: ""; position: absolute; inset: 0; width: 1920px; height: 1080px;
    background: radial-gradient(ellipse 1100px 520px at 50% 50%, rgba(0,18,8,.45), rgba(0,18,8,0) 75%);
    z-index: -1;
  }

  .countdown-content {
    position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; z-index: 7;
  }
  #countdownHeader {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    margin-bottom: 6px; z-index: 7; text-align: center;
  }
  .clock-badge {
    display: inline-flex; align-items: center; gap: 10px;
    background: rgba(11, 42, 20, 0.85); border: 2.5px solid #38e8ff;
    border-radius: 30px; padding: 6px 28px;
    font-size: 32px; letter-spacing: 5px; color: #38e8ff; font-weight: 700;
    text-shadow: 0 0 16px rgba(56,232,255,.9);
    box-shadow: 0 0 24px rgba(56,232,255,.35);
  }
  .clock-badge.is-paused-badge {
    border-color: #ffb703 !important;
    color: #ffb703 !important;
    text-shadow: 0 0 16px rgba(255,183,3,.9) !important;
    box-shadow: 0 0 24px rgba(255,183,3,.4) !important;
    animation: badgePausePulse 1.8s ease-in-out infinite alternate;
  }
  @keyframes badgePausePulse {
    0% { opacity: 0.85; transform: scale(0.98); }
    100% { opacity: 1; transform: scale(1.02); }
  }
  .clock-team {
    font-size: 82px; letter-spacing: 2px; color: #ffffff;
    text-transform: uppercase; font-weight: 700; line-height: 1.05;
    text-shadow: 0 0 24px rgba(255,255,255,.8), 0 4px 10px rgba(0,0,0,.9);
    max-width: 1400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    padding-top: 4px;
  }
  .clock-meta {
    font-size: 34px; letter-spacing: 3px; color: #c6f03a; font-weight: 700;
    text-shadow: 0 0 16px rgba(198,240,58,.8), 0 2px 4px rgba(0,0,0,.9);
    text-transform: uppercase;
  }

  #digits {
    position: relative; display: flex; align-items: center; justify-content: center;
    font-size: 460px; line-height: 1; color: #fff; white-space: nowrap;
    transform: translateY(var(--nudge, 0em));
    text-shadow: 0 10px 40px rgba(0,0,0,.6), 0 0 35px rgba(255,255,255,0.25);
    z-index: 7;
  }
  #digits.long { font-size: 340px; }
  #digits .d { display: inline-block; width: var(--cell, .42em); text-align: center; }
  #digits .c { display: inline-block; width: var(--colon, .22em); text-align: center; }

  /* =========================================================================
     PLAYER CARD VIEW (ptpl-draft-card.html)
     Scoped & Precise Layout inside the 1920x1080 Stage
     ========================================================================= */
  :root {
    --neon-grad: linear-gradient(100deg, #7a35ff 0%, #4a6dff 14%, #25d3ff 38%, #54f0b0 62%, #a8ff3c 82%, #d3f53a 100%);
    --field-fill: linear-gradient(180deg, rgba(62,68,68,.93) 0%, rgba(36,41,41,.94) 50%, rgba(27,31,31,.95) 100%);
  }

  /* HUD frames */
  .hud {
    position: absolute; box-sizing: border-box; pointer-events: none;
    border: 1.5px solid rgba(110,230,255,.38);
    background:
      repeating-linear-gradient(0deg,  rgba(120,255,210,.07) 0 1px, transparent 1px 30px),
      repeating-linear-gradient(90deg, rgba(120,255,210,.07) 0 1px, transparent 1px 30px);
    box-shadow: inset 0 0 40px rgba(0,255,170,.06);
  }
  .hud::before, .hud::after {
    content: ""; position: absolute; width: 26px; height: 26px; border: 3px solid rgba(160,255,230,.75);
    filter: drop-shadow(0 0 4px rgba(120,255,220,.8));
  }
  .hud::before { left: -3px; top: -3px; border-right: none; border-bottom: none; }
  .hud::after  { right: -3px; bottom: -3px; border-left: none; border-top: none; }
  .hud.h1 { left: 136px; top: 222px; width: 826px; height: 330px; }
  .hud.h2 { left: 136px; top: 558px; width: 826px; height: 394px; }
  .hudticks {
    position: absolute; right: 0; bottom: -14px; display: flex; gap: 5px; pointer-events: none;
  }
  .hudticks i { display: block; width: 18px; height: 5px; background: #38e8ff; box-shadow: 0 0 6px #38e8ff; opacity: .9; }
  .hudticks i:nth-child(2) { width: 8px; background: #b6ff3c; box-shadow: 0 0 6px #b6ff3c; }
  .hudticks i:nth-child(3) { width: 28px; opacity: .6; }
  .hudside {
    position: absolute; right: -8px; top: 70px; display: flex; flex-direction: column; gap: 6px; pointer-events: none;
  }
  .hudside i { display: block; width: 6px; height: 6px; background: #38e8ff; box-shadow: 0 0 6px #38e8ff; opacity: .85; }

  /* Photo box */
  .photo {
    position: absolute; left: 164px; top: 250px; width: 280px; height: 290px; box-sizing: border-box;
    background: #fffffa; border: 9px solid #c6f03a; border-radius: 42px;
    box-shadow: 0 0 20px rgba(198,240,58,.6), 0 6px 18px rgba(0,0,0,.35);
    overflow: hidden; display: flex; align-items: center; justify-content: center;
  }
  .photo img {
    width: 100%; height: 100%; object-fit: cover; border-radius: 33px; display: block;
  }
  .photo .placeholder-avatar {
    width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1b3820 0%, #0d2212 100%);
    border-radius: 33px; color: rgba(198,240,58,0.7);
  }

  /* Information Bars */
  .bar {
    position: absolute; box-sizing: border-box; height: 94px;
    isolation: isolate;
    background: none;
    overflow: hidden;
    border-radius: 26px;
    transform: skewX(-15deg);
    box-shadow:
      -10px 0 20px rgba(122,53,255,.55),
       10px 0 20px rgba(168,255,60,.50),
       0 0 12px rgba(37,211,255,.45),
       0 10px 18px rgba(0,0,0,.40);
    display: flex; align-items: center; padding-left: 38px;
    color: #fff; font-size: 62px; letter-spacing: .5px; line-height: 1;
  }
  .bar::after {
    content: ""; position: absolute; inset: 4px; border-radius: 22px; z-index: -1;
    background: var(--field-fill);
  }
  .bar .lbl {
    display: inline-block; transform: scaleX(.92); transform-origin: left center; padding-top: 4px;
    -webkit-text-stroke: 1.6px #fff;
    text-shadow: 0 3px 4px rgba(0,0,0,.65), 0 0 8px rgba(0,0,0,.35);
    white-space: nowrap; flex-shrink: 0;
  }
  .bar .val {
    display: inline-block; transform: scaleX(.94); transform-origin: left center;
    color: #c6f03a; font-weight: 700; letter-spacing: 1px;
    text-shadow: 0 0 12px rgba(198,240,58,.55), 0 2px 4px rgba(0,0,0,.8);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }

  /* NAME bar */
  .bar.name::after {
    background:
      linear-gradient(90deg, rgba(120,60,215,.95) 0%, rgba(98,48,178,.90) 16%, rgba(72,42,128,.70) 26%, rgba(40,42,52,.0) 40%),
      var(--field-fill);
  }
  .bar.name::before {
    content: ""; position: absolute; left: 190px; top: 4px; bottom: 4px; width: 26px; z-index: -1;
    background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.16), rgba(255,255,255,0));
    transform: skewX(-8deg);
  }

  /* NICK NAME box */
  .bar.nick {
    transform: none; border-radius: 26px; padding-left: 0; justify-content: center;
    --g: linear-gradient(115deg, #8a3dff 0%, #5a5bff 14%, #26d4ff 46%, #6af0a0 68%, #b3ff3a 100%);
  }
  .bar.nick::after { border-radius: 22px; }
  .bar.nick .val {
    transform: none; text-align: center; max-width: 380px; padding: 0 12px;
  }

  /* lens flares */
  .flare { position: absolute; width: 260px; height: 70px; margin: -35px 0 0 -130px; pointer-events: none; z-index: 3; mix-blend-mode: screen; }
  .flare::before {
    content: ""; position: absolute; inset: 0;
    background: radial-gradient(ellipse at center, var(--fc1) 0%, var(--fc2) 18%, rgba(0,0,0,0) 62%);
    animation: flarePulse 3.2s ease-in-out infinite;
  }
  .flare::after {
    content: ""; position: absolute; left: 0; right: 0; top: 50%; height: 3px; margin-top: -1.5px;
    background: linear-gradient(90deg, rgba(255,255,255,0), var(--fc1) 50%, rgba(255,255,255,0));
    filter: blur(.6px);
    animation: flarePulse 3.2s ease-in-out infinite;
  }
  .flare.f1 { --fc1: rgba(235,215,255,.98); --fc2: rgba(150,90,255,.55); }
  .flare.f2 { --fc1: rgba(235,255,150,.98); --fc2: rgba(150,255,50,.50); width: 320px; margin-left: -160px; }
  .flare.f2::before, .flare.f2::after { animation-delay: -1.6s; }
  @keyframes flarePulse { 0%,100% { opacity: .75; transform: scaleX(.92); } 50% { opacity: 1; transform: scaleX(1.06); } }
  .flare::before, .flare::after { transform-origin: center; }

  /* flowing shimmer in neon border */
  .bar .flow {
    position: absolute; left: 0; top: 0; bottom: 0; width: 240%; z-index: -2; pointer-events: none;
    background: var(--g, var(--neon-grad));
    will-change: transform, opacity; backface-visibility: hidden;
    animation: borderFlow 7s ease-in-out infinite alternate;
    animation-delay: var(--dl, 0s);
  }
  @keyframes borderFlow {
    from { transform: translate3d(0,0,0);        opacity: .9; }
    to   { transform: translate3d(-58.333%,0,0); opacity: 1;  }
  }
  .bar.nick { --dl: 0s; }
  .bar.name { --dl: -1.4s; }
  .bar.cat  { --dl: -2.8s; }
  .bar.role { --dl: -4.2s; }
  .bar.city { --dl: -5.6s; }

  .nicklbl {
    position: absolute; left: 486px; top: 366px;
    font-size: 62px; letter-spacing: 1px; line-height: 1;
    transform: skewX(-12deg) scaleX(.92); transform-origin: left center;
    color: #fff;
    -webkit-text-stroke: 1.6px #fff;
    text-shadow: 0 3px 4px rgba(0,0,0,.65), 0 0 8px rgba(0,0,0,.35);
  }

  /* Team Picked Pill / Header Badge */
  #teamPickBadge {
    position: absolute; left: 486px; top: 254px;
    display: flex; align-items: baseline; gap: 14px;
    z-index: 2;
  }
  #teamPickBadge .pill-label {
    font-size: 28px; color: #38e8ff; letter-spacing: 2px;
    -webkit-text-stroke: 1px #38e8ff;
    text-shadow: 0 0 10px rgba(56,232,255,.6);
  }
  #teamPickBadge .pill-name {
    font-size: 44px; color: #ffffff; letter-spacing: 1.5px;
    text-transform: uppercase; font-weight: 700;
    text-shadow: 0 0 14px rgba(255,255,255,.7), 0 2px 4px rgba(0,0,0,.8);
    max-width: 440px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  /* Player Card Transition Container */
  #playerCardContent {
    position: absolute; inset: 0;
    transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1), transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
  }
  #playerCardContent.anim-exit-left {
    opacity: 0; transform: translate3d(-50px, 0, 0);
  }
  #playerCardContent.anim-exit-right {
    opacity: 0; transform: translate3d(50px, 0, 0);
  }
  #playerCardContent.anim-enter-left {
    opacity: 0; transform: translate3d(-50px, 0, 0);
  }
  #playerCardContent.anim-enter-right {
    opacity: 0; transform: translate3d(50px, 0, 0);
  }
  #playerCardContent.anim-active {
    opacity: 1; transform: translate3d(0, 0, 0);
  }

  /* Empty state status tag */
  #emptyStateTag {
    position: absolute; left: 160px; top: 170px;
    display: none; align-items: center; gap: 10px;
    background: rgba(11,42,20,.75); border: 1.5px solid rgba(198,240,58,.5);
    border-radius: 20px; padding: 6px 18px;
    color: #c6f03a; font-size: 26px; letter-spacing: 2px;
    box-shadow: 0 0 15px rgba(198,240,58,.3); z-index: 4;
  }

  /* Floating admin controls */
  #adminControlBar {
    position: fixed; top: 14px; right: 16px; z-index: 9999;
    display: flex; align-items: center; gap: 10px;
    background: rgba(8, 28, 14, 0.88); backdrop-filter: blur(10px);
    border: 1px solid rgba(198, 240, 58, 0.35); border-radius: 40px;
    padding: 6px 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.6);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    transition: opacity 0.4s ease;
  }
  #adminControlBar.idle-hidden { opacity: 0.18; }
  #adminControlBar:hover { opacity: 1 !important; }
  .ctrl-btn {
    background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
    color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 13px;
    font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex;
    align-items: center; gap: 6px; transition: all .2s;
  }
  .ctrl-btn:hover {
    background: rgba(198,240,58,0.2); border-color: #c6f03a; color: #c6f03a;
  }
  .status-dot {
    width: 9px; height: 9px; border-radius: 50%; background: #28a745;
    box-shadow: 0 0 8px #28a745; display: inline-block;
  }
  .status-dot.connecting { background: #ffc107; box-shadow: 0 0 8px #ffc107; }
  .status-dot.disconnected { background: #dc3545; box-shadow: 0 0 8px #dc3545; }
  .league-badge {
    color: #38e8ff; font-size: 13px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.5px;
  }
</style>
@vite(['resources/js/app.js'])
</head>
<body>

<!-- Floating Admin Navigation Bar -->
<div id="adminControlBar">
  <span class="status-dot" id="liveStatusDot" title="Live Broadcast Connected"></span>
  <span class="league-badge" id="leagueBadgeText">{{ strtoupper($leagueType) }} LEAGUE</span>

  <button type="button" class="ctrl-btn" id="modeToggleBtn" onclick="toggleViewMode()" title="Switch Screen Mode">
    ⇋ Toggle View
  </button>

  @if(($availableLeagues ?? collect())->count() > 1)
    <div style="display: flex; gap: 4px;">
      @foreach($availableLeagues as $lg)
        <a href="{{ route('admin.drafting-screen', ['league' => $lg->slug]) }}"
           class="ctrl-btn {{ $leagueType === $lg->slug ? 'active' : '' }}"
           style="{{ $leagueType === $lg->slug ? 'background: rgba(198,240,58,0.25); border-color: #c6f03a; color: #c6f03a;' : '' }}">
          {{ $lg->name }}
        </a>
      @endforeach
    </div>
  @endif

  <button type="button" class="ctrl-btn" id="fsToggleBtn" onclick="toggleFullscreen()" title="Toggle Fullscreen">
    ⛶ Fullscreen
  </button>
  <a href="{{ route('admin.dashboard') }}" class="ctrl-btn" title="Back to Admin Dashboard">
    ← Admin Panel
  </a>
</div>

<div id="stage">
  <!-- Shared Background Textures -->
  <div class="tex blotch" id="texB"></div>
  <div class="tex fine" id="texF"></div>
  <div class="tex streak" id="texS"></div>
  <div class="vignette"></div>
  <canvas id="sparks" width="1920" height="1080"></canvas>

  <!-- Rotating Neon Star (Only visible during Card View, strictly hidden in Countdown View) -->
  <svg id="starSvg" viewBox="-750 -750 1500 1500" xmlns="http://www.w3.org/2000/svg">
    <g id="starRot">
      <polygon id="gapStar" fill="rgba(6,44,18,.55)"/>
      <g class="glow-yellow">
        <polygon id="yelStar" fill="#d2ee45"/>
      </g>
      <polygon id="innStar" fill="#1c7034"/>
      <polygon id="redStar" fill="none" stroke="#e5112e" stroke-width="15" stroke-linejoin="miter" class="glow-red"/>
      <g id="sticks" class="glow-white" stroke="#fff" stroke-linecap="round" fill="none"></g>
    </g>
  </svg>

  <!-- Shared Purple Left Sidebar -->
  <div id="side"><div id="sideTrack"></div></div>

  <!-- =======================================================================
       1. COUNTDOWN VIEW (ptpl-draft-countdown.html)
       Active during the round countdown timer
       ======================================================================= -->
  <div id="countdownView" class="view-mode is-hidden">
    <div class="timer" id="timer">
      <div class="countdown-content">
        <div id="countdownHeader">
          <div class="clock-badge {{ ($activeRoundData['isPaused'] ?? false) ? 'is-paused-badge' : '' }}" id="clockBadge">
            {{ ($activeRoundData['isPaused'] ?? false) ? '⏸ PAUSED' : '★ ON THE CLOCK ★' }}
          </div>
          <div class="clock-team" id="clockTeamName">
            {{ strtoupper($activeRoundData['currentTeamName'] ?? 'WAITING FOR TURN') }}
          </div>
          <div class="clock-meta" id="clockMetaInfo">
            ROUND {{ $activeRoundData['roundNumber'] ?? 1 }} • PICK {{ $activeRoundData['currentPickNumber'] ?? 1 }} • {{ strtoupper($activeRoundData['categoryName'] ?? 'DRAFT') }}
          </div>
        </div>
        <div id="digits">00:00</div>
      </div>
    </div>
  </div>

  <!-- =======================================================================
       2. PLAYER CARD VIEW (ptpl-draft-card.html)
       Active when pick is made or countdown ends
       ======================================================================= -->
  <div id="cardView" class="view-mode is-active">
    <!-- HUD decorative frames -->
    <div class="hud h1"><div class="hudside"><i></i><i></i><i></i></div></div>
    <div class="hud h2"><div class="hudticks"><i></i><i></i><i></i></div></div>

    <!-- Empty state banner -->
    <div id="emptyStateTag">
      <span>★</span> AWAITING FIRST PLAYER PICK <span>★</span>
    </div>

    <!-- Dynamic Player Card Content -->
    <div id="playerCardContent" class="anim-active">
      <!-- Picked By Team Pill -->
      <div id="teamPickBadge">
        <span class="pill-label">PICKED BY :</span>
        <span class="pill-name" id="playerTeam">
          {{ strtoupper($initialPick['teamName'] ?? ($activeRound ? 'READY FOR PICK' : 'AWAITING FIRST PICK')) }}
        </span>
      </div>

      <!-- Photo Container -->
      <div class="photo" id="playerPhoto">
        @if(!empty($initialPick['player']['passportPictureUrl']))
          <img id="playerPhotoImg"
               src="{{ $initialPick['player']['passportPictureUrl'] }}"
               alt="{{ $initialPick['player']['fullName'] ?? 'Player' }}"
               onerror="this.style.display='none'; document.getElementById('playerPhotoPlaceholder').style.display='flex';">
          <div id="playerPhotoPlaceholder" class="placeholder-avatar" style="display: none;">
            <svg width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
        @else
          <img id="playerPhotoImg" src="" alt="Player" style="display: none;"
               onerror="this.style.display='none'; document.getElementById('playerPhotoPlaceholder').style.display='flex';">
          <div id="playerPhotoPlaceholder" class="placeholder-avatar">
            <svg width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
        @endif
      </div>

      <!-- NICK NAME -->
      <div class="nicklbl">NICK NAME :</div>
      <div class="bar nick" style="left:486px;top:436px;width:424px;height:96px">
        <i class="flow"></i>
        <span class="val" id="playerNick">
          {{ strtoupper($initialPick['player']['nickName'] ?? '—') }}
        </span>
      </div>

      <!-- NAME -->
      <div class="bar name" style="left:160px;top:580px;width:750px">
        <i class="flow"></i>
        <span class="lbl">NAME :</span>
        <span class="val" id="playerName" style="margin-left: 14px; max-width: 530px;">
          {{ strtoupper($initialPick['player']['fullName'] ?? ($activeRound ? 'DRAFT IN PROGRESS' : 'AWAITING FIRST PICK')) }}
        </span>
      </div>

      <!-- CATEGORY -->
      <div class="bar cat" style="left:160px;top:700px;width:750px">
        <i class="flow"></i>
        <span class="lbl">CATEGORY :</span>
        <span class="val" id="playerCategory" style="margin-left: 14px; max-width: 440px;">
          {{ strtoupper($initialPick['player']['category'] ?? ($activeRound->category->name ?? '—')) }}
        </span>
      </div>

      <!-- ROLE -->
      <div class="bar role" style="left:160px;top:816px;width:364px;height:98px">
        <i class="flow"></i>
        <span class="lbl">ROLE :</span>
        <span class="val" id="playerRole" style="margin-left: 10px; max-width: 215px; font-size: 50px;">
          {{ strtoupper($initialPick['player']['role'] ?? '—') }}
        </span>
      </div>

      <!-- CITY -->
      <div class="bar city" style="left:544px;top:816px;width:366px;height:98px">
        <i class="flow"></i>
        <span class="lbl">CITY :</span>
        <span class="val" id="playerCity" style="margin-left: 10px; max-width: 215px; font-size: 50px;">
          {{ strtoupper($initialPick['player']['city'] ?? '—') }}
        </span>
      </div>

      <!-- Flares -->
      <div class="flare f1" style="left:318px;top:582px"></div>
      <div class="flare f2" style="left:770px;top:674px"></div>
    </div>
  </div>
</div>

<script>
(function () {
  /* ================= Server Initial State ================= */
  var initialPick = @json($initialPick);
  var initialRound = @json($activeRoundData);
  var currentLeagueType = @json($leagueType);
  var currentPickId = initialPick ? (parseInt(initialPick.pickId, 10) || 0) : 0;
  var pickTransitionCount = 0;
  var currentMode = 'card'; // 'countdown' | 'card'
  var currentTurnKey = null;
  var latestRoundData = initialRound;
  var latestPickData = initialPick;
  var showingPickedCard = false;

  var $ = function (id) { return document.getElementById(id); };
  var NS = 'http://www.w3.org/2000/svg';

  /* ================= Tweakable Visual Parameters ================= */
  var P = {
    cx: 1705, cy: 531.6,
    Rinner: 334, Ryellow: 531, Rred: 680,
    k: 0.382,
    rotSpeed: 18,
    rot0: 1.0,
    sparkVX: 200,
    texVX: 120,
    sideSpeed: 52
  };

  /* Fit stage to viewport proportionally (1920x1080) */
  var stage = $('stage');
  function fit() {
    var vw = window.innerWidth || document.documentElement.clientWidth;
    var vh = window.innerHeight || document.documentElement.clientHeight;
    var s = Math.min(vw / 1920, vh / 1080);
    var tx = ((vw - 1920 * s) / 2);
    var ty = ((vh - 1080 * s) / 2);
    stage.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + s + ')';
  }
  window.addEventListener('resize', fit);
  fit();

  /* Star Geometry */
  function starPts(R, k) {
    var pts = [];
    for (var i = 0; i < 10; i++) {
      var a = (-90 + 36 * i) * Math.PI / 180;
      var r = (i % 2 === 0) ? R : R * k;
      pts.push([r * Math.cos(a), r * Math.sin(a)]);
    }
    return pts;
  }
  function pstr(pts) { return pts.map(function (p) { return p[0].toFixed(2) + ',' + p[1].toFixed(2); }).join(' '); }

  $('gapStar').setAttribute('points', pstr(starPts(P.Rred, P.k)));
  $('yelStar').setAttribute('points', pstr(starPts(P.Ryellow, P.k)));
  $('innStar').setAttribute('points', pstr(starPts(P.Rinner, P.k)));
  $('redStar').setAttribute('points', pstr(starPts(P.Rred, P.k)));

  /* White neon sticks */
  function addSticks(R, offset, longT, shortT, longSide, width) {
    var g = $('sticks');
    for (var i = 0; i < 5; i++) {
      var tipA = (-90 + 72 * i) * Math.PI / 180;
      var T = [R * Math.cos(tipA), R * Math.sin(tipA)];
      [-1, 1].forEach(function (side) {
        var cA = tipA + side * 36 * Math.PI / 180;
        var C = [R * P.k * Math.cos(cA), R * P.k * Math.sin(cA)];
        var e = [C[0] - T[0], C[1] - T[1]];
        var len = Math.hypot(e[0], e[1]);
        var u = [e[0] / len, e[1] / len];
        var n = [-u[1], u[0]];
        var M = [(T[0] + C[0]) / 2, (T[1] + C[1]) / 2];
        var ax = [Math.cos(tipA), Math.sin(tipA)];
        var d = M[0] * ax[0] + M[1] * ax[1];
        var v = [M[0] - d * ax[0], M[1] - d * ax[1]];
        if (n[0] * v[0] + n[1] * v[1] < 0) n = [-n[0], -n[1]];
        var range = (side === longSide) ? longT : shortT;
        var p1 = [T[0] + e[0] * range[0] + n[0] * offset, T[1] + e[1] * range[0] + n[1] * offset];
        var p2 = [T[0] + e[0] * range[1] + n[0] * offset, T[1] + e[1] * range[1] + n[1] * offset];
        var l = document.createElementNS(NS, 'line');
        l.setAttribute('x1', p1[0].toFixed(1)); l.setAttribute('y1', p1[1].toFixed(1));
        l.setAttribute('x2', p2[0].toFixed(1)); l.setAttribute('y2', p2[1].toFixed(1));
        l.setAttribute('stroke-width', width);
        g.appendChild(l);
      });
    }
  }
  addSticks(P.Rred,    +32, [0.05, 0.64], [0.05, 0.62], +1, 8);
  addSticks(P.Ryellow, -21, [0.13, 0.86], [0.13, 0.56], -1, 6);

  /* Side Bar */
  var track = $('sideTrack');
  var UNIT = 1000;
  function chevDown() {
    return '<svg width="56" height="68" viewBox="0 0 46 56"><g fill="none" stroke="#b08ae8" stroke-width="8" stroke-linecap="square"><path d="M6 22 L23 6 L40 22"/><path d="M6 46 L23 30 L40 46"/></g></svg>';
  }
  function chevUp() {
    return '<svg width="56" height="38" viewBox="0 0 46 31"><path d="M2 29 L23 8 L44 29 L44 21 L23 0 L2 21 Z" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="miter"/></svg>';
  }
  var seq = [
    ['draft', '<div class="txt">DRAFT</div>'],
    ['dchev', chevDown()],
    ['ptpl',  '<div class="txt ptxt">PTPL 2026</div>'],
    ['uchev', chevUp()],
    ['ptpl b','<div class="txt ptxt">PTPL 2026</div>'],
    ['uchev last', chevUp()]
  ];
  var html = '';
  for (var rep = 0; rep < 4; rep++) {
    seq.forEach(function (s) { html += '<div class="sitem ' + s[0] + '">' + s[1] + '</div>'; });
  }
  track.innerHTML = html;
  var unitH = 0;
  Array.prototype.slice.call(track.children, 0, seq.length).forEach(function (c) { unitH += c.offsetHeight; });
  UNIT = unitH || UNIT;
  var ptxts = Array.prototype.slice.call(track.querySelectorAll('.ptxt'));

  /* Sparkles Canvas */
  var cv = $('sparks'), ctx = cv.getContext('2d');
  function rng(seed) { return function () { seed |= 0; seed = seed + 0x6D2B79F5 | 0; var t = Math.imul(seed ^ seed >>> 15, 1 | seed); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
  var rnd = rng(20261021);
  var sparks = [];
  for (var i = 0; i < 300; i++) {
    sparks.push({
      x: rnd() * 1920, y: rnd() * 1080,
      vx: P.sparkVX * (0.85 + rnd() * 0.3), vy: (rnd() - 0.5) * 14,
      w: 5 + rnd() * 8, h: 4 + rnd() * 5,
      f: 0.25 + rnd() * 0.9, ph: rnd(),
      c: rnd()
    });
  }

  var SPR = 100, sprites = [];
  for (var k = 0; k < 4; k++) {
    var sc2 = document.createElement('canvas'); sc2.width = sc2.height = SPR;
    var sx = sc2.getContext('2d'), cc = (k + 0.5) / 4;
    sx.fillStyle = 'rgba(255,' + (120 + cc * 70).toFixed(0) + ',' + (30 + cc * 30).toFixed(0) + ',.9)';
    sx.shadowColor = 'rgba(255,140,30,.95)'; sx.shadowBlur = 30;
    sx.beginPath(); sx.arc(SPR / 2, SPR / 2, 12, 0, 6.2832); sx.fill();
    sprites.push(sc2);
  }

  /* Main animation loop */
  var t0 = performance.now(), lastDim = null;
  var starSvg = $('starSvg'), texF = $('texF'), texB = $('texB'), texS = $('texS');

  function frame(now) {
    var t = Math.max(0, (now - t0) / 1000);

    // Star rotation + drift
    var w1 = 2 * Math.PI * t / 20, w2 = 2 * w1, w3 = 3 * w1, wp = 5 * w1, wp2 = 10 * w1;
    var dx = 9.171 + 1.755 * Math.cos(w1) - 16.431 * Math.sin(w1) - 0.084 * Math.cos(w2) - 0.343 * Math.sin(w2) - 0.289 * Math.cos(w3) - 0.087 * Math.sin(w3);
    var dy = -10.44 + 16.418 * Math.cos(w1) + 2.178 * Math.sin(w1) + 0.171 * Math.cos(w2) + 0.16 * Math.sin(w2) - 0.611 * Math.cos(w3) + 0.336 * Math.sin(w3);
    var sc = 0.9912 - 0.0281 * Math.cos(wp) + 0.0029 * Math.sin(wp) - 0.0008 * Math.cos(wp2) + 0.0017 * Math.sin(wp2);
    var rot = P.rot0 + P.rotSpeed * t;
    starSvg.style.transform = 'translate3d(' + dx.toFixed(2) + 'px,' + dy.toFixed(2) + 'px,0) rotate(' + rot.toFixed(3) + 'deg) scale(' + sc.toFixed(4) + ')';

    // Texture drift
    texF.style.transform = 'translate3d(' + ((P.texVX * t) % 512).toFixed(1) + 'px,0,0)';
    texB.style.transform = 'translate3d(' + ((P.texVX * 0.6 * t) % 1024).toFixed(1) + 'px,0,0)';
    texS.style.transform = 'translate3d(' + ((P.texVX * 0.8 * t) % 700).toFixed(1) + 'px,0,0)';

    // Side bar upward loop
    var off = (P.sideSpeed * t) % UNIT;
    track.style.transform = 'translate3d(0,' + (110 - UNIT - off).toFixed(1) + 'px,0)';
    var fl = Math.sin(t * 7.3) * Math.sin(t * 2.1);
    var dim = fl > 0.82;
    if (dim !== lastDim) {
      lastDim = dim;
      ptxts.forEach(function (el, idx) { el.style.opacity = (dim && idx % 2 === 0) ? 0.55 : 1; });
    }

    // Sparkles
    ctx.clearRect(0, 0, 1920, 1080);
    for (var i = 0; i < sparks.length; i++) {
      var s = sparks[i];
      var x = ((s.x + s.vx * t) % 2000 + 2000) % 2000 - 40;
      var y = ((s.y + s.vy * t) % 1080 + 1080) % 1080;
      var a = 0.25 + 0.75 * Math.abs(Math.sin(Math.PI * (s.f * t + s.ph)));
      var dw = SPR * (s.w * 0.9 / 24), dh = SPR * (s.h * 0.9 / 24);
      ctx.globalAlpha = a * 0.9;
      ctx.drawImage(sprites[Math.min(3, (s.c * 4) | 0)], x - dw / 2, y - dh / 2, dw, dh);
    }
    ctx.globalAlpha = 1;

    requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);

  /* Warm-up reveal gate */
  var started = false;
  function reveal() {
    if (started) return;
    started = true;
    DraftTimer._measure();
    requestAnimationFrame(function () {
      t0 = performance.now();
      stage.classList.add('ready');
      DraftTimer._reveal();
    });
  }
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(reveal);
  } else {
    requestAnimationFrame(reveal);
  }
  setTimeout(reveal, 150);

  /* =========================================================================
     DraftTimer Implementation (Authoritative Real-Time Countdown)
     ========================================================================= */
  var DraftTimer = (function () {
    var elTimer = $('timer'), elDigits = $('digits');
    var total = 60, remainingMs = 60000, running = false, serverDeadline = null, clockOffset = 0;
    var finished = false, lastShown = null, booted = false, wantStart = false, animFrameId = null;
    var api = { onTick: null, onFinish: null };

    function nowMs() { return Date.now(); }
    function curServerNow() { return Date.now() + clockOffset; }
    function curMs() {
      if (running && serverDeadline) {
        return Math.max(0, serverDeadline - curServerNow());
      }
      return remainingMs;
    }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function fmt(sec) {
      var big = Math.max(total, sec) >= 3600;
      var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), r = sec % 60;
      return { text: big ? (h + ':' + pad(m) + ':' + pad(r)) : (pad(Math.floor(sec / 60)) + ':' + pad(r)), big: big };
    }
    function draw(force) {
      var sec = Math.ceil(curMs() / 1000);
      if (!force && sec === lastShown) return;
      var changed = sec !== lastShown;
      lastShown = sec;
      var f = fmt(sec), ch = f.text, kids = elDigits.children;
      elDigits.classList.toggle('long', f.big);
      if (kids.length !== ch.length) {
        var htm = '';
        for (var i = 0; i < ch.length; i++) htm += '<span class="' + (ch[i] === ':' ? 'c' : 'd') + '">' + ch[i] + '</span>';
        elDigits.innerHTML = htm;
      } else {
        for (var j = 0; j < ch.length; j++) {
          var k = kids[j];
          if (k.textContent !== ch[j]) k.textContent = ch[j];
          var want = ch[j] === ':' ? 'c' : 'd';
          if (k.className !== want) k.className = want;
        }
      }
      if (changed && typeof api.onTick === 'function') { try { api.onTick(sec); } catch (e) {} }
    }

    api.updateClockOffset = function (serverTimeMs) {
      if (serverTimeMs) {
        var s = parseInt(serverTimeMs, 10);
        if (!isNaN(s) && s > 0) {
          clockOffset = s - Date.now();
        }
      }
    };

    api.set = function (sec, autostart) {
      sec = Math.max(0, +sec || 0);
      total = sec;
      remainingMs = sec * 1000;
      serverDeadline = null;
      running = false;
      finished = false;
      draw(true);
      if (autostart) api.start();
    };
    api.start = function (deadlineMs) {
      if (!booted) { wantStart = true; return; }
      if (deadlineMs) {
        serverDeadline = deadlineMs;
      } else if (!serverDeadline) {
        serverDeadline = curServerNow() + remainingMs;
      }
      running = true;
      finished = false;
      draw(true);
    };
    api.resume = api.start;
    api.pause = function () {
      wantStart = false;
      if (!running) return;
      remainingMs = curMs();
      serverDeadline = null;
      running = false;
      draw(true);
    };
    api.reset = function (autostart) {
      wantStart = false;
      finished = false;
      remainingMs = total * 1000;
      serverDeadline = null;
      if (autostart) {
        running = true;
        serverDeadline = curServerNow() + remainingMs;
      } else {
        running = false;
      }
      draw(true);
    };
    api.setRemaining = function (sec) {
      sec = Math.max(0, +sec || 0);
      remainingMs = sec * 1000;
      if (sec > total) total = sec;
      if (running) {
        serverDeadline = curServerNow() + remainingMs;
      }
      if (sec > 0) finished = false;
      draw(true);
    };
    api.add = function (sec) {
      var ms = Math.max(0, curMs() + (+sec || 0) * 1000);
      api.setRemaining(ms / 1000);
    };
    api.getState = function () {
      return { total: total, remaining: Math.ceil(curMs() / 1000), running: running, finished: finished };
    };

    function tick() {
      if (running) {
        if (curMs() <= 0) {
          running = false;
          remainingMs = 0;
          serverDeadline = null;
          finished = true;
          draw(true);
          if (typeof api.onFinish === 'function') { try { api.onFinish(); } catch (e) {} }
        } else {
          draw(false);
        }
      }
      animFrameId = requestAnimationFrame(tick);
    }
    animFrameId = requestAnimationFrame(tick);

    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden && running) {
          draw(true);
        }
      });
    }

    api._reveal = function () {
      booted = true;
      if (wantStart) { wantStart = false; api.start(); }
    };
    api._measure = function () {
      try {
        var c = document.createElement('canvas').getContext('2d');
        c.font = '100px "Bebas Neue"';
        var w = 0, d;
        for (d = 0; d <= 9; d++) w = Math.max(w, c.measureText('' + d).width);
        elDigits.style.setProperty('--cell', (w / 100 + 0.02).toFixed(3) + 'em');
        elDigits.style.setProperty('--colon', (c.measureText(':').width / 100 + 0.04).toFixed(3) + 'em');
        var m = c.measureText('0123456789');
        if (m.fontBoundingBoxAscent != null) {
          var fa = m.fontBoundingBoxAscent, fd = m.fontBoundingBoxDescent;
          var baselineFromTop = (100 - (fa + fd)) / 2 + fa;
          var glyphCentre = baselineFromTop - (m.actualBoundingBoxAscent - m.actualBoundingBoxDescent) / 2;
          elDigits.style.setProperty('--nudge', ((50 - glyphCentre) / 100).toFixed(4) + 'em');
        }
      } catch (e) {}
    };

    return api;
  })();
  window.DraftTimer = DraftTimer;

  /* ================= View Modes & Transitions ================= */
  var countdownView = $('countdownView');
  var cardView = $('cardView');

  function updateCountdownHeader(roundData) {
    if (!roundData) return;
    if (roundData.currentTeamName) {
      $('clockTeamName').textContent = roundData.currentTeamName.toUpperCase();
    }
    var metaParts = [];
    var rNum = roundData.roundNumber || roundData.roundId;
    if (rNum) metaParts.push('ROUND ' + rNum);
    if (roundData.currentPickNumber) metaParts.push('PICK ' + roundData.currentPickNumber);
    if (roundData.categoryName) metaParts.push(roundData.categoryName.toUpperCase());
    $('clockMetaInfo').textContent = metaParts.length ? metaParts.join(' • ') : 'LIVE DRAFT';

    var badge = $('clockBadge');
    if (badge) {
      if (Boolean(roundData.isPaused)) {
        badge.textContent = '⏸ PAUSED';
        badge.classList.add('is-paused-badge');
      } else {
        badge.textContent = '★ ON THE CLOCK ★';
        badge.classList.remove('is-paused-badge');
      }
    }
  }

  function showCountdownView() {
    currentMode = 'countdown';

    stage.classList.remove('mode-card');
    stage.classList.add('mode-countdown');
    var star = $('starSvg');
    if (star) star.style.display = 'none';

    cardView.classList.remove('is-active');
    cardView.classList.add('is-hidden');

    countdownView.classList.remove('is-hidden');
    void countdownView.offsetWidth;
    countdownView.classList.add('is-active');

    if (latestRoundData) {
      updateCountdownHeader(latestRoundData);
    }
  }

  function showCardView(pickData) {
    currentMode = 'card';

    stage.classList.remove('mode-countdown');
    stage.classList.add('mode-card');
    var star = $('starSvg');
    if (star) star.style.display = 'block';

    countdownView.classList.remove('is-active');
    countdownView.classList.add('is-hidden');

    cardView.classList.remove('is-hidden');
    void cardView.offsetWidth;
    cardView.classList.add('is-active');

    var pData = pickData || latestPickData;
    if (pData && pData.player) {
      renderPlayerDOM(pData);
    } else {
      var emptyTag = $('emptyStateTag');
      if (emptyTag) emptyTag.style.display = 'flex';
    }
  }

  function buildTurnInstanceKey(roundData) {
    if (!roundData) return 'none';
    var rNum = roundData.roundNumber || roundData.roundId || roundData.id || 0;
    var pNum = roundData.currentPickNumber || 0;
    var tId = roundData.currentTeamId || 0;
    var startedAt = roundData.turnStartedAt || roundData.currentTurnStartedAtTs || roundData.currentTurnStartedAt || '';
    return rNum + '-' + pNum + '-' + tId + '@' + startedAt;
  }

  function switchToCountdownView(roundData, options) {
    if (!roundData) return;
    latestRoundData = roundData;
    options = options || {};

    if (roundData.serverTimeMs) {
      DraftTimer.updateClockOffset(roundData.serverTimeMs);
    }

    var totalSec = parseInt(roundData.turnTimeSeconds, 10) || 60;
    var hasRemaining = (typeof roundData.remainingTurnSeconds !== 'undefined' && roundData.remainingTurnSeconds !== null)
      ? roundData.remainingTurnSeconds
      : (typeof roundData.remainingSeconds !== 'undefined' && roundData.remainingSeconds !== null ? roundData.remainingSeconds : null);

    var remainingSec = (hasRemaining !== null) ? parseInt(hasRemaining, 10) : totalSec;
    var turnStartedAtMs = (roundData.turnStartedAtMs !== undefined && roundData.turnStartedAtMs !== null)
      ? parseInt(roundData.turnStartedAtMs, 10)
      : null;

    // If turn is active but unstarted (remaining is 0 because turnStartedAt is null and not paused)
    if (remainingSec <= 0 && !roundData.isPaused && !roundData.turnStartedAt && !roundData.currentTurnStartedAtTs && !turnStartedAtMs) {
      remainingSec = totalSec;
    }

    var isPaused = Boolean(roundData.isPaused);
    var turnKey = buildTurnInstanceKey(roundData);
    var isNewTurn = (turnKey !== currentTurnKey);
    var isForceReset = Boolean(options.forceReset) ||
      (typeof roundData.message === 'string' && roundData.message.toLowerCase().includes('reset'));

    updateCountdownHeader(roundData);

    var serverDeadline = (turnStartedAtMs && !isPaused) ? (turnStartedAtMs + (totalSec * 1000)) : null;

    if (isNewTurn || isForceReset) {
      currentTurnKey = turnKey;
      DraftTimer.set(totalSec, false);
      DraftTimer.setRemaining(remainingSec);
      if (isPaused || remainingSec <= 0) {
        DraftTimer.pause();
      } else {
        DraftTimer.start(serverDeadline);
      }
    } else {
      var timerState = DraftTimer.getState();
      if (isPaused) {
        // Explicitly paused on server
        DraftTimer.pause();
        if (remainingSec !== null && remainingSec !== undefined) {
          DraftTimer.setRemaining(remainingSec);
        }
      } else {
        // Active/running state
        if (!timerState.running) {
          // Transition: was paused, now resuming!
          if (remainingSec > 0) {
            DraftTimer.setRemaining(remainingSec);
          }
          DraftTimer.start(serverDeadline);
        } else if (serverDeadline) {
          if (options.source === 'poll') {
            // Polling source cannot overwrite healthy running countdown unless drift is massive (> 5s)
            var curRem = timerState.remaining;
            if (Math.abs(curRem - remainingSec) > 5) {
              DraftTimer.start(serverDeadline);
            }
          } else {
            DraftTimer.start(serverDeadline);
          }
        }
      }
    }

    showCountdownView();
  }

  function switchToCardView(pickData) {
    if (pickData) {
      latestPickData = pickData;
    }
    showCardView(latestPickData);
  }

  // When timer finishes (hits 0), freeze at 00:00 until a pick is made or round completes
  DraftTimer.onFinish = function () {
    console.log('[DraftingScreen] Countdown timer finished at 00:00.');
  };

  /* ================= Player Card Update & Alternating Transitions ================= */
  function renderPlayerDOM(data) {
    if (!data || !data.player) {
      $('emptyStateTag').style.display = 'flex';
      $('playerTeam').textContent = 'AWAITING FIRST PICK';
      $('playerNick').textContent = '—';
      $('playerName').textContent = 'AWAITING FIRST PICK';
      $('playerCategory').textContent = '—';
      $('playerRole').textContent = 'DRAFT IN PROGRESS';
      $('playerCity').textContent = '—';
      var photoImg = $('playerPhotoImg');
      var placeholder = $('playerPhotoPlaceholder');
      if (photoImg) photoImg.style.display = 'none';
      if (placeholder) placeholder.style.display = 'flex';
      return;
    }

    $('emptyStateTag').style.display = 'none';
    var p = data.player;

    // Team name
    $('playerTeam').textContent = data.teamName || '—';

    // Nickname
    $('playerNick').textContent = (p.nickName && p.nickName.trim()) ? p.nickName.toUpperCase() : '—';

    // Full name
    var nameEl = $('playerName');
    var fullName = p.fullName || '—';
    nameEl.textContent = fullName.toUpperCase();
    if (fullName.length > 22) {
      nameEl.style.fontSize = '40px';
    } else if (fullName.length > 16) {
      nameEl.style.fontSize = '48px';
    } else {
      nameEl.style.fontSize = '60px';
    }

    // Category
    $('playerCategory').textContent = (p.category && p.category.trim()) ? p.category.toUpperCase() : '—';

    // Role
    var roleEl = $('playerRole');
    var roleText = (p.role && p.role.trim()) ? p.role.toUpperCase() : '—';
    roleEl.textContent = roleText;
    if (roleText.length > 14) {
      roleEl.style.fontSize = '38px';
    } else {
      roleEl.style.fontSize = '50px';
    }

    // City
    var cityEl = $('playerCity');
    var cityText = (p.city && p.city.trim()) ? p.city.toUpperCase() : '—';
    cityEl.textContent = cityText;
    if (cityText.length > 12) {
      cityEl.style.fontSize = '38px';
    } else {
      cityEl.style.fontSize = '50px';
    }

    // Photo
    var photoImg = $('playerPhotoImg');
    var placeholder = $('playerPhotoPlaceholder');
    if (p.passportPictureUrl) {
      photoImg.src = p.passportPictureUrl;
      photoImg.style.display = 'block';
      if (placeholder) placeholder.style.display = 'none';
    } else {
      if (photoImg) photoImg.style.display = 'none';
      if (placeholder) placeholder.style.display = 'flex';
    }
  }

  // Handle incoming pick with immediate DOM render and rotating neon star
  function onNewPlayerPicked(pickData) {
    if (!pickData || !pickData.pickId) return;

    if (pickData.serverTimeMs) {
      DraftTimer.updateClockOffset(pickData.serverTimeMs);
    }

    if (pickData.stateVersion) {
      var v = parseInt(pickData.stateVersion, 10);
      if (!isNaN(v) && v > 0) {
        latestStateVersion = Math.max(latestStateVersion, v);
      }
    }

    var incomingPickId = parseInt(pickData.pickId, 10);
    if (incomingPickId <= currentPickId) {
      console.log('[DraftingScreen] Ignored older/duplicate pick:', incomingPickId, '<=', currentPickId);
      return;
    }

    currentPickId = incomingPickId;
    latestPickData = pickData;
    showingPickedCard = true;
    pickTransitionCount++;

    // Immediately switch to Card View so the audience sees the picked player with neon star
    showCardView(pickData);

    var isFromLeft = (pickTransitionCount % 2 === 1);
    var content = $('playerCardContent');
    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    renderPlayerDOM(pickData);

    if (!prefersReducedMotion && content) {
      content.className = isFromLeft ? 'anim-enter-left' : 'anim-enter-right';
      void content.offsetWidth;
      content.className = 'anim-active';
    }
  }

  /* ================= Initial View State Initialization ================= */
  if (initialRound && initialRound.status === 'active') {
    // If active round is paused and an initial pick is present, show player card
    if (initialRound.isPaused && initialPick && initialPick.player) {
      showingPickedCard = true;
      switchToCardView(initialPick);
    } else {
      switchToCountdownView(initialRound, { source: 'init' });
    }
  } else {
    // No active round, or round is completed -> Show Player Card View with latest pick!
    showingPickedCard = true;
    switchToCardView(initialPick);
    if (!initialPick || !initialPick.player) {
      var emptyTag = $('emptyStateTag');
      if (emptyTag) emptyTag.style.display = 'flex';
    }
  }

  /* ================= Real-Time Echo / Reverb Listener ================= */
  var echoInitAttempts = 0;
  function setupEchoListener() {
    if (typeof window.Echo === 'undefined') {
      echoInitAttempts++;
      if (echoInitAttempts === 1 && typeof window !== 'undefined') {
        window.addEventListener('echo:ready', function () {
          setupEchoListener();
        }, { once: true });
      }
      if (echoInitAttempts > 15) {
        console.warn('[DraftingScreen] Echo not yet available, will keep retrying in background...');
      }
      setTimeout(setupEchoListener, 300);
      return;
    }

    var channelNames = ['draft.league.' + currentLeagueType];
    if (currentLeagueType === 'male') {
      channelNames.push('draft.league.males');
    } else if (currentLeagueType === 'males') {
      channelNames.push('draft.league.male');
    } else if (currentLeagueType === 'female') {
      channelNames.push('draft.league.females');
    } else if (currentLeagueType === 'females') {
      channelNames.push('draft.league.female');
    }

    channelNames.forEach(function (cName) {
      console.log('[DraftingScreen] Subscribing to channel:', cName);
      var channel = window.Echo.channel(cName);

      // 1. Turn changed / countdown updated
      channel.listen('.draft.turn.changed', function (data) {
        console.log('[DraftingScreen] .draft.turn.changed event received on ' + cName + ':', data);
        handleTurnChanged(data);
      });
      channel.listen('draft.turn.changed', function (data) {
        console.log('[DraftingScreen] draft.turn.changed event received on ' + cName + ':', data);
        handleTurnChanged(data);
      });
      channel.listen('DraftTurnChanged', function (data) {
        console.log('[DraftingScreen] DraftTurnChanged event received on ' + cName + ':', data);
        handleTurnChanged(data);
      });

      // 2. Player picked
      channel.listen('.draft.player.picked', function (data) {
        console.log('[DraftingScreen] .draft.player.picked event received on ' + cName + ':', data);
        onNewPlayerPicked(data);
      });
      channel.listen('draft.player.picked', function (data) {
        console.log('[DraftingScreen] draft.player.picked event received on ' + cName + ':', data);
        onNewPlayerPicked(data);
      });
      channel.listen('DraftPlayerPicked', function (data) {
        console.log('[DraftingScreen] DraftPlayerPicked event received on ' + cName + ':', data);
        onNewPlayerPicked(data);
      });
    });

    // WebSocket connection lifecycle
    if (window.Echo.connector && window.Echo.connector.pusher) {
      var conn = window.Echo.connector.pusher.connection;
      conn.bind('connected', function () {
        console.log('[DraftingScreen] WebSocket connected.');
        var dot = $('liveStatusDot');
        if (dot) dot.className = 'status-dot';
        fetchLatestAuthoritativePick();
      });
      conn.bind('connecting', function () {
        var dot = $('liveStatusDot');
        if (dot) dot.className = 'status-dot connecting';
      });
      conn.bind('disconnected', function () {
        var dot = $('liveStatusDot');
        if (dot) dot.className = 'status-dot disconnected';
      });
      conn.bind('unavailable', function () {
        var dot = $('liveStatusDot');
        if (dot) dot.className = 'status-dot disconnected';
      });
    }
  }

  var lastHandledTurnSig = null;
  var lastHandledTurnTime = 0;
  var latestStateVersion = 0;

  function handleTurnChanged(data) {
    if (!data) return;

    if (data.serverTimeMs) {
      DraftTimer.updateClockOffset(data.serverTimeMs);
    }

    if (data.stateVersion) {
      var v = parseInt(data.stateVersion, 10);
      if (!isNaN(v) && v > 0) {
        if (latestStateVersion && v < latestStateVersion) {
          console.log('[DraftingScreen] Stale event stateVersion ' + v + ' < ' + latestStateVersion + ' rejected.');
          return;
        }
        latestStateVersion = Math.max(latestStateVersion, v);
      }
    }

    var rId = data.roundId || data.id || 0;
    var pNum = data.currentPickNumber || 0;
    var tId = data.currentTeamId || 0;
    var isP = Boolean(data.isPaused);
    var rem = (data.remainingTurnSeconds !== undefined && data.remainingTurnSeconds !== null)
      ? data.remainingTurnSeconds
      : (data.remainingSeconds !== undefined && data.remainingSeconds !== null ? data.remainingSeconds : '');
    var sig = [rId, pNum, tId, data.status, isP, rem, data.message || ''].join(':');
    var now = Date.now();
    if (sig === lastHandledTurnSig && (now - lastHandledTurnTime < 400)) {
      // Duplicate event within 400ms across duplicate channels/listeners
      return;
    }
    lastHandledTurnSig = sig;
    lastHandledTurnTime = now;

    console.log('[DraftingScreen] handleTurnChanged event processed:', data);
    latestRoundData = data;

    var isReset = Boolean(data.forceReset) ||
      (typeof data.message === 'string' && data.message.toLowerCase().includes('reset'));

    // If round completed, switch to Card View showing the latest pick!
    if (data.status === 'completed') {
      DraftTimer.pause();
      showingPickedCard = true;
      showCardView();
      return;
    }

    // Active round in progress (running, paused, or reset)
    if (data.status === 'active') {
      var isPaused = Boolean(data.isPaused);

      // If we are currently displaying a freshly confirmed player pick:
      if (showingPickedCard && !isReset) {
        if (isPaused) {
          // The next turn is queued up in paused state waiting to be resumed.
          // KEEP SHOWING THE PLAYER CARD with the neon star!
          var totalSec = parseInt(data.turnTimeSeconds, 10) || 60;
          var remSec = (data.remainingTurnSeconds !== undefined && data.remainingTurnSeconds !== null)
            ? parseInt(data.remainingTurnSeconds, 10)
            : (data.remainingSeconds !== undefined && data.remainingSeconds !== null ? parseInt(data.remainingSeconds, 10) : totalSec);
          currentTurnKey = buildTurnInstanceKey(data);
          DraftTimer.set(totalSec, false);
          DraftTimer.setRemaining(remSec);
          DraftTimer.pause();
          return;
        } else {
          // The next turn has now actively started/resumed!
          showingPickedCard = false;
          switchToCountdownView(data, { source: 'broadcast', forceReset: false });
          return;
        }
      }

      // Normal turn countdown or pause/resume/reset:
      switchToCountdownView(data, { source: 'broadcast', forceReset: isReset });
    }
  }

  // Authoritative fallback fetch
  function fetchLatestAuthoritativePick() {
    var url = '{{ route("admin.drafting-screen.latest") }}?league=' + encodeURIComponent(currentLeagueType);
    fetch(url, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (!data) return;

      if (data.serverTimeMs) {
        DraftTimer.updateClockOffset(data.serverTimeMs);
      }

      if (data.stateVersion) {
        var v = parseInt(data.stateVersion, 10);
        if (!isNaN(v) && v > 0 && latestStateVersion && v < latestStateVersion) {
          return; // Ignore stale polling snapshot
        }
      }

      // Handle newer pick
      if (data.pickId && data.pickId > currentPickId) {
        console.log('[DraftingScreen] Recovered newer pick from server:', data.pickId);
        onNewPlayerPicked(data);
        return;
      }

      // Handle active round countdown synchronization
      if (data.activeRound && data.activeRound.status === 'active') {
        var ar = data.activeRound;
        if (ar.stateVersion) {
          var arv = parseInt(ar.stateVersion, 10);
          if (!isNaN(arv) && arv > 0 && latestStateVersion && arv < latestStateVersion) {
            return; // Ignore stale round info from poll
          }
        }
        latestRoundData = ar;

        if (showingPickedCard) {
          if (!ar.isPaused) {
            // Next turn resumed
            showingPickedCard = false;
            switchToCountdownView(ar, { source: 'poll' });
          } else {
            // Keep player card, sync timer in background
            var totalSec = parseInt(ar.turnTimeSeconds, 10) || 60;
            var rem = ar.remainingSeconds !== undefined && ar.remainingSeconds !== null ? parseInt(ar.remainingSeconds, 10) : totalSec;
            currentTurnKey = buildTurnInstanceKey(ar);
            DraftTimer.set(totalSec, false);
            DraftTimer.setRemaining(rem);
            DraftTimer.pause();
          }
        } else {
          switchToCountdownView(ar, { source: 'poll' });
        }
      } else if (!data.activeRound || data.activeRound.status === 'completed') {
        // Round is closed or finished
        DraftTimer.pause();
        if (currentMode === 'countdown') {
          showCardView();
        }
      }
    })
    .catch(function (err) {
      console.warn('[DraftingScreen] Sync check error:', err);
    });
  }

  // Periodic safety sync (every 5 seconds if disconnected, every 15 seconds if connected)
  setInterval(function () {
    var isConnected = window.Echo && window.Echo.connector && window.Echo.connector.pusher &&
      window.Echo.connector.pusher.connection.state === 'connected';
    if (!isConnected) {
      fetchLatestAuthoritativePick();
    }
  }, 5000);

  setInterval(function () {
    var isConnected = window.Echo && window.Echo.connector && window.Echo.connector.pusher &&
      window.Echo.connector.pusher.connection.state === 'connected';
    if (isConnected) {
      fetchLatestAuthoritativePick();
    }
  }, 15000);

  // Sync on tab visibility change
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      fetchLatestAuthoritativePick();
    }
  });

  setupEchoListener();

  /* ================= Idle Admin Control Bar Auto-Hide ================= */
  var ctrlBar = $('adminControlBar');
  var idleTimer = null;
  function resetIdleTimer() {
    if (ctrlBar) ctrlBar.classList.remove('idle-hidden');
    clearTimeout(idleTimer);
    idleTimer = setTimeout(function () {
      if (ctrlBar) ctrlBar.classList.add('idle-hidden');
    }, 3500);
  }
  document.addEventListener('mousemove', resetIdleTimer);
  document.addEventListener('touchstart', resetIdleTimer);
  resetIdleTimer();

  /* Keyboard Shortcuts for manual test / broadcast ops */
  document.addEventListener('keydown', function (e) {
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
    if (e.key === 't' || e.key === 'T') {
      toggleViewMode();
    } else if (e.code === 'Space') {
      var state = DraftTimer.getState();
      if (state.running) DraftTimer.pause(); else DraftTimer.start();
    } else if (e.key === 'r' || e.key === 'R') {
      DraftTimer.reset();
    }
  });

  /* Expose helpers for testing & fullscreen */
  window.__draftingScreen = {
    getCurrentPickId: function () { return currentPickId; },
    getMode: function () { return currentMode; },
    showCountdownView: showCountdownView,
    showCardView: showCardView,
    switchToCountdown: switchToCountdownView,
    switchToCard: switchToCardView,
    onNewPlayerPicked: onNewPlayerPicked,
    handleTurnChanged: handleTurnChanged,
    fetchLatest: fetchLatestAuthoritativePick
  };
})();

function toggleViewMode() {
  if (window.__draftingScreen) {
    if (window.__draftingScreen.getMode() === 'countdown') {
      window.__draftingScreen.showCardView();
    } else {
      window.__draftingScreen.showCountdownView();
    }
  }
}

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(function () {});
  } else {
    document.exitFullscreen().catch(function () {});
  }
}
</script>
</body>
</html>
