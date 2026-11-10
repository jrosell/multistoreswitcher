<?php
$_SERVER['HTTP_HOST'] = 'localhost:' . (getenv('TEST_SHOP_PORT') ?: '8080');
require '/var/www/html/config/config.inc.php';

global $kernel;
$kernel = new AdminKernel('dev', true);
$kernel->boot();
if (is_readable('/run/cloud-ca.crt')) {
    copy('/run/cloud-ca.crt', _PS_CACHE_CA_CERT_FILE_);
}

function requireSuccess($result, $message)
{
    if (!$result) {
        throw new RuntimeException($message);
    }
}

$source = new Shop(1);
Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
Configuration::updateValue('PS_REWRITING_SETTINGS', 0);
Configuration::updateValue('PS_JS_THEME_CACHE', 0);
Configuration::updateValue('PS_CSS_THEME_CACHE', 0);
Configuration::updateValue('PS_SMARTY_CACHE', 0);
Configuration::updateValue('PS_SMARTY_FORCE_COMPILE', 2);
$source->name = 'Spain';
requireSuccess($source->update(), 'Could not name first shop');

$secondId = (int) Db::getInstance()->getValue(
    'SELECT id_shop FROM ' . _DB_PREFIX_ . 'shop WHERE name = "France"'
);
if (!$secondId) {
    $second = new Shop();
    $second->name = 'France';
    $second->id_shop_group = $source->id_shop_group;
    $second->id_category = $source->id_category;
    $second->theme_name = $source->theme_name;
    $second->active = true;
    requireSuccess($second->add(), 'Could not add second shop');
    $second->associateSuperAdmins();

    $port = getenv('TEST_SHOP_PORT') ?: '8080';
    $url = new ShopUrl();
    $url->id_shop = $second->id;
    $url->domain = '127.0.0.1:' . $port;
    $url->domain_ssl = '127.0.0.1:' . $port;
    $url->physical_uri = '/';
    $url->virtual_uri = '';
    $url->main = true;
    $url->active = true;
    requireSuccess($url->add(), 'Could not add second shop URL');
}

Shop::resetStaticCache();
foreach (Shop::getCompleteListOfShopsID() as $shopId) {
    $shop = new Shop($shopId);
    if ((int) $shopId !== 1) {
        $_POST['categoryBox'] = [];
        $shop->copyShopData(1, array_fill_keys(array_keys(Shop::getAssoTables()), true));
    }
    if ($shop->theme_name !== $source->theme_name) {
        $shop->theme_name = $source->theme_name;
        requireSuccess($shop->update(), 'Could not synchronize development theme');
    }
}

Shop::setContext(Shop::CONTEXT_ALL);
if (!is_file('/var/www/html/var/.module-dev-french')) {
    $result = Language::downloadAndInstallLanguagePack('fr');
    if ($result !== true) {
        throw new RuntimeException('French language installation failed: ' . json_encode($result));
    }
    touch('/var/www/html/var/.module-dev-french');
}

$module = Module::getInstanceByName('multistoreswitcher');
requireSuccess($module instanceof Module, 'Module could not load');
if (!Module::isInstalled('multistoreswitcher')) {
    requireSuccess($module->install(), 'Module installation failed');
}
foreach (['displayNav2', 'displayHeader'] as $hook) {
    requireSuccess($module->registerHook($hook, Shop::getCompleteListOfShopsID()), 'Could not register ' . $hook);
}

echo 'PrestaShop ' . _PS_VERSION_ . '; theme: ' . $source->theme_name
    . '; shops: ' . count(Shop::getCompleteListOfShopsID())
    . '; languages: ' . count(Language::getLanguages()) . "; module installed\n";
