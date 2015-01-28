<?php
	header('Content-type: application/json');
	error_reporting(E_ALL | E_STRICT);
	ini_set('display_errors', true);
	
	$search = "";	
	$query = "1 = 0"; // Force no results for empty search
	$itemnumber = 1;
	if (isset($_GET["s"]))
	{
		$query = "";
		$first = true;
		$search = urldecode($_GET["s"]);
		$search = str_replace("'", "", $search);
		$search = str_replace("\n", "", $search);
		$terms = explode(" ", $search);

		for ($x = 0; $x < count($terms); $x++)
		{
			if (strpos($terms[$x], '#') !== FALSE || strpos($terms[$x], '-') !== FALSE || strpos($terms[$x], '&') !== FALSE)
			{
				$terms[$x] = "\"" . $terms[$x] . "\"";
			}
		}

		$search_processed = implode(" ", $terms);
		$query = $query . "(ftc MATCH '" . $search_processed . "')";
		
	}
	
	
	// Default to rebuilding the search data
	$rebuildsearchdata = "1";
	$pathToDb = $_SERVER['DOCUMENT_ROOT'] . '\blog\content\data\ghost-dev.db';
	
	
		
	
	$db = new SQLite3($pathToDb);
	
	
	
	$xresult = $db->query("PRAGMA auto_vacuum = NONE;");

	///// Create the update check table if it doesn't exist.
	$xresult = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='searchdata_update_check'");
	$xrows = array();
	$xrows = $xresult->fetchArray();

	if ($xrows[0] != "searchdata_update_check")
	{
		$xresult = $db->query("CREATE TABLE searchdata_update_check (last_update TEXT);");
		$db->exec("INSERT INTO searchdata_update_check (last_update) VALUES ('0');");
	} 
	
	///// Read last update value from update check table
	$xresult = $db->query("SELECT last_update FROM searchdata_update_check;");
	$xrows = array();
	$xrows = $xresult->fetchArray();

	///// Look for posts that have been updated after last update check date
	$yresult = $db->query("SELECT updated_at FROM posts ORDER BY updated_at DESC LIMIT 1;");
	$yrows = array();
	$yrows = $yresult->fetchArray();

	if (isset($xrows) && isset($yrows))
	{
		if ($xrows[0] == $yrows[0])
		{
			// If newer updates do not exist, no need to update
			$rebuildsearchdata = "0";
		}
	}

	///// Make sure searchdata virtual table exists...
	$zresult = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='searchdata'");
	$zrows = array();
	$zrows = $zresult->fetchArray();

	if ($zrows[0] != "searchdata")
	{
		// If searchdata table doesn't exist, force a rebuild
		$rebuildsearchdata = "1";
	}

	function sql_preview($html)
	{
		$strippedHtml = strip_tags($html);
		$preview = substr($strippedHtml,0, 160);
		return $preview;
	}
	
	///// Rebuild search data if necessary
	if ($rebuildsearchdata == "1")
	{
		$db->createFunction('preview', 'sql_preview');

		$db->exec("UPDATE searchdata_update_check SET last_update = '" . $yrows[0] . "';");

		$xresult = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='searchdata'");
		$xrows = array();
		$xrows = $xresult->fetchArray();

		if ($xrows[0] != "searchdata")
		{
			// Essentially create a copy of the important post columns, with a new "ftc" column. We'll fill that with an aggregate of title and post content for searching against because FTS can only MATCH against one column. We use the other columns for rendering out the results list.
			$xresult = $db->query("CREATE VIRTUAL TABLE searchdata USING fts4(title, meta_description, slug, author_id, published_at, status, markdown,search_preview, ftc);");
		}

		$xresult = $db->query("DELETE FROM searchdata;");

		// Create the search data with aggregate search column: CAKE.
		$xresult = $db->query("INSERT INTO searchdata (title, meta_description, slug, author_id, published_at, status, markdown, search_preview, ftc) SELECT title, meta_description, slug, author_id, published_at, status, markdown,preview(html), title || ' ' || markdown AS ftc FROM posts;");
	}
	
	
	
	
	function sql_rank($aMatchInfo)
	{
		$iSize = 4;
		$iPhrase = (int) 0;   // Current phrase
		$score = (double)0.0; // Value to return

		/* Check that the number of arguments passed to this function is correct.
		** If not, jump to wrong_number_args. Set aMatchinfo to point to the array
		** of unsigned integer values returned by FTS function matchinfo. Set
		** nPhrase to contain the number of reportable phrases in the users full-text
		** query, and nCol to the number of columns in the table.
		*/
		$aMatchInfo = (string) func_get_arg(0);
		$nPhrase = ord(substr($aMatchInfo, 0, $iSize));
		$nCol = ord(substr($aMatchInfo, $iSize, $iSize));
		if (func_num_args() > (1 + $nCol))
		{
			throw new Exception("Invalid number of arguments : ".$nCol);
		}

		// Iterate through each phrase in the users query.
		for ($iPhrase = 0; $iPhrase < $nPhrase; $iPhrase++)
		{
			$iCol = (int) 0; // Current column

			/* Now iterate through each column in the users query. For each column,
			** increment the relevancy score by:
			**
			**   (<hit count> / <global hit count>) * <column weight>
			**
			** aPhraseinfo[] points to the start of the data for phrase iPhrase. So
			** the hit count and global hit counts for each column are found in
			** aPhraseinfo[iCol*3] and aPhraseinfo[iCol*3+1], respectively.
			*/
			$aPhraseinfo = substr($aMatchInfo, (2 + $iPhrase * $nCol * 3) * $iSize);

			for ($iCol = 0; $iCol < $nCol; $iCol++)
			{
				$nHitCount = ord(substr($aPhraseinfo, 3 * $iCol * $iSize, $iSize));
				$nGlobalHitCount = ord(substr($aPhraseinfo, (3 * $iCol + 1) * $iSize, $iSize));
				$weight = ($iCol < func_num_args() - 1) ? (double) func_get_arg($iCol + 1) : 0;

				if ($nHitCount > 0 && $nGlobalHitCount > 0)
				{
					$score += ((double)$nHitCount / (double)$nGlobalHitCount) * $weight;
				}
			}
		}

		return $score;
	}
	
	
	///// Search the virtual searchdata table...

	$db->createFunction('rank', 'sql_rank');
	
	
	$result = $db->query("SELECT title, meta_description, slug, author_id, published_at, search_preview, rank(matchinfo(searchdata), 0, 1.0, 0.5) AS score FROM searchdata WHERE status='published' AND " . $query . " ORDER BY score DESC LIMIT 99");

	$rows = array();
	$foundrows = false;
	$jsonresults = array();
	
	
	while ($rows = $result->fetchArray(SQLITE3_ASSOC))
	{
	
    $foundrows = true;
    $author_result = $db->query("SELECT name, slug FROM users WHERE id=" . $rows["author_id"] . " LIMIT 1");
    $author_rows = array();
    $author_rows = $author_result->fetchArray();	
	
	$jsonresults[] = $rows;		
	}
	
	echo json_encode($jsonresults);
	
	$db->close();

	  
	
?>
