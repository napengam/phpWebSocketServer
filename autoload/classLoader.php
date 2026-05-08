<?php

/**
 * ClassLoader
 * Unified autoloader + route helper.
 *
 * Generates: <projectRoot>/autoload/autoload_map.php
 * Structure:
 *   [
 *     'classes' => [ FQCN => file ],
 *     'routes'  => [ shortName => [files...] ],
 *   ]
 *
 * Validation strategy:
 *   - Production (autoRebuild = false): map is trusted, no FS checks at runtime.
 *   - Development (autoRebuild = true): once per request, max-mtime of all
 *     source dirs is compared against map mtime. If stale -> full rebuild.
 */
class ClassLoader {

    private static array $mapCache = [];
    private static string $basePath;
    private static array $paths;
    private static array $routeDirs;
    private static string $mapFile;
    private static bool $autoRebuild = true;
    private static bool $staleChecked = false;

    public static function load(
            string $projectRootName,
            array $paths,
            array $routeDirs = ['GUI', 'Api', 'Controller'],
            bool $autoRebuild = true,
            ?string $startDir = null
    ): void {
        $startDir = $startDir ?? self::detectStartDir();
        $basePath = self::findProjectRoot($projectRootName, $startDir);
        $autoloadDir = $basePath . '/autoload';
        $mapFile = $autoloadDir . '/autoload_map.php';
        if (!is_dir($autoloadDir) && !mkdir($autoloadDir, 0775, true) && !is_dir($autoloadDir)) {
            throw new RuntimeException("Cannot create autoload dir: $autoloadDir");
        }
        self::$basePath = $basePath;
        self::$paths = $paths;
        self::$routeDirs = $routeDirs;
        self::$mapFile = $mapFile;
        self::$autoRebuild = $autoRebuild;
        if (is_file($mapFile)) {
            self::$mapCache = require $mapFile;
        } else {
            self::rebuild();
        }
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void {
        self::ensureFresh();
        $file = self::$mapCache['classes'][$class] ?? null;
        if ($file && is_file($file)) {
            require_once $file;
            return;
        }
        if (self::$autoRebuild) {
            self::rebuild();
            $file = self::$mapCache['classes'][$class] ?? null;
            if ($file && is_file($file)) {
                require_once $file;
                return;
            }
        }
        throw new RuntimeException("Class '$class' not found.");
    }

    /**
     * Once per request in dev mode: check if map is stale and rebuild if so.
     */
    private static function ensureFresh(): void {
        if (!self::$autoRebuild || self::$staleChecked) {
            return;
        }
        self::$staleChecked = true;
        if (self::isMapStale()) {
            self::rebuild();
        }
    }

    private static function isMapStale(): bool {
        if (!is_file(self::$mapFile)) {
            return true;
        }
        $mapMtime = filemtime(self::$mapFile);
        foreach (self::$paths as $p) {
            $dir = self::$basePath . '/' . $p;
            if (!is_dir($dir)) {
                continue;
            }
            $rii = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($rii as $f) {
                if ($f->isFile() && $f->getExtension() === 'php' && $f->getMTime() > $mapMtime) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function rebuild(): array {
        $classes = [];
        $routes = [];
        foreach (self::$paths as $path) {
            $dir = self::$basePath . '/' . $path;
            if (!is_dir($dir)) {
                continue;
            }
            $rii = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $filePath = $file->getPathname();
                $defs = self::extractDefinitions($filePath);
                foreach ($defs as $fqcn => $shortName) {
                    if (isset($classes[$fqcn]) && $classes[$fqcn] !== $filePath) {
                        fwrite(STDERR, "ClassLoader: duplicate class '$fqcn' in {$classes[$fqcn]} and $filePath\n");
                    }
                    $classes[$fqcn] = $filePath;
                    if (self::isRouteFile($filePath)) {
                        $routes[$shortName][] = $filePath;
                    }
                }
            }
        }
        foreach ($routes as $short => $files) {
            if (count($files) > 1) {
                fwrite(STDERR, "ClassLoader: route short name '$short' is ambiguous: " . implode(', ', $files) . "\n");
            }
        }
        $map = ['classes' => $classes, 'routes' => $routes];
        self::writeMapFile(self::$mapFile, $map);
        self::$mapCache = $map;
        return $map;
    }

    private static function isRouteFile(string $filePath): bool {
        $rel = str_replace('\\', '/', substr($filePath, strlen(self::$basePath) + 1));
        $segments = explode('/', $rel);
        foreach (self::$routeDirs as $rd) {
            if (in_array($rd, $segments, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract namespace + class/interface/trait/enum definitions from a file.
     * Returns [FQCN => shortName].
     */
    private static function extractDefinitions(string $file): array {
        $code = file_get_contents($file);
        if ($code === false) {
            return [];
        }
        $tokens = token_get_all($code);
        $count = count($tokens);
        $namespace = '';
        $defs = [];
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (!is_array($t)) {
                continue;
            }
            if ($t[0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    $tj = $tokens[$j];
                    if ($tj === ';' || $tj === '{') {
                        break;
                    }
                    if (is_array($tj)) {
                        if ($tj[0] === T_STRING || $tj[0] === T_NS_SEPARATOR || (defined('T_NAME_QUALIFIED') && $tj[0] === T_NAME_QUALIFIED)) {
                            $namespace .= $tj[1];
                        }
                    }
                }
                continue;
            }
            if ($t[0] === T_CLASS || $t[0] === T_INTERFACE || $t[0] === T_TRAIT || (defined('T_ENUM') && $t[0] === T_ENUM)) {
                // Skip anonymous class: T_CLASS preceded by T_NEW
                $prev = $i - 1;
                while ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $prev--;
                }
                if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_NEW) {
                    continue;
                }
                for ($j = $i + 1; $j < $count; $j++) {
                    $tj = $tokens[$j];
                    if (is_array($tj) && $tj[0] === T_STRING) {
                        $short = $tj[1];
                        $fqcn = $namespace !== '' ? $namespace . '\\' . $short : $short;
                        $defs[$fqcn] = $short;
                        break;
                    }
                }
            }
        }
        return $defs;
    }

    private static function writeMapFile(string $file, array $data): void {
        $content = "<?php\n// Auto-generated autoload map. Do not edit.\n"
                . "// Generated: " . date('Y-m-d H:i:s') . "\n"
                . "return " . var_export($data, true) . ";\n";
        $tmp = $file . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new RuntimeException("Failed writing $tmp");
        }
        if (!rename($tmp, $file)) {
            if (is_file($tmp) && !unlink($tmp)) {
                throw new RuntimeException("Cannot remove tmp file: $tmp");
            }
            throw new RuntimeException("Failed renaming $tmp -> $file");
        }
    }

    private static function detectStartDir(): string {
        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            return dirname(realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']);
        }
        if (!empty($_SERVER['argv'][0])) {
            $s = $_SERVER['argv'][0];
            return dirname(realpath($s) ?: $s);
        }
        return getcwd() ?: __DIR__;
    }

    private static function findProjectRoot(string $name, string $startDir): string {
        $dir = $startDir;
        for ($i = 0; $i < 20; $i++) {
            if (basename($dir) === $name) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        throw new RuntimeException("Project root '$name' not found above: $startDir");
    }

    public static function getMap(): array {
        return self::$mapCache;
    }

    public static function getRoutes(): array {
        return self::$mapCache['routes'] ?? [];
    }

    /**
     * Load route file by short class name. Returns true if loaded.
     */
    public static function loadRoute(string $shortName): bool {
        self::ensureFresh();
        $routes = self::$mapCache['routes'] ?? [];
        if (!isset($routes[$shortName]) && self::$autoRebuild) {
            self::rebuild();
            $routes = self::$mapCache['routes'];
        }
        $files = $routes[$shortName] ?? [];
        if (!$files) {
            return false;
        }
        $file = $files[0];
        if (!is_file($file)) {
            return false;
        }
        require_once $file;
        return true;
    }
}

$paths = [
    'server',
    'phpClient'
];

ClassLoader::load('phpWebSocketServer', $paths);

