<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 * @package    EcommerceFramework
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */


namespace OnlineShop\Framework\PricingManager\Rule;

class Listing extends \Pimcore\Model\Listing\AbstractListing {

    /**
     * @var array|\OnlineShop\Framework\PricingManager\IRule
     */
    protected $rules;

    /**
     * @var boolean
     */
    protected $validate;


    /**
     * @param bool $state
     */
    public function setValidation($state)
    {
        $this->validate = (bool)$state;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function isValidOrderKey($key)
    {
        return in_array($key, array('prio', 'name'));
    }

    /**
     * @return array
     */
    function getRules()
    {
        // load rules if not loaded yet
        if(empty($this->rules))
            $this->load();

        return $this->rules;
    }

    /**
     * @param array $rules
     * @return void
     */
    function setRules(array $rules)
    {
        $this->rules = $rules;
    }

}
