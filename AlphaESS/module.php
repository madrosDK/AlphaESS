<?

require_once __DIR__.'/../libs/functions.php';  // globale Funktionen

// Offset von Register (erster Wert 1) zu Adresse (erster Wert 0) ist -1
if (!defined('MODBUS_REGISTER_TO_ADDRESS_OFFSET'))
{
	define("MODBUS_REGISTER_TO_ADDRESS_OFFSET", -1);
}

// ArrayOffsets
if (!defined('IMR_START_REGISTER'))
{
	define("IMR_START_REGISTER", 0);
//	define("IMR_END_REGISTER", 3);
	define("IMR_SIZE", 1);
	define("IMR_RW", 2);
	define("IMR_FUNCTION_CODE", 3);
	define("IMR_NAME", 4);
	define("IMR_DESCRIPTION", 5);
	define("IMR_TYPE", 6);
	define("IMR_UNITS", 7);
	define("IMR_SF", 8);
}

class ALPHAESS extends IPSModule
{
    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);
    }
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyBoolean('active', 'true');
  			$this->RegisterPropertyString('hostIp', '');
        $this->RegisterPropertyInteger("Interval", 0);
	      $this->RegisterPropertyBoolean("TemperatureInput1", false);
        $this->RegisterPropertyBoolean("TemperatureInput2", false);
        $this->RegisterTimer("UpdateTimer", 0, "ALPHAESS_RequestRead(\$_IPS['TARGET']);");
	//Profil

	if (!IPS_VariableProfileExists("Cos.Phi")){
         	IPS_CreateVariableProfile("Cos.Phi", 2);
                IPS_SetVariableProfileDigits("Cos.Phi", 2);
                IPS_SetVariableProfileText("Cos.Phi", "", "");
            }
	if (!IPS_VariableProfileExists("ALPHAESS.Rotation")){
                IPS_CreateVariableProfile("ALPHAESS.Rotation", 1);
                IPS_SetVariableProfileAssociation("ALPHAESS.Rotation", -1, "Links", "", -1);
                IPS_SetVariableProfileAssociation("ALPHAESS.Rotation", 0, "None", "", -1);
                IPS_SetVariableProfileAssociation("ALPHAESS.Rotation", 1, "Rechts", "", -1);
            }
	if (!IPS_VariableProfileExists("Scheinleistung")){
         	IPS_CreateVariableProfile("Scheinleistung", 2);
                IPS_SetVariableProfileDigits("Scheinleistung", 2);
                IPS_SetVariableProfileText("Scheinleistung", "", " VA");
            }
	if (!IPS_VariableProfileExists("Blindleistung")){
         	IPS_CreateVariableProfile("Blindleistung", 2);
                IPS_SetVariableProfileDigits("Blindleistung", 2);
                IPS_SetVariableProfileText("Blindleistung", "", " var");
            }
    }
    public function ApplyChanges()
    {
        parent::ApplyChanges();

	//Variable Anlegen oder LÃ¶schen
	//Temperatur 1
	if ($this->ReadPropertyBoolean("TemperatureInput1") === true)
		{
	$this->RegisterVariableFloat("Temp1", "Temperatur 1", "Temperature", 8);
	}
	else
	{
	$this->UnregisterVariable("Temp1");
	}
	//Temperatur 2
	if ($this->ReadPropertyBoolean("TemperatureInput2") === true)
                {
        $this->RegisterVariableFloat("Temp2", "Temperatur 2", "Temperature", 9);
        }
        else
        {
        $this->UnregisterVariable("Temp2");
        }

        $this->RegisterVariableFloat("VoltL1", "Volt L1-L2", "Volt", 1);
        $this->RegisterVariableFloat("VoltL2", "Volt L2-L3", "Volt", 1);
        $this->RegisterVariableFloat("VoltL3", "Volt L1-L3", "Volt", 1);

	$this->RegisterVariableFloat("Volt2L1", "Volt L1", "Volt.230", 2);
        $this->RegisterVariableFloat("Volt2L2", "Volt L2", "Volt.230", 2);
        $this->RegisterVariableFloat("Volt2L3", "Volt L3", "Volt.230", 2);

        $this->RegisterVariableFloat("AmpereL1", "Ampere L1", "Ampere.16", 3);
        $this->RegisterVariableFloat("AmpereL2", "Ampere L2", "Ampere.16", 3);
        $this->RegisterVariableFloat("AmpereL3", "Ampere L3", "Ampere.16", 3);

        $this->RegisterVariableFloat("WattL1", "Watt L1", "Watt.14490", 4);
        $this->RegisterVariableFloat("WattL2", "Watt L2", "Watt.14490", 4);
        $this->RegisterVariableFloat("WattL3", "Watt L3", "Watt.14490", 4);

        $this->RegisterVariableFloat("Watt_Total", "Verbrauch Gesamt", "Watt.14490", 5);


        $this->RegisterVariableFloat("Frequenz", "Frequenz", "Hertz.50", 7);

        $this->RegisterVariableFloat("Total", "Total kWh", "Electricity", 6);

	$this->RegisterVariableFloat("CosPhiL1", "Cos Phi L1", "Cos.Phi", 10);
	$this->RegisterVariableFloat("CosPhiL2", "Cos Phi L2", "Cos.Phi", 10);
	$this->RegisterVariableFloat("CosPhiL3", "Cos Phi L3", "Cos.Phi", 10);

	$this->RegisterVariableInteger("Drehfeld", "Drehfeld", "ALPHAESS.Rotation", 11);

	$this->RegisterVariableFloat("ScheinleistungL1", "Scheinleistung L1", "Scheinleistung", 12);
	$this->RegisterVariableFloat("ScheinleistungL2", "Scheinleistung L2", "Scheinleistung", 12);
	$this->RegisterVariableFloat("ScheinleistungL3", "Scheinleistung L3", "Scheinleistung", 12);

	$this->RegisterVariableFloat("BlindleistungL1", "Blindleistung L1", "Blindleistung", 13);
	$this->RegisterVariableFloat("BlindleistungL2", "Blindleistung L2", "Blindleistung", 13);
	$this->RegisterVariableFloat("BlindleistungL3", "Blindleistung L3", "Blindleistung", 13);


        if ($this->ReadPropertyInteger("Interval") > 0)
            $this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("Interval"));
        else
            $this->SetTimerInterval("UpdateTimer", 0);
    }
    public function RequestRead()
    {
        $Gateway = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($Gateway == 0)
            return false;
        $IO = IPS_GetInstance($Gateway)['ConnectionID'];
        if ($IO == 0)
            return false;
        if (!$this->lock($IO))
            return false;
	//Spannung L1-L2, L2-L3, L3-L1
         for ($index = 0; $index < 3; $index++)
        {
            $Volt = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19006 + ($index * 2), "Quantity" => 2, "Data" => "")));
            if ($Volt === false)
            {
                $this->unlock($IO);
                return false;
            }
            $Volt = unpack("f", strrev(substr($Volt, 2)))[1];
            $this->SendDebug('Volt L1-L2', $Volt, 0);
	    SetValue($this->GetIDForIdent("VoltL" . ($index + 1)), $Volt);
        }
	//Spannung L1-N, L2-N, L3-N
	for ($index = 0; $index < 3; $index++)
        {
            $Volt2 = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19000 + ($index * 2), "Quantity" => 2, "Data" => "")));
            if ($Volt2 === false)
            {
                $this->unlock($IO);
                return false;
            }
            $Volt2 = unpack("f", strrev(substr($Volt2, 2)))[1];
            $this->SendDebug('Volt L'. ($index + 1), $Volt2, 0);
	    SetValue($this->GetIDForIdent("Volt2L" . ($index + 1)), $Volt2);
        }
	//Strom
        for ($index = 0; $index < 3; $index++)
        {
            $Ampere = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19012 + ($index * 2), "Quantity" => 2, "Data" => "")));
            if ($Ampere === false)
            {
                $this->unlock($IO);
                return false;
            }
            $Ampere = unpack("f", strrev(substr($Ampere, 2)))[1];
            $this->SendDebug('Ampere L' . ($index + 1), $Ampere, 0);
            SetValue($this->GetIDForIdent("AmpereL" . ($index + 1)), $Ampere);
        }
	//Arbeit
        for ($index = 0; $index < 3; $index++)
        {
            $Watt = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19020 + ($index * 2), "Quantity" => 2, "Data" => "")));
            if ($Watt === false)
            {
                $this->unlock($IO);
                return false;
            }
            $Watt = unpack("f", strrev(substr($Watt, 2)))[1];
            $this->SendDebug('Watt L' . ($index + 1), $Watt, 0);
            SetValue($this->GetIDForIdent("WattL" . ($index + 1)), $Watt);
        }
	//Arbeit Gesammt
        $Watt_Total = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19026, "Quantity" => 2, "Data" => "")));
        if ($Watt_Total=== false)
        {
            $this->unlock($IO);
            return false;
        }
        $Watt_Total= unpack("f", strrev(substr($Watt_Total, 2)))[1];
        $this->SendDebug('Verbrauch Gesammt', $Watt_Total, 0);
        SetValue($this->GetIDForIdent("Watt_Total"), $Watt_Total);

	//Frequenz
        $Frequenz = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19050, "Quantity" => 2, "Data" => "")));
        if ($Frequenz === false)
        {
            $this->unlock($IO);
            return false;
        }
        $Frequenz = unpack("f", strrev(substr($Frequenz, 2)))[1];
        $this->SendDebug('Frequenz', $Frequenz, 0);
        SetValue($this->GetIDForIdent("Frequenz"), $Frequenz);

	//Verbrauch

          $total= $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19060, "Quantity" => 2, "Data" => "")));
        if ($total === false)
        {
            $this->unlock($IO);
            return false;
        }
        $total = unpack("f", strrev(substr($total,2)))[1] / 1000;
        $this->SendDebug('Total', $total, 0);
    	SetValue($this->GetIDForIdent("Total"), $total);

	//Cos Phi
	for ($index = 0; $index < 3; $index++)
        {
            $Cos = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19044 + ($index * 2), "Quantity" => 2, "Data" => "")));
            if ($Cos === false)
            {
                $this->unlock($IO);
                return false;
            }
            $Cos = unpack("f", strrev(substr($Cos, 2)))[1];
            $this->SendDebug('Cos L'. ($index + 1), $Cos, 0);
	    SetValue($this->GetIDForIdent("CosPhiL" . ($index + 1)), $Cos);
        }

	//Drehfeld
        $Drehfeld = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19052, "Quantity" => 2, "Data" => "")));
        if ($Drehfeld === false)
        {
            $this->unlock($IO);
            return false;
        }
        $Drehfeld = unpack("f", strrev(substr($Drehfeld, 2)))[1];
        $this->SendDebug('Drehfeld', $Drehfeld, 0);
        SetValue($this->GetIDForIdent("Drehfeld"), $Drehfeld);

	//Scheinleistung
	for ($index = 0; $index < 3; $index++)
        {
            $Scheinleistung = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19028 + ($index * 2), "Quantity" => 2, "Data" => "")));
            if ($Scheinleistung === false)
            {
                $this->unlock($IO);
                return false;
            }
            $Scheinleistung = unpack("f", strrev(substr($Scheinleistung, 2)))[1];
            $this->SendDebug('Scheinleistung L'. ($index + 1), $Scheinleistung, 0);
	    SetValue($this->GetIDForIdent("ScheinleistungL" . ($index + 1)), $Scheinleistung);
        }

	//Blindleistung
	for ($index = 0; $index < 3; $index++)
        {
            $Blindleistung = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 19036 + ($index * 2), "Quantity" => 2, "Data" => "")));
            if ($Blindleistung === false)
            {
                $this->unlock($IO);
                return false;
            }
            $Blindleistung = unpack("f", strrev(substr($Blindleistung, 2)))[1];
            $this->SendDebug('Blindleistung L'. ($index + 1), $Blindleistung, 0);
	    SetValue($this->GetIDForIdent("BlindleistungL" . ($index + 1)), $Blindleistung);
        }

 	//Temperatur 1
        $Temp1 = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 10865, "Quantity" => 2, "Data" => "")));
        if ($Temp1 === false)
        {
            $this->unlock($IO);
            return false;
        }
        $Temp1 = unpack("f", strrev(substr($Temp1, 2)))[1];
        $this->SendDebug('Temperatur 1', $Temp1, 0);
	if ($this->ReadPropertyBoolean("TemperatureInput1") === true)
		{
		SetValue($this->GetIDForIdent("Temp1"), $Temp1);
		}
	else
		{
		}

	//Temperatur 2
        $Temp2 = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => 10867, "Quantity" => 2, "Data" => "")));
        if ($Temp2 === false)
        {
            $this->unlock($IO);
            return false;
        }
        $Temp2 = unpack("f", strrev(substr($Temp2, 2)))[1];
        $this->SendDebug('Temperatur 2', $Temp2, 0);
        if ($this->ReadPropertyBoolean("TemperatureInput1") === true)
		{
		SetValue($this->GetIDForIdent("Temp2"), $Temp2);
		}
	else
		{
		}



        IPS_Sleep(333);
        $this->unlock($IO);
        return true;
    }
    /**
     * Versucht eine Semaphore zu setzen und wiederholt dies bei Misserfolg bis zu 100 mal.
     * @param string $ident Ein String der den Lock bezeichnet.
     * @return boolean TRUE bei Erfolg, FALSE bei Misserfolg.
     */
    private function lock($ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter('ModBus' . '.' . (string) $ident, 1))
            {
                return true;
            }
            else
            {
                IPS_Sleep(5);
            }
        }
        return false;
    }
    /**
     * LÃƒÂ¶scht eine Semaphore.
     * @param string $ident Ein String der den Lock bezeichnet.
     */
    private function unlock($ident)
    {
        IPS_SemaphoreLeave('ModBus' . '.' . (string) $ident);
    }
}
?>
