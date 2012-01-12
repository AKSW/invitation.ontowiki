/**
 * The ready-function is called once the website is ready for changes.
 *
 * Here Event-Listeners for the components are activated.
 */
$(document).ready(function() {
    $('#invitloading').addClass('hidden');
    $('#invitinput').autocomplete(urlBase + 'invitation/autocomplete');

    $('#invitadd').live('click', invitationAdd);
   
    $('.invitclear').live('click', function(){
        $(this).parent().remove()
    });

    $('#invitsubmit').live('click', invitationSubmit);
});

/**
 * This function is called when the user presses the submit-button.
 * It sends the list of invitation-mails to the controller.
 */
function invitationSubmit() {
    var mailList = new Array();
    var input = $('#invitinput').val();

    if($('.invitmail').size() > 0) {
        $('.invitmail').each(function(index) {
            mailList.push($(this).attr('title'));
        });
    }
    else if(input.length > 0) {
        if(input.indexOf(',') == -1) {      
            mailList.push(input);
            $('#invitinput').val('E-Mail');
        }
    }
    else return;

    $('#invitmessages').html('');
    $('#invitloading').removeClass('hidden');

    $.post(urlBase + 'invitation/submit','invitemails='+escape(mailList.toString()),invitationMessage);
}

/**
 * This function is called when the users adds an address to the maillist.
 * It sends a message to the controller and receives, whether the email is correct or already invited.
 */
function invitationAdd() {
    var email = $('#invitinput').val();
    if(email.length > 0 && email != 'E-Mail') {
        $('#invitinput').val('E-Mail');
        if($('.invitmail').size() < 5) {
            if($('.invitmail[title='+email+']').length == 0) {
                if(email.indexOf(',') == -1) {
                    var emailprefix = email.length > 12 ? email.substr(0,12)+"..." : email;

                    $('div#invitmaillist').append(
                        '<div class="invitmail token button" title="'+email+'">'+
                            '<img title="invalid address" class="inviticoninvalid" src="'+$('#inviticonerror').attr('src')+'"/>'+
                            '<img title="already invited" class="inviticoninvited" src="'+$('#inviticoninfo').attr('src')+'"/>'+
                            '<img title="remove" class="invitclear" src="'+$('#inviticondelete').attr('src')+'"/>'+
                            '<div class="mail">'+emailprefix+'</div>'+
                        '</div>')

                    $.post(urlBase + 'invitation/invited','q='+escape(email),function(data,textStatus,jqXHR) {
                        if(data == '1') $('.invitmail[title='+email+']').addClass('invitmailinvited');
                        if(data == '2') $('.invitmail[title='+email+']').addClass('invitmailinvalid');
                    });
                }
            }
        }
    }
}

/**
 * This function is called when the controller sends back a message after submiting the maillist.
 * It splits and interprets the message.
 */
function invitationMessage(data,textStatus,jqXHR) {
    var messageBox = $('#invitmessages');
    $('#invitloading').addClass('hidden');
    data = data.trim();
    while(data.length > 0) {
        var msgCode = data.charCodeAt(0);
        var msgLength = (data.charCodeAt(1) << 7) + data.charCodeAt(2);
        var msg = data.substr(3,msgLength);
        data = data.substr(3+msgLength).trim();
        
        switch(msgCode) {
            // Message-code 0 changes the whole content of the module
            case 0:
                $('#invitmain').html(msg);
                break;
            // Message-code 1 displays an success-message
            case 1:
                messageBox.append('<div class="messagebox success">'+msg+'</div>');
                break;
            // Message-code 2 displays an error
            case 2:
                messageBox.append('<div class="messagebox error">'+msg+'</div>');
                break;
            // Message-code 3 displays an alert-window
            case 3:
                alert(msg);
                break;
            // Message-code 4 makes addresses red
            case 4:
                var mailToken = $('.invitmail[title='+msg+']');
                mailToken.removeClass('invitmailinvalid');
                mailToken.removeClass('invitmailinvited');
                mailToken.addClass('invitmailerror')
                break;
            // Message-code 5 removes addresses
            case 5:
                $('.invitmail[title='+msg+']').remove();
                break;
        }
    }
}
