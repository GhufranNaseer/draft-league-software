<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
<meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
<meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
<meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">
<script>
    window.__REVERB_KEY__ = "{{ config('broadcasting.connections.reverb.key') }}";
</script>
<title>PTPL 2026 - Live Draft Screen</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&family=Bebas+Neue&display=swap" rel="stylesheet">
@vite(['resources/js/app.js'])
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
  #stage.mode-countdown #starSvg, #stage.mode-skipped #starSvg, #stage.mode-round-closed #starSvg { display: none !important; }

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
  #skippedView.view-mode.is-active {
    display: flex !important;
  }
  #roundClosedView.view-mode.is-active {
    display: flex !important;
    opacity: 1 !important;
    z-index: 100 !important;
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

  /* Heartbeat animation for final 10 seconds of countdown */
  #digits.timer-heartbeat {
    animation: timerHeartbeat 0.9s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
    transform-origin: center center;
    will-change: transform, filter;
  }

  @keyframes timerHeartbeat {
    0% {
      transform: translateY(var(--nudge, 0em)) scale(1);
      filter: drop-shadow(0 0 0 rgba(255, 42, 68, 0));
    }
    14% {
      transform: translateY(var(--nudge, 0em)) scale(1.07);
      filter: drop-shadow(0 0 35px rgba(255, 42, 68, 0.85));
    }
    28% {
      transform: translateY(var(--nudge, 0em)) scale(1);
      filter: drop-shadow(0 0 10px rgba(255, 42, 68, 0.3));
    }
    42% {
      transform: translateY(var(--nudge, 0em)) scale(1.04);
      filter: drop-shadow(0 0 25px rgba(255, 42, 68, 0.65));
    }
    70% {
      transform: translateY(var(--nudge, 0em)) scale(1);
      filter: drop-shadow(0 0 0 rgba(255, 42, 68, 0));
    }
    100% {
      transform: translateY(var(--nudge, 0em)) scale(1);
      filter: drop-shadow(0 0 0 rgba(255, 42, 68, 0));
    }
  }

  /* Skipped turn card replacing countdown digits */
  .skipped-turn-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    z-index: 7;
    padding: 28px 70px;
    border-radius: 40px;
    background: radial-gradient(circle, rgba(90, 10, 25, 0.92) 0%, rgba(35, 5, 12, 0.96) 100%);
    border: 4.5px solid #ff2a44;
    box-shadow: 0 0 60px rgba(255, 42, 68, 0.65), inset 0 0 35px rgba(255, 42, 68, 0.35);
    margin-top: 14px;
    min-width: 960px;
    max-width: 1500px;
    animation: skippedCardGlow 2s ease-in-out infinite alternate;
  }
  @keyframes skippedCardGlow {
    0% { transform: scale(0.99); box-shadow: 0 0 40px rgba(255, 42, 68, 0.5); }
    100% { transform: scale(1.01); box-shadow: 0 0 75px rgba(255, 42, 68, 0.9); }
  }
  .skipped-badge-glory {
    display: inline-flex;
    align-items: center;
    background: #ff1e3c;
    color: #ffffff;
    font-size: 52px;
    letter-spacing: 8px;
    font-weight: 700;
    padding: 10px 48px;
    border-radius: 44px;
    box-shadow: 0 0 30px rgba(255, 30, 60, 0.95);
    margin-bottom: 14px;
  }
  .skipped-team-name {
    font-size: 115px;
    letter-spacing: 3px;
    color: #ffffff;
    font-weight: 700;
    line-height: 1.05;
    text-transform: uppercase;
    text-shadow: 0 0 35px rgba(255,255,255,0.9), 0 4px 18px rgba(0,0,0,0.95);
    max-width: 1400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .skipped-meta-line {
    font-size: 54px;
    letter-spacing: 4px;
    color: #ffb703;
    font-weight: 700;
    text-transform: uppercase;
    text-shadow: 0 0 24px rgba(255, 183, 3, 0.85);
    margin-top: 6px;
  }
  .skipped-full-summary {
    font-size: 36px;
    letter-spacing: 4px;
    color: #ffdede;
    font-weight: 700;
    text-transform: uppercase;
    margin-top: 12px;
    border-top: 2px solid rgba(255,255,255,0.22);
    padding-top: 12px;
    width: 100%;
  }

  /* =========================================================================
     3. SKIPPED VIEW (State C) - Full Stage Layout
     ========================================================================= */
  #skippedView {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1920px;
    height: 1080px;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 8;
  }
  .skipped-screen-backdrop {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, rgba(130, 10, 25, 0.55) 0%, rgba(18, 3, 7, 0.94) 100%);
    pointer-events: none;
  }
  .skipped-screen-container {
    position: relative;
    z-index: 9;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 50px 96px;
    border-radius: 46px;
    background: radial-gradient(circle, rgba(95, 10, 26, 0.96) 0%, rgba(28, 4, 10, 0.98) 100%);
    border: 5px solid #ff2a44;
    box-shadow: 0 0 90px rgba(255, 42, 68, 0.8), inset 0 0 50px rgba(255, 42, 68, 0.4);
    min-width: 1050px;
    max-width: 1600px;
    animation: skippedCardGlow 2.2s ease-in-out infinite alternate;
  }
  .skipped-waiting-status {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    font-size: 26px;
    letter-spacing: 3px;
    color: #ffb7b7;
    font-weight: 600;
    margin-top: 24px;
    text-transform: uppercase;
  }
  .skipped-pulsing-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #ff2a44;
    box-shadow: 0 0 16px #ff2a44;
    animation: skippedPulse 1.2s infinite ease-in-out;
  }
  @keyframes skippedPulse {
    0%, 100% { transform: scale(0.9); opacity: 0.6; }
    50% { transform: scale(1.3); opacity: 1; }
  }

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

  /* =========================================================================
     4. ROUND CLOSED VIEW (ptpl-draft-round-closed.html)
     Scoped & Precise 1920x1080 Stage Overlay
     ========================================================================= */
  :root {
    --gold: #f6c90e;
    --amber: #ffb703;
    --lime: #c6f03a;
    --crimson: #ff1e3c;
    --white: #fff;
  }

  #roundClosedView {
    position: absolute; left: 0; top: 0; width: 1920px; height: 1080px; z-index: 100;
    display: flex; align-items: center; justify-content: center; pointer-events: none;
  }
  .rc-backing {
    position: absolute; left: 0; top: 0; width: 1920px; height: 1080px;
    background: radial-gradient(ellipse 1100px 560px at 50% 50%, rgba(0,14,6,.55), rgba(0,14,6,0) 72%);
    opacity: 0;
  }
  .rc-panel {
    position: relative; width: 1380px; height: 880px; box-sizing: border-box; border-radius: 36px; overflow: hidden;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: linear-gradient(180deg, rgba(5,34,16,.80) 0%, rgba(2,22,10,.88) 100%);
    box-shadow: 0 0 90px rgba(246,201,14,.20), 0 34px 90px rgba(0,0,0,.55);
    opacity: 0; transform: scale(.9);
  }
  .rc-panel::before {
    content: ""; position: absolute; inset: 0; border-radius: inherit; padding: 5px; pointer-events: none;
    background: linear-gradient(100deg, var(--gold) 0%, var(--lime) 50%, var(--amber) 100%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude;
  }
  .rc-panel::after {
    content: ""; position: absolute; inset: 5px; border-radius: 31px; pointer-events: none;
    background:
      repeating-linear-gradient(0deg,  rgba(198,240,58,.045) 0 1px, transparent 1px 36px),
      repeating-linear-gradient(90deg, rgba(198,240,58,.045) 0 1px, transparent 1px 36px);
  }
  .rc-sweep {
    position: absolute; top: -10%; bottom: -10%; left: 0; width: 260px; pointer-events: none; z-index: 4;
    background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.22), rgba(255,255,255,0));
    transform: translate3d(-400px,0,0) skewX(-18deg); opacity: 0;
  }
  .rc-content { position: relative; z-index: 3; display: flex; flex-direction: column; align-items: center; }

  /* top glory badge */
  .rc-badge { display: flex; align-items: center; gap: 34px; opacity: 0; }
  #roundClosedBadge {
    font-family: 'Barlow Condensed', 'Bebas Neue', Impact, sans-serif; font-weight: 700;
    font-size: 58px; letter-spacing: .207em; line-height: 1; color: var(--gold); white-space: nowrap;
    text-shadow: 0 0 22px rgba(246,201,14,.75), 0 0 48px rgba(246,201,14,.35), 0 3px 4px rgba(0,0,0,.55);
  }
  .rc-badge .ln { display: block; width: 170px; height: 4px; border-radius: 4px; background: linear-gradient(90deg, rgba(246,201,14,0), var(--gold)); box-shadow: 0 0 12px rgba(246,201,14,.8); }
  .rc-badge .ln.r { background: linear-gradient(90deg, var(--gold), rgba(246,201,14,0)); }

  /* huge round number */
  .rc-num { position: relative; margin-top: 6px; display: flex; justify-content: center; }
  .rc-flash {
    position: absolute; left: 50%; top: 50%; width: 1100px; height: 420px; margin: -210px 0 0 -550px; opacity: 0; pointer-events: none;
    background: radial-gradient(ellipse at center, rgba(255,226,120,.95) 0%, rgba(246,201,14,.45) 30%, rgba(246,201,14,0) 68%);
  }
  .rc-numwrap { position: relative; opacity: 0; }
  #roundClosedNumber {
    display: inline-block; white-space: nowrap;
    font-family: 'Bebas Neue', 'Barlow Condensed', Impact, sans-serif; font-size: 340px; line-height: .98; letter-spacing: .012em;
    color: transparent; background: linear-gradient(180deg, #ffffff 0%, #ffffff 52%, #ffe38a 100%);
    -webkit-background-clip: text; background-clip: text;
    filter: drop-shadow(0 9px 0 rgba(0,0,0,.38)) drop-shadow(0 0 38px rgba(246,201,14,.50));
  }

  /* category */
  .rc-cat {
    margin-top: 2px; opacity: 0; white-space: nowrap; display: flex; align-items: baseline; gap: .3em;
    font-family: 'Barlow Condensed', 'Bebas Neue', Impact, sans-serif; font-weight: 700; font-size: 88px; letter-spacing: .18em; line-height: 1;
    text-shadow: 0 4px 6px rgba(0,0,0,.55);
  }
  #roundClosedCategory { color: var(--gold); text-shadow: 0 0 26px rgba(246,201,14,.55), 0 4px 6px rgba(0,0,0,.55); }
  .rc-cat .sfx { color: var(--lime); text-shadow: 0 0 22px rgba(198,240,58,.45), 0 4px 6px rgba(0,0,0,.55); }

  /* CLOSED pill */
  .rc-pill {
    position: relative; margin-top: 40px; height: 150px; padding: 0 84px 0 70px; box-sizing: border-box; border-radius: 28px;
    display: flex; align-items: center; gap: 34px; isolation: isolate;
    transform: skewX(-12deg); opacity: 0;
  }
  .rc-pill .fill { position: absolute; inset: 0; border-radius: inherit; z-index: -1; background: linear-gradient(180deg, #4a0612 0%, #26020a 100%); }
  .rc-pill::before {
    content: ""; position: absolute; inset: 0; border-radius: inherit; padding: 5px; z-index: 2; pointer-events: none;
    background: linear-gradient(100deg, var(--crimson) 0%, var(--amber) 50%, var(--crimson) 100%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude;
  }
  .rc-pill::after {
    content: ""; position: absolute; inset: -6px; border-radius: 34px; z-index: -2; pointer-events: none;
    background: linear-gradient(100deg, var(--crimson), var(--amber) 50%, var(--crimson));
    filter: blur(22px); opacity: .45;
  }
  .rc-pill .dot { width: 30px; height: 30px; border-radius: 50%; background: var(--crimson); box-shadow: 0 0 18px 4px rgba(255,30,60,.9); flex: none; }
  #roundClosedStatus {
    font-family: 'Bebas Neue', 'Barlow Condensed', Impact, sans-serif; font-size: 122px; line-height: 1; letter-spacing: .115em; padding-top: 6px;
    color: #fff; white-space: nowrap; text-shadow: 0 0 26px rgba(255,30,60,.9), 0 5px 6px rgba(0,0,0,.55);
  }

  /* summary */
  #roundClosedSummary {
    margin-top: 44px; opacity: 0; white-space: nowrap;
    font-family: 'Barlow Condensed', 'Bebas Neue', Impact, sans-serif; font-weight: 700; font-size: 46px; letter-spacing: .152em; line-height: 1;
    color: rgba(255,255,255,.93); text-shadow: 0 3px 5px rgba(0,0,0,.6);
  }

  /* Entry animations (triggered when #roundClosedView gets .play) */
  #roundClosedView.play .rc-backing { animation: rcFade .8s ease-out forwards; }
  #roundClosedView.play .rc-panel   { animation: rcPanelIn .75s cubic-bezier(.2,.85,.25,1) forwards; }
  #roundClosedView.play .rc-badge   { animation: rcDrop .55s ease-out .25s forwards; }
  #roundClosedView.play .rc-flash   { animation: rcFlash 1s ease-out .45s forwards; }
  #roundClosedView.play .rc-numwrap { animation: rcSlam .8s cubic-bezier(.2,.9,.3,1) .4s forwards; }
  #roundClosedView.play .rc-cat     { animation: rcRise .6s ease-out .95s forwards; }
  #roundClosedView.play .rc-pill    { animation: rcPillIn .65s cubic-bezier(.2,.9,.3,1) 1.2s forwards; }
  #roundClosedView.play .rc-pill::after { animation: rcPulse 1.9s ease-in-out 1.95s infinite; }
  #roundClosedView.play .rc-pill .dot   { animation: rcDot 1.9s ease-in-out 1.95s infinite; }
  #roundClosedView.play #roundClosedSummary { animation: rcFade .7s ease-out 1.65s forwards; }
  #roundClosedView.play .rc-sweep   { animation: rcSweep 1.3s ease-in-out 1.25s forwards; }

  @keyframes rcFade    { from { opacity: 0; } to { opacity: 1; } }
  @keyframes rcPanelIn { from { opacity: 0; transform: scale(.9); } to { opacity: 1; transform: scale(1); } }
  @keyframes rcDrop    { from { opacity: 0; transform: translate3d(0,-34px,0); } to { opacity: 1; transform: translate3d(0,0,0); } }
  @keyframes rcFlash   { 0% { opacity: 0; transform: scale(.55); } 35% { opacity: .9; } 100% { opacity: 0; transform: scale(1.5); } }
  @keyframes rcSlam    { 0% { opacity: 0; transform: scale(1.55); } 55% { opacity: 1; transform: scale(.965); } 100% { opacity: 1; transform: scale(1); } }
  @keyframes rcRise    { from { opacity: 0; transform: translate3d(0,26px,0); } to { opacity: 1; transform: translate3d(0,0,0); } }
  @keyframes rcPillIn  { 0% { opacity: 0; transform: skewX(-12deg) scale(.5); } 70% { opacity: 1; transform: skewX(-12deg) scale(1.07); } 100% { opacity: 1; transform: skewX(-12deg) scale(1); } }
  @keyframes rcPulse   { 0%,100% { opacity: .35; } 50% { opacity: .95; } }
  @keyframes rcDot     { 0%,100% { opacity: .55; } 50% { opacity: 1; } }
  @keyframes rcSweep   { 0% { opacity: 1; transform: translate3d(-400px,0,0) skewX(-18deg); } 100% { opacity: 1; transform: translate3d(1700px,0,0) skewX(-18deg); } }
</style>
</head>
<body>

<!-- Floating Admin Navigation Bar -->
<div id="adminControlBar">
  <span class="status-dot" id="liveStatusDot" title="Live Broadcast Connected"></span>
  <span class="league-badge" id="leagueBadgeText">{{ strtoupper($leagueType) }} LEAGUE</span>

  <button type="button" class="ctrl-btn" id="modeToggleBtn" onclick="toggleViewMode()" title="Switch Screen Mode">
    ⇋ Toggle View
  </button>
  <button type="button" class="ctrl-btn" id="playerCardBtn" onclick="switchToPlayerCardExplicitly()" title="Show Player Details">
    ★ Player Details
  </button>
  <button type="button" class="ctrl-btn" id="roundClosedBtn" onclick="switchToRoundClosedExplicitly()" title="Show Round Closed">
    ■ Round Closed
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
    <defs>
      <!-- Circular clip path for team logo in star center -->
      <clipPath id="starTeamLogoClip">
        <circle cx="0" cy="0" r="148"/>
      </clipPath>
      <!-- Radial gradient for center medallion background -->
      <radialGradient id="starMedallionGrad" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#0e3a1d" stop-opacity="0.96"/>
        <stop offset="70%" stop-color="#082211" stop-opacity="0.98"/>
        <stop offset="100%" stop-color="#031007" stop-opacity="1"/>
      </radialGradient>
      <!-- Ring glow effect -->
      <filter id="starRingGlow" x="-35%" y="-35%" width="170%" height="170%">
        <feGaussianBlur stdDeviation="8" result="blur"/>
        <feMerge>
          <feMergeNode in="blur"/>
          <feMergeNode in="SourceGraphic"/>
        </feMerge>
      </filter>
    </defs>

    <g id="starRot">
      <polygon id="gapStar" fill="rgba(6,44,18,.55)"/>
      <g class="glow-yellow">
        <polygon id="yelStar" fill="#d2ee45"/>
      </g>
      <polygon id="innStar" fill="#1c7034"/>
      <polygon id="redStar" fill="none" stroke="#e5112e" stroke-width="15" stroke-linejoin="miter" class="glow-red"/>
      <g id="sticks" class="glow-white" stroke="#fff" stroke-linecap="round" fill="none"></g>

      <!-- Center Team Logo Medallion (Centered in Star at 0, 0) -->
      <g id="starTeamLogoGroup">
        <!-- Medallion backdrop and outer gold/lime glowing ring -->
        <circle cx="0" cy="0" r="162" fill="url(#starMedallionGrad)" stroke="#d2ee45" stroke-width="6" filter="url(#starRingGlow)"/>
        <!-- Accent inner ring with dashed styling -->
        <circle cx="0" cy="0" r="152" fill="none" stroke="rgba(255,255,255,0.45)" stroke-width="2.5" stroke-dasharray="8 6"/>
        <!-- Inner glow rim -->
        <circle cx="0" cy="0" r="148" fill="none" stroke="#1c7034" stroke-width="3"/>
        <!-- Team Logo Image -->
        <image id="starTeamLogoImg"
               href="{{ $initialPick['teamLogoUrl'] ?? '' }}"
               x="-135" y="-135" width="270" height="270"
               preserveAspectRatio="xMidYMid meet"
               clip-path="url(#starTeamLogoClip)"
               style="{{ !empty($initialPick['teamLogoUrl']) ? '' : 'display: none;' }}"/>
        <!-- Fallback team name / initials when logo image is not set -->
        <text id="starTeamLogoFallback"
              x="0" y="16"
              text-anchor="middle"
              fill="#ffffff"
              font-family="'Barlow Condensed', 'Bebas Neue', sans-serif"
              font-weight="700"
              font-size="44"
              letter-spacing="2"
              style="{{ !empty($initialPick['teamLogoUrl']) ? 'display: none;' : '' }}">
          {{ strtoupper(substr($initialPick['teamName'] ?? 'KTPL', 0, 12)) }}
        </text>
      </g>
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

        <!-- Skipped Turn Card: Replaces countdown display at expired turn -->
        <div id="skippedTurnBox" class="skipped-turn-card" style="display: none;">
          <div class="skipped-badge-glory">SKIPPED</div>
          <div class="skipped-team-name" id="skippedTeamName">—</div>
          <div class="skipped-meta-line" id="skippedMetaInfo">ROUND 1 | PICK 1</div>
          <div class="skipped-full-summary" id="skippedFullSummary">— | ROUND 1 | PICK 1 SKIPPED</div>
        </div>
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

  <!-- =======================================================================
       3. SKIPPED VIEW (State C)
       Active when turn countdown expires without a pick until admin clicks SHOW TIMER
       ======================================================================= -->
  <div id="skippedView" class="view-mode is-hidden">
    <div class="skipped-screen-backdrop"></div>
    <div class="skipped-screen-container">
      <div class="skipped-badge-glory">SKIPPED</div>
      <div class="skipped-team-name" id="skippedScreenTeamName">
        {{ strtoupper($latestSkip['teamName'] ?? ($activeRoundData['currentTeamName'] ?? 'KARACHI KIDZ')) }}
      </div>
      <div class="skipped-meta-line" id="skippedScreenMetaInfo">
        ROUND {{ $latestSkip['roundNumber'] ?? ($activeRoundData['roundNumber'] ?? 1) }} | PICK {{ $latestSkip['pickNumber'] ?? ($activeRoundData['currentPickNumber'] ?? 1) }}
      </div>
      <div class="skipped-full-summary" id="skippedScreenFullSummary">
        {{ strtoupper($latestSkip['teamName'] ?? ($activeRoundData['currentTeamName'] ?? 'KARACHI KIDZ')) }} | ROUND {{ $latestSkip['roundNumber'] ?? ($activeRoundData['roundNumber'] ?? 1) }} | PICK {{ $latestSkip['pickNumber'] ?? ($activeRoundData['currentPickNumber'] ?? 1) }} SKIPPED
      </div>
      <div class="skipped-waiting-status">
        <span class="skipped-pulsing-dot"></span> WAITING FOR ADMIN TO SHOW NEXT TIMER
      </div>
    </div>
  </div>

  <!-- =======================================================================
       4. ROUND CLOSED VIEW (ptpl-draft-round-closed.html)
       Active when round completes or when triggered by admin
       ======================================================================= -->
  <div id="roundClosedView" class="view-mode is-hidden">
    <div class="rc-backing"></div>
    <div class="rc-panel">
      <div class="rc-sweep"></div>
      <div class="rc-content">
        <div class="rc-badge"><i class="ln"></i><span id="roundClosedBadge">&#9733; ROUND CONCLUDED &#9733;</span><i class="ln r"></i></div>
        <div class="rc-num"><div class="rc-flash"></div><div class="rc-numwrap"><span id="roundClosedNumber">ROUND #{{ $activeRoundData['roundNumber'] ?? 1 }}</span></div></div>
        <div class="rc-cat"><span id="roundClosedCategory">{{ strtoupper($activeRoundData['categoryName'] ?? 'PLATINUM') }}</span><span class="sfx">CATEGORY</span></div>
        <div class="rc-pill"><i class="fill"></i><span class="dot"></span><span id="roundClosedStatus">CLOSED</span></div>
        <div id="roundClosedSummary">ALL PICKS COMPLETED &bull; PREPARING NEXT ROUND</div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  /* ================= Server Initial State ================= */
  var initialPick = @json($initialPick);
  var initialRound = @json($activeRoundData);
  var initialSkip = @json($latestSkip);
  var currentLeagueType = @json($leagueType);
  var currentPickId = initialPick ? (parseInt(initialPick.pickId, 10) || 0) : 0;
  var pickTransitionCount = 0;
  var currentMode = 'card'; // 'countdown' | 'card'
  var currentTurnKey = null;
  var latestRoundData = initialRound;
  var latestPickData = initialPick;
  var latestSkipData = initialSkip;
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

    var starLogoGroup = $('starTeamLogoGroup');
    if (starLogoGroup) {
      starLogoGroup.setAttribute('transform', 'rotate(' + (-rot).toFixed(3) + ')');
    }

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
      var isHeartbeat = running && sec > 0 && sec <= 10;
      elDigits.classList.toggle('timer-heartbeat', isHeartbeat);

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
      elDigits.classList.remove('timer-heartbeat');
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
      draw(false);
    };
    api.resume = api.start;
    api.pause = function () {
      wantStart = false;
      if (!running) return;
      remainingMs = curMs();
      serverDeadline = null;
      running = false;
      elDigits.classList.remove('timer-heartbeat');
      draw(false);
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
      elDigits.classList.remove('timer-heartbeat');
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
  var skippedView = $('skippedView');

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

    if (roundClosedAutoShiftTimer) {
      clearTimeout(roundClosedAutoShiftTimer);
      roundClosedAutoShiftTimer = null;
    }

    stage.classList.remove('mode-card');
    stage.classList.remove('mode-skipped');
    stage.classList.remove('mode-round-closed');
    stage.classList.add('mode-countdown');
    var star = $('starSvg');
    if (star) star.style.display = 'none';

    var sv = $('skippedView');
    if (sv) {
      sv.classList.remove('is-active');
      sv.classList.add('is-hidden');
    }

    var rcView = $('roundClosedView');
    if (rcView) {
      rcView.classList.remove('is-active', 'play');
      rcView.classList.add('is-hidden');
      rcView.style.display = 'none';
      rcView.style.opacity = '0';
    }

    if (cardView) {
      cardView.classList.remove('is-active');
      cardView.classList.add('is-hidden');
    }

    if (countdownView) {
      countdownView.classList.remove('is-hidden');
      void countdownView.offsetWidth;
      countdownView.classList.add('is-active');
    }

    if (latestRoundData) {
      updateCountdownHeader(latestRoundData);
    }
  }

  function showCardView(pickData) {
    currentMode = 'card';

    if (roundClosedAutoShiftTimer) {
      clearTimeout(roundClosedAutoShiftTimer);
      roundClosedAutoShiftTimer = null;
    }

    stage.classList.remove('mode-countdown');
    stage.classList.remove('mode-skipped');
    stage.classList.remove('mode-round-closed');
    stage.classList.add('mode-card');
    var star = $('starSvg');
    if (star) star.style.display = 'block';

    var sv = $('skippedView');
    if (sv) {
      sv.classList.remove('is-active');
      sv.classList.add('is-hidden');
    }

    var rcView = $('roundClosedView');
    if (rcView) {
      rcView.classList.remove('is-active', 'play');
      rcView.classList.add('is-hidden');
      rcView.style.display = 'none';
      rcView.style.opacity = '0';
    }

    if (countdownView) {
      countdownView.classList.remove('is-active');
      countdownView.classList.add('is-hidden');
    }
    var digEl = $('digits');
    if (digEl) digEl.classList.remove('timer-heartbeat');

    if (cardView) {
      cardView.classList.remove('is-hidden');
      void cardView.offsetWidth;
      cardView.classList.add('is-active');
    }

    var pData = pickData || latestPickData;
    if (pData && pData.player) {
      renderPlayerDOM(pData);
    } else {
      var emptyTag = $('emptyStateTag');
      if (emptyTag) emptyTag.style.display = 'flex';
    }
  }

  function showSkippedView(skipData) {
    currentMode = 'skipped';
    showingPickedCard = false;

    if (roundClosedAutoShiftTimer) {
      clearTimeout(roundClosedAutoShiftTimer);
      roundClosedAutoShiftTimer = null;
    }

    stage.classList.remove('mode-countdown');
    stage.classList.remove('mode-card');
    stage.classList.remove('mode-round-closed');
    stage.classList.add('mode-skipped');

    var star = $('starSvg');
    if (star) star.style.display = 'none';

    var rcView = $('roundClosedView');
    if (rcView) {
      rcView.classList.remove('is-active', 'play');
      rcView.classList.add('is-hidden');
      rcView.style.display = 'none';
      rcView.style.opacity = '0';
    }

    if (countdownView) {
      countdownView.classList.remove('is-active');
      countdownView.classList.add('is-hidden');
    }
    var digEl = $('digits');
    if (digEl) digEl.classList.remove('timer-heartbeat');

    if (cardView) {
      cardView.classList.remove('is-active');
      cardView.classList.add('is-hidden');
    }

    var sv = $('skippedView');
    if (sv) {
      sv.classList.remove('is-hidden');
      void sv.offsetWidth;
      sv.classList.add('is-active');
    }

    DraftTimer.pause();

    var sData = skipData || latestSkipData;
    if (sData) {
      latestSkipData = sData;
      var tName = (sData.teamName || 'UNKNOWN TEAM').toUpperCase();
      var rNum = sData.roundNumber || 1;
      var pNum = sData.pickNumber || 1;
      if ($('skippedScreenTeamName')) $('skippedScreenTeamName').textContent = tName;
      if ($('skippedScreenMetaInfo')) $('skippedScreenMetaInfo').textContent = 'ROUND ' + rNum + ' | PICK ' + pNum;
      if ($('skippedScreenFullSummary')) $('skippedScreenFullSummary').textContent = tName + ' | ROUND ' + rNum + ' | PICK ' + pNum + ' SKIPPED';
    }
  }

  /* ================= Round Closed Controller ================= */
  var RoundClosed = (function () {
    var view = $('roundClosedView'), elNum = $('roundClosedNumber'), elCat = $('roundClosedCategory'),
        elSt = $('roundClosedStatus'), elSum = $('roundClosedSummary'), elBadge = $('roundClosedBadge');
    var api = {}, base = {};

    function up(x) { return String(x == null ? '' : x).toUpperCase().trim(); }

    function fitOne(el, maxW) {
      if (!el) return;
      if (!base[el.id]) base[el.id] = parseFloat(getComputedStyle(el).fontSize) || 100;
      el.style.fontSize = base[el.id] + 'px';
      var w = el.scrollWidth || el.offsetWidth;
      if (w > maxW) el.style.fontSize = Math.max(24, base[el.id] * maxW / w).toFixed(1) + 'px';
    }

    api.fit = function () {
      fitOne(elNum, 1240);
      fitOne(elSt, 760);
      fitOne(elSum, 1240);
      fitOne(elBadge, 900);
      if (elCat && elCat.parentNode) {
        var row = elCat.parentNode;
        if (!base.cat) base.cat = parseFloat(getComputedStyle(row).fontSize) || 88;
        row.style.fontSize = base.cat + 'px';
        var w = row.scrollWidth;
        if (w > 1240) row.style.fontSize = Math.max(28, base.cat * 1240 / w).toFixed(1) + 'px';
      }
    };

    api.set = function (d) {
      d = d || {};
      if (d.number != null && elNum) elNum.textContent = up(d.number);
      else if (d.round != null && elNum) elNum.textContent = 'ROUND #' + up(d.round).replace(/^ROUND\s*/, '').replace(/^#/, '');
      if (d.category != null && elCat) elCat.textContent = up(d.category).replace(/\s*CATEGORY$/, '');
      if (d.status != null && elSt) elSt.textContent = up(d.status);
      if (d.summary != null && elSum) elSum.textContent = up(d.summary);
      if (d.badge != null && elBadge) elBadge.textContent = up(d.badge);
      api.fit();
    };

    api.show = function () {
      var v = $('roundClosedView');
      if (!v) return;
      v.style.transition = 'none';
      v.style.display = 'flex';
      v.style.opacity = '1';
      v.style.zIndex = '100';
      v.classList.remove('play');
      void v.offsetWidth;
      v.classList.add('play');
      api.fit();
    };
    api.replay = api.show;
    api.hide = function () {
      var v = $('roundClosedView');
      if (!v) return;
      v.style.transition = 'opacity .45s ease-in';
      v.style.opacity = '0';
    };

    return api;
  })();

  var roundClosedAutoShiftTimer = null;

  function showRoundClosedView(roundData, options) {
    currentMode = 'round-closed';
    options = options || {};

    if (roundClosedAutoShiftTimer) {
      clearTimeout(roundClosedAutoShiftTimer);
      roundClosedAutoShiftTimer = null;
    }

    stage.classList.remove('mode-countdown');
    stage.classList.remove('mode-card');
    stage.classList.remove('mode-skipped');
    stage.classList.add('mode-round-closed');

    var star = $('starSvg');
    if (star) star.style.display = 'none';

    if (countdownView) {
      countdownView.classList.remove('is-active');
      countdownView.classList.add('is-hidden');
      countdownView.style.display = 'none';
    }
    var digEl = $('digits');
    if (digEl) digEl.classList.remove('timer-heartbeat');

    if (cardView) {
      cardView.classList.remove('is-active');
      cardView.classList.add('is-hidden');
      cardView.style.display = 'none';
    }

    var sv = $('skippedView');
    if (sv) {
      sv.classList.remove('is-active');
      sv.classList.add('is-hidden');
      sv.style.display = 'none';
    }

    var rcView = $('roundClosedView');
    if (rcView) {
      rcView.classList.remove('is-hidden');
      rcView.style.display = 'flex';
      rcView.style.opacity = '1';
      rcView.style.zIndex = '100';
      void rcView.offsetWidth;
      rcView.classList.add('is-active');
    }

    DraftTimer.pause();

    var rData = roundData || latestRoundData || {};
    var rNum = rData.roundNumber || rData.roundId || (latestPickData && latestPickData.roundNumber) || 1;
    var cat = rData.categoryName || (latestPickData && latestPickData.player && latestPickData.player.category) || 'DRAFT';

    RoundClosed.set({
      round: rNum,
      category: cat,
      status: 'CLOSED',
      summary: 'ALL PICKS COMPLETED • PREPARING NEXT ROUND',
      badge: '★ ROUND CONCLUDED ★'
    });
    RoundClosed.show();

    // Automatically transition to the confirmed player card view after 15 seconds if not explicitly disabled
    if (!options.noAutoShift) {
      roundClosedAutoShiftTimer = setTimeout(function () {
        console.log('[DraftingScreen] Auto-transitioning from Round Closed to Player Details...');
        showCardView(latestPickData);
      }, 15000);
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
        DraftTimer.pause();
        var curSec = Math.ceil(timerState.remaining);
        if (remainingSec !== null && remainingSec !== undefined && Math.abs(curSec - remainingSec) > 1) {
          DraftTimer.setRemaining(remainingSec);
        }
      } else {
        if (!timerState.running) {
          var curSec = Math.ceil(timerState.remaining);
          if (remainingSec > 0 && Math.abs(curSec - remainingSec) > 1) {
            DraftTimer.setRemaining(remainingSec);
          }
          DraftTimer.start(serverDeadline);
        } else if (serverDeadline) {
          var curSec = Math.ceil(timerState.remaining);
          if (options.source === 'poll') {
            if (Math.abs(curSec - remainingSec) > 5) {
              DraftTimer.start(serverDeadline);
            }
          } else if (Math.abs(curSec - remainingSec) > 1) {
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

  // When timer finishes (hits 0), immediately transition to State C (SKIPPED screen)
  DraftTimer.onFinish = function () {
    console.log('[DraftingScreen] Countdown timer finished at 00:00.');
    DraftTimer.pause();
    if (latestRoundData) {
      var teamName = latestRoundData.currentTeamName || 'ACTIVE TEAM';
      var rNum = latestRoundData.roundNumber || latestRoundData.roundId || 1;
      var pNum = latestRoundData.currentPickNumber || 1;
      showSkippedView({
        teamName: teamName,
        roundNumber: rNum,
        pickNumber: pNum
      });
    }
    fetchLatestAuthoritativePick();
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

      var starLogoImg = $('starTeamLogoImg');
      var starLogoFallback = $('starTeamLogoFallback');
      if (starLogoImg) starLogoImg.style.display = 'none';
      if (starLogoFallback) {
        starLogoFallback.textContent = 'KTPL';
        starLogoFallback.style.display = 'block';
      }
      return;
    }

    $('emptyStateTag').style.display = 'none';
    var p = data.player;

    // Team name
    $('playerTeam').textContent = data.teamName || '—';

    // Team Logo in Star Center
    var starLogoImg = $('starTeamLogoImg');
    var starLogoFallback = $('starTeamLogoFallback');
    var tName = (data.teamName && data.teamName.trim()) ? data.teamName : 'KTPL';
    var logoUrl = data.teamLogoUrl || (data.team && data.team.logoUrl) || null;

    if (logoUrl) {
      if (starLogoImg) {
        starLogoImg.setAttribute('href', logoUrl);
        starLogoImg.style.display = 'block';
      }
      if (starLogoFallback) {
        starLogoFallback.style.display = 'none';
      }
    } else {
      if (starLogoImg) {
        starLogoImg.style.display = 'none';
        starLogoImg.setAttribute('href', '');
      }
      if (starLogoFallback) {
        starLogoFallback.textContent = tName.length > 12 ? tName.substring(0, 10).toUpperCase() + '…' : tName.toUpperCase();
        starLogoFallback.style.display = 'block';
      }
    }

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

    renderPlayerDOM(pickData);

    // If this pick completed the round:
    if (pickData.isRoundCompleted || (pickData.roundData && pickData.roundData.status === 'completed')) {
      console.log('[DraftingScreen] Final round pick confirmed! Triggering Round Closed view first.');
      showRoundClosedView(pickData.roundData || latestRoundData);
      return;
    }

    if (currentMode === 'round-closed') {
      return;
    }

    // Immediately switch to Card View so the audience sees the picked player with neon star
    showCardView(pickData);

    if (!prefersReducedMotion && content) {
      content.className = isFromLeft ? 'anim-enter-left' : 'anim-enter-right';
      void content.offsetWidth;
      content.className = 'anim-active';
    }
  }

  /* ================= Initial View State Initialization ================= */
  if (initialRound && initialRound.status === 'active') {
    // If active round is waiting for admin after a skipped turn:
    if (initialRound.isWaitingForAdmin) {
      var sInit = initialSkip || {
        teamName: initialRound.currentTeamName,
        roundNumber: initialRound.roundNumber,
        pickNumber: initialRound.currentPickNumber
      };
      showSkippedView(sInit);
    } else if (initialRound.isPaused && initialPick && initialPick.player && !initialSkip) {
      // If active round is paused and an initial pick is present without an active skip, show player card
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

    var channelNames = ['draft.screen', 'draft.league.' + currentLeagueType];
    if (currentLeagueType === 'male') {
      channelNames.push('draft.league.males');
    } else if (currentLeagueType === 'males') {
      channelNames.push('draft.league.male');
    } else if (currentLeagueType === 'female') {
      channelNames.push('draft.league.females');
    } else if (currentLeagueType === 'females') {
      channelNames.push('draft.league.female');
    }
    @if(isset($availableLeagues))
      @foreach($availableLeagues as $al)
        if (channelNames.indexOf('draft.league.{{ $al->slug }}') === -1) {
          channelNames.push('draft.league.{{ $al->slug }}');
        }
      @endforeach
    @endif

    channelNames.forEach(function (cName) {
      console.log('[DraftingScreen] Subscribing to channel:', cName);
      var channel = window.Echo.channel(cName);

      // 0. Screen actions (Remote Control from Admin Panel)
      channel.listen('.draft.screen.action', function (data) {
        console.log('[DraftingScreen] .draft.screen.action event received on ' + cName + ':', data);
        handleScreenAction(data);
      });
      channel.listen('draft.screen.action', function (data) {
        console.log('[DraftingScreen] draft.screen.action event received on ' + cName + ':', data);
        handleScreenAction(data);
      });
      channel.listen('DraftScreenAction', function (data) {
        console.log('[DraftingScreen] DraftScreenAction event received on ' + cName + ':', data);
        handleScreenAction(data);
      });

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
    var isWait = Boolean(data.isWaitingForAdmin);
    var sig = [rId, pNum, tId, data.status, isP, rem, isWait, data.message || ''].join(':');
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

    // If round completed, show Round Closed screen first, then auto-shift to player card!
    if (data.status === 'completed') {
      DraftTimer.pause();
      showingPickedCard = true;
      showRoundClosedView(data);
      return;
    }

    // 1. Check if turn expired and round is waiting for admin (State C: SKIPPED screen)
    if (data.isWaitingForAdmin || data.state === 'skipped_waiting_for_admin') {
      DraftTimer.pause();
      var sInfo = data.skippedPick || data.latestSkip || {
        teamName: data.currentTeamName,
        roundNumber: data.roundNumber,
        pickNumber: data.currentPickNumber
      };
      showSkippedView(sInfo);
      return;
    }

    // 2. Active round in progress (running, paused, or reset)
    if (data.status === 'active') {
      var isPaused = Boolean(data.isPaused);

      // If we were showing SKIPPED screen and admin clicked SHOW TIMER:
      if (currentMode === 'skipped') {
        showingPickedCard = false;
        switchToCountdownView(data, { source: 'broadcast', forceReset: true });
        return;
      }

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

      if (data.latestSkip) {
        latestSkipData = data.latestSkip;
      }

      // 1. If active round is currently waiting for admin after a skipped turn:
      if (data.activeRound && data.activeRound.status === 'active' && data.activeRound.isWaitingForAdmin) {
        var arWait = data.activeRound;
        latestRoundData = arWait;
        if (data.pickId) {
          currentPickId = Math.max(currentPickId, parseInt(data.pickId, 10) || 0);
        }
        showSkippedView(data.latestSkip || {
          teamName: arWait.currentTeamName,
          roundNumber: arWait.roundNumber,
          pickNumber: arWait.currentPickNumber
        });
        return;
      }

      // 2. Handle newer pick
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

        // If we were showing skipped screen but round is no longer waiting for admin (admin clicked SHOW TIMER):
        if (currentMode === 'skipped' && !ar.isWaitingForAdmin) {
          showingPickedCard = false;
          switchToCountdownView(ar, { source: 'poll', forceReset: true });
          return;
        }

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
  }, 2000);

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

  function handleScreenAction(data) {
    if (!data || !data.action) return;
    if (data.action === 'show_player_card') {
      if (roundClosedAutoShiftTimer) {
        clearTimeout(roundClosedAutoShiftTimer);
        roundClosedAutoShiftTimer = null;
      }
      showCardView(latestPickData);
    } else if (data.action === 'show_round_closed') {
      showRoundClosedView(data.payload || latestRoundData, { noAutoShift: true });
    } else if (data.action === 'toggle_view') {
      toggleViewMode();
    }
  }

  /* Expose helpers for testing & fullscreen */
  window.__draftingScreen = {
    getCurrentPickId: function () { return currentPickId; },
    getMode: function () { return currentMode; },
    showCountdownView: showCountdownView,
    showCardView: showCardView,
    showSkippedView: showSkippedView,
    showRoundClosedView: showRoundClosedView,
    switchToCountdown: switchToCountdownView,
    switchToCard: switchToCardView,
    onNewPlayerPicked: onNewPlayerPicked,
    handleTurnChanged: handleTurnChanged,
    handleScreenAction: handleScreenAction,
    fetchLatest: fetchLatestAuthoritativePick,
    RoundClosed: RoundClosed
  };
})();

function toggleViewMode() {
  if (window.__draftingScreen) {
    var mode = window.__draftingScreen.getMode();
    if (mode === 'countdown') {
      window.__draftingScreen.showCardView();
    } else if (mode === 'card') {
      window.__draftingScreen.showRoundClosedView();
    } else {
      window.__draftingScreen.showCountdownView();
    }
  }
}

function switchToPlayerCardExplicitly() {
  if (window.__draftingScreen) {
    window.__draftingScreen.showCardView();
  }
}

function switchToRoundClosedExplicitly() {
  if (window.__draftingScreen) {
    window.__draftingScreen.showRoundClosedView(null, { noAutoShift: true });
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
