#!/usr/bin/env python3
import re
import zipfile
from pathlib import Path

zpath = Path(__file__).resolve().parents[1] / "dist" / "custom-rss-builder-client.zip"
with zipfile.ZipFile(zpath) as z:
    main = z.read("custom-rss-builder/custom-rss-builder.php").decode("utf-8")
print("BUILD", re.search(r"CRB_BUILD_ID', '([^']+)'", main).group(1))
print("VARIANT", re.search(r"CRB_PACKAGE_VARIANT', '([^']+)'", main).group(1))
