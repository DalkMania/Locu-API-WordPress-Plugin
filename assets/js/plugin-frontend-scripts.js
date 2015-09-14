jQuery(document).ready(function($) {

	if( $('.locu-nav').children().not( $('.locu-active') ) ) {
		$('.locu-nav li:first-child').addClass('locu-active');
		var tab_id = $('.locu-nav li:first-child').attr('data-tab');
		$("#"+tab_id).addClass('locu-panel-active');
	}

	$('.locu-nav li').click(function(){
		var tab_id = $(this).attr('data-tab');
		$('.locu-nav li').removeClass('locu-active');
		$('.locu-panel').removeClass('locu-panel-active');

		$(this).addClass('locu-active');
		$("#"+tab_id).addClass('locu-panel-active');
		
	});

});