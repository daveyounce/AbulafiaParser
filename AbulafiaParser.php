<?php
include Parser;

class AbulafiaParser{
	public static function init( Parser $parser ) {
		$parser->setHook( 'sgtable', [ self::class, 'parseForDb' ] );
		$parser->setHook( 'sgdisplay', [ self::class, 'renderRandom' ] );
	}
	
	public static function tableslicer(){
	     $wgHooks['ArticleSave'][] = [ self::class, 'parseForDb'] ;
    }	
	
	public static function parseForDb($input, $argv, $parser ){
		//get db
	    $db =& wfGetDB( DB_SLAVE );
            $parser->disableCache();	
	    $ret='';
	
	    $tablename = $parser->mTitle->getText();	
	
	     //if the table has any entries in the db, find & delete them
	     $deletesql = 'DELETE FROM abulafia_entries2 WHERE sgtable=\''.mysqli_escape_string($tablename).'\'';
	     $result = $db->safeQuery($deletesql);
	
	
	     //split it into subtables
	     $subtables = explode("\n;",$input);
	     foreach($subtables as $subtable){
		    //strip off subtable name as $subtablename
		    //$subtable = substr($subtable,2);
			$newline=strpos($subtable,"\n");
			$subtablename = substr($subtable,0,$newline);
			$subtable = substr($subtable,$newline+1);
			//divide the rest of the subtable into entries
			$entries = explode("\n",$subtable);
			$rel_prob = 1;
			$text = 'howdy';
			$rel_array=array();
			foreach ($entries as $entry){
				$firstcomma = strpos($entry,",");
				if ($firstcomma === false){
					//$ret .= 'No comma found on line: '.$entry;
				} else {
					$relprobsection = substr($entry,0,$firstcomma);
					$entrysection = substr($entry,$firstcomma+1);
					for($i=0;$i<$relprobsection;$i++)
					{
						$rel_array[] = $entrysection;
					}
				}
				//serialize & insert
			}
			$serializedrelarray = serialize($rel_array);
			$query = 'INSERT INTO abulafia_entries2 values (DEFAULT,\''.mysqli_escape_string($tablename).'\',\''.mysqli_escape_string($subtablename).'\',\''.mysqli_escape_string($serializedrelarray).'\')';
			$result=$db->query($query);
				
		    }
	    }
	    
	    public static function callFromDb($tablename,$page,$calldepth){
			//get db
			$db =& wfGetDB( DB_SLAVE );
			
			
			//if $tablename has no '.', its supposed to be local
			$dotposition=strpos($tablename,".");
			if ($dotposition === false){
				//assume local, no '.' was present
				$subtablename = $tablename;
				$tablename = $page;
			} else{
				$subtablename = substr($tablename,$dotposition+1);
				$tablename = substr($tablename,0,$dotposition);
				$page = $tablename;
				//$output = 'received '.$tablename.'-'.$subtablename;
			}
			
			//try to find entries in the db that correspond to $tablename
			$sql = 'SELECT * FROM abulafia_entries2 WHERE sgtable = \''.mysqli_escape_string($tablename).'\' AND subtable = \''.mysqli_escape_string($subtablename).'\'';
			if ((strpos($tablename,'SELECT'))||(strpos($subtablename,'SELECT'))){return;}
			$result = $db->safeQuery($sql);
			
			//NEED: Error Handling!
			
			//construct array of possible entries
			while($row = $db->fetchRow($result)){
				$randtable = unserialize($row['serializedarray']);
				
				//for($i = 0; $i < $row['rel_prob']; $i++){
					//$randtable[] = $row['entry'];
				//}
			}
			if (sizeof($randtable)>0){
		            $choice = mt_rand(0,sizeof($randtable)-1);
			    //construct output string, calling callFromDb recursively if [table] encountered
			    //echo $randtable[$choice];
			    if ($calldepth<150){
				$output .= self.sgProcessEntry($randtable[$choice],$page,$calldepth);
			    } else { //too many recursions!
				$output = "Recursive call depth more than 150 in ".$tablename.".".$page.". Please check to make sure your table calls are not just calling themselves.";
		               } 
			}
			//output string
		    return $output;
		}

	    
	    public static function sgProcessEntry($incoming,$page,$calldepth){
			//read through incoming string until table encountered, then callfromDB on it, then continue with string!
			$output='';
			if (strpos($incoming,'[') === false)//no tables, feed on through
			{
				$output = $incoming;
			} else {
				//$output = $incoming;
				$done = false;
				$whatsleft = $incoming;
				while($done == false){
					$openbracket = strpos($whatsleft,'[');
					if ($openbracket === false){
						$done = true;
						//$output .= $whatsleft;
					} else{
						$output .= substr($whatsleft,0,strpos($whatsleft,'['));
						$whatsleft = substr($whatsleft,strpos($whatsleft,'[')+1);
					}
					$closebracket = strpos($whatsleft,']');
					if ($closebracket === false){//tablename never terminates
						$done = true;
						$output .= $whatsleft;
					} else { //tablename ends, pass to callfromdb
						$tablename = substr($whatsleft,0,$closebracket);
						$whatsleft = substr($whatsleft,$closebracket+1);
						//iterate calldepth on this callFromDb
						$output .= self.callFromDb($tablename,$page,$calldepth+1);
						//$output .= '(called ['.$tablename.'])';
					}
					if ($whatsleft == ''){ $done = true;}			
				}
			}
			return $output;
		}

        public static function renderRandom( $input, $argv, $parser ) {
			$iterations = $argv["iterations"];
			$linebreak = $argv["linebreak"];
			if ($linebreak=='') 
			{
				$linebreak="true";
			}else if ($linebreak=="false")
			{
				$linebreak=false;
			}
			$outputstr = '';
			if((0 < $iterations)&&($iterations < 100)){
				for($i=0;$i<$iterations;$i++){
					//$outputstr.=callFromDb($parser->mTitle->getText().'.main');
					$outputstr.=self.sgProcessEntry($input,$parser->mTitle->getText(),0);
					//$outputstr.=callFromDb($parser->mTitle->getText().'.main',$parser);
					if ($linebreak=="true") $outputstr.='<br/>';
					
				}				
			} else {return 'Invalid argument for \'iterations\': '.$argv["iterations"];}
			
			return $parser->internalParse($outputstr);
		}
	
}	
	

?>
