---
title: Caching
layout: default
nav_order: 4
---

# Caching

Parsed HTML is cached in `cache/html/` keyed by the content file path. The index is cached in `cache/index.php`. Both rebuild automatically when the source file is newer.

Delete the `cache/` directory to force a full rebuild.
