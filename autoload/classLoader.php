<?php

/**
 * ClassLoader
 * Unified autoloader + route helper.
 *
 * Generates: <projectRoot>/autoload/autoload_map.php
 * Structure:
 *   [
 *     'classes' => [ FQCN => ['file' => ..., 'mtime' => ...] ],
 *     'routes'  => [ shortName => [files...] ],
 *   ]
 */
class ClassLoader {

    private static array $mapCache = [];
    private static string $basePath;
    private static array $paths;
    private static array $routeDirs;
    private static string $mapFile;
    private static bool $autoRebuild = true;

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
        self::$mapCache = is_file($mapFile) ? (require $mapFile) : self::rebuild();
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void {
        $entry = self::$mapCache['classes'][$class] ?? null;
        if ($entry && is_file($entry['file']) && filemtime($entry['file']) === $entry['mtime']) {
            require_once $entry['file'];
            return;
        }
        if (!self::$autoRebuild) {
            if ($entry && is_file($entry['file'])) {
                require_once $entry['file'];
                return;
            }
            throw new RuntimeException("Class '$class' not in autoload map (autoRebuild disabled).");
        }
        self::rebuild();
        $entry = self::$mapCache['classes'][$class] ?? null;
        if ($entry && is_file($entry['file'])) {
            require_once $entry['file'];
            return;
        }
        throw new RuntimeException("Class '$class' not found.");
    }

    private static function rebuild(): array {
        $classes = [];
        $routes = [];
        foreach (self::$paths as $path) {
            $full = self::$basePath . '/' . $path;
            if (!is_dir($full)) continue;
            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $filePath = str_replace('\\', '/', $file->getPathname());
                $defs = self::extractDefinitions($filePath);
                $isRoute = self::isRouteFile($filePath);
                foreach ($defs as $fqcn) {
                    if (isset($classes[$fqcn]) && $classes[$fqcn]['file'] !== $filePath) {
                        fwrite(STDERR, "ClassLoader: duplicate class '$fqcn' in {$classes[$fqcn]['file']} and $filePath\n");
                    }
                    $classes[$fqcn] = ['file' => $filePath, 'mtime' => filemtime($filePath)];
                    if ($isRoute) {
                        $short = strrchr($fqcn, '\\') ? substr(strrchr($fqcn, '\\'), 1) : $fqcn;
                        $routes[$short][] = $filePath;
                    }
                }
            }
        }
        ksort($classes);
        ksort($routes);
        foreach ($routes as $s => $files) {
            if (count($files) > 1) {
                fwrite(STDERR, "ClassLoader: route name '$s' resolves to multiple files: " . implode(', ', $files) . "\n");
            }
        }
        $data = ['classes' => $classes, 'routes' => $routes];
        self::writeMapFile(self::$mapFile, $data);
        self::$mapCache = $data;
        return $data;
    }

    private static function isRouteFile(string $filePath): bool {
        $segments = explode('/', $filePath);
        foreach (self::$routeDirs as $dir) {
            if (in_array($dir, $segments, true)) return true;
        }
        return false;
    }

    private static function extractDefinitions(string $file): array {
        $tokens = token_get_all(file_get_contents($file));
        $n = count($tokens);
        $defs = [];
        $namespace = '';
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            if (!is_array($t)) continue;
            if ($t[0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $n; $j++) {
                    $tj = $tokens[$j];
                    if (is_array($tj) && ($tj[0] === T_STRING || $tj[0] === T_NAME_QUALIFIED)) {
                        $namespace .= $tj[1];
                    } elseif ($tj === ';' || $tj === '{') {
                        break;
                    }
                }
                continue;
            }
            if (in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM ?? -1], true)) {
                $prev = $i > 0 ? $tokens[$i - 1] : null;
                if ($t[0] === T_CLASS && is_array($prev) && $prev[0] === T_NEW) continue;
                for ($j = $i + 1; $j < $n; $j++) {
                    $tj = $tokens[$j];
                    if (is_array($tj) && $tj[0] === T_STRING) {
                        $defs[] = ($namespace ? $namespace . '\\' : '') . $tj[1];
                        break;
                    }
                    if ($tj === '{') break;
                }
            }
        }
        return $defs;
    }

    private static function writeMapFile(string $file, array $data): void {
        $tmp = $file . '.tmp.' . getmypid();
        $contents = "<?php\n// Auto-generated. Do not edit.\n// " . date('Y-m-d H:i:s') . "\nreturn " . var_export($data, true) . ";\n";
        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Failed writing $tmp");
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
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
            if (basename($dir) === $name) return $dir;
            $parent = dirname($dir);
            if ($parent === $dir) break;
            $dir = $parent;
        }
        throw new RuntimeException("Project root '$name' not found above: $startDir");
    }

    public static function getMap(): array { return self::$mapCache; }
    public static function getRoutes(): array { return self::$mapCache['routes'] ?? []; }

    /**
     * Load route file by short class name. Returns true if loaded.
     */
    public static function loadRoute(string $shortName): bool {
        $routes = self::$mapCache['routes'] ?? [];
        if (!isset($routes[$shortName]) && self::$autoRebuild) {
            self::rebuild();
            $routes = self::$mapCache['routes'];
        }
        $files = $routes[$shortName] ?? [];
        if (!$files) return false;
        $file = $files[0];
        if (!is_file($file)) return false;
        require_once $file;
        return true;
    }
}
$paths = [
    'server',
    'phpClient'
];

ClassLoader::load('phpWebSocketServer', $paths);

