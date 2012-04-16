<?php
/**
 * Search
 *
 * Search lets you search for stuff in data, allowing spelling mistakes!
 *
 * @package		Search
 * @version		0.5
 * @author		Jaap Rood / Ronald Mansveld
 * @license		MIT License
 * @link		http://github.com/JaapRood/fuel-search
 */


namespace Search;

class Search {

	protected $term = null;
	protected $data = null;
	protected $fields = array();
	protected $relevance = 75;
	protected $limit = null;
	protected $offset = null;

	private $_words = array();

	public static function find($search_term = '') {
		return new static($search_term);
	}

	public function __construct($search_term = '') {
		if (!is_string($search_term)) throw new InvalidArgumentException('The search term must be a string');

		$this->term = explode(' ', strtolower($search_term));

		return $this;
	}


	/**
	 * The data you want to search in. Accepts an array of arrays and an array
	 * of objects.
	 *
	 * @param	mixed	$data	The data to search through. An array of arrays, or an array of objects
	 * @return 	$this
	 */
	public function in($data) {
		if (!is_array($data)) {
			throw new InvalidArgumentException('Search only accepts an array of arrays, or an array of objects');
		}

		// lets make sure this is data we can search through, before we waste resources on trying
		foreach ($data as $key => $value) {
			if (!is_object($value) && !is_array($value)) {
				throw new InvalidArgumentException('Search only accepts an array of arrays, or an array of objects');
			}
		}

		$this->data = $data;
		// lets try and prevent the next step, because with big datasets, this could turn out to be pretty memory intensive
		//$this->_normalized_data = \Format::forge($data)->to_array();

		return $this;
	}

	/**
	 * The fields of the data entries you wan't to look into for the search term
	 *
	 * @param 	mixed	either an array of fields or a variable amount of strings
	 * @return 	$this
	 */
	public function by($fields = null) {
		if (!is_array($fields)) $fields = func_get_args();

		$this->fields = array_merge($this->fields, $fields);
		array_unique($this->fields);

		return $this;
	}

	/**
	 * Limit the amount of the results you'll get back
	 * @param 	int		$limit	the limit of results you want to get back
	 * @return 	$this
	 */
	public function limit($limit) {
		$this->limit = (int) $limit;

		return $this;
	}

	/**
	 * offset the results you'll get back
	 * @param 	int		$offset	the offset from which you want results back
	 * @return 	$this
	 */
	public function offset($offset) {
		$this->offset = (int) $offset;

		return $this;
	}

	/**
	 * Determine how well the results should relate to the searchterm by percentage.
	 * With a relevance of 50% using a 8 letter search term, results are included that
	 * need 4 transformations or less to get to the search term.
	 *
	 * @param	int		$relevance	percentage (0-100) of relevancy
	 * @return	$this
	 */
	public function relevance($relevance) {
		$relevance = (int) $relevance;

		if ($relevance > 100) $relevance = 100;
		if ($relevance < 1) $relevance = 1;

		$this->relevance = $relevance;

		return $this;
	}


	/**
	 * Executes the search that's been built. It retrieves the words out of the
	 * specified fields from the data. It then generates a score for each one of them,
	 * excludes the ones with a too low of a score and sort the results to return the data.
	 *
	 * @return	array	The search results
	 */
	public function execute() {
		if (empty($this->data)) { // if there is no data to work with
			return array(); // searching in nothing leads to nothing
		}

		$total_cost_limit = 0;
		$min_len = false;
		$max_len = false;

		foreach ($this->term as $term) {
			$cost_limit = (int) ceil( (strlen($term) * (100-$this->relevance) ) / 100);
			$total_cost_limit += $cost_limit;
			$min_len = $min_len ? min($min_len, strlen($term) - $cost_limit) : strlen($term) - $cost_limit; // anything that's the cost limit longer that search term, will score too low for sure
			$max_len = $max_len ? max($max_len, strlen($term) + $cost_limit) : strlen($term) + $cost_limit; // anything that's the cost limit longer that search term, will score too high for sure
		}
		
		$this->get_words($min_len, $max_len);
		$entry_scores = $this->get_entry_scores($total_cost_limit);

		$results = array();

		uasort($entry_scores, function($a, $b){ // sort the entries by score
			return ($a < $b) ? -1 : 1;
		});
		
		$offset = is_int($this->offset) ? $this->offset : 0;
		$limit = is_int($this->limit) ? $this->limit : null;
		
		
		if ($offset > 0 ||  is_int($limit)) { // if we have a offset or limit
			$entry_scores = array_slice($entry_scores, $offset, $limit, true);
		}
		
		foreach ($entry_scores as $entry_key => $score) {
			$results[$entry_key] = $this->data[$entry_key];
		}

		return $results;
	}


	/**
	 * Get's all the words from the specified fields in the data. they will be set
	 * with a reference to the data it belongs to so it can be linked back
	 *
	 * @param	int		the minimal length the returned words should be
	 * @param	int		the maximal length the returned words should be
	 * @return	void
	 */
	protected function get_words($min_length, $max_length) {
		if (empty($this->data) || empty($this->fields)) return array();

		$this->_words = array();

		foreach ($this->data as $entry_key => $entry) {
			if (!is_object($entry) && !is_array($entry)) continue; // if this is not either an array or an object, we won't be able to work with it
			$words_in_entry = array();

			foreach ($this->fields as $field) {
				if (is_array($entry)) { // if this entry is an array
					if (!array_key_exists($field, $entry)) continue; // if the field is not set in this entry, there is not much to find!

					$field_contents = $entry[$field];
				} else { // because of the if earlier statement, if it's not an array, it must be an object
					if (!isset($entry->$field)) continue; // if the field is not set in this entry, there is not much to find!

					$field_contents = $entry->$field;
				}

				$field_words = explode(' ', $field_contents);

				foreach ($field_words as $word) {
					$word = strtolower($word);

					if (isset($words_in_entry[$word])) continue; // if we already found this word in this entry, we don't need it again

					$word_length = strlen($word);

					if ($word_length >= $min_length && $word_length <= $max_length) {
						if (!isset($words[$word])) $words[$word] = array(); // add the word if it doesnt exist yet

						$this->_words[$word][] = array('key' => $entry_key);
					}
					$words_in_entry[$word] = true; // mark this word as done for this entry
				}
			}
		}
	}

	/**
	 * Determine the scores per entry. With multiple matching words, the best score goes
	 *
	 * @param	int		$total_cost_limit		the score limit the entry should meet to be included in results
	 * @return	array	arrays with entry keys and scores (entry_key => score)
	 */
	protected function get_entry_scores($total_cost_limit = 0) {
		$results = array(); // array of entry_key => score
		foreach ($this->_words as $word => $entries) {

			//run score for each word in searchterm, lowest score wins.
			$score = 0; //init score
			foreach ($this->term as $term) {
				$cost_limit = (int) ceil( (strlen($term) * (100-$this->relevance) ) / 100);
				$score += static::get_word_score($word, $term, $cost_limit + 1);
			}

			if ($score <= $total_cost_limit) { // if this word scores within our cost limit
				foreach ($entries as $entry) {
					if (!isset($results[$entry['key']]) || (isset($results[$entry['key']]) && $results[$entry['key']] > $score)) { // if his entries score improved
						$results[$entry['key']] = $score;
					}
				}
			}
		}

		return $results;
	}

	/**
    * Calculate the distance between $word and $search_term based on the Damerau-Levenshtein algorithm.
    *
    * Credits for this algorithm implementation go out to Ronald Mansveld, who worked hard to make it efficient
    * while creating useful results
    * @author 	Ronald Mansveld
    *
    * @param	string	$word			The word to check
    * @param	string	$search_term		The searchterm to check word against
    * @param	int		$cost_limit		The maximum cost we are looking for (so we can break early on words with higher costs)
    * @return	int						The distance between $word and $search_term
    *
    * @todo		incorporate the prefix and suffix matching into the score (to get more relevant results)
    * @todo		reformat and comment to make sure everyone can see what does what and why
    */
	protected static function get_word_score($word, $search_term, $cost_limit) {
		if ($word == $search_term) return 0;

		$len1 = strlen($word);
		$len2 = strlen($search_term);

		if ($len1 == 0) return $len2;
		if ($len2 == 0) return $len1;

		//strip common prefix
		$i = 0;
		do {
			if ($word{$i} != $search_term{$i}) break;
			$i++;
			$len1--;
			$len2--;
		} while ($len1 > 0 && $len2 > 0);

		if ($i > 0) {
		   $word = substr($word, $i);
		   $search_term = substr($search_term, $i);
		}

		//strip common suffix
		$i = 0;
		do {
		   if ($word{$len1-1} != $search_term{$len2-1}) break;
		   $i++;
		   $len1--;
		   $len2--;
		} while ($len1 > 0 && $len2 > 0);
		if ($i > 0) {
		   $word = substr($word, 0, $len1);
		   $search_term = substr($search_term, 0, $len2);
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
				$cost = $word{$i-1} == $search_term{$j-1} ? 0 : 1;
				$new = min(
						$matrix[$i-1][$j] + 1,
						$matrix[$i][$j-1] + 1,
						$matrix[$i-1][$j-1] + $cost
				);
				if ($i > 1 && $j > 1 && $word{$i-2} == $search_term{$j-1} && $word{$i-1} == $search_term{$j-2}) {
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
}