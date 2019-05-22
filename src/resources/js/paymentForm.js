var eraseCardData = false;

function sendPaymentDataToAnet(eraseData) {
	
	var secureData = {}; authData = {}; cardData = {};
	
	var eraseData = eraseData || false;
	if(eraseData == true) { eraseCardData = true; } 

    // Extract the card number, expiration date, and card code.
    cardData.cardNumber = document.getElementById("number").value;
    cardData.month = document.getElementById("month").value;
    cardData.year = document.getElementById("year").value;
    cardData.cardCode = document.getElementById("cvv").value;
    secureData.cardData = cardData;

    // The Authorize.Net Client Key is used in place of the traditional Transaction Key. The Transaction Key
    // is a shared secret and must never be exposed. The Client Key is a public key suitable for use where
    // someone outside the merchant might see it.
    authData.clientKey = document.getElementById("authorizeKeys").getAttribute('data-clientkey');
    authData.apiLoginID = document.getElementById("authorizeKeys").getAttribute('data-apiLoginId');
    secureData.authData = authData;

    document.getElementById("authorizeSubmit").disabled = true;

	// Pass the card number and expiration date to Accept.js for submission to Authorize.Net.
    Accept.dispatchData(secureData, responseHandler);

}

function responseHandler(response) {
    if (response.messages.resultCode === "Error") {
        for (var i = 0; i < response.messages.message.length; i++) {
            console.log(response.messages.message[i].code + ": " + response.messages.message[i].text);
        }
        alert("Please check your credit card and try again.");
        
        document.getElementById("authorizeSubmit").disabled = false;
        
    } else {
        paymentFormUpdate(response.opaqueData);
	}
}

function paymentFormUpdate(opaqueData) {
	
    document.getElementById("token").value = opaqueData.dataValue;
    document.getElementById("tokenDescriptor").value = opaqueData.dataDescriptor;
    
    // Remove Card Data so that it's not sent back to the server.
    
    if(eraseCardData == true) {
	    document.getElementById("number").value = '';
	    document.getElementById("month").value = '';
	    document.getElementById("year").value = '';
	    document.getElementById("cvv").value = '';
    }
    
    document.getElementById("paymentForm").submit();
    
}