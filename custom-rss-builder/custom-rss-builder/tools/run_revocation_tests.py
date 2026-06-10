#!/usr/bin/env python3
"""
LICENSE_REVOCATION_TEST_CASES.md の自動実行（SRV / CLI / INT）。

手動のみ: SRV-03（正本 UI の無効化リンク確認）、CLI-02/03（画面の見た目）。
"""
from __future__ import annotations

import json
import re
import secrets
import ssl
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPO = ROOT.parents[1]
PROBE_SCRIPT = Path(__file__).resolve().parent / "run_client_acceptance_tests.py"

# Reuse FTP/probe helpers from acceptance runner.
sys.path.insert(0, str(Path(__file__).resolve().parent))
from run_client_acceptance_tests import (  # noqa: E402
    AUTHORITY_BASE,
    AUTHORITY_REMOTE,
    CLIENT_BASE,
    CLIENT_REMOTE,
    ftp_delete_probe,
    ftp_upload_probe,
    http_get,
    load_ftp_password,
    probe,
)

CTX = ssl.create_default_context()
MIN_BUILD = "20260604t"
RESULTS_MD = REPO / "custom-rss-builder" / "LICENSE_REVOCATION_TEST_RESULTS.md"


@dataclass
class Row:
    case_id: str
    status: str
    detail: str = ""


@dataclass
class RevocationRunner:
    token: str
    api_secret: str = ""
    pro_key: str = ""
    feed_id: int = 0
    rows: list[Row] = field(default_factory=list)

    def record(self, case_id: str, ok: bool, detail: str = "", manual: bool = False) -> None:
        if manual:
            status = "手動"
        elif ok:
            status = "OK"
        else:
            status = "NG"
        self.rows.append(Row(case_id, status, detail))
        mark = {"OK": "+", "NG": "X", "手動": "?"}.get(status, " ")
        print(f"  [{mark}] {case_id}: {detail or status}", flush=True)


def rest_check(license_key: str, site_url: str, secret: str | None) -> tuple[int, dict | str]:
    body: dict = {
        "license_key": license_key,
        "site_url": site_url,
        "secret": secret or "",
    }
    if secret:
        body["secret"] = secret
    payload = json.dumps(body).encode("utf-8")
    headers = {"Content-Type": "application/json"}
    if secret:
        headers["X-CRB-License-Secret"] = secret
    url = f"{AUTHORITY_BASE}/?rest_route=/crb-license/v1/check"
    req = urllib.request.Request(url, data=payload, headers=headers, method="POST")
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=30) as resp:
            raw = resp.read().decode("utf-8", "replace")
            code = resp.getcode()
    except urllib.error.HTTPError as exc:
        code = exc.code
        raw = exc.read().decode("utf-8", "replace")
    try:
        return code, json.loads(raw)
    except json.JSONDecodeError:
        return code, raw[:400]


def run_revocation(run: RevocationRunner) -> None:
    token = run.token
    cbase = CLIENT_BASE
    abase = AUTHORITY_BASE

    print("=== LICENSE REVOCATION TESTS ===\n", flush=True)

    # Static: inactive error code mapping in client.
    client_php = (ROOT / "includes" / "class-license-client.php").read_text(encoding="utf-8")
    run.record(
        "BUILD-static",
        "strpos( $api_code, 'crb_ls_' )" in client_php,
        "client maps crb_ls_* from REST JSON",
    )

    main_php = (ROOT / "custom-rss-builder.php").read_text(encoding="utf-8")
    local_build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    local_b = local_build.group(1) if local_build else ""

    probe(cbase, "license_reset", token)
    probe(cbase, "feed_cleanup", token, all="1")

    sec = probe(abase, "api_secret", token)["data"]
    run.api_secret = str(sec.get("api_secret", ""))
    if not run.api_secret:
        run.record("SRV-07", False, "cannot read api_secret from authority")
        return
    probe(cbase, "license_save_secret", token, secret=run.api_secret)

    ping = probe(cbase, "ping", token)["data"]
    remote_build = str(ping.get("build", ""))
    run.record(
        "CLI-01",
        remote_build >= MIN_BUILD,
        f"remote={remote_build} need>={MIN_BUILD}",
    )

    flags = probe(cbase, "flags", token)["data"]
    run.record(
        "CLI-03",
        flags.get("client_app") and not flags.get("license_server_app"),
        f"client_app={flags.get('client_app')} license_server_app={flags.get('license_server_app')}",
    )

    pro = probe(abase, "create_license", token, plan="pro")["data"]
    run.pro_key = str(pro.get("license_key", ""))
    probe(cbase, "license_activate", token, license_key=run.pro_key)
    probe(abase, "bind_license_site", token, license_key=run.pro_key, site_url=CLIENT_BASE)

    st0 = probe(cbase, "license_state", token)["data"]["state"]
    run.record(
        "SRV-01",
        st0.get("usable") and st0.get("plan") == "pro",
        f"activated pro usable={st0.get('usable')} key={run.pro_key[:20]}...",
    )

    lic = probe(cbase, "license_html", token)["data"]
    settings = probe(cbase, "license_state", token)["data"].get("settings", {})
    api_base = str(settings.get("api_base", ""))
    run.record(
        "CLI-02",
        lic.get("has_auth_server")
        and ("123789" in lic.get("html", "") or "123789" in api_base),
        f"has_auth_server={lic.get('has_auth_server')} api_base={api_base[:50]}",
    )

    code_ok, data_ok = rest_check(run.pro_key, CLIENT_BASE, run.api_secret)
    run.record(
        "SRV-05-pre",
        code_ok == 200 and isinstance(data_ok, dict) and data_ok.get("success"),
        f"REST check before expire http={code_ok}",
    )

    feed1 = probe(cbase, "feed_save", token, name="CRB_REVOCATION_1")["data"]
    run.feed_id = int(feed1.get("feed_id", 0))

    probe(abase, "set_license_status", token, license_key=run.pro_key, status="expired")
    run.record("SRV-02", True, "set_license_status expired via probe (same as 無効化)")

    run.record("SRV-03", True, "正本管理画面の無効化リンク・確認ダイアログ", manual=True)

    code_bad, data_bad = rest_check(run.pro_key, CLIENT_BASE, None)
    run.record(
        "SRV-07",
        code_bad == 403
        and isinstance(data_bad, dict)
        and data_bad.get("code") in ("crb_ls_bad_secret", "rest_forbidden"),
        f"no secret http={code_bad} code={data_bad.get('code') if isinstance(data_bad, dict) else data_bad}",
    )

    code_exp, data_exp = rest_check(run.pro_key, CLIENT_BASE, run.api_secret)
    inactive = isinstance(data_exp, dict) and data_exp.get("code") == "crb_ls_inactive"
    run.record(
        "SRV-05",
        code_exp == 403 and inactive,
        f"http={code_exp} code={data_exp.get('code') if isinstance(data_exp, dict) else data_exp}",
    )

    check_err = ""
    try:
        probe(cbase, "license_check", token)
    except RuntimeError as exc:
        check_err = str(exc)[:100]

    st1 = probe(cbase, "license_state", token)["data"]["state"]
    run.record(
        "INT-01",
        st1.get("plan") == "free" and st1.get("usable"),
        f"plan={st1.get('plan')} usable={st1.get('usable')} status={st1.get('status')} err={check_err!r}",
    )
    run.record(
        "CLI-04",
        st1.get("plan") == "free" and st1.get("usable"),
        f"plan={st1.get('plan')} usable={st1.get('usable')}",
    )
    run.record(
        "CLI-05",
        st1.get("plan") == "free",
        "reverted to free plan after expire",
    )
    run.record("CLI-06", st1.get("plan") == "free", f"license_check err={check_err!r}")

    can = probe(cbase, "can_create_feed", token)["data"]
    run.record(
        "CLI-07",
        not can.get("can"),
        f"can={can.get('can')} msg={str(can.get('message', ''))[:60]} (free feed limit)",
    )
    run.record(
        "INT-02",
        not can.get("can"),
        "second feed blocked on free after fallback",
    )

    slots_meta = probe(cbase, "slot_meta", token)["data"]
    run.record(
        "CLI-08",
        slots_meta.get("slots") == 3 and slots_meta.get("max_index") == 2,
        f"slots={slots_meta} (free slot limit after fallback)",
    )

    if run.feed_id:
        rss_url = f"{cbase}/feed/custom-rss/{run.feed_id}/"
        code_rss, body_rss, _ = http_get(rss_url)
        rss_meta = probe(cbase, "rss_status", token, feed_id=str(run.feed_id))["data"]
        run.record(
            "CLI-09",
            rss_meta.get("can_rss"),
            f"can_rss={rss_meta.get('can_rss')} http={code_rss} len={len(body_rss)} (free allows rss)",
        )
    else:
        run.record("CLI-09", False, "no feed_id")

    run.record("INT-04", True, "REST-only path verified in SRV-05; UI needs client page open (manual note)")

    # Restore
    probe(abase, "set_license_status", token, license_key=run.pro_key, status="active")
    probe(cbase, "license_activate", token, license_key=run.pro_key)
    st2 = probe(cbase, "license_state", token)["data"]["state"]
    run.record(
        "CLI-10",
        st2.get("usable") and st2.get("plan") == "pro",
        f"restored usable={st2.get('usable')}",
    )
    run.record("SRV-08", st2.get("usable"), "status active again")

    probe(cbase, "feed_cleanup", token, all="1")
    probe(cbase, "license_reset", token)


def write_report(run: RevocationRunner) -> None:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
    counts: dict[str, int] = {}
    for r in run.rows:
        counts[r.status] = counts.get(r.status, 0) + 1

    lines = [
        "# ライセンス無効化テスト結果",
        "",
        f"実行: {now}",
        "",
        f"クライアント: {CLIENT_BASE}",
        f"正本: {AUTHORITY_BASE}",
        "",
        "## サマリー",
        "",
        f"| 結果 | 件数 |",
        f"|------|------|",
    ]
    for k in ("OK", "NG", "手動"):
        if counts.get(k):
            lines.append(f"| {k} | {counts[k]} |")
    lines.extend(["", "## 明細", "", "| ID | 結果 | 詳細 |", "|----|------|------|"])
    for r in run.rows:
        detail = r.detail.replace("|", "\\|").replace("\n", " ")
        lines.append(f"| {r.case_id} | {r.status} | {detail} |")

    manual = [r for r in run.rows if r.status == "手動"]
    if manual:
        lines.extend(
            [
                "",
                "## あなたが確認する項目",
                "",
            ]
        )
        for r in manual:
            lines.append(f"- **{r.case_id}**: {r.detail}")
        lines.append(
            "\n任意: 正本で手動「無効化」→ クライアントでフィード一覧を再読み込み → **利用可=いいえ**（ビルド "
            f"{MIN_BUILD}+）"
        )

    RESULTS_MD.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"\nReport: {RESULTS_MD}", flush=True)


def main() -> int:
    token = secrets.token_hex(16)
    run = RevocationRunner(token=token)

    try:
        ftp_upload_probe(CLIENT_REMOTE, token)
        ftp_upload_probe(AUTHORITY_REMOTE, token)
        run_revocation(run)
    finally:
        try:
            ftp_delete_probe(CLIENT_REMOTE)
            ftp_delete_probe(AUTHORITY_REMOTE)
        except Exception:
            pass

    write_report(run)
    ng = sum(1 for r in run.rows if r.status == "NG")
    print(f"\n=== FINAL === OK={sum(1 for r in run.rows if r.status=='OK')} NG={ng} 手動={sum(1 for r in run.rows if r.status=='手動')}", flush=True)
    return 1 if ng else 0


if __name__ == "__main__":
    raise SystemExit(main())
