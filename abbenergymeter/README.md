[![Symcon Module](https://img.shields.io/badge/Symcon-PHPModul-blue.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Symcon Version](https://img.shields.io/badge/dynamic/json?color=blue&label=Symcon%20Version&prefix=%3E%3D&query=compatibility.version&url=https%3A%2F%2Fraw.githubusercontent.com%2Froastedelectrons%2FABBEnergyMeter%2Fmain%2Flibrary.json)
![Module Version](https://img.shields.io/badge/dynamic/json?color=green&label=Module%20Version&query=version&url=https%3A%2F%2Fraw.githubusercontent.com%2Froastedelectrons%2FABBEnergyMeter%2Fmain%2Flibrary.json)
![GitHub](https://img.shields.io/github/license/roastedelectrons/ABBEnergyMeter)


# Symcon-Modul: ABB EQ Energy Meter B-Serie <!-- omit in toc -->  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Anhang](#5-anhang)
  - [1. GUID der Module](#1-guid-der-module)
  - [2. Changelog](#2-changelog)
  - [3. Quellen](#3-quellen)
- [6. Lizenz](#6-lizenz)

## 1. Funktionsumfang

Modul zum Einbinden der ABB Stromzähler der B-Serie über ModBus RTU (RS485) in IP-Symcon. Durch eine blockweise Auslesung der ModBus Register wird eine schnellere Auslesung und geringere Systemlast erzielt. Es können mehrere Zähler auf einem physikalischen RS485-Bus betrieben werden.  

Folgende Module beinhaltet das ABBEnergyMeter Repository:

- __EnergyMeter B21/B23/B24 Steel__ ([Dokumentation](ABBBSeriesSteel))

	Symcon-Modul zum Einbinden der ABB Stromzähler B21, B23 und B24 112-100 (Steel) mit ModBus RTU (RS485).

- __EnergyMeter B21/B23/B24 Silver__ ([Dokumentation](ABBBSeriesSilver))

	Symcon-Modul zum Einbinden der ABB Stromzähler B21, B23 und B24 312-100 (Silver) mit ModBus RTU (RS485).

## 2. Voraussetzungen

- IP-Symcon ab Version 5.5
- unterstützte Zähler
- ModBus RTU Schnittstelle (RS485)

## 3. Software-Installation

* Über den Module Store das 'ABB Energy Meter'-Modul installieren.
* Alternativ über das Module Control die URL dieses Repositories

## 4. Einrichten der Instanzen in IP-Symcon

Ist direkt in der Dokumentation der jeweiligen Module beschrieben.  

## 5. Anhang

###  1. GUID der Module

 
| Modul   | Typ    | Prefix  | GUID                                   |
| :-----: | :----: | :-----: | :------------------------------------: |
| ABB B Series Sliver | Device | ABBEM | {F315CAC7-57EF-E5E8-97DA-A561A5AEA628} |
| ABB B Series Steel  | Device | ABBEM  | {C38A4827-563E-57E8-0FF2-7E0B2E4C235F} |



### 2. Changelog

Version 1.1 (2023-08-20):  
* Modul-Namen vereinheitlicht und Unterstützung für B21 Zähler ergänzt
* Ab jetzt unter MIT-Lizenz

Version 1.0:  
* Erstes offizielles Release  

### 3. Quellen
1. [Handbuch ABB B-Series Zähler (inkl. ModBus Register-Listen)](https://library.e.abb.com/public/c1e3b171b375492492e79ca10f34e05e/2CDC512084D0101.pdf)
2. Sturktur des Modul wurde in Anlehnung an [Symcon-Modul: B+G E-Tech](https://github.com/Nall-chan/BGETech) by [Nall-chan](https://github.com/Nall-chan) entwickelt

## 6. Lizenz ##
MIT License

Copyright (c) 2023 Tobias Ohrdes



 