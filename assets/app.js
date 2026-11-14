(() => {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const screenshotStages = ['before', 'during', 'after'];
    const state = { trades: [], strategies: [], weeklyPlans: [], economicCalendarEvents: [], calendarLoaded: false, calendarLoading: false, calendarError: '', calendarUpdatedAt: null, weeklyPlanScreenshots: [], page: 'dashboard', isNavigating: false, period: 'all', toastTimer: null, screenshots: emptyScreenshots() };
    const byId = (id) => document.getElementById(id);
    const money = (value, currency = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value) || 0);
    const dateTimeValue = (date = new Date()) => {
        const pad = (part) => String(part).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
    const tradeDate = (trade) => trade.exit_date || trade.entry_date;
    const hasPnl = (trade) => trade.pnl !== null && trade.pnl !== undefined && Number.isFinite(Number(trade.pnl));
    const realizedTrades = (trades = state.trades) => trades.filter((trade) => trade.status === 'closed' && hasPnl(trade));
    const outcomeOf = (trade) => {
        if (trade.status !== 'closed') return null;
        if (['win', 'loss', 'breakeven'].includes(trade.outcome)) return trade.outcome;
        if (!hasPnl(trade)) return null;
        return Number(trade.pnl) > 0 ? 'win' : Number(trade.pnl) < 0 ? 'loss' : 'breakeven';
    };
    const classifiedTrades = (trades = state.trades) => trades.filter((trade) => trade.status === 'closed' && outcomeOf(trade) !== null);
    const pnlClass = (value) => Number(value) > 0 ? 'pnl-positive' : Number(value) < 0 ? 'pnl-negative' : 'pnl-neutral';
    const signedMoney = (value) => `${Number(value) > 0 ? '+' : ''}${money(value)}`;
    const displayDate = (value, options = { month: 'short', day: 'numeric', year: 'numeric' }) => {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, options).format(date);
    };
    function emptyScreenshots() {
        return { before: [], during: [], after: [] };
    }

    async function request(url, options = {}) {
        const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
        if (options.method && options.method !== 'GET') headers['X-CSRF-Token'] = csrfToken;
        const response = await fetch(url, { ...options, headers });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status === 401) window.location.replace('index.php');
            throw new Error(body.error || 'Something went wrong. Please try again.');
        }
        return body;
    }

    function showToast(message) {
        const toast = byId('toast');
        toast.textContent = message;
        toast.classList.add('visible');
        clearTimeout(state.toastTimer);
        state.toastTimer = setTimeout(() => toast.classList.remove('visible'), 3200);
    }

    function filteredTrades() {
        const status = byId('statusFilter').value;
        const side = byId('sideFilter').value;
        const query = byId('tradeSearch').value.trim().toLowerCase();
        return state.trades.filter((trade) => {
            const matchesStatus = status === 'all' || trade.status === status;
            const matchesSide = side === 'all' || trade.side === side;
            const searchable = [trade.symbol, trade.setup, trade.notes, trade.market, trade.emotion, trade.outcome].join(' ').toLowerCase();
            return matchesStatus && matchesSide && searchable.includes(query);
        });
    }

    function tradeRow(trade, includeSide = false) {
        const pnl = hasPnl(trade) ? Number(trade.pnl) : null;
        const direction = trade.side === 'long' ? '↗ Long' : '↘ Short';
        const dateRange = `${displayDate(trade.entry_date, { month: 'short', day: 'numeric' })} <span class="muted-arrow">→</span> ${trade.exit_date ? displayDate(trade.exit_date, { month: 'short', day: 'numeric' }) : '—'}`;
        const outcomeLabels = { win: 'Win', loss: 'Loss', breakeven: 'Breakeven' };
        const outcomeLabel = outcomeLabels[outcomeOf(trade)] || '—';
        return `<tr>
            <td><div class="symbol-cell"><span class="symbol-avatar">${escapeHtml(trade.symbol.slice(0, 2))}</span><span class="symbol-meta">${escapeHtml(trade.symbol)}<small>${escapeHtml(trade.market)}</small></span></div></td>
            ${includeSide ? `<td><span class="side-pill ${escapeHtml(trade.side)}">${direction}</span></td>` : ''}
            <td>${escapeHtml(trade.setup || '—')}</td>
            ${includeSide ? `<td>${dateRange}</td>` : `<td>${displayDate(tradeDate(trade))}</td>`}
            <td>${outcomeLabel}</td>
            <td><span class="status-pill ${escapeHtml(trade.status)}">${trade.status === 'open' ? '● Open' : '✓ Closed'}</span></td>
            <td class="align-right ${pnl === null ? 'pnl-neutral' : pnlClass(pnl)}">${pnl === null ? '—' : signedMoney(pnl)}</td>
            <td><div class="row-actions"><button class="row-action edit-trade" data-id="${escapeHtml(trade.id)}" type="button" title="Edit trade" aria-label="Edit ${escapeHtml(trade.symbol)} trade"><svg viewBox="0 0 20 20"><path d="m12.5 4.5 3 3M4 16l3.2-.7L16 6.5 13.5 4 4.7 12.8 4 16Z"/></svg></button><button class="row-action delete delete-trade" data-id="${escapeHtml(trade.id)}" type="button" title="Delete trade" aria-label="Delete ${escapeHtml(trade.symbol)} trade"><svg viewBox="0 0 20 20"><path d="M4.5 6h11M8 6V4h4v2m-6 0 .7 10h6.6L14 6m-4 3v4m2-4v4"/></svg></button></div></td>
        </tr>`;
    }

    function renderRows() {
        const recent = state.trades.slice(0, 5);
        byId('recentTradesBody').innerHTML = recent.map((trade) => tradeRow(trade)).join('');
        byId('recentEmpty').classList.toggle('hidden', state.trades.length > 0);
        const filtered = filteredTrades();
        byId('journalTradesBody').innerHTML = filtered.map((trade) => tradeRow(trade, true)).join('');
        byId('journalTotal').textContent = `${filtered.length} ${filtered.length === 1 ? 'trade' : 'trades'}`;
        byId('journalEmpty').classList.toggle('hidden', state.trades.length > 0);
        byId('filterEmpty').classList.toggle('hidden', state.trades.length === 0 || filtered.length > 0);
        byId('tradeCount').textContent = String(state.trades.length);
        renderAnalytics();
    }

    function inDashboardPeriod(trade) {
        if (state.period === 'all') return true;
        const cutoff = new Date();
        cutoff.setDate(cutoff.getDate() - Number(state.period));
        return new Date(tradeDate(trade)) >= cutoff;
    }

    function startOfWeek(date = new Date()) {
        const start = new Date(date);
        start.setHours(0, 0, 0, 0);
        start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
        return start;
    }

    function tradesInWeek(trades, weekStart) {
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 7);
        return trades.filter((trade) => {
            const date = new Date(tradeDate(trade));
            return !Number.isNaN(date.getTime()) && date >= weekStart && date < weekEnd;
        });
    }

    function scheduleWeeklyWinRateRefresh() {
        const nextWeekStart = startOfWeek();
        nextWeekStart.setDate(nextWeekStart.getDate() + 7);
        setTimeout(() => {
            renderDashboard();
            scheduleWeeklyWinRateRefresh();
        }, nextWeekStart.getTime() - Date.now());
    }

    function renderDashboard() {
        const pnlTrades = realizedTrades().filter(inDashboardPeriod);
        const outcomes = classifiedTrades().filter(inDashboardPeriod);
        const missingPnl = state.trades.filter((trade) => trade.status === 'closed' && !hasPnl(trade) && inDashboardPeriod(trade)).length;
        const wins = outcomes.filter((trade) => outcomeOf(trade) === 'win');
        const losses = outcomes.filter((trade) => outcomeOf(trade) === 'loss');
        const breakeven = outcomes.filter((trade) => outcomeOf(trade) === 'breakeven');
        const thisWeekStart = startOfWeek();
        const lastWeekStart = new Date(thisWeekStart);
        lastWeekStart.setDate(lastWeekStart.getDate() - 7);
        const thisWeekOutcomes = tradesInWeek(classifiedTrades(), thisWeekStart);
        const lastWeekOutcomes = tradesInWeek(classifiedTrades(), lastWeekStart);
        const showingLastWeek = thisWeekOutcomes.length === 0 && lastWeekOutcomes.length > 0;
        const weeklyOutcomes = showingLastWeek ? lastWeekOutcomes : thisWeekOutcomes;
        const weeklyWins = weeklyOutcomes.filter((trade) => outcomeOf(trade) === 'win');
        const winRate = weeklyOutcomes.length ? (weeklyWins.length / weeklyOutcomes.length) * 100 : 0;

        byId('winRate').innerHTML = `${winRate.toFixed(1)}<span class="value-unit">%</span>`;
        byId('winRateLabel').textContent = showingLastWeek ? 'LAST WEEK WIN RATE' : 'THIS WEEK WIN RATE';
        byId('winContext').textContent = `${weeklyWins.length} winning of ${weeklyOutcomes.length} classified`;
        byId('winBar').style.width = `${Math.min(100, winRate)}%`;
        byId('missingPnlCount').textContent = String(missingPnl);
        byId('outcomeTotal').textContent = String(outcomes.length);
        byId('outcomePeriod').textContent = state.period === 'all' ? 'ALL TIME' : `LAST ${state.period} DAYS`;
        byId('winnerCount').textContent = String(wins.length);
        byId('loserCount').textContent = String(losses.length);
        byId('evenCount').textContent = String(breakeven.length);
        byId('outcomeWinBar').style.width = `${outcomes.length ? wins.length / outcomes.length * 100 : 0}%`;
        byId('outcomeLossBar').style.width = `${outcomes.length ? losses.length / outcomes.length * 100 : 0}%`;
        byId('outcomeEvenBar').style.width = `${outcomes.length ? breakeven.length / outcomes.length * 100 : 0}%`;
        const best = pnlTrades.reduce((current, trade) => !current || Number(trade.pnl) > Number(current.pnl) ? trade : current, null);
        byId('bestTrade').textContent = best ? `${best.symbol} · ${best.setup || best.side}` : 'Log a trade to see your best.';
        byId('bestTradePnl').textContent = best ? signedMoney(best.pnl) : '';
    }

    function renderAnalytics() {
        const trades = classifiedTrades();
        const newStats = () => ({ trades: 0, wins: 0, losses: 0, breakeven: 0 });
        const addTrade = (stats, trade) => {
            stats.trades++;
            const outcome = outcomeOf(trade);
            stats[outcome === 'win' ? 'wins' : outcome === 'loss' ? 'losses' : 'breakeven']++;
        };
        const groupBy = (items, getName) => {
            const groups = new Map();
            items.forEach((trade) => {
                const name = getName(trade);
                const stats = groups.get(name) || newStats();
                addTrade(stats, trade);
                groups.set(name, stats);
            });
            return [...groups.entries()].map(([name, stats]) => ({ name, stats }));
        };
        const summarize = (items) => items.reduce((stats, trade) => {
            addTrade(stats, trade);
            return stats;
        }, newStats());
        const table = (rows, emptyMessage) => `
            <div class="analytics-table-wrap">
                <table class="analytics-breakdown-table">
                    <thead><tr><th>GROUP</th><th>TRADES</th><th>W / L / BE</th><th>WIN RATE</th></tr></thead>
                    <tbody>${rows.length ? rows.map(({ name, stats }) => {
                        const rate = stats.trades ? stats.wins / stats.trades * 100 : 0;
                        return `<tr><td>${escapeHtml(name)}</td><td>${stats.trades}</td><td>${stats.wins} / ${stats.losses} / ${stats.breakeven}</td><td><span class="analytics-rate"><span>${rate.toFixed(0)}%</span><i><b style="width:${rate}%"></b></i></span></td></tr>`;
                    }).join('') : `<tr><td class="analytics-no-data" colspan="4">${emptyMessage}</td></tr>`}</tbody>
                </table>
            </div>`;
        const byFrequency = (rows) => rows.sort((a, b) => b.stats.trades - a.stats.trades || a.name.localeCompare(b.name));
        const noTrades = 'Log closed trades with an outcome to see statistics.';
        const withTotal = (rows) => trades.length ? [{ name: 'All trades', stats: summarize(trades) }, ...rows] : rows;

        const monthly = groupBy(trades, (trade) => {
            const date = new Date(tradeDate(trade));
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        }).sort((a, b) => b.name.localeCompare(a.name)).map(({ name, stats }) => {
            const [year, month] = name.split('-').map(Number);
            return { name: new Intl.DateTimeFormat(undefined, { month: 'short', year: 'numeric' }).format(new Date(year, month - 1, 1)), stats };
        });
        byId('monthlyStats').innerHTML = table(withTotal(monthly), noTrades);

        const weekdays = groupBy(trades, (trade) => new Intl.DateTimeFormat(undefined, { weekday: 'long' }).format(new Date(trade.entry_date)));
        const weekdayOrder = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        weekdays.sort((a, b) => weekdayOrder.indexOf(a.name) - weekdayOrder.indexOf(b.name));
        byId('weekdayStats').innerHTML = table(weekdays, noTrades);

        const sessions = groupBy(trades, (trade) => {
            const hour = new Date(trade.entry_date).getHours();
            return hour < 6 ? 'Overnight' : hour < 12 ? 'Morning' : hour < 18 ? 'Afternoon' : 'Evening';
        });
        const sessionOrder = ['Overnight', 'Morning', 'Afternoon', 'Evening'];
        sessions.sort((a, b) => sessionOrder.indexOf(a.name) - sessionOrder.indexOf(b.name));
        byId('sessionStats').innerHTML = table(sessions, noTrades);

        byId('tickerStats').innerHTML = table(byFrequency(groupBy(trades, (trade) => trade.symbol?.trim().toUpperCase() || 'Unspecified')), noTrades);
        byId('planStats').innerHTML = table(groupBy(trades, (trade) => trade.followed_plan === 'yes' ? 'Followed plan' : trade.followed_plan === 'no' ? 'Did not follow plan' : 'Not recorded'), noTrades);
        byId('emotionStats').innerHTML = table(byFrequency(groupBy(trades, (trade) => trade.emotion?.trim() || 'Not recorded')), noTrades);
        byId('setupStats').innerHTML = table(byFrequency(groupBy(trades, (trade) => trade.setup?.trim() || 'Uncategorized')), noTrades);
        byId('marketStats').innerHTML = table(byFrequency(groupBy(trades, (trade) => trade.market?.trim() || 'Unspecified')), noTrades);
        byId('directionStats').innerHTML = table(groupBy(trades, (trade) => trade.side === 'short' ? 'Short' : 'Long'), noTrades);
    }

    function calendarLocalDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${date.getFullYear()}-${month}-${day}`;
    }

    function renderEconomicCalendar() {
        const selectedImpact = byId('economicCalendarImpactFilter').value;
        const selectedDate = byId('economicCalendarDateFilter').value;
        const impactCategory = (value) => {
            const impact = String(value || '').toLowerCase();
            return ['high', 'medium', 'low', 'holiday'].includes(impact) ? impact : 'other';
        };
        const events = state.economicCalendarEvents
            .filter((event) => (!selectedDate || calendarLocalDate(event.date) === selectedDate)
                && (selectedImpact === 'all' || impactCategory(event.impact) === selectedImpact))
            .sort((left, right) => new Date(left.date) - new Date(right.date));
        const rows = events.map((event) => {
            const date = new Date(event.date);
            const time = Number.isNaN(date.getTime())
                ? '—'
                : new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(date);
            const impact = String(event.impact || 'Other');
            const impactClass = impactCategory(impact);
            return `<tr>
                <td><span class="calendar-date">${escapeHtml(displayDate(event.date, { weekday: 'short', month: 'short', day: 'numeric' }))}</span><small>${escapeHtml(time)}</small></td>
                <td><span class="calendar-currency">${escapeHtml(event.currency || '—')}</span></td>
                <td class="calendar-event-title">${escapeHtml(event.title || '—')}</td>
                <td><span class="calendar-impact ${impactClass}">${escapeHtml(impact)}</span></td>
                <td>${escapeHtml(event.actual || '—')}</td>
                <td>${escapeHtml(event.forecast || '—')}</td>
                <td>${escapeHtml(event.previous || '—')}</td>
            </tr>`;
        }).join('');

        byId('economicCalendarEventsBody').innerHTML = rows;
        byId('economicCalendarResultCount').textContent = `${events.length} ${events.length === 1 ? 'event' : 'events'}`;
        byId('economicCalendarUpdated').textContent = state.calendarUpdatedAt
            ? `Updated ${new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(state.calendarUpdatedAt)}`
            : 'Not loaded yet';
        byId('economicCalendarTableWrap').classList.toggle('hidden', events.length === 0);
        byId('economicCalendarNoMatches').classList.toggle('hidden', !state.calendarLoaded || state.economicCalendarEvents.length === 0 || events.length > 0);
        byId('economicCalendarEmpty').classList.toggle('hidden', !state.calendarLoaded || state.economicCalendarEvents.length > 0);
        byId('economicCalendarLoading').classList.toggle('hidden', !state.calendarLoading);
        byId('clearEconomicCalendarDate').classList.toggle('hidden', !selectedDate);
        byId('economicCalendarError').textContent = state.calendarError
            ? `The economic calendar could not be loaded: ${state.calendarError}`
            : '';
        byId('economicCalendarError').classList.toggle('hidden', !state.calendarError);
    }

    async function loadEconomicCalendar(forceRefresh = false) {
        if (state.calendarLoading || (state.calendarLoaded && !forceRefresh)) return;
        state.calendarLoading = true;
        state.calendarError = '';
        byId('refreshEconomicCalendar').disabled = true;
        renderEconomicCalendar();
        try {
            const result = await request('api.php?resource=economic-calendar');
            if (!Array.isArray(result.events)) {
                throw new Error('The calendar provider returned an unexpected response.');
            }
            state.economicCalendarEvents = result.events;
            state.calendarLoaded = true;
            state.calendarUpdatedAt = new Date();
        } catch (error) {
            state.calendarError = error.message;
        } finally {
            state.calendarLoading = false;
            byId('refreshEconomicCalendar').disabled = false;
            renderEconomicCalendar();
        }
    }

    function renderStrategyList() {
        byId('strategyCount').textContent = `${state.strategies.length} ${state.strategies.length === 1 ? 'strategy' : 'strategies'}`;
        if (!state.strategies.length) {
            byId('strategyList').innerHTML = '<section class="panel strategy-empty"><h3>No strategies saved yet.</h3><p>Add your entry and risk rules above to start building your playbook.</p></section>';
            return;
        }

        const formatStrategyText = (value) => escapeHtml(value || 'Not added yet.').replace(/\n/g, '<br>');
        byId('strategyList').innerHTML = state.strategies.map((strategy) => `
            <article class="panel strategy-card">
                <div class="strategy-card-heading">
                    <div><span class="strategy-market">${escapeHtml(strategy.market || 'Any market')}</span><h3>${escapeHtml(strategy.name)}</h3></div>
                    <div class="strategy-actions">
                        <button class="button button-secondary edit-strategy" data-id="${escapeHtml(strategy.id)}" type="button">Edit</button>
                        <button class="button strategy-delete delete-strategy" data-id="${escapeHtml(strategy.id)}" type="button" aria-label="Delete ${escapeHtml(strategy.name)}">Delete</button>
                    </div>
                </div>
                <div class="strategy-details">
                    <section><h4>ENTRY RULES</h4><p>${formatStrategyText(strategy.entry_rules)}</p></section>
                    <section><h4>EXIT RULES</h4><p>${formatStrategyText(strategy.exit_rules)}</p></section>
                    <section><h4>RISK MANAGEMENT</h4><p>${formatStrategyText(strategy.risk_management)}</p></section>
                    <section><h4>NOTES</h4><p>${formatStrategyText(strategy.notes)}</p></section>
                </div>
            </article>`).join('');
    }

    function resetStrategyForm() {
        byId('strategyForm').reset();
        byId('strategyId').value = '';
        byId('strategyFormTitle').textContent = 'Add a strategy';
        byId('saveStrategy').textContent = 'Save strategy';
        byId('cancelStrategyEdit').classList.add('hidden');
        byId('strategyFormError').classList.add('hidden');
    }

    function editStrategy(id) {
        const strategy = state.strategies.find((item) => item.id === id);
        if (!strategy) {
            showToast('That strategy could not be found.');
            return;
        }
        byId('strategyId').value = strategy.id;
        byId('strategyName').value = strategy.name;
        byId('strategyMarket').value = strategy.market;
        byId('strategyEntryRules').value = strategy.entry_rules;
        byId('strategyExitRules').value = strategy.exit_rules;
        byId('strategyRiskManagement').value = strategy.risk_management;
        byId('strategyNotes').value = strategy.notes;
        byId('strategyFormTitle').textContent = 'Edit strategy';
        byId('saveStrategy').textContent = 'Update strategy';
        byId('cancelStrategyEdit').classList.remove('hidden');
        byId('strategyFormError').classList.add('hidden');
        byId('strategyName').focus();
    }

    async function saveStrategy(event) {
        event.preventDefault();
        const form = byId('strategyForm');
        if (!form.reportValidity()) return;
        const button = byId('saveStrategy');
        const payload = Object.fromEntries(new FormData(form).entries());
        const editing = Boolean(payload.id);
        button.disabled = true;
        button.textContent = editing ? 'Updating…' : 'Saving…';
        byId('strategyFormError').classList.add('hidden');
        try {
            const result = await request('api.php?resource=strategies', {
                method: editing ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            if (editing) state.strategies = state.strategies.map((strategy) => strategy.id === result.strategy.id ? result.strategy : strategy);
            else state.strategies.unshift(result.strategy);
            state.strategies.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
            renderStrategyList();
            resetStrategyForm();
            showToast(editing ? 'Strategy updated.' : 'Strategy saved.');
        } catch (error) {
            byId('strategyFormError').textContent = error.message;
            byId('strategyFormError').classList.remove('hidden');
        } finally {
            button.disabled = false;
            button.textContent = byId('strategyId').value ? 'Update strategy' : 'Save strategy';
        }
    }

    async function deleteStrategy(id) {
        const strategy = state.strategies.find((item) => item.id === id);
        if (!strategy || !window.confirm(`Delete the ${strategy.name} strategy? This cannot be undone.`)) return;
        try {
            await request('api.php?resource=strategies', { method: 'DELETE', body: JSON.stringify({ id }) });
            state.strategies = state.strategies.filter((item) => item.id !== id);
            if (byId('strategyId').value === id) resetStrategyForm();
            renderStrategyList();
            showToast('Strategy deleted.');
        } catch (error) {
            showToast(error.message);
        }
    }

    function weeklyPlanWeekLabel(value) {
        const [year, month, day] = value.split('-').map(Number);
        const start = new Date(year, month - 1, day);
        const end = new Date(start);
        end.setDate(end.getDate() + 6);
        return `${displayDate(start)} – ${displayDate(end)}`;
    }

    function renderWeeklyPlans() {
        byId('weeklyPlanCount').textContent = `${state.weeklyPlans.length} ${state.weeklyPlans.length === 1 ? 'plan' : 'plans'}`;
        if (!state.weeklyPlans.length) {
            byId('weeklyPlanList').innerHTML = '<section class="panel weekly-plan-empty"><h3>No weekly levels saved yet.</h3><p>Add a pair, week, and key levels to build your weekly market plan.</p></section>';
            return;
        }

        const formatWeeklyText = (value, emptyText) => escapeHtml(value || emptyText).replace(/\r?\n/g, '<br>');
        byId('weeklyPlanList').innerHTML = state.weeklyPlans.map((plan) => `
            <article class="panel weekly-plan-card">
                <div class="weekly-plan-card-heading">
                    <div><h3>${escapeHtml(plan.pair)}</h3><span>${escapeHtml(weeklyPlanWeekLabel(plan.week_start))}</span></div>
                    <div class="strategy-actions">
                        <button class="button button-secondary edit-weekly-plan" data-id="${escapeHtml(plan.id)}" type="button">Edit</button>
                        <button class="button strategy-delete delete-weekly-plan" data-id="${escapeHtml(plan.id)}" type="button" aria-label="Delete ${escapeHtml(plan.pair)} weekly plan">Delete</button>
                    </div>
                </div>
                <div class="weekly-plan-details">
                    <section>
                        <h4>KEY LEVELS TO WATCH</h4>
                        <p>${formatWeeklyText(plan.key_levels, 'Not added yet.')}</p>
                        ${plan.screenshots?.length ? `<div class="screenshot-previews weekly-plan-screenshots">${plan.screenshots.map((screenshot, index) => `
                            <div class="screenshot-preview">
                                <button class="screenshot-image-button weekly-plan-screenshot-button" type="button" data-plan-id="${escapeHtml(plan.id)}" data-screenshot-index="${index}" aria-label="View weekly plan screenshot: ${escapeHtml(screenshot.name)}"><img src="${escapeHtml(screenshot.data)}" alt=""></button>
                                <div class="screenshot-preview-footer"><span title="${escapeHtml(screenshot.name)}">${escapeHtml(screenshot.name)}</span></div>
                            </div>`).join('')}</div>` : ''}
                    </section>
                    <section><h4>WEEKLY OUTCOME / REVIEW</h4><p>${formatWeeklyText(plan.weekly_outcome, 'Not recorded yet.')}</p></section>
                </div>
            </article>`).join('');
    }

    function renderWeeklyPlanScreenshots() {
        byId('weeklyPlanScreenshotPreviews').innerHTML = state.weeklyPlanScreenshots.map((screenshot, index) => `
            <div class="screenshot-preview">
                <button class="screenshot-image-button" type="button" data-stage="weekly" data-screenshot-index="${index}" aria-label="View weekly plan screenshot: ${escapeHtml(screenshot.name)}"><img src="${escapeHtml(screenshot.data)}" alt=""></button>
                <div class="screenshot-preview-footer"><span title="${escapeHtml(screenshot.name)}">${escapeHtml(screenshot.name)}</span><button type="button" class="remove-weekly-screenshot" data-screenshot-index="${index}" aria-label="Remove weekly plan screenshot ${escapeHtml(screenshot.name)}">×</button></div>
            </div>`).join('');
    }

    function resetWeeklyPlanForm() {
        byId('weeklyPlanForm').reset();
        byId('weeklyPlanId').value = '';
        byId('weeklyPlanWeek').value = dateTimeValue().slice(0, 10);
        byId('weeklyPlanScreenshotInput').value = '';
        state.weeklyPlanScreenshots = [];
        renderWeeklyPlanScreenshots();
        byId('weeklyPlanFormTitle').textContent = 'Add weekly levels';
        byId('saveWeeklyPlan').textContent = 'Save weekly plan';
        byId('cancelWeeklyPlanEdit').classList.add('hidden');
        byId('weeklyPlanFormError').classList.add('hidden');
    }

    function editWeeklyPlan(id) {
        const plan = state.weeklyPlans.find((item) => item.id === id);
        if (!plan) {
            showToast('That weekly plan could not be found.');
            return;
        }
        byId('weeklyPlanId').value = plan.id;
        byId('weeklyPlanPair').value = plan.pair;
        byId('weeklyPlanWeek').value = plan.week_start;
        byId('weeklyPlanLevels').value = plan.key_levels;
        byId('weeklyPlanOutcome').value = plan.weekly_outcome;
        byId('weeklyPlanScreenshotInput').value = '';
        state.weeklyPlanScreenshots = (plan.screenshots || []).map((screenshot) => ({ ...screenshot }));
        renderWeeklyPlanScreenshots();
        byId('weeklyPlanFormTitle').textContent = 'Edit weekly levels';
        byId('saveWeeklyPlan').textContent = 'Update weekly plan';
        byId('cancelWeeklyPlanEdit').classList.remove('hidden');
        byId('weeklyPlanFormError').classList.add('hidden');
        byId('weeklyPlanPair').focus();
    }

    async function saveWeeklyPlan(event) {
        event.preventDefault();
        const form = byId('weeklyPlanForm');
        if (!form.reportValidity()) return;
        const button = byId('saveWeeklyPlan');
        const payload = { ...Object.fromEntries(new FormData(form).entries()), screenshots: state.weeklyPlanScreenshots };
        const editing = Boolean(payload.id);
        button.disabled = true;
        button.textContent = editing ? 'Updating…' : 'Saving…';
        byId('weeklyPlanFormError').classList.add('hidden');
        try {
            const result = await request('api.php?resource=weekly-analysis', {
                method: editing ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            if (editing) state.weeklyPlans = state.weeklyPlans.map((plan) => plan.id === result.weeklyPlan.id ? result.weeklyPlan : plan);
            else state.weeklyPlans.unshift(result.weeklyPlan);
            state.weeklyPlans.sort((a, b) => b.week_start.localeCompare(a.week_start) || a.pair.localeCompare(b.pair));
            renderWeeklyPlans();
            resetWeeklyPlanForm();
            showToast(editing ? 'Weekly plan updated.' : 'Weekly plan saved.');
        } catch (error) {
            byId('weeklyPlanFormError').textContent = error.message;
            byId('weeklyPlanFormError').classList.remove('hidden');
        } finally {
            button.disabled = false;
            button.textContent = byId('weeklyPlanId').value ? 'Update weekly plan' : 'Save weekly plan';
        }
    }

    async function deleteWeeklyPlan(id) {
        const plan = state.weeklyPlans.find((item) => item.id === id);
        if (!plan || !window.confirm(`Delete the ${plan.pair} weekly plan for ${weeklyPlanWeekLabel(plan.week_start)}? This cannot be undone.`)) return;
        try {
            await request('api.php?resource=weekly-analysis', { method: 'DELETE', body: JSON.stringify({ id }) });
            state.weeklyPlans = state.weeklyPlans.filter((item) => item.id !== id);
            if (byId('weeklyPlanId').value === id) resetWeeklyPlanForm();
            renderWeeklyPlans();
            showToast('Weekly plan deleted.');
        } catch (error) {
            showToast(error.message);
        }
    }

    function navigate(page) {
        const nextPage = byId(`${page}Page`);
        if (!nextPage) return;
        byId('sidebar').classList.remove('open');
        if (page === state.page || state.isNavigating) return;

        state.isNavigating = true;
        window.PipTrackPageTransition.start().then(() => {
            state.page = page;
            document.querySelectorAll('.page-section').forEach((section) => section.classList.add('hidden'));
            nextPage.classList.remove('hidden');
            document.querySelectorAll('.nav-item[data-page]').forEach((item) => item.classList.toggle('active', item.dataset.page === page));
            const pageLabels = { dashboard: 'Dashboard', journal: 'Trade journal', strategy: 'Strategy', weeklyAnalysis: 'Weekly analysis', economicCalendar: 'Economic calendar', analytics: 'Analytics' };
            byId('breadcrumbCurrent').textContent = pageLabels[page] || page;
            state.isNavigating = false;
            if (page === 'economicCalendar') loadEconomicCalendar();
        });
    }

    function resetForm(trade = null) {
        byId('tradeForm').reset();
        const savedScreenshots = trade?.screenshots || {};
        state.screenshots = Array.isArray(savedScreenshots)
            ? { ...emptyScreenshots(), before: savedScreenshots.map((screenshot) => ({ ...screenshot })) }
            : Object.fromEntries(screenshotStages.map((stage) => [stage, (savedScreenshots[stage] || []).map((screenshot) => ({ ...screenshot }))]));
        screenshotStages.forEach((stage) => { byId(`screenshotInput${stage[0].toUpperCase()}${stage.slice(1)}`).value = ''; });
        renderScreenshots();
        byId('tradeId').value = trade?.id || '';
        byId('modalTitle').textContent = trade ? 'Edit trade' : 'Log a trade';
        byId('symbol').value = trade?.symbol || '';
        byId('market').value = trade?.market || 'Stocks';
        document.querySelector(`input[name="side"][value="${trade?.side || 'long'}"]`).checked = true;
        byId('status').value = trade?.status || 'closed';
        byId('outcome').value = trade ? outcomeOf(trade) || '' : '';
        byId('entryDate').value = trade?.entry_date ? dateTimeValue(new Date(trade.entry_date)) : dateTimeValue();
        byId('exitDate').value = trade?.exit_date ? dateTimeValue(new Date(trade.exit_date)) : dateTimeValue();
        byId('emotion').value = trade?.emotion || '';
        byId('followedPlan').value = trade?.followed_plan || '';
        byId('notes').value = trade?.notes || '';
        byId('formError').classList.add('hidden');
        updateStatusFields();
    }

    function updateStatusFields() {
        const closed = byId('status').value === 'closed';
        byId('exitDate').required = closed;
        byId('exitDateField').classList.toggle('hidden', !closed);
        byId('outcome').required = closed;
        byId('outcomeField').classList.toggle('hidden', !closed);
        if (!closed) {
            byId('exitDate').value = '';
            byId('outcome').value = '';
        }
    }

    function openModal(trade = null) {
        resetForm(trade);
        byId('tradeModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => byId('symbol').focus(), 30);
    }

    function closeModal() {
        byId('tradeModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function formPayload() {
        const formData = new FormData(byId('tradeForm'));
        return { ...Object.fromEntries(formData.entries()), screenshots: state.screenshots };
    }

    function renderScreenshots() {
        screenshotStages.forEach((stage) => {
            byId(`screenshotPreviews${stage[0].toUpperCase()}${stage.slice(1)}`).innerHTML = state.screenshots[stage].map((screenshot, index) => `
                <div class="screenshot-preview">
                    <button class="screenshot-image-button" type="button" data-stage="${stage}" data-screenshot-index="${index}" aria-label="View ${stage}-trade screenshot: ${escapeHtml(screenshot.name)}"><img src="${escapeHtml(screenshot.data)}" alt=""></button>
                    <div class="screenshot-preview-footer"><span title="${escapeHtml(screenshot.name)}">${escapeHtml(screenshot.name)}</span><button type="button" class="remove-screenshot" data-stage="${stage}" data-screenshot-index="${index}" aria-label="Remove ${stage}-trade screenshot ${escapeHtml(screenshot.name)}">×</button></div>
                </div>`).join('');
        });
    }

    function showScreenshot(screenshot, context) {
        if (!screenshot) return;
        byId('lightboxImage').src = screenshot.data;
        byId('lightboxImage').alt = `${context} screenshot: ${screenshot.name}`;
        byId('lightboxCaption').textContent = `${context} · ${screenshot.name}`;
        byId('screenshotLightbox').classList.remove('hidden');
        byId('closeScreenshotLightbox').focus();
    }

    function openScreenshot(stage, index) {
        const screenshot = stage === 'weekly'
            ? state.weeklyPlanScreenshots[index]
            : state.screenshots[stage]?.[index];
        const context = stage === 'weekly' ? 'Weekly plan' : `${stage[0].toUpperCase() + stage.slice(1)} the trade`;
        showScreenshot(screenshot, context);
    }

    function openSavedWeeklyPlanScreenshot(planId, index) {
        const plan = state.weeklyPlans.find((item) => item.id === planId);
        showScreenshot(plan?.screenshots?.[index], 'Weekly plan');
    }

    function closeScreenshot() {
        byId('screenshotLightbox').classList.add('hidden');
        byId('lightboxImage').src = '';
        byId('lightboxImage').alt = '';
        byId('lightboxCaption').textContent = '';
        if (byId('tradeModal').classList.contains('hidden')) {
            document.body.style.overflow = '';
        }
    }

    function readImageFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve({ name: file.name, data: reader.result });
            reader.onerror = () => reject(new Error(`Could not read ${file.name}.`));
            reader.readAsDataURL(file);
        });
    }

    async function addScreenshots(event) {
        const files = [...event.target.files];
        event.target.value = '';
        const stage = event.target.dataset.stage;
        if (!screenshotStages.includes(stage)) return;
        const input = event.target;
        const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
        const available = 4 - state.screenshots[stage].length;
        if (files.length > available) {
            byId('formError').textContent = `You can add up to 4 screenshots ${stage === 'before' ? 'before' : stage === 'during' ? 'during' : 'after'} the trade. ${available} slot${available === 1 ? '' : 's'} remaining.`;
            byId('formError').classList.remove('hidden');
            return;
        }
        const invalid = files.find((file) => !allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024);
        if (invalid) {
            byId('formError').textContent = !allowedTypes.includes(invalid.type)
                ? 'Choose PNG, JPG, or WebP images only.'
                : `${invalid.name} is larger than 2 MB.`;
            byId('formError').classList.remove('hidden');
            return;
        }

        input.disabled = true;
        try {
            const screenshots = await Promise.all(files.map(readImageFile));
            state.screenshots[stage].push(...screenshots);
            renderScreenshots();
            byId('formError').classList.add('hidden');
        } catch (error) {
            byId('formError').textContent = error.message;
            byId('formError').classList.remove('hidden');
        } finally {
            input.disabled = false;
        }
    }

    async function addWeeklyPlanScreenshots(event) {
        const input = event.target;
        const files = [...input.files];
        input.value = '';
        const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
        const available = 4 - state.weeklyPlanScreenshots.length;
        if (files.length > available) {
            byId('weeklyPlanFormError').textContent = `You can add up to 4 screenshots to this weekly plan. ${available} slot${available === 1 ? '' : 's'} remaining.`;
            byId('weeklyPlanFormError').classList.remove('hidden');
            return;
        }
        const invalid = files.find((file) => !allowedTypes.includes(file.type) || file.size > 2 * 1024 * 1024);
        if (invalid) {
            byId('weeklyPlanFormError').textContent = !allowedTypes.includes(invalid.type)
                ? 'Choose PNG, JPG, or WebP images only.'
                : `${invalid.name} is larger than 2 MB.`;
            byId('weeklyPlanFormError').classList.remove('hidden');
            return;
        }

        input.disabled = true;
        try {
            const screenshots = await Promise.all(files.map(readImageFile));
            state.weeklyPlanScreenshots.push(...screenshots);
            renderWeeklyPlanScreenshots();
            byId('weeklyPlanFormError').classList.add('hidden');
        } catch (error) {
            byId('weeklyPlanFormError').textContent = error.message;
            byId('weeklyPlanFormError').classList.remove('hidden');
        } finally {
            input.disabled = false;
        }
    }

    async function saveTrade(event) {
        event.preventDefault();
        const form = byId('tradeForm');
        if (!form.reportValidity()) return;
        const button = byId('saveTrade');
        button.disabled = true;
        button.textContent = 'Saving…';
        try {
            const payload = formPayload();
            const editing = Boolean(payload.id);
            const result = await request('api.php', {
                method: editing ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            if (editing) state.trades = state.trades.map((trade) => trade.id === result.trade.id ? result.trade : trade);
            else state.trades.unshift(result.trade);
            state.trades.sort((a, b) => new Date(b.entry_date) - new Date(a.entry_date));
            closeModal();
            renderAll();
            showToast(editing ? 'Trade updated.' : 'Trade saved. Nice work showing up.');
        } catch (error) {
            byId('formError').textContent = error.message;
            byId('formError').classList.remove('hidden');
        } finally {
            button.disabled = false;
            button.innerHTML = 'Save trade <span>→</span>';
        }
    }

    async function deleteTrade(id) {
        const trade = state.trades.find((item) => item.id === id);
        if (!trade || !window.confirm(`Delete the ${trade.symbol} trade? This cannot be undone.`)) return;
        try {
            await request('api.php', { method: 'DELETE', body: JSON.stringify({ id }) });
            state.trades = state.trades.filter((item) => item.id !== id);
            renderAll();
            showToast('Trade deleted.');
        } catch (error) {
            showToast(error.message);
        }
    }

    function exportCsv() {
        if (!state.trades.length) return showToast('Log a trade before exporting your journal.');
        const columns = ['symbol', 'market', 'side', 'status', 'setup', 'entry_date', 'exit_date', 'outcome', 'pnl', 'emotion', 'followed_plan', 'notes'];
        const textColumns = new Set(['symbol', 'market', 'side', 'status', 'setup', 'entry_date', 'exit_date', 'outcome', 'emotion', 'followed_plan', 'notes']);
        const csvEscape = (value, protectFormula) => {
            let text = String(value ?? '');
            if (protectFormula && /^[\t\r\n ]*[=+\-@]/.test(text)) text = `'${text}`;
            return `"${text.replace(/"/g, '""')}"`;
        };
        const csv = [columns.join(','), ...state.trades.map((trade) => columns.map((column) => csvEscape(trade[column], textColumns.has(column))).join(','))].join('\r\n');
        const link = document.createElement('a');
        const downloadUrl = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        link.href = downloadUrl;
        link.download = `piptrack-trades-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
        showToast('Your trade journal has been exported.');
    }

    function renderAll() {
        renderRows();
        renderDashboard();
    }

    async function init() {
        byId('todayLabel').textContent = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(new Date());
        byId('weeklyPlanWeek').value = dateTimeValue().slice(0, 10);
        document.querySelectorAll('[data-page]').forEach((button) => button.addEventListener('click', () => navigate(button.dataset.page)));
        document.querySelectorAll('[data-open-trade]').forEach((button) => button.addEventListener('click', () => openModal()));
        byId('addTradeButton').addEventListener('click', () => openModal());
        byId('closeModal').addEventListener('click', closeModal);
        byId('cancelModal').addEventListener('click', closeModal);
        byId('tradeModal').addEventListener('click', (event) => { if (event.target === byId('tradeModal')) closeModal(); });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (!byId('screenshotLightbox').classList.contains('hidden')) closeScreenshot();
            else if (!byId('tradeModal').classList.contains('hidden')) closeModal();
        });
        byId('status').addEventListener('change', updateStatusFields);
        byId('tradeForm').addEventListener('submit', saveTrade);
        byId('strategyForm').addEventListener('submit', saveStrategy);
        byId('cancelStrategyEdit').addEventListener('click', resetStrategyForm);
        byId('weeklyPlanForm').addEventListener('submit', saveWeeklyPlan);
        byId('cancelWeeklyPlanEdit').addEventListener('click', resetWeeklyPlanForm);
        byId('weeklyPlanScreenshotInput').addEventListener('change', addWeeklyPlanScreenshots);
        byId('economicCalendarImpactFilter').addEventListener('change', renderEconomicCalendar);
        byId('economicCalendarDateFilter').addEventListener('change', renderEconomicCalendar);
        byId('clearEconomicCalendarDate').addEventListener('click', () => {
            byId('economicCalendarDateFilter').value = '';
            renderEconomicCalendar();
        });
        byId('refreshEconomicCalendar').addEventListener('click', () => loadEconomicCalendar(true));
        screenshotStages.forEach((stage) => {
            byId(`screenshotInput${stage[0].toUpperCase()}${stage.slice(1)}`).addEventListener('change', addScreenshots);
        });
        byId('tradeForm').addEventListener('click', (event) => {
            const imageButton = event.target.closest('.screenshot-image-button');
            if (imageButton) {
                openScreenshot(imageButton.dataset.stage, Number(imageButton.dataset.screenshotIndex));
                return;
            }
            const button = event.target.closest('.remove-screenshot');
            if (!button) return;
            state.screenshots[button.dataset.stage].splice(Number(button.dataset.screenshotIndex), 1);
            renderScreenshots();
        });
        byId('weeklyPlanForm').addEventListener('click', (event) => {
            const imageButton = event.target.closest('.screenshot-image-button');
            if (imageButton) {
                openScreenshot('weekly', Number(imageButton.dataset.screenshotIndex));
                return;
            }
            const button = event.target.closest('.remove-weekly-screenshot');
            if (!button) return;
            state.weeklyPlanScreenshots.splice(Number(button.dataset.screenshotIndex), 1);
            renderWeeklyPlanScreenshots();
        });
        byId('closeScreenshotLightbox').addEventListener('click', closeScreenshot);
        byId('screenshotLightbox').addEventListener('click', (event) => {
            if (event.target === byId('screenshotLightbox')) closeScreenshot();
        });
        byId('tradeSearch').addEventListener('input', renderRows);
        byId('statusFilter').addEventListener('change', renderRows);
        byId('sideFilter').addEventListener('change', renderRows);
        byId('exportButton').addEventListener('click', exportCsv);
        byId('logoutButton').addEventListener('click', async () => {
            const button = byId('logoutButton');
            button.disabled = true;
            try {
                await request('api.php?resource=auth', { method: 'DELETE', body: JSON.stringify({}) });
                window.location.replace('index.php');
            } catch (error) {
                showToast(error.message);
                button.disabled = false;
            }
        });
        byId('mobileMenu').addEventListener('click', () => byId('sidebar').classList.toggle('open'));
        document.querySelectorAll('.period-button').forEach((button) => button.addEventListener('click', () => {
            state.period = button.dataset.period;
            document.querySelectorAll('.period-button').forEach((item) => item.classList.toggle('active', item === button));
            renderDashboard();
        }));
        document.addEventListener('click', (event) => {
            const editButton = event.target.closest('.edit-trade');
            const deleteButton = event.target.closest('.delete-trade');
            const editStrategyButton = event.target.closest('.edit-strategy');
            const deleteStrategyButton = event.target.closest('.delete-strategy');
            const editWeeklyPlanButton = event.target.closest('.edit-weekly-plan');
            const deleteWeeklyPlanButton = event.target.closest('.delete-weekly-plan');
            const savedWeeklyScreenshotButton = event.target.closest('.weekly-plan-screenshot-button');
            if (editButton) openModal(state.trades.find((trade) => trade.id === editButton.dataset.id));
            if (deleteButton) deleteTrade(deleteButton.dataset.id);
            if (editStrategyButton) editStrategy(editStrategyButton.dataset.id);
            if (deleteStrategyButton) deleteStrategy(deleteStrategyButton.dataset.id);
            if (editWeeklyPlanButton) editWeeklyPlan(editWeeklyPlanButton.dataset.id);
            if (deleteWeeklyPlanButton) deleteWeeklyPlan(deleteWeeklyPlanButton.dataset.id);
            if (savedWeeklyScreenshotButton) openSavedWeeklyPlanScreenshot(savedWeeklyScreenshotButton.dataset.planId, Number(savedWeeklyScreenshotButton.dataset.screenshotIndex));
        });

        try {
            const result = await request('api.php');
            state.trades = result.trades;
            renderAll();
        } catch (error) {
            showToast(error.message);
            byId('recentEmpty').classList.remove('hidden');
            byId('recentEmpty').querySelector('p').textContent = 'The journal could not load your data. Please check the server and refresh.';
        }
        scheduleWeeklyWinRateRefresh();

        try {
            const result = await request('api.php?resource=strategies');
            state.strategies = result.strategies;
            renderStrategyList();
        } catch (error) {
            showToast(`Strategies could not load: ${error.message}`);
            byId('strategyList').innerHTML = `<section class="panel strategy-empty" role="alert"><h3>Strategies could not load.</h3><p>${escapeHtml(error.message)}</p></section>`;
        }

        try {
            const result = await request('api.php?resource=weekly-analysis');
            state.weeklyPlans = result.weeklyPlans;
            renderWeeklyPlans();
        } catch (error) {
            showToast(`Weekly plans could not load: ${error.message}`);
            byId('weeklyPlanList').innerHTML = `<section class="panel weekly-plan-empty" role="alert"><h3>Weekly plans could not load.</h3><p>${escapeHtml(error.message)}</p></section>`;
        }
    }

    init();
})();
