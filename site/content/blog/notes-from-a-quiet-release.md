---
title: 'Notes from a quiet release'
date: 2026-04-15
author: 'Marko Krstić'
reading_time: 4
excerpt: 'We shipped a feature this week that nobody noticed, and that was exactly the goal. A short note on the kind of release that does not call attention to itself.'
image: /uploads/media/quiet-release.jpg
cover_caption: 'Photo by Carl Heyerdahl on Unsplash.'
categories:
  - Notes
tags:
  - shipping
  - process
---

The best release I have shipped in a long time went out at half past three on a Tuesday afternoon. The pull request was thirty lines, the changelog was one bullet, and the only reaction in our team channel was a single 👍 from someone who happened to be online.

Three days later we have not had a single bug report, not a single support ticket, not a single user message. The feature is doing exactly what it was supposed to do, which is making something already-existing about ten percent faster. Nobody has stopped to admire it. That is the whole point.

## What changed

The work itself was small. We had been doing the same database call twice on a hot path because the function was called from two different layers and neither layer trusted the other. We added a caching shim — fifty lines including tests — and removed the duplicate call. p95 dropped from 240ms to 190ms.

The temptation, once the patch was in, was to write it up. Internal blog post. Demo at the all-hands. Maybe even a tweet. We did none of those things, partly because there was nothing photogenic to show, and partly because the impact was real but small, and dressing it up would have made it look like more than it was.

## Why this matters

Loud releases set an expectation that work is something you announce. Most software work is not announceable. It is a bug fix here, a small refactor there, a quietly improved error message. If your culture only celebrates the big launches, the small steady stream of improvements becomes invisible — and then undervalued.

I would rather work somewhere that ships ten quiet things a week than somewhere that throws a parade for one big thing every quarter. The quiet things are usually what actually moves the product.
