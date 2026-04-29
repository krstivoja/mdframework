---
title: 'Clean PHP architecture without the ceremony'
date: 2026-04-10
author: 'Marko Krstić'
reading_time: 6
excerpt: 'You do not need hexagons, ports, and adapters to write maintainable PHP. You need three folders and the discipline to keep them honest.'
categories:
  - tech
tags:
  - php
---

Most plugin codebases collapse under their own folder structure long before the business logic does. The fix is rarely another pattern.

I run with three folders: `Domain` for things the business cares about, `Infrastructure` for things the framework cares about, `Http` for the wire. If a file does not fit, it does not exist yet — write the thing first, place it later.
