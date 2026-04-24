/* global React */
// Content pages — Home (welcome), Archive (blog list), Post (single), NotFound.
// Matches the archive.php + post.php + page.php templates in site/themes/default.

const POSTS = [
  { slug: "hello-world",           title: "Hello World",              date: "2026-04-22", tags: ["meta"],
    excerpt: "Kicking off the blog with a few notes on why a flat-file CMS still matters in 2026." },
  { slug: "on-ultralight-php",     title: "On Ultralight PHP",        date: "2026-04-15", tags: ["php", "craft"],
    excerpt: "Why the MD Framework core fits in a single file — and why that's not a stunt." },
  { slug: "markdown-primer",       title: "A markdown primer",        date: "2026-04-02", tags: ["design"],
    excerpt: "Frontmatter, tables, task lists, code fences. Enough to stop googling the syntax." },
  { slug: "caching-deep-dive",     title: "Caching deep-dive",        date: "2026-03-20", tags: ["php", "perf"],
    excerpt: "How the O(1) mtime marker lets the site skip the directory walk entirely." },
];

function HomePage() {
  return (
    <>
      <h1>Welcome</h1>
      <p>
        This is the homepage. It's a static markdown file at <code>content/pages/index.md</code>.
      </p>
      <p>
        Visit <a href="#">the blog</a> for recent posts. Or read <a href="#">about the author</a>.
      </p>
      <h2>Recently</h2>
      {POSTS.slice(0, 3).map(p => (
        <article key={p.slug}>
          <h2><a href="#">{p.title}</a></h2>
          <div className="meta">
            <time>{p.date}</time>
            {p.tags.map(t => <a key={t} className="tag" href="#">{t}</a>)}
          </div>
          <p>{p.excerpt}</p>
        </article>
      ))}
    </>
  );
}

function BlogArchive({ onOpen }) {
  return (
    <>
      <h1>Blog</h1>
      {POSTS.map(p => (
        <article key={p.slug}>
          <h2><a onClick={() => onOpen(p)}>{p.title}</a></h2>
          <div className="meta">
            <time>{p.date}</time>
            {p.tags.map(t => <a key={t} className="tag" href="#">{t}</a>)}
          </div>
          <p>{p.excerpt}</p>
        </article>
      ))}
      <nav className="pagination">
        <a href="#">&larr; Prev</a>
        <span>Page 1 of 2</span>
        <a href="#">Next &rarr;</a>
      </nav>
    </>
  );
}

function PostPage({ post, onBack }) {
  return (
    <article>
      <h1>{post.title}</h1>
      <div className="meta">
        <time>{post.date}</time>
        {post.tags.map(t => <span key={t} className="tag">{t}</span>)}
      </div>

      <p>
        A flat-file CMS is a weird thing to ship in 2026 — and yet. The install is a folder copy.
        The backup is a zip. The content is legible without the app. When everything else is managed,
        the appeal of a thing you can read with <code>cat</code> gets louder.
      </p>

      <blockquote>
        "The best content store is the one you can grep."
      </blockquote>

      <h2>Why Markdown on disk</h2>
      <p>
        No database means no migrations, no schema churn, no 400-line docker-compose. Content is
        Markdown with frontmatter, stored under <code>content/</code>. A change is a diff.
      </p>

      <pre><code>{`---
title: Hello World
date: 2026-04-22
tags: [meta]
---

This is the first post.`}</code></pre>

      <h2>What you lose</h2>
      <p>
        Real-time collaboration. Heavy permission models. Anything that depends on transactional
        writes from many authors at once. For a one- or two-person blog, that list is fine.
      </p>

      <p>
        <a onClick={onBack}>← Back to all posts</a>
      </p>
    </article>
  );
}

function AboutPage() {
  return (
    <>
      <h1>About</h1>
      <p>
        This site runs on <a href="https://github.com/krstivoja/mdframework">MD Framework</a>, an
        ultralight flat-file CMS written in PHP. Content is Markdown on disk. No database, no build step.
      </p>
      <p>
        It's maintained by one person, in their spare time, when the fancy strikes.
      </p>
      <img alt="A quiet desk" src="../../assets/sample-about.png" />
      <p>
        Questions or notes? Reach out over email.
      </p>
    </>
  );
}

function NotFoundPage() {
  return (
    <div className="not-found">
      <h1>404</h1>
      <p>Page not found.</p>
      <p><a href="#">← Home</a></p>
    </div>
  );
}

Object.assign(window, { HomePage, BlogArchive, PostPage, AboutPage, NotFoundPage, POSTS });
