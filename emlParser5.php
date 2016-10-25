<?php
$dir = isset($argv[2]) ? $argv[2] : "./";
$switch = isset($argv[3]) ? $argv[3] : "";
$output = isset($argv[4]) ? $argv[4] : "";

ini_set('memory_limit', '1024M'); // or you could use 1G

$filter = $argv[1];

$filters = explode(",", $filter);

for ($i=0; $i<sizeof($filters); $i++)
{
    $filters[$i] = strtolower($filters[$i]);    
}            

//$filters = array(" ") + $filters;
array_unshift($filters, " ");

error_reporting( error_reporting() & ~E_NOTICE );

function rglob($pattern, $flags = 0) {
    $files = glob($pattern, $flags); 
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        echo "Dir: $dir\n";
        $files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
    }
    return $files;
}

function ParseEml($eml)
{
	$raw = "";
	$handle = @fopen($eml, "r");
	if ($handle) {
		while (($buffer = fgets($handle, 4096)) !== false) {
			$raw .= $buffer;
			// echo $buffer;
		}
		if (!feof($handle)) {
			//echo "Error: unexpected fgets() fail\n";
		}
		fclose($handle);
	}
	else
	{
		return;
	}
	
    //$raw = file_get_contents($eml);
    $lines = explode("\n", $raw);

    $header = "";
    $value = "";

    $headers = array();
    $index = 0;
    
    foreach ($lines as $line)
    {    
        if (trim($line) == "") 
        {
            if ($header != "")
            {
                $headers[strtolower("$header")] .= preg_replace('/\\t/', ' ', $value);
            }
            break;
        }
        
        $first = substr($line, 0, 1);
        if (trim($first) == "")
        {                          
            $value .= " " . trim($line);
        }
        else
        {
            preg_match("/[^a-zA-Z0-9]/", $first, $match);            
            
            if (sizeof($match) > 0)
            {
                $value .= " " . rtrim($line);
            }
            else
            {
                if ($header != "")
                {
                    $headers[strtolower("$header")] .= " " . rtrim(preg_replace('/\\t/', ' ', $value));
                }

                $pos = strpos($line, ": ");
                $header = substr($line, 0, $pos);
                
                if ($index == 0 && strtolower($header) != "return-path") return null;
                $value = rtrim(substr($line, $pos + 2, strlen($line) - ($pos + 2)));   
            }     
        }
        
        $index++;
        
    }
    
    if ($header != "")
    {
        $headers[strtolower("$header")] .= preg_replace('/\\t/', ' ', $value);
    }
    

    return $headers;
}

function ClearHeader()
{
    global $record;
    foreach ($record as $key=>$value)
    {
        $records[$key] = "";
    }
}
 
$parses = array();
$record = array();

$files = glob($dir, GLOB_BRACE);

$i = 1;

// Importing records from source now
foreach ($files as $file)
{
    $i++;
    $header = ParseEml("$file");
    
    if ($header == null) continue;
    
    $exist = false;
    foreach($header as $key=>$value) 
    {
        if ($filter == "ALL")
        {
            $record[$key] = "";
            $exist = true;
        }
        else
        {
            if (in_array($key, $filters))
            {
                $record[$key] = "";                
                $exist = true;
            }
        }
    }
    //print_r($header);
    if ($exist)
    {
        $header = array_merge( array(" " => $file), $header);
        $parses[] = $header;
    }
             
}

function GetSummary($line)
{
    $pos = strpos($line, "test[");
    
}

// Filtering columns
$displays = array();
$summary = array();

foreach ($parses as $parse)
{
    ClearHeader();
    foreach ($parse as $key=>$value)
    {
        if ($filter == "ALL")
        {
            $record[$key] = $value;
        }
        else
        {
            if (in_array($key, $filters))
            {
                $record[$key] = $value;
            }            
        }
        
        if (strtolower($key) == "x-spam-status")
        {
            // Process summary
        }
    }
    //print_r($parse);
    ksort($record);
    $displays[] = $record;
}

// Create Header Row
ClearHeader();

$line = "";
foreach ($record as $key=>$value)
{
    $line .= "$key\t";
}

if ($switch == "-f") 
    file_put_contents($output, "$line\n");
else
    echo "$line\n";

//exit($line);
$scores = array();

foreach ($displays as $display)
{
    $line = "";
    foreach ($display as $key=>$value)
    {
		if (strtolower($key) == 'x-spam-status')
		{
			// find tests=
			$posStart = strpos($value, "tests=");
			$posEnded = strpos($value, "TOTAL_SCORE");
			
			$content = substr($value, $posStart + 6, $posEnded - $posStart - 8);
			
			$content = preg_replace("/[^a-zA-Z0-9:.,_ ]/", "", $content);
			$lists = explode(",", trim($content));
			foreach ($lists as $list)
			{
				$list = trim($list);
				
				$scores["$list"] = isset($scores["$list"]) ?  
										($scores["$list"] + 1) : 1;
			}

		}
        $line .= "$value\t";    
    }

    if ($switch == "-f") 
        file_put_contents($output, "$line\n", FILE_APPEND);
    else
	{
        echo "$line\n";      	
	}		
}

arsort($scores);

foreach ($scores as $key=>$value)
{
	echo "$value\t$key\n";
}

echo "Total: " . sizeof($displays) . "\n";

?>
