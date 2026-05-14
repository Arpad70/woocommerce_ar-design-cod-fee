# Release

## 1.0.1

Aktualni release modulu AR Design COD Fee.

### Zmeny

- Sjednocena kostra modulu s ostatnimi AR Design pluginy.
- Pridana `Core` vrstva, `languages` adresar a GitHub release workflow.
- Zachovan takeover WooCommerce COD gateway a runtime vypocet extra dobirky podle dopravce.

### Kontrola pred vydanim

- `php scripts/verify-version-consistency.php`
- `find . -path './build' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l`
- `scripts/build-plugin.sh`

### GitHub release

Workflow `.github/workflows/release.yml` publikuje release asset `ar-design-cod-fee.zip`.
