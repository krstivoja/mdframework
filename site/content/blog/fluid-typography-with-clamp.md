---
title: 'Fluid typography with clamp()'
date: 2026-03-14
author: 'Marko Krstić'
reading_time: 4
excerpt: 'One CSS function, three arguments, no media queries. Here is the formula I derive from scratch every time so I never forget it.'
categories:
  - tutorial
tags:
  - css
---

`clamp(min, preferred, max)` is the single best CSS addition of the last five years. Smooth scaling between two viewport sizes, no breakpoints, no JavaScript.

The trick is the preferred value: `calc(<base> + <delta> * (100vw - <minVw>) / (<maxVw> - <minVw>))`. Once you build that helper once, you stop hand-rolling responsive type forever.
