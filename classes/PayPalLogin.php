<?php
/**
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
 */

namespace PayPalModule;

use Address;
use Configuration;
use Context;
use Country;
use Customer;
use Language;
use PayPal;
use PrestaShopDatabaseException;
use PrestaShopException;
use State;
use Tools;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class PayPalLogin
 *
 * @package PayPalModule
 */
class PayPalLogin
{
    /** @var array $logs */
    protected $logs = [];
    /** @var bool $enableLog */
    protected $enableLog = false;
    /** @var PayPalRestApi $rest */
    protected $rest;

    /**
     * PayPalLogin constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->rest = PayPalRestApi::getInstance();
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function getIdentityAPIURL()
    {
        if (!Configuration::get(PayPal::LIVE)) {
            return 'api.sandbox.paypal.com';
        } else {
            return 'api.paypal.com';
        }
    }

    /**
     * @return string
     */
    public function getTokenServiceEndpoint()
    {
        return '/v1/identity/openidconnect/tokenservice';
    }

    /**
     * @return string
     */
    public function getUserInfoEndpoint()
    {
        return '/v1/identity/openidconnect/userinfo';
    }

    /**
     * @return string
     */
    public static function getReturnLink()
    {
        return \Context::getContext()->shop->getBaseURL(true, true).'index.php?fc=module&module=paypal&controller=logintoken';
    }

    /**
     * @return array|bool|mixed|PayPalLoginUser
     * @throws \Adapter_Exception
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getAuthorizationCode()
    {
        unset($this->logs);

        $context = \Context::getContext();
        $isLogged = $context->customer->isLogged();

        if ($isLogged) {
            return $this->getRefreshToken();
        }

        $params = [
            'grant_type'   => 'authorization_code',
            'code'         => \Tools::getValue('code'),
            'redirect_url' => PayPalLogin::getReturnLink(),
        ];

        $result = $this->rest->send(
            $this->getTokenServiceEndpoint(),
            http_build_query($params, '', '&'),
            ['Content-Type', 'application/x-www-form-urlencoded'],
            true,
            'POST'
        );
        if (!$result) {
            return false;
        }

        if ($this->enableLog === true) {
            $handle = fopen(dirname(__FILE__).'/Results.txt', 'a+');
            fwrite($handle, "Request => ".print_r(http_build_query($params, '', '&'), true)."\r\n");
            fwrite($handle, "Result => ".print_r($result, true)."\r\n");
            fwrite($handle, "Journal => ".print_r($this->logs, true."\r\n"));
            fclose($handle);
        }

        $result = json_decode($result, true);
        /** @var array $result */

        if ($result && isset($result['access_token'])) {
            $login = new PayPalLoginUser();

            $customer = $this->getUserInformation($result['access_token'], $login);

            if (!$customer) {
                return false;
            }

            $temp = PayPalLoginUser::getByIdCustomer((int) $context->customer->id);

            if ($temp) {
                $login = $temp;
            }

            $login->id_customer = (int) $customer->id;
            $login->token_type = (string) $result->token_type;
            $login->expires_in = (string) (time() + (int) $result->expires_in);
            $login->refresh_token = (string) $result->refresh_token;
            $login->id_token = (string) $result->id_token;
            $login->access_token = (string) $result->access_token;

            $login->save();

            return $login;
        }

        return false;
    }

    /**
     * @return array|bool|mixed
     * @throws PrestaShopException
     * @throws \Adapter_Exception
     * @throws \PrestaShopDatabaseException
     */
    public function getRefreshToken()
    {
        unset($this->logs);
        $login = PayPalLoginUser::getByIdCustomer((int) Context::getContext()->customer->id);

        if (!is_object($login)) {
            return false;
        }

        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $login->refresh_token,
        ];

        $result = $this->rest->send(
            $this->getTokenServiceEndpoint().'?'.http_build_query($params, '', '&'),
            http_build_query($params, '', '&'),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            true,
            'POST'
        );
        if ($result) {
            return false;
        }

        if ($this->enableLog === true) {
            $handle = fopen(dirname(__FILE__).'/Results.txt', 'a+');
            fwrite($handle, "Request => ".print_r(http_build_query($params, '', '&'), true)."\r\n");
            fwrite($handle, "Result => ".print_r($result, true)."\r\n");
            fwrite($handle, "Journal => ".print_r($this->logs, true."\r\n"));
            fclose($handle);
        }

        $result = json_decode($result, true);

        if ($result) {
            $login->access_token = $result->access_token;
            $login->expires_in = (string) (time() + $result->expires_in);
            $login->save();

            return $login;
        }

        return false;
    }

    /**
     * @param $accessToken
     * @param $login
     *
     * @return bool|Customer
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getUserInformation($accessToken, &$login)
    {
        unset($this->logs);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$accessToken,
        ];

        $params = [
            'schema' => 'openid',
        ];

        $result = $this->rest->send(
            $this->getUserInfoEndpoint().'?'.http_build_query($params, '', '&'),
            false,
            $headers
        );
        if (!$result) {
            return false;
        }

        if ($this->enableLog === true) {
            $handle = fopen(dirname(__FILE__).'/Results.txt', 'a+');
            fwrite($handle, "Request => ".print_r(http_build_query($params, '', '&'), true)."\r\n");
            fwrite($handle, "Result => ".print_r($result, true)."\r\n");
            fwrite($handle, "Headers => ".print_r($headers, true)."\r\n");
            fwrite($handle, "Journal => ".print_r($this->logs, true."\r\n"));
            fclose($handle);
        }

        $result = json_decode($result, true);

        if ($result) {
            $customer = new \Customer();
            $customer = $customer->getByEmail($result->email);

            if (!$customer) {
                $customer = $this->setCustomer($result);
            }

            $login->account_type = (string) $result->account_type;
            $login->user_id = (string) $result->user_id;
            $login->verified_account = (string) $result->verified_account;
            $login->zoneinfo = (string) $result->zoneinfo;
            $login->age_range = (string) $result->age_range;

            return $customer;
        }

        return false;
    }

    /**
     * @param mixed $result
     *
     * @return Customer
     * @throws PrestaShopException
     */
    protected function setCustomer($result)
    {
        $customer = new Customer();
        $customer->firstname = $result->given_name;
        $customer->lastname = $result->family_name;
        $customer->id_lang = Language::getIdByIso(strstr($result->language, '_', true));


        $customer->birthday = $result->birthday;
        $customer->email = $result->email;
        $customer->passwd = Tools::encrypt(Tools::passwdGen());
        $customer->save();

        $resultAddress = $result->address;

        $address = new Address();
        $address->id_customer = $customer->id;
        $address->id_country = Country::getByIso($resultAddress->country);
        $address->alias = 'My address';
        $address->lastname = $customer->lastname;
        $address->firstname = $customer->firstname;
        $address->address1 = $resultAddress->street_address;
        $address->postcode = $resultAddress->postal_code;
        $address->city = $resultAddress->locality;
        $address->phone = $result->phone_number;
        if (isset($resultAddress->region)) {
            if ($idState = (int) State::getIdByIso($resultAddress->region)) {
                $address->id_state = $idState;
            }
        }

        $address->save();

        return $customer;
    }
}
