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
        echo 'Number of <b>Size in KBytes</b><br>';
        echo var_dump($numsize);
        exit;
        ?>
    </head>
    <body>
        <?php
        $numfiles = Array();
        $numlines = Array();
        $numsize = Array();

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
                        if ($file == 'rewritePHP') {
                            //closedir($dh);
                            continue;
                        }
                        walkDir($path . '/' . $file);
                    }
                    $arr = explode('.', $file);
                    if ($arr[1] == 'php' || $arr[1] == 'js' || $arr[1] == 'css' || $arr[1] == 'html') {
                        //echo $path . '/' . $file . '<br>';
                        readSourceFile($path . '/' . $file, $arr[1]);
                    }
                }
                closedir($dh);
                return;
            }
        }
        ?>
    </body>
</html>
