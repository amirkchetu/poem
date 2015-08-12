var $j = jQuery.noConflict();
$j(document).ready(function() {
    // Change the help text of the server addresses
    var utgVal = $j("#payment_shift4_payment_use_utg option:selected").val();
    var enabledVal = $j("#payment_shift4_payment_active option:selected").val();
    var processingMode = $j("#payment_shift4_payment_processing_mode option:selected").val();

    if (processingMode == 'demo') {
        $j('#enabled_no').hide();
        $j('#utg_no').hide();
        $j('#utg_yes').hide();
        $j('#processing_mode_demo').show();
        disableFields(true);
    }
    if (enabledVal == 0) {
        $j('#utg_no').hide();
        $j('#utg_yes').hide();
        $j('#enabled_no').show();
        $j('#processing_mode_demo').hide();
        removeAsterisk(true);
        $j('fieldset#payment_shift4_payment :input').not('#payment_shift4_payment_active').attr('disabled', 'disabled');
        $j('fieldset#payment_shift4_payment :input').not(this).removeClass('required-entry');
    }
    $j('#payment_shift4_payment_active').change(function() {
        $j('#utg_no').hide();
        $j('#utg_yes').hide();
        if (this.value == 0) {
            $j('#enabled_no').show();
            $j('#processing_mode_demo').hide();
            removeAsterisk(true);
            $j('fieldset#payment_shift4_payment :input').not(this).attr('disabled', 'disabled');
            $j('fieldset#payment_shift4_payment :input').not(this).removeClass('required-entry');
        } else {
            removeAsterisk(false);
            $j('fieldset#payment_shift4_payment :input').not('#payment_shift4_payment_active, #payment_shift4_payment_access_token').removeAttr('disabled');
            $j('fieldset#payment_shift4_payment :input').not('#payment_shift4_payment_active, [type=button]').addClass('required-entry');
        }
    });
    // Change the Help text of the Server addresses as user changes the dropdown
    $j('#payment_shift4_payment_use_utg').change(function() {
        $j('#enabled_no').hide();
        $j('#processing_mode_demo').hide();
        if (this.value == 1) {
            $j('#utg_yes').show();
            $j('#utg_no').hide();
        } else {
            $j('#utg_no').show();
            $j('#utg_yes').hide();
        }
    });
    $j('#payment_shift4_payment_processing_mode').change(function() {
        $j('#enabled_no').hide();
        $j('#utg_no').hide();
        $j('#utg_yes').hide();
        if (this.value == 'demo') {
            $j('#enabled_no').hide();
            $j('#processing_mode_demo').show();
            $j('#payment_shift4_payment_auth_token').val('');
            // Disable fields
            disableFields(true);
        } else {
            $j('#enabled_no').show();
            $j('#processing_mode_demo').hide();
            disableFields(false);
        }
    });
    if (utgVal == 1) {
        $j('#utg_yes').show();
        $j('#utg_no').hide();
        $j('#enabled_no').hide();
        $j('#processing_mode_demo').hide();
    } else if (utgVal == 0) {
        $j('#utg_no').show();
        $j('#utg_yes').hide();
        $j('#enabled_no').hide();
        $j('#processing_mode_demo').hide();
    } else if (enabledVal == 1) {
        $j('#enabled_no').show();
        $j('#utg_no').hide();
        $j('#utg_yes').hide();
    } else if (processingMode == 'demo') {
        $j('#enabled_no').hide();
        $j('#utg_no').hide();
        $j('#utg_yes').hide();
        $j('#processing_mode_demo').show();
    }

    var accessToken = $j('#payment_shift4_payment_access_token').val();
    if (accessToken != '') {
        $j("label[for=payment_shift4_payment_auth_token]").text('Auth Token');
        $j('#payment_shift4_payment_auth_token').removeClass('required-entry');
        $j("#row_payment_shift4_payment_auth_token").hide();
    } else {
        $j("label[for=payment_shift4_payment_auth_token]").text('Auth Token*');
        $j('#payment_shift4_payment_auth_token').addClass('required-entry');
        $j("#row_payment_shift4_payment_masked_access_token").hide();
    }
    
    
    function disableFields(flag) {
        var fieldsArray = ['payment_shift4_payment_use_utg', 'payment_shift4_payment_server_addresses', 'payment_shift4_payment_auth_token', 'payment_shift4_payment_masked_access_token'];
        $j(fieldsArray).each(function(index, id) {
            var labelText = $j('label[for=' + id + ']').text();
            var str;
            if (flag == true) {
                if (labelText.indexOf('*') !== -1) {
                    str = labelText.substring(0, labelText.length - 1);
                }
                $j("#" + id).attr('disabled', 'disabled').removeClass('required-entry');
            } else {
                if (labelText.indexOf('*') === -1) {
                    str = labelText;
                    str = str + '*';
                }
                $j("#" + id).removeAttr('disabled').addClass('required-entry');
            }
            $j('label[for=' + id + ']').text(str);
        });
		// check if token already exchanged
		var maskedToken = $j("#payment_shift4_payment_masked_access_token").val();
		if (maskedToken != '') {
			$j("#payment_shift4_payment_auth_token").removeClass('required-entry');
		}
    }

    function removeAsterisk(flag) {
        $j('#payment_shift4_payment table tr').each(function(index, value) {
            var rowid = $j(this).attr('id');
            var label = $j('#' + rowid).find('label');
            var labelText = label.text();
            var str;
            if (flag == true) {
                if (labelText.indexOf('*') !== -1) {
                    str = labelText.substring(0, labelText.length - 1);
                }
            } else {
                if (labelText.indexOf('*') === -1) {
                    var str = labelText;
                    str = str + '*';
                }
            }
            label.text(str);
        });
    }

    /* collapse and expand help text */
    $j("#payment_shift4_payment p.note,#payment_shift4_payment_cardsaved p.note").append("&nbsp;");
    $j("#payment_shift4_payment p.note,#payment_shift4_payment_cardsaved p.note").addClass("collapse");
    $j("#payment_shift4_payment p.note,#payment_shift4_payment_cardsaved p.note").removeClass("note");
    $j("#payment_shift4_payment p.collapse,#payment_shift4_payment_cardsaved p.collapse").children("span").hide();

    $j("#payment_shift4_payment p.collapse,#payment_shift4_payment_cardsaved p.collapse").click(function(e) {
        if (e.target != this)
            return;

        $j(this).children("span").toggle('show',
                function() {
                    if ($j(this).parent().hasClass("collapse")) {
                        $j(this).parent().addClass("expand");
                        $j(this).parent().removeClass("collapse");
                    } else {
                        $j(this).parent().addClass("collapse");
                        $j(this).parent().removeClass("expand");
                    }
                }
        );
    });
    /* END collapse and expand help text */
    unMaskAccessCode();

    /* arrange reset button */

    jQuery("#row_payment_shift4_payment_server_addresses > td > button").appendTo('#row_payment_shift4_payment_server_addresses > td:last');
    jQuery("#row_payment_shift4_payment_auth_token > td > button").appendTo('#row_payment_shift4_payment_auth_token > td:last');
    jQuery("#row_payment_shift4_payment_masked_access_token > td > button").appendTo('#row_payment_shift4_payment_masked_access_token > td:last');

    /* END arrange reset button */
    
    //Make Aceess Token Read Only
    jQuery("#payment_shift4_payment_masked_access_token").attr('readonly','readonly');

});

/* Add the title validation */

Validation.addAllThese([
    ['validate-shift4-payment-title', 'The title must be between 1 and 100 valid characters: a-z, A-Z, 0-9, period (.), hyphen (-), space ( ), apostrophe (\'), or registered mark (®).', function(v) {
            return Validation.get('IsEmpty').test(v) || /^[a-zA-Z0-9.\,\-\(\)\®\'\s]{1,100}$/.test(v)
        }]
]);

function unMaskAccessCode() {
    /* Unmask access token code */
    mask_value = $j("#payment_shift4_payment_access_token").val();
    mask_first = mask_value.substring(0, 4);
    mask_last = mask_value.substring(mask_value.length - 4, mask_value.length);
    mask_middle = mask_value.substring(4, mask_value.length - 4);
    unmask_value = mask_first + '-XXXX-XXXX-XXXX-XXXX-' + mask_last;
    if (mask_value != '') {
        $j("#payment_shift4_payment_masked_access_token").val(unmask_value);
    }
    $j("#row_payment_shift4_payment_access_token").hide();

    /* END Unmask access token code */
}  