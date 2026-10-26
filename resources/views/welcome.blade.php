<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fff">
    <meta name="description" content="MedEMS brings the essential work of modern hospitals into one clear, connected system.">
    <title>MedEMS | Hospital care, better connected</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="medems-site">
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand-lockup" href="{{ url('/') }}" aria-label="MedEMS home">
                <img src="{{ asset('images/expert-media-logo.png') }}" alt="Expert Media Solutions Ltd" class="brand-lockup__logo">
                <span class="brand-lockup__divider" aria-hidden="true"></span>
                <span class="brand-lockup__name">Med<span>EMS</span></span>
            </a>
            <nav class="site-nav" aria-label="Main navigation">
                <a href="#platform">Platform</a>
                <a href="#capabilities">Capabilities</a>
                <a href="#built-for-care">About</a>
            </nav>
            <a class="button button--dark header-login" href="{{ route('login') }}">Sign in <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <main>
        <section class="hero" id="platform">
            <div class="hero__grid" aria-hidden="true"></div>
            <div class="hero__inner">
                <div class="hero__copy">
                    <p class="eyebrow"><span class="eyebrow__dot"></span> A clearer way to care</p>
                    <h1>Care moves better<br>when everything<br><span>works together.</span></h1>
                    <p class="hero__description">The hospital management system that brings your people, patients and everyday operations into one connected place.</p>
                    <div class="hero__actions">
                        <a class="button button--red" href="{{ route('login') }}">Access your workspace <span aria-hidden="true">→</span></a>
                        <a class="text-link" href="#capabilities">Explore the platform <span aria-hidden="true">↓</span></a>
                    </div>
                    <div class="hero__note"><span class="hero__note-icon" aria-hidden="true">✓</span> Thoughtfully designed for the people behind every patient journey</div>
                </div>

                <div class="hero-visual" aria-label="Illustration of the MedEMS hospital operations dashboard">
                    <div class="hero-visual__orb hero-visual__orb--one"></div>
                    <div class="hero-visual__orb hero-visual__orb--two"></div>
                    <div class="dashboard-card">
                        <div class="dashboard-card__top">
                            <div class="dashboard-brand"><span class="dashboard-brand__mark">M</span><span>Med<span>EMS</span></span></div>
                            <div class="dashboard-top__right"><span class="live-dot"></span> Live overview <span class="avatar">AD</span></div>
                        </div>
                        <div class="dashboard-card__body">
                            <div class="dashboard-heading"><div><span class="dashboard-kicker">MONDAY, 24 JUNE 2024</span><h2>Good morning, Admin</h2><p>Here’s what’s happening at your hospital today.</p></div><span class="dashboard-menu">•••</span></div>
                            <div class="stat-grid">
                                <div class="stat-card"><span class="stat-card__label">Patients today</span><strong>128</strong><span class="stat-card__change">↑ 12% <small>vs last week</small></span><span class="stat-icon stat-icon--red" aria-hidden="true">＋</span></div>
                                <div class="stat-card"><span class="stat-card__label">Appointments</span><strong>36</strong><span class="stat-card__change stat-card__change--dark">14 upcoming</span><span class="stat-icon stat-icon--gray" aria-hidden="true">▦</span></div>
                                <div class="stat-card"><span class="stat-card__label">In consultation</span><strong>08</strong><span class="stat-card__change stat-card__change--dark">Across 4 departments</span><span class="stat-icon stat-icon--gray" aria-hidden="true">♡</span></div>
                            </div>
                            <div class="dashboard-lower">
                                <div class="activity-panel"><div class="panel-heading"><strong>Today’s activity</strong><span>View report ↗</span></div><div class="chart"><div class="chart__y"><span>40</span><span>30</span><span>20</span><span>10</span></div><div class="chart__main"><div class="chart__gridlines"><i></i><i></i><i></i><i></i></div><svg viewBox="0 0 360 112" role="img" aria-label="Patient activity rising through the day" preserveAspectRatio="none"><defs><linearGradient id="chart-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#e51d2a" stop-opacity=".18"/><stop offset="1" stop-color="#e51d2a" stop-opacity="0"/></linearGradient></defs><path d="M0 86 C28 80 34 57 68 64 S105 80 135 52 S173 60 204 39 S247 48 269 31 S316 40 360 12 L360 112 L0 112Z" fill="url(#chart-fill)"/><path d="M0 86 C28 80 34 57 68 64 S105 80 135 52 S173 60 204 39 S247 48 269 31 S316 40 360 12" fill="none" stroke="#e51d2a" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round"/></svg><div class="chart__x"><span>8 AM</span><span>10 AM</span><span>12 PM</span><span>2 PM</span><span>4 PM</span></div></div></div></div>
                                <div class="schedule-panel"><div class="panel-heading"><strong>Next appointments</strong><span>Today⌄</span></div><div class="appointment"><span class="appointment__time">09:30</span><span class="appointment__avatar appointment__avatar--peach">AO</span><span class="appointment__details"><b>Amara Okafor</b><small>General consultation</small></span><span class="appointment__status">Waiting</span></div><div class="appointment"><span class="appointment__time">10:15</span><span class="appointment__avatar appointment__avatar--blue">TK</span><span class="appointment__details"><b>Tunde Kolawole</b><small>Cardiology follow-up</small></span><span class="appointment__status appointment__status--booked">Booked</span></div><div class="appointment"><span class="appointment__time">10:45</span><span class="appointment__avatar appointment__avatar--green">BM</span><span class="appointment__details"><b>Bisi Mohammed</b><small>Laboratory review</small></span><span class="appointment__status appointment__status--booked">Booked</span></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="floating-pill floating-pill--top"><span class="floating-pill__icon">✓</span><span><b>Everything in sync</b><small>Across your hospital</small></span></div>
                    <div class="floating-pill floating-pill--bottom"><span class="floating-pill__pulse"></span><span><b>Care, without the gaps</b><small>One connected workflow</small></span></div>
                </div>
            </div>
            <div class="hero__bottom"><span>ONE CONNECTED PLATFORM</span><span class="hero__bottom-line"></span><span>BUILT AROUND BETTER CARE</span></div>
        </section>

        <section class="capabilities section-wrap" id="capabilities">
            <div class="section-heading"><div><p class="eyebrow eyebrow--muted">One platform. Every department.</p><h2>Less time navigating.<br><span>More time caring.</span></h2></div><p>From the first point of contact to the final report, MedEMS helps your team stay informed, coordinated and focused on what matters.</p></div>
            <div class="capability-grid">
                <article class="capability-card"><span class="capability-card__number">01</span><span class="capability-card__icon capability-card__icon--red" aria-hidden="true">＋</span><h3>Patient care</h3><p>Keep patient records, visits and clinical notes together for a more complete view of every journey.</p><a href="{{ route('login') }}" aria-label="Sign in to patient care">↗</a></article>
                <article class="capability-card"><span class="capability-card__number">02</span><span class="capability-card__icon capability-card__icon--dark" aria-hidden="true">◷</span><h3>Appointments &amp; visits</h3><p>Coordinate schedules, check-ins and follow-ups with a clearer view of the day ahead.</p><a href="{{ route('login') }}" aria-label="Sign in to appointments and visits">↗</a></article>
                <article class="capability-card"><span class="capability-card__number">03</span><span class="capability-card__icon capability-card__icon--soft" aria-hidden="true">⌁</span><h3>Labs &amp; pharmacy</h3><p>Connect requests, results, prescriptions and inventory across your care teams.</p><a href="{{ route('login') }}" aria-label="Sign in to labs and pharmacy">↗</a></article>
                <article class="capability-card"><span class="capability-card__number">04</span><span class="capability-card__icon capability-card__icon--dark" aria-hidden="true">▤</span><h3>Operations &amp; billing</h3><p>Bring admissions, billing and essential hospital workflows into one reliable system.</p><a href="{{ route('login') }}" aria-label="Sign in to operations and billing">↗</a></article>
            </div>
        </section>

        <section class="care-band" id="built-for-care"><div class="care-band__inner"><div class="care-band__mark" aria-hidden="true">M</div><div><p class="eyebrow eyebrow--light">Built for the work behind care</p><h2>One hospital.<br><span>One clearer picture.</span></h2></div><p>Give every team a dependable way to work together, with the information they need close at hand.</p><a class="button button--white" href="{{ route('login') }}">Sign in to MedEMS <span aria-hidden="true">→</span></a><div class="care-band__decor" aria-hidden="true"></div></div></section>

        <section class="closing-cta section-wrap"><div><p class="eyebrow eyebrow--muted">Your team’s workspace</p><h2>Better connected starts here.</h2></div><a class="button button--red" href="{{ route('login') }}">Sign in to your workspace <span aria-hidden="true">→</span></a></section>
    </main>

    <footer class="site-footer"><div class="site-footer__inner"><a class="brand-lockup brand-lockup--footer" href="{{ url('/') }}" aria-label="MedEMS home"><img src="{{ asset('images/expert-media-logo.png') }}" alt="Expert Media Solutions Ltd" class="brand-lockup__logo"><span class="brand-lockup__divider" aria-hidden="true"></span><span class="brand-lockup__name">Med<span>EMS</span></span></a><p>Connected hospital operations. More time for care.</p><span>© {{ date('Y') }} MedEMS <i>·</i> By Expert Media Solutions Ltd</span></div></footer>
    </body>
</html>
