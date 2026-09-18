<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#143d35">
    <title><?= e(config()['app_name']) ?> · IT-Zeiterfassung</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/app.css?v=4">
    <script src="/assets/js/app.js?v=4" defer></script>
</head>
<body>
<a href="#main" class="skip-link">Zum Inhalt</a>
<script id="boot" type="application/json"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php if (!$actor): ?>
<div class="login-layout">
    <section class="login-story" aria-label="Willkommen">
        <a class="brand brand-light" href="/"><span class="brand-mark">z<span>·</span></span><span><?= e(config()['app_name']) ?><small>ZEIT FÜR DEN ÜBERBLICK</small></span></a>
        <div class="login-story-copy"><span class="eyebrow text-emerald-200">GUTE ARBEIT. KLAR ERFASST.</span><h1>Mehr Zeit für<br>die eigentliche<br><em>Arbeit.</em></h1><p>Ein gemeinsamer Ort für eure IT-Leistungen.<br>Einfach festhalten. Zusammen den Überblick behalten.</p></div>
        <div class="login-story-bottom"><span class="status-dot"></span> Gemeinsam organisiert. Nachvollziehbar abgerechnet.</div>
    </section>
    <main id="main" class="login-main">
        <div class="login-card"><span class="eyebrow">WILLKOMMEN ZURÜCK</span><h2>Anmelden</h2><p class="muted mb-8">Dein Zugang zu <?= e(config()['company_name']) ?>.</p>
            <form data-action="login" class="form-stack">
                <label>Benutzername<input name="username" autocomplete="username" required maxlength="64" autofocus placeholder="Dein Benutzername"></label>
                <label>Passwort<input name="password" type="password" autocomplete="current-password" required maxlength="256" placeholder="Dein Passwort"></label>
                <div class="form-error" role="alert" hidden></div>
                <button class="btn btn-primary btn-wide" type="submit">Anmelden <span aria-hidden="true">→</span></button>
            </form>
            <p class="text-sm muted mt-7">Du brauchst einen Zugang oder ein neues Passwort?<br>Dein Administrator hilft dir weiter.</p>
        </div>
        <p class="login-footer"><?= e(config()['app_name']) ?> · IT-Zeiterfassung</p>
    </main>
</div>
<?php else: ?>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="/"><span class="brand-mark">z<span>·</span></span><span><?= e(config()['app_name']) ?><small>IT-ZEITERFASSUNG</small></span></a>
        <div class="workspace-label"><span class="workspace-icon">↗</span><span><?= e(config()['company_name']) ?><small><?= $actor['kind']==='customer'?'Kundenbereich':'Dein Arbeitsbereich' ?></small></span></div>
        <nav id="navigation" aria-label="Hauptnavigation"></nav>
        <div class="sidebar-bottom"><div class="sidebar-note"><span class="status-dot"></span><?= $actor['kind']==='customer'?'Freigegebene Leistungen':'Ein Team. Ein Überblick.' ?></div><button class="profile" data-command="profile"><span class="avatar"><?= e(strtoupper(substr($actor['display_name'],0,1))) ?></span><span><?= e($actor['display_name']) ?><small><?= $actor['is_admin']?'Administrator':($actor['kind']==='customer'?'Kundenzugang':'Teammitglied') ?></small></span><span class="ml-auto" aria-hidden="true">⌄</span></button></div>
    </aside>
    <div class="app-content">
        <header class="topbar"><button class="icon-button mobile-menu" data-command="menu" aria-label="Menü öffnen" aria-controls="sidebar" aria-expanded="false">☰</button><span class="topbar-label">Arbeitsbereich <span aria-hidden="true">/</span> <strong id="breadcrumb">Übersicht</strong></span><div class="topbar-right"><span class="today-label"><?= e((new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('d.m.Y')) ?></span><button class="icon-button" data-command="logout" aria-label="Abmelden" title="Abmelden">↪</button></div></header>
        <main id="main" class="main-content" tabindex="-1"><div class="loading-state">Dein Arbeitsbereich wird geladen …</div></main>
        <footer class="app-footer"><span><?= e(config()['app_name']) ?> · Klar erfasst.</span><span>Zeiten in Europe/Berlin · Beträge netto in EUR</span></footer>
    </div>
</div>
<?php endif ?>
<dialog id="modal" aria-labelledby="modal-title"><div class="dialog-content" id="modal-content"></div></dialog>
<div id="toast" role="status" aria-live="polite" hidden></div>
<noscript><div class="noscript">Bitte JavaScript aktivieren, um die Zeiterfassung zu bedienen.</div></noscript>
</body>
</html>
