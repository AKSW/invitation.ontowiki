<?php
    require_once 'InvitationDB.php';

    /**
     * OntoWiki module - Instance Invitation
     *
     * The Controller returns and controls the Autocomplete-Function of the Invitation-Module
     * @category   OntoWiki
     * @package    OntoWiki_extensions_modules_outline
     * @copyright  Copyright (c) 2011, {@link http://aksw.org AKSW}
     * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
     */
    class InvitationController extends OntoWiki_Controller_Component {

        /**
         * This function is called when the user adds a new mail to the sendlist.
         * The function prooves whether there is already an invitation to that mail and whether the mail is correct.
         * It sends back the following codes: 0 = ok, 1 = already invited, 2 = mail invalid
         */
        public function invitedAction() {
            $this->_helper->viewRenderer->setNoRender();

            $validator   = new Zend_Validate_EmailAddress();
            $receiver    = trim($this->_request->q);

            if(!$validator->isValid($receiver)) {
                echo '2';
                return;
            }

            $modelUri    = $this->_privateConfig->modeluri;
            $accountMail = $this->_owApp->user->getEmail();
            $resource    = $this->_owApp->selectedResource;

            if(InvitationDB::wasInvited($modelUri, $accountMail, $receiver, $resource)) {
                echo '1';
                return;
            }

            echo '0';
        }

        /**
         * This function returns the result of the Autocomplete-Query for the Invitation-Module
         */
        public function autocompleteAction() {
            $this->_helper->viewRenderer->setNoRender();

            $modelUri = $this->_privateConfig->modeluri;
            $accountMail = $this->_owApp->user->getEmail();

            $mails = InvitationDB::getInvitationMails($modelUri,$accountMail,$this->_request->q);

            foreach($mails as $mail) echo $mail."\n";
        }

        /**
         * This function is called when the invited person clicks on the invitation-link.
         * Here the properties-template is loaded and displayed which shows all properties of the instance.
         */
        public function instanceAction() {
            try {
                $modelUri = $this->_privateConfig->modeluri;

                $invitationCode = $this->_request->inv;
                $resource       = InvitationDB::getInstance($modelUri,$invitationCode);

                $store          = $this->_owApp->erfurt->getStore();
                $graph          = $store->getModel($resource['modelUri']);
                $translate      = $this->_owApp->translate;

                //Set the title
                $title = OntoWiki_Utils::contractNamespace($resource['instanceUri']);
                $windowTitle = sprintf($translate->_('Properties of %1$s'), $title);
                $this->view->placeholder('main.window.title')->set($windowTitle);

                //Load the property-graph
                $model = new OntoWiki_Model_Resource($store, $graph, $resource['instanceUri']);
                $values = $model->getValues();
                $predicates = $model->getPredicates();

                //Set values for Output-Template
                $this->view->values        = $values;
                $this->view->predicates    = $predicates;
                $this->view->resourceUri   = $resource;

            } catch(Exception $e) {
                $this->view->error = $e->getMessage();
            }
        }

        /**
         * This function is called by the Modul-Javascript when the Send-Button is pressed.
         * It validates the input and sends an email to the given address.
         * The function also sends back one or more messages, which control the output of the module.
         */
        public function submitAction() {
            $this->_helper->viewRenderer->setNoRender();

            $eingabe = $this->_request->invitemails;
            $receiverList = explode(',',$eingabe);
            $modelUri = $this->_privateConfig->modeluri;

            $validator = new Zend_Validate_EmailAddress();

            $resource                  = $this->_owApp->selectedResource;
            $resourceModel             = $this->_owApp->selectedModel;
            $accountMail               = $this->_owApp->user->getEmail();

            $invitationCount = InvitationDB::invitationCount($modelUri, $accountMail, intval($this->_privateConfig->invitationlimittime));

            //Proove, whether user is allowed to send more invitations
            if($invitationCount + count($receiverList) > intval($this->_privateConfig->invitationlimit)) {
                echo $this->buildMessage(2,'No more invitations possible!');
                return;
            }

            if(strlen($this->_privateConfig->smtp->server) > 0)
            {
                $config = $this->_privateConfig->smtp->config->toArray();
                $transport = new Zend_Mail_Transport_Smtp($this->_privateConfig->smtp->server,$config);
            }
            else $transport = null;

            $successMails = array();
            $errorMails = array();
            $invalidMails = array();
            foreach($receiverList as $receiver) {

                $receiver = trim($receiver);

                if(!$validator->isValid($receiver)) {
                    $invalidMails[] = $receiver;
                    echo $this->buildMessage(5,$receiver);
                    continue;
                }

                //Generate an unique invitation-Code
                $invitationCode            = InvitationDB::generateCode($modelUri);

                //Load the mailcontent from the template
                $this->view->sender        = $accountMail;
                $this->view->invitationURL = $this->_config->urlBase.'invitation/instance?inv='.urlencode($invitationCode);
                $content                   = $this->view->render('templates/invitation/email.phtml');


                try {
                    $mail = new Zend_Mail();

                    if($transport != null) $mail->setDefaultTransport($transport);

                    $mail->addTo($receiver);
                    $mail->setFrom($this->_privateConfig->sender);
                    $mail->setSubject($this->_privateConfig->title);
                    $mail->setBodyText($content);
                    if($transport != null) $mail->send($transport);
                    else $mail->send();

                    //Save invitation in database
                    InvitationDB::addInvitation($modelUri,$invitationCode,$accountMail,$receiver,$resourceModel->getModelUri(),$resource);

                    $successMails[] = $receiver;
                    echo $this->buildMessage(5,$receiver);
                } catch(Exception $e) {
                    $errorMails[] = $receiver;
                    echo $this->buildMessage(4,$receiver);
                    echo $this->buildMessage(3,$e->getMessage());
                }
            }

            // Send message with successfully sent mails
            if(count($successMails) > 0) {
                $msg = 'Invitations sent to:';
                foreach($successMails as $mail) $msg .= '<br/>'.(strlen($mail) > 12 ? substr($mail,0,12).'...' : $mail);
                echo $this->buildMessage(1,$msg);
            }

            // Send message with invalid mails
            if(count($invalidMails) > 0) {
                $msg = 'Invalid addresses:';
                foreach($invalidMails as $mail) $msg .= '<br/>'.(strlen($mail) > 12 ? substr($mail,0,12).'...' : $mail);
                echo $this->buildMessage(2,$msg); 
            }

            // Send message with mails which could not be sent
            if(count($errorMails) > 0) {
                $msg = 'Unable to send:';
                foreach($errorMails as $mail) $msg .= '<br/>'.(strlen($mail) > 12 ? substr($mail,0,12).'...' : $mail);
                echo $this->buildMessage(2,$msg);
            }
        }

        /**
         * This function encodes the given message into the controller-module-communication-format
         * - The first byte is the message-code
         * - The second and third byte represent the length of the messsage
         * - The length-bytes are followed by the message
         * @param $type The type is the message-code
         * @param $message The main message-text
         * @return The encoded message-string
         */
        public function buildMessage($type,$message) {
            //In several browsers there is a javascript-bug causing char-values above 127 to turn into 65533
            //That is the reason we shift only 7 to the right and cut with 0x7F
            return $message = chr($type & 0xFF).chr(strlen($message) >> 7 & 0x7F).chr(strlen($message) & 0x7F).$message;
        }
    }
?>
