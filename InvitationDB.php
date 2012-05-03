<?php

/**
 * The InvitationDB-Class represents the interface between the database and the controller.
 * It manages the invitations and checks all constraints
 */
class InvitationDB
{

    /**
     * This function returns the model to the ontology to run queries
     * @param modelUri The uri to the ontology
     * @return Model of ontology
     */
    public static function getModel($modelUri)
    {
        $store = Erfurt_App::getInstance()->getStore();
        try {
          return $store->getModel($modelUri);
        } catch(Exception $e) {
          return self::createModel($modelUri);
        }
    }

    /**
     * This function creates a new invitation-ontology with the given uri
     * @param modelUri The uri of the new ontology
     * @return The model of the new ontology
     */
    private static function createModel($modelUri)
    {
        $store = Erfurt_App::getInstance()->getStore();
        try {
            $model = $store->getNewModel($modelUri);
            $queryCache = Erfurt_App::getInstance()->getQueryCache();
            $config = Erfurt_App::getInstance()->getConfig();

            $store->addStatement(
                $modelUri,
                'Invitation',
                'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
                array('value' => 'http://www.w3.org/2002/07/owl#Class','type' => 'uri')
            );

            $model->setOption(
                $config->sysont->properties->hidden,
                array(
                    array(
                        'value'    => 'true',
                        'type'     => 'literal',
                        'datatype' => EF_XSD_BOOLEAN
                    )
                )
            );
            return $model;
        } catch (Erfurt_Store_Exception $e) {
            return null;
        }
    }

    /**
     * This function prooves whether there is an ontology with the given uri
     * @param modelUri The uri to the ontology
     * @return Whether the ontology exists
     */
    public static function existsModel($modelUri)
    {
        $store = Erfurt_App::getInstance()->getStore();
        try {
          $model = $store->getModel($modelUri);
          return true;
        } catch(Exception $e) {
          return false;
        }
    }

    /**
     * This function checks whether there is already an invitation from the sender to the
     * receiver for the instanceUri.
     * @param modelUri The uri to the ontology
     * @param sender The address of the sender
     * @param receiver The address of the receiver
     * @param instanceUri The uri to the invitation-instance
     * @return The number of invitations from the sender to the receiver for the instance
     */
    public static function wasInvited($modelUri, $sender, $receiver, $instanceUri)
    {
        $model = self::getModel($modelUri);

        $query = new Erfurt_Sparql_SimpleQuery();
        $query->setProloguePart('SELECT ?instanceUri')
            ->setWherePart(
                'WHERE {' .
                '?resourceUri <http://rdfs.org/sioc/ns#email> "'.$sender.'" . ' .
                '?resourceUri <http://rdfs.org/sioc/ns#addressed_to> "'.$receiver.'" . ' .
                '?resourceUri <http://rdfs.org/sioc/ns#topic> ?instanceUri . ' .
                'FILTER (?instanceUri = <'.$instanceUri.'>) }'
            )
            ->setOrderClause('?instanceUri');
        $queryData = $model->sparqlQuery($query);

        return count($queryData);
    }

    /**
     * This function returns an array of mail-addresses which where invited by the sender.
     * @param modelUri The uri to the ontology
     * @param sender The address of the sender
     * @param like A char-sequence which must be found in the receiver-address
     * @return A List of all addresses invited by the sender
     */
    public static function getInvitationMails($modelUri, $sender, $like)
    {
        $model = self::getModel($modelUri);

        $query = new Erfurt_Sparql_SimpleQuery();
        $query->setProloguePart('SELECT ?receiver')
            ->setWherePart(
                'WHERE {' .
                '?resourceUri <http://rdfs.org/sioc/ns#email> "'.$sender.'" . ' .
                '?resourceUri <http://rdfs.org/sioc/ns#addressed_to> ?receiver . ' .
                'FILTER regex(?receiver,"'.$like.'","i") }'
            )
            ->setOrderClause('?receiver');
        $queryData = $model->sparqlQuery($query);

        $result = array();
        foreach($queryData as $mail) $result[] = $mail['receiver'];

        return array_unique($result);
    }

    /**
     * This function returns the instance-uri to an invitation specified by the code
     * @return A tuple with the model- and instance-Uri
     */
    public static function getInstance($modelUri, $invitationCode)
    {
        $model = self::getModel($modelUri);

        $query = new Erfurt_Sparql_SimpleQuery();
        $query->setProloguePart('SELECT ?instanceUri, ?modelUri')
            ->setWherePart(
                'WHERE {' .
                '?resourceUri <http://www.w3.org/2000/01/rdf-schema#label> "'.$invitationCode.'".' .
                '?resourceUri <http://rdfs.org/sioc/ns#topic> ?instanceUri .' .
                '?resourceUri <http://rdfs.org/sioc/ns#Container> ?modelUri }'
            );
        $queryData = $model->sparqlQuery($query);

        if(count($queryData) == 0) throw new Exception('Invitationcode invalid!'); //Code not found

        return $queryData[0];
    }

    /**
     * This function returns the number of invitations the sender has ordered in the last time.
     * @param modelUri The uri of the ontology
     * @param sender The address of the sender
     * @param time The time interval (minutes)
     * @return The number of invitations sent in the last $time minutes
     */
    public static function invitationCount($modelUri, $sender, $time)
    {
        $model = self::getModel($modelUri);

        $query = new Erfurt_Sparql_SimpleQuery();
        $query->setProloguePart('SELECT ?time')
            ->setWherePart(
                'WHERE {' .
                '?resourceUri <http://rdfs.org/sioc/ns#email> "' . $sender . '" .' .
                '?resourceUri <http://dublincore.org/2010/10/11/dcterms.rdf#created> ?time }'
            );
        $queryData = $model->sparqlQuery($query);

        $allowedTime = time() - $time * 60;
        $result = 0;

        foreach ($queryData as $data) {
            if (intval($data['time']) > $allowedTime) {
                $result++;
            }
        }

        return $result;
    }

    /**
     * This function inserts a new invitation to the invitastion-database
     * @param code The unique code of the invitation
     * @param sender The mail-address of the inviting person
     * @param receiver The mail-address of the invited person
     * @param instanceUri The uri to the instance, the invited person should read
     */
    public static function addInvitation($modelUri, $code, $sender, $receiver, $instanceModelUri, $instanceUri)
    {
        $store = Erfurt_App::getInstance()->getStore();

        $resourceUri = 'invitation'.$code;
        $model = self::getModel($modelUri);

        $store->addStatement(
            $modelUri,
            $resourceUri,
            'http://rdfs.org/sioc/ns#email',
            array('value' => $sender,'type' => 'literal')
        );

        $store->addStatement(
            $modelUri,
            $resourceUri,
            'http://dublincore.org/2010/10/11/dcterms.rdf#created',
            array('value' => time(),'type' => 'literal')
        );

        $store->addStatement(
            $modelUri,
            $resourceUri,
            'http://rdfs.org/sioc/ns#addressed_to',
            array('value' => $receiver,'type' => 'literal')
        );

        $store->addStatement(
            $modelUri,
            $resourceUri,
            'http://rdfs.org/sioc/ns#Container',
            array('value' => $instanceModelUri,'type' => 'uri')
        );

        $store->addStatement(
            $modelUri,
            $resourceUri,
            'http://rdfs.org/sioc/ns#topic',
            array('value' => $instanceUri,'type' => 'uri')
        );

        $store->addStatement(
            $modelUri,
            $resourceUri,
            'http://www.w3.org/2000/01/rdf-schema#label',
            array('value' => $code, 'type' => 'literal')
        );

        $store->addStatement(
            $modelUri,
            $resourceUri,
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
            array('value' => 'Invitation', 'type' => 'uri')
        );
    }

    /**
     * This function generates an unique code to identify an invitation
     * @return An unique code for an invitation
     */
    public static function generateCode($modelUri)
    {

        $model = self::getModel($modelUri);

        //Repeat while code already used
        do {
            //Chars for Code
            $codeChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
            $codeLength = 20;

            //Generate new code
            $code = '';
            for ($i=0; $i<$codeLength; $i++) {
                $charNumber = rand(0, strlen($codeChars) - 1);
                $code .= $codeChars[$charNumber];
            }

            //Proove whether code is unique
            $query = new Erfurt_Sparql_SimpleQuery();
            $query->setProloguePart('SELECT ?resourceUri')
                ->setWherePart(
                    'WHERE {' .
                    '?resourceUri <http://www.w3.org/2000/01/rdf-schema#label> "' . $code .
                    '"}'
                );
            $queryData = $model->sparqlQuery($query);

        } while (count($queryData) > 0);

        return $code;
    }
}
