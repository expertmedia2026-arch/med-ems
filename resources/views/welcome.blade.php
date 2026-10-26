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
        <div class="site-header__inner" x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false">
            <a class="brand-lockup" href="{{ url('/') }}" aria-label="MedEMS home">
                <img src="{{ asset('images/expert-media-logo.png') }}" alt="Expert Media Solutions Ltd" class="brand-lockup__logo">
                <span class="brand-lockup__divider" aria-hidden="true"></span>
                <span class="brand-lockup__name">Med<span>EMS</span></span>
            </a>
            <nav class="site-nav" id="site-navigation" :class="{ 'site-nav--open': menuOpen }" aria-label="Main navigation">
                <a href="#platform" @click="menuOpen = false">Platform</a>
                <a href="#capabilities" @click="menuOpen = false">Capabilities</a>
                <a href="#built-for-care" @click="menuOpen = false">About</a>
            </nav>
            <div class="site-header__actions">
                <button class="menu-toggle" type="button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen.toString()" aria-controls="site-navigation" :aria-label="menuOpen ? 'Close navigation menu' : 'Open navigation menu'">
                    <span class="menu-toggle__lines" :class="{ 'is-active': menuOpen }" aria-hidden="true"><i></i><i></i><i></i></span>
                </button>
                <a class="button button--dark header-login" href="{{ route('login') }}">Sign in <span aria-hidden="true">↗</span></a>
            </div>
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

                <div class="hero-visual" role="img" aria-label="MedEMS admin dashboard preview showing summary cards and the live patient queue">
                    <div class="hero-visual__orb hero-visual__orb--one"></div>
                    <div class="hero-visual__orb hero-visual__orb--two"></div>
                    <div class="admin-preview">
                        <aside class="admin-preview__sidebar">
                            <div class="preview-brand"><span class="preview-brand__mark">M</span><span>Med<span>EMS</span></span></div>
                            <span class="preview-sidebar__label">HOSPITAL WORKSPACE</span>
                            <div class="preview-nav preview-nav--active"><span>▦</span>Dashboard</div>
                            <div class="preview-nav"><span>＋</span>Patients</div>
                            <div class="preview-nav"><span>▤</span>Staff</div>
                            <div class="preview-nav"><span>▦</span>Departments</div>
                            <div class="preview-nav"><span>◷</span>Consultation Pricing</div>
                            <div class="preview-nav"><span>◷</span>Appointments</div>
                            <div class="preview-nav"><span>＋</span>A&amp;E</div>
                            <div class="preview-nav"><span>▧</span>A&amp;E Pharmacy</div>
                            <div class="preview-nav"><span>⌁</span>Mortuary</div>
                            <div class="preview-nav"><span>◉</span>Triage</div>
                            <div class="preview-nav"><span>＋</span>Medication administration</div>
                            <div class="preview-nav"><span>◷</span>Staff Duty Roster</div>
                            <div class="preview-nav"><span>⌁</span>Admissions</div>
                            <div class="preview-nav"><span>⌕</span>Laboratory</div>
                            <div class="preview-nav"><span>⌕</span>External Lab Request</div>
                            <div class="preview-nav"><span>▧</span>Pharmacy</div>
                            <div class="preview-nav"><span>₦</span>Billing</div>
                            <div class="preview-nav"><span>₦</span>Finance overview</div>
                            <div class="preview-nav"><span>⚙</span>Hospital Settings</div>
                            <div class="preview-sidebar__foot"><span class="preview-sidebar__avatar">A</span><span><b>Hospital Admin</b><small>Administrator</small></span><span class="preview-sidebar__dots">•••</span></div>
                        </aside>
                        <div class="admin-preview__main">
                            <div class="preview-topbar"><span class="preview-topbar__menu">☰</span><span>Dashboard</span><span class="preview-topbar__right"><span class="preview-notification">♧</span><span class="preview-avatar">A</span><span class="preview-user-name">Hospital Admin<small>Administrator</small></span></span></div>
                            <div class="preview-content">
                                <div class="preview-page-heading"><div><h2>Welcome, Hospital Admin</h2><p>Administrator · {{ now()->format('l, d F Y') }}</p></div><span class="preview-date">Today⌄</span></div>
                                <div class="preview-card-grid">
                                    <div class="preview-stat preview-stat--blue"><span>Patients today</span><b>24</b></div>
                                    <div class="preview-stat preview-stat--amber"><span>In queue</span><b>16</b></div>
                                    <div class="preview-stat preview-stat--amber"><span>Pending lab requests</span><b>06</b></div>
                                    <div class="preview-stat preview-stat--green"><span>Revenue today</span><b>₦240k</b></div>
                                    <div class="preview-stat preview-stat--blue"><span>Awaiting dispensing</span><b>02</b></div>
                                    <div class="preview-stat preview-stat--teal"><span>Pharmacy patients today</span><b>08</b></div>
                                    <div class="preview-stat preview-stat--green"><span>Dispensed today</span><b>12</b></div>
                                    <div class="preview-stat preview-stat--teal"><span>Staff duty roster</span><b>8 shifts this week</b></div>
                                    <div class="preview-stat preview-stat--teal"><span>Active admissions</span><b>01</b></div>
                                    <div class="preview-stat preview-stat--amber"><span>Admission results to review</span><b>03</b></div>
                                    <div class="preview-stat preview-stat--blue"><span>Nursing visits to finish</span><b>02</b></div>
                                    <div class="preview-stat preview-stat--red"><span>Low-stock drugs</span><b>02</b></div>
                                    <div class="preview-stat preview-stat--red"><span>Unpaid invoices</span><b>20</b></div>
                                    <div class="preview-stat preview-stat--red"><span>Outstanding balance</span><b>₦100,490</b></div>
                                    <div class="preview-stat preview-stat--red"><span>Active A&amp;E visits</span><b>03</b></div>
                                </div>
                                <div class="preview-queue">
                                    <div class="preview-queue__heading"><b>Live queue</b><span>View all <i>↗</i></span></div>
                                    <div class="preview-queue__table">
                                        <div class="preview-queue__row preview-queue__row--head"><span>Patient</span><span>Department</span><span>Complaint</span><span>Doctor</span><span>Waiting</span><span>Status</span></div>
                                        <div class="preview-queue__row"><span>Patient A</span><span>General Medicine</span><span>Outpatient visit</span><span>Unassigned</span><span>12 min</span><em>Waiting for triage</em></div>
                                        <div class="preview-queue__row"><span>Patient B</span><span>Paediatrics</span><span>Outpatient visit</span><span>Unassigned</span><span>08 min</span><em>Waiting for triage</em></div>
                                        <div class="preview-queue__row"><span>Patient C</span><span>Cardiology</span><span>Follow-up</span><span>Dr. A.</span><span>05 min</span><em class="preview-queue__status--active">In consultation</em></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="preview-caption"><span class="preview-caption__dot"></span> Admin dashboard <span>•</span> Example overview</div>
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
