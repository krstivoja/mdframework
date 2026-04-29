---
title: 'Composer tips and traps'
date: 2026-03-26
author: 'Marko Krstić'
reading_time: 5
excerpt: 'A handful of Composer flags and conventions that have saved me from production fires more than once.'
categories:
  - tech
tags:
  - php
---

`composer install --no-dev --optimize-autoloader` should be in every deploy script. The default install command is for laptops, not servers.

Pin your PHP version in `composer.json` with `"config": {"platform": ...}`. The number of times I have debugged a production-only failure that was just a Composer mismatch is embarrassing.
