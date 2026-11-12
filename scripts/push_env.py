#!/usr/bin/env python3
"""
Compose and upload the Custospark Events production .env via SFTP, WITHOUT
ever printing credential values.

Events has NO staging environment. There is exactly one target:

    production -> /home/u214605677/domains/events-api.custospark.com

Usage:
  python scripts/push_env.py production
  python scripts/push_env.py --render .env.rendered production   # local preview

Inputs (never printed):
  - Backend/.env                  SSH_DEPLOY_* creds + MAIL_* / PESAPAL_* /
                                  CUSTOSPARK_* values carried to production
  - Backend/.env.production       preferred source of DB_* values (gitignored)
                                  OR a commented "#Production" DB block in
                                  Backend/.env (Academy-style fallback)

APP_KEY is read from the live server .env and carried forward. It is NEVER
regenerated here (except by the first-time manual `key:generate`).
"""
from __future__ import annotations

import sys
from pathlib import Path

import paramiko

BACKEND_ROOT = Path(__file__).resolve().parent.parent
ENV_PATH = BACKEND_ROOT / ".env"
ENV_PRODUCTION_PATH = BACKEND_ROOT / ".env.production"

APP_DIRS = {
    "production": "/home/u214605677/domains/events-api.custospark.com",
}
APP_URL = {
    "production": "https://events-api.custospark.com",
}
FRONTEND_URL = {
    "production": "https://events.custospark.com",
}

CARRY_FROM_LOCAL = (
    "APP_VERSION",
    "APP_TIMEZONE",
    "TENANT_DOMAIN_SUFFIX",
    "PLATFORM_ADMIN_EMAILS",
    "MAIL_MAILER",
    "MAIL_SCHEME",
    "MAIL_HOST",
    "MAIL_PORT",
    "MAIL_USERNAME",
    "MAIL_PASSWORD",
    "MAIL_ENCRYPTION",
    "MAIL_FROM_ADDRESS",
    "MAIL_FROM_NAME",
    "CUSTOSPARK_API_URL",
    "CUSTOSPARK_API_KEY",
    "CUSTOSPARK_WEBHOOK_SECRET",
    "CUSTOSPARK_COMMISSION_PERCENT",
    "PESAPAL_SANDBOX_CONSUMER_KEY",
    "PESAPAL_SANDBOX_CONSUMER_SECRET",
    "PESAPAL_PRODUCTION_CONSUMER_KEY",
    "PESAPAL_PRODUCTION_CONSUMER_SECRET",
)


def load_env(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    if not path.exists():
        return out
    with open(path, "r", encoding="utf-8", errors="ignore") as fh:
        for raw in fh:
            line = raw.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            value = value.strip()
            if len(value) >= 2 and value[0] == value[-1] and value[0] in ('"', "'"):
                value = value[1:-1]
            out[key.strip()] = value
    return out


def parse_db_block(env_text: str, marker: str) -> dict[str, str]:
    """Return {DB_*: value} from the commented block under `#<marker>`."""
    block: dict[str, str] = {}
    in_block = False
    for raw in env_text.splitlines():
        line = raw.strip()
        if line.startswith("#") and not line.startswith("# DB_"):
            in_block = line == f"#{marker}"
            continue
        if in_block and line.startswith("# DB_") and "=" in line:
            key, _, value = line[1:].strip().partition("=")
            block[key.strip()] = value.strip()
        elif line and not line.startswith("#") and block:
            break
    return block


def load_db(env_name: str) -> dict[str, str]:
    if ENV_PRODUCTION_PATH.exists():
        db = {k: v for k, v in load_env(ENV_PRODUCTION_PATH).items() if k.startswith("DB_")}
    else:
        env_text = ENV_PATH.read_text(encoding="utf-8", errors="ignore")
        db = parse_db_block(env_text, "Production" if env_name == "production" else "Staging")

    missing = [k for k in ("DB_DATABASE", "DB_USERNAME", "DB_PASSWORD") if not db.get(k)]
    if missing:
        raise SystemExit(
            "ERROR: production DB credentials missing "
            f"({', '.join(missing)}). Copy .env.production.example to .env.production "
            "and fill the DB_* values (do not commit it)."
        )
    db.setdefault("DB_CONNECTION", "mysql")
    db.setdefault("DB_HOST", "127.0.0.1")
    db.setdefault("DB_PORT", "3306")
    return db


def q(value: str) -> str:
    escaped = str(value).replace("\\", "\\\\").replace('"', '\\"')
    return f'"{escaped}"'


def build_content(env_name: str, app_key: str = "") -> str:
    secrets = load_env(ENV_PATH)
    db = load_db(env_name)

    values: dict[str, str] = {
        "APP_NAME": "Custospark Events",
        "APP_ENV": env_name,
        "APP_KEY": app_key,
        "APP_DEBUG": "false",
        "APP_URL": APP_URL[env_name],
        "APP_VERSION": secrets.get("APP_VERSION", "0.1.0"),
        "APP_TIMEZONE": secrets.get("APP_TIMEZONE", "Africa/Kampala"),
        "APP_LOCALE": "en",
        "APP_FALLBACK_LOCALE": "en",
        "APP_FAKER_LOCALE": "en_US",
        "APP_MAINTENANCE_DRIVER": "file",
        "BCRYPT_ROUNDS": "12",
        "LOG_CHANNEL": "stack",
        "LOG_STACK": "single",
        "LOG_DEPRECATIONS_CHANNEL": "null",
        "LOG_LEVEL": "warning",
        "DB_CONNECTION": db.get("DB_CONNECTION", "mysql"),
        "DB_HOST": db.get("DB_HOST", "127.0.0.1"),
        "DB_PORT": db.get("DB_PORT", "3306"),
        "DB_DATABASE": db["DB_DATABASE"],
        "DB_USERNAME": db["DB_USERNAME"],
        "DB_PASSWORD": db["DB_PASSWORD"],
        "SESSION_DRIVER": "database",
        "SESSION_LIFETIME": "120",
        "SESSION_ENCRYPT": "true",
        "SESSION_PATH": "/",
        "SESSION_DOMAIN": ".custospark.com",
        "SESSION_SECURE_COOKIE": "true",
        "BROADCAST_CONNECTION": "log",
        "FILESYSTEM_DISK": "local",
        "QUEUE_CONNECTION": "database",
        "CACHE_STORE": "database",
        "MAIL_MAILER": "smtp",
        "MAIL_SCHEME": "tls",
        "MAIL_HOST": "",
        "MAIL_PORT": "587",
        "MAIL_FROM_ADDRESS": "hello@custospark.com",
        "MAIL_FROM_NAME": "Custospark Events",
        "FRONTEND_URL": FRONTEND_URL[env_name],
        "CORS_ALLOWED_ORIGINS": FRONTEND_URL[env_name],
        "CORS_MAX_AGE": "3600",
        "SANCTUM_STATEFUL_DOMAINS": "events.custospark.com,events-api.custospark.com",
        "APP_FORCE_HTTPS": "true",
        "TRUSTED_PROXIES": "127.0.0.1,::1",
        "TENANT_DOMAIN_SUFFIX": secrets.get("TENANT_DOMAIN_SUFFIX", "custospark.events"),
        "PLATFORM_ADMIN_EMAILS": secrets.get("PLATFORM_ADMIN_EMAILS", ""),
        "CUSTOSPARK_API_URL": secrets.get("CUSTOSPARK_API_URL", "https://api.custospark.com"),
        "CUSTOSPARK_API_KEY": secrets.get("CUSTOSPARK_API_KEY", ""),
        "CUSTOSPARK_WEBHOOK_SECRET": secrets.get("CUSTOSPARK_WEBHOOK_SECRET", ""),
        "CUSTOSPARK_COMMISSION_PERCENT": secrets.get("CUSTOSPARK_COMMISSION_PERCENT", "5"),
        "PESAPAL_ENABLED": "true",
        "PESAPAL_ENVIRONMENT": "production",
        "PESAPAL_BYPASS": "false",
        "PESAPAL_CALLBACK_URL": f"{APP_URL[env_name]}/api/v1/payments/callback",
        "PESAPAL_IPN_URL": f"{APP_URL[env_name]}/api/v1/payments/ipn",
        "PESAPAL_TOKEN_CACHE_TTL": "1800",
    }

    for key in CARRY_FROM_LOCAL:
        value = secrets.get(key, "")
        if value:
            values[key] = value

    # Production-only overrides (DB_* is handled above; APP_KEY must stay from
    # the live server; all other values may be overridden, e.g. MAIL_*).
    if ENV_PRODUCTION_PATH.exists():
        for key, value in load_env(ENV_PRODUCTION_PATH).items():
            if key.startswith("DB_") or key in {"APP_KEY", "APP_ENV", "APP_URL", "APP_DEBUG"}:
                continue
            if value:
                values[key] = value

    # Never carry a sandbox IPN id to production. A production id may be set
    # explicitly in Backend/.env.production (from `php artisan pesapal:register-ipn`).
    if not values.get("PESAPAL_IPN_ID"):
        values["PESAPAL_IPN_ID"] = ""

    lines = [f"{key}={q(value)}" for key, value in values.items()]
    return "\n".join(lines) + "\n"


def connect(creds: dict[str, str]) -> paramiko.SSHClient:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        hostname=creds["SSH_DEPLOY_HOST"],
        port=int(creds["SSH_DEPLOY_PORT"]),
        username=creds["SSH_DEPLOY_USER"],
        password=creds["SSH_DEPLOY_PASSWORD"],
        timeout=30,
    )
    return client


def main() -> None:
    argv = sys.argv[1:]
    render_to: str | None = None
    if argv and argv[0] == "--render":
        render_to = argv[1] if len(argv) > 1 else "production.env"
        argv = argv[2:]

    env_name = argv[0] if argv else "production"
    if env_name not in APP_DIRS:
        raise SystemExit("Usage: python scripts/push_env.py [--render <path>] production")

    secrets = load_env(ENV_PATH)
    missing_ssh = [
        k
        for k in ("SSH_DEPLOY_HOST", "SSH_DEPLOY_PORT", "SSH_DEPLOY_USER", "SSH_DEPLOY_PASSWORD")
        if not secrets.get(k)
    ]
    if missing_ssh:
        raise SystemExit(
            "ERROR: SSH_DEPLOY_* missing from Backend/.env "
            f"({', '.join(missing_ssh)}). Copy them from the Academy Backend .env."
        )

    app_key = ""
    try:
        probe = connect(secrets)
        _stdin, stdout, _stderr = probe.exec_command(
            f"grep -E '^APP_KEY=' {APP_DIRS[env_name]}/.env 2>/dev/null | cut -d= -f2- "
            "| tr -d '\"' | tr -d \"'\""
        )
        stdout.channel.recv_exit_status()
        app_key = stdout.read().decode("utf-8", "replace").strip()
        probe.close()
    except Exception as exc:  # noqa: BLE001 - first deploy has no .env yet
        print(f"note: could not read live APP_KEY ({type(exc).__name__}) - leaving empty")

    content = build_content(env_name, app_key)
    if render_to:
        with open(render_to, "w", encoding="utf-8", newline="\n") as fh:
            fh.write(content)
        print(
            f"OK  {env_name}: rendered to {render_to} "
            f"({len(content.encode('utf-8'))} bytes, creds masked)"
        )
        return

    client = connect(secrets)
    sftp = client.open_sftp()
    target = f"{APP_DIRS[env_name]}/.env.{env_name}"
    with sftp.file(target, "w") as fh:
        fh.write(content.encode("utf-8"))
    _stdin, stdout, _stderr = client.exec_command(
        f"mv -f {APP_DIRS[env_name]}/.env.{env_name} {APP_DIRS[env_name]}/.env && "
        f"chmod 600 {APP_DIRS[env_name]}/.env"
    )
    stdout.channel.recv_exit_status()
    count = content.count("=")
    print(
        f"OK  {env_name}: uploaded .env.{env_name} -> renamed to .env "
        f"({len(content.encode('utf-8'))} bytes, {count} keys, creds masked)"
    )
    sftp.close()
    client.close()


if __name__ == "__main__":
    main()
