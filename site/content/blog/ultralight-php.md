---
title: 'On Ultralight PHP'
---

# On Ultralight PHP 2

Cached markdown-to-HTML is essentially free at runtime. One `file_exists` check, one `readfile`. That's it.

The first request to any URL parses the markdown and writes a PHP cache file. Every subsequent request returns the cached HTML until the source .md file's mtime changes.  

  

asdadasd