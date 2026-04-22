---
title: On Ultralight PHP
date: 2026-04-21
categories: [php, architecture]
tags: [markdown, performance]
excerpt: Why a flat-file site can be faster than WordPress.
---

# On Ultralight PHP

Cached markdown-to-HTML is essentially free at runtime. One `file_exists` check, one `readfile`. That's it.

The first request to any URL parses the markdown and writes a PHP cache file. Every subsequent request returns the cached HTML until the source `.md` file's mtime changes.
