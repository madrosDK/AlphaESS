<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ABBEnergyMeter.php'; 

class ABBBSeriesSilver extends ABBEnergyMeter
{
	const PREFIX = "ABBEM";
    const DeviceIdent = "ABB_B23_SILVER";
}