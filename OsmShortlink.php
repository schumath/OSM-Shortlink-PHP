<?php
// PHP OSM Shortlink - Mathias Schuh 2022 - GNU General Public License 2.0
// This a php implementation of OSM Shortlink (https://wiki.openstreetmap.org/wiki/Shortlink)
//
// encodeShortCode() is a PHP implementation of makeShortCode() from https://github.com/openstreetmap/openstreetmap-website/blob/e84b2bd22f7c92fb7a128a91c999f86e350bf04d/app/assets/javascripts/application.js
// decodeShortCode() is a PHP implementation from H. v. Hatzfeld http://www.salesianer.de/util/shortcode.js


/**
 * Return a short link representing a location in OpenStreetmap.
 * Provide coordinates and optional zoom level.
 * @param int|float $lat Latitude  e.g.: 50.1111111
 * @param int|float $lon Longitude e.g.: 10.5555555
 * @param int $zoom [optional] Zoom-Level 0...20. default = 15
 * @return string Shortlink URL. e.g.: "https://osm.org/go/0D_QEtY0--"
 */
function getShortLink($lat, $lon, $zoom = 15){
    $shortCode = encodeShortCode($lat, $lon, $zoom);
    return "https://osm.org/go/" . $shortCode;
}


/**
 * Return a short code representing a location in OpenStreetmap.
 * Provide coordinates and optional zoom level.
 * @param int|float $lat Latitude. e.g.: 50.1111111
 * @param int|float $lon Longitude. e.g.: 10.555555
 * @param int $zoom [optional] Zoom-Level 0...20. default = 15
 * @return string Short code. e.g.: "0D_QEtY0--"
 */
function encodeShortCode($lat, $lon, $zoom = 15) {
    $charArray = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_~";
    $x = round(($lon + 180.0) * ((1 << 30) / 90.0));
    $y = round(($lat +  90.0) * ((1 << 30) / 45.0));
    $str = "";
    $c1 = interleave(unsignedRightShift($x, 17), unsignedRightShift($y, 17));
    $c2 = interleave(unsignedRightShift($x, 2) & 0x7fff, unsignedRightShift($y, 2) & 0x7fff);
    for ($i = 0; $i < ceil(($zoom + 8) / 3.0) && $i < 5; ++$i) {
        $digit = ($c1 >> (24 - 6 * $i)) & 0x3f;
        $str.= substr($charArray, $digit,1);
    }
    for ($i = 5; $i < ceil(($zoom + 8) / 3.0); ++$i) {
        $digit = ($c2 >> (24 - 6 * ($i - 5))) & 0x3f;
        $str.= substr($charArray, $digit,1);
    }
    $str .= str_repeat("-", (($zoom + 8) % 3));
    return $str;
}


/**
 * Return Coordinates and zoom-level of a short code representing a location in OpenStreetmap.
 * @param string $shortCode Short code. e.g.: "0D_QEtY0--"
 * @return array associative array with coordinates (latitude, longitude) and zoom-level.
 * <p> e.g.: [
 *   "lat" => 50.111110210419,
 *   "lon" => 10.555543899536,
 *   "zoom"=> 15
 *   ]</p>
 */
function decodeShortCode($shortCode) {
    $charArray = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_~";
    // replace @ (old shortlink format) with ~ (new shortlink format)
    $shortCode = str_replace("@","~", $shortCode);
    $x = 0;
    $y = 0;
    $z = -8;
    for ($i=0; $i < strlen($shortCode); $i++) {
        $ch = substr($shortCode, $i,1);
        $digit = strrpos($charArray, $ch);
        if ($digit === false) break;
        // distribute 6 bits into x and y
        $x<<=3;
        $y<<=3;
        for ($j=2; $j>=0; $j--) {
            $x |= (($digit & (1 << ($j+$j+1))) == 0 ? 0 : (1 << $j));
            $y |= (($digit & (1 << ($j+$j))) == 0 ? 0 : (1 << $j));
        }
        $z += 3;
    }
    $x = $x * pow(2,2-3*$i) * 90 - 180;
    $y = $y * pow(2,2-3*$i) * 45 -  90;
    // adjust z
    if ($i < strlen($shortCode) && substr($shortCode, $i, 1) == "-") {
        $z -= 2;
        if ($i+1 < strlen($shortCode) && substr($shortCode, $i+1, 1) == "-") {
            $z++;
        }
    }

    return [
        "lat" => $y,
        "lon" => $x,
        "zoom"=> $z
    ];
}


/** helper functions */
function unsignedRightShift($value, $steps) {
    if ($steps == 0) return $value;
    return ($value >> $steps) & ~(1 << (8 * PHP_INT_SIZE - 1) >> ($steps - 1));
}

function interleave($x, $y) {
    $x = ($x | ($x << 8)) & 0x00ff00ff;
    $x = ($x | ($x << 4)) & 0x0f0f0f0f;
    $x = ($x | ($x << 2)) & 0x33333333;
    $x = ($x | ($x << 1)) & 0x55555555;
    $y = ($y | ($y << 8)) & 0x00ff00ff;
    $y = ($y | ($y << 4)) & 0x0f0f0f0f;
    $y = ($y | ($y << 2)) & 0x33333333;
    $y = ($y | ($y << 1)) & 0x55555555;
    return ($x << 1) | $y;
}
