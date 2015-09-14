jQuery(document).ready(function($) {

	jQuery('#lookup').click(function(evt) {

		evt.preventDefault();

		var $nonce = jQuery('#ajaxsecurity').val(),
		$api_key = + jQuery('#locu_api_key').val();
		$name = + jQuery('#locu_establishment_name').val();

		// Information of our Request
    var data = {
        'action': 'locu_ajax_look_up',
        'data': { api_key : jQuery('#locu_api_key').val(), name : jQuery('#locu_establishment_name').val() },
        'nonce': $nonce
    };

	    // The variable ajax_url should be the URL of the admin-ajax.php file
	    $.post( ajaxurl, data, function(response) {
	        jQuery('#locu_id').val(response.locu_id);
	    }, 'json');

	});    



		

});