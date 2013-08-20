<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

/*
* Keyword Search search engine
*
* Author: Jeff Rynhart
*
* University of Denver, May 2013
*
*/
class KeywordSearch {

    private $textBody = null;

    private $suffixes = array("able", "ac", "al", "an", "cy", "en", "ence", "er", "ed", "es", "ful", "ing", "ion", "ious", "less", "ly", "ment", "ness", "or", "ship",
    	 					  "sion", "some", "tion", "tude", "ty", "ular", "uous", "ure", "ward", "ware", "wise", "y", "e", "s", "ance", "ant", "ar", "ary", "ee", "est");

    function __construct($text = null) 
    {
		$this->textBody = $text;
    }

    public function setTextBody($text)
    {
    	$this->textBody = $text;
    }

    /*
  	 * 	Instantiate keywordSearch object with a text file as default.  This file will be searched if not null; a haystack file does not need to be present.
  	 *	
  	 *	Search an external file by passing it in as a second argument.
  	 *
  	 *	Will return an object of key:value pairs: where the key is the input keyword, and the value is an array of int positions of the text string where the keywords were located.
  	*/
    public function keywordSearch($needle, $haystack = null) 
	{
	    // Validate input
	    if($needle == "")
	    {
	    	echo "Error: No keyword(s) entered!";
	    	return -1;
	    }

	    // Use text passed in as function argument if present.  If not, use the textBody property
	    if($haystack != null)
	    	$text = strtolower($haystack);	
	    else if($this->textBody != null)
	    	$text = strtolower($this->textBody);
	   	else
	   	{
	   		echo "Error: No text to search!";
	   		return -1;
	   	}

	   	// Determine if the keyword 'needle' has been enclosed in single or double quotes.  
	   	// If so, return it as a single string keyword submission.  If not, explode the string and search for each keyword separately.
	   	// This function contains a 'stop-word' remover: eliminates common words that search engines can safely ignore.
	   	$keywordArray = $this->processKeywordString($needle);

		$resultsObj = null;

		foreach($keywordArray as $keyword)	
		{
			$posArray = array();
			$posArray2 = array();

			// Begin by searching for the raw keyword in the text
		    $posArray = $this->search($keyword,$text);	   

	        // Double characters are unnecessary info!
	        $textNoDoubles = $this->removeDoubleCharsFromString($text);
	        $keywordNoDoubles = $this->removeDoubleCharsFromString($keyword);

	        // Next, remove any suffix and run the keyword root through the search function.
	        // This will also call a suffix search on the keyword root to 'try on' different suffixes...
	        $posArray2  = $this->rootSearch($keywordNoDoubles,$textNoDoubles);
	        if(!empty($posArray2))
	        {
	        	$posArray = array_merge($posArray2,$posArray);
	        }
 
 			// After that, try to add different suffixes to the keyword itself...
 			$posArray2 = $this->suffixSearch($keywordNoDoubles,$textNoDoubles);
        	if(!empty($posArray2))
        	{
        		$posArray = array_merge($posArray2,$posArray);
        	}

		    // Add any hits to the result set object, and remove any duplicate hits
		    if(!empty($posArray))
		    {
		    	$resultsObj[$keyword] = array_unique($posArray);
		    }
		}

	    return $resultsObj;     
	}

    // This module adds intelligence to the php 'stripos' function.  It will ensure a hit on a word segment does not return a find.
    // Example:  A search for 'rat' should not return a hit on 'ration', 'gratitude' or 'brat'
    private function search($keyword, $text)
    {
    	$wordPos = null;
    	$wordPosArray = array();
	    $wordLength = strlen($keyword);

	    // Test 
	    //echo $keyword . "<br />";

	    // If a string segment is bounded by any of these chars, it is most likely not a segment of another word
	    $okP_Chars    	= array( "\r", "\n", ' ', ',', '-', ':', '(', '[', '{', '.', ord("") );
	    $okF_Chars		= array( "\r", "\n", ' ', ',', '-', ':', '(', '[', '{', '.', ord("") );

	    // The core
	    $wordPos = stripos($text, $keyword);

	    // Filter out any undesirable hits
	    while($wordPos !== false && $wordPos >= 0)
	    {
	        if($wordPos == 0)
	        	$precedingChar = ":";  	
	        else
	        	$precedingChar = $text[$wordPos-1];
	        	
	        $followingChar = $text[$wordPos + $wordLength];

	        // Make sure the found segment's preceding and following characters are ok.  
	        if(in_array($precedingChar, $okP_Chars) && in_array($followingChar, $okF_Chars))
	        {
	        	// It has passed the test, add it to the found array
	        	array_push($wordPosArray,$wordPos);

	        	// Are there any more?  Keep on looking through the text, starting after the last found word...
	        	$wordPos = stripos($text, $keyword, ($wordPos + $wordLength));
	        } 
	    }

	    return $wordPosArray;
    }

	// Removes suffix from root
	private function rootSearch($keyword, $text)
	{
	    $wordPos = null;
	    $wordPosArray = array();
	    $WordPosArray2 = array();

	    $length = strlen($keyword);
    
	    // Grab the last 3 characters of the keyword and check if they are a known suffix.  If so, chop them off and run the root through search()
	    if($length > 3)
	    {
	        $sub = substr($keyword, -3);
	        if(in_array($sub, $this->suffixes))
	        {
	            $keySuffix_3 = substr($keyword, 0, $length-3);
	            $wordPosArray = $this->search($keySuffix_3, $text);

	            // As long as we have the root, might as well try on some different suffixes
	            $WordPosArray2 = $this->suffixSearch($keySuffix_3, $text);

	            if(!empty($WordPosArray2))
	            	$wordPosArray = array_merge($WordPosArray2,$wordPosArray);	
	        }
	    }
	        
	    // Grab the last 2 characters of the keyword and check if they are a known suffix.  If so, chop them off and run the root through search()
	    if($wordPos == null && $length > 2)
	    {
	        $sub = substr($keyword, -2);
	        if(in_array($sub, $this->suffixes))
	        {
	            $keySuffix_2 = substr($keyword, 0, $length-2);
	            $WordPosArray2 = $this->search($keySuffix_2, $text);

	            if(!empty($WordPosArray2))
	            	$wordPosArray = array_merge($WordPosArray2,$wordPosArray);	

	            // As long as we have the root, might as well try on some different suffixes
	            $WordPosArray2 = $this->suffixSearch($keySuffix_2, $text);

	            if(!empty($WordPosArray2))
	            	$wordPosArray = array_merge($WordPosArray2,$wordPosArray);	       
	        }
	    }
	    
	    // Remove single character suffixes... give it a shot
	    if($wordPos == null && $length > 1)
	    {
	        $sub = substr($keyword, -1);
	        if($sub == 'y' || $sub == 'e'|| $sub == 's')
	        {
	            $keyNoS = substr($keyword, 0, $length-1);
	            $WordPosArray2 = $this->search($keyNoS, $text);

	            $wordPosArray = array_merge($WordPosArray2,$wordPosArray);

	            if(!empty($WordPosArray2))
	            	$WordPosArray2 = $this->suffixSearch($keyNoS, $text);

	            if(!empty($WordPosArray2))
	            	$wordPosArray = array_merge($WordPosArray2,$wordPosArray);	
	        }
	    }

	    return array_unique($wordPosArray);
	}

	// Loop through known suffixes, attaching each to keyword before dropping it into search()
	private function suffixSearch($keyword, $text)
	{
	    $wordPos = null;
	    $wordPosArray = array();

	    foreach($this->suffixes as $suffix)
	    {
	        $addSuffix = $keyword . $suffix;					
	        $wordPosArray = $this->search($addSuffix,$text);

	        $sub = substr($keyword, -1);
	        if($sub == 'y' || $sub == 'e'|| $sub == 's')
	        {
	        	$length = strlen($keyword); 
	        	$removeLast = substr($keyword, 0, $length-1);

	        	$addSuffix = $removeLast . $suffix;
			
	        	$WordPosArray = $this->search($addSuffix,$text);
	        }
	    }

	    return $wordPosArray;
	}

	private function removeDoubleCharsFromString($string)
	{
	    $length = strlen($string);
	    $string2 = $string . " ";

	    for( $i=0; $i < $length; $i++ )
	    {
	        $ichar = $string2{$i}; 
	        $jchar = $string2{$i + 1}; 
	        
	        if( $ichar == $jchar )
	        {
	            $string[$i + 1] = ""; 
	        }
	    }
	    return $string;
	}

    private function processKeywordString($searchKeywordString) 
	{
		// Create array of individual words within search string.  If quotations are used around search terms, treat them as one word.
		$searchKeywords = array();

		if(substr($searchKeywordString, 0, 1) == '"' || substr($searchKeywordString, 0, 1) == "'")
			$searchKeywords[0] = $this->removeQuotes($searchKeywordString);
		else
			$searchKeywords = explode(" ", $searchKeywordString); 

		// Remove 'stop' words from the search token array
		return $this->removeStopWords($searchKeywords); 
	}

	private function removeQuotes($string)
	{
		return substr($string, 1, -1);
	}

	private function removeStopWords($wordArray)
	{
		$stopWords = array('a','able','about','across','after','all','almost','also','am','among','an','and','any','are','as','at','be','because','been','but','by','can','cannot','could','dear','did','do','does',
						'either','else','ever','every','for','from','get','got','had','has','have','he','her','hers','him','his','how','however','i','if','in','into','is','it','its','just','least','let','like',
						'likely','may','me','might','most','must','my','neither','no','nor','not','of','off','often','on','only','or','other','our','own','rather','said','say','says','she','should','since',
						'so','some','than','that','the','their','them','then','there','these','they','this','tis','to','too','twas','us','wants','was','we','were','what','when','where','which','while','who',
						'whom','why','will','with','would','yet','you','your');

		foreach($wordArray as $word)
		{
			$wordLower = strtolower($word);

			if(strlen($wordLower) < 3)
				unset($wordArray[ array_search($word,$wordArray) ]);

			if(in_array($wordLower,$stopWords))
			{
				unset($wordArray[ array_search($word,$wordArray) ]);
			}		
		}

		return $wordArray;
	}
}