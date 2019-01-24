# commerce-authorize
Authorize.net AIM - Craft Commerce 2 Plugin

'''<fieldset>
	
		<label>Card Holder</label>
		<input maxlength="70" name="firstName" placeholder="First Name" required="required" type="text">
		<input maxlength="70" name="lastName" placeholder="Last Name" required="required" type="text">

		<label>Card Number</label>
		<input id="number" maxlength="19" name="number" placeholder="Card Number" required="required" type="text">
		
		<label>Card Expiration Date</label>
		<select id="month" name="month" required="required">
			<option value="{{ month }}">{{ month }}</option>
		</select>
		<select id="year" name="year" required="required">
			<option value="{{ year }}">{{ year }}</option>
		</select>
		
		<label>CVV/CVV2</label>
		<input id="cvv" maxlength="4" name="cvv" placeholder="CVV" required="required" type="text">
		
		<!-- Required fields for Authorize.net Accept.js -->
		
		<input id="token" name="token" type="hidden"> 
		<input id="tokenDescriptor" name="tokenDescriptor" type="hidden"> 
		
		{{ cart.gateway.getPaymentFormHtml({})|raw }}
		
		<button class="store-button" id="authorizeSubmit" name="authorizeSubmit" onclick="event.preventDefault(); sendPaymentDataToAnet();">Pay Now</button>
	
</fieldset>'''
