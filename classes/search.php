<?php

namespace Search;

use FuelException;

class Search {
	
	protected static function find($search_term = '') {
		return new static($search_term);
	}
	
	/**
    * Calculate the distance between $word and $search_term based on the Damerau-Levenshtein algorithm.
    *
    * Credits for this algorithm implementation go out to Ronald Mansveld, who worked hard to make it efficient
    * @author 	Ronald Mansveld
    * 
    * @param	string	$word			The word to check
    * @param	string	$searchterm		The searchterm to check word against
    * @param	int		$cost_limit		The maximum cost we are looking for (so we can break early on words with higher costs)
    * @return	int						The distance between $word and $search_term
    *
    * @todo		incorporate the prefix and suffix matching into the score (to get more relevant results)
    * @todo		reformat and comment to make sure everyone can see what does what and why
    */
	protected static function get_score($word, $search_term, $cost_limit) {
		if ($word == $searchterm) return 0;
      
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
	
	protected $search_term = null;
	protected $search_data = null;
	
	public function __construct($search_term = '') {
		if (!is_string($search_term)) throw new FuelException('The search term must be a string');
		
		
	}
	
	public function in($data) {
		
	}
	
	public function execute() {
		
	}
}