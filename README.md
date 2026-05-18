# FrontPress Studio

Ultralight flat-file CMS built in PHP. No database. Content is Markdown files on disk; the admin is a browser UI at `/admin`.

- **Docs:** https://krstivoja.github.io/mdframework/
- **Releases:** https://github.com/krstivoja/frontpress-studio/releases

## Requirements

- PHP 8.1+
- Apache with `mod_rewrite` (or nginx with the equivalent rewrites)
- Composer (for source installs only)

## Install

### Shared hosting (zip)

Download `frontpress-studio-<version>.zip` from [Releases](https://github.com/krstivoja/frontpress-studio/releases) and unzip its contents into your domain's document root. Visit `/admin` and sign in with **`fpsadmin`** / **`fpspass`** — a persistent banner will nag you until you set a real password under **Settings → Security**.

### Source install (development)

```bash
git clone https://github.com/krstivoja/frontpress-studio.git
cd mdframework/app
composer install --working-dir=cms

# Admin SPA (React + Vite)
cd src
npm install
npm run dev    # HMR on localhost:5173 — visit /admin/ on your PHP host
npm run build  # production assets to ../admin/assets/
```

See the [full docs](https://frontpress.studio/docs) for directory layout, theming, caching, and the extending guide.

## Sponsor

FrontPress Studio is built and maintained by [Marko Krstić](https://markokrstic.com) in the open. If it saves you time, please consider sponsoring — it directly funds new features, docs, and maintenance.

- **Ko-fi:** https://ko-fi.com/dplugins
- **Buy Me a Coffee:** https://buymeacoffee.com/krstivoja
- **PayPal (one-time):** https://www.paypal.me/newinstockholm

Sponsors are credited in the changelog and on the docs site (opt-in).

## License

MIT — see [LICENSE](LICENSE).
