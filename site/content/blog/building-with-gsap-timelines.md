---
title: 'Building with GSAP timelines'
date: 2026-04-22
author: 'Marko Krstić'
reading_time: 5
excerpt: 'Why I reach for timelines almost every time, and the position parameter trick that finally made them click for me.'
categories:
  - tech
tags:
  - gsap
---

The position parameter is the whole game. Once you stop chaining `.to()` calls and start placing tweens at named labels, choreography stops feeling like a fight.

A timeline is a tiny scheduler. Treat it like one. Name your labels, group your sequences, and reuse the same timeline for play, pause, and reverse.
