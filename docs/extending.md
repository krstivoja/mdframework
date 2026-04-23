---
title: Extending
layout: default
---

# Extending

**Add a new collection** — create a folder under `content/` and add `.md` files. It immediately gets a `/folder` archive and `/folder/slug` post routes. No configuration needed.

**Add front matter fields** — add any key to a file's YAML block. It's available in `$meta` inside templates and indexed automatically for filtering with `posts(['your_field' => 'value'])`.

**Custom templates** — add a `template: my-template` front matter field, then in `public/index.php` switch on `$data['meta']['template']` before calling `render()`.
