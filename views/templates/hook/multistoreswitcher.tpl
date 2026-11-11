{*
 * @author    Jordi Rosell <jroselln@gmail.com>
 * @copyright 2025 Jordi Rosell
 * @license   MIT License
 *}
{if $shop_list|count > 1}
  <div class="store-switcher dropdown d-inline-block"{if $multistoreswitcher_responsive} data-multistoreswitcher-responsive{/if}>
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
            type="button"
            data-toggle="dropdown"
            data-bs-toggle="dropdown"
            aria-expanded="false">
      {$current_shop.name|escape:'htmlall':'UTF-8'}
    </button>
    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-right">
      {foreach from=$shop_list item=shop}
        <li>
          <a class="dropdown-item {if $shop.is_current}active{/if}"
             href="{$shop.url|escape:'htmlall':'UTF-8'}">
            {$shop.name|escape:'htmlall':'UTF-8'}
            {if $shop.is_current} <span class="visually-hidden">({l s='Current' d='Modules.Multistoreswitcher.Shop'})</span>{/if}
          </a>
        </li>
      {/foreach}
    </ul>
    {if $multistoreswitcher_responsive}
      <select class="form-select custom-select d-none js-multistoreswitcher-select"
              aria-label="{l s='Store' d='Modules.Multistoreswitcher.Shop'}">
        {foreach from=$shop_list item=shop}
          <option value="{$shop.url|escape:'htmlall':'UTF-8'}"{if $shop.is_current} selected="selected"{/if}>
            {$shop.name|escape:'htmlall':'UTF-8'}
          </option>
        {/foreach}
      </select>
    {/if}
  </div>
{/if}
