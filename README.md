# PipTrack — Trading journal

A lightweight, password-protected multi-user trading journal built with PHP and vanilla JavaScript. It has no package manager or database dependency.

## Run with XAMPP

1. Put the project folder in `C:\xampp\htdocs`.
2. Start Apache in the XAMPP Control Panel.
3. Open `http://localhost/Trading%20journal/` (or the matching folder URL) in your browser.

Alternatively, run PHP's development server from the project folder:

```powershell
php -S 127.0.0.1:8080
```

Then open `http://127.0.0.1:8080/`.

The PHP process must be able to create and write to its data directory. By default, data is stored in a uniquely named `.ledger-*` directory beside the active document root, outside that document root. Set `LEDGER_DATA_DIR` to use a different persistent directory. If the application is run outside a web root, the default is `storage/`; Apache access to that fallback directory is denied by its `.htaccess` file.

Users can create an account with a username, email address, phone number, and password. Password hashes and recovery-code hashes are stored in `users.json` inside the protected data directory. Each new user has separate trade, strategy, and weekly-plan files. Existing single-user accounts and journal data remain available; on their first sign-in, existing accounts are asked to add recovery contacts.

Select **Remember me for 30 days** on sign-in to keep the session active on that device; the password itself is never stored by the application. Use **Sign out** in the sidebar to end the session. Recovery codes expire after 10 minutes and can be requested once per minute for each account.

### Configure email and SMS recovery

Password recovery requires server-side provider configuration. Do not put provider credentials in project files or send them in chat.

- Password recovery needs the `curl` and `openssl` PHP extensions. The live economic calendar also needs `curl` and outbound HTTPS access from the PHP server. Restart the PHP server after changing its `php.ini`.
- If PHP cannot verify the calendar feed's TLS certificate, configure `curl.cainfo` with a trusted CA bundle. XAMPP installations can use `apache/bin/curl-ca-bundle.crt`.
- Gmail SMTP uses `LEDGER_SMTP_USERNAME`, `LEDGER_SMTP_PASSWORD` (a Google App Password), and `LEDGER_SMTP_FROM_EMAIL`. `LEDGER_SMTP_HOST` defaults to `smtp.gmail.com`; `LEDGER_SMTP_PORT` defaults to `587` (or use `465`).
- Africa's Talking SMS uses `LEDGER_AT_USERNAME` and `LEDGER_AT_API_KEY`. `LEDGER_AT_SENDER_ID` is optional and depends on sender-ID approval.

Set these as environment variables for the PHP/Apache process and restart it before testing recovery. If a provider is not configured, its recovery option reports that setup is needed.
On Windows, add the variables in **Environment Variables**, then fully restart VS Code and the PHP server so the server process can read them. Do not put provider keys in project files or commit them.

## Journal features

- Sign up for an isolated account, sign in and out, and reset a password using a code delivered by email or SMS.
- Create, edit, search, filter, and delete open or closed trades.
- Attach up to four PNG, JPG, or WebP screenshots before, during, and after each trade (up to 2 MB each per image); preview and remove screenshots before saving. Screenshots are saved by trade stage and reappear when editing the trade.
- Record market, symbol, direction, entry and exit dates, emotion, plan adherence, notes, and screenshots.
- Build a separate strategy library with market, entry rules, exit rules, risk management, and notes; strategies can be edited or deleted independently of trades.
- Create a separate weekly market plan for each pair and week, record expected key levels, attach up to four PNG, JPG, or WebP chart screenshots (2 MB each), and add an outcome review later.
- View this week's Forex Factory economic-calendar events, filter by impact or a specific date, and see event times in the browser's local timezone.
- Switch between light and dark themes from the moon/sun button; the selected theme is remembered across visits.
- Classify closed trades as wins, losses, or breakeven; open trades remain unclassified.
- Trades do not record entry or exit prices, so new trades have no P&L and are excluded from P&L analytics.
- Review closed-trade outcomes by month, weekday, entry session, symbol, setup, plan adherence, emotion, market, and direction.
- Filter dashboard metrics by all time, 30 days, or 7 days; export the full journal as CSV.

The journal is designed for local use. Its login is not intended as a substitute for production access controls; do not expose it to the public internet without HTTPS and additional hardening.
