---
title: 'CSS Grid deep dive'
date: 2026-04-16
author: 'Marko Krstić'
reading_time: 7
excerpt: 'Subgrid is finally everywhere. Here is the mental model I wish I had when I started, and the three layouts I rebuild every project.'
categories:
  - tutorial
tags:
  - css
---

Grid is two systems stacked: the explicit grid you define, and the implicit grid that fills in the rest. Most bugs I help debug come from confusing the two.

Once subgrid landed in every browser, the case for nested flex containers got weaker. Lining a card's title up with its sibling card's title is now one line of CSS instead of three media queries.
