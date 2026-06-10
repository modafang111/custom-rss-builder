# -*- coding: utf-8 -*-
"""
Level 3: クライアント用 / ライセンス正本サーバー用の2種 ZIP をビルドする。

- custom-rss-builder-client.zip     … license-server/ なし
- custom-rss-builder-license-server.zip … フィード関連 PHP なし

開発用フルツリーは custom-rss-builder/custom-rss-builder/ のまま。
"""
from __future__ import annotations

import json
import os
import re
import sys
import zipfile
from pathlib import Path

BASE = Path(__file__).resolve().parent
PLUGIN_DIR = BASE / "custom-rss-builder" / "custom-rss-builder"
DIST_DIR = BASE / "dist"
MAIN_FILE = "custom-rss-builder.php"
PLUGIN_SLUG = "custom-rss-builder"

SKIP_NAMES = {".DS_Store", "Thumbs.db"}

# 配布 ZIP 共通で除外（開発・検証用）
DEV_REL_PREFIXES = (
    "tools/",
    "test-fixture/",
    "tests/",
)

DEV_REL_EXACT = frozenset()

# クライアント ZIP から除外（正本サーバー機能）
CLIENT_REL_PREFIXES = (
    "license-server/",
)
CLIENT_REL_EXACT = frozenset(
    {
        "includes/class-custom-rss-builder-authority.php",
    }
)

# サーバー ZIP から除外（フィード・RSS・取り込み・クライアントライセンス UI）
SERVER_REL_EXACT = frozenset(
    {
        "admin/class-admin-page.php",
        "admin/class-admin-license.php",
        "admin/views/edit-feed.php",
        "admin/views/settings-page.php",
        "admin/views/preview-results.php",
        "admin/views/import-preview-results.php",
        "admin/views/license-settings.php",
        "admin/views/partials/feed-import-settings.php",
        "admin/views/partials/feed-meta-panel.php",
        "admin/views/partials/license-key-form.php",
        "assets/css/admin.css",
        "assets/js/admin.js",
        "includes/class-custom-rss-builder.php",
        "includes/class-css-extractor.php",
        "includes/class-element-discovery.php",
        "includes/class-feed-manager.php",
        "includes/class-html-fetcher.php",
        "includes/class-item-builder.php",
        "includes/class-content-template.php",
        "includes/class-post-importer.php",
        "includes/class-rss-generator.php",
        "includes/class-import-scheduler.php",
        "includes/class-import-loopback.php",
        "includes/class-license-client.php",
        "includes/functions-sanitize.php",
        "includes/functions-html-cache.php",
        "includes/functions-css.php",
        "includes/functions-extract-candidates.php",
        "includes/functions-feed43.php",
        "includes/functions-discover-infer.php",
        "includes/functions-import-schedule.php",
        "includes/functions-ai-settings.php",
        "includes/functions-ai-transform.php",
        "includes/functions-free-credit.php",
        "includes/functions-link-rewrite.php",
        "public/class-rss-endpoint.php",
        "public/class-import-endpoint.php",
    }
)

DEFAULT_CLIENT_API_BASE = "https://123789.jp/custom-rss-builder"
CLIENT_SECRETS_LOCAL = BASE / "tools" / "crb_client_build_secrets.local.json"
CLIENT_SECRETS_FALLBACK = BASE / "tools" / "crb_client_build_secrets.json"


def load_client_build_secrets() -> dict[str, str]:
    """Client ZIP 用 API Secret（gitignore 済み local JSON または環境変数）。"""
    for path in (CLIENT_SECRETS_LOCAL, CLIENT_SECRETS_FALLBACK):
        if path.is_file():
            data = json.loads(path.read_text(encoding="utf-8"))
            secret = str(data.get("api_secret", "")).strip()
            if secret:
                api_base = str(data.get("api_base", DEFAULT_CLIENT_API_BASE)).strip()
                return {"api_base": api_base or DEFAULT_CLIENT_API_BASE, "api_secret": secret}

    env_secret = os.environ.get("CRB_LICENSE_API_SECRET", "").strip()
    if env_secret:
        api_base = os.environ.get("CRB_LICENSE_API_BASE", DEFAULT_CLIENT_API_BASE).strip()
        return {"api_base": api_base or DEFAULT_CLIENT_API_BASE, "api_secret": env_secret}

    raise SystemExit(
        "Client build requires API Secret.\n"
        "  python tools/fetch_authority_api_secret.py\n"
        "  or set CRB_LICENSE_API_SECRET in the environment."
    )


def patch_client_distribution_constants(content: str, secrets: dict[str, str]) -> str:
    """配布 client ZIP に認証サーバー接続定数を埋め込む（利用者の手入力不要）。"""
    content = re.sub(
        r"\n?define\(\s*'CRB_LICENSE_DEFAULT_CLIENT_API_BASE'[^\n]*\n?",
        "\n",
        content,
    )
    content = re.sub(
        r"\n?define\(\s*'CRB_LICENSE_API_BASE'[^\n]*\n?",
        "\n",
        content,
    )
    content = re.sub(
        r"\n?define\(\s*'CRB_LICENSE_API_SECRET'[^\n]*\n?",
        "\n",
        content,
    )

    api_base = secrets["api_base"].replace("\\", "\\\\").replace("'", "\\'")
    api_secret = secrets["api_secret"].replace("\\", "\\\\").replace("'", "\\'")
    block = (
        f"\ndefine( 'CRB_LICENSE_DEFAULT_CLIENT_API_BASE', '{api_base}' );"
        f"\ndefine( 'CRB_LICENSE_API_BASE', '{api_base}' );"
        f"\ndefine( 'CRB_LICENSE_API_SECRET', '{api_secret}' );"
    )
    return re.sub(
        r"(define\(\s*'CRB_BUILD_ID'\s*,\s*'[^']+'\s*\)\s*;)",
        rf"\1{block}",
        content,
        count=1,
    )


HEADER_PATCHES: dict[str, dict[str, str]] = {
    "client": {
        "Plugin Name": "Custom RSS Builder",
        "Description": "RSS・投稿取り込みクライアント（ライセンスは正本サーバーへ REST 接続）。",
    },
    "authority": {
        "Plugin Name": "Custom RSS Builder License Server",
        "Description": "ライセンス発行・REST API・PayPal（正本サーバー専用）。",
    },
}


def should_skip_name(name: str) -> bool:
    return name in SKIP_NAMES or name.startswith(".")


def rel_posix(path: Path, root: Path) -> str:
    return path.relative_to(root).as_posix()


def is_dev_excluded(rel: str) -> bool:
    if rel in DEV_REL_EXACT:
        return True
    return any(rel.startswith(p) for p in DEV_REL_PREFIXES)


def is_client_excluded(rel: str) -> bool:
    if is_dev_excluded(rel):
        return True
    if rel in CLIENT_REL_EXACT:
        return True
    return any(rel.startswith(p) for p in CLIENT_REL_PREFIXES)


def is_server_excluded(rel: str) -> bool:
    if is_dev_excluded(rel):
        return True
    if rel in SERVER_REL_EXACT:
        return True
    if rel.startswith("admin/views/partials/feed-"):
        return True
    return False


def authority_deploy_prune_paths() -> tuple[tuple[str, ...], frozenset[str]]:
    """FTP 正本デプロイ後にリモートから削除するパス（旧フルツリー残骸）。"""
    prefixes: list[str] = list(DEV_REL_PREFIXES) + [
        "admin/views/partials/feed-",
    ]
    exact = set(SERVER_REL_EXACT) | {
        "includes/functions-free-credit.php",
        "includes/functions-ai-settings.php",
        "includes/functions-ai-transform.php",
        "includes/functions-link-rewrite.php",
        "crb_acceptance_probe.php",
    }
    return tuple(prefixes), frozenset(exact)


def patch_main_plugin(content: str, variant: str) -> str:
    if variant not in HEADER_PATCHES:
        raise ValueError(f"unknown variant: {variant}")

    content = re.sub(
        r"define\(\s*'CRB_PACKAGE_VARIANT'\s*,\s*'[^']*'\s*\)\s*;\s*\n?",
        "",
        content,
    )
    content = re.sub(
        r"(define\(\s*'CRB_BUILD_ID'\s*,\s*'[^']+'\s*\)\s*;)",
        rf"define( 'CRB_PACKAGE_VARIANT', '{variant}' );\n\1",
        content,
        count=1,
    )

    for key, value in HEADER_PATCHES[variant].items():
        content = re.sub(
            rf"^\s*\*\s*{re.escape(key)}:\s*.*$",
            f" * {key}: {value}",
            content,
            count=1,
            flags=re.MULTILINE,
        )
    if variant == "client":
        content = patch_client_distribution_constants(content, load_client_build_secrets())
    return content


def iter_included_files(variant: str) -> list[tuple[Path, str]]:
    exclude_fn = is_client_excluded if variant == "client" else is_server_excluded
    out: list[tuple[Path, str]] = []
    for file_path in sorted(PLUGIN_DIR.rglob("*")):
        if file_path.is_dir() or should_skip_name(file_path.name):
            continue
        if file_path.suffix == ".pyc" or "__pycache__" in file_path.parts:
            continue
        rel = rel_posix(file_path, PLUGIN_DIR)
        if exclude_fn(rel):
            continue
        out.append((file_path, rel))
    return out


def prepare_file_bytes(src: Path, rel: str, variant: str) -> bytes:
    data = src.read_bytes()
    if rel == MAIN_FILE:
        return patch_main_plugin(data.decode("utf-8"), variant).encode("utf-8")
    return data


def materialize_variant(variant: str, dest_dir: Path) -> tuple[int, list[str]]:
    """配布 ZIP と同一内容のディレクトリを生成（FTP デプロイ等）。"""
    dest_dir.mkdir(parents=True, exist_ok=True)
    entries: list[str] = []
    for src, rel in iter_included_files(variant):
        out_path = dest_dir / rel
        out_path.parent.mkdir(parents=True, exist_ok=True)
        out_path.write_bytes(prepare_file_bytes(src, rel, variant))
        entries.append(rel)
    return len(entries), entries


def write_zip(variant: str, output: Path) -> tuple[int, list[str]]:
    if output.exists():
        output.unlink()

    entries: list[str] = []
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as zf:
        for src, rel in iter_included_files(variant):
            arcname = f"{PLUGIN_SLUG}/{rel}"
            zf.writestr(arcname, prepare_file_bytes(src, rel, variant))
            entries.append(arcname)

    return len(entries), entries


def verify_zip(path: Path, variant: str) -> list[str]:
    errors: list[str] = []
    with zipfile.ZipFile(path) as zf:
        names = zf.namelist()
        main = f"{PLUGIN_SLUG}/{MAIN_FILE}"
        if main not in names:
            errors.append(f"{path.name}: missing {main}")
            return errors
        body = zf.read(main).decode("utf-8")
        if f"CRB_PACKAGE_VARIANT', '{variant}'" not in body:
            errors.append(f"{path.name}: CRB_PACKAGE_VARIANT not set to {variant}")

        if variant == "client":
            forbidden = [n for n in names if "/license-server/" in n]
            if forbidden:
                errors.append(f"{path.name}: contains license-server ({len(forbidden)} files)")
        else:
            forbidden = [
                n
                for n in names
                if any(
                    x in n
                    for x in (
                        "/class-feed-manager.php",
                        "/class-admin-page.php",
                        "/class-rss-endpoint.php",
                        "/class-license-client.php",
                        "/license-settings.php",
                        "/functions-ai-settings.php",
                    )
                )
            ]
            if forbidden:
                errors.append(f"{path.name}: contains feed/client files: {forbidden[:3]}")

        required_authority = f"{PLUGIN_SLUG}/license-server/bootstrap.php"
        required_client = f"{PLUGIN_SLUG}/includes/class-feed-manager.php"
        if variant == "authority" and required_authority not in names:
            errors.append(f"{path.name}: missing {required_authority}")
        if variant == "client" and required_client not in names:
            errors.append(f"{path.name}: missing {required_client}")

    return errors


def main() -> int:
    if not PLUGIN_DIR.is_dir():
        print(f"Missing: {PLUGIN_DIR}", file=sys.stderr)
        return 1

    DIST_DIR.mkdir(parents=True, exist_ok=True)
    outputs = {
        "client": DIST_DIR / "custom-rss-builder-client.zip",
        "authority": DIST_DIR / "custom-rss-builder-license-server.zip",
    }

    all_errors: list[str] = []
    for variant, out_path in outputs.items():
        count, _ = write_zip(variant, out_path)
        print(f"OK  {out_path.name} ({count} files)")
        all_errors.extend(verify_zip(out_path, variant))

    if all_errors:
        for err in all_errors:
            print(f"FAIL {err}", file=sys.stderr)
        return 2

    print("\nAll distribution ZIP checks passed.")
    print(f"Output directory: {DIST_DIR}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
