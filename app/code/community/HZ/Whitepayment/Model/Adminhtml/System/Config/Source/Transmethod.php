<?php	
class HZ_Whitepayment_Model_Adminhtml_System_Config_Source_Transmethod{
	public function toOptionArray(){			
		$options = array(		
			array('value' => 'AUTH_CAPTURE', 'label'=>'Capture'),
			array('value' => 'AUTH_ONLY', 'label'=>'Authorization'),
		);			
		return $options;			
	}		
}
?>