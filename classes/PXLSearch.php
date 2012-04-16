<?php
/**
 * @author   Ronald Mansveld
 */
ini_set('memory_limit', '128M');
set_time_limit(0);

class PXLSearch {
   /**
    * @var array	Haystack contains the tables and fields to search, in the following format: [tbl_name => [field, field, ...], tbl_name => [field, ...], ...]
    */
   protected static $haystack = null;
   protected static $skip = array();
   
   
   /**
    * Find the entries in the tables given in haystack, where the fields contain word(s) like the searchterm
    * @param	String	$searchterm	The string to search for
    * @param	Number	$relevance The percentage in which words should look alike the searchterm
    * @return	Array				The array with the search results [[tbl_name, id, score], [tbl_name, id, score], ...]
    *
    * Warning! This function should NOT be overridden unless you know exactly what you're doing!
    * Several hooks have been provided to assist you in making variations:
    * @see Search::preFind()
    * @see Search::getHaystack()
    * @see Search::postFind()
    */
   static public function find($searchterm = null, $relevance = 80) {
      /* One does not simply walk into Mordor */
      /* Oftewel: afblijven, tenzij je precies weet waar je mee bezig bent */
      
      $start = Tools::microtime();
         $searchterm = strtolower($searchterm);
         if ($relevance > 100) $relevance = 100;
         if ($relevance < 1) $relevance = 1;
         $cost_limit = (int) ceil((strlen($searchterm)*(100-$relevance))/100);
         $minlen = strlen($searchterm) - $cost_limit;
         $maxlen = strlen($searchterm) + $cost_limit;
         $searchterm = pxl_db_safe($searchterm);
      $end = Tools::microtime();
      //echo('find::init: '.($end-$start).' seconds<br />');
      
      $start = Tools::microtime();
         //preFind
         $ret = static::preFind($searchterm);
         if ($ret !== true) {
            return $ret;
         }
      $end = Tools::microtime();
      //echo('find::preFind: '.($end-$start).' seconds<br />');
      
      //search
      $start = Tools::microtime();
         $entries = array();
         $db = new Database(true);
         $queries = array();
         foreach (static::getHaystack() as $table => $fields) {
            $q = "
               SELECT
                  `id`,
                  '".pxl_db_safe($table)."' AS `tbl_name`,
                  CONCAT_WS(' ', `".implode('`, `', array_map('pxl_db_safe', $fields))."`) AS `content`
               FROM
                  `".pxl_db_safe($table)."`
            ";
            $queries[] = $q;
         }
         $q = '('.implode(') UNION (', $queries).')';
      $end = Tools::microtime();
      //echo('find::building queries: '.($end-$start).' seconds<br />');
      
      $start = Tools::microtime();
         $entries = $db->matrix($q);
      $end = Tools::microtime();
      //echo('find::performing query: '.($end-$start).' seconds<br />');
      unset($queries, $q);
      
      $start = Tools::microtime();
         $words = array();
         foreach ($entries as $entry) {
            $done = array();
            $content = $entry['content'];
            unset($entry['content']);
            $w = explode(' ', $content);
            foreach ($w as $word) {
               $word = strtolower($word);
               if (isset($done[$word]) || isset(static::$skip[$word])) {
                  continue;
               }
               if (strlen($word) >= $minlen && strlen($word) <= $maxlen) {
                  if (!isset($words[$word])) {
                     $words[$word] = array();
                  }
                  $words[$word][] = $entry;
               }
               $done[$word] = true;
            }
         }
         unset($entries, $done, $content, $entry, $w, $word); //mem cleanup
      $end = Tools::microtime();
      //echo('find::finding words: '.($end-$start).' seconds<br />');
      //echo(count($words).' words found!<br />');
      
      $start = Tools::microtime();
         $results = array();
         foreach ($words as $word => $entries) {
            $score = static::getScore($word, $searchterm, $cost_limit + 1);
            if ($score <= $cost_limit) {
               foreach ($entries as $entry) {
                  $entry['score'] = $score;
                  $results[] = $entry;
               }
            }
         }
      $end = Tools::microtime();
      //echo('find::calculating score: '.($end-$start).' seconds<br />');
      unset($words, $word, $score); //mem cleanup
      
      $start = Tools::microtime();
         array_walk($results, function(&$v, $k) {$v = array($v, $k);});
         usort($results, function($a, $b) {
               if ($a['score'] == $b['score']) return 0;
               return ($a['score'] < $b['score']) ? -1 : 1;
            });
         array_walk($results, function(&$v, $k) {$v = $v[0];});
      $end = Tools::microtime();
      //echo('find::sorting results: '.($end-$start).' seconds<br />');
      //return results
      return static::postFind($searchterm, $results);
   }
   
   /**
    * Calculate the distance between $word and $searchterm based on the Damerau-Levenshtein algorithm.
    * @param	String	$word		The word to check
    * @param	String	$searchterm	The searchterm to check word against
    * @param	Int	$cost_limit	The maximum cost we are looking for (so we can break early on words with higher costs)
    * @return	Int				The distance between $word and $searchterm
    */
   final protected static function getScore($word, $searchterm, $cost_limit) {
      if ($word == $searchterm) return 0;
      
      $len1 = strlen($word);
      $len2 = strlen($searchterm);
      
      if ($len1 == 0) return $len2;
      if ($len2 == 0) return $len1;
      
      //strip common prefix
      $i = 0;
      do {
         if ($word{$i} != $searchterm{$i}) break;
         $i++;
         $len1--;
         $len2--;
      } while ($len1 > 0 && $len2 > 0);
      if ($i > 0) {
         $word = substr($word, $i);
         $searchterm = substr($searchterm, $i);
      }
      
      //strip common suffix
      $i = 0;
      do {
         if ($word{$len1-1} != $searchterm{$len2-1}) break;
         $i++;
         $len1--;
         $len2--;
      } while ($len1 > 0 && $len2 > 0);
      if ($i > 0) {
         $word = substr($word, 0, $len1);
         $searchterm = substr($searchterm, 0, $len2);
      }
      
      if ($len1 == 0) return $len2;
      if ($len2 == 0) return $len1;
      
      $matrix = array();
      for ($i = 0; $i <= $len1; $i++) {
         $matrix[$i] = array();
         $matrix[$i][0] = $i;
      }
      for ($i = 0; $i <= $len2; $i++) {
         $matrix[0][$i] = $i;
      }
      
      for ($i = 1; $i <= $len1; $i++) {
         $best = $cost_limit;
         for ($j = 1; $j <= $len2; $j++) {
            $cost = $word{$i-1} == $searchterm{$j-1} ? 0 : 1;
            $new = min(
                     $matrix[$i-1][$j] + 1,
                     $matrix[$i][$j-1] + 1,
                     $matrix[$i-1][$j-1] + $cost
                  );
            if ($i > 1 && $j > 1 && $word{$i-2} == $searchterm{$j-1} && $word{$i-1} == $searchterm{$j-2}) {
               $new = min(
                        $new,
                        $matrix[$i-2][$j-2] + $cost
                     );
            }
            $matrix[$i][$j] = $new;
            if ($new < $best) {
               $best = $new;
            }
         }
         if ($best >= $cost_limit) {
            return $cost_limit;
         }
      }
      return $matrix[$len1][$len2];
   }
   
   
   /** Overloadable **/
   /**
    * Hook provided to execute code before the actual search is started
    * @param	String	$searchterm	The string to search for
    * @return	Mixed				True to continue searching, all other values will be used as return-value from Search::find()
    *
    * This function provides a pre-search hook to allow cache-lookups etc. Pay close attention to the return-value of this function!
    *
    * When this function returns true (boolean, since exact-comparison (===) is used), the find-function will continue the search.
    * Any value !== true will mean the search is aborted, and the return-value from this function is used as the return-value from the find-function!
    */
   static protected function preFind($searchterm) {
      return true;
   }
   
   /**
    * Hook provided to execute code after the search is done.
    * @param	String	$searchterm	The string that has been searched for
    * @param	Array 	$results	The array containing the results
    * @return	Array				The results as they should be returned to the caller of the find-function
    *
    * Warning! The return-value from this function will be returned as-is from the find-function!
    */
   static protected function postFind($searchterm, $results) {
      return $results;
   }
   
   /**
    * Gives a possibility to add extra features to the haystack, i.e. adding tables on the fly for logged-in users etc.
    * @return	Array		Array with the haystack, as defined at Search::$haystack
    */
   static protected function getHaystack() {
      
      //RAD-hack to allow just tablename(s) in $haystack, and find the appropriate fields on the fly
      if (is_string(static::$haystack)) {
         static::$haystack = array(static::$haystack); //not official form, but this will be picked up in the next step
      }
      if (is_array(static::$haystack)) {
         $h = array();
         foreach(static::$haystack as $table => $fields) {
            if (!is_array($fields)) {
               if (is_string($fields)) {
                  //$fields is tablename...
                  $q = "
                     SELECT
                        `COLUMN_NAME` AS `field`
                     FROM
                        `information_schema`.`COLUMNS`
                     WHERE
                        `TABLE_SCHEMA` = DATABASE()
                        AND `TABLE_NAME` = '".pxl_db_safe($fields)."'
                        AND `DATA_TYPE` IN ('varchar', 'longtext', 'mediumtext', 'smalltext', 'text', 'tinytext')
                  ";
                  $db = new Database(true);
                  $h[$fields] = $db->matrix($q, 'field', 'field');
               } else {
                  throw new InvalidArgumentException('Invalid haystack!');
               }
            } else {
               $h[$table] = $fields;
            }
         }
         static::$haystack = $h;
      }
      
      // regular code
      if (!is_array(static::$haystack)) {
         throw new InvalidArgumentException('Haystack is not an array!');
      }
      foreach (static::$haystack as $table => $fields) {
         if (!is_array($fields)) {
            throw new InvalidArgumentException('No fields defined in haystack!');
         }
      }
      return static::$haystack;
   }
}