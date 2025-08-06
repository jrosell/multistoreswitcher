{*
 * @author    Jordi Rosell <jroselln@gmail.com>
 * @copyright 2025 Jordi Rosell
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
          {assign var="translation_key" value="store_name_"|cat:$shop.id}
          {assign var="translated_name" value={l s=$translation_key d='Modules.Multistoreswitcher.Shop'}}
          <option
            value="{$shop.url}"
            {if $shop.is_current} selected="selected"{/if}
            data-iso-code="{$shop.id}"
          >
            {if $translated_name == $translation_key}
              {$shop.name|truncate:30}
            {else}
              {$translated_name|truncate:30}
            {/if}
          </option>
        {/foreach}
      </select>
    </div>
  </div>
{/if}
