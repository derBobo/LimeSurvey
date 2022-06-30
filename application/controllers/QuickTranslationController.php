<?php

class QuickTranslationController extends LSBaseController
{


    /**
     * Here we have to use the correct layout (NOT main.php)
     *
     * @param string $view
     * @return bool
     */
    protected function beforeRender($view)
    {
        $this->layout = 'layout_questioneditor';
        LimeExpressionManager::SetSurveyId($this->aData['surveyid']);
        LimeExpressionManager::StartProcessingPage(false, true);

        return parent::beforeRender($view);
    }

    /**
     *
     *
     * @param $surveyid
     * @return void
     * @throws CHttpException
     */
    public function actionIndex($surveyid)
    {
        /* existing + read (survey) already checked in SurveyCommonAction : existing use model : then if surveyid is not valid : return a 404 */
        /* survey : read OK, not survey:tranlations:read … */
        if (!Permission::model()->hasSurveyPermission($surveyid, 'translations', 'read')) {
            throw new CHttpException(401, "401 Unauthorized");
        }

        $oSurvey = Survey::model()->findByPk($surveyid);

        //------------------------ Initial and get helper classes  --------------
        //KCFINDER SETTINGS
        Yii::app()->session['FileManagerContext'] = "edit:survey:{$oSurvey->sid}";
        Yii::app()->loadHelper('admin.htmleditor');
        initKcfinder();

        App()->getClientScript()->registerScriptFile(App()->getConfig('adminscripts') . 'translation.js');
        Yii::app()->loadHelper("database");
        Yii::app()->loadHelper("admin.htmleditor");
        Yii::app()->loadHelper("surveytranslator");
        //-----------------------------------------------------------------------

        //this GET-Param is the language to which it should be translated (e.g. 'de')
        $languageToTranslate = Yii::app()->getRequest()->getParam('lang');

        if (!empty($languageToTranslate) && !in_array($languageToTranslate, $oSurvey->getAllLanguages())) {
            Yii::app()->setFlashMessage(gT("Invalid language"), 'warning');
            $languageToTranslate = null;
        }
        $action = Yii::app()->getRequest()->getParam('action');
        $actionvalue = Yii::app()->getRequest()->getPost('actionvalue');

        //todo: is this really needed when it is a own action now ...?
        if ($action == "ajaxtranslategoogleapi") {
            echo $this->translateGoogleApi();
            return;
        }

        $baselang = $oSurvey->language;
        $additionalLanguages = $oSurvey->additionalLanguages;

        //set it directly to the first additional language (if any exists), if no language was selected by user
        if (empty($languageToTranslate) && count($additionalLanguages) > 0) {
            $languageToTranslate = $additionalLanguages[0];
        }

        // TODO need to do some validation here on surveyid
        $survey_title = $oSurvey->defaultlanguage->surveyls_title;
        $supportedLanguages = getLanguageData(false, Yii::app()->session['adminlang']);

        $baselangdesc = $supportedLanguages[$baselang]['description'];

        $aData = array(
            "surveyid" => $surveyid,
            "survey_title" => $survey_title,
            "tolang" => $languageToTranslate,
        );
        $quickTranslation = new \LimeSurvey\Models\Services\QuickTranslation($oSurvey);

        if (!empty($languageToTranslate)) {
            // Only save if the administration user has the correct permission
            //todo: this is only necessary on save ...
            if ($actionvalue == "translateSave" && Permission::model()->hasSurveyPermission($surveyid, 'translations', 'update')) {
                $this->translateSave($languageToTranslate, $quickTranslation);
                Yii::app()->setFlashMessage(gT("Saved"), 'success');
            }

            $tolangdesc = $supportedLanguages[$languageToTranslate]['description'];
            // Display tabs with fields to translate, as well as input fields for translated values

            //todo: this view information has to be passed to the view
            $views = $this->displayUntranslatedFields($oSurvey, $languageToTranslate, $baselang, $baselangdesc, $tolangdesc);
        }

        $aData['sidemenu']['state'] = false;
        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title . " (" . gT("ID") . ":" . $surveyid . ")";
        if (Permission::model()->hasSurveyPermission($surveyid, 'translations', 'update')) {
            $aData['surveybar']['savebutton']['form'] = 'frmeditgroup';
            $aData['surveybar']['closebutton']['url'] = 'surveyAdministration/view/surveyid/' . $surveyid; // Close button
            $aData['topBar']['showSaveButton'] = true;
        }

        $aData['display']['menu_bars'] = false;
        $this->aData = $aData;
        $this->render('index', [
            'survey' => $oSurvey,
            'languageToTranslate' => $languageToTranslate,
            'additionalLanguages' => $additionalLanguages
        ]);
    }

    /**
     * @param $survey Survey the survey object
     * @param $tolang
     * @param $baselang
     * @param $quickTranslation \LimeSurvey\Models\Services\QuickTranslation the quicktranslation object
     * @return void
     */
    private function translateSave($tolang, $quickTranslation)
    {
        $tab_names = $quickTranslation->getTabNames();
        $tab_names_full = $tab_names;

        //todo: this part could also into QuickTranslation
        foreach ($tab_names as $type) {
            $amTypeOptions = $quickTranslation->setupTranslateFields($type);
            $type2 = $amTypeOptions["associated"];

            if (!empty($type2)) {
                $tab_names_full[] = $type2;
            }
        }

        foreach ($tab_names_full as $type) {
            $size = (int) Yii::app()->getRequest()->getPost("{$type}_size"); //todo: what is size here?
            // start a loop in order to update each record
            $i = 0;
            while ($i <= $size) {
                // define each variable
                if (Yii::app()->getRequest()->getPost("{$type}_newvalue_{$i}")) {
                    $old = Yii::app()->getRequest()->getPost("{$type}_oldvalue_{$i}");
                    $new = Yii::app()->getRequest()->getPost("{$type}_newvalue_{$i}");

                    // check if the new value is different from old, and then update database
                    if ($new != $old) {
                        $id1 = Yii::app()->getRequest()->getPost("{$type}_id1_{$i}");
                        $id2 = Yii::app()->getRequest()->getPost("{$type}_id2_{$i}");
                        $iScaleID = Yii::app()->getRequest()->getPost("{$type}_scaleid_{$i}");
                        $quickTranslation->updateTranslations($type, $tolang, $new, $id1, $id2, $iScaleID);
                    }
                }
                $i++;
            } // end while
        } // end foreach
    }

    /**
     * @param $quickTranslation \LimeSurvey\Models\Services\QuickTranslation the quick translation object
     * @param $tolang string language to translate to
     * @param $baselang  string the base language
     * @param $baselangdesc string the base language description
     * @param $tolangdesc string the language to translate description
     * @return array
     * @throws CException
     */
    private function displayUntranslatedFields($quickTranslation, $tolang, $baselang, $baselangdesc, $tolangdesc)
    {
        // Define aData
        $survey = $quickTranslation->getSurvey();
        $aData['surveyid'] = $survey->sid;
        $aData['tab_names'] = $quickTranslation->getTabNames();
        $aData['tolang'] = $tolang;
        $aData['baselang'] = $baselang;
        $aData['baselangdesc'] = $baselangdesc;
        $aData['tolangdesc'] = $tolangdesc;

        //This is for the tab navbar
        $aData['amTypeOptions'] = $quickTranslation->getAllTranslateFields();

        $aViewUrls['translateformheader_view'][] = $aData;

        //Set the output as empty
        $aViewUrls['output'] = '';

        //iterate through all tabs and define content of each tab
        foreach ($aData['tab_names'] as $tabName) {
            $amTypeOptions = $quickTranslation->setupTranslateFields($tabName);
            // Setup form
            $evenRow = false; //deprecated => using css

            $all_fields_empty = true;

            $resultbase = $quickTranslation->getTranslations($tabName, $baselang);
            $resultto =  $quickTranslation->getTranslations($tabName, $tolang);

            $type2 = $amTypeOptions["associated"];
            $associated = false;
            if (!empty($type2)) {
                $associated = true;
                //get type otions again again
                $amTypeOptions2 = $quickTranslation->setupTranslateFields($type2);
                $resultbase2 = $quickTranslation->getTranslations($tabName, $baselang);
                $resultto2 = $quickTranslation->getTranslations($tabName, $tolang);
            } else {
                $resultbase2 = $resultbase;
                $resultto2 = $resultto;
            }

            $aData['type'] = $tabName;

            //always set first tab active
            $aData['activeTab'] = $tabName === 'title';

            $aData['translateTabs'] = $this->displayTranslateFieldsHeader($baselangdesc, $tolangdesc, $tabName);
            $aViewUrls['output'] .= $this->renderPartial("translatetabs_view", $aData, true);

            $countResultBase = count($resultbase);
            for ($j = 0; $j < $countResultBase; $j++) {
                $oRowfrom = $resultbase[$j];
                $oResultBase2 = $resultbase2[$j];
                $oResultTo = $resultto[$j];
                $oResultTo2 = $resultto2[$j];

                $aRowfrom = array();
                $aResultBase2 = array();
                $aResultTo = array();
                $aResultTo2 = array();

                $class = get_class($oRowfrom);
                if ($class == 'QuestionGroup') {
                    $aRowfrom = $oRowfrom->questiongroupl10ns[$baselang]->getAttributes();
                    $aResultBase2 = !empty($type2) ? $oResultBase2->questiongroupl10ns[$baselang]->getAttributes() : $aRowfrom;
                    $aResultTo = $oResultTo->questiongroupl10ns[$tolang]->getAttributes();
                    $aResultTo2 = !empty($type2) ? $oResultTo2->questiongroupl10ns[$tolang]->getAttributes() : $aResultTo;
                } elseif ($class == 'Question' || $class == 'Subquestion') {
                    $aRowfrom = $oRowfrom->questionl10ns[$baselang]->getAttributes();
                    if (!empty($oRowfrom['parent_qid'])) {
                        $aRowfrom['parent'] = $oRowfrom->parent->getAttributes();
                    }
                    $aResultBase2 = !empty($type2) ? $oResultBase2->questionl10ns[$baselang]->getAttributes() : $aRowfrom;
                    $aResultTo = $oResultTo->questionl10ns[$tolang]->getAttributes();
                    $aResultTo2 = !empty($type2) ? $oResultTo2->questionl10ns[$tolang]->getAttributes() : $aResultTo;
                } elseif ($class == 'Answer') {
                    $aRowfrom = $oRowfrom->answerl10ns[$baselang]->getAttributes();
                    $aResultBase2 = !empty($type2) ? $oResultBase2->answerl10ns[$baselang]->getAttributes() : $aRowfrom;
                    $aResultTo = $oResultTo->answerl10ns[$tolang]->getAttributes();
                    $aResultTo2 = !empty($type2) ? $oResultTo2->answerl10ns[$tolang]->getAttributes() : $aResultTo;
                }
                $aRowfrom = array_merge($aRowfrom, $oRowfrom->getAttributes());
                $aResultBase2 = array_merge($aResultBase2, $oResultBase2->getAttributes());
                $aResultTo = array_merge($aResultTo, $oResultTo->getAttributes());
                $aResultTo2 = array_merge($aResultTo2, $oResultTo2->getAttributes());

                $textfrom = htmlspecialchars_decode($aRowfrom[$amTypeOptions["dbColumn"]]);

                $textto = $aResultTo[$amTypeOptions["dbColumn"]];
                if ($associated) {
                    $textfrom2 = htmlspecialchars_decode($aResultBase2[$amTypeOptions2["dbColumn"]]);
                    $textto2 = $aResultTo2[$amTypeOptions2["dbColumn"]];
                }

                $gid = ($amTypeOptions["gid"] == true) ? $gid = $aRowfrom['gid'] : null;
                $qid = ($amTypeOptions["qid"] == true) ? $qid = $aRowfrom['qid'] : null;

                $textform_length = strlen(trim($textfrom));

                $all_fields_empty = !($textform_length > 0);

                $aData = array_merge($aData, array(
                    'textfrom' => $this->cleanup($textfrom),
                    'textfrom2' => $this->cleanup($textfrom2),
                    'textto' => $this->cleanup($textto),
                    'textto2' => $this->cleanup($textto2),
                    'rowfrom' => $aRowfrom,
                    'rowfrom2' => $aResultBase2,
                    'evenRow' => $evenRow,
                    'gid' => $gid,
                    'qid' => $qid,
                    'amTypeOptions' => $amTypeOptions,
                    'amTypeOptions2' => $amTypeOptions2,
                    'i' => $j,
                    'type' => $tabName,
                    'type2' => $type2,
                    'associated' => $associated,
                ));

                $aData['translateFields'] = $this->displayTranslateFields(
                    $survey->sid,
                    $gid,
                    $qid,
                    $tabName,
                    $amTypeOptions,
                    $textfrom,
                    $textto,
                    $j,
                    $aRowfrom //todo: what happend here?
                );
                if ($associated && strlen(trim($textfrom2)) > 0) {
                    $aData['translateFields'] .= $this->displayTranslateFields(
                        $survey->sid,
                        $gid,
                        $qid,
                        $type2,
                        $amTypeOptions2,
                        $textfrom2,
                        $textto2,
                        $j,
                        $aResultBase2 //todo:  what happend here?
                    );
                }

                $aViewUrls['output'] .= $this->renderPartial("translatefields_view", $aData, true);
            } // end for

            $aData['all_fields_empty'] = $all_fields_empty;
            $aData['translateFieldsFooter'] = $this->displayTranslateFieldsFooter();
            $aData['bReadOnly'] = !Permission::model()->hasSurveyPermission($survey->sid, 'translations', 'update');
            $aViewUrls['output'] .= $this->renderPartial("translatefieldsfooter_view", $aData, true);
        } // end foreach
        // Submit buttonrender
        $aViewUrls['translatefooter_view'][] = $aData;
        return $aViewUrls;
    }

    /**
     *
     *
     * @param $string
     * @return string
     */
    private function cleanup($string): string
    {
        if (extension_loaded('tidy')) {
            $oTidy = new tidy();

            $cleansedString = $oTidy->repairString($string, array(), 'utf8');
        } else {
            //We should check for tidy on Installation!
            $cleansedString = $string;
        }

        return $cleansedString;
    }

    /**
     * Formats and displays header of translation fields table
     * @param string $baselangdesc The source translation language, e.g. "English"
     * @param string $tolangdesc The target translation language, e.g. "German"
     * @param string $type
     * @return string $translateoutput
     */
    private function displayTranslateFieldsHeader($baselangdesc, $tolangdesc, $type)
    {

        $translateoutput = "<table class='table table-striped'>";
        $translateoutput .= '<thead>';
        $threeRows = ($type == 'question' || $type == 'subquestion' || $type == 'question_help' || $type == 'answer');
        $translateoutput .= $threeRows ? '<th class="col-md-2 text-strong">' . gT('Question code / ID') . "</th>" : '';
        $translateoutput .= '<th class="' . ($threeRows ? "col-sm-5 text-strong" : "col-sm-6") . '" >' . $baselangdesc . "</th>";
        $translateoutput .= '<th class="' . ($threeRows ? "col-sm-5 text-strong" : "col-sm-6") . '" >' . $tolangdesc . "</th>";
        $translateoutput .= '</thead>';

        return $translateoutput;
    }

    /**
     * Formats and displays translation fields (base language as well as to language)
     *
     * @param string $iSurveyID Survey id
     * @param string $gid Group id
     * @param string $qid Question id
     * @param string $type Type of database field that is being translated, e.g. title, question, etc.
     * @param array $amTypeOptions Array containing options associated with each $type
     * @param string $textfrom The text to be translated in source language
     * @param string $textto The text to be translated in target language
     * @param integer $i Counter
     * @param array $rowfrom Contains current row of database query
     *
     * @return string $translateoutput
     */
    private function displayTranslateFields(
        $iSurveyID,
        $gid,
        $qid,
        $type,
        $amTypeOptions,
        $textfrom,
        $textto,
        $i,
        $rowfrom
    ) {
        $translateoutput = "<tr>";
        $value1 = (!empty($amTypeOptions["id1"])) ? $rowfrom[$amTypeOptions["id1"]] : "";
        $value2 = (!empty($amTypeOptions["id2"])) ? $rowfrom[$amTypeOptions["id2"]] : "";
        $iScaleID = (!empty($amTypeOptions["scaleid"])) ? $rowfrom[$amTypeOptions["scaleid"]] : "";
        // Display text in original language
        // Display text in foreign language. Save a copy in type_oldvalue_i to identify changes before db update
        if ($type == 'answer') {
            $translateoutput .= "<td class='col-sm-2'>" . htmlspecialchars($rowfrom['answer']) . " (" . $rowfrom['qid'] . ") </td>";
        }
        if ($type == 'question_help' || $type == 'question') {
            $translateoutput .= "<td class='col-sm-2'>" . htmlspecialchars($rowfrom['title']) . " ({$rowfrom['qid']}) </td>";
        } elseif ($type == 'subquestion') {
            $translateoutput .= "<td class='col-sm-2'>" . htmlspecialchars($rowfrom['parent']['title']) . " ({$rowfrom['parent']['qid']}) </td>";
        }

        $translateoutput .= "<td class='_from_ col-sm-5' id='" . $type . "_from_" . $i . "'><div class='question-text-from'>"
            . showJavaScript($textfrom)
            . "</div></td>";

        $translateoutput .= "<td class='col-sm-5'>";

        $translateoutput .= CHtml::hiddenField("{$type}_id1_{$i}", $value1);
        $translateoutput .= CHtml::hiddenField("{$type}_id2_{$i}", $value2);
        if (is_numeric($iScaleID)) {
            $translateoutput .= CHtml::hiddenField("{$type}_scaleid_{$i}", $iScaleID);
        }
        $nrows = max($this->calcNRows($textfrom), $this->calcNRows($textto));
        $translateoutput .= CHtml::hiddenField("{$type}_oldvalue_{$i}", $textto);

        $aDisplayOptions = array(
            'class' => 'col-sm-10',
            'cols' => '75',
            'rows' => $nrows,
            'readonly' => !Permission::model()->hasSurveyPermission($iSurveyID, 'translations', 'update')
        );
        if ($type == 'group') {
            $aDisplayOptions['maxlength'] = 100;
        }

        $translateoutput .= CHtml::textArea("{$type}_newvalue_{$i}", $textto, $aDisplayOptions);
        $htmleditor_data = array(
            "edit" . $type,
            $type . "_newvalue_" . $i,
            htmlspecialchars($textto),
            $iSurveyID,
            $gid,
            $qid,
            "translate" . $amTypeOptions["HTMLeditorType"]
        );
        $translateoutput .= $this->loadEditor($amTypeOptions, $htmleditor_data);

        $translateoutput .= "</td>";
        $translateoutput .= "</tr>";

        return $translateoutput;
    }

    /**
     * @param $htmleditor
     * @param string[] $aData
     * @return mixed
     */
    private function loadEditor($htmleditor, $aData)
    {
        $editor_function = "";
        $displayType = strtolower($htmleditor["HTMLeditorDisplay"]);
        $displayTypeIsEmpty = empty($displayType);

        if ($displayType == "inline" || $displayTypeIsEmpty) {
            $editor_function = "getEditor";
        } elseif ($displayType == "popup") {
            $editor_function = "getPopupEditor";
            $aData[2] = urlencode($htmleditor['description']);
        } elseif ($displayType == "modal") {
            $editor_function = "getModalEditor";
            $aData[2] = $htmleditor['description'];
        }
        return call_user_func_array($editor_function, $aData);
    }

    /**
     * calcNRows($subject) calculates the vertical size of textbox for survey translation.
     * The function adds the number of line breaks <br /> to the number of times a string wrap occurs.
     * @param string $subject The text string that is being translated
     * @return double
     */
    private function calcNRows($subject)
    {
        // Determines the size of the text box
        // A proxy for box sixe is string length divided by 80
        $pattern = "(<br..?>)";
        $pattern = '[(<br..?>)|(/\n/)]';

        $nrows_newline = preg_match_all($pattern, $subject, $matches);

        $subject_length = strlen((string) $subject);
        $nrows_char = ceil($subject_length / 80);

        return $nrows_newline + $nrows_char;
    }

    /**
     * Formats and displays footer of translation fields table
     * @return string $translateoutput
     */
    private function displayTranslateFieldsFooter()
    {
        $translateoutput = "</table>";
        return $translateoutput;
    }

    /**
     * menuItem() creates a menu item with text and image in the admin screen menus
     * @param string $jsMenuText
     * @param string $menuImageText
     * @param string $menuIconClasses
     * @param string $scriptname
     * @return string
     */
    private function menuItem($jsMenuText, $menuImageText, $menuIconClasses, $scriptname)
    {
        //$imageurl = Yii::app()->getConfig("adminimageurl");

        //$img_tag = CHtml::image($imageurl . "/" . $menuImageFile, $jsMenuText, array('name'=>$menuImageText));
        $icon_tag = '<span class="' . $menuIconClasses . '"></span>' . $jsMenuText;
        $menuitem = CHtml::link($icon_tag, '#', array(
            'onclick' => "window.open('{$scriptname}', '_top')"
        ));
        return $menuitem;
    }

    /**
     * menuSeparator() creates a separator bar in the admin screen menus
     * @return string
     */
    private function menuSeparator()
    {

        $imageurl = Yii::app()->getConfig("adminimageurl");

        $image = CHtml::image($imageurl . "/separator.gif", '');
        return $image;
    }

    /**
     *
     *
     * @return void
     */
    public function actionAjaxtranslategoogleapi()
    {
        // Ensure YII_CSRF_TOKEN, we are in admin, then only user with admin right can post
        /* No Permission check on survey, seems unneded (return a josn with current string posted */

        //todo: check permission
        //todo: check if googletranslate is activated ...
        if (Yii::app()->request->isPostRequest) {
            echo self::translateGoogleApi();
        }
    }

    /*
     * translateGoogleApi.php
     * Creates a JSON interface for the auto-translate feature
     */
    private function translateGoogleApi()
    {
        $sBaselang   = Yii::app()->getRequest()->getPost('baselang');
        $sTolang     = Yii::app()->getRequest()->getPost('tolang');
        $sToconvert  = Yii::app()->getRequest()->getPost('text');

        $aSearch     = array('zh-Hans', 'zh-Hant-HK', 'zh-Hant-TW', 'nl-informal', 'de-informal', 'de-easy', 'it-formal', 'pt-BR', 'es-MX', 'nb', 'nn');
        $aReplace    = array('zh-CN', 'zh-TW', 'zh-TW', 'nl', 'de', 'de', 'it', 'pt', 'es', 'no', 'no');
        $sBaselang = str_replace($aSearch, $aReplace, $sBaselang);
        $sTolang = str_replace($aSearch, $aReplace, $sTolang);

        $error = false;

        try {
            require_once(APPPATH . '/third_party/gtranslate-api/GTranslate.php');
            $gtranslate = new Gtranslate();
            $objGt = $gtranslate;

            // Gtranslate requires you to run function named XXLANG_to_XXLANG
            $sProcedure = $sBaselang . "_to_" . $sTolang;

            $parts = LimeExpressionManager::SplitStringOnExpressions($sToconvert);

            $sparts = array();
            foreach ($parts as $part) {
                if ($part[2] == 'EXPRESSION') {
                    $sparts[] = $part[0];
                } else {
                    $convertedPart = $objGt->$sProcedure($part[0]);
                    $convertedPart  = str_replace("<br>", "\r\n", $convertedPart);
                    $convertedPart  = html_entity_decode(stripcslashes($convertedPart));
                    $sparts[] = $convertedPart;
                }
            }
            $sOutput = implode(' ', $sparts);
        } catch (GTranslateException $ge) {
            // Get the error message and build the ouput array
            $error = true;
            $sOutput = $ge->getMessage();
        }

        $aOutput = array(
            'error'     =>  $error,
            'baselang'  =>  $sBaselang,
            'tolang'    =>  $sTolang,
            'converted' =>  $sOutput
        );

        header('Content-type: application/json');
        return ls_json_encode($aOutput);
    }

}
