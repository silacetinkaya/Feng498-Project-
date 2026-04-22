<?php
// index.php

$userLoginUrl = 'user_login.php';
$userSignupUrl = 'user_login.php';
$businessLoginUrl = 'business_login.php';
$exploreUrl = 'user_login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pricely</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Cormorant+Garamond:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: #f7f4f1;
      color: #1b1b1b;
      overflow-x: hidden;
    }

    a { text-decoration: none; color: inherit; }

    .container {
      width: min(1220px, calc(100% - 40px));
      margin: 0 auto;
    }

    .navbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: rgba(247,244,241,0.72);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(75, 45, 57, 0.08);
      transition: all 0.3s ease;
    }

    .navbar.scrolled {
      background: rgba(247,244,241,0.9);
      backdrop-filter: blur(16px);
      box-shadow: 0 10px 30px rgba(38, 18, 25, 0.06);
    }

    .nav-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding: 16px 0;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 900;
      font-size: 28px;
      letter-spacing: -0.04em;
      color: #2a1019;
    }

    .brand-mark {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: linear-gradient(135deg, #4a0019, #8a3e5d);
      display: grid;
      place-items: center;
      color: #fff;
      font-weight: 900;
      box-shadow: 0 12px 26px rgba(74,0,25,0.22);
    }

    .nav-links {
      display: flex;
      gap: 28px;
      align-items: center;
      color: #5b4a51;
      font-weight: 600;
    }

    .nav-links a {
      position: relative;
    }

    .nav-links a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -6px;
      width: 0;
      height: 2px;
      background: #4a0019;
      transition: width 0.25s ease;
    }

    .nav-links a:hover::after {
      width: 100%;
    }

    .nav-actions {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .btn {
      border: none;
      cursor: pointer;
      border-radius: 999px;
      padding: 14px 22px;
      font-weight: 800;
      font-size: 15px;
      transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease, background 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn:hover {
      transform: translateY(-2px);
    }

    .btn-light {
      background: #efe6e2;
      color: #2a1019;
    }

    .btn-dark {
      background: #2a1019;
      color: white;
      box-shadow: 0 12px 22px rgba(42,16,25,0.18);
    }

    .btn-accent {
      background: #efe76e;
      color: #2a1019;
      box-shadow: 0 14px 30px rgba(239,231,110,0.26);
    }

    .btn-accent:hover {
      box-shadow: 0 18px 34px rgba(239,231,110,0.32);
    }
.hero {
  padding-top: 110px;
  padding-bottom: 38px;
  position: relative;
}

    .hero-grid {
      display: grid;
      grid-template-columns: 1.06fr 0.94fr;
      gap: 28px;
      align-items: stretch;
    }

   .hero-left {
  background: linear-gradient(180deg, #f7f1ec 0%, #fffaf5 100%);
  border: 1px solid #eadfd7;
  border-radius: 40px;
  padding: 56px;
  position: relative;
  overflow: hidden;
  min-height: 760px;
}

    .hero-left::before {
      content: "";
      position: absolute;
      width: 300px;
      height: 300px;
      top: -80px;
      right: -80px;
      background: rgba(165, 101, 126, 0.12);
      border-radius: 50%;
      filter: blur(10px);
    }

    .hero-left::after {
      content: "";
      position: absolute;
      width: 340px;
      height: 340px;
      left: -120px;
      bottom: -140px;
      background: rgba(239, 231, 110, 0.16);
      border-radius: 50%;
      filter: blur(25px);
    }

    .hero-left > * {
      position: relative;
      z-index: 1;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,0.84);
      border: 1px solid #eadfd7;
      color: #7b6a72;
      border-radius: 999px;
      padding: 10px 14px;
      font-size: 13px;
      font-weight: 700;
      margin-bottom: 24px;
      box-shadow: 0 8px 18px rgba(65, 30, 42, 0.04);
    }

    .hero-mini-tabs {
      display: flex;
      justify-content: center;
      gap: 70px;
      align-items: center;
      margin-bottom: 12px;
      color: #d0c3c8;
      font-family: 'Cormorant Garamond', serif;
      font-size: clamp(38px, 4vw, 60px);
      font-style: italic;
      font-weight: 500;
      letter-spacing: -0.03em;
    }

    .hero-mini-tabs .active {
      color: #3a0517;
      transform: scale(1.03);
    }

    .hero-logo-top {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 18px;
      font-size: 58px;
      color: #3a0517;
    }

    h1 {
      margin: 0;
      font-size: clamp(44px, 6vw, 78px);
      line-height: 0.95;
      letter-spacing: -0.06em;
      max-width: 720px;
      text-align: center;
      color: #2f0715;
    }

    .hero-title-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 18px;
      margin-top: 10px;
    }

    .hero-text {
      margin-top: 8px;
      font-size: 20px;
      line-height: 1.7;
      color: #5f4b54;
      max-width: 680px;
      text-align: center;
    }

   .animated-words {
  display: inline-block;
  position: relative;
  min-width: 260px;
  height: 1.1em;
  vertical-align: top;
  text-align: center;
}

    .animated-words span {
  position: absolute;
  left: 50%;
  top: 0;
  transform: translateX(-50%);
  opacity: 0;
  animation: wordSwap 9s infinite;
  width: 100%;
}

    .animated-words span:nth-child(1) { animation-delay: 0s; }
    .animated-words span:nth-child(2) { animation-delay: 3s; }
    .animated-words span:nth-child(3) { animation-delay: 6s; }

    @keyframes wordSwap {
      0% { opacity: 0; transform: translateX(-50%) translateY(14px); }
      8% { opacity: 1; transform: translateX(-50%) translateY(0); }
      28% { opacity: 1; transform: translateX(-50%) translateY(0); }
      36% { opacity: 0; transform: translateX(-50%) translateY(-14px); }
      100% { opacity: 0; transform: translateX(-50%) translateY(-14px); }
    }

    .search-hero-box {
      margin-top: 34px;
      background: rgba(255,255,255,0.86);
      border: 1px solid #dfcfc7;
      border-radius: 28px;
      padding: 18px;
      box-shadow: 0 20px 40px rgba(53, 23, 34, 0.06);
    }

    .search-shell {
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 14px;
      border: 1px solid #ead8cf;
      border-radius: 22px;
      padding: 14px 16px;
      background: #fff;
      transition: all 0.25s ease;
    }

    .search-shell:focus-within {
      border-color: #b78da0;
      box-shadow: 0 0 0 4px rgba(183, 141, 160, 0.12);
    }

    .search-icon {
      font-size: 22px;
      color: #8d707d;
    }

    .hero-search-input {
      border: none;
      outline: none;
      font-size: 18px;
      background: transparent;
      color: #42212e;
      width: 100%;
      font-weight: 500;
    }

    .hero-search-input::placeholder {
      color: #8b727d;
    }

    .search-send {
      width: 50px;
      height: 50px;
      border-radius: 16px;
      border: none;
      cursor: pointer;
      background: #d8c6ce;
      color: white;
      font-size: 22px;
      display: grid;
      place-items: center;
      transition: all 0.2s ease;
    }

    .search-send:hover {
      background: #4a0019;
      transform: translateY(-1px);
    }

    .prompt-tags {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 18px;
    }

    .prompt-tag {
      padding: 12px 18px;
      border-radius: 16px;
      border: 1px solid #ead8cf;
      color: #6c5560;
      background: rgba(255,255,255,0.7);
      font-weight: 700;
      font-size: 15px;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .prompt-tag:hover {
      transform: translateY(-2px);
      background: white;
      box-shadow: 0 10px 22px rgba(41, 12, 24, 0.05);
    }

    .hero-actions {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 30px;
    }

    .hero-right {
      display: grid;
      grid-template-rows: 1fr auto;
      gap: 20px;
    }

    .mockup-card {
      background: linear-gradient(180deg, #f4ece7 0%, #ede7e1 100%);
      border: 1px solid #e6dcd4;
      border-radius: 36px;
      padding: 34px;
      position: relative;
      overflow: hidden;
      min-height: 700px;
    }

    .mockup-phone {
      width: 325px;
      max-width: 100%;
      margin: 70px auto 0;
      background: #181818;
      border-radius: 38px;
      padding: 12px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.18);
      animation: floatSoft 5s ease-in-out infinite;
    }

    .mockup-screen {
      background: #fff;
      border-radius: 28px;
      overflow: hidden;
      min-height: 570px;
    }

    .mock-top {
      height: 76px;
      background: linear-gradient(135deg, #4a0019, #7c3b56);
      padding: 20px 18px;
      color: white;
      font-weight: 800;
    }

    .mock-business {
      padding: 18px;
      border-bottom: 1px solid #eee;
    }

    .mock-business h4 {
      margin: 0 0 6px;
      font-size: 20px;
    }

    .mock-badge {
      display: inline-block;
      background: #f2f7ef;
      color: #28774b;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
    }

    .mock-services {
      padding: 16px;
      display: grid;
      gap: 12px;
    }

    .service-item {
      border: 1px solid #ededed;
      border-radius: 18px;
      padding: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      transition: 0.2s ease;
    }

    .service-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 22px rgba(0,0,0,0.05);
    }

    .service-meta h5 {
      margin: 0 0 5px;
      font-size: 15px;
    }

    .service-meta p {
      margin: 0;
      font-size: 12px;
      color: #777;
    }

    .price-tag {
      font-weight: 800;
      color: #111;
    }

    .floating-pill {
      position: absolute;
      background: #fff;
      border: 1px solid #ece1d8;
      border-radius: 999px;
      padding: 12px 16px;
      font-size: 13px;
      font-weight: 800;
      box-shadow: 0 14px 30px rgba(0,0,0,0.08);
      animation: floatSoft 4s ease-in-out infinite;
    }

    .pill-1 { top: 30px; right: 26px; }
    .pill-2 { top: 132px; left: 24px; animation-delay: 0.7s; }
    .pill-3 { bottom: 48px; right: 30px; animation-delay: 1.3s; }

    @keyframes floatSoft {
      0%,100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }

    .stat-box {
      background: white;
      border: 1px solid #eadfd7;
      border-radius: 24px;
      padding: 24px;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .stat-box:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 30px rgba(40, 12, 22, 0.06);
    }

    .stat-box h3 {
      margin: 0;
      font-size: 34px;
      letter-spacing: -0.04em;
      color: #2a1019;
    }

    .stat-box p {
      margin: 8px 0 0;
      color: #666;
      font-weight: 600;
    }

    section {
      padding: 38px 0;
    }

    .section-heading {
      display: flex;
      justify-content: space-between;
      align-items: end;
      gap: 20px;
      margin-bottom: 24px;
    }

    .section-heading h2 {
      margin: 0;
      font-size: clamp(28px, 4vw, 50px);
      line-height: 1.04;
      letter-spacing: -0.04em;
      color: #2b0d1a;
    }

    .section-heading p {
      margin: 0;
      max-width: 520px;
      color: #66555d;
      line-height: 1.7;
      font-size: 16px;
    }

    .split-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 22px;
    }

    .split-card {
      border-radius: 32px;
      padding: 36px;
      min-height: 330px;
      position: relative;
      overflow: hidden;
      transition: transform 0.28s ease, box-shadow 0.28s ease;
    }

    .split-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 34px rgba(43, 11, 24, 0.08);
    }

    .split-card.customer {
      background: #efe8ec;
      border: 1px solid #dfd0d7;
    }

    .split-card.business {
      background: #f6f0d8;
      border: 1px solid #ece1a9;
    }

    .split-card h3 {
      margin: 0 0 14px;
      font-size: 40px;
      letter-spacing: -0.04em;
      color: #2d0c18;
    }

    .split-card p {
      margin: 0;
      color: #584851;
      line-height: 1.7;
      max-width: 480px;
      font-size: 17px;
    }

    .bullet-list {
      margin: 20px 0 28px;
      display: grid;
      gap: 10px;
      color: #24151b;
      font-weight: 600;
    }

    .bullet-list span::before {
      content: "•";
      margin-right: 10px;
    }

    .categories-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 18px;
    }

    .category-card {
      background: white;
      border: 1px solid #eadfd7;
      border-radius: 26px;
      padding: 24px;
      min-height: 170px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .category-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 16px 30px rgba(0,0,0,0.06);
      border-color: #d8c4cc;
    }

    .cat-icon {
      width: 56px;
      height: 56px;
      border-radius: 18px;
      display: grid;
      place-items: center;
      font-size: 26px;
      background: #f9f1ed;
    }

    .category-card h4 {
      margin: 18px 0 6px;
      font-size: 22px;
      letter-spacing: -0.03em;
      color: #2d0d19;
    }

    .category-card p {
      margin: 0;
      color: #6a6a6a;
      line-height: 1.6;
      font-size: 14px;
    }

    .features-wrap {
      background: #f5ece8;
      border: 1px solid #eadad2;
      border-radius: 38px;
      padding: 34px;
    }

    .features-grid {
      display: grid;
      grid-template-columns: 1fr 340px 1fr;
      gap: 20px;
      align-items: center;
    }

    .feature-column {
      display: grid;
      gap: 18px;
    }

    .feature-card {
      background: white;
      border: 1px solid #ebe0d8;
      border-radius: 22px;
      padding: 20px;
      box-shadow: 0 10px 24px rgba(0,0,0,0.04);
      transition: transform 0.24s ease, box-shadow 0.24s ease;
    }

    .feature-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 14px 28px rgba(0,0,0,0.06);
    }

    .feature-card h4 {
      margin: 0 0 8px;
      font-size: 20px;
      letter-spacing: -0.03em;
      color: #2d0c19;
    }

    .feature-card p {
      margin: 0;
      color: #666;
      line-height: 1.6;
      font-size: 14px;
    }

    .center-phone {
      background: #111;
      border-radius: 34px;
      padding: 12px;
      box-shadow: 0 24px 50px rgba(0,0,0,0.16);
      animation: floatSoft 5s ease-in-out infinite;
    }

    .center-phone .screen {
      border-radius: 24px;
      background: #fff;
      min-height: 540px;
      padding: 18px;
      display: grid;
      gap: 14px;
    }

    .mini-box {
      background: #f7f5f1;
      border: 1px solid #ece7df;
      border-radius: 18px;
      padding: 16px;
      font-weight: 700;
      color: #333;
      transition: transform 0.2s ease;
    }

    .mini-box:hover {
      transform: translateX(4px);
    }

    .business-section {
      background: #250813;
      color: white;
      border-radius: 42px;
      padding: 42px;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 24px;
      overflow: hidden;
      position: relative;
    }

    .business-section::before {
      content: "";
      position: absolute;
      width: 420px;
      height: 420px;
      right: -150px;
      top: -120px;
      background: rgba(239,231,110,0.08);
      border-radius: 50%;
      filter: blur(20px);
    }

    .business-section p {
      color: rgba(255,255,255,0.78);
      line-height: 1.8;
      font-size: 16px;
    }

    .dark-list {
      display: grid;
      gap: 12px;
      margin: 22px 0 28px;
    }

    .dark-list div {
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 18px;
      padding: 16px 18px;
      font-weight: 700;
    }

    .ad-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }

    .ad-card {
      border-radius: 26px;
      padding: 24px;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .ad-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 34px rgba(0,0,0,0.08);
    }

    .ad-card h4 {
      margin: 0 0 12px;
      font-size: 30px;
      letter-spacing: -0.04em;
    }

    .ad-card .price {
      font-size: 38px;
      font-weight: 900;
      letter-spacing: -0.04em;
      margin-bottom: 14px;
    }

    .ad-card ul {
      padding-left: 18px;
      margin: 0 0 18px;
      line-height: 1.8;
    }

    .business-preview {
      background: linear-gradient(180deg, #1b0a11, #12060b);
      border-radius: 28px;
      border: 1px solid rgba(255,255,255,0.08);
      padding: 24px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }

    .preview-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }

    .preview-pill {
      background: rgba(255,255,255,0.08);
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
    }

    .preview-item {
      background: rgba(255,255,255,0.05);
      border-radius: 18px;
      padding: 16px;
      margin-bottom: 12px;
      color: rgba(255,255,255,0.9);
      font-weight: 700;
    }

    .footer {
      padding: 36px 0 50px;
      color: #666;
    }

    .footer-box {
      border-top: 1px solid #e8ddd6;
      padding-top: 28px;
      display: flex;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
    }

    .footer-links {
      display: flex;
      gap: 18px;
      flex-wrap: wrap;
      font-weight: 600;
    }

    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      animation: fadeUp 0.9s ease forwards;
    }

    .fade-up.delay-1 { animation-delay: 0.15s; }
    .fade-up.delay-2 { animation-delay: 0.3s; }
    .fade-up.delay-3 { animation-delay: 0.45s; }
    .fade-up.delay-4 { animation-delay: 0.6s; }

    @keyframes fadeUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .reveal {
      opacity: 0;
      transform: translateY(42px);
      transition: opacity 0.85s ease, transform 0.85s ease;
    }

    .reveal.show {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 1100px) {
      .hero-grid,
      .features-grid,
      .business-section {
        grid-template-columns: 1fr;
      }

      .categories-grid,
      .ad-cards,
      .split-grid {
        grid-template-columns: 1fr 1fr;
      }

      .stats-row {
        grid-template-columns: 1fr 1fr;
      }

      .nav-links {
        display: none;
      }

      .hero-mini-tabs {
        gap: 30px;
        font-size: 40px;
      }
    }

    @media (max-width: 720px) {
      .hero-left,
      .mockup-card,
      .features-wrap,
      .business-section {
        padding: 24px;
      }

      .categories-grid,
      .split-grid,
      .ad-cards,
      .stats-row {
        grid-template-columns: 1fr;
      }

      .nav-actions {
        flex-wrap: wrap;
        justify-content: end;
      }

      .brand {
        font-size: 24px;
      }

      h1 {
        font-size: 44px;
      }

      .section-heading {
        flex-direction: column;
        align-items: start;
      }

      .hero-mini-tabs {
        gap: 18px;
        font-size: 30px;
      }

      .prompt-tags {
        justify-content: flex-start;
      }

      .hero-title-wrap,
      .hero-actions {
        align-items: flex-start;
        justify-content: flex-start;
      }

      h1,
      .hero-text {
        text-align: left;
      }

      .hero-logo-top {
        justify-content: flex-start;
      }
    }
  </style>
</head>
<body>

  <header class="navbar">
    <div class="container nav-inner">
      <a href="index.php" class="brand">
        <div class="brand-mark">p</div>
        <span>Pricely</span>
      </a>

      <nav class="nav-links">
        <a href="#categories">Services</a>
        <a href="#how-it-works">How it works</a>
        <a href="#business">For businesses</a>
        <a href="#ads">Advertising</a>
      </nav>

      <div class="nav-actions">
        <a href="<?= htmlspecialchars($userLoginUrl) ?>" class="btn btn-light">User Log in</a>
        <a href="<?= htmlspecialchars($userSignupUrl) ?>" class="btn btn-accent">User Sign up</a>
        <a href="<?= htmlspecialchars($businessLoginUrl) ?>" class="btn btn-dark">Business Log in</a>
      </div>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="container hero-grid">
        <div class="hero-left">
          <div class="hero-logo-top fade-up">⌘</div>

          <div class="hero-mini-tabs fade-up delay-1">
            <span>Search</span>
            <span class="active">Book</span>
            <span>Grow</span>
          </div>

          <div class="hero-title-wrap">
            <h1 class="fade-up delay-2">
              Book local services with
              <span class="animated-words">
                <span>ease</span>
                <span>speed</span>
                <span>confidence</span>
              </span>
            </h1>

            <p class="hero-text fade-up delay-3">
              Find trusted businesses, compare services, choose staff and book instantly.
            </p>
          </div>

          <div class="search-hero-box fade-up delay-4">
            <form action="<?= htmlspecialchars($exploreUrl) ?>" method="get">
              <div class="search-shell">
                <div class="search-icon">⌕</div>
                <input
                  type="text"
                  name="q"
                  class="hero-search-input"
                  id="heroSearchInput"
                  placeholder="Search for haircut, manicure, gym, tattoo..."
                >
                <button type="submit" class="search-send">↑</button>
              </div>
            </form>

            <div class="prompt-tags">
              <div class="prompt-tag search-fill" data-text="Haircut near me">Look up</div>
              <div class="prompt-tag search-fill" data-text="Best nail salon in İzmir">Compare</div>
              <div class="prompt-tag search-fill" data-text="Book skin care appointment">Book</div>
              <div class="prompt-tag search-fill" data-text="Find gym with trainer">Explore</div>
              <div class="prompt-tag search-fill" data-text="Tattoo studio open today">Try this</div>
            </div>
          </div>

          <div class="hero-actions fade-up delay-4">
            <a href="<?= htmlspecialchars($userLoginUrl) ?>" class="btn btn-accent">Get started</a>
            <a href="<?= htmlspecialchars($businessLoginUrl) ?>" class="btn btn-dark">Business portal</a>
          </div>
        </div>

        <div class="hero-right">
          <div class="mockup-card">
            <div class="floating-pill pill-1">⚡ Instant Booking</div>
            <div class="floating-pill pill-2">✔ Verified Businesses</div>
            <div class="floating-pill pill-3">🔥 Offers & Promotions</div>

            <div class="mockup-phone">
              <div class="mockup-screen">
                <div class="mock-top">Book your next appointment</div>

                <div class="mock-business">
                  <h4>Glow Beauty Center</h4>
                  <div class="mock-badge">4.9 rating • 1.2k reviews</div>
                </div>

                <div class="mock-services">
                  <div class="service-item">
                    <div class="service-meta">
                      <h5>Haircut & styling</h5>
                      <p>45 min • Instant confirmation</p>
                    </div>
                    <div class="price-tag">₺450</div>
                  </div>

                  <div class="service-item">
                    <div class="service-meta">
                      <h5>Gel manicure</h5>
                      <p>60 min • Staff selection</p>
                    </div>
                    <div class="price-tag">₺600</div>
                  </div>

                  <div class="service-item">
                    <div class="service-meta">
                      <h5>Skin care session</h5>
                      <p>90 min • Deposit required</p>
                    </div>
                    <div class="price-tag">₺850</div>
                  </div>

                  <div class="service-item">
                    <div class="service-meta">
                      <h5>Personal training</h5>
                      <p>50 min • Available today</p>
                    </div>
                    <div class="price-tag">₺700</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="stats-row reveal">
            <div class="stat-box">
              <h3>10k+</h3>
              <p>service bookings</p>
            </div>
            <div class="stat-box">
              <h3>500+</h3>
              <p>local businesses</p>
            </div>
            <div class="stat-box">
              <h3>4.9</h3>
              <p>average satisfaction</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="reveal">
      <div class="container">
        <div class="split-grid">
          <div class="split-card customer">
            <h3>For Customers</h3>
            <p>
              Search businesses, compare services, choose staff, see availability and book without calling.
            </p>
            <div class="bullet-list">
              <span>Discover trusted local businesses</span>
              <span>Compare services, reviews and prices</span>
              <span>Choose the right staff member</span>
              <span>Book instantly in a few clicks</span>
            </div>
            <a href="<?= htmlspecialchars($userSignupUrl) ?>" class="btn btn-dark">Start booking</a>
          </div>

          <div class="split-card business">
            <h3>For Businesses</h3>
            <p>
              Manage services, staff and working hours. Receive bookings, promote your business and grow faster.
            </p>
            <div class="bullet-list">
              <span>Add products and bookable services</span>
              <span>Manage staff and availability</span>
              <span>Receive offers and customer messages</span>
              <span>Boost visibility with advertising packages</span>
            </div>
            <a href="<?= htmlspecialchars($businessLoginUrl) ?>" class="btn btn-accent">Grow your business</a>
          </div>
        </div>
      </div>
    </section>

    <section id="how-it-works" class="reveal">
      <div class="container">
        <div class="section-heading">
          <h2>How it works</h2>
          <p>Simple for customers, powerful for businesses.</p>
        </div>

        <div class="categories-grid">
          <div class="category-card">
            <div class="cat-icon">🔎</div>
            <div>
              <h4>Search</h4>
              <p>Find a service or business near you with a clean and quick discovery flow.</p>
            </div>
          </div>

          <div class="category-card">
            <div class="cat-icon">📋</div>
            <div>
              <h4>Choose</h4>
              <p>Compare services, staff, pricing, reviews and available booking times.</p>
            </div>
          </div>

          <div class="category-card">
            <div class="cat-icon">📅</div>
            <div>
              <h4>Book</h4>
              <p>Reserve your appointment in seconds and keep everything organized in one place.</p>
            </div>
          </div>

          <div class="category-card">
            <div class="cat-icon">🚀</div>
            <div>
              <h4>Grow</h4>
              <p>Businesses can manage operations and increase visibility with built-in promotion tools.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="categories" class="reveal">
      <div class="container">
        <div class="section-heading">
          <h2>Popular categories</h2>
          <p>Designed for both everyday needs and appointment-based services.</p>
        </div>

        <div class="categories-grid">
          <div class="category-card">
            <div class="cat-icon">💇</div>
            <div><h4>Hairdresser</h4><p>Haircuts, styling, coloring and care services.</p></div>
          </div>

          <div class="category-card">
            <div class="cat-icon">💅</div>
            <div><h4>Nail Bar</h4><p>Manicure, pedicure and beauty care sessions.</p></div>
          </div>

          <div class="category-card">
            <div class="cat-icon">🏋️</div>
            <div><h4>Gym</h4><p>Training sessions, memberships and wellness bookings.</p></div>
          </div>

          <div class="category-card">
            <div class="cat-icon">🩺</div>
            <div><h4>Clinic</h4><p>Appointments, consultations and service scheduling.</p></div>
          </div>

          <div class="category-card">
            <div class="cat-icon">🛠️</div>
            <div><h4>Repair</h4><p>Technical fixes, maintenance and service requests.</p></div>
          </div>

          <div class="category-card">
            <div class="cat-icon">☕</div>
            <div><h4>Cafe</h4><p>Discover local spots, offers and popular places nearby.</p></div>
          </div>

          <div class="category-card">
            <div class="cat-icon">🍽️</div>
            <div><h4>Restaurant</h4><p>Explore trending businesses and featured locations.</p></div>
          </div>

          <div class="category-card">
            <div class="cat-icon">🐾</div>
            <div><h4>Pet Shop</h4><p>Pet care, services, supplies and specialized bookings.</p></div>
          </div>
        </div>
      </div>
    </section>

    <section class="reveal">
      <div class="container">
        <div class="section-heading">
          <h2>Everything your users need</h2>
          <p>A booking experience that feels simple, modern and fast.</p>
        </div>

        <div class="features-wrap">
          <div class="features-grid">
            <div class="feature-column">
              <div class="feature-card">
                <h4>Real-time availability</h4>
                <p>Customers can quickly see available times and choose what fits best.</p>
              </div>
              <div class="feature-card">
                <h4>Staff selection</h4>
                <p>Let users book the exact person they want for the service.</p>
              </div>
              <div class="feature-card">
                <h4>Verified reviews</h4>
                <p>Build trust with transparent feedback and visible customer experience.</p>
              </div>
            </div>

            <div class="center-phone">
              <div class="screen">
                <div class="mini-box">Upcoming appointment</div>
                <div class="mini-box">Choose staff member</div>
                <div class="mini-box">Best offers nearby</div>
                <div class="mini-box">Fast booking confirmation</div>
                <div class="mini-box">Message the business</div>
                <div class="mini-box">Easy reschedule flow</div>
              </div>
            </div>

            <div class="feature-column">
              <div class="feature-card">
                <h4>Offers & discounts</h4>
                <p>Promote special deals and highlight attractive services.</p>
              </div>
              <div class="feature-card">
                <h4>Messaging</h4>
                <p>Allow direct communication between customers and businesses.</p>
              </div>
              <div class="feature-card">
                <h4>Smart discovery</h4>
                <p>Help users explore the right categories, businesses and services faster.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="business" class="reveal">
      <div class="container">
        <div class="business-section">
          <div>
            <div class="eyebrow" style="background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.08); color: rgba(255,255,255,0.78);">
              Business management made simple
            </div>

            <h2 style="margin:0; font-size: clamp(30px, 4vw, 54px); line-height:1.05; letter-spacing:-0.04em;">
              Everything businesses need to grow.
            </h2>

            <p>
              Manage services, staff, working hours, incoming offers and customer communication from one dashboard.
            </p>

            <div class="dark-list">
              <div>Manage staff and assign services</div>
              <div>Set business hours and availability</div>
              <div>Receive bookings, messages and offers</div>
              <div>Promote your business with featured advertising</div>
            </div>

            <a href="<?= htmlspecialchars($businessLoginUrl) ?>" class="btn btn-accent">Open business panel</a>
          </div>

          <div class="business-preview">
            <div class="preview-head">
              <h3 style="margin:0; font-size:28px;">Business Dashboard</h3>
              <span class="preview-pill">Live</span>
            </div>

            <div class="preview-item">Manage products and bookable services</div>
            <div class="preview-item">Edit staff, roles and service assignments</div>
            <div class="preview-item">Set weekly working hours</div>
            <div class="preview-item">Reply to reviews and customer messages</div>
            <div class="preview-item">Buy Bronze / Silver / Gold visibility packages</div>
          </div>
        </div>
      </div>
    </section>

    <section id="ads" class="reveal">
      <div class="container">
        <div class="section-heading">
          <h2>Boost visibility with advertising</h2>
          <p>Simple packages for businesses that want more discovery, more traffic and more bookings.</p>
        </div>

        <div class="ad-cards">
          <div class="ad-card" style="background:#fff8f0; color:#1b1b1b; border:1px solid #efe2d4;">
            <h4>Bronze</h4>
            <div class="price">199₺</div>
            <ul style="color:#555;">
              <li>Featured on home</li>
              <li>Better visibility for new customers</li>
              <li>Entry-level promotion</li>
            </ul>
            <a href="<?= htmlspecialchars($businessLoginUrl) ?>" class="btn btn-dark">Get started</a>
          </div>

          <div class="ad-card" style="background:#f5f1ee; color:#1b1b1b; border:1px solid #e8e1d7;">
            <h4>Silver</h4>
            <div class="price">499₺</div>
            <ul style="color:#555;">
              <li>Featured on home</li>
              <li>Boosted in search results</li>
              <li>Great for category discovery</li>
            </ul>
            <a href="<?= htmlspecialchars($businessLoginUrl) ?>" class="btn btn-dark">Choose silver</a>
          </div>

          <div class="ad-card" style="background:#12070b; color:#fff; border:1px solid rgba(255,255,255,0.05);">
            <h4>Gold</h4>
            <div class="price">599₺</div>
            <ul>
              <li>Featured on home</li>
              <li>Boosted in search results</li>
              <li>Highlighted on map</li>
            </ul>
            <a href="<?= htmlspecialchars($businessLoginUrl) ?>" class="btn btn-accent">Choose gold</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container footer-box">
      <div>
        <strong style="display:block; font-size:20px; margin-bottom:8px; color:#2a1019;">Pricely</strong>
        <span>Discover, book and grow with local services.</span>
      </div>

      <div class="footer-links">
        <a href="#categories">Services</a>
        <a href="#business">For businesses</a>
        <a href="<?= htmlspecialchars($userLoginUrl) ?>">User Log in</a>
        <a href="<?= htmlspecialchars($userSignupUrl) ?>">User Sign up</a>
        <a href="<?= htmlspecialchars($businessLoginUrl) ?>">Business Log in</a>
      </div>
    </div>
  </footer>

  <script>
    window.addEventListener('scroll', function () {
      const nav = document.querySelector('.navbar');
      if (window.scrollY > 20) {
        nav.classList.add('scrolled');
      } else {
        nav.classList.remove('scrolled');
      }
    });

    const reveals = document.querySelectorAll('.reveal');

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('show');
        }
      });
    }, { threshold: 0.15 });

    reveals.forEach(el => observer.observe(el));

    const rotatingPlaceholders = [
      "Search for haircut, manicure, gym, tattoo...",
      "Find the best nail salon near you...",
      "Book a skin care appointment today...",
      "Explore gyms, clinics, cafés and more..."
    ];

    const heroSearchInput = document.getElementById('heroSearchInput');
    let placeholderIndex = 0;

    setInterval(() => {
      placeholderIndex = (placeholderIndex + 1) % rotatingPlaceholders.length;
      if (heroSearchInput !== document.activeElement) {
        heroSearchInput.setAttribute('placeholder', rotatingPlaceholders[placeholderIndex]);
      }
    }, 2600);

    document.querySelectorAll('.search-fill').forEach(tag => {
      tag.addEventListener('click', function () {
        heroSearchInput.value = this.dataset.text;
        heroSearchInput.focus();
      });
    });
  </script>
</body>
</html>