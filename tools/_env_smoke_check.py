# -*- coding: utf-8 -*-
"""One-shot env smoke check (FTP + credential load)."""
from __future__ import annotations

import ftplib
import importlib.util
import json
import re
import sys
from io import BytesIO
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
MAIN = (
    ROOT
    / "custom-rss-builder"
    / "custom-rss-builder"
    / "custom-rss-builder.php"
)


def main() -> int:
    text = MAIN.read_text(encoding="utf-8")
    local_ver = re.search(r"\* Version:\s*(\S+)", text)
    local_build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", text)
    print("local Version:", local_ver.group(1) if local_ver else "?")
    print("local BUILD_ID:", local_build.group(1) if local_build else "?")

    cfg = json.loads((ROOT / "deploy.local.json").read_text(encoding="utf-8-sig"))
    host, user, pw, port = (
        cfg["host"],
        cfg["user"],
        cfg["password"],
        int(cfg.get("port", 21)),
    )
    remote = cfg["remote_path"]
    ftp = ftplib.FTP()
    ftp.connect(host, port, timeout=60)
    ftp.login(user, pw)
    ftp.set_pasv(True)
    ftp.cwd(remote)
    names = ftp.nlst()
    print("FTP OK:", host)
    print("cwd:", remote)
    print("entries:", len(names))
    print("has main php:", "custom-rss-builder.php" in names)

    buf = BytesIO()
    ftp.retrbinary("RETR custom-rss-builder.php", buf.write)
    remote_text = buf.getvalue().decode("utf-8", errors="replace")
    r_ver = re.search(r"\* Version:\s*(\S+)", remote_text)
    r_build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", remote_text)
    print("remote Version:", r_ver.group(1) if r_ver else "?")
    print("remote BUILD_ID:", r_build.group(1) if r_build else "?")
    ftp.quit()

    spec = importlib.util.spec_from_file_location(
        "deploy_client", ROOT / "tools" / "deploy_client_plugin_ftp.py"
    )
    assert spec and spec.loader
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    h, u, p, pt = mod.load_credentials()
    print("deploy_client credentials OK:", h, u, "port", pt, "pw_len", len(p))
    return 0


if __name__ == "__main__":
    sys.exit(main())
