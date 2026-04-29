---
title: 'PHP attributes in practice'
date: 2026-03-17
author: 'Marko Krstić'
reading_time: 5
excerpt: 'Attributes are not docblocks. The moment you treat them like a typed metadata channel, half of your container code disappears.'
categories:
  - tutorial
tags:
  - php
---

I resisted attributes for two years. They felt like Java cosplay. Then I had to wire fifteen routes by hand and caved.

The win is not the syntax — it is the reflection API. You can scan a class, find every method tagged with `#[Route]`, and register them at boot with no array of strings to maintain. The attributes become the spec.
