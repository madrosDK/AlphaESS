<?php
echo "Configuration file generator:";


$devices = array( 'ABB_B23_SILVER', 'ABB_B23_STEEL' );

foreach ($devices as $device){
    $file_in = './'.$device.'.csv';
    $file_out = '../devices/'.$device.'.json';
        
    $rows = array_map('str_getcsv_map', file($file_in));

    $header = array_shift($rows);
    $variables = array();

    foreach ($rows as $row) {
      $variables[] = array_combine($header, $row);
    }

    foreach($variables as $i=>$row){

        foreach ($row as $key=>$value){
            if ($key == 'ReadOut') $value = (boolean) $value;
            if ($key == 'ReadAddress') $value = (int)  $value;
            if ($key == 'WriteFunctionCode') $value  = (int) $value;
            if ($key == 'WriteAddress') $value  = (int) $value;
            if ($key == 'ReadFunctionCode') $value  = (int) $value;				
            if ($key == 'Size') $value  = (int) $value;
            if ($key == 'Factor') $value = (float) $value;
            // General
            $variables[$i][$key] = $value;
        }
           
    }

    $json_out = json_encode( $variables, JSON_PRETTY_PRINT );
    write_file( $file_out, $json_out);

}


function write_file( $file, $data ){
    if(!is_dir( dirname($file))){
        mkdir(dirname($file) ,0777, true);
    }    
    $fh = fopen($file, 'w');
    fwrite($fh, $data );
    fclose($fh);
    echo "\ngenerated: ".$file;
}
function str_getcsv_map( $data ){
	return str_getcsv( $data, ";");
}

function GetVariableProfileByUnit( $unit )
{
    switch ($unit) 
    {
        case "W":
            return "~Watt";
        case "kWh":
            return "~Electricity";
        case "V":
            return "~Volt.230";
        case "A":
            return "~Ampere";
        case "Hz":
            return "~Hertz.50";
        case "°":
            return "PhaseAngle";
        case "currency":
            return "~Euro";
        case "1":
                return "";
        default:
            return $unit;

    }
} 

function GetVariableType( $variable )
{
    switch ($variable['ReadFunctionCode']){
        case 0:
            $varType = 0;
            $variable['ReadOut'] = false;
        case 1:
            $varType = 0;
            break;                        
        case 2:
            $varType = 0;
            break;                        
        case 3:
            if ($variable['Factor'] != 0 ){
                //Float
                $varType = 2;
            } else {
                //Integer
                $varType = 1;
            }
            break;
        case 4:
            if ($variable['Factor'] != 0 ){
                //Float
                $varType = 2;
            } else {
                //Integer
                $varType = 1;
            }
            break;                        
    }
    return $varType;
}

function jsonToCSV($jfilename, $cfilename)
{
    if (($json = file_get_contents($jfilename)) == false)
        die('Error reading json file...');
    $data = json_decode($json, true);
    $fp = fopen($cfilename, 'w');
    $header = false;
    foreach ($data as $row)
    {
        if (empty($header))
        {
            $header = array_keys($row);
            fputcsv($fp, $header);
            $header = array_flip($header);
        }
        fputcsv($fp, array_merge($header, $row));
    }
    fclose($fp);
    return;
}