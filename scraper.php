<?php

require 'vendor/autoload.php';
require 'htmlcontentparser.php';

use PHPHtmlParser\Dom;
use PHPHtmlParser\Dom\TextNode;


$url_file_path = 'urls.txt';

$db = new PDO('sqlite:database.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$urls = [];

$file_handle = fopen($url_file_path, 'r');

if ($file_handle) {
    // Read each line from the file until the end of the file
    while (($line = fgets($file_handle)) !== false) {
        $urls[] = trim($line);
    }

    fclose($file_handle);
} else {
    echo "Error opening the file.";
}


if (empty($urls)) {
    exit('URLs not found!');
}

$dataAdded = 0;

foreach ($urls as $url) {

    $type = 'place';

    // Check if the URL already exists in the database
    $stmt = $db->prepare('SELECT COUNT(*) FROM main WHERE source_url = :source_url');
    $stmt->bindParam(':source_url', $url, PDO::PARAM_STR);
    $stmt->execute();
    $rowCount = $stmt->fetchColumn();

    if ($rowCount > 0) {
        continue; // Skip the INSERT query
    }

    $httpClient = new \GuzzleHttp\Client();
    $response = $httpClient->get($url);
    $htmlString = (string) $response->getBody();
    libxml_use_internal_errors(true);

    $dom = new Dom;
    $dom->loadStr($htmlString);

    // location
    $bread_crumbs = $dom->find('.site-content .site-main #breadcrumbs')[0];
    $location_span = $bread_crumbs->find('span span')[2];
    $location_link = $location_span->find('a')[0];
    $location = strip_tags($location_link);

    // address and gmap iframe
    $address = null;
    $gmapIframe = null;

    $firstIframe = $dom->find('.entry-content iframe')[0];
    $firstIframeSrc = $firstIframe->getAttribute('src');
    if (strpos($firstIframeSrc, 'google.com/maps') !== false) {
        $gmapIframe = $firstIframe;
    }

    $addressParagrapgh = $dom->find('.entry-content p')[0];
    if ($addressParagrapgh) {

        $addressIframe = $addressParagrapgh->find('iframe')[0];

        if ($addressIframe) {
            $addressIframe->delete();
        }
        unset($addressIframe);

        $aTags = $addressParagrapgh->find('a');
        foreach ($aTags as $a) {
            $id = $a->id();
            $innerhtml = $a->innerHtml;
            $textnode = new TextNode($innerhtml);
            $a->getParent()->replaceChild($id, $textnode);
        }
        unset($aTags);

        $address = trim(strip_tags($addressParagrapgh));

        if (strlen($address) > 120 || strlen($address) === 0) {
            $address = null;
        }
    }

    // images
    $mainDiv = $dom->find('.inside-article');
    $relatedDiv = $mainDiv->find('.yarpp-related-website')[0];
    if ($relatedDiv) {
        $relatedDiv->delete();
    }
    unset($relatedDiv);

    $allImages = $mainDiv->find('img');

    $mainImage = null;
    $secondaryImages = null;

    $secondaryArr = [];

    foreach ($allImages as $image) {
        $tag = $image->getTag();

        $src = $tag->getAttribute('src')['value'];
        $width = $tag->getAttribute('width')['value'];
        $height = $tag->getAttribute('height')['value'];

        $imageArr = ['src' => $src, 'width' => $width, 'height' => $height];
        $imgJson = json_encode($imageArr);

        if (empty($mainImage)) {
            $mainImage = $imgJson;
        } else {
            $secondaryArr[] = $imgJson;
        }
    }

    if (!empty($secondaryArr)) {
        $secondaryImages = json_encode(['images' => $secondaryArr]);
    }

    // title
    $titleH1 = $dom->find('header .entry-title');
    $titleInnerTags = $titleH1->find('span');
    foreach ($titleInnerTags as $span) {
        $span->delete();
    }
    unset($titleInnerTags);
    $title = strip_tags($titleH1);

    // slug
    $parsedUrl = parse_url($url);
    $path = $parsedUrl['path'];
    $path = rtrim($path, '/');
    $pathSegments = explode('/', $path);
    $slug = end($pathSegments);

    // youtube videos
    $yt_urls = null;
    $yt_video_arr = [];
    $insideArticle = $dom->find('.inside-article');
    $allIframes = $insideArticle->find('iframe');

    foreach ($allIframes as $iframe) {
        $src = $iframe->getAttribute('src');
        if (strpos($src, 'youtube.com') !== false || strpos($src, 'youtu.be') !== false) {
            $yt_video_arr[] = ["src" => $src];
        }
    }

    if (!empty($yt_video_arr)) {
        $yt_urls = json_encode(['ytvideos' => $yt_video_arr]);
    }

    // html text
    $inner_content_dom = $dom->find('.entry-content')[0];
    $htmltext = null;
    $pagetext = null;
    if (!empty($inner_content_dom)) {
        $htmltext = processHtmlContent($inner_content_dom);
        $pagetext = trim(strip_tags($htmltext->innerHtml));

        if ($address === null) {
            $textAllH3 = $htmltext->find('h3');
            foreach ($textAllH3 as $h3) {
                $h3->delete();
            }
            unset($textAllH3);

            $textAllPTags = $htmltext->find('p');
            foreach ($textAllPTags as $pTag) {
                $pTag->delete();
            }
            unset($textAllPTags);

            $address = trim(strip_tags($htmltext));

            $pagetext = str_replace($address, '', $pagetext);
        }
    }



    // Prepare query
    $stmt = $db->prepare('INSERT INTO main (source_url, type, address, gmaps_iframe_url, main_image_url, secondary_images, title, slug, yt_videos, page_text, location) VALUES (:source_url, :type, :address, :gmaps_iframe_url, :main_image_url, :secondary_images, :title, :slug, :yt_videos, :page_text, :location)');

    // Bind the parameters to the placeholders
    $stmt->bindParam(':source_url', $url, PDO::PARAM_STR);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->bindParam(':address', $address, PDO::PARAM_STR);
    $stmt->bindParam(':gmaps_iframe_url', $gmapIframe, PDO::PARAM_STR);
    $stmt->bindParam(':main_image_url', $mainImage, PDO::PARAM_STR);
    $stmt->bindParam(':secondary_images', $secondaryImages, PDO::PARAM_STR);
    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
    $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt->bindParam(':yt_videos', $yt_urls, PDO::PARAM_STR);
    $stmt->bindParam(':page_text', $pagetext, PDO::PARAM_STR);
    $stmt->bindParam(':location', $location, PDO::PARAM_STR);

    $stmt->execute();

    $dataAdded++;

    echo 'Added query for url: ' . $url . PHP_EOL;
}

echo 'Execution finished! ' . $dataAdded . ' new query added into the database.';