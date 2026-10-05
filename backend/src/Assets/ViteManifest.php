<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 10 — Vite Manifest Reader | Added: 2026-04-03

namespace Nemesis\Assets;

/**
 * Reads Vite's manifest.json and resolves hashed asset URLs.
 *
 * Vite 5+ writes the manifest to:
 *   public/build/.vite/manifest.json
 *
 * Manifest structure:
 *   {
 *     "resources/js/app.js": {
 *       "file": "assets/app-3a7b92f.js",
 *       "css": ["assets/app-1a2b3c4.css"],
 *       "isEntry": true
 *     }
 *   }
 */
class ViteManifest implements ManifestInterface
{
    private array  $manifest  = [];
    private bool   $loaded    = false;
    private string $buildPath = '/build';

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $devUrl    = 'http://localhost:5173',
        private readonly string $hotFile   = '',
    ) {
        $this->load();
    }

    public function loaded(): bool
    {
        return $this->loaded;
    }

    public function all(): array
    {
        $out = [];
        foreach ($this->manifest as $src => $entry) {
            $out[$src] = $this->buildPath . '/' . ($entry['file'] ?? $src);
        }
        return $out;
    }

    /**
     * Resolve a source path to its versioned public URL.
     *
     * If the Vite dev server is running (hot file present), return the
     * HMR URL so edits reload instantly without a full page refresh.
     */
    public function url(string $path): string
    {
        // Dev-server / HMR mode
        if ($this->isHot()) {
            return rtrim($this->devUrl, '/') . '/' . ltrim($path, '/');
        }

        // Strip leading "resources/" if caller passed the full source path
        $key = $path;

        if (isset($this->manifest[$key])) {
            $file = $this->manifest[$key]['file'] ?? $key;
            return $this->buildPath . '/' . $file;
        }

        // Fallback: return the path as-is under /build
        return $this->buildPath . '/' . ltrim($path, '/');
    }

    /**
     * Generate the full <script type="module"> + <link rel="stylesheet"> HTML
     * for an entry point, including any imported CSS chunks.
     *
     * In HMR mode: emits the Vite client script first.
     */
    public function tags(string $path): string
    {
        $isCssEntry = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'css';

        if ($this->isHot()) {
            $base = rtrim($this->devUrl, '/');
            $tags = ['<script type="module" src="' . $base . '/@vite/client"></script>'];
            $url = $base . '/' . ltrim($path, '/');
            $tags[] = $isCssEntry
                ? '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES) . '">'
                : '<script type="module" src="' . htmlspecialchars($url, ENT_QUOTES) . '"></script>';
            return implode("\n", $tags);
        }

        $tags = [];
        $key  = $path;

        if (!isset($this->manifest[$key])) {
            return '<!-- Vite: entry "' . htmlspecialchars($key, ENT_QUOTES) . '" not in manifest -->';
        }

        $entry = $this->manifest[$key];

        $entryFile = (string) ($entry['file'] ?? $key);
        $isCssEntry = $isCssEntry || strtolower(pathinfo($entryFile, PATHINFO_EXTENSION)) === 'css';

        if ($isCssEntry) {
            return '<link rel="stylesheet" href="' . $this->buildPath . '/' . htmlspecialchars($entryFile, ENT_QUOTES) . '">';
        }

        // CSS chunks imported by this entry
        foreach ($entry['css'] ?? [] as $cssFile) {
            $tags[] = '<link rel="stylesheet" href="' . $this->buildPath . '/' . $cssFile . '">';
        }

        // The JS entry itself
        $tags[] = '<script type="module" src="' . $this->buildPath . '/' . htmlspecialchars($entryFile, ENT_QUOTES) . '"></script>';

        return implode("\n", $tags);
    }

    // -------------------------------------------------------------------------

    private function load(): void
    {
        if (!file_exists($this->manifestPath)) {
            return;
        }

        $raw = file_get_contents($this->manifestPath);
        if ($raw === false) return;

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return;

        $this->manifest = $decoded;
        $this->loaded   = true;

        // Derive buildPath from the manifest file location
        // e.g. public/build/.vite/manifest.json → /build
        $dir = dirname($this->manifestPath);          // public/build/.vite
        $dir = dirname($dir);                         // public/build
        $rel = ltrim(str_replace(
            str_replace('/', DIRECTORY_SEPARATOR, realpath(getcwd()) ?: getcwd()),
            '',
            realpath($dir) ?: $dir
        ), DIRECTORY_SEPARATOR . '/');

        // Remove the "public" prefix so URLs are web-relative
        $this->buildPath = '/' . trim(str_replace('public/', '', $rel), '/');
    }

    private function isHot(): bool
    {
        if ($this->hotFile !== '' && file_exists($this->hotFile)) {
            return true;
        }
        return false;
    }
}
