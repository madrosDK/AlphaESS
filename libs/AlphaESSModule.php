<?php

declare(strict_types=1);


class AlphaESS extends IPSModule
{

    const Timeout = 1000;
    const Sleep = 200;
    const Swap = false;


    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);
    }

    public function Create()
    {
        parent::Create();

        $this->RequireParent("{A5F663AB-C400-4FE5-B207-4D67CC030564}");

        $this->RegisterProfile(2, "kVarh", "Electricity", "", " kVarh", 0, 0, 0, 2);
        $this->RegisterProfile(2, "kVAh", "Electricity", "", " kVAh", 0, 0, 0, 2);
        $this->RegisterProfile(2, "W", "Electricity", "", " VA", 0, 0, 0, 2);
        $this->RegisterProfile(2, "VA", "Electricity", "", " VA", 0, 0, 0, 2);
        $this->RegisterProfile(2, "Var", "Electricity", "", " Var", 0, 0, 0, 2);
        $this->RegisterProfile(2, "kg", "Gauge", "", " kg", 0, 0, 0, 2);
        $this->RegisterProfile(2, "PhaseAngle", "Speedo", "", "°", -180, 180, 0, 2);

        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterTimer("UpdateTimer", 0, static::PREFIX ."_RequestRead(\$_IPS['TARGET']);");

        $variables = $this->GetDeviceTemplate( static::DeviceIdent );

        foreach ($variables as $index => $value)
        {
            $variables[$index]['VariableName'] = $this->Translate ( $variables[$index]['Quantity'] ) ." (". $this->Translate ( $variables[$index]['Channel'] ) .")";
        }

	    $this->RegisterPropertyString("Variables", json_encode ( $variables ) );

        $this->RegisterPropertyBoolean("ReadBlock", true);
    }


    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $variables = json_decode ( $this->ReadPropertyString("Variables"), true);

      //  $NewRows = $variables; //$this->GetDeviceTemplate( static::DeviceIdent ); //NEU
      //  $NewPos = 0;  //NEU

        //$variables = json_decode ( $this->ReadPropertyString("Variables"), true);

        foreach ($variables as $i=>$value)
        {
            $this->MaintainVariable($value['Ident'], $value['VariableName'], $value['VariableType'], $value['VariableProfile'], $value['ReadAddress'], $value['ReadOut']);

        } 
          /*  foreach ($NewRows as $Index => $Row) {    //NEU ab hier
              if ($value['Ident'] == str_replace(" ", "", $Row[0])) {
                unset($NewRows[$Index]);
              }
            }

            if ($NewPos < $variables[$index]['VariableName']) {
                $NewPos = $variables[$index]['VariableName'];
              } //bis hier


        }
        if (count($NewRows) != 0) { //neu ab hier
            foreach ($NewRows as $NewVariable) {
              $variables[$index]['VariableName'] = $this->Translate ( $variables[$index]['Quantity'] ) ." (". $this->Translate ( $variables[$index]['Channel'] ) .")";
            }
            IPS_SetProperty($this->InstanceID, 'Variables', json_encode($variables));
            IPS_ApplyChanges($this->InstanceID);
            return;

*/

        $this->MaintainVariable("ERROR__CONNECTION", "Error Connection",0, "~Alert", 1000, true);

        if ($this->ReadPropertyInteger("Interval") > 0)
            $this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("Interval") * 1000);
        else
            $this->SetTimerInterval("UpdateTimer", 0);
    }

    // Configuration for ModBus Gateway
    public function GetConfigurationForParent()
    {
        $config["SwapWords"] = static::Swap;
        return json_encode($config);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Form['actions'][0]['onClick'] = static::PREFIX . '_RequestRead($id);';
        return json_encode($Form);
    }


    public function RequestRead()
    {

        $startTime = microtime(true);

        $GatewayID = IPS_GetInstance($this->InstanceID)['ConnectionID'];

        $Gateway = IPS_GetInstance($GatewayID);
        if ($Gateway['InstanceStatus'] != 102)
        {
            SetValue($this->GetIDForIdent('ERROR__CONNECTION'), true);
            return false;
        }


        if ($Gateway['ConnectionID'] == 0)
        {
            SetValue($this->GetIDForIdent('ERROR__CONNECTION'), true);
            return false;
        }

        $IO = IPS_GetInstance($Gateway['ConnectionID']);
        $IOID = $IO['InstanceID'];

        if ($IO['InstanceStatus'] != 102)
        {
            SetValue($this->GetIDForIdent('ERROR__CONNECTION'), true);
            return false;
        }

        if (!$this->lock($IOID, static::Timeout))
        {
            SetValue($this->GetIDForIdent('ERROR__CONNECTION'), true);
            return false;
        }

        $result = $this->ReadData();

        IPS_Sleep( static::Sleep);

        $this->unlock($IOID);

        $time =  microtime(true) - $startTime;
        $this->SendDebug('ReadOut Time', $time, 0);

        SetValue($this->GetIDForIdent('ERROR__CONNECTION'), !$result);

        if ($result === false)
        {
            $this->SetStatus(200);
            return false;
        }

        $this->SetStatus(102);
        return true;
    }

    private function ReadData()
    {

        if ( $this->ReadPropertyBoolean("ReadBlock") )
        {

            $ModBusRegister = array();
            $variables = json_decode ( $this->ReadPropertyString("Variables"), true );

            foreach ($variables as $value)
            {
                $ModBusRegister[$value['ReadAddress']] = $value;
            }

            $RegisterSort = array();

            foreach ($ModBusRegister as $i=>$row)
            {
                $RegisterSort[(int)$row['ReadFunctionCode'] ][(int) $row['ReadAddress']] = $row;
            }

            $Blocks = array();
            $i = 0;
            foreach ($RegisterSort as $FunctionCode => $Registers)
            {

                $StartAddress = false;
                $LastAddress = false;
                $BlockRegisters = array();

                foreach ($Registers as $Register)
                {
                    if ( $Register['ReadOut'] == true)
                    {
                        $EndAddress = $Register['ReadAddress'] + $Register['Size']-1;
                        if ($StartAddress === false)
                        {
                            $StartAddress = $Register['ReadAddress'];
                        }
                        if ($LastAddress === false)
                        {
                            $LastAddress = $EndAddress;
                        }


                        if ( $EndAddress > ($StartAddress + 124) )
                        {
                            $Blocks[$i]['ReadFunctionCode'] = $FunctionCode;
                            $Blocks[$i]['Size'] = $LastAddress - $StartAddress + 1;
                            $Blocks[$i]['ReadAddress'] = $StartAddress;
                            $Blocks[$i]['Registers'] = $BlockRegisters;
                            $BlockRegisters = array();
                            $StartAddress = $Register['ReadAddress'];
                            $LastAddress = $EndAddress;
                            $i++;
                        } else
                        {
                            $LastAddress = $EndAddress;
                        }
                        $BlockRegisters[] = $Register;
                    }
                }
                $Blocks[$i]['ReadFunctionCode'] = $FunctionCode;
                $Blocks[$i]['Size'] = $LastAddress - $StartAddress + 1;
                $Blocks[$i]['ReadAddress'] = $StartAddress;
                $Blocks[$i]['Registers'] = $BlockRegisters;
            }


            foreach ($Blocks as $i=>$row)
            {

                $result = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => (int)$row['ReadFunctionCode'], "Address" => (int) $row['ReadAddress'], "Quantity" => (int) $row['Size'], "Data" => "")));

                if ($result === false)
                {
                    return false;

                } else
                {
                    $result = substr($result, 2);

                    foreach ($row['Registers'] as $register)
                    {
                        $start = ($register['ReadAddress'] - $row['ReadAddress']) * 2;
                        $length = ($register['Size']) * 2;
                        $value = $this->decode_binary_string(substr($result, $start, $length), $register['DataType'] ); //, $device['config']['SwapWords']

                        if ($value !== false)
                        {
                        if ((float) $register['Factor'] != 0)
                        {
                            $value = $value * (float)$register['Factor'];
                        }

                        $this->SetValueExt( $register, $value);
                        }

                    }

                }
            }

        }
        else
        {

            $ModBusRegister = json_decode($this->ReadPropertyString('Variables'), true);

            foreach ($ModBusRegister as $i=>$row)
            {
                if ((boolean)$ModBusRegister[$i]['ReadOut'] == true)
                {
                    $result = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => (int)$row['ReadFunctionCode'], "Address" => (int) $row['ReadAddress'], "Quantity" => (int) $row['Size'], "Data" => "")));

                    if ($result === false)
                    {
                        return false;

                    } else
                    {
                        if (strlen(str_replace('f', '', bin2hex(substr($result, 2))  )) == 0)
                        {
                            $value = 0;
                        }

                        $value = $this->decode_binary_string(substr($result, 2),$row['DataType'] );

                        if ($value !== false)
                        {
                        if ((float) $row['Factor'] != 0)
                        {
                            $value = $value * (float)$row['Factor'];
                        }

                        $this->SetValueExt($row, $value);
                        }
                    }

                }
            }
        }

        return true;

    }

    /**
     * Setzte eine IPS-Variableauf den Wert von $value.
     *
     * @param array $Variable Statusvariable
     * @param mixed $Value    Neuer Wert der Statusvariable.
     */
    protected function SetValueExt($Variable, $Value)
    {
        $id = @$this->GetIDForIdent($Variable['Ident']);
        if ($id == false) {
            $this->MaintainVariable($Variable['Ident'], $Variable['VariableName'], $Variable['VariableType'], $Variable['VariableProfile'], $Variable['ReadAddress'], $Variable['ReadOut']);
        }
        $this->SetValue($Variable['Ident'], $Value);
        return true;
    }

    private function GetDeviceTemplate( string $deviceIdent )
    {

        $file = __DIR__ . "/../libs/devices/".$deviceIdent.".json";
        if (is_file($file))
        {
            $info = json_decode(file_get_contents($file), true);
        }
        else
        {
            $info = false;
        }

        return $info;
    }


    private function decode_binary_string( $string, $type){
        $value = 0;
        $type = strtoupper($type);

        switch ($type)
        {
            case 'UINT16':
                $value = unpack('n', $string)[1];

                if ( bin2hex($string) == 'ffff'){
                    $value = 0;
                    return false;
                }
                break;

            case 'INT16':
                $value = unpack('s', strrev($string))[1];

                if ( bin2hex($string) == '8000' ||  bin2hex($string) == '7fff'){
                    $value = 0;
                    return false;
                }
                break;

            case 'UINT32':
                $value = unpack('N', $string)[1];

                if ( bin2hex($string) == 'ffffffff'){
                    $value = 0;
                    return false;
                }
                break;


            case 'INT32':
                $value = unpack('l', strrev($string))[1];

                if ( bin2hex($string) == '80000000' || bin2hex($string) == '7fffffff'){
                    $value = 0;
                    return false;
                }
                break;

            case 'UINT64':
                $value = unpack('N', substr($string, 4))[1];

                if ( bin2hex($string) == 'ffffffffffffffff'){
                    $value = 0;
                    return false;
                }

                break;

            case 'INT64':
                $value = unpack('l', strrev(substr($string, 4)))[1];

                if ( bin2hex($string) == '7fffffffffffffff'){
                    $value = 0;
                    return false;
                }
                break;

            case 'FLOAT32':
                $value = unpack('l', strrev($string))[1];

                $ulong = pack("L", $value);

                $value = unpack("f", $ulong)[1];
                if ( bin2hex($string) == '7fc00000'){
                    $value = 0;
                    return false;
                }
                break;

            case 'FLOAT64':
                $value = unpack('f', substr($string, 4))[1];
                break;

        }

        return $value;
    }

    /**
     * Versucht eine Semaphore zu setzen und wiederholt dies bei Misserfolg bis zu 100 mal.
     * @param string $ident Ein String der den Lock bezeichnet.
     * @return boolean TRUE bei Erfolg, FALSE bei Misserfolg.
     */
    private function lock($ident, $timeout)
    {

        if (IPS_SemaphoreEnter('ModBus' . '.' . (string) $ident, $timeout))
        {
            return true;
        }

        return false;
    }

    /**
     * Löscht eine Semaphore.
     * @param string $ident Ein String der den Lock bezeichnet.
     */
    private function unlock($ident)
    {
        IPS_SemaphoreLeave('ModBus' . '.' . (string) $ident);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ float.
     *
     * @param int    $VarTyp   Typ der Variable
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param int    $MinValue Minimaler Wert.
     * @param int    $MaxValue Maximaler wert.
     * @param int    $StepSize Schrittweite
     */
    protected function RegisterProfile($VarTyp, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits = 0)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $VarTyp);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != $VarTyp) {
                throw new \Exception('Variable profile type does not match for profile ' . $Name, E_USER_WARNING);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        switch ($VarTyp) {
            case VARIABLETYPE_FLOAT:
                IPS_SetVariableProfileDigits($Name, $Digits);
                // no break
            case VARIABLETYPE_INTEGER:
                IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
                break;
        }
    }
}
