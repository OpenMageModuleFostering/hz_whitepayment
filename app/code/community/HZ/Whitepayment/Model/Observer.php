<?php
/**
 * NOTICE OF LICENSE
 *
 * @category	Whitepayment
 * @package		HZ_Whitepayment
 * @author		Whitepayment.
 * @license		http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class HZ_Whitepayment_Model_Observer {
	public function disableMethod(Varien_Event_Observer $observer){
		$moduleName="HZ_Whitepayment";
		if('whitepayment'==$observer->getMethodInstance()->getCode()){
			if(!Mage::getStoreConfigFlag('advanced/modules_disable_output/'.$moduleName)) {
				//nothing here, as module is ENABLE
			} else {
				$observer->getResult()->isAvailable=false;
			}
			
		}
	}
}
?>