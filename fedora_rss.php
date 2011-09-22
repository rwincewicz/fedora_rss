<?php

require_once('Stomp/Stomp.php');
$conn = new Stomp("tcp://localhost:61613");
$file = 'fedora_rss.xml';
echo "connecting....";
$conn->connect();
echo " done!\n";
echo "\nWaiting...\n";
$conn->subscribe("/topic/fedora.apim.update");
while (1) {
  if (($msg = $conn->readFrame()) !== false) {

    $mess = (string) $msg;
    $mess = implode("\n", array_slice(explode("\n", $mess), 9));
    $date = date('r');
    $xml = new DOMDocument();
    $xml->loadXML($mess);
    $tag = $xml->getElementsByTagName('title')->item(0)->nodeValue;
    echo 'Event type: ' . $tag . "\n";
    $pid = $xml->getElementsByTagName('content')->item(0)->nodeValue;
    echo 'pid: ' . $pid . "\n";
    $check = check_url('http://192.168.56.103:8080/fedora/objects/' . $pid . '/datastreams/FULL_SIZE/content');
    var_dump($check);
    if ($tag == 'ingest' && $check) {
      if (!file_exists($file)) {
        $rss = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $rss .= '<rss version="2.0">' . "\n";
        $rss .= '<channel>' . "\n";
        $rss .= '<title>New fedora images</title>' . "\n";
        $rss .= '<item>' . "\n";
        $rss .= '<title>' . $pid . '</title>' . "\n";
        $rss .= '<link>http://192.168.56.103/fedora/repository/' . $pid . '</link>' . "\n";
        $rss .= '<guid>http://192.168.56.103/fedora/repository/' . $pid . '</guid>' . "\n";
        $rss .= '<description>' . "\n";
        $rss .= '<![CDATA[<img src="http://192.168.56.103/fedora/repository/' . $pid . '/FULL_SIZE" />]]>' . "\n";
        $rss .= '</description>' . "\n";
        $rss .= '<pubDate>' . $date . '</pubDate>' . "\n";
        $rss .= '</item>' . "\n";
        $rss .= '</channel>' . "\n";
        $rss .= '</rss>';

        $fh = fopen($file, 'w');
        fwrite($fh, $rss);
        fclose($fh);
      }
      else {
        $fh = fopen($file, 'r');
        $lines = array();
        while (!feof($fh)) {
          $lines[] = fgets($fh, 4096);
        }
        fclose($fh);
        $fh = fopen($file, 'w');
        $lc = count($lines);
        
        unset($lines[$lc - 1]);
        unset($lines[$lc - 2]);
        $lines[] = '<item>';
        $lines[] = '<title>' . $pid . '</title>' . "\n";
        $lines[] = '<link>http://192.168.56.103/fedora/repository/' . $pid . '</link>' . "\n";
        $lines[] = '<guid>http://192.168.56.103/fedora/repository/' . $pid . '</guid>' . "\n";
        $lines[] = '<description>' . "\n";
        $lines[] = '<![CDATA[<img src="http://192.168.56.103/fedora/repository/' . $pid . '/FULL_SIZE" />]]>' . "\n";
        $lines[] = '</description>' . "\n";
        $lines[] = '<pubDate>' . $date . '</pubDate>' . "\n";
        $lines[] = '</item>' . "\n";
        $lines[] = '</channel>' . "\n";
        $lines[] = '</rss>';
        $rss = implode("", $lines);
        fwrite($fh, $rss);
        fclose($fh);
      }
    }
    $conn->ack($msg);
  }
}

$conn->disconnect();

function check_url($url) {
  $fp = curl_init();
  curl_setopt($fp, CURLOPT_URL, $url);
  curl_setopt($fp, CURLOPT_RETURNTRANSFER, TRUE);
  $page = curl_exec($fp);
  curl_close($fp);
  $page_array = explode(' ', $page);
  if ($page_array[1] == 'No') {
    return FALSE;
  }
  else
    return TRUE;
}

?>