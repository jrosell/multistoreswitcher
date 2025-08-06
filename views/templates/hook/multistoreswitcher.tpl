{*
 * @author    Jordi Rosell
 * @copyright 2025
 * @license   MIT License
*}
{if $shop_list|count > 1}
  <div id="_desktop_store_switcher" class="store-switcher">
    <div class="store-switcher__wrapper">
      <select
        id="store-selector"
        class="form-select js-store-selector"
        aria-label="{l s='Select store' d='Modules.Multistoreswitcher.Shop'}"
        onchange="if (this.value) window.location.href = this.value;"
      >
        {foreach from=$shop_list item=shop}
          <option
            value="{$shop.url}"
            {if $shop.is_current} selected="selected"{/if}
            data-iso-code="{$shop.id}"
          >
            {$shop.name_to_display|truncate:30}
          </option>
        {/foreach}
      </select>
    </div>
  </div>
{/if}