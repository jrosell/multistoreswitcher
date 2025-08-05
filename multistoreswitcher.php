<?php
/**
 * @author    Jordi Rosell <jroselln@gmail.com>
 * @copyright 2025 Jordi Rosell
 * @license   MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MultistoreSwitcher extends Module
{
    public function __construct()
    {
        $this->name = 'multistoreswitcher';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Jordi Rosell';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Multistore Switcher');
        $this->description = $this->l('Adds a store switcher dropdown to let users navigate between multistore shops.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $hookNames = ['displayNav2', 'displayHeader'];

        // Get all shop IDs
        $shopIds = Shop::getCompleteListOfShopsID();

        foreach ($shopIds as $shopId) {
            // Switch context to each shop
            Shop::setContext(Shop::CONTEXT_SHOP, $shopId);

            foreach ($hookNames as $hookName) {
                if (!$this->isRegisteredInHook($hookName)) {
                    $this->registerHook($hookName);
                }
                $this->moveToTopOfHook($hookName, $shopId);
            }
        }

        // Restore to all shops context
        Shop::setContext(Shop::CONTEXT_ALL);

        return true;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function render()
    {
        // Safety checks
        if (!Shop::isFeatureActive() || !Validate::isLoadedObject($this->context->shop)) {
            return '';
        }

        $shops = Shop::getShops(false, null, true);
        $activeShops = [];
        $groupStatus = [];

        foreach ($shops as $shopData) {
            // Normalize shop data
            $id_shop = is_array($shopData) ? (int)$shopData['id_shop'] : (int)$shopData->id_shop;
            $id_shop_group = is_array($shopData) ? (int)$shopData['id_shop_group'] : (int)$shopData->id_shop_group;
            $name = is_array($shopData) ? $shopData['name'] : $shopData->name;
            $domain = is_array($shopData) ? $shopData['domain'] : $shopData->domain;
            $domain_ssl = is_array($shopData) ? $shopData['domain_ssl'] : $shopData->domain_ssl;
            $uri = is_array($shopData) ? $shopData['uri'] : $shopData->uri;
            $shop_active = is_array($shopData) ? (bool)$shopData['active'] : (bool)$shopData->active;

            // Skip if shop is not active
            if (!$shop_active) {
                continue;
            }

            // Check group status
            if (!isset($groupStatus[$id_shop_group])) {
                $result = Db::getInstance()->getRow(
                    'SELECT `active` FROM `' . _DB_PREFIX_ . 'shop_group` WHERE `id_shop_group` = ' . (int)$id_shop_group
                );
                $groupStatus[$id_shop_group] = (bool)($result['active'] ?? false);
            }

            if (!$groupStatus[$id_shop_group]) {
                continue; // Skip if group is disabled
            }

            $useSsl = (bool)Configuration::get('PS_SSL_ENABLED');
            $domainUsed = $useSsl && !empty($domain_ssl) ? $domain_ssl : $domain;

            $activeShops[] = [
                'id' => $id_shop,
                'name' => $name,
                'url' => ($useSsl ? 'https://' : 'http://') . $domainUsed . $uri,
                'is_current' => $id_shop === (int)$this->context->shop->id,
            ];
        }

        if (count($activeShops) <= 1) {
            return ''; // Only show if more than one store
        }

        // Normalize $this->context->shop to array (critical fix)
        $currentShop = [
            'id' => (int) $this->context->shop->id,
            'name' => $this->context->shop->name,
        ];

        // Assign to Smarty
        $this->context->smarty->assign([
            'shop_list' => $activeShops,
            'current_shop' => $currentShop, // ← Now it's an array, not object
        ]);

        if (!file_exists(_PS_MODULE_DIR_ . 'multistoreswitcher/views/templates/hook/multistoreswitcher.tpl')) {
            die('Template file missing!');
        }

        return $this->context->smarty->fetch(_PS_MODULE_DIR_.'multistoreswitcher/views/templates/hook/multistoreswitcher.tpl');
    }

    public function hookDisplayHeader()
    {
        // Not done:
        // $this->context->controller->addCSS($this->_path.'views/css/multistoreswitcher.css');
    }

    public function hookDisplayNav2()
    {
        return $this->render();
    }

    public function hookDisplayTop()
    {
        return $this->render();
    }

    public function hookDisplayFooter()
    {
        return $this->render();
    }

    protected function moveToTopOfHook($hookName, $shopId)
    {
        $idHook = Hook::getIdByName($hookName);
        if (!$idHook) {
            return false;
        }

        $idModule = (int) $this->id;

        return Db::getInstance()->update(
            'hook_module',
            ['position' => 0],
            "id_hook = $idHook AND id_module = $idModule AND id_shop = $shopId"
        );
    }
}
