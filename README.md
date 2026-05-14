# AR Design COD Fee for WooCommerce

Samostatny modul pro extra poplatek za dobirku podle dopravce. Modul prevezme WooCommerce gateway `cod`, ale source of truth pro extra COD fee drzi ve vlastni shipping sekci `COD podle dopravcu`.

## Funkce

- nahrazuje `WC_Gateway_COD` vlastni gateway tridou,
- pocita extra COD fee podle zvolene dopravy,
- podporuje rezimy `fixed` a `price_based`,
- umi pracovat s pevnou castkou i procentem z objednavky (napr. `2.5%`),
- umi zobrazit dynamicky text u dobirky podle aktualne zvolene dopravy,
- drzi nastaveni v sekci `WooCommerce -> Nastavenia -> Doprava -> COD podľa dopravcu`.

## Pozadavky

- WordPress 5.3+
- WooCommerce 7.0+
- PHP 7.4+

## Instalace

1. Nahrajte adresar `ar-design-cod-fee` do `wp-content/plugins`.
2. Aktivujte plugin `AR Design COD Fee for WooCommerce`.
3. V administraci WooCommerce otevrette `WooCommerce -> Nastavenia -> Doprava -> COD podľa dopravcu`.
4. Nastavte pravidla pro DPD, GLS, Packetu, osobni odber nebo fallback dopravce.
5. Otestujte checkout s dobirkou pro alespon jednu dopravu.

## Release

```bash
php scripts/verify-version-consistency.php
scripts/build-plugin.sh
```

GitHub Actions workflow `.github/workflows/release.yml` vytvori zip asset `ar-design-cod-fee.zip`.
