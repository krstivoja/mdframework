<?php

declare(strict_types=1);

namespace MD;

/**
 * Renders Vite dev or production asset tags for the React admin shell.
 *
 * Dev mode is detected by the presence of `.vite-hot` in the cms root,
 * which the Vite dev server writes on `listening` and removes on shutdown.
 */
class Vite
{
    private string $hotFile;
    private string $manifestFile;
    private string $publicBase;

    public function __construct(string $cmsRoot, string $publicCmsRoot, string $publicBase = '/cms/dist/')
    {
        $this->hotFile      = $cmsRoot . '/.vite-hot';
        $this->manifestFile = $publicCmsRoot . '/dist/.vite/manifest.json';
        $this->publicBase   = rtrim($publicBase, '/') . '/';
    }

    public function isDev(): bool
    {
        return is_file($this->hotFile);
    }

    public function tags(string $entry): string
    {
        if ($this->isDev()) {
            $base = rtrim((string)file_get_contents($this->hotFile), "\r\n ");
            if ($base === '') {
                $base = 'http://localhost:5173';
            }
            $b = htmlspecialchars($base, ENT_QUOTES);
            $e = htmlspecialchars($entry, ENT_QUOTES);
            // React Fast Refresh preamble — required when the HTML is not
            // served through Vite's transformIndexHtml (e.g. when PHP renders
            // the shell). Must run before any JSX module loads.
            return '<script type="module" src="' . $b . '/@vite/client"></script>'
                . '<script type="module">'
                . 'import RefreshRuntime from "' . $b . '/@react-refresh";'
                . 'RefreshRuntime.injectIntoGlobalHook(window);'
                . 'window.$RefreshReg$ = () => {};'
                . 'window.$RefreshSig$ = () => (type) => type;'
                . 'window.__vite_plugin_react_preamble_installed__ = true;'
                . '</script>'
                . '<script type="module" src="' . $b . '/' . $e . '"></script>';
        }

        if (!is_file($this->manifestFile)) {
            return '<!-- vite manifest missing — run `npm run build` in app/cms -->';
        }
        $manifest = json_decode((string)file_get_contents($this->manifestFile), true);
        if (!is_array($manifest) || !isset($manifest[$entry])) {
            return '<!-- vite entry "' . htmlspecialchars($entry, ENT_QUOTES) . '" not found in manifest -->';
        }
        $item = $manifest[$entry];
        $tags = '';
        foreach ($item['css'] ?? [] as $css) {
            $tags .= '<link rel="stylesheet" href="' . htmlspecialchars($this->publicBase . $css, ENT_QUOTES) . '">';
        }
        $tags .= '<script type="module" src="' . htmlspecialchars($this->publicBase . $item['file'], ENT_QUOTES) . '"></script>';
        return $tags;
    }
}
