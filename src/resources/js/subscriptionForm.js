var eraseCardData = false;

function sendPaymentDataToAnet(target, eraseData) {
    
    // Assign target to global variable so we can use later.
    
    window.target = target;
	
	var secureData = {}; authData = {}; cardData = {};
	
	var eraseData = eraseData || false;
	if(eraseData == true) { eraseCardData = true; } 

    // Extract the card number, expiration date, and card code.
    cardData.cardNumber = target.querySelector("input[name$=number]").value;
    cardData.month = target.querySelector("input[name$=month]").value;
    cardData.year = target.querySelector("input[name$=year]").value;
    cardData.cardCode = target.querySelector("input[name$=cvv]").value;
    secureData.cardData = cardData;

    // The Authorize.Net Client Key is used in place of the traditional Transaction Key. The Transaction Key
    // is a shared secret and must never be exposed. The Client Key is a public key suitable for use where
    // someone outside the merchant might see it.
    authData.clientKey = document.querySelector('[id$="authorizeKeys"]').getAttribute('data-clientkey');
    authData.apiLoginID = document.querySelector('[id$="authorizeKeys"]').getAttribute('data-apiLoginId');
    secureData.authData = authData;

    document.getElementById('[id$="authorizeSubmit"]').disabled = true;

	// Pass the card number and expiration date to Accept.js for submission to Authorize.Net.
    Accept.dispatchData(secureData, responseHandler);

}

function responseHandler(response) {
    // Accept.js responseHandler fails if there's another JS error on the page.
    // This try/catch loop exposes other JS errors to the developer through the console.
    try {
        if (response.messages.resultCode === "Error") {
            for (var i = 0; i < response.messages.message.length; i++) {
                console.log(response.messages.message[i].code + ": " + response.messages.message[i].text);
            }
            alert("Please check your credit card and try again.");
            
            document.getElementById('[id$="authorizeSubmit"]').disabled = false;
            
        } else {
            paymentFormUpdate(response.opaqueData);
    	}
    } catch (error) {
        console.log(error);
    }
}

function paymentFormUpdate(opaqueData) {
	
    window.target.querySelector("input[name=$token").value = opaqueData.dataValue;
    window.target.querySelector("input[name=$tokenDescriptor]").value = opaqueData.dataDescriptor;
    
    // Remove Card Data so that it's not sent back to the server.
    
    if(eraseCardData == true) {
	    window.target.querySelector("input[name$=number]").value = '';
	    window.target.querySelector("input[name$=month]").value = '';
	    window.target.querySelector("input[name$=year]").value = '';
	    window.target.querySelector("input[name$=cvv]").value = '';
    }
    
    window.target.submit();
    
}