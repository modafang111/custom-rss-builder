# -*- coding: utf-8 -*-
import base64
import ftplib
import re
import sys
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

REMOTE = (
    "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/"
    "custom-rss-builder/includes/functions-sales-lp.php"
)
FZ = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"


def load_password() -> str:
    tree = ET.parse(FZ)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() != "sv7288.xserver.jp":
            continue
        if (srv.findtext("User") or "").strip() != "ideamart1":
            continue
        enc = srv.find("Pass")
        if enc is not None and enc.text:
            return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def main() -> int:
    pw = load_password()
    ftp = ftplib.FTP("sv7288.xserver.jp", "ideamart1", pw, timeout=60)
    buf = BytesIO()
    ftp.retrbinary(f"RETR {REMOTE}", buf.write)
    ftp.quit()
    body = buf.getvalue().decode("utf-8", errors="replace")
    ver = re.search(r"CRB_SALES_LP_VERSION',\s*'(\d+)'", body)
    print("FTP remote version:", ver.group(1) if ver else "NOT FOUND")
    print("FTP standard card:", "price-card--standard" in body)
    print("FTP standard helper:", "crb_sales_lp_standard_payment_url" in body)
    return 0


if __name__ == "__main__":
    sys.exit(main())
