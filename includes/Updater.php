<?php

declare(strict_types=1);

namespace ArDesign\CodFee;

use ArDesign\Shared\Updates\GitHubPluginUpdater as BaseGitHubPluginUpdater;

if (! defined('ABSPATH')) {
    exit;
}

require_once WP_PLUGIN_DIR . '/ar-design-shared-support/includes/updates/GitHubPluginUpdater.php';

final class ArDesignCodFeeUpdater extends BaseGitHubPluginUpdater
{
    public function __construct(string $repositoryFullName, string $pluginBasename, string $currentVersion)
    {
        parent::__construct(
            $repositoryFullName,
            $pluginBasename,
            $currentVersion,
            array(
                'plugin_slug' => 'ar-design-cod-fee',
                'plugin_name' => 'AR Design COD Fee for WooCommerce',
                'text_domain' => 'ar-design-cod-fee',
                'description' => 'Samostatný modul pre nastaviteľnú extra dobierku podľa dopravcu vo WooCommerce.',
                'author_label' => 'AR Design',
                'user_agent_slug' => 'ar-design-cod-fee',
                'cache_key_prefix' => 'ar_design_cod_fee_release_data_',
                'preferred_zip_names' => array('ar-design-cod-fee.zip'),
                'allow_any_zip_fallback' => false,
            )
        );
    }
}
