<?php
require_once(ABSPATH . "wp-admin" . '/includes/image.php');
require_once(ABSPATH . "wp-admin" . '/includes/file.php');
require_once(ABSPATH . "wp-admin" . '/includes/media.php');
 
class Import_Script
{
 
    static $URL = 'https://www.google.com/searchbyimage?&image_url=';
    static $topstuff_div_query = "/body/div[@id='main']/div/div[@id='cnt']/div[@id='rcnt']/div[@id='center_col']/div[@id='res']/div[@id='topstuff']";
    static $title_h3_query = "/body/div[@id='main']/div/div[@id='cnt']/div[@id='rcnt']/div[@id='center_col']/div[@id='res']
        /div[@id='search']/div[@id='ires']/ol[@id='rso']/li[not(@id='imagebox_bigimages') and @class='g']/div[@class='vsc']//h3[@class='r']";
    static $span_text_query = "/body/div[@id='main']/div/div[@id='cnt']/div[@id='rcnt']/div[@id='center_col']/div[@id='res']
                /div[@id='search']/div[@id='ires']/ol[@id='rso']/li[not(@id='imagebox_bigimages') and @class='g']/div[@class='vsc']//span[@class='st']";
                 
    static $domains = array(".com", ".org", ".net", ".hu");
    static $links = array("http://", "www");
    static $picture_file_names = array(".jpg", ".jpeg", ".png");
 
    public $img_file_name;
 
    function __construct($image_url, $order)
    {
 
        try {
 
            $full_url = $this->compose_url($image_url);
            $img_res_url = $this->open_url($full_url);
            $body_dom = $this->get_tag_content_as_dom($img_res_url);
 
            $topstuff_div = $this->get_xpath_result($body_dom, self::$topstuff_div_query);
            $best_guess = $this->get_best_guess($topstuff_div);
 
            $titles = $this->get_xpath_result($body_dom, self::$title_h3_query);
 
            $span_texts = $this->get_xpath_result($body_dom, self::$span_text_query);
 
            // if length is > 0 then search result isn't empty
            if ($titles->length > 0 && $span_texts->length > 0) {
                $best_guess = $this->sanitize_best_guess($best_guess);
                if ($best_guess) {
                    $this->img_file_name = strtolower($this->compose_img_file_name($best_guess));
                } else {
                    $this->img_file_name = strtolower($this->compose_img_file_name($img_name));
                }
            } else {
                echo "Nothing found about the picture, url: " . $image_url;
            }
        } catch (Exception $e) {
            echo 'Exception caught: ',  $e->getMessage(), "<br />";
            echo 'Exception for url: '.$image_url."<br />";
            sleep(10);
        }
    }
 
    function loop_xpath_res($xpath_res)
    {
        foreach ($xpath_res as $val) {
            echo $val->nodeValue . " | ";
        }
        echo "<br />";
    }
 
    function compose_img_file_name($word)
    {
        return str_replace(" ", "-", $word);
    }
 
    function sanitize_best_guess($best_guess)
    {
        if ($best_guess) $best_guess = $this->filter_out_bad_words($best_guess);
        return $best_guess;
    }
 
    function filter_out_bad_words($string)
    {
        $string = $this->remove_containing_word($string, self::$links);
        $string = $this->remove_containing_word($string, self::$domains);
        $string = $this->remove_containing_word($string, self::$picture_file_names);
        $string = preg_replace('/[^ -\pL]/', '', $string); // only letters, whitespace and hyphens 'abc -'
        $string = preg_replace("#[^a-zA-Z0-9 -]#", "", $string);
        $string = trim($string, '-');
        $string = trim($string, ' ');
        $string = $this->remove_specific_word($string, '-');
        $string = trim($string, '-');
        $string = trim($string, ' ');
        return $string;
    }
 
    function contains_specific_word($string, $specific_word)
    {
        $string_array = explode(" ", $string);
        foreach ($string_array as $element) {
            if (strcasecmp($element, $specific_word) == 0) return true;
        }
        return false;
    }
 
    function remove_specific_word($string, $bad_words)
    {
        $string_array = explode(" ", $string);
        if (is_array($bad_words)) {
            foreach ($string_array as $index => $word) {
                foreach ($bad_words as $bad_word) {
                    if (strcasecmp($word, $bad_word) == 0) unset($string_array[$index]);
                }
            }
        } else {
            foreach ($string_array as $index => $word) {
                if (strcasecmp($word, $bad_words) == 0) unset($string_array[$index]);
            }
        }
        return implode(" ", $string_array);
    }
 
    function remove_containing_word($string, $word_peaces)
    {
        $new_string = $string;
        foreach ($word_peaces as $part_of_word) {
            $word_pos = strripos($new_string, $part_of_word);
            if ($word_pos !== false) {
                $words_array = explode(" ", $new_string);
                foreach ($words_array as $index => $word) {
                    if (stripos($word, $part_of_word) !== false) {
                        unset($words_array[$index]);
                    }
                }
                $new_string = implode(" ", $words_array);
            }
        }
        return $new_string;
    }
 
    function get_best_guess($topstuff_div)
    {
        $topstuff_result = '';
        foreach ($topstuff_div as $val) {
            $topstuff_result .= $val->nodeValue . " ";
        }
        $best_guess = $this->strstr_after($topstuff_result, 'Best guess for this image:');
        return trim($best_guess, ' ');
    }
 
    function strstr_after($haystack, $needle)
    {
        $pos = stripos($haystack, $needle);
        if (is_int($pos)) {
            return substr($haystack, $pos + strlen($needle));
        }
        // Most likely false or null
        return $pos;
    }
 
    function compose_url($request)
    {
        $full_url = self::$URL . $request;
        return $full_url;
    }
 
    function open_url($full_url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $full_url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_REFERER, 'http://localhost');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.97 Safari/537.11");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $content = utf8_decode(curl_exec($curl));
        curl_close($curl);
        return $content;
    }
 
    function get_tag_content_as_dom($img_res_url, $tag_name = 'body')
    {
        $dom = new DOMDocument();
        $dom->strictErrorChecking = false; // turn off warnings and errors when parsing
        @$dom->loadHTML($img_res_url);
        $body = $dom->getElementsByTagName($tag_name);
        $body = $body->item(0);
        $new_dom = new DOMDocument();
        $node = $new_dom->importNode($body, true);
        $new_dom->appendChild($node);
        return $new_dom;
    }
 
    function get_xpath_result($dom, $xpath_query)
    {
        $dom_xpath = new DOMXPath($dom);
        return $dom_xpath->query($xpath_query);
    }
}

$i = 1
// could be in some loop where you give picture urls
// foreach {
$pic_url = 'http://kaizern.com/blog/beautiful-landscapes-1.jpg';
$importScript = new Import_Script($pic_url, $i);

if ($importScript->img_file_name) {
	$image_file_name = $importScript->img_file_name;

    echo "Image name: <strong>" . $image_file_name . "</strong> ";
    echo "Imported picture " . $i . ", url: " . $pic_url . " and title: ".$post_title."<br />";
	
	// do here with the image file name some specific operations for your website, e.g insert new picture etc
} else {
    echo "Nothing found about the picture, url: " . $pic_url . "<br />";
}
			
// } end of some loop