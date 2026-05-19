# Changelog

## 1.0.4 - 2026-05-20

- Pri checkoute sa dobierka automaticky skryje pre DPD Pickup / Pickup Station, keď vybraný pickup point nepodporuje COD.
- Pri GLS Parcel Locker / Parcel Shop sa dobierka skryje podľa capability flagov z vybraného pickup pointu a checkout sa po výbere miesta okamžite obnoví.

## 1.0.3 - 2026-05-15

- Doplněna další pravidla pro výpočet extra dobírky podle dopravce.
- Release srovnává lokální pracovní změny s GitHub release stavem.

## 1.0.2

- Doplnen `readme.txt` pro WordPress plugin format.
- Dokoncen git housekeeping repozitare (`.gitignore`, ignorace build artefaktu, executable bit release skriptu).
- Publikovan follow-up release, aby GitHub tag odpovidal kompletne pushnutemu stavu repozitare.

## 1.0.1

- Sjednocena kostra modulu s ostatními AR Design pluginy.
- Přidány `Core`, updater, rollback manager a release metadata.
- Přidána `languages` vrstva a GitHub workflow pro release.
- Zachována stávající logika takeoveru WooCommerce COD gateway a výpočtu extra dobírky.

## 1.0.0

- Zaveden samostatný modul pro extra COD fee podle dopravce.
- Přidán takeover WooCommerce `cod` gateway.
- Přidána podpora fixed a price-based COD fee pravidel.
