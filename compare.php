<?php
// Change these to customize stuff
// Path to where the RDF files should be stored
$dest = 'RDF';
$cachetime = 604800; // 7 days

// Filename of TitleIdentifier export from BHL
$title_id_source = 'titleidentifier.txt';
$title_source = 'title.txt';
$creator_source = 'creator.txt';

// Get the latest downloads from BHL
if (!file_exists($title_id_source)) {
  print "Exports are missing. ";
  download_dumps();
}

// Let's cache all of the creators
$titles = import_titles();

// Make the destination directory
if (!file_exists($dest)) {
  mkdir($dest);
}

// This is faster for testing if we can pull from a cache;
if (file_exists('compare-cache.txt')) {
  print "Using data cache for speeeeeeed!!!\n";
  $titles = unserialize(file_get_contents('compare-cache.txt'));

} else {

  $lines = line_count($title_id_source);
  $count = 0;
  $pause_count = 0;
  $fp = fopen($title_id_source, 'r');
  $filename = null;
  fgetcsv($fp, 0, "\t");

  while ($data = fgetcsv($fp, 0, "\t")) {
    // Get some info from the file
    $title_id = $data[0];
    $id_type = $data[1];
    $id = $data[2];

    // Do we care about this record?
    if (count($data) < 4 || $id_type != 'OCLC') { $count++; continue; }

    $titles[$title_id]['oclc_number'] = $id;

    // Are we interested in this identifier?
    $filename = "{$id}.rdf";

    // Let's be nice and not beat up their servers too much.
    if (!file_exists("{$dest}/{$filename}")) {
      print "Getting {$filename} (TitleID = $title_id)\n";

      // Download and save the RDF in one swell foop
      file_put_contents("{$dest}/{$filename}", file_get_contents("http://experiment.worldcat.org/oclc/{$filename}"));

      // Let's be extra nice. Every 30th download, we rest for 10 seconds
      if ($pause_count % 30 == 0) {
        sleep(10);
      }
      $pause_count++;
    }

    print chr(13).'('.(int)($count/$lines*100)."%) ";
    $txt = '<?xml version="1.0"?'.'>'."\n".file_get_contents("{$dest}/{$filename}");
    if (strlen($txt) > 30) {
      $rdf = new DomDocument;
      $rdf->loadXml($txt);

      $xph = new DOMXPath($rdf);
      $xph->registerNamespace('rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
      // Get the things that are about the OCLC number we are interestd in

      foreach($xph->query('//rdf:Description[@rdf:about[contains(.,\'http://www.worldcat.org/oclc/'.$id.'\')]]') as $node) {

  
        // Get the OCLC title
        $titles[$title_id]['oclc_title'] = $node->getElementsByTagName('name')[0]->textContent;
  
        // Find the "contributors"
        $contributors = $node->getElementsByTagName('contributor');
        foreach ($contributors as $c) {
          $viaf = $c->getAttribute('rdf:resource');
    
          $c2 = $xph->query('//rdf:Description[@rdf:about="'.$viaf .'"]');
          $c2 = $c2[0];
          if ($c2) {
            $name       = $c2->getElementsByTagName('name');
            $familyName = $c2->getElementsByTagName('familyName');
            $givenName  = $c2->getElementsByTagName('givenName');
            $birthDate  = $c2->getElementsByTagName('birthDate');
            $deathDate  = $c2->getElementsByTagName('deathDate');
            $type       = $c2->getElementsByTagName('type');
            $type       = $type[0]->getAttribute('rdf:resource');
    
            $titles[$title_id]['oclc_authors'][] = array(
              'viaf' => preg_replace('|http://viaf.org/viaf/|', '', $viaf),
              'type' => preg_replace('|http://schema.org/|', '', $type),
              'name' => (count($name) > 0 ? @$name[0]->textContent : ''),
              'familyName' => (count($familyName) > 0 ? @$familyName[0]->textContent : ''),
              'givenName' => (count($givenName) > 0 ? @$givenName[0]->textContent : ''),
              'birthDate' => (count($birthDate) > 0 ? @$birthDate[0]->textContent : ''),
              'deathDate' => (count($deathDate) > 0 ? @$deathDate[0]->textContent : ''),
              'matched' => '',
            );          
          }   
        }
        // Find the "creators"
        $creators = $node->getElementsByTagName('creator');
        foreach ($creators as $c) {
          $viaf = $c->getAttribute('rdf:resource');
    
          $c2 = $xph->query('//rdf:Description[@rdf:about="'.$viaf .'"]');
          $c2 = $c2[0];
    
          if ($c2) {
            $name       = $c2->getElementsByTagName('name');
            $familyName = $c2->getElementsByTagName('familyName');
            $givenName  = $c2->getElementsByTagName('givenName');
            $birthDate  = $c2->getElementsByTagName('birthDate');
            $deathDate  = $c2->getElementsByTagName('deathDate');
            $type       = $c2->getElementsByTagName('type');
            $type       = $type[0]->getAttribute('rdf:resource');
    
            $titles[$title_id]['oclc_authors'][] = array(
              'viaf' => preg_replace('|http://viaf.org/viaf/|', '', $viaf),
              'type' => preg_replace('|http://schema.org/|', '', $type),
              'name' => (count($name) > 0 ? @$name[0]->textContent : ''),
              'familyName' => (count($familyName) > 0 ? @$familyName[0]->textContent : ''),
              'givenName' => (count($givenName) > 0 ? @$givenName[0]->textContent : ''),
              'birthDate' => (count($birthDate) > 0 ? @$birthDate[0]->textContent : ''),
              'deathDate' => (count($deathDate) > 0 ? @$deathDate[0]->textContent : ''),
              'matched' => '',
            );          
          }
        }
      }
    }
    $count++;
  }
  print chr(13)."(100%)    \n";
  fclose($fp);
  file_put_contents('compare-cache.txt', serialize($titles));
}

// Almost done!  
export_results($titles);
exit(0);

// ============================================================== 

// Helper function to allow us a progress counter
function line_count($file) {
  $linecount = 0;
  $handle = fopen($file, "r");
  while(!feof($handle)){
    $line = fgets($handle);
    $linecount++;
  }
  fclose($handle);
  return $linecount;
}

// Helper function to get the dump files from BHL
function download_dumps() {
  global $title_id_source;
  global $title_source;
  global $creator_source;
  print "Downloading from BHL....\n";
  file_put_contents($title_source, fopen('https://www.biodiversitylibrary.org/data/title.txt', 'r'));
  file_put_contents($title_id_source, fopen('https://www.biodiversitylibrary.org/data/titleidentifier.txt', 'r'));
  file_put_contents($creator_source, fopen('http://www.biodiversitylibrary.org/data/creator.txt', 'r'));
}

// Helper function to cache a bunch of stuff in memory
function import_titles() {
  global $title_source;
  global $creator_source;
  
  $t = array();
  print "Caching titles and creators....";
  $fh = fopen($title_source, 'r');
  fgetcsv($fh, 0, "\t");
  while ($line = fgetcsv($fh, 0, "\t")) {
    if (!isset($t[$line[0]])) {
      $t[$line[0]] = array(
        'bhl_id' => $line[0],
        'bhl_title' => $line[4],
        'bhl_creators' => array(),
        'oclc_authors' => array()
      );
    } 
  }  

  print "Caching creators....";
  $fh = fopen($creator_source, 'r');
  fgetcsv($fh, 0, "\t");
  while ($line = fgetcsv($fh, 0, "\t")) {
    if (isset($t[$line[0]])) {
      if (isset($line[2])) {
        $t[$line[0]]['bhl_creators'][] = $line[2];
      }
    }
  }
  print "Done\n";
  return $t;
  
}

// Save our results
function export_results($titles) {
  // Print the results:
  print "Exporting results...";
  $fout = fopen('titles-viaf.csv','w');
  fwrite($fout, chr(239) . chr(187) . chr(191) .'BHL ID, OCLC Number, BHL Title, OCLC Title, BHL Author, OCLC VIAF, OCLC Name, OCLC givenName, OCLC familyName, OCLC birthDate, OCLC deathDate'."\n");

  // Loop through the titles
  foreach ($titles as $t) {
    // We only care if there were BHL creators
    if (!isset($t['bhl_creators'])) { continue; }
    
    // For each of the BHL Creators... .
    foreach ($t['bhl_creators'] as $bhl_author) {
      // Start setting up the CSV output
      $csv = array();
      $csv[] = $t['bhl_id'];
      $csv[] = (isset($t['oclc_number']) ? $t['oclc_number'] : '');
      $csv[] = $t['bhl_title'];
      $csv[] = (isset($t['oclc_title']) ? $t['oclc_title'] : '');
      $csv[] = $bhl_author;
      
      // Now we try to compare the BHL Creators to the OCLC Creators
      for ($i = 0; $i < count($t['oclc_authors']); $i++) {
      
        // Skip over things we've already matched
        if (!$t['oclc_authors'][$i]['matched']) {
          
          // Strip the date string from the BHL Author and make it FIRSTNAME LASTNAME
          $normalized_bhl_author = explode(',', $bhl_author);
          for ($n = 0; $n < count($normalized_bhl_author); $n++) {
            $normalized_bhl_author[$n] = trim($normalized_bhl_author[$n]);
            if (preg_match('/\d{3}/', $normalized_bhl_author[$n])) {
              unset($normalized_bhl_author[$n]);
            }
          }
          $nba = trim(array_shift($normalized_bhl_author));
          $nba = trim(implode(', ', $normalized_bhl_author).' '.$nba);
                    
          // Calculate how "different" the two creator strings are
          $diff = levenshtein_utf8($nba, $t['oclc_authors'][$i]['name']);
          // If they are "too much different" then we see if the OCLC Author contains the BHL Author string
          if ($diff > 5) {
            $haystack = preg_replace('/[^a-z0-9]/i','',$nba);
            $needle = preg_replace('/[^a-z0-9]/i','',$t['oclc_authors'][$i]['name']);
            if ($haystack && $needle) {
              $res = strpos(strtolower($haystack), strtolower($needle));
              if ($res !== false && $res >= 0) {
                $diff = -2;
              }
            }
          }
          // If there was a match (or an approximate match) we add the info to the CSV
          if ($diff <= 5) {
            $t['oclc_authors'][$i]['matched'] = 1;
            $csv[] = $t['oclc_authors'][$i]['viaf'];
            $csv[] = $t['oclc_authors'][$i]['name'];
            $csv[] = $t['oclc_authors'][$i]['givenName'];
            $csv[] = $t['oclc_authors'][$i]['familyName'];
            $csv[] = $t['oclc_authors'][$i]['birthDate'];
            $csv[] = $t['oclc_authors'][$i]['deathDate'];
            break;
          }
        }
      }
      // And finally we output the CSV
      fputcsv($fout, $csv);
      
      // We keep doing this until we run out of BHL Creators
      // It's possible we might print a BHL Creator with no matching OCLC Creator. That's OK.
    }

    // Print out the OCLC Creators that WERE NOT matched to BHL Creators
    for ($i = 0; $i < count($t['oclc_authors']); $i++) {
      if (!$t['oclc_authors'][$i]['matched']) {
        $csv = array();
        $csv[] = $t['bhl_id'];
        $csv[] = $t['oclc_number'];
        $csv[] = $t['bhl_title'];
        $csv[] = (isset($t['oclc_title']) ? $t['oclc_title'] : '');
        $csv[] = '';
        $csv[] = $t['oclc_authors'][$i]['viaf'];
        $csv[] = $t['oclc_authors'][$i]['name'];
        $csv[] = $t['oclc_authors'][$i]['givenName'];
        $csv[] = $t['oclc_authors'][$i]['familyName'];
        $csv[] = $t['oclc_authors'][$i]['birthDate'];
        $csv[] = $t['oclc_authors'][$i]['deathDate'];
        fputcsv($fout, $csv);
      }
    }
    fputcsv($fout, array());
  }
  fclose($fout);
  print "Done!\n";
}

// This is used to calculate how "different" two strings are.
function levenshtein_utf8($s1, $s2) {

  $charMap = array();
  $s1 = utf8_to_extended_ascii($s1, $charMap);
  $s2 = utf8_to_extended_ascii($s2, $charMap);
 
  return levenshtein($s1, $s2);
}
function utf8_to_extended_ascii($str, &$map){
  // find all multibyte characters (cf. utf-8 encoding specs)
  $matches = array();
  if (!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches))
    return $str; // plain ascii string

  // update the encoding map with the characters not already met
  foreach ($matches[0] as $mbc)
    if (!isset($map[$mbc]))
      $map[$mbc] = chr(128 + count($map));

  // finally remap non-ascii characters
  return strtr($str, $map);
}
