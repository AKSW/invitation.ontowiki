<?php
/**
 * This file is part of the invitation extension for OntoWiki
 */

require_once 'InvitationDB.php';

/**
 * OntoWiki module - Instance Invitation
 *
 * This module represents a little Invitation-Window on the right side of the Instance-View.
 * Its task is to show the Output and manage the Script-includings.
 * @category   OntoWiki
 * @package    Extensions_Invitation
 * @copyright  Copyright (c) 2011, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class InvitationModule extends OntoWiki_Module
{
    /**
     * Returns the title of the Invitation-Module-Window
     * @return title of module-window
     */
    public function getTitle ()
    {
        return 'Invitation';
    }

    /**
     * Returns the content, defined by the Inviation-Template, and implements the Javascript-Elements
     * @return content of module-window
     */
    public function getContents ()
    {

        $this->view->state = 'okay';

        $modelUri = $this->_privateConfig->modeluri;

        //If the user does not have an email, he can't invite
        if (strlen($this->_owApp->user->getEmail()) == 0) {
            $this->view->user = $this->_owApp->user;
            $this->view->state = 'nomail';
        }

        if (!InvitationDB::existsModel($modelUri)) {
            $this->view->state = 'noontology';
            InvitationDB::getModel($modelUri);
        }

        $this->view->headScript()->appendFile($this->view->moduleUrl.'resources/jquery.autocomplete.js');
        $this->view->headScript()->appendFile($this->view->moduleUrl.'resources/invitation.js');
        $this->view->headLink()->appendStylesheet($this->view->moduleUrl.'resources/invitation.css');

        $content = $this->render('templates/invitation/invitation');
        return $content;
    }

    /**
     * Returns whether the Module-Window is visible
     * @return Visibility of Module-Window
     */
    public function shouldShow ()
    {
        return true;
    }
}
