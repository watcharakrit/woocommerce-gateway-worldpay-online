jQuery(document).ready(function($){
	var checkbox = document.getElementsByName('worldpay_use_saved_card_details')[0];
	var newCardFormSections = $('.worldpay_new_card_fields');

	var testing = $("#worldpay_use_saved_card_details").val();
	// comsole.log( testing );

	if(checkbox != null)
	{
		newCardFormSections.hide();
		$(checkbox).click(function()
		{
			newCardFormSections.toggle();
		});
	}
	WorldpayCheckout.setupNewCardForm();
});