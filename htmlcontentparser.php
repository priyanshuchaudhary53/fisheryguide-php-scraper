<?php

require 'vendor/autoload.php';

use PHPHtmlParser\Dom\TextNode;

function processHtmlContent($domNode)
{
    $entryContent = $domNode;

    // Remove the relatedWebsite element
    $relatedWebsite = $entryContent->find('.yarpp-related-website')[0];
    if ($relatedWebsite) {
        $relatedWebsite->delete();
    }
    unset($relatedWebsite);

    // Remove all images
    $inside_imgs = $entryContent->find('img');
    if ($inside_imgs) {
        foreach ($inside_imgs as $img) {
            $img->delete();
        }
    }
    unset($inside_imgs);

    // Remove all iframes
    $inside_iframes = $entryContent->find('iframe');
    if ($inside_iframes) {
        foreach ($inside_iframes as $iframe) {
            $iframe->delete();
        }
    }
    unset($inside_iframes);

    // Remove ezoicDivs elements
    $ezoicDivs = $entryContent->find('div[id^="ezoic-pub-ad-"]');
    foreach ($ezoicDivs as $div) {
        $div->delete();
    }
    unset($ezoicDivs);

    // Remove the first h3 and p elements if first h3 is 'address'
    $firstH3 = $entryContent->find('h3')[0];
    if ($firstH3 && strtolower($firstH3->innerHtml) === 'address') {
        $firstH3->delete();
        $firstP = $entryContent->find('p')[0];
        if ($firstP) {
            $firstP->delete();
        }
        unset($firstP);
    }
    unset($firstH3);

    // Remove the county_div element
    $county_div = $entryContent->find('div.county');
    if ($county_div) {
        $county_div->delete();
    }
    unset($county_div);

    // Remove ul element
    $uls = $entryContent->find('ul');
    foreach ($uls as $ul) {
        $ul->delete();
    }

    // Remove the youtube videos and links H3
    $all_h3 = $entryContent->find('h3');
    foreach ($all_h3 as $h3) {
        if (strtolower($h3->innerHtml) === 'youtube videos' || strtolower($h3->innerHtml) === 'links') {
            $h3->delete();
        }
    }

    // Remove all span elements with id starting with 'ezoic-'
    $ezoicSpans = $entryContent->find('span[id^="ezoic-"]');
    foreach ($ezoicSpans as $span) {
        $span->delete();
    }

    // Find all 'a' tags in the HTML content
    $aTags = $entryContent->find('a');
    foreach ($aTags as $a) {
        $id = $a->id();
        $innerhtml = $a->innerHtml;
        $textnode = new TextNode($innerhtml);
        $a->getParent()->replaceChild($id, $textnode);
    }


    // Return the modified HTML content
    return $entryContent->innerhtml;
}