var eraseCardData = false;

function sendPaymentDataToAnet(eraseData) {
	
	var secureData = {}; authData = {}; cardData = {};
	
	var eraseData = eraseData || false;
	if(eraseData == true) { eraseCardData = true; } 

    // Extract the card number, expiration date, and card code.
    cardData.cardNumber = document.querySelector('[id$="number"]').value.replace(/\s+/g, '');
    cardData.month = document.querySelector('[id$="month"]').value;
    cardData.year = document.querySelector('[id$="year"]').value;
    cardData.cardCode = document.querySelector('[id$="cvv"]').value.replace(/\s+/g, '');
    secureData.cardData = cardData;

    // The Authorize.Net Client Key is used in place of the traditional Transaction Key. The Transaction Key
    // is a shared secret and must never be exposed. The Client Key is a public key suitable for use where
    // someone outside the merchant might see it.
    authData.clientKey = document.querySelector('[id$="authorizeKeys"]').getAttribute('data-clientkey');
    authData.apiLoginID = document.querySelector('[id$="authorizeKeys"]').getAttribute('data-apiLoginId');
    secureData.authData = authData;

    document.querySelector('[id$="authorizeSubmit"]').disabled = true;

	// Pass the card number and expiration date to Accept.js for submission to Authorize.Net.
    Accept.dispatchData(secureData, responseHandler);

}

function responseHandler(response) {
    if (response.messages.resultCode === "Error") {
        for (var i = 0; i < response.messages.message.length; i++) {
            console.log(response.messages.message[i].code + ": " + response.messages.message[i].text);
        }
        alert("Please check your credit card and try again.");
        
        document.querySelector('[id$="authorizeSubmit"]').disabled = false;
        
    } else {
        paymentFormUpdate(response.opaqueData);
	}
}

function paymentFormUpdate(opaqueData) {
	
    document.querySelector('[id$="token"]').value = opaqueData.dataValue;
    document.querySelector('[id$="tokenDescriptor"]').value = opaqueData.dataDescriptor;
    
    // Remove Card Data so that it's not sent back to the server.
    
    if(eraseCardData == true) {
	    document.querySelector('[id$="number"]').value = '';
	    document.querySelector('[id$="month"]').value = '';
	    document.querySelector('[id$="year"]').value = '';
	    document.querySelector('[id$="cvv"]').value = '';
    }
    
    document.getElementById("paymentForm").submit();
    
}
