<?php

# removes annotation mark-up from elasticsearch result highlights as well as full documents, adds <em> tags
# around hit terms, also adds the relevant "class" if hit terms are from the filter categories.
function remove_markup($text) {
	$splitters = array("](_hit_term=", "](");
	$strings = $text;
	$first_splitter = True;
	foreach ($splitters as $splitter) {
		# find the hits, encapsulate them in <em>s if hit term. 
	    $strings = explode($splitter, $strings, 2);
	    # result example "was [unable" "371151008) to"
	    while (count($strings) > 1) {
	    	# separate the hit term - explode on first [ from the right, so use strrev
	    	$front = explode("[", strrev($strings[0]), 2);
	    	# $front is now reversed
	    	if ($first_splitter) {
	    		# add <em> tags around hit term while reversing strings back
	    		# if term is part of filter query, add filter class to <em> tags
	    		if (isset($_SESSION['filters'])) {
	    			// many concepts inside the highlight or just one?
	    			$before_bracket = explode(")", $strings[1], 2);
	    			$before_ampersand = explode("&", $strings[1], 2);
	    				// if several concept inside
	    			if (count($before_ampersand) > 1 
	    					&& strlen($before_ampersand[0]) < strlen($before_bracket[0])) {
	    				$concept = $before_ampersand[0];
	    				// one concept inside
	    			} else {
	    				$concept = $before_bracket[0];
	    			}
	    				// is concept part of the filters?
	    			if (array_key_exists($concept, $_SESSION['filter_map'])) {
	    				$beginning = strrev($front[1]) . '<span class="'.$_SESSION['filter_map'][$concept].'">' . strrev($front[0]) . "</span>";
	    			} else {
	    				// not part of filters
	    				$beginning = strrev($front[1]) . "<em>" . strrev($front[0]) . "</em>";
	    			}
	    			// no filters present
	    		} else {
	    			$beginning = strrev($front[1]) . "<em>" . strrev($front[0]) . "</em>";
	    		}
	    	} else {
	    		# not adding tags as term is not a hit term
	    		$beginning = strrev($front[1]) . strrev($front[0]);
	    	}
	        #get rid of annotations of the term
	    	$end = explode(")", $strings[1], 2);
	        $result = $beginning . $end[1];
	        $strings = explode($splitter, $result, 2);
	    }
	    $first_splitter = False;
	    $strings = $strings[0];
	}
	return $strings;
}
