#!/usr/bin/env python3
"""sv7288 上で wordpress-123 / PluginTest / custom-rss-builder を探す。"""
from __future__ import annotations

import base64
import ftplib
import socket
import xml.etree.ElementTree as ET
from pathlib import Path

FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
TARGET_IP = socket.gethostbyname("wordpress-123.com")


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (srv.findtext("User") or "").strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("credentials not found")


def walk(ftp: ftplib.FTP, path: str, depth: int, hits: list[str]) -> None:
    if depth > 5:
        return
    try:
        ftp.cwd(path)
    except ftplib.error_perm:
        return
    try:
        names = ftp.nlst()
    except ftplib.error_perm:
        return
    for name in names:
        if name in (".", ".."):
            continue
        low = name.lower()
        full = f"{path}/{name}".replace("//", "/")
        if any(
            k in low
            for k in (
                "wordpress-123",
                "plugintest",
                "custom-rss",
            )
        ):
            hits.append(full)
        if depth < 5 and "." not in name:
            walk(ftp, full, depth + 1, hits)


def main() -> int:
    print("wordpress-123.com IP:", TARGET_IP)
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    hits: list[str] = []
    ftp.cwd("/")
    for name in ftp.nlst():
        if name in (".", ".."):
            continue
        low = name.lower()
        if "wordpress" in low or "123" in low or "plugin" in low:
            hits.append("/" + name)
        walk(ftp, "/" + name, 1, hits)
    ftp.quit()
    print("matches:")
    for h in sorted(set(hits)):
        print(" ", h)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
