<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
header('Cache-Control: no-store, private');
ledgerStartSession();

$account = ledgerAuthAccount();
$authenticated = ledgerIsAuthenticated($account);
$contactSetupRequired = $authenticated && ledgerRecoveryContactRequired($account);
$showApplication = $authenticated && !$contactSetupRequired;
$authMode = $contactSetupRequired ? 'contact_setup' : 'login';
$scriptName = $showApplication ? 'app.js' : 'auth.js';
$scriptVersion = substr(hash_file('sha256', __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $scriptName), 0, 12);
$styleVersion = substr(hash_file('sha256', __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.css'), 0, 12);
$themeVersion = substr(hash_file('sha256', __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'theme.js'), 0, 12);
$transitionVersion = substr(hash_file('sha256', __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'page-transition.js'), 0, 12);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101812">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <title>PipTrack — Trading journal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="assets/theme.js?v=<?= htmlspecialchars($themeVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    <link rel="stylesheet" href="assets/app.css?v=<?= htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8') ?>">
    <script src="assets/page-transition.js?v=<?= htmlspecialchars($transitionVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="assets/<?= htmlspecialchars($scriptName, ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($scriptVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body data-auth-mode="<?= htmlspecialchars($authMode, ENT_QUOTES, 'UTF-8') ?>">
<div id="pageTransition" class="page-transition hidden" role="status" aria-live="polite" aria-atomic="true" aria-hidden="true">
    <div class="page-transition-content">
        <span class="page-transition-countdown" id="pageTransitionCountdown">3</span>
        <strong>Loading page</strong>
        <span>Please wait a moment.</span>
    </div>
</div>
<?php if ($showApplication): ?>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="#" aria-label="PipTrack home">
            <span class="brand-mark"><span></span><span></span><span></span></span>
            <span class="brand-name">PipTrack</span>
        </a>
        <div class="workspace-label">WORKSPACE</div>
        <div class="nav-group">
            <div class="nav-label">OVERVIEW</div>
            <button class="nav-item active" data-page="dashboard" type="button">
                <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="3" width="5.5" height="6" rx="1"/><rect x="11.5" y="3" width="5.5" height="3.5" rx="1"/><rect x="11.5" y="8.5" width="5.5" height="8.5" rx="1"/><rect x="3" y="11" width="5.5" height="6" rx="1"/></svg>
                Dashboard
            </button>
            <button class="nav-item" data-page="journal" type="button">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 3.5h8.5A1.5 1.5 0 0 1 15 5v12H6.5A1.5 1.5 0 0 1 5 15.5z"/><path d="M5 15.5A1.5 1.5 0 0 1 6.5 14H15M8 7h4M8 10h4"/></svg>
                Trade journal <span class="nav-count" id="tradeCount">0</span>
            </button>
            <button class="nav-item" data-page="strategy" type="button">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2.5 1.4 4.2 4.1 1.5-4.1 1.4-1.4 4.2-1.5-4.2-4.2-1.4 4.2-1.5L10 2.5Z"/><path d="m15.5 12.5.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"/></svg>
                Strategy
            </button>
            <button class="nav-item" data-page="weeklyAnalysis" type="button">
                <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4.5" width="14" height="13" rx="1.5"/><path d="M6.5 2.5v4m7-4v4M3 8h14m-10 3h2m3 0h2m-7 3h2"/></svg>
                Weekly analysis
            </button>
            <button class="nav-item" data-page="economicCalendar" type="button">
                <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4.5" width="14" height="13" rx="1.5"/><path d="M6.5 2.5v4m7-4v4M3 8h14"/><circle cx="7" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><path d="M7 15h5"/></svg>
                Economic calendar
            </button>
            <button class="nav-item" data-page="analytics" type="button">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 16.5V10m4.7 6.5V4m4.6 12.5V7m4.7 9.5V2.5"/></svg>
                Analytics
            </button>
        </div>
        <div class="sidebar-bottom">
            <div class="sidebar-tip">
                <div class="tip-icon"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5v3m0 9v3m7.5-7.5h-3m-9 0h-3m12.303-5.303-2.121 2.121m-6.364 6.364-2.121 2.121m10.606 0-2.121-2.121M6.318 6.318 4.197 4.197"/><circle cx="10" cy="10" r="4"/></svg></div>
                <strong>Small edges add up.</strong>
                <p>Show up, take notes, and let the data do the talking.</p>
            </div>
            <div class="profile-row"><span class="profile-avatar">T</span><span><strong><?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?></strong><small>Free plan</small></span></div>
            <button class="logout-button" id="logoutButton" type="button">Sign out</button>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open navigation"><svg viewBox="0 0 20 20"><path d="M3 5h14M3 10h14M3 15h14"/></svg></button>
            <div class="breadcrumbs"><span>Workspace</span><span class="crumb-slash">/</span><strong id="breadcrumbCurrent">Dashboard</strong></div>
            <div class="topbar-actions">
                <div class="topbar-date"><span class="live-dot"></span><span id="todayLabel"></span></div>
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode">
                    <svg class="theme-icon theme-icon-moon" viewBox="0 0 20 20" aria-hidden="true"><path d="M16.8 12.7A7.8 7.8 0 0 1 7.3 3.2a7.8 7.8 0 1 0 9.5 9.5Z"/></svg>
                    <svg class="theme-icon theme-icon-sun" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="3.3"/><path d="M10 1.8v2m0 12.4v2M1.8 10h2m12.4 0h2M4.2 4.2l1.4 1.4m8.8 8.8 1.4 1.4m0-11.6-1.4 1.4m-8.8 8.8-1.4 1.4"/></svg>
                </button>
                <button class="icon-button" id="exportButton" type="button" title="Export trades as CSV" aria-label="Export trades as CSV">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 3v9m0 0 3-3m-3 3L7 9M4 13v3.5h12V13"/></svg>
                </button>
                <button class="button button-primary top-add-button" id="addTradeButton" type="button"><span class="button-plus">+</span> Log a trade</button>
            </div>
        </header>

        <div class="page-wrap">
            <div id="toast" class="toast" role="status" aria-live="polite"></div>
            <section id="dashboardPage" class="page-section">
                <div class="page-heading">
                    <div><div class="eyebrow"><span class="eyebrow-line"></span> YOUR PERFORMANCE</div><h1>Good trading starts<br class="mobile-break"> with <span>good habits.</span></h1><p class="page-subtitle">A clear view of your decisions, one trade at a time.</p></div>
                    <div class="period-control" role="group" aria-label="Dashboard date range"><button class="period-button active" data-period="all" type="button">All time</button><button class="period-button" data-period="30" type="button">30 days</button><button class="period-button" data-period="7" type="button">7 days</button></div>
                </div>

                <div class="stats-grid">
                    <article class="stat-card"><div class="stat-top"><span id="winRateLabel">THIS WEEK WIN RATE</span><span class="stat-icon"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="m6.5 10 2.25 2.25L13.5 7.5"/></svg></span></div><div class="stat-value" id="winRate">0<span class="value-unit">%</span></div><div class="stat-foot"><span class="stat-context" id="winContext">Closed trades</span><span class="mini-track"><i id="winBar"></i></span></div></article>
                    <article class="stat-card"><div class="stat-top"><span>CLOSED WITHOUT P&amp;L</span><span class="stat-icon"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M10 6v4m0 4h.01"/></svg></span></div><div class="stat-value" id="missingPnlCount">0</div><div class="stat-foot"><span class="stat-context">Price fields are not recorded</span><span class="stat-foot-tag">Excluded from analytics</span></div></article>
                </div>

                <div class="dashboard-grid">
                    <section class="panel breakdown-panel">
                        <div class="panel-heading"><div><div class="panel-kicker">CONSISTENCY CHECK</div><h2>Trade outcomes</h2></div><span class="outcome-period" id="outcomePeriod">ALL TIME</span></div>
                        <div class="outcome-total"><strong id="outcomeTotal">0</strong><span>classified trades</span></div>
                        <div class="outcome-bar"><span class="outcome-win" id="outcomeWinBar"></span><span class="outcome-loss" id="outcomeLossBar"></span><span class="outcome-even" id="outcomeEvenBar"></span></div>
                        <div class="outcome-legend">
                            <div><span class="outcome-label"><i class="dot win-dot"></i> Winners</span><strong id="winnerCount">0</strong></div>
                            <div><span class="outcome-label"><i class="dot loss-dot"></i> Losers</span><strong id="loserCount">0</strong></div>
                            <div><span class="outcome-label"><i class="dot even-dot"></i> Breakeven</span><strong id="evenCount">0</strong></div>
                        </div>
                        <div class="best-trade-box"><span class="best-icon">↗</span><div><small>BEST TRADE</small><strong id="bestTrade">Log a trade to see your best.</strong></div><span id="bestTradePnl"></span></div>
                    </section>
                </div>

                <section class="panel recent-panel">
                    <div class="panel-heading recent-heading"><div><div class="panel-kicker">YOUR JOURNAL</div><h2>Recent trades</h2></div><button class="text-button" data-page="journal" type="button">View all trades <span>→</span></button></div>
                    <div class="table-wrap"><table class="trade-table"><thead><tr><th>INSTRUMENT</th><th>SETUP</th><th>DATE</th><th>OUTCOME</th><th>STATUS</th><th class="align-right">NET P&amp;L</th><th></th></tr></thead><tbody id="recentTradesBody"></tbody></table></div>
                    <div id="recentEmpty" class="empty-state"><div class="empty-illustration"><svg viewBox="0 0 64 64" aria-hidden="true"><path d="M15 12h26l9 9v31H15z"/><path d="M41 12v10h9M23 32h19M23 39h13M23 46h8"/></svg><span>↗</span></div><h3>Your next trade starts here.</h3><p>Log your first trade to start building a clearer picture of your edge.</p><button class="button button-primary" data-open-trade type="button"><span class="button-plus">+</span> Log your first trade</button></div>
                </section>
            </section>

            <section id="journalPage" class="page-section hidden">
                <div class="page-heading subpage-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> YOUR JOURNAL</div><h1>Every trade tells<br class="mobile-break"> a <span>story.</span></h1><p class="page-subtitle">Capture the details. Learn from the patterns.</p></div><button class="button button-primary" data-open-trade type="button"><span class="button-plus">+</span> Log a trade</button></div>
                <div class="journal-toolbar"><div class="search-wrap"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.8" cy="8.8" r="5.8"/><path d="m13 13 4 4"/></svg><input id="tradeSearch" type="search" placeholder="Search symbol, setup, notes..." aria-label="Search trades"></div><select id="statusFilter" class="filter-select" aria-label="Filter by status"><option value="all">All statuses</option><option value="closed">Closed</option><option value="open">Open</option></select><select id="sideFilter" class="filter-select" aria-label="Filter by direction"><option value="all">All directions</option><option value="long">Long</option><option value="short">Short</option></select><div class="journal-total" id="journalTotal">0 trades</div></div>
                <section class="panel journal-panel"><div class="table-wrap"><table class="trade-table journal-table"><thead><tr><th>INSTRUMENT</th><th>DIRECTION</th><th>SETUP</th><th>TRADE DATES</th><th>OUTCOME</th><th>STATUS</th><th class="align-right">NET P&amp;L</th><th></th></tr></thead><tbody id="journalTradesBody"></tbody></table></div><div id="journalEmpty" class="empty-state"><div class="empty-illustration"><svg viewBox="0 0 64 64" aria-hidden="true"><path d="M15 12h26l9 9v31H15z"/><path d="M41 12v10h9M23 32h19M23 39h13M23 46h8"/></svg><span>↗</span></div><h3>No trades in your journal yet.</h3><p>Start with one. You can always add the details later.</p><button class="button button-primary" data-open-trade type="button"><span class="button-plus">+</span> Log a trade</button></div><div id="filterEmpty" class="filter-empty hidden">No trades match those filters. Try a different search.</div></section>
            </section>

            <section id="strategyPage" class="page-section hidden">
                <div class="page-heading subpage-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> YOUR PLAYBOOK</div><h1>Strategy <span>library.</span></h1><p class="page-subtitle">Define your rules so every trade has a clear plan.</p></div></div>
                <section class="panel strategy-form-panel">
                    <div class="panel-heading"><div><div class="panel-kicker">BUILD YOUR PLAYBOOK</div><h2 id="strategyFormTitle">Add a strategy</h2></div></div>
                    <form id="strategyForm">
                        <input type="hidden" name="id" id="strategyId">
                        <div class="strategy-form-grid">
                            <label class="form-field"><span>Strategy name <b>*</b></span><input name="name" id="strategyName" required maxlength="80" placeholder="e.g. Breakout retest"></label>
                            <label class="form-field"><span>Market</span><select name="market" id="strategyMarket"><option value="">Any market</option><option>Stocks</option><option>Forex</option><option>Futures</option><option>Crypto</option><option>Options</option><option>Other</option></select></label>
                            <label class="form-field strategy-wide-field"><span>Entry rules <b>*</b></span><textarea name="entry_rules" id="strategyEntryRules" rows="3" required maxlength="3000" placeholder="What conditions must be true before you enter?"></textarea></label>
                            <label class="form-field"><span>Exit rules</span><textarea name="exit_rules" id="strategyExitRules" rows="3" maxlength="3000" placeholder="How will you close or invalidate the trade?"></textarea></label>
                            <label class="form-field"><span>Risk management</span><textarea name="risk_management" id="strategyRiskManagement" rows="3" maxlength="2000" placeholder="Position sizing, risk limits, and safeguards"></textarea></label>
                            <label class="form-field strategy-wide-field"><span>Notes</span><textarea name="notes" id="strategyNotes" rows="2" maxlength="3000" placeholder="Additional reminders or examples"></textarea></label>
                        </div>
                        <div id="strategyFormError" class="modal-error hidden" role="alert"></div>
                        <div class="strategy-form-actions"><button class="button button-secondary hidden" id="cancelStrategyEdit" type="button">Cancel edit</button><button class="button button-primary" id="saveStrategy" type="submit">Save strategy</button></div>
                    </form>
                </section>
                <div class="strategy-list-heading"><div><div class="panel-kicker">YOUR STRATEGIES</div><h2>Saved strategies</h2></div><span class="journal-total" id="strategyCount">0 strategies</span></div>
                <div id="strategyList" class="strategy-grid"></div>
            </section>

            <section id="weeklyAnalysisPage" class="page-section hidden">
                <div class="page-heading subpage-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> WEEKLY MARKET PLAN</div><h1>Weekly <span>analysis.</span></h1><p class="page-subtitle">Record the key levels to watch for each pair and week, then review what happened.</p></div></div>
                <section class="panel weekly-plan-form-panel">
                    <div class="panel-heading"><div><div class="panel-kicker">PLAN YOUR WEEK</div><h2 id="weeklyPlanFormTitle">Add weekly levels</h2></div></div>
                    <form id="weeklyPlanForm">
                        <input type="hidden" name="id" id="weeklyPlanId">
                        <div class="weekly-plan-form-grid">
                            <label class="form-field"><span>Pair / instrument <b>*</b></span><input name="pair" id="weeklyPlanPair" required maxlength="20" placeholder="e.g. EURUSD, XAUUSD, US30"></label>
                            <label class="form-field"><span>Week of <b>*</b></span><input type="date" name="week_start" id="weeklyPlanWeek" required><small>Choose any date in the week; it will be saved as Monday's date.</small></label>
                            <label class="form-field weekly-plan-wide-field"><span>Expected key levels to watch <b>*</b></span><textarea name="key_levels" id="weeklyPlanLevels" rows="4" required maxlength="4000" placeholder="One level per line, including its price and context&#10;Support — 1.0800&#10;Resistance — 1.0950"></textarea></label>
                            <section class="screenshot-stage weekly-plan-wide-field">
                                <h3>Key-level screenshots</h3>
                                <p>Attach chart images that show the levels you plan to watch.</p>
                                <label class="screenshot-picker">Add screenshots<input id="weeklyPlanScreenshotInput" type="file" accept="image/png,image/jpeg,image/webp" multiple></label>
                                <small>PNG, JPG, or WebP · Up to 4 images, 2 MB each</small>
                                <div id="weeklyPlanScreenshotPreviews" class="screenshot-previews"></div>
                            </section>
                            <label class="form-field weekly-plan-wide-field"><span>Weekly outcome / review</span><textarea name="weekly_outcome" id="weeklyPlanOutcome" rows="3" maxlength="3000" placeholder="After the week, note which levels were reached and how price reacted."></textarea></label>
                        </div>
                        <div id="weeklyPlanFormError" class="modal-error hidden" role="alert"></div>
                        <div class="weekly-plan-form-actions"><button class="button button-secondary hidden" id="cancelWeeklyPlanEdit" type="button">Cancel edit</button><button class="button button-primary" id="saveWeeklyPlan" type="submit">Save weekly plan</button></div>
                    </form>
                </section>
                <div class="weekly-plan-list-heading"><div><div class="panel-kicker">YOUR WEEKLY PLANS</div><h2>Saved key levels</h2></div><span class="journal-total" id="weeklyPlanCount">0 plans</span></div>
                <div id="weeklyPlanList" class="weekly-plan-grid"></div>
            </section>

            <section id="economicCalendarPage" class="page-section hidden">
                <div class="page-heading subpage-heading economic-calendar-heading">
                    <div><div class="eyebrow"><span class="eyebrow-line"></span> MARKET EVENTS</div><h1>Economic <span>calendar.</span></h1><p class="page-subtitle">Review this week's scheduled releases and their market impact.</p></div>
                    <div class="economic-calendar-actions"><span id="economicCalendarUpdated" class="journal-total">Not loaded yet</span><button class="button button-secondary" id="refreshEconomicCalendar" type="button">Refresh events</button></div>
                </div>
                <div class="economic-calendar-toolbar panel">
                    <div class="economic-calendar-filters">
                        <label class="form-field"><span>Filter by impact</span><select id="economicCalendarImpactFilter"><option value="all">All impacts</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option><option value="holiday">Holiday</option><option value="other">Other</option></select></label>
                        <label class="form-field"><span>Filter by date</span><input id="economicCalendarDateFilter" type="date" aria-label="Filter events by date"></label>
                        <button class="button button-secondary hidden" id="clearEconomicCalendarDate" type="button">All dates</button>
                    </div>
                    <div class="economic-calendar-meta"><span id="economicCalendarResultCount">0 events</span><span>Times shown in your local timezone</span></div>
                </div>
                <section class="panel economic-calendar-panel">
                    <div class="economic-calendar-source-box">
                        <div class="economic-calendar-source-copy"><span>CALENDAR SOURCE</span><h2>Forex Factory</h2><p>Open the full calendar for more event details.</p></div>
                        <a class="economic-calendar-source-link" href="https://www.forexfactory.com/calendar" target="_blank" rel="noopener noreferrer">www.forexfactory.com/calendar <span aria-hidden="true">↗</span></a>
                    </div>
                    <div id="economicCalendarLoading" class="calendar-notice" role="status">Loading this week's economic events…</div>
                    <div id="economicCalendarError" class="calendar-notice calendar-error hidden" role="alert"></div>
                    <div id="economicCalendarEmpty" class="empty-state hidden"><h3>No events found for this week.</h3><p>Refresh the calendar to check the latest schedule.</p></div>
                    <div id="economicCalendarNoMatches" class="filter-empty hidden">No events match the selected date and impact filters.</div>
                    <div id="economicCalendarTableWrap" class="table-wrap hidden">
                        <table class="trade-table economic-calendar-table">
                            <thead><tr><th>DATE / TIME</th><th>CURRENCY</th><th>EVENT</th><th>IMPACT</th><th>ACTUAL</th><th>FORECAST</th><th>PREVIOUS</th></tr></thead>
                            <tbody id="economicCalendarEventsBody"></tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section id="analyticsPage" class="page-section hidden">
                <div class="page-heading subpage-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> YOUR TRADE DATA</div><h1>Analytics</h1><p class="page-subtitle">Explore outcomes by month, time, symbol, setup, and trade habits.</p></div></div>
                <div class="analytics-grid">
                    <section class="panel analytics-panel"><div class="panel-heading"><div><div class="panel-kicker">MONTH STATS</div><h2>Monthly outcomes</h2></div></div><div id="monthlyStats"></div></section>
                    <section class="panel analytics-panel">
                        <div class="panel-heading"><div><div class="panel-kicker">TIME STATS</div><h2>When you trade</h2></div></div>
                        <div class="analytics-subsection"><h3>Weekday</h3><div id="weekdayStats"></div></div>
                        <div class="analytics-subsection"><h3>Entry session</h3><div id="sessionStats"></div></div>
                    </section>
                    <section class="panel analytics-panel"><div class="panel-heading"><div><div class="panel-kicker">TICKER</div><h2>By symbol</h2></div></div><div id="tickerStats"></div></section>
                    <section class="panel analytics-panel">
                        <div class="panel-heading"><div><div class="panel-kicker">TRADE MANAGEMENT</div><h2>Plan &amp; mindset</h2></div></div>
                        <div class="analytics-subsection"><h3>Plan adherence</h3><div id="planStats"></div></div>
                        <div class="analytics-subsection"><h3>Emotion</h3><div id="emotionStats"></div></div>
                    </section>
                    <section class="panel analytics-panel"><div class="panel-heading"><div><div class="panel-kicker">SETUP STATS</div><h2>By setup</h2></div></div><div id="setupStats"></div></section>
                    <section class="panel analytics-panel">
                        <div class="panel-heading"><div><div class="panel-kicker">TRADE TYPES</div><h2>Market &amp; direction</h2></div></div>
                        <div class="analytics-subsection"><h3>Market</h3><div id="marketStats"></div></div>
                        <div class="analytics-subsection"><h3>Direction</h3><div id="directionStats"></div></div>
                    </section>
                </div>
            </section>

            <footer class="page-footer"><span>Made for the long game.</span><span><i class="footer-dot"></i> Your data stays on this device.</span></footer>
        </div>
    </main>
</div>

<div class="modal-backdrop hidden" id="tradeModal" role="presentation">
    <section class="trade-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-heading"><div><div class="panel-kicker">YOUR JOURNAL, YOUR RULES</div><h2 id="modalTitle">Log a trade</h2><p>Record the plan, the execution, and what you learned.</p></div><button class="modal-close" id="closeModal" type="button" aria-label="Close dialog">×</button></div>
        <form id="tradeForm">
            <input type="hidden" name="id" id="tradeId">
            <div class="form-section-title"><span>01</span> THE TRADE</div>
            <div class="form-grid">
                <label class="form-field"><span>Symbol <b>*</b></span><input name="symbol" id="symbol" required maxlength="20" placeholder="e.g. AAPL, ES, BTC/USD" autocomplete="off"></label>
                <label class="form-field"><span>Market</span><select name="market" id="market"><option value="Stocks">Stocks</option><option value="Forex">Forex</option><option value="Futures">Futures</option><option value="Crypto">Crypto</option><option value="Options">Options</option><option value="Other">Other</option></select></label>
                <label class="form-field"><span>Direction <b>*</b></span><div class="segmented-control"><label><input type="radio" name="side" value="long" checked><span>↗ Long</span></label><label><input type="radio" name="side" value="short"><span>↘ Short</span></label></div></label>
                <label class="form-field"><span>Status <b>*</b></span><select name="status" id="status"><option value="closed">Closed</option><option value="open">Open</option></select></label>
            </div>
            <p class="form-note">Entry and exit prices are not recorded, so new trades will have no P&amp;L and will be excluded from P&amp;L analytics.</p>
            <div class="form-section-title form-section-spaced"><span>02</span> TRADE SCREENSHOTS</div>
            <div class="screenshot-stages">
                <section class="screenshot-stage">
                    <h3>Before the trade</h3>
                    <p>Capture your chart, setup, and plan before entry.</p>
                    <label class="screenshot-picker">Add screenshots<input id="screenshotInputBefore" type="file" data-stage="before" accept="image/png,image/jpeg,image/webp" multiple></label>
                    <small>PNG, JPG, or WebP · Up to 4 images, 2 MB each</small>
                    <div id="screenshotPreviewsBefore" class="screenshot-previews"></div>
                </section>
                <section class="screenshot-stage">
                    <h3>During the trade</h3>
                    <p>Record how the trade develops and any decisions you make.</p>
                    <label class="screenshot-picker">Add screenshots<input id="screenshotInputDuring" type="file" data-stage="during" accept="image/png,image/jpeg,image/webp" multiple></label>
                    <small>PNG, JPG, or WebP · Up to 4 images, 2 MB each</small>
                    <div id="screenshotPreviewsDuring" class="screenshot-previews"></div>
                </section>
                <section class="screenshot-stage">
                    <h3>After the trade</h3>
                    <p>Save the final chart and review the outcome.</p>
                    <label class="screenshot-picker">Add screenshots<input id="screenshotInputAfter" type="file" data-stage="after" accept="image/png,image/jpeg,image/webp" multiple></label>
                    <small>PNG, JPG, or WebP · Up to 4 images, 2 MB each</small>
                    <div id="screenshotPreviewsAfter" class="screenshot-previews"></div>
                </section>
            </div>
            <div class="form-section-title form-section-spaced"><span>03</span> TRADE DATES</div>
            <div class="form-grid">
                <label class="form-field"><span>Entry date &amp; time <b>*</b></span><input name="entry_date" id="entryDate" type="datetime-local" required></label>
                <label class="form-field" id="exitDateField"><span>Exit date &amp; time <b>*</b></span><input name="exit_date" id="exitDate" type="datetime-local"></label>
            </div>
            <div class="form-section-title form-section-spaced">04 <span class="section-title-text">REFLECTION</span></div>
            <div class="form-grid">
                <label class="form-field" id="outcomeField"><span>Trade outcome <b>*</b></span><select name="outcome" id="outcome"><option value="">Select outcome</option><option value="win">Win</option><option value="loss">Loss</option><option value="breakeven">Breakeven</option></select></label>
                <label class="form-field"><span>Emotion / mindset</span><select name="emotion" id="emotion"><option value="">Select if useful</option><option>Confident</option><option>Patient</option><option>Focused</option><option>Anxious</option><option>FOMO</option><option>Frustrated</option><option>Overconfident</option><option>Other</option></select></label>
                <label class="form-field"><span>Did you follow your plan?</span><select name="followed_plan" id="followedPlan"><option value="">Not recorded</option><option value="yes">Yes</option><option value="no">No</option></select></label>
                <label class="form-field full-field"><span>Trade notes &amp; lessons</span><textarea name="notes" id="notes" rows="3" maxlength="4000" placeholder="What was the thesis? What went well? What would you do differently?"></textarea></label>
            </div>
            <div class="modal-error hidden" id="formError" role="alert"></div>
            <div class="modal-footer"><span class="form-required-note"><b>*</b> Required fields</span><div><button class="button button-secondary" id="cancelModal" type="button">Cancel</button><button class="button button-primary" id="saveTrade" type="submit">Save trade <span>→</span></button></div></div>
        </form>
    </section>
</div>
<div class="screenshot-lightbox hidden" id="screenshotLightbox" role="presentation">
    <section class="lightbox-dialog" role="dialog" aria-modal="true" aria-label="Screenshot viewer">
        <button class="lightbox-close" id="closeScreenshotLightbox" type="button" aria-label="Close screenshot">×</button>
        <img id="lightboxImage" src="" alt="">
        <p id="lightboxCaption"></p>
    </section>
</div>
<?php else: ?>
<main class="auth-page">
    <button class="theme-toggle auth-theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode">
        <svg class="theme-icon theme-icon-moon" viewBox="0 0 20 20" aria-hidden="true"><path d="M16.8 12.7A7.8 7.8 0 0 1 7.3 3.2a7.8 7.8 0 1 0 9.5 9.5Z"/></svg>
        <svg class="theme-icon theme-icon-sun" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="3.3"/><path d="M10 1.8v2m0 12.4v2M1.8 10h2m12.4 0h2M4.2 4.2l1.4 1.4m8.8 8.8 1.4 1.4m0-11.6-1.4 1.4m-8.8 8.8-1.4 1.4"/></svg>
    </button>
    <section class="auth-card">
        <a class="auth-brand" href="#" aria-label="PipTrack home">
            <span class="brand-mark"><span></span><span></span><span></span></span>
            <span class="brand-name">PipTrack</span>
        </a>
        <div class="auth-card-copy">
            <div class="panel-kicker" id="authKicker"><?= $contactSetupRequired ? 'ACCOUNT RECOVERY' : 'PRIVATE TRADING WORKSPACE' ?></div>
            <h1 id="authTitle"><?= $contactSetupRequired ? 'Add recovery contacts.' : 'Welcome to PipTrack.' ?></h1>
            <p id="authDescription"><?= $contactSetupRequired ? 'Add an email address and phone number so you can recover your account.' : 'Sign in or create an account to protect your trading journal.' ?></p>
        </div>
        <?php if (!$authenticated): ?>
            <nav class="auth-navigation" id="authNavigation" aria-label="Account options">
                <button class="auth-nav-button active" type="button" data-auth-mode="login">Sign in</button>
                <button class="auth-nav-button" type="button" data-auth-mode="signup">Sign up</button>
            </nav>
        <?php endif; ?>
        <div class="auth-notice hidden" id="authNotice" role="status"></div>
        <div class="auth-error hidden" id="authError" role="alert"></div>
        <?php if (!$authenticated): ?>
            <form class="auth-form" id="loginForm" data-auth-mode-form="login" data-auth-action="login">
                <label class="form-field"><span>Username</span><input name="username" type="text" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._\-]{3,32}" autocomplete="username" placeholder="Your username"></label>
                <label class="form-field"><span>Password</span><span class="password-input-wrap"><input id="loginPassword" name="password" type="password" required minlength="8" maxlength="72" autocomplete="current-password" placeholder="Your password"><button class="password-toggle" type="button" aria-controls="loginPassword" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle></svg><svg class="eye-off-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle><path d="m3 3 14 14"></path></svg></button></span></label>
                <label class="auth-remember-me"><input name="remember_me" type="checkbox" value="1"><span>Remember me for 30 days</span></label>
                <button class="button button-primary auth-submit" type="submit">Sign in</button>
                <button class="auth-text-button" type="button" data-auth-mode="forgot">Forgot password?</button>
            </form>
            <form class="auth-form hidden" id="signupForm" data-auth-mode-form="signup" data-auth-action="signup" hidden>
                <label class="form-field"><span>Username</span><input name="username" type="text" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._\-]{3,32}" autocomplete="username" placeholder="Choose a username"></label>
                <label class="form-field"><span>Email</span><input name="email" type="email" required maxlength="254" autocomplete="email" placeholder="you@example.com"></label>
                <label class="form-field"><span>Phone number</span><input name="phone" type="tel" required maxlength="32" pattern="\+[1-9][0-9]{6,14}" autocomplete="tel" placeholder="+254712345678"></label>
                <label class="form-field"><span>Password</span><span class="password-input-wrap"><input id="signupPassword" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="At least 8 characters"><button class="password-toggle" type="button" aria-controls="signupPassword" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle></svg><svg class="eye-off-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle><path d="m3 3 14 14"></path></svg></button></span></label>
                <label class="form-field"><span>Confirm password</span><span class="password-input-wrap"><input id="signupPasswordConfirmation" name="password_confirmation" type="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="Enter your password again"><button class="password-toggle" type="button" aria-controls="signupPasswordConfirmation" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle></svg><svg class="eye-off-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle><path d="m3 3 14 14"></path></svg></button></span></label>
                <button class="button button-primary auth-submit" type="submit">Create account</button>
                <button class="button button-primary auth-submit auth-back-button" type="button" data-auth-mode="login">Go back</button>
            </form>
            <form class="auth-form hidden" id="recoveryRequestForm" data-auth-mode-form="forgot" data-auth-action="request_reset" hidden>
                <label class="form-field"><span>Send code to</span><select name="channel" id="recoveryChannel"><option value="email">Email</option><option value="phone">Phone number</option></select></label>
                <label class="form-field"><span id="recoveryContactLabel">Email address</span><input name="contact" id="recoveryContact" type="email" required maxlength="254" autocomplete="email" placeholder="you@example.com"></label>
                <button class="button button-primary auth-submit" type="submit">Send reset code</button>
                <button class="button button-primary auth-submit auth-back-button" type="button" data-auth-mode="login">Go back</button>
            </form>
            <form class="auth-form hidden" id="recoveryCompleteForm" data-auth-mode-form="reset" data-auth-action="reset_password" hidden>
                <input type="hidden" name="channel">
                <input type="hidden" name="contact">
                <label class="form-field"><span>8-digit reset code</span><input name="code" type="text" required minlength="8" maxlength="8" pattern="[0-9]{8}" inputmode="numeric" autocomplete="one-time-code" placeholder="Enter the code"></label>
                <label class="form-field"><span>New password</span><span class="password-input-wrap"><input id="resetPassword" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="At least 8 characters"><button class="password-toggle" type="button" aria-controls="resetPassword" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle></svg><svg class="eye-off-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle><path d="m3 3 14 14"></path></svg></button></span></label>
                <label class="form-field"><span>Confirm new password</span><span class="password-input-wrap"><input id="resetPasswordConfirmation" name="password_confirmation" type="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="Enter the new password again"><button class="password-toggle" type="button" aria-controls="resetPasswordConfirmation" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle></svg><svg class="eye-off-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5-7.5 5-7.5-5-7.5-5Z"></path><circle cx="10" cy="10" r="2.2"></circle><path d="m3 3 14 14"></path></svg></button></span></label>
                <button class="button button-primary auth-submit" type="submit">Reset password</button>
                <button class="button button-primary auth-submit auth-back-button" type="button" data-auth-mode="forgot">Go back</button>
            </form>
        <?php else: ?>
            <form class="auth-form" id="contactSetupForm" data-auth-mode-form="contact_setup" data-auth-action="update_contacts">
                <label class="form-field"><span>Email</span><input name="email" type="email" required maxlength="254" autocomplete="email" placeholder="you@example.com"></label>
                <label class="form-field"><span>Phone number</span><input name="phone" type="tel" required maxlength="32" pattern="\+[1-9][0-9]{6,14}" autocomplete="tel" placeholder="+254712345678"></label>
                <button class="button button-primary auth-submit" type="submit">Save recovery contacts</button>
                <button class="button button-primary auth-submit auth-back-button" id="contactSetupBack" type="button">Go back</button>
            </form>
        <?php endif; ?>
        <p class="auth-footnote">Passwords are stored as secure hashes. Recovery codes expire after 10 minutes.</p>
    </section>
</main>
<?php endif; ?>
</body>
</html>
