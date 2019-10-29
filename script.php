<?php

// Settings params
$url = "https://pastebin.com";
$totalNumberOfRequests = 100;
$useProxy = false; // If true, the script will use a random proxy server else it will use a direct connection.

$everySeconds = 60; // every 1 minute (60). How often the script will send a bulk of requests.
//$everySeconds = 300; // every 5 minutes (300). How often the script will send a bulk of requests.

$totalNumberOfSeconds = 120; // total number of execution is 120 seconds.
//$totalNumberOfSeconds = 86400; // "24hours = 86400 seconds

set_time_limit(100);

$urlOfTheProxies = "https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt";
#$urlOfTheProxies = "http://localhost/proxies.txt";


print("\nThe Script is starting...");

$frecuency = floor($totalNumberOfSeconds / $everySeconds);
$numberOfRequestsPerRun = floor($totalNumberOfRequests / $frecuency);

$initialDatetime = (new DateTime())->getTimestamp();
$lastRun = -1;


// Program Start

$htmlDom = new DOMDocument;

$randomProxy = getRandomProxy();
print("\nA proxy server has randomly been selected: " . $randomProxy . " from " . $urlOfTheProxies);
print("\nGetting all links from: " . $url. " Wait a moment...");

$links = getAllLinksFromUrl($url, $randomProxy);

print("\nThe script has detected " . count($links) . " links.");


while (true) {

    $secondsElapsed = (new DateTime())->getTimestamp() - $initialDatetime;
    $nextRun = floor($secondsElapsed / $everySeconds);

    if ($lastRun < $nextRun) {

        $now = (new DateTime())->format('Y-m-d H:i:s');
        print("\nRequests storm has started at: " . $now);

        $lastRun = $nextRun;
        sendRequestStorm($numberOfRequestsPerRun, $links);
    }

    if ($lastRun >= $frecuency) {
        break;
    }
}

function sendRequestStorm($numberOfTimes, $listOfLinks) {
    $randomProxy = getRandomProxy();
    print("\nA proxy server has randomly been selected: " . $randomProxy . " from https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt");

    print("\n" . $numberOfTimes . ' requests will be run this time');
    for ($i = 1; $i <= $numberOfTimes; $i++) {
        $randomLink = getRandomLinkFromAllLinks($listOfLinks);

        $now = (new DateTime())->format('Y-m-d H:i:s');

        print("\n" . $now . " - Sending a request (" . $i . ") to:" . $randomLink);
    }
}

function getRandomProxy() {
    global $useProxy;
    global $urlOfTheProxies;

    if (!$useProxy) return null;

    $html = sendRequest($urlOfTheProxies);
    $urls = explode("\n", $html);
    $urls = array_filter($urls);

    if (count($urls) == 0) {
        throw new Exception("There are no urls on list of proxies.");
    }

    $indexOfrandomProxy = array_rand($urls);
    $randomProxy = $urls[$indexOfrandomProxy];
    return $randomProxy;
}

function getAllLinksFromUrl($url, $proxy = null) {

    $html = sendRequest($url, $proxy);

    $urls = linkify($html, $url);

    if (count($urls) == 0) {
        throw new Exception("There are no links on " . $url. ". There must be at least 1 link.");
    }

    return $urls;
}

function getRandomLinkFromAllLinks($links) {
    $randIndex = array_rand($links);
    
    $randomLink = $links[$randIndex];

    $randomUrl = $randomLink["href"];

    return $randomUrl;
}

function sendRequest($url, $proxy = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    if (isset($proxy)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }
    
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);

    if(curl_exec($ch) === false)
    {
        throw new Exception("PHP Curl Error." . curl_error($ch));
    }

    $curl_scraped_page = curl_exec($ch);
    curl_close($ch);

    return $curl_scraped_page;
}

/**
 * Turn all URLs in clickable links.
 * 
 * @param string $value
 * @param array  $protocols  http/https, ftp, mail, twitter
 * @param array  $attributes
 * @return string
 */
function linkify($html, $urlBase = null)
{
    
    //Instantiate the DOMDocument class.
    $htmlDom = new DOMDocument;
    
    //Parse the HTML of the page using DOMDocument::loadHTML
    @$htmlDom->loadHTML($html);
    
    //Extract the links from the HTML.
    $links = $htmlDom->getElementsByTagName('a');
    
    //Array that will contain our extracted links.
    $extractedLinks = array();

    //Loop through the DOMNodeList.
    //We can do this because the DOMNodeList object is traversable.
    foreach($links as $link){
    
        //Get the link text.
        $linkText = $link->nodeValue;
        //Get the link in the href attribute.
        $linkHref = $link->getAttribute('href');
    
        //If the link is empty, skip it and don't
        //add it to our $extractedLinks array
        if(strlen(trim($linkHref)) == 0){
            continue;
        }
    
        //Skip if it is a hashtag / anchor link.
        if($linkHref[0] == '#'){
            continue;
        } else if ($linkHref[0] == '/') {
            $linkHref = $urlBase . $linkHref;
        } else if (preg_match("/^(?!http)(.*)$/", $linkHref)) {
            $linkHref = $urlBase . "/" . $linkHref;
        }
    
        //Add the link to our $extractedLinks array.
        $extractedLinks[] = array(
            'text' => $linkText,
            'href' => $linkHref
        );

        if (isset($urlBase)) {
            $extractedLinks = array_filter($extractedLinks, function ($item) use ($urlBase) {
                return strstr($item["href"], $urlBase);
            });
        }
    
    }

    return $extractedLinks;
}
