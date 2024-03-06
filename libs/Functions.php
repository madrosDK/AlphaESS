<?php

private function checkModbusGateway(string $hostIp, int $hostPort, int $hostmodbusDevice, int $hostSwapWords): array
{
	// Splitter-Instance Id des ModbusGateways
	$foundGatewayId = 0;
	// I/O Instance Id des ClientSockets
	$foundClientSocketId = 0;

	// Erst die ClientSockets durchsuchen
	// --> ClientSocketId merken (somit kann es keine doppelten ClientSockets mehr geben!!!)

	// danach die dazugehörige GatewayId ermitteln und merken

	foreach (IPS_GetInstanceListByModuleID(MODBUS_INSTANCES) as $modbusInstanceId)
	{
		$connectionInstanceId = IPS_GetInstance($modbusInstanceId)['ConnectionID'];

		// check, if hostIp and hostPort of currenct ClientSocket is matching new settings
		if (0 != (int)$connectionInstanceId && $hostIp == IPS_GetProperty($connectionInstanceId, "Host") && $hostPort == IPS_GetProperty($connectionInstanceId, "Port"))
		{
			$foundClientSocketId = $connectionInstanceId;

			// check, if "Geraete-ID" of currenct ModbusGateway is matching new settings
			if ($hostmodbusDevice == IPS_GetProperty($modbusInstanceId, "DeviceID"))
			{
				$foundGatewayId = $modbusInstanceId;
			}

			$this->SendDebug("ModBusInstance and ClientSocket", "found: ModBusInstance=".$foundGatewayId.", ClientSocket=".$foundClientSocketId, 0);

			break;
		}
	}

	// Modbus-Gateway erstellen, sofern noch nicht vorhanden
	$applyChanges = false;
	$currentGatewayId = 0;
	if (0 == $foundGatewayId)
	{
		$this->SendDebug("ModBusInstance and ClientSocket", "not found!", 0);

		// ModBus Gateway erstellen
		$currentGatewayId = IPS_CreateInstance(MODBUS_INSTANCES);
		IPS_SetInfo($currentGatewayId, MODUL_PREFIX."-Modul: ".date("Y-m-d H:i:s"));
		$applyChanges = true;

		// Achtung: ClientSocket wird immer mit erstellt
		$clientSocketId = (int)IPS_GetInstance($currentGatewayId)['ConnectionID'];
		IPS_SetInfo($clientSocketId, MODUL_PREFIX."-Modul: ".date("Y-m-d H:i:s"));
		IPS_SetName($clientSocketId, MODUL_PREFIX."ClientSocket_Temp");

		$this->SendDebug("ModBusInstance and ClientSocket", "created: ModBusInstance=".$currentGatewayId.", ClientSocket=".$clientSocketId, 0);
	}
	else
	{
		$currentGatewayId = $foundGatewayId;
	}

	// Modbus-Gateway Einstellungen setzen
	if (MODUL_PREFIX."ModbusGateway" != IPS_GetName($currentGatewayId))
	{
		IPS_SetName($currentGatewayId, MODUL_PREFIX."ModbusGateway".$hostmodbusDevice);
	}
	if (0 != IPS_GetProperty($currentGatewayId, "GatewayMode"))
	{
		IPS_SetProperty($currentGatewayId, "GatewayMode", 0);
		$applyChanges = true;
	}
	if ($hostmodbusDevice != IPS_GetProperty($currentGatewayId, "DeviceID"))
	{
		IPS_SetProperty($currentGatewayId, "DeviceID", $hostmodbusDevice);
		$applyChanges = true;
	}
	if ($hostSwapWords != IPS_GetProperty($currentGatewayId, "SwapWords"))
	{
		IPS_SetProperty($currentGatewayId, "SwapWords", $hostSwapWords);
		$applyChanges = true;
	}

	if ($applyChanges)
	{
		@IPS_ApplyChanges($currentGatewayId);
		IPS_Sleep(100);
	}


	// Hat Modbus-Gateway bereits einen ClientSocket?
	$applyChanges = false;
	$clientSocketId = (int)IPS_GetInstance($currentGatewayId)['ConnectionID'];
	$currentClientSocketId = 0;
	// wenn ja und noch kein Interface vorhanden, dann den neuen ClientSocket verwenden
	if (0 == $foundClientSocketId && 0 != $clientSocketId)
	{
		// neuen ClientSocket als Interface merken
		$currentClientSocketId = $clientSocketId;
	}
	// wenn ja und bereits ein Interface vorhanden, dann den neuen ClientSocket löschen
	elseif (0 != $foundClientSocketId/* && 0 != $clientSocketId*/)
	{
		// bereits vorhandenen ClientSocket weiterverwenden
		$currentClientSocketId = $foundClientSocketId;
	}
	// ClientSocket erstellen, sofern noch nicht vorhanden
	else
	/*if (0 == $currentClientSocketId)*/
	{
		$this->SendDebug("ModBusInstance and ClientSocket", "ModBusInstance=".$currentGatewayId.", ClientSocket not found!", 0);

		// Client Soket erstellen
		$currentClientSocketId = IPS_CreateInstance(CLIENT_SOCKETS);
		IPS_SetInfo($currentClientSocketId, MODUL_PREFIX."-Modul: ".date("Y-m-d H:i:s"));

		$this->SendDebug("ModBusInstance and ClientSocket", "ClientSocket=".$currentClientSocketId." created", 0);

		$applyChanges = true;
	}

	// ClientSocket Einstellungen setzen
	if (MODUL_PREFIX."ClientSocket" != IPS_GetName($currentClientSocketId))
	{
		IPS_SetName($currentClientSocketId, MODUL_PREFIX."ClientSocket");
		$applyChanges = true;
	}
	if ($hostIp != IPS_GetProperty($currentClientSocketId, "Host"))
	{
		IPS_SetProperty($currentClientSocketId, "Host", $hostIp);
		$applyChanges = true;
	}
	if ($hostPort != IPS_GetProperty($currentClientSocketId, "Port"))
	{
		IPS_SetProperty($currentClientSocketId, "Port", $hostPort);
		$applyChanges = true;
	}
	if (true != IPS_GetProperty($currentClientSocketId, "Open"))
	{
		IPS_SetProperty($currentClientSocketId, "Open", true);
		$applyChanges = true;

		$this->SendDebug("ClientSocket-Status", "ClientSocket activated (".$currentClientSocketId.")", 0);
	}

	if ($applyChanges)
	{
		@IPS_ApplyChanges($currentClientSocketId);
		IPS_Sleep(100);
	}


	// Client Socket mit Gateway verbinden
	// sofern bereits ein ClientSocket mit dem Gateway verbunden ist, dieses vom Gateway trennen und löschen
	$oldClientSocket = (int)IPS_GetInstance($currentGatewayId)['ConnectionID'];
	if ($oldClientSocket != $currentClientSocketId)
	{
		if (0 != $oldClientSocket)
		{
			IPS_DisconnectInstance($currentGatewayId);
			$this->deleteInstanceNotInUse($oldClientSocket, CLIENT_SOCKETS);
		}

		// neuen ClientSocket mit Gateway verbinden
		IPS_ConnectInstance($currentGatewayId, $currentClientSocketId);

		$this->SendDebug("ModBusInstance and ClientSocket", "remove old ClientSocket=".$oldClientSocket." and connect new ClientSocket=".$currentClientSocketId." with ModBusInstance=".$currentGatewayId, 0);
	}

	return array($currentGatewayId, $currentClientSocketId);
}
