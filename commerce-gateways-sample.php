<?php return [  
	
	// If you would like to store your Gateway settings outside the Control Panel, place this file in the config folder.
	
	// After you create your gateway in Craft Commerce, you can use the handle in Commerce > System Settings > Gateways table to overwrite the settings here.
	
	'placeYourGatewayHandleHere' => [
	
		// API Login ID
		
		'apiLoginId' => '',
		
		// Transaction Key
		
		'transactionKey' => '',
		
		// Public Client Key
		
		'publicKey' => '',
		
		// Set this to 'true' when using the Authorize.net Sandbox.
		
		'developerMode' => false,
		
		// Set this to true to use Accept.js. Payment button changes are required unless using the default display below.
		
		'acceptJS' => true,
		
		// Authorize.net does not allow refunds to process until they have settled (around 24 hours). 
		// This value (true) automatically voids the transaction if the refund fails. 
		// The entire transaction will be voided, partial voiding is not possible.
		
		'voidRefunds' => true,
		
		//////////////////////////
		// DEFAULT PAYMENT FORM //
		//////////////////////////
		
		// If you wish to use a custom form as outlined in the documentation, leave set value in this section to 'false'.
		
		// Insert the default form when cart.gateway.getPaymentFormHtml is called in the checkout template.
		
		'insertForm' => true,
		
		// Customize the text that appears inside the submit button at the bottom of the form.
		
		'paymentButton' => 'Complete Checkout',
		
		// Only applies when using the default form display in conjunction with Accept.js. See the documentation for additional details.
		
		'disableAcceptData' => true,
		
		///////////////////////////
		// SAVED PAYMENT METHODS //
		///////////////////////////
		
		// Payment sources are saved using the Authorize.net Customer Information Manager (CIM). 
		// CIM must be enabled inside Authorize.net prior to enabling this feature. 
		// After enabling stored payment sources, the only credit card information stored in the database will be the last four digits of the card number so that the card can be identified later (if Accept.js is not enabled).
		
		//⚠ WARNING: If this feature is disabled after payment sources are saved, an error will be thrown if the customer tries to use or modify the payment source. 
		// You may wish to run a database backup and then manually clear the Payment Sources database table before disabling this feature.
		
		'savePaymentMethods' => false,
		
		// Enter the text that should appear before the last four digits of the credit card number in the payment selector (i.e. 'Saved Card - x' will result in 'Saved Card - x9999'). 
		// If left blank, only the last 4 digits will appear. Payment methods that have already been stored will not be affected by this field change. 
		// If Accept.js is enabled, the last four digits of the card number will not be appended to this label. 
		// If the description field is filled by the customer, it will override the card name set in this field.
		
		'savedCardPrefix' => 'Saved Card - x'
		
		// Note: If Accept.js is enabled, a separate profile will be created for each payment source stored inside Authorize.net. See the documentation for additional details.
	],
];