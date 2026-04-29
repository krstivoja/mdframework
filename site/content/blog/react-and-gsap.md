---
title: 'React and GSAP, without the cleanup bugs'
date: 2026-03-20
author: 'Marko Krstić'
reading_time: 6
excerpt: 'The useGSAP hook turns "why is my animation playing twice" into "why is this so quiet now". Here is what changed and why.'
categories:
  - tech
tags:
  - gsap
---

Before `useGSAP`, every React + GSAP codebase I touched had the same bug: tweens that survived unmount and replayed on the next render. The fix was always the same — a careful `useEffect` cleanup that nobody remembered to write.

`useGSAP` makes the cleanup the default. You can still leak, but you have to try.
