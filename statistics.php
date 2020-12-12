<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
        <?php
        $path = getcwd();
        walkDir($path);
        echo "<h2>Path ;  $path</h2><p>";
        echo 'Number of <b>Files</b><br>';
        echo var_dump($numfiles);
        echo 'Number of <b>Lines of Code</b><br>';
        echo var_dump($numlines);
        echo 'Size in KBytes</b><br>';
        echo var_dump($numsize);
        exit;
        ?>
    </head>
    <body>
        <?php
        $numfiles = [];
        $numlines = [];
        $numsize = [];

        function readSourceFile($file, $ext) {
            global $numfiles, $numlines, $numsize;
            $numfiles[$ext]++;
            $numlines[$ext] += count(file($file));
            $numsize[$ext] += floor(max(1, filesize($file) / 1024));
        }

        function walkDir($path) {
            if (is_dir($path)) {
                $dh = opendir($path);
                while (($file = readdir($dh)) !== false) {
                    if ($file == '.' || $file == '..' || substr($file, 0, 1) == '.') {
                        continue;
                    }
                    if (is_dir($path . '/' . $file)) {
                        walkDir($path . '/' . $file);
                    }
                    $arr = explode('.', $file);
                    $ext = $arr[count($arr) - 1];
                    if (stripos(' php js css html ', $ext) > 0) {
                        readSourceFile($path . '/' . $file, $ext);
                    }
                }
                closedir($dh);
                return;
            }
        }
        ?>
    </body>
</html>
