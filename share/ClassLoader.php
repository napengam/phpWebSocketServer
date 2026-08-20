<?php

/**
 * ClassLoader
 * -----------
 * Unified autoloader and route helper for a PHP project.
 *
 * Responsibilities:
 *   - Scans configured source folders for PHP files.
 *   - Extracts fully-qualified class/interface/trait names via token parsing.
 *   - Builds a single autoload map file: /autoload/autoload_map.php
 *     Structure:
 *       [
 *         'classes' => [ FQCN => ['file' => string, 'mtime' => int] ],
 *         'routes'  => [ shortClassName => filePath ]
 *       ]
 *   - Registers an autoloader that:
 *       * Loads classes based on the generated map.
 *       * Verifies file modification times and rebuilds the map if entries are outdated.
 *   - Provides a simple routing helper by mapping "short" class names
 *     (basename of the FQCN) to files located in /GUI/ or /Api/ directories.
 *
 * Project root detection
 * ----------------------
 * The project root is determined by walking upwards from the start directory
 * until we find a directory named 'auatoload' on the highest level
 *
 * Requirements:
 *   - $anchor must exist directly inside the project root, e.g.:
 *         /project_root/autoload/
 *   - If no ancestor directory contains $anchor as an immediate child,
 *     detection fails and an exception is thrown.
 *
 * This approach avoids issues with symlinked script paths by relying on
 * a logical "anchor" artifact inside the project root.
 *
 * Usage:
 *   ClassLoader::load($anchor, $paths);
 *   - $anchor: name of a file or directory that exists inside the project root.
 *   - $paths: array of relative source paths to scan (e.g. ['src', 'lib']).
 */
class ClassLoader {

    /** Unified in-memory cache of the loaded map */
    private static array $mapCache = [];
    private static string $basePath;
    private static array $paths;
    private static string $mapFile;

    /**
     * Initialize the autoloader.
     */
    public static function load(array $paths): void {
        if (!empty(self::$mapCache)) {
            return; // already initialized (cache)
        }

        // robust start dir (CLI + web)
        $start = $_SERVER['SCRIPT_FILENAME'] ?? getcwd();
        $startDir = is_file($start) ? dirname($start) : $start;

        // symlink-safe
        $startDir = realpath($startDir) ?: $startDir;

        $basePath = self::findProjectFolder($startDir, 'autoload');

        if (!$basePath) {
            throw new Exception("Base path containing 'autolad' not found.");
        }

        $autoloadDir = $basePath . '/autoload';
        $mapFile = $autoloadDir . '/autoload_map.php';

        self::$basePath = $basePath;
        self::$paths = $paths;
        self::$mapFile = $mapFile;

        if (!is_dir($autoloadDir)) {
            mkdir($autoloadDir, 0775, true);
        }

        // load or build map
        $map = is_file($mapFile) ? require $mapFile : self::buildAutoloadMap($basePath, $paths, $mapFile);

        self::$mapCache = $map;
        $classMap = $map['classes'];

        spl_autoload_register(function ($class) use (&$classMap, $basePath, $paths, $mapFile) {
            $entry = $classMap[$class] ?? null;

            $full = $basePath . '/' . $entry;
            if ($entry && is_file($full)) {
                require_once $full;
                return;
            }

            $short = basename(str_replace('\\', '/', $class));
            $entry = $classMap[$short] ?? null;
            if ($entry && is_file($entry)) {
                require_once $entry;
                return;
            }
            return;
        });
    }

    /**
     * Build unified map and write to disk.
     */
    private static function buildAutoloadMap(string $basePath, array $paths, string $mapFile): array {
        $classes = [];
        $routes = [];
        $cpaths = $paths;
        $guiRoutes = [];
        if (self::isMultiDimensional($paths)) {
            $cpaths = $paths['classes'];
            $guiRoutes = $paths['routes'];
        }
        $seenFiles = []; // ✅ prevent duplicate processing
        $shortIndex = []; // ✅ track short-name conflicts
        foreach ($cpaths as $path) {
            $fullPath = realpath("$basePath/$path");
            if (!$fullPath || !is_dir($fullPath)) {
                throw new Exception("Invalid path: {$path}");
            }
            $rii = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $realPath = $file->getRealPath();
                $normalizedBase = rtrim(str_replace('\\', '/', realpath($basePath)), '/');
                $normalizedFile = str_replace('\\', '/', $realPath);
                // make relative
                $filePath = ltrim(str_replace($normalizedBase, '', $normalizedFile), '/');
                // skip duplicate files (symlinks etc.)
                if (isset($seenFiles[$realPath])) {
                    continue;
                }
                $seenFiles[$realPath] = true;
                $defs = self::extractDefinitions($realPath);
                foreach ($defs as $def) {
                    // HARD FAIL on duplicate FQCN
                    if (isset($classes[$def])) {
                        throw new Exception(
                                        "Duplicate class '{$def}' found in:\n" .
                                        "- {$classes[$def]}\n" .
                                        "- {$filePath}"
                                );
                    }
                    $classes[$def] = $filePath;
                    // short name handling (safe)
                    $short = basename(str_replace('\\', '/', $def));
                    if (!isset($shortIndex[$short])) {
                        $shortIndex[$short] = $def;
                    } else {
                        // conflict → remove both from short mapping
                        unset($routes[$short]);
                        $shortIndex[$short] = false;
                    }
                    // router mapping (only if not conflicted)
                    if ($shortIndex[$short] !== false) {
                        $found = array_filter($guiRoutes, function ($p) use ($filePath) {
                            return strpos($filePath, $p) !== false;
                        });
                        if ($found) {
                            $routes[$short] = $filePath;
                        }
                    }
                }
            }
        }
        //  sanity check
        if (empty($classes)) {
            throw new Exception("Autoload map is empty — no classes found.");
        }
        ksort($classes);
        ksort($routes);
        $data = [
            'classes' => $classes,
            'routes' => $routes,
        ];
        self::writeMapFile($mapFile, $data);
        return $data;
    }

    private static function isMultiDimensional(array $array): bool {
        foreach ($array as $value) {
            if (is_array($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extracts PHP class/interface/trait names from a file.
     */
    private static function extractDefinitions(string $file): array {
        if (!is_file($file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
            return [];
        }

        $contents = file_get_contents($file);
        $tokens = token_get_all($contents);

        $defs = [];
        $namespace = '';

        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {

            if (!is_array($tokens[$i])) {
                continue;
            }

            // -------------------------
            // NAMESPACE
            // -------------------------
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                $i++;

                while (isset($tokens[$i]) && is_array($tokens[$i]) &&
                in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)
                ) {
                    $namespace .= $tokens[$i][1];
                    $i++;
                }
            }

            // -------------------------
            // CLASS / INTERFACE / TRAIT / ENUM
            // -------------------------
            if (in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {

                // skip anonymous class
                $prev = $tokens[$i - 1] ?? null;
                if ($tokens[$i][0] === T_CLASS && is_array($prev) && $prev[0] === T_NEW) {
                    continue;
                }

                $i++;

                // skip whitespace
                while (isset($tokens[$i]) && is_array($tokens[$i]) &&
                $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }

                if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $name = $tokens[$i][1];

                    $defs[] = $namespace ? $namespace . '\\' . $name : $name;
                }
            }
        }

        return $defs;
    }

    /**
     * Writes the unified autoload map file.
     */
    private static function writeMapFile(string $file, array $data): void {
        $tmpFile = $file . '.tmp';

        $content = "<?php\n\n" .
                "// Auto-generated combined autoload map. Do not edit manually.\n" .
                "// Generated on: " . date('Y-m-d H:i:s') . "\n\n" .
                "return " . var_export($data, true) . ";\n";

        // ensure directory exists
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // write complete file first
        file_put_contents($tmpFile, $content, LOCK_EX);

        // atomic swap
        rename($tmpFile, $file);
    }

    /*
     * ***********************************************
     * $anchor must be a file or directory just one 
     * below project directory 
     * **********************************************
     */

    private static function findProjectFolder(string $startDir, string $anchor): string {
        $dir = $startDir;
        $found = null;

        while ($dir !== dirname($dir)) {

            // resolve real path (symlink-safe)
            $realDir = realpath($dir) ?: $dir;

            $path = $realDir . DIRECTORY_SEPARATOR . $anchor;

            if (file_exists($path)) {
                // keep updating → highest wins
                $found = $realDir;
            }

            $dir = dirname($dir);
        }

        if ($found !== null) {
            return $found;
        }

        throw new Exception("Project root containing '{$anchor}' not found.");
    }

    // -------------------------------
    // 🔹 Public helper methods
    // -------------------------------

    public static function getMap(): array {
        return self::$mapCache;
    }

    public static function getRoutes(): array {
        return self::$mapCache['routes'] ?? [];
    }

    public static function getFileHash(string $relativePath): ?string {
        return self::$mapCache['hashes'][$relativePath] ?? null;
    }

    /**
     * Dynamically instantiate a controller by short name
     * Example: ClassLoader::createRoute('DashboardController');
     */
    public static function createRoute(string $shortName): bool {
        $routes = self::$mapCache['routes'] ?? [];

        if (!isset($routes[$shortName])) {
            // ?Rebuild route map if missing
            $map = self::buildAutoloadMap(self::$basePath, self::$paths, self::$mapFile);
            self::$mapCache = $map;
            $routes = $map['routes'] ?? [];

            if (!isset($routes[$shortName])) {
                return false; // still not found
            }
        }

        $file = $routes[$shortName] ?? null;
        if ($file && is_file($file)) {
            require_once $file;
            return true;
        }

        return false;
    }
}
