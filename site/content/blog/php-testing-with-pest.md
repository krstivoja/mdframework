---
title: 'PHP testing with Pest'
date: 2026-02-27
author: 'Marko Krstić'
reading_time: 6
excerpt: 'Pest is PHPUnit with the ceremony removed. Here is how I migrated a five-year-old test suite without a single test getting rewritten.'
categories:
  - tech
tags:
  - php
---

PHPUnit is fine. I just write more tests when the syntax fits on one line, and Pest fits on one line.

The migration is mechanical: install Pest, run `pest:init`, point it at the existing PHPUnit suite. Nothing breaks. New tests use the cleaner DSL, old tests keep working. Migrations should always feel like this.
