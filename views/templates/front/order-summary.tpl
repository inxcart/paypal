{*
 * Copyright (C) 2017-2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
{capture name=path}
  <a href="{$link->getPageLink('order', true)|escape:'htmlall'}">
    {l s='Your shopping cart' mod='paypal'}
  </a>
  <span class="navigation-pipe">{$navigationPipe|escape:'htmlall'}</span>
  {l s='PayPal' mod='paypal'}
{/capture}

<h1>{l s='Order summary' mod='paypal'}</h1>

<h3>{l s='PayPal payment' mod='paypal'}</h3>
<form action="{$confirm_form_action|escape:'htmlall'}" method="post" data-ajax="false">
  {$paypal_cart_summary|escape}
  <p>
    <b>{l s='Please confirm your order by clicking \'I confirm my order\'' mod='paypal'}.</b>
  </p>
  <p class="cart_navigation">
    <input type="submit" name="confirmation" value="{l s='I confirm my order' mod='paypal'}" class="exclusive_large"/>
  </p>
</form>

