---
title: 'A small build pipeline for plain HTML'
date: 2026-03-28
author: 'Marko Krstić'
reading_time: 7
excerpt: 'You can do a surprising amount with a folder of HTML files, a tiny shell script, and rsync. Here is the setup I keep coming back to for small sites.'
image: /uploads/media/plain-html.jpg
cover_caption: 'Photo by Florian Olivo on Unsplash.'
categories:
  - Howto
tags:
  - web
  - tooling
  - simplicity
---

Whenever I have a site that does not need a CMS — a documentation page, a small portfolio, a one-off campaign — I reach for the same approximate setup. It has been roughly the same for ten years. Each time I revisit it I expect to find something obviously better; each time I conclude that the current arrangement is fine and adopt the latest incremental improvement.

Here is what it looks like today.

## The folder

```
site/
├── src/
│   ├── _layout.html
│   ├── _head.html
│   ├── index.html
│   └── about.html
├── public/
│   ├── style.css
│   └── img/
└── build.sh
```

`src/` holds the pages. Files starting with an underscore are partials, never copied to the output directly; they are only meant to be included by other files. `public/` is the static assets you do not want to template. `build.sh` is forty lines of shell.

## The build script

The script does three things, in order: it walks `src/` for non-underscore HTML files, expands `<!-- include _file.html -->` directives via a single sed substitution, and writes the result to `dist/`. It then rsyncs `public/` over `dist/` so assets land alongside the pages.

That is it. No webpack, no postcss, no node_modules. The whole thing runs in under half a second on every save, because there is almost nothing to do.

## Why this works

The thing that surprises people about this setup is that you do not lose much by skipping a "real" build tool. Modern browsers handle CSS variables, custom properties, nested selectors, and module imports natively. Most of the things a build pipeline used to do for you — autoprefixing, polyfills, minifying — are either unnecessary now or solvable with a single binary you run once.

You also get a few real benefits:

- The output is exactly what is in your editor. Debugging is reading.
- You can hand the folder to anyone — a designer, a junior developer, a future you — and they can edit it without learning your toolchain.
- The site keeps working forever. There is no `npm install` that breaks in three years because a transitive dependency disappeared.

## When you outgrow it

The setup breaks down when you have shared data — a blog with twenty posts, say, or a docs site with a sidebar that needs to know about every page. At that point you want a real templating engine and an index step. But that is a higher bar than people usually assume. A site with ten pages and no shared data does not need it.

## The takeaway

The default for small sites should be small tools. Reach for the static site generator when you have an actual reason. Until then, a folder, a shell script, and rsync are doing more for you than you give them credit for.
