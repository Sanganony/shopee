jQuery(document).ready(function(){
	jQuery("#billing_city,#billing_svw_district,#billing_svw_ward,#shipping_city,#shipping_svw_district,#shipping_svw_ward").select2();


	if ( jQuery('.svw-select-city').length > 0 ) {
		jQuery('.svw-select-city').on( 'change', function(){
			jQuery.ajax({
				type: 'POST',
			  	url: svw.ajax.url,
			  	data: {
			  		city_id : jQuery(this).val(),
			  		action: 'update_checkout_district'
			  	}
			}).done(function(result) {
				jQuery('.svw-select-district').html(result);
			});
		});
	}

	if ( jQuery('.svw-select-district').length > 0 ) {
		jQuery('.svw-select-district').on('change', function(){
			jQuery.ajax({
				type: 'POST',
			  	url: svw.ajax.url,
			  	data: {
			  		district_id : jQuery(this).val(),
			  		action: 'update_checkout_ward'
			  	}
			}).done(function(result) {
				jQuery('.svw-select-ward').html(result);
			});
		});
	}

	if ( jQuery('.svw-select-ward').length > 0 ) {
		// jQuery('.svw-select-ward').on('change', function(){
		// 	jQuery.ajax({
		// 		type: 'POST',
		// 	  	url: svw.ajax.url,
		// 	  	data: {
		// 	  		ward_id : jQuery(this).val(),
		// 	  		action: 'update_ward_id'
		// 	  	}
		// 	}).done(function(result) {
		// 	});
		// });
	}
});