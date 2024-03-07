<?php

declare(strict_types=1);

/*
 * @addtogroup bgetech
 * @{
 *
 * @package       BGETech
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2022 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.51
 *
 */
require_once __DIR__ . '/SemaphoreHelper.php';  // diverse Klassen
eval('declare(strict_types=1);namespace AlphaESS {?>' . file_get_contents(__DIR__ . '/helper/VariableProfileHelper.php') . '}');

/**
 * BGETech ist die Basisklasse für alle Energie-Zähler der Firma B+G E-Tech
 * Erweitert ipsmodule.
 * @property array $Variables
 * @method void RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, float $StepSize)
 * @method void RegisterProfileFloat(string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits)
 */


class AlphaESS extends IPSModule
{
    use \AlphaESS\SemaphoreHelper;
    use \AlphaESS\VariableProfileHelper;
    const Swap = true;
    const PREFIX = '';
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{A5F663AB-C400-4FE5-B207-4D67CC030564}');
        $this->RegisterPropertyInteger('Interval', 0);
        $Variables = [];
        foreach (static::$Variables as $Pos => $Variable) {
            $Variables[] = [
                'Ident'    => str_replace(' ', '', $Variable[0]),
                'Name'     => $this->Translate($Variable[0]),
                'VarType'  => $Variable[1],
                'Profile'  => $Variable[2],
                'Address'  => $Variable[3],
                'Function' => $Variable[4],
                'Quantity' => $Variable[5],
                'Pos'      => $Pos + 1,
                'Keep'     => $Variable[6]
            ];
        }
        $this->RegisterPropertyString('Variables', json_encode($Variables));
        $this->RegisterTimer('UpdateTimer', 0, static::PREFIX . '_RequestRead($_IPS["TARGET"]);');
    }

    public function RequestRead()
    {
        $Gateway = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($Gateway == 0) {
            return false;
        }
        $IO = IPS_GetInstance($Gateway)['ConnectionID'];
        if ($IO == 0) {
            return false;
        }
        if (!$this->lock($IO)) {
            return false;
        }
        $Result = $this->ReadData();
        //IPS_Sleep(333);
        $this->unlock($IO);
        return $Result;
    }

    private function ReadData()
    {
        $Variables = json_decode($this->ReadPropertyString('Variables'), true);
        foreach ($Variables as $Variable) {
            if (!$Variable['Keep']) {
                continue;
            }
            $SendData['DataID'] = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';
            $SendData['Function'] = $Variable['Function'];
            $SendData['Address'] = $Variable['Address'];
            $SendData['Quantity'] = $Variable['Quantity'];
            $SendData['Data'] = '';
            set_error_handler([$this, 'ModulErrorHandler']);
            $ReadData = $this->SendDataToParent(json_encode($SendData));
            restore_error_handler();
            if ($ReadData === false) {
                return false;
            }
            $ReadValue = substr($ReadData, 2);
            $this->SendDebug($Variable['Name'] . ' RAW', $ReadValue, 1);
            if (static::Swap) {
                $ReadValue = strrev($ReadValue);
            }
            $Value = $this->ConvertValue($Variable, $ReadValue);
            if ($Value === null) {
                $this->LogMessage(sprintf($this->Translate('Combination of type and size of value (%s) not supported.'), $Variable['Name']), KL_ERROR);
                continue;
            }
            $this->SendDebug($Variable['Name'], $Value, 0);
            $this->SetValueExt($Variable, $Value);
        }
        return true;
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //$Form['actions'][0]['onClick'] = static::PREFIX . '_RequestRead($id);';
        if (count(static::$Variables) == 1) {
            unset($Form['elements'][1]);
        }
        return json_encode($Form);
    }


}
