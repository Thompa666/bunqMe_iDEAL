<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/qrcode.min.js"></script>

<script type="text/javascript" > 
(function($) {
	$.fn.currencyInput = function() {
		this.each(function() {
		  var wrapper = $("<div class='currency-input' />");
		  $(this).wrap(wrapper);
		  $(this).before("<span class='currency-symbol'>€</span>");
		  $(this).change(function() {
			var min = parseFloat($(this).attr("min"));
			var max = parseFloat($(this).attr("max"));
			var value = this.valueAsNumber;
			if(value < min)
			  value = min;
			else if(value > max)
			  value = max;
			$(this).val(value.toFixed(2)); 
		  });
		});
	  };
})(jQuery);

$(document).ready(function() {
	$('input.currency').currencyInput();
  
	function guid() {
		function s4() {
			return Math.floor((1 + Math.random()) * 0x10000)
				.toString(16)
				.substring(1);
			}
			return s4() + s4() + '-' + s4() + '-' + s4() + '-' +
			s4() + '-' + s4() + s4() + s4();
	}

	function getIdealIssuers() {
		$.ajax({
			type: 'GET',
			url: 'https://api.bunq.me/v1/bunqme-merchant-directory-ideal',
			beforeSend: function(xhr) {
				xhr.setRequestHeader("X-Bunq-Client-Request-Id", guid());
				xhr.setRequestHeader("X-Bunq-Geolocation", "0 0 0 0 NL");	
				xhr.setRequestHeader("X-Bunq-Language", "en_US");	
				xhr.setRequestHeader("X-Bunq-Region", "en_US");	
			},
			success: function(issuerData) {
				idealIssuers = issuerData['Response'][0]['IdealDirectory']['country'][0]['issuer'];
				$.each(idealIssuers, function(key, value) {   
					$('#idealIssuer').append($("<option></option>")
						.attr("value",value.bic)
						.text(value.name)); 
				});
			}
		});
	}

	$('#btnPayBunq').click(function() {
		$('#bunqMobileLink').html('Please wait for payment link...');
		
		$.ajax({
			type: 'POST',
			data: '{"amount": "'+$('#inputAmount').val()+'","description": "'+$('#inputDescription').val()+'","issuer": "BUNQNL2A"}',
			url: 'bunqMeIDEALrequest.php',
			success: function(idealData) {
				if(idealData['bunqToken'].length > 0){
					$('#iDEAL').hide();
					$('#bunqMobileLink').html('<a href=bunq://x-bunq-app/token-qr-bunqme-tab-entry?token='+idealData['bunqToken']+' target=_blank><b>Open in bunq app</b></a>');
					$('#qr-code-bunq').attr('src','data:image/png;base64,'+idealData['bunqQrCode'])
				}				
			}
		});
	});  
	
	$('#btnPayIdeal').click(function() {
		$('#idealIssuer').find('option').not(':first').remove();
		getIdealIssuers();
		
		$('#bunqMobileLink').html('');
		$('#qr-code-bunq').attr('src','');
		$('#iDEAL').show();
	});  
	
	$( "#idealIssuer" ).change(function () {
		$('#iDEALlink').html('Please wait for payment link...');

		$selectedIssuer = this.value;
		$.ajax({
			type: 'POST',
			data: '{"amount": "'+$('#inputAmount').val()+'","description": "'+$('#inputDescription').val()+'","issuer": "'+this.value+'"}',
			url: 'bunqMeIDEALrequest.php',
			success: function(idealData) {
				if(idealData['url'].length > 0){
					$('#iDEALlink').html('<a href='+idealData['url']+' target=_blank><b>Betalen via iDEAL</b></a>');	
					window.location.href = idealData['url'];
				}			
			}
		});
	});
	
	$("#inputAmount").keyup(function() {
		$('#btnPayBunq').prop("disabled", false);
		$('#btnPayIdeal').prop("disabled", false);
	});
});
</script>

<style>
	.btn {
	  font-size:24px;	
	  height: 33px;
	}

	.currency {
	  font-size:24px;	
	  padding-left:12px;
	  width: 150px;
	  height: 30px;
	}

	.currency-symbol {
	  font-size:24px;	 	
	  position:absolute;
	  padding: 5px 1px;
	  vertical-align: middle;
	}
</style>

Amount: <br/><input type="number" id="inputAmount" class="currency" min="0.01" max="2500.00" value="" />
<br/>
Description: <br/><input type="text" id="inputDescription" value="Food" />
<br/>
<br/>
<button type="button" class="btn" id="btnPayBunq" disabled>Pay with bunq</button>
<button type="button" class="btn" id="btnPayIdeal" disabled>Pay with iDEAL</button>
<br/>
<img id="qr-code-bunq" src="">
<br/>
<p id="bunqMobileLink"></p>
<br/>
<div id="iDEAL" hidden>
	<b>Select bank:</b> 
	<select name="idealIssuer" id="idealIssuer">
		<option value="" disabled="" selected="">Select a bank</option>
	</select>
	<br/>
	<p id="iDEALlink"></p>
</div>
