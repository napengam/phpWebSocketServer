<?php

/**
 * Dynamically loads classes from specified directories.
 * Builds and updates class map when missing classes are found.
 * 
 * @requires explicit $paths input
 */
class ClassLoader {

    public static function load(string $projectFolder, array $paths): void {
        $baseDir = dirname(__DIR__);
        $basePath = self::findBasePath($baseDir, $projectFolder);

        if (!$basePath) {
            throw new Exception("Base path containing '{$projectFolder}' not found.");
        }

        $autoloadDir = $basePath . '/autoload';
        $classMapFile = $autoloadDir . '/classmap.php';

        if (!is_dir($autoloadDir)) {
            mkdir($autoloadDir, 0775, true);
        }

        // Load or create class map
        $classMap = file_exists($classMapFile) ? require $classMapFile : self::fillClassMapOnce($basePath, $paths, $classMapFile);

        // Register autoloader
        spl_autoload_register(function ($class) use ($basePath, $paths, &$classMap, $classMapFile) {
            if (isset($classMap[$class]) && file_exists($classMap[$class])) {
                require_once $classMap[$class];
                return;
            }

            $classMap = self::fillClassMapOnce($basePath, $paths, $classMapFile);
            if (isset($classMap[$class])) {
                require_once $classMap[$class];
                return;
            }
            throw new Exception("Class $class not found.");
        });
    }

    private static function fillClassMapOnce(string $basePath, array $paths, string $classMapFile): array {
        $classMap = [];

        foreach ($paths as $path) {
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$basePath/$path"));
            foreach ($rii as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $filename = basename($file->getFilename(), '.php');
                $classMap[$filename] = str_replace('\\', '/', $file->getPathname());
            }
        }

        file_put_contents($classMapFile, "<?php\n\n" .
                "// Auto-generated class map. Do not edit manually.\n" .
                "// Generated on: " . date('Y-m-d H:i:s') . "\n\n" .
                "return " . var_export($classMap, true) . ";\n");

        return $classMap;
    }

    private static function findBasePath(string $startPath, string $targetFolder): ?string {
        $parts = explode(DIRECTORY_SEPARATOR, $startPath);
        $path = [];

        foreach ($parts as $part) {
            $path[] = $part;
            if ($part === $targetFolder) {
                return implode(DIRECTORY_SEPARATOR, $path);
            }
        }

        return null;
    }
}

/*
 * ***********************************************
 * where to look below given dir for class files
 * **********************************************
 */
$paths = [
    'server',
    'phpClient'
];

ClassLoader::load('phpWebSocketServer', $paths);

