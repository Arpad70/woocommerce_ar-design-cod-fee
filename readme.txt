=== AR Design COD Fee for WooCommerce ===
Contributors: arpad70
Requires at least: 5.3
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 1.0.12
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Samostatny modul pro extra poplatek za dobirku podle dopravce ve WooCommerce.

== Description ==

Plugin prevezme WooCommerce gateway `cod` a ridi extra COD fee podle zvolene dopravy. Podporuje pevne castky, procenta z objednavky a pravidla podle ceny objednavky.

== Changelog ==

= 1.0.5 =
* Dobierka sa pri GLS a DPD pickup checkoutoch skryva podla capability flagov vybraneho odberneho miesta.
* Release/build pipeline je zladena so spolocnym AR Design build skriptom.

= 1.0.4 =
* Pri checkoute sa dobierka automaticky skryje pre DPD Pickup / Pickup Station, ked vybrany pickup point nepodporuje COD.
* Pri GLS Parcel Locker / Parcel Shop sa dobierka skryje podla capability flagov z vybraneho pickup pointu a checkout sa po vybere miesta okamzite obnovi.

= 1.0.2 =
* Doplnen `readme.txt` pro WordPress plugin format.
* Doplnen `.gitignore` a ignorace build artefaktu.
* Dokoncen follow-up release pro finalni pushnuty stav repozitare.

= 1.0.1 =
* Sjednocena kostra modulu s ostatnimi AR Design pluginy.
* Pridana Core vrstva, languages a GitHub release workflow.
* Zachovana stavajici logika vypoctu extra dobirky podle dopravce.

= 1.0.0 =
* Prvni samostatny release modulu pro extra COD fee podle dopravce.
