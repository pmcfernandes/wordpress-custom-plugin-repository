<?php

if (!isset($_GET['plugin'])) {
    http_response_code(404);
    die();
}

render($_GET['plugin']);
die();


/** Custom functions here !!!!!!!!! */

function getDomain(): string {
    $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
    return $protocol . '://' . $_SERVER['HTTP_HOST'];
}

function getPluginFolder(string $slug): string | bool {
    $folder =  __DIR__ . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR;

    if (file_exists($folder)) {
        return $folder;
    }

    return false;
}

function getLastestFilename(string $slug): string {
    $files = glob(getPluginFolder($slug) . $slug . ".*.zip");
    rsort($files);
    return $files[0];
}

function extractFromZip(string $f, string $plugin_name): string | bool {
    $searchForFile  = $plugin_name . DIRECTORY_SEPARATOR . $plugin_name . '.php';
    $z = new \ZipArchive();
    $z->open($f);

    for ($i = 1; $i < $z->numFiles; $i++) {
        $filename = $z->getNameIndex($i);

        if ($filename == $searchForFile) {
            $fp = $z->getStreamName($searchForFile, ZipArchive::FL_UNCHANGED);
            if($fp) {
                return stream_get_contents($fp);    
            }
        }
    }

    return false;
}

function readFileLines(string $content): array {
    $arr = [];

    foreach (preg_split('~[\r\n]+~', $content) as $line){
        if(empty($line) or ctype_space($line)) continue;

        if (str_starts_with(trim($line), '* ')) {
            $arr[] = $line;
        }
    }

    return $arr;
}

function extractPluginData(string $content): array {
    $data = [];
    $re = '/(?:\s\*\s)([a-zA-Z\s]+)(?:\:+)([\w\W]+)/m';

    foreach (readFileLines($content) as $line) {        
        preg_match_all($re, $line, $matches, PREG_SET_ORDER, 0);

        if ($matches) {
            $match = $matches[0];
            $data[$match[1]] = trim($match[2]);
        }
    }

    return $data;
}

function render(string $slug): void {
    if (!getPluginFolder($slug)) {
        http_response_code(404);
        die();
    }

    $fileName = getLastestFilename($slug);
    $modifiedAt = filemtime($fileName);

    $extracted = extractFromZip($fileName, $slug);
    $metadata = extractPluginData($extracted);

    $name = $metadata['Plugin Name'] ?? $slug;
    $version = $metadata['Version'] ?? '1.0.0';
    $author = $metadata['Author'] ?? 'Jonh Doe';
    $authorURI = $metadata['Author URI'] ?? '#';
    $description = $metadata['Description'] ?? '';
    
    $lowImage = getPluginFolder($slug) . 'banner-772x250.jpg';
    if (!file_exists($lowImage)) {
        $lowImage = "https://placehold.co/772x250?text=" . $slug;
    }   

    $highImage = getPluginFolder($slug) . 'banner-1544x500.jpg';
    if (!file_exists($highImage)) {
        $highImage ="https://placehold.co/1544x500?text=" . $slug;
    }   

    $downloadURI = implode([getDomain(), '/', 'repositories', '/', $slug, '/', basename($fileName)]);

    $update = array(
        'name' => $name,
        'slug' => $slug,
        "version" => $version,
        "last_updated" => date('Y-m-d H:i:s', $modifiedAt), 
        "download_url" => $downloadURI,
        "author" => "<a href='" . $authorURI . "'>" . $author . "</a>",
        "author_profile" =>  $authorURI,
        "tested" => "6.6",
        "requires" => "6.0",
        "requires_php" => "7.4",
        "sections" => array(
            "description" => $description,
            "installation" => "Click the activate button and that's it.",
            "changelog" => "",
        ),
        "banners" => array(
            "low" => $lowImage,
            "high" => $highImage,
        )
    );

    header('Content-Type: application/json');
    echo json_encode($update);
}