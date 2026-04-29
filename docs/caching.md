---
title: Caching
layout: default
---

# Caching

Parsed HTML is cached in `cache/html/` keyed by the content file path. The index is cached in `cache/index.php`. Both rebuild automatically when the source file is newer.

Delete the `cache/` directory to force a full rebuild — or use **Settings → Site settings → Cache** in the admin: **Clear cache** wipes HTML + index + Twig artefacts, **Clear & rebuild** also warms the index and HTML cache (`POST /admin/api/cache/clear` and `/admin/api/cache/rebuild`).
