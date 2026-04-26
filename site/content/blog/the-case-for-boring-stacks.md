---
title: 'The case for boring stacks'
date: 2026-04-22
author: 'Marko Krstić'
reading_time: 6
excerpt: 'The most exciting tooling decision is usually the one nobody at the table wants to bring up. Here is why I keep choosing it anyway.'
image: /uploads/media/boring-stacks.jpg
cover_caption: 'Photo by Mike Petrucci on Unsplash.'
categories:
  - Essays
tags:
  - workflow
  - simplicity
  - tooling
---

There is a particular kind of meeting I have learned to recognise in the first thirty seconds. Someone draws a box on the whiteboard, labels it with the project name, and immediately starts proposing technologies. Within five minutes the box has sprouted four queues, two databases, an event bus, and a cache. The work itself — what the project actually has to do — has not been mentioned once.

The argument for boring stacks is not that the boring tools are better. They are usually worse, in the narrow sense that they have fewer features and less impressive performance numbers. The argument is that they fail in ways you have already thought about. You know what happens when Postgres runs out of disk. You have a runbook for a wedged cron job. You know which Stack Overflow answer is wrong.

## What you actually save

A team I worked with once spent a whole quarter migrating a small internal tool from a queue we had used for years to a streaming platform that solved a problem we did not have. The replacement was a strict superset of the original on paper. In practice it added three new failure modes, one of which only surfaced under load we never produced in staging. The week we cut over, the on-call rotation tripled.

There was nothing wrong with the new tool. It was simply the wrong shape for what we were doing. The old queue was boring; the new one was interesting. Interesting tools demand attention. Boring tools fade into the background and let you think about your domain.

## When boring becomes wrong

I do not want to overstate this. Boring is not a virtue when it papers over a real mismatch. If your data model is genuinely a graph, a relational schema will hurt you forever. If the team has six engineers and the deployment story takes a full day, you have a problem worth solving with new tooling.

The trick is to be honest about which case you are in. The default should be: stay boring until the boredom genuinely costs more than the alternative. That is a much higher bar than "we could be using X."

## The reading test

Before adopting any new piece of infrastructure, I now make myself answer one question: if a junior engineer has to debug this at 2am with no context, can they get to a useful state in under thirty minutes? Boring tools tend to pass this test because they have a generation of accumulated answers. Interesting tools rarely do.

It is a low bar. Most teams cannot clear it. Until you can, more interesting tools will not help.
