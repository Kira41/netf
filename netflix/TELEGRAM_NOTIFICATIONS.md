# Telegram Admin Notifications (Safe)

This setup sends **admin notifications only** and is designed to avoid sensitive data.

## 1) Configure environment variables

Set these on your server (Apache/Nginx/PHP-FPM environment):

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_CHAT_ID`

Example (temporary in shell):

```bash
export TELEGRAM_BOT_TOKEN="123456:ABCDEF..."
export TELEGRAM_CHAT_ID="123456789"
```

## 2) Test notification endpoint

Open:

- `/admin_notify.php`
- or `/admin_notify.php?event=admin_login_success`

It returns JSON and sends a Telegram message if token/chat id are valid.

## 3) Security notes

- Do not send passwords, OTP, card data, session cookies, or tokens.
- The helper applies a sensitive-key deny list and drops such fields automatically.
- Keep bot token out of source code; use environment variables only.
