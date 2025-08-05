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

        $shopIds = Shop::getCompleteListOfShopsID(); // Returns array of IDs
        $activeShops = [];
        $groupStatus = [];

        foreach ($shopIds as $idShop) {
            $shop = new Shop($idShop);

            if (!Validate::isLoadedObject($shop) || !$shop->active) {
                continue;
            }

            $idShopGroup = (int)$shop->id_shop_group;

            if (!isset($groupStatus[$idShopGroup])) {
                $groupTable = _DB_PREFIX_ . 'shop_group';
                $sql = "SELECT `active` FROM `{$groupTable}` WHERE `id_shop_group` = {$idShopGroup}";
                $result = Db::getInstance()->getRow($sql);
                $groupStatus[$idShopGroup] = (bool)($result['active'] ?? false);
            }

            if (!$groupStatus[$idShopGroup]) {
                continue;
            }

            $useSsl = (bool) Configuration::get('PS_SSL_ENABLED');
            $domainUsed = $useSsl && !empty($shop->domain_ssl) ? $shop->domain_ssl : $shop->domain;

            $activeShops[] = [
                'id'         => (int) $shop->id,
                'name'       => $shop->name,
                'url'        => ($useSsl ? 'https://' : 'http://') . $domainUsed . $shop->getBaseURI(),
                'is_current' => $shop->id === $this->context->shop->id,
            ];
        }

        if (count($activeShops) <= 1) {
            return ''; // Only show if more than one store
        }

        $currentShop = [
            'id'   => (int) $this->context->shop->id,
            'name' => $this->context->shop->name,
        ];

        $this->context->smarty->assign([
            'shop_list'    => $activeShops,
            'current_shop' => $currentShop,
        ]);

        if (!file_exists(_PS_MODULE_DIR_ . 'multistoreswitcher/views/templates/hook/multistoreswitcher.tpl')) {
            die('Template file missing!');
        }

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'multistoreswitcher/views/templates/hook/multistoreswitcher.tpl');
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
