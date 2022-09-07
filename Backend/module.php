<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/icon-mapping.php';

    class Backend extends IPSModule
    {
        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
            $this->RegisterPropertyString('topic', 'nspanel');
            $this->RegisterPropertyString('fullTopic', '%prefix%/%topic%');
            $this->RegisterPropertyString('listCards', '{}');

            $this->RegisterAttributeInteger('activeCardEntitie', 0);
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();
            $topic = $this->FilterFullTopicReceiveData();
            $this->SetReceiveDataFilter('.*' . $topic . '.*');

            $listCards = json_decode($this->ReadPropertyString('listCards'), true);

            foreach ($listCards as $key => $card) {
                foreach ($card[$card['cardType'] . 'Values'] as $cardKey => $cardValue) {
                }
            }
        }

        public function GetConfigurationForm()
        {
            global $icons;
            $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

            $iconOptions = [];

            foreach ($icons as $key => $value) {
                array_push($iconOptions, ['caption' => $key, 'value' => $key]);
            }

            //CardEntities Icons
            $form['elements'][2]['items'][0]['columns'][3]['edit']['columns'][2]['edit']['options'] = $iconOptions;

            return json_encode($form);
        }

        public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
        {
            $this->entityUpd($this->ReadAttributeInteger('activeCardEntitie'));
            $this->SendDebug('MessageSink :: Aktualisiere Karte', $page, 0);
        }

        public function ReceiveData($JSONString)
        {
            if (!empty($this->ReadPropertyString('topic'))) {
                //$this->SendDebug('ReceiveData :: JSON', $JSONString, 0);
                $data = json_decode($JSONString, true);
                $Payload = json_decode($data['Payload'], true);
                //$this->SendDebug('ReceiveData :: Topic', $data['Topic'], 0);
                $activeCard = $this->ReadAttributeInteger('activeCardEntitie');
                switch ($data['Topic']) {
                case 'tele/' . $this->ReadPropertyString('topic') . '/RESULT':
                    $Payload = json_decode($data['Payload'], true);
                    if (array_key_exists('CustomRecv', $Payload)) {
                        $this->SendDebug('ReceiveData :: Payload CustomRecv', $Payload['CustomRecv'], 0);
                        switch ($Payload['CustomRecv']) {
                            case 'event,startup,39,eu': //Display wartet auf Initialisierung die Nummer steht für die Firmware Version
                            case 'event,startup,40,eu':
                            case 'event,startup,41,eu':
                            case 'event,startup,42,eu':
                            case 'event,startup,43,eu':
                                $this->SendDebug('Initialisierung :: Display', $data['Topic'], 0);
                                $this->CustomSend('time~' . date('H:i'));
                                $this->CustomSend('date~' . date('d.m.Y'));
                                $this->CustomSend('timeout~20');
                                $this->CustomSend('dimmode~10~100~6371');
                                $this->CustomSend('pageType~screensaver');
                                break;
                            case 'event,buttonPress2,cardEntities,bPrev':
                                $countCards = count(json_decode($this->ReadPropertyString('listCards'), true));
                                $this->SendDebug('Button bPrev :: Anzahl Karten / Aktive Karte', $countCards . '/' . $activeCard, 0);

                                if ($activeCard == 0) { //Letzte Karte aufrufen
                                    $this->SendDebug('Button bPrev :: Letzte Karte countCards', $countCards, 0);
                                    $this->entityUpd($countCards - 1);
                                    return;
                                }
                                if ($countCards > $activeCard) { //Vorherige Karte aufrufen
                                    $this->entityUpd($activeCard - 1);
                                    return;
                                }
                                // No break. Add additional comment above this line if intentional
                            case 'event,buttonPress2,cardEntities,bNext':
                                $countCards = count(json_decode($this->ReadPropertyString('listCards'), true)) - 1;
                                $this->SendDebug('Button bNext :: Anzahl Karten / Aktive Karte', $countCards . '/' . $activeCard, 0);
                                if ($activeCard == $countCards) { //Erste Karte aufrufen
                                    $this->entityUpd(0);
                                    return;
                                }
                                if ($activeCard < $countCards) { //Vorherige Karte aufrufen
                                    $this->entityUpd($activeCard + 1);
                                    return;
                                }
                                // No break. Add additional comment above this line if intentional
                            case 'event,buttonPress2,screensaver,bExit,2': //Exit Screensaver
                                $this->entityUpd($activeCard);
                                break;
                            case 'event,sleepReached,cardEntities':
                                $this->CustomSend('pageType~screensaver');
                                break;
                            case preg_match('(event,pageOpenDetail,popupLight,)', $Payload['CustomRecv']) ? true : false :
                                $Light = explode(',', $Payload['CustomRecv'])[3];
                                $this->SendDebug('Event :: popupLight', $Light, 0);
                                $this->entityUpdateDetail($Light);
                                $this->UnregisterAlleMessages();
                                break;
                            case 'event,buttonPress2,popupLight,bExit':
                                $this->entityUpd($activeCard);
                                //TODO Unregister Variablen aus Popup
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,OnOff,)', $Payload['CustomRecv']) ? true : false : //event,buttonPress2,11555,OnOff,1
                                $Light = explode(',', $Payload['CustomRecv'])[2];
                                $State = explode(',', $Payload['CustomRecv'])[4];
                                $this->SendDebug('Event :: buttonPress OnOff', $Light, 0);
                                RequestAction($Light, boolval($State));
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,brightnessSlider,)', $Payload['CustomRecv']) ? true : false : //event,buttonPress2,28790,brightnessSlider,57
                                $Light = explode(',', $Payload['CustomRecv'])[2];
                                $State = explode(',', $Payload['CustomRecv'])[4];
                                $this->SendDebug('Event :: buttonPress brightnessSlider', $Light, 0);

                                $variableID = $this->getVariablefromCard($Light, 'sliderBrightnessPos');
                                $value = $this->dimDevice($variableID, $State);
                                RequestAction($variableID, $this->dimDevice($variableID, $value));
                                break;
                            default:
                            $this->SendDebug('Case Payload Result Topic :: Payload', $data['Payload'], 0);
                            break;
                        }
                    }
                    // No break. Add additional comment above this line if intentional
                default:
                    break;
                }
            }
        }

        public function entityUpdateDetail(string $internalNameEntity)
        {
            $activeCard = $this->ReadAttributeInteger('activeCardEntitie');
            $listCards = json_decode($this->ReadPropertyString('listCards'), true);
            $card = $listCards[$activeCard];

            foreach ($card[$card['cardType'] . 'Values'] as $cardKey => $cardValue) {
                if ($cardValue['internalNameEntity'] == $internalNameEntity) {

            /** ToDos
             *  Register Variablen für Popup
             *  Icon Color an Status der Lampe anpassen
             */
                    $entityUpdateDetail = 'entityUpdateDetail~';
                    $entityUpdateDetail .= $internalNameEntity . '~';
                    $entityUpdateDetail .= get_icon($cardValue['icon']) . '~';
                    $entityUpdateDetail .= '58338~'; //IconColor
            $entityUpdateDetail .= intval(GetValue($internalNameEntity)) . '~'; //ButtonState

            $sliderBrightnessPos = '';
                    if ($cardValue['sliderBrightnessPos'] > 0) { // Wenn Variable nicht vorhanden deaktiviere diese, sonst rechne um
                $sliderBrightnessPos = GetValueFormatted($cardValue['sliderBrightnessPos']); //sliderBrightnessPos
                    } else {
                        $sliderBrightnessPos = 'disable';
                    }
                    $entityUpdateDetail .= $sliderBrightnessPos . '~'; //sliderBrightnessPos

                    $sliderColorTempPos = '';
                    if ($cardValue['sliderColorTempPos'] > 0) { // Wenn Variable nicht vorhanden deaktiviere diese, sonst rechne um
                        $sliderColorTempPos = 'disable';
                    } else {
                        $sliderColorTempPos = 'disable';
                    }
                    $entityUpdateDetail .= $sliderColorTempPos . '~'; //sliderColorTempPos

                    if (!$cardValue['colorMode']) { // Wenn Variable nicht vorhanden deaktiviere diese, sonst rechne um
                        $cardValue['colorMode'] = 'disable';
                    }
                    $entityUpdateDetail .= $cardValue['colorMode'] . '~'; //colorMode
            $entityUpdateDetail .= $this->Translate('Color') . '~'; //colorTranslation
            $entityUpdateDetail .= $this->Translate('Color Temperature') . '~'; //brightness
            $entityUpdateDetail .= $this->Translate('Brightness') . '~'; //brightness
            $this->CustomSend($entityUpdateDetail);
                }
            }
        }
        public function entityUpd(int $page)
        {
            //Unregister alle Messages, damit nicht ständig die Karten neu gesendet werden.
            $this->UnregisterAlleMessages();
            $RegisterMessages = [];

            $this->SendDebug('entityUpd :: Karte', $page, 0);
            $listCards = json_decode($this->ReadPropertyString('listCards'), true);

            $card = $listCards[$page];

            $entityUpd = 'entityUpd~';
            $entityUpd .= $card['heading'] . '~';
            $entityUpd .= $card['navigation'] . '~';

            switch ($card['cardType']) {
                case 'cardEntities':
                    foreach ($card[$card['cardType'] . 'Values'] as $cardKey => $cardValue) {
                        $entityUpd .= $cardValue['type'] . '~';
                        $entityUpd .= $cardValue['internalNameEntity'] . '~';
                        $entityUpd .= get_icon($cardValue['icon']) . '~';
                        $entityUpd .= '17299~';
                        $entityUpd .= $cardValue['DisplayNameEntity'];
                        switch ($cardValue['type']) {
                            case 'light':
                            case 'switch':
                                $Value = intval(GetValue($cardValue['internalNameEntity']));
                                break;
                            default:
                                # code...
                                break;
                        }
                        //Sammele IDs für RegisterMessage
                        array_push($RegisterMessages, $cardValue['internalNameEntity']);
                        $entityUpd .= '~' . $Value . '~';
                    }
                    break;
                    case 'cardMedia':
                        foreach ($card[$card['cardType'] . 'Values'] as $cardKey => $cardValue) {
                            /**
                             * $playpauseicon = 'play'; //TODO GetValue Status Play / Pause
                             * $entityUpd .= $cardValue['internalNameEntity'].'~';
                             * $entityUpd .= get_icon($cardValue['icon']).'~';
                             * $entityUpd .= GetValue($cardValue['title']).'~';
                             * $entityUpd .= GetValue($cardValue['author']).'~';
                             * $entityUpd .= GetValue($cardValue['volume']).'~';
                             * $entityUpd .= get_icon($playpauseicon).'~';
                             * $entityUpd .= 'delete~'; //currentSpeaker wird erstmal nicht unterstützt
                             * $entityUpd .= 'delete~'; //speakerList wird erstmal nicht unterstützt
                             * $entityUpd .= 'enable~'; //speakerList wird erstmal nicht unterstützt
                             */
                        }
                        break;
                default:
                    # code...
                    break;
            }

            //Registriere Message für Variablenänderungen auf der aktuellen Karte
            for ($i = 0; $i < count($RegisterMessages); $i++) {
                $this->RegisterMessage($RegisterMessages[$i], VM_UPDATE);
            }

            $this->CustomSend('pageType~cardEntities');
            $this->CustomSend($entityUpd);
            $this->WriteAttributeInteger('activeCardEntitie', $page);
        }

        public function CustomSend(string $payload)
        {
            $this->SendDebug('CustomSend :: Payload', $payload, 0);
            $this->MQTTCommand('CustomSend', $payload);
            $this->MQTTCommand('cmnd/' . $this->ReadPropertyString('topic') . '/CustomSend', $payload);
        }

        public function showCardValueList($Value)
        {
            switch ($Value) {
                case 'cardEntities':
                    $this->UpdateFormField('cardEntitiesValues', 'visible', true);
                    $this->UpdateFormField('cardEntitiesValues', 'enabled', true);
                    $this->UpdateFormField('cardThermoValues', 'visible', false);
                    $this->UpdateFormField('cardThermoValues', 'enabled', false);
                    $this->UpdateFormField('cardMediaValues', 'visible', false);
                    $this->UpdateFormField('cardMediaValues', 'enabled', false);
                    break;
                case 'cardThermo':
                    $this->UpdateFormField('cardEntitiesValues', 'visible', false);
                    $this->UpdateFormField('cardEntitiesValues', 'enabled', false);
                    $this->UpdateFormField('cardThermoValues', 'visible', true);
                    $this->UpdateFormField('cardThermoValues', 'enabled', true);
                    $this->UpdateFormField('cardMediaValues', 'visible', false);
                    $this->UpdateFormField('cardMediaValues', 'enabled', false);
                    break;
                case 'cardMedia':
                    $this->UpdateFormField('cardEntitiesValues', 'visible', false);
                    $this->UpdateFormField('cardEntitiesValues', 'enabled', false);
                    $this->UpdateFormField('cardThermoValues', 'visible', false);
                    $this->UpdateFormField('cardThermoValues', 'enabled', false);
                    $this->UpdateFormField('cardMediaValues', 'visible', true);
                    $this->UpdateFormField('cardMediaValues', 'enabled', true);
                    break;
                case 'cardAlarm':
                    $this->UpdateFormField('cardEntitiesValues', 'visible', false);
                    $this->UpdateFormField('cardEntitiesValues', 'enabled', false);
                    $this->UpdateFormField('cardThermoValues', 'visible', false);
                    $this->UpdateFormField('cardThermoValues', 'enabled', false);
                    $this->UpdateFormField('cardMediaValues', 'visible', false);
                    $this->UpdateFormField('cardMediaValues', 'enabled', false);
                    break;
                    //TODO cardAlarm
                case 'cardQR':
                    $this->UpdateFormField('cardEntitiesValues', 'visible', false);
                    $this->UpdateFormField('cardEntitiesValues', 'enabled', false);
                    $this->UpdateFormField('cardThermoValues', 'visible', false);
                    $this->UpdateFormField('cardThermoValues', 'enabled', false);
                    $this->UpdateFormField('cardMediaValues', 'visible', false);
                    $this->UpdateFormField('cardMediaValues', 'enabled', false);
                    //TODO cardQR
                    break;
                default:
                    echo $Value;
                    break;
            }
        }

        private function getVariablefromCard($internalNameEntity, string $searchVariable)
        {
            $activeCard = $this->ReadAttributeInteger('activeCardEntitie');
            $listCards = json_decode($this->ReadPropertyString('listCards'), true);
            $card = $listCards[$activeCard];

            foreach ($card[$card['cardType'] . 'Values'] as $cardKey => $cardValue) {
                if ($cardValue['internalNameEntity'] == $internalNameEntity) {
                    return $cardValue[$searchVariable];
                }
            }
        }

        private function FilterFullTopicReceiveData()
        {
            $FullTopic = explode('/', $this->ReadPropertyString('fullTopic'));
            $PrefixIndex = array_search('%prefix%', $FullTopic);
            $TopicIndex = array_search('%topic%', $FullTopic);

            $SetCommandArr = $FullTopic;
            $SetCommandArr[$PrefixIndex] = '.*.';
            $SetCommandArr[$TopicIndex] = $this->ReadPropertyString('topic');
            $topic = implode('\/', $SetCommandArr);

            return $topic;
        }

        private function MQTTCommand($command, $Payload, $retain = 0)
        {
            $FullTopic = explode('/', $this->ReadPropertyString('fullTopic'));
            $PrefixIndex = array_search('%prefix%', $FullTopic);
            $TopicIndex = array_search('%topic%', $FullTopic);

            $SetCommandArr = $FullTopic;
            $index = count($SetCommandArr);

            $SetCommandArr[$PrefixIndex] = 'cmnd';
            $SetCommandArr[$TopicIndex] = $this->ReadPropertyString('topic');
            $SetCommandArr[$index] = $command;

            $Topic = implode('/', $SetCommandArr);

            $result = true;

            //MQTT Server
            $Server['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
            $Server['PacketType'] = 3;
            $Server['QualityOfService'] = 0;
            $Server['Retain'] = boolval($retain);
            $Server['Topic'] = $Topic;
            $Server['Payload'] = $Payload;
            $ServerJSON = json_encode($Server, JSON_UNESCAPED_SLASHES);
            $result = @$this->SendDataToParent($ServerJSON);

            if ($result === false) {
                $last_error = error_get_last();
                echo $last_error['message'];
            }
        }

        private function dimDevice($variableID, $value)
        {
            $this->SendDebug('variableID', $variableID, 0);
            $this->SendDebug('value', $value, 0);
            if (!IPS_VariableExists($variableID)) {
                return false;
            }
            $targetVariable = IPS_GetVariable($variableID);

            if ($targetVariable['VariableCustomProfile'] != '') {
                $profileName = $targetVariable['VariableCustomProfile'];
            } else {
                $profileName = $targetVariable['VariableProfile'];
            }

            if (!IPS_VariableProfileExists($profileName)) {
                return false;
            }

            // Revert value for reversed profile
            if (preg_match('/\.Reversed$/', $profileName)) {
                $value = 100 - $value;
            }

            $profile = IPS_GetVariableProfile($profileName);

            if (($profile['MaxValue'] - $profile['MinValue']) <= 0) {
                return false;
            }
            $percentToValue = function ($value) use ($profile)
            {
                return (max(0, min($value, 100)) / 100) * ($profile['MaxValue'] - $profile['MinValue']) + $profile['MinValue'];
            };
            return intval($percentToValue($value));
        }

        private function UnregisterAlleMessages()
        {
            $MessageList = $this->GetMessageList();
            foreach ($MessageList as $key => $Message) {
                $this->UnregisterMessage($key, VM_UPDATE);
            }
        }
    }