<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/icon-mapping.php';
require_once __DIR__ . '/../libs/functions.php';

    class Backend extends IPSModule
    {
        use Icons;
        use Functions;

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
            $this->RegisterPropertyString('topic', 'nspanel');
            $this->RegisterPropertyString('fullTopic', '%prefix%/%topic%');
            $this->RegisterPropertyString('listCards', '{}');
            $this->RegisterPropertyBoolean('screensaver', true);
            $this->RegisterPropertyInteger('screensaverTimeout', 20);
            $this->RegisterPropertyBoolean('weatherActive', false);
            $this->RegisterPropertyInteger('standbyBrightness', 10);
            $this->RegisterAttributeInteger('activeCardEntitie', 0);
            $this->RegisterAttributeBoolean('activeScreensaver', true);
            $this->RegisterAttributeString('weatherUpdate', 'weatherUpdate~X~27.1C~Fr.~Y~29.3C~Sa.~Z~25.4C~So.~A~24.1C~Mo.~B~23.8C~~');

            $this->RegisterAttributeString('activePopup', '');

            $this->RegisterTimer('NSPanelUpdateDate', ((time() % 60) ?: 60) * 1000, 'NSP_setDateTime($_IPS[\'TARGET\']);');
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

            //CardEntities und CardGrid haben die selben Values, deswegen die selbe Liste
            foreach ($listCards as $key => $card) {
                switch ($card['cardType']) {
                    case 'cardGrid':
                        $cardType = 'cardEntities';
                        break;
                    default:
                        $cardType = $card['cardType'];
                        break;
                }
                foreach ($card[$cardType . 'Values'] as $cardKey => $cardValue) {
                }
            }

            $screensaverTimeout = $this->ReadPropertyInteger('screensaverTimeout');
            $this->CustomSend('timeout~' . strval($screensaverTimeout));
        }

        public function GetConfigurationForm()
        {
            //global $icons;
            $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

            $iconOptions = [];

            foreach ($this->icons as $key => $value) {
                array_push($iconOptions, ['caption' => $key, 'value' => $key]);
            }

            //CardEntities Icons
            $form['elements'][3]['items'][0]['columns'][3]['edit']['columns'][2]['edit']['options'] = $iconOptions;

            return json_encode($form);
        }

        public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
        {
            $this->SendDebug('MessageSink :: Triggerd by', $SenderID, 0);
            switch ($Message) {
                case VM_UPDATE:
                     if ($Data[1]) {
                         if (!$this->ReadAttributeBoolean('activeScreensaver')) {
                             if ($this->ReadAttributeString('activePopup') != '') {
                                 $this->SendDebug('MessageSink :: Aktualisiere aktives PopUp', $this->ReadAttributeString('activePopup'), 0);
                                 $activePopup = json_decode($this->ReadAttributeString('activePopup'), true);
                                 $this->entityUpdateDetail($activePopup['popupTyp'], $activePopup['internalNameEntity']);
                                 return;
                             }
                             $this->entityUpd($this->ReadAttributeInteger('activeCardEntitie'));
                             $this->SendDebug('MessageSink :: Aktualisiere Karte', $this->ReadAttributeInteger('activeCardEntitie'), 0);
                         }
                     }
                    break;

                default:
                    # code...
                    break;
            }
        }

        public function ReceiveData($JSONString)
        {
            if (!empty($this->ReadPropertyString('topic'))) {
                $data = json_decode($JSONString, true);

                //Für MQTT Fix in IPS Version 6.3
                if (IPS_GetKernelDate() > 1670886000) {
                    $data['Payload'] = utf8_decode($data['Payload']);
                }

                $Payload = json_decode($data['Payload'], true);
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
                            case 'event,startup,44,eu':
                            case 'event,startup,45,eu':
                            case 'event,startup,46,eu':
                            case 'event,startup,47,eu':
                            case 'event,startup,48,eu':
                            case 'event,startup,49,eu':
                            case 'event,startup,50,eu':
                            case 'event,startup,51,eu':
                                $this->SendDebug('Initialisierung :: Display', $data['Topic'], 0);
                                $this->CustomSend('time~' . date('H:i'));
                                $this->CustomSend('date~' . date('d.m.Y'));
                                $standbyBrightness = $this->ReadPropertyInteger('standbyBrightness');
                                $this->CustomSend('dimmode~' . $standbyBrightness . '~100~6371');
                                if ($this->ReadPropertyBoolean('screensaver')) {
                                    $screensaverTimeout = $this->ReadPropertyInteger('screensaverTimeout');
                                    $this->CustomSend('timeout~' . strval($screensaverTimeout));
                                    $this->CustomSend('pageType~screensaver');
                                }
                                $this->CustomSend('pageType~screensaver');
                                break;
                            case 'event,buttonPress2,navPrev,button':
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
                                break;
                            case 'event,buttonPress2,navNext,button':
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
                                break;

                            case preg_match('(event,buttonPress2,screensaver,bExit,*)', $Payload['CustomRecv']) ? true : false: //Exit Screensaver
                                $this->entityUpd($activeCard);
                                $this->WriteAttributeBoolean('activeScreensaver', false);
                                break;
                            case 'event,sleepReached,cardEntities':
                            case 'event,sleepReached,cardGrid':
                            case 'event,sleepReached,cardMedia':
                                if ($this->ReadPropertyBoolean('screensaver')) {
                                    $this->CustomSend('pageType~screensaver');
                                    $this->WriteAttributeBoolean('activeScreensaver', true);
                                    if ($this->ReadPropertyBoolean('weatherActive')) {
                                        $this->CustomSend($this->ReadAttributeString('weatherUpdate'));
                                    }
                                }
                                break;
                            case preg_match('(event,pageOpenDetail,popupLight,)', $Payload['CustomRecv']) ? true : false:
                                $Light = explode(',', $Payload['CustomRecv'])[3];
                                $this->SendDebug('Event :: popupLight', $Light, 0);
                                $this->UnregisterAlleMessages();
                                $this->entityUpdateDetail('popupLight', $Light);
                                break;
                            case 'event,buttonPress2,popupLight,bExit':
                                $this->UnregisterAlleMessages();
                                $this->WriteAttributeString('activePopup', '');
                                $this->SetBuffer('entityUpd', '');
                                $this->entityUpd($activeCard);
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,button)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,34187,button
                                $VariableID = explode(',', $Payload['CustomRecv'])[2];
                                $this->SendDebug('Event :: buttonPress2 button', $VariableID, 0);
                                $variableState = GetValue($VariableID);
                                RequestAction($VariableID, !$variableState);
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,OnOff,)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,11555,OnOff,1
                                $Light = explode(',', $Payload['CustomRecv'])[2];
                                $State = explode(',', $Payload['CustomRecv'])[4];
                                $this->SendDebug('Event :: buttonPress OnOff', $Light, 0);
                                RequestAction($Light, boolval($State));
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,brightnessSlider,)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,28790,brightnessSlider,57
                                $Light = explode(',', $Payload['CustomRecv'])[2];
                                $State = explode(',', $Payload['CustomRecv'])[4];
                                $this->SendDebug('Event :: buttonPress brightnessSlider', $Light, 0);

                                $variableID = $this->getVariablefromCard($Light, 'sliderBrightnessPos');
                                $value = $this->dimDevice($variableID, $State);
                                RequestAction($variableID, $this->dimDevice($variableID, $value));
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,colorTempSlider,)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,35933,colorTempSlider,33

                                $Light = explode(',', $Payload['CustomRecv'])[2];
                                $State = explode(',', $Payload['CustomRecv'])[4];
                                $this->SendDebug('Event :: buttonPress colorTempSlider', $Light, 0);

                                $variableID = $this->getVariablefromCard($Light, 'sliderColorTempPos');
                                $VariableProfile = $this->getVariableProfile($variableID);
                                $MinValue = $VariableProfile['MinValue'];
                                $MaxValue = $VariableProfile['MaxValue'];

                                $sliderColorTempPos = $this->Scale($State, 0, 100, $MinValue, $MaxValue);
                                $this->SendDebug('Event :: buttonPress colorTempSlider Scaled Value', $sliderColorTempPos, 0);
                                RequestAction($variableID, $sliderColorTempPos);
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,colorWheel,)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,55653,colorWheel,25|90|160
                                $Light = explode(',', $Payload['CustomRecv'])[2];
                                $State = explode(',', $Payload['CustomRecv'])[4];
                                $State = explode('|', $State);
                                $variableID = $this->getVariablefromCard($Light, 'colorMode');
                                $this->SendDebug('Event :: buttonPress colorWheel ColorVariable', $variableID, 0);
                                $rgb = $this->pos_to_color($State[0], $State[1], $State[2]);
                                $hex = sprintf('%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
                                RequestAction($variableID, hexdec($hex));
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,media-pause)', $Payload['CustomRecv']) ? true : false: //"event,buttonPress2,11068,media-pause
                                $Media = explode(',', $Payload['CustomRecv'])[2];
                                $this->SendDebug('Event :: buttonPress media-pause', $Media, 0);

                                $variableID = $this->getVariablefromCard($Media, 'playPause');
                                $VariableProfile = $this->getVariableProfile($variableID);

                                $value = GetValue($variableID);

                                if ($this->mediaProfileMapping($VariableProfile, 'pause') == $value) {
                                    $value = $this->mediaProfileMapping($VariableProfile, 'play');
                                } else {
                                    $value = $this->mediaProfileMapping($VariableProfile, 'pause');
                                }
                                $this->SendDebug('Event :: buttonPress media-pause Variable / Value', $variableID . ' / ' . $value, 0);
                                RequestAction($variableID, $value);
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,media-next)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,11068,media-next
                                $Media = explode(',', $Payload['CustomRecv'])[2];
                                $this->SendDebug('Event :: buttonPress media-next', $Media, 0);

                                $variableID = $this->getVariablefromCard($Media, 'next');
                                $VariableProfile = $this->getVariableProfile($variableID);
                                $value = $this->mediaProfileMapping($VariableProfile, 'next');
                                $this->SendDebug('Event :: buttonPress media-next Variable / Value', $variableID . ' / ' . $value, 0);
                                RequestAction($variableID, $value);
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,media-back)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,11068,media-back
                                $Media = explode(',', $Payload['CustomRecv'])[2];
                                $this->SendDebug('Event :: buttonPress media-back', $Media, 0);

                                $variableID = $this->getVariablefromCard($Media, 'previous');
                                $VariableProfile = $this->getVariableProfile($variableID);
                                $value = $this->mediaProfileMapping($VariableProfile, 'previous');
                                $this->SendDebug('Event :: buttonPress media-back Variable / Value', $variableID . ' / ' . $value, 0);
                                RequestAction($variableID, $value);
                                break;
                            case preg_match('(event,buttonPress2,[0-9]+,volumeSlider,)', $Payload['CustomRecv']) ? true : false: //event,buttonPress2,11068,volumeSlider,9
                                $Media = explode(',', $Payload['CustomRecv'])[2];
                                $State = explode(',', $Payload['CustomRecv'])[4];
                                $this->SendDebug('Event :: buttonPress volumeSlider', $Media, 0);

                                $variableID = $this->getVariablefromCard($Media, 'volume');
                                $VariableProfile = $this->getVariableProfile($variableID);
                                $value = $this->dimDevice($variableID, $State);

                                $this->SendDebug('Event :: buttonPress volumeSlider Variable / Value', $variableID . ' / ' . $value, 0);
                                RequestAction($variableID, $value);

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

        public function entityUpdateDetail(string $popupTyp, string $internalNameEntity)
        {
            $activeCard = $this->ReadAttributeInteger('activeCardEntitie');
            $listCards = json_decode($this->ReadPropertyString('listCards'), true);
            $card = $listCards[$activeCard];

            //Unregister alle Messages, damit nicht ständig die Karten neu gesendet werden.
            $this->UnregisterAlleMessages();
            $RegisterMessages = [];

            //CardEntities und CardGrid haben die selben Values, deswegen die selbe Liste
            foreach ($listCards as $key => $card) {
                switch ($card['cardType']) {
                    case 'cardGrid':
                        $cardType = 'cardEntities';
                        break;
                    default:
                        $cardType = $card['cardType'];

                        break;
                }
            }

            foreach ($card[$cardType . 'Values'] as $cardKey => $cardValue) {
                if ($cardValue['internalNameEntity'] == $internalNameEntity) {

                    /** ToDos
                     *  Icon Color an Status der Lampe anpassen
                     */
                    $entityUpdateDetail = 'entityUpdateDetail~';
                    $entityUpdateDetail .= $internalNameEntity . '~';
                    $entityUpdateDetail .= $this->get_icon($cardValue['icon']) . '~';
                    $entityUpdateDetail .= '58338~'; //IconColor
                    $entityUpdateDetail .= intval(GetValue($internalNameEntity)) . '~'; //ButtonState

                    //Sammle IDs für RegisterMessage (internalNameEntity)
                    array_push($RegisterMessages, $cardValue['internalNameEntity']);

                    $sliderBrightnessPos = '';

                    // Wenn Variable nicht vorhanden deaktiviere diese, sonst rechne um
                    if ($cardValue['sliderBrightnessPos'] > 0) {
                        $VariableProfile = $this->getVariableProfile($cardValue['sliderBrightnessPos']);
                        $MinValue = $VariableProfile['MinValue'];
                        $MaxValue = $VariableProfile['MaxValue'];
                        $sliderBrightnessPos = $this->Scale(GetValue($cardValue['sliderBrightnessPos']), $MinValue, $MaxValue, 0, 100);

                        //Sammle IDs für RegisterMessage (sliderBrightnessPos)
                        array_push($RegisterMessages, $cardValue['sliderBrightnessPos']);
                    } else {
                        $sliderBrightnessPos = 'disable';
                    }
                    $entityUpdateDetail .= $sliderBrightnessPos . '~'; //sliderBrightnessPos

                    ### Color Temperature Start ###
                    $sliderColorTempPos = '';
                    // Wenn Variable nicht vorhanden deaktiviere diese, sonst rechne um
                    if ($cardValue['sliderColorTempPos'] > 0) {
                        $VariableProfile = $this->getVariableProfile($cardValue['sliderColorTempPos']);
                        $MinValue = $VariableProfile['MinValue'];
                        $MaxValue = $VariableProfile['MaxValue'];
                        $sliderColorTempPos = $this->Scale(GetValue($cardValue['sliderColorTempPos']), $MinValue, $MaxValue, 0, 100);

                        //Sammle IDs für RegisterMessage (sliderBrightnessPos)
                        array_push($RegisterMessages, $cardValue['sliderColorTempPos']);
                    } else {
                        $sliderColorTempPos = 'disable';
                    }
                    $entityUpdateDetail .= $sliderColorTempPos . '~'; //sliderColorTempPos

                    ### Color Temperature Ende ###

                    // Wenn Variable nicht vorhanden deaktiviere diese, sonst rechne um
                    if ($cardValue['colorMode'] > 0) {
                        array_push($RegisterMessages, $cardValue['colorMode']);
                    } else {
                        $cardValue['colorMode'] = 'disable';
                    }
                    $entityUpdateDetail .= $cardValue['colorMode'] . '~'; //colorMode
                    $entityUpdateDetail .= $this->Translate('Color') . '~'; //colorTranslation
                    $entityUpdateDetail .= $this->Translate('Color Temperature') . '~'; //brightness
                    $entityUpdateDetail .= $this->Translate('Brightness') . '~'; //brightness

                    //Registriere Message für Variablenänderungen auf der aktuellen Karte
                    $this->RegisterAllMessages($RegisterMessages);

                    $this->CustomSend($entityUpdateDetail);

                    $activePopup = [];
                    $activePopup['popupTyp'] = $popupTyp;
                    $activePopup['internalNameEntity'] = $internalNameEntity;

                    $this->WriteAttributeString('activePopup', json_encode($activePopup));
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
                case 'cardGrid':
                    foreach ($card['cardEntities' . 'Values'] as $cardKey => $cardValue) {
                        $entityUpd .= $cardValue['type'] . '~';
                        $entityUpd .= $cardValue['internalNameEntity'] . '~';
                        $entityUpd .= $this->get_icon($cardValue['icon']) . '~';
                        $entityUpd .= '17299~';
                        $entityUpd .= $cardValue['DisplayNameEntity'];
                        switch ($cardValue['type']) {
                            case 'light':
                            case 'switch':
                                $Value = intval(GetValue($cardValue['internalNameEntity']));
                                break;
                            case 'text':
                                $variableProfileSuffix = ($this->getVariableProfile($cardValue['internalNameEntity']) != false ? ' ' . $this->getVariableProfile($cardValue['internalNameEntity'])['Suffix'] : '');
                                $variableProfilePrefix = ($this->getVariableProfile($cardValue['internalNameEntity']) != false ? ' ' . $this->getVariableProfile($cardValue['internalNameEntity'])['Prefix'] : '');
                                $Value = strval($variableProfilePrefix . GetValue($cardValue['internalNameEntity'])) . $variableProfileSuffix;
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
                            $Status = GetValue($cardValue['playPause']);

                            $StatusProfile = $this->getVariableProfile($cardValue['playPause']);

                            $playpauseicon = $this->mediaProfileMapping($StatusProfile, $Status);
                            $entityUpd .= $cardValue['internalNameEntity'] . '~';
                            $entityUpd .= GetValue($cardValue['title']) . '~';
                            $entityUpd .= '~'; //Title Color
                            $entityUpd .= GetValue($cardValue['author']) . '~';
                            $entityUpd .= '~'; //Author Color
                            $entityUpd .= GetValue($cardValue['volume']) . '~';
                            $entityUpd .= $this->get_icon($playpauseicon) . '~';
                            $entityUpd .= 'disable~'; //onOffBUtton
                            $entityUpd .= 'disable~'; //Shuffle Icon

                            array_push($RegisterMessages, $cardValue['playPause']);
                            array_push($RegisterMessages, $cardValue['title']);
                            array_push($RegisterMessages, $cardValue['author']);
                            array_push($RegisterMessages, $cardValue['volume']);
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
            $this->RegisterAllMessages($RegisterMessages);

            //Update an Display nur senden, wenn sich wirklich was verändert hat, um das flackern zu minimieren.
            if (($this->GetBuffer('entityUpd') != $entityUpd) || ($this->ReadAttributeBoolean('activeScreensaver'))) {
                $this->CustomSend('pageType~' . $card['cardType']);
                $this->CustomSend($entityUpd);
            }
            $this->SetBuffer('entityUpd', $entityUpd);
            $this->WriteAttributeInteger('activeCardEntitie', $page);
        }

        public function CustomSend(string $payload)
        {
            $this->SendDebug('CustomSend :: Payload', $payload, 0);
            $this->MQTTCommand('CustomSend', $payload);
            //Für MQTT Fix in IPS Version 6.3
            if (IPS_GetKernelDate() > 1670886000) {
                $payload = utf8_encode($payload);
            }
            $this->MQTTCommand('cmnd/' . $this->ReadPropertyString('topic') . '/CustomSend', $payload);
        }

        public function showCardEntitiesTypeValues($Value)
        {
            switch ($Value) {
                case 'light':
                    $this->UpdateFormField('sliderBrightnessPos', 'visible', true);
                    $this->UpdateFormField('sliderColorTempPos', 'visible', true);
                    $this->UpdateFormField('colorMode', 'visible', true);
                    $this->UpdateFormField('content', 'visible', false);
                    break;
                case 'switch':
                    $this->UpdateFormField('sliderBrightnessPos', 'visible', false);
                    $this->UpdateFormField('sliderColorTempPos', 'visible', false);
                    $this->UpdateFormField('colorMode', 'visible', false);
                    $this->UpdateFormField('content', 'visible', false);
                    break;
                case 'text':
                    $this->UpdateFormField('sliderBrightnessPos', 'visible', false);
                    $this->UpdateFormField('sliderColorTempPos', 'visible', false);
                    $this->UpdateFormField('colorMode', 'visible', false);
                    $this->UpdateFormField('content', 'visible', true);
                    break;
                case 'shutter':
                    break;
                }
        }

        public function showCardValueList($Value)
        {
            switch ($Value) {
                case 'cardEntities':
                case 'cardGrid':
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

        public function setDateTime()
        {
            $this->CustomSend('time~' . date('H:i'));
            $this->CustomSend('date~' . date('d.m.Y'));
        }

        public function weatherUpdate($values)
        {
            //weatherUpdate~X~27.1C~Fr.~Y~29.3C~Sa.~Z~25.4C~So.~A~24.1C~Mo.~B~23.8C~~
            $command = 'weatherUpdate~';
            $command .= $this->get_icon($values[0]) . '~'; //Tag 1 Icon
            $command .= $values[1] . '~'; //Tag 1 Temperatur
            $command .= $values[2] . '~'; //Tag 2 Name
            $command .= $this->get_icon($values[3]) . '~'; //Tag 2 Icon
            $command .= $values[4] . '~'; //Tag 2 Temperatur
            $command .= $values[5] . '~'; //Tag 3 Name
            $command .= $this->get_icon($values[6]) . '~'; //Tag 3 Icon
            $command .= $values[7] . '~'; //Tag 3 Temperatur
            $command .= $values[8] . '~'; //Tag 4 Name
            $command .= $this->get_icon($values[9]) . '~'; //Tag 4 Icon
            $command .= $values[10] . '~'; //Tag 4 Temperatur
            $command .= $values[11] . '~'; //Tag 5 Name
            $command .= $this->get_icon($values[12]) . '~'; //Tag 5 Icon
            $command .= $values[13] . '~~'; //Tag 5 Temperatur
            $this->WriteAttributeString('weatherUpdate', $command);
            $this->CustomSend($command);
        }

        private function getVariablefromCard($internalNameEntity, string $searchVariable)
        {
            $activeCard = $this->ReadAttributeInteger('activeCardEntitie');
            $listCards = json_decode($this->ReadPropertyString('listCards'), true);
            $card = $listCards[$activeCard];

            //CardEntities und CardGrid haben die selben Values, deswegen die selbe Liste
            foreach ($listCards as $key => $card) {
                switch ($card['cardType']) {
                                case 'cardGrid':
                                    $cardType = 'cardEntities';
                                    break;
                                default:
                                    $cardType = $card['cardType'];
                                    break;
                            }
                foreach ($card[$cardType . 'Values'] as $cardKey => $cardValue) {
                }
            }

            foreach ($card[$cardType . 'Values'] as $cardKey => $cardValue) {
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

        private function RegisterAllMessages($Variables)
        {
            for ($i = 0; $i < count($Variables); $i++) {
                $this->RegisterMessage($Variables[$i], VM_UPDATE);
            }
        }

        private function getVariableProfile(int $VariableID)
        {
            $this->SendDebug('Variable', $VariableID, 0);
            $variable = IPS_GetVariable($VariableID);
            if ($variable['VariableCustomProfile'] != '') {
                $profileName = $variable['VariableCustomProfile'];
            } else {
                $profileName = $variable['VariableProfile'];
            }

            if ($profileName == '') {
                return false;
            }

            return IPS_GetVariableProfile($profileName);
        }

        private function mediaProfileMapping($VariableProfile, $Value)
        {
            switch ($VariableProfile['ProfileName']) {
                case 'SONOS.Status':
                    switch ($Value) {
                        case 2:
                            return 'play';
                        case 1:
                            return 'pause';
                        case 'play':
                            return $VariableProfile['Associations'][1]['Value'];
                        case 'pause':
                            return $VariableProfile['Associations'][2]['Value'];
                            break;
                        case 'previous':
                            return $VariableProfile['Associations'][0]['Value'];
                        case 'next':
                            return $VariableProfile['Associations'][4]['Value'];
                            break;
                        default:
                            # code...
                            break;
                    }
                    break;

                default:
                    # code...
                    break;
            }
        }
    }