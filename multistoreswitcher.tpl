{if $shop_list|count > 1}
  <div class="store-switcher dropdown d-inline-block">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false">
      {$current_shop.name}
    </button>
    <ul class="dropdown-menu">
      {foreach from=$shop_list item=shop}
        <li>
          <a class="dropdown-item {if $shop.is_current}active{/if}"
             href="{$shop.url}">
            {$shop.name}
            {if $shop.is_current} <span class="visually-hidden">({l s='Current' d='Modules.Multistoreswitcher.Shop'})</span>{/if}
          </a>
        </li>
      {/foreach}
    </ul>
  </div>
{/if}