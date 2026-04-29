---
title: 'SVG animation basics'
date: 2026-03-05
author: 'Marko Krstić'
reading_time: 5
excerpt: 'Stroke draws, morphing paths, and why the SVG attribute model surprises every JavaScript developer at least once.'
categories:
  - tutorial
tags:
  - gsap
---

SVG animations live in two worlds at once: SMIL, CSS, and JavaScript can each move the same element, and they will fight if you let them. Pick one and commit.

For real production work I always pick GSAP. Browser support is uniform, the syntax matches the rest of my animation code, and I do not have to remember whether a property is an attribute or a style.
