---
title: 'Modern CSS resets, revisited'
date: 2026-04-04
author: 'Marko Krstić'
reading_time: 4
excerpt: 'A short tour through the resets I have actually shipped this year, and the lines I copy-paste into every new project without thinking.'
categories:
  - tutorial
tags:
  - css
---

Resets used to be defensive — flatten everything because you could not trust the browser. In 2026 they are aspirational — set sensible defaults so you have to write less code per component.

`* { box-sizing: border-box }`, `body { line-height: 1.5 }`, and `img { max-width: 100% }` cover ninety percent of the cases. Everything else is project taste.
