<?php

namespace LimeSurvey\Models\Services;

use Answer;
use AnswerL10n;
use Question;
use QuestionGroup;
use QuestionGroupL10n;
use QuestionL10n;
use Survey;
use SurveyLanguageSetting;

/**
 * This class is responsible for quick translation and  all DB actions needed.
 *
 *
 */
class QuickTranslation
{
    /** @var Survey the survey */
    private $survey;

    /** @var array tab names */
    private $tab_names = [
        "title",
        "welcome",
        "group",
        "question",
        "subquestion",
        "answer",
        "emailinvite",
        "emailreminder",
        "emailconfirmation",
        "emailregistration",
        "emailbasicadminnotification",
        "emaildetailedadminnotification"
    ];

    /**
     * Quicktranslation constructor.
     *
     * @param Survey $survey the survey object
     *
     */
    public function __construct($survey)
    {
        $this->survey = $survey;
    }

    /**
     * This function gets the translation for a specific type. Different types need different query.
     *
     * @param $type
     * @param $baselang
     * @return array|\CActiveRecord|mixed|Question[]|SurveyLanguageSetting[]|void|null
     */
    public function getTranslations($type, $baselang)
    {
        switch ($type) {
            case 'title':
            case 'description':
            case 'welcome':
            case 'end':
            case 'emailinvite':
            case 'emailinvitebody':
            case 'emailreminder':
            case 'emailreminderbody':
            case 'emailconfirmation':
            case 'emailconfirmationbody':
            case 'emailregistration':
            case 'emailregistrationbody':
            case 'email_confirm':
            case 'email_confirmbody':
            case 'emailbasicadminnotification':
            case 'emailbasicadminnotificationbody':
            case 'emaildetailedadminnotification':
            case 'emaildetailedadminnotificationbody':
                return SurveyLanguageSetting::model()->resetScope()->findAllByPk([
                    'surveyls_survey_id' => $this->survey->sid,
                    'surveyls_language' => $baselang
                ]);
            case 'group':
            case 'group_desc':
                return QuestionGroup::model()
                    ->with('questiongroupl10ns', ['condition' => 'language = ' . $baselang])
                    ->findAllByAttributes(['sid' => $this->survey->sid], ['order' => 't.gid']);
            case 'question':
            case 'question_help':
                return Question::model()
                    ->with('questionl10ns', ['condition' => 'language = ' . $baselang])
                    ->with('parent', 'group')
                    ->findAllByAttributes(
                        ['sid' => $this->survey->sid, 'parent_qid' => 0],
                        ['order' => 'group_order, t.question_order, t.scale_id']
                    );
            case 'subquestion':
                return Question::model()
                    ->with('questionl10ns', array('condition' => 'language = ' . $baselang))
                    ->with('parent', array('condition' => 'language = ' . $baselang))
                    ->with('group', array('condition' => 'language = ' . $baselang))
                    ->findAllByAttributes(
                        ['sid' => $this->survey->sid],
                        [
                            'order' => 'group_order, parent.question_order,t.scale_id, t.question_order',
                            'condition' => 't.parent_qid>0',
                            'params' => array()]
                    );
            case 'answer':
                return Answer::model()
                    ->with('answerl10ns', array('condition' => 'language = ' . $baselang))
                    ->with('question')
                    ->with('group')
                    ->findAllByAttributes(
                        [],
                        [
                            'order' => 'group_order, question.question_order, t.scale_id, t.sortorder',
                            'condition' => 'question.sid=:sid',
                            'params' => array(':sid' => $this->survey->sid)
                        ]
                    );
        }
    }

    /**
     * Updates the translation for a given field name (e.g. surveyls_title)
     *
     * @param $fieldName  string the field name from frontend
     * @param $tolang string shortcut for language (e.g. 'de')
     * @param $new   string the new value to save as translation
     * @param $id1 int  groupid or questionid
     * @param $answerCode string the answer code
     * @param $iScaleID
     *
     * @return int|null
     */
    public function updateTranslations($fieldName, $tolang, $new, $id1 = 0, $answerCode = '', $iScaleID = '')
    {
        switch ($fieldName) {
            case 'title':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_title' => substr($new, 0, 200)));
            case 'description':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_description' => $new));
            case 'welcome':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_welcometext' => $new));
            case 'end':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_endtext' => $new));
            case 'emailinvite':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_invite_subj' => $new));
            case 'emailinvitebody':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_invite' => $new));
            case 'emailreminder':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_remind_subj' => $new));
            case 'emailreminderbody':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_remind' => $new));


            case 'emailconfirmation':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_confirm_subj' => $new));
            case 'emailconfirmationbody':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_confirm' => $new));


            case 'emailregistration':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_register_subj' => $new));
            case 'emailregistrationbody':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_register' => $new));
            case 'emailbasicadminnotification':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('email_admin_notification_subj' => $new));
            case 'emailbasicadminnotificationbody':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('email_admin_notification' => $new));
            case 'emaildetailedadminnotification':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('email_admin_responses_subj' => $new));
            case 'emaildetailedadminnotificationbody':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('email_admin_responses' => $new));

            //todo: these two seem to be twice
            case 'email_confirm':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_confirm_subject' => $new));
            case 'email_confirmbody':
                return SurveyLanguageSetting::model()->updateByPk(array('surveyls_survey_id' => $this->survey->sid, 'surveyls_language' => $tolang), array('surveyls_email_confirm' => $new));

            case 'group': //todo: here id1 = groupid
                return QuestionGroupL10n::model()->updateAll(array('group_name' => mb_substr($new, 0, 100)), 'gid = :gid and language = :language', array(':gid' => $id1, ':language' => $tolang));
            case 'group_desc':
                return QuestionGroupL10n::model()->updateAll(array('description' => $new), 'gid = :gid and language = :language', array(':gid' => $id1, ':language' => $tolang));

            case 'question': //todo: here id1 = questionid
                return QuestionL10n::model()->updateAll(array('question' => $new), 'qid = :qid and language = :language', array(':qid' => $id1, ':language' => $tolang));
            case 'question_help':
                return QuestionL10n::model()->updateAll(array('help' => $new), 'qid = :qid and language = :language', array(':qid' => $id1, ':language' => $tolang));
            case 'subquestion':
                return QuestionL10n::model()->updateAll(array('question' => $new), 'qid = :qid and language = :language', array(':qid' => $id1, ':language' => $tolang));
            case 'answer':
                $oAnswer = Answer::model()->find('qid = :qid and code = :code and scale_id = :scale_id', array(':qid' => $id1, ':code' => $answerCode, ':scale_id' => $iScaleID));
                return AnswerL10n::model()->updateAll(array('answer' => $new), 'aid = :aid and language = :language', array(':aid' => $oAnswer->aid, ':language' => $tolang));
            default:
                return null;
        }
    }

    /**
     * Creates a customised array with database information for use by survey translation.
     * This array structure is the base for the whole algorithm. Each returned array consists of the following information
     *  type -->
     *  dbColumn  -->  the name of the db column where to find the
     *  id1  -->
     *  id2  -->
     *  gid  -->
     *  qid  -->
     *  description -->
     *  HTMLeditorType -->
     *  HTMLeditorDisplay -->
     *  associated --> the associated field for the current one. If empty string this one has no associated field
     *
     * @param string $type Type of database field that is being translated, e.g. title, question, etc.
     * @return array
     */
    public function setupTranslateFields($type)
    {
        $aData = array();

        switch ($type) {
            case 'title':
                $aData = array(
                    'type' => 1,  //todo: what is this good for?
                    'dbColumn' => 'surveyls_title',
                    'id1' => '', //todo: description... what is id1?
                    'id2' => '', //todo: description... what is id2?
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Survey title and description"),
                    'HTMLeditorType' => "title",
                    'HTMLeditorDisplay' => "Inline",
                    'associated' => "description"
                );
                break;

            case 'description':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_description',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Description:"),
                    'HTMLeditorType' => "description",
                    'HTMLeditorDisplay' => "Inline",
                    'associated' => ""
                );
                break;

            case 'welcome':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_welcometext',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Welcome and end text"),
                    'HTMLeditorType' => "welcome",
                    'HTMLeditorDisplay' => "Inline",
                    'associated' => "end"
                );
                break;

            case 'end':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_endtext',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("End message:"),
                    'HTMLeditorType' => "end",
                    'HTMLeditorDisplay' => "Inline",
                    'associated' => ""
                );
                break;

            case 'group':
                $aData = array(
                    'type' => 2,
                    'dbColumn' => 'group_name',
                    'id1' => 'gid',
                    'id2' => '',
                    'gid' => true,
                    'qid' => false,
                    'description' => gT("Question groups"),
                    'HTMLeditorType' => "group",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => "group_desc"
                );
                break;

            case 'group_desc':
                $aData = array(
                    'type' => 2,
                    'dbColumn' => 'description',
                    'id1' => 'gid',
                    'id2' => '',
                    'gid' => true,
                    'qid' => false,
                    'description' => gT("Group description"),
                    'HTMLeditorType' => "group_desc",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'question':
                $aData = array(
                    'type' => 3,
                    'dbColumn' => 'question',
                    'id1' => 'qid',
                    'id2' => '',
                    'gid' => true,
                    'qid' => true,
                    'description' => gT("Questions"),
                    'HTMLeditorType' => "question",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => "question_help"
                );
                break;

            case 'question_help':
                $aData = array(
                    'type' => 3,
                    'dbColumn' => 'help',
                    'id1' => 'qid',
                    'id2' => '',
                    'gid' => true,
                    'qid' => true,
                    'description' => gT("Question help"),
                    'HTMLeditorType' => "question_help",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'subquestion':
                $aData = array(
                    'type' => 4,
                    'dbColumn' => 'question',
                    'id1' => 'qid',
                    'id2' => '',
                    'gid' => true,
                    'qid' => true,
                    'description' => gT("Subquestions"),
                    'HTMLeditorType' => "question",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'answer': // TODO not touched
                $aData = array(
                    'type' => 5,
                    'dbColumn' => 'answer',
                    'id1' => 'qid',
                    'id2' => 'code',
                    'scaleid' => 'scale_id',
                    'gid' => false,
                    'qid' => true,
                    'description' => gT("Answer options"),
                    'HTMLeditorType' => "subquestion",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'emailinvite':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_invite_subj',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Invitation email subject"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => "emailinvitebody"
                );
                break;

            case 'emailinvitebody':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_invite',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Invitation email"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'emailreminder':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_remind_subj',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Reminder email subject"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => "emailreminderbody"
                );
                break;

            case 'emailreminderbody':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_remind',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Reminder email"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'emailconfirmation':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_confirm_subj',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Confirmation email subject"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => "emailconfirmationbody"
                );
                break;

            case 'emailconfirmationbody':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_confirm',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Confirmation email"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'emailregistration':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_register_subj',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Registration email subject"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => "emailregistrationbody"
                );
                break;

            case 'emailregistrationbody':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_register',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Registration email"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'email_confirm':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_confirm_subj',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Confirmation email"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => "email_confirmbody"
                );
                break;

            case 'email_confirmbody':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'surveyls_email_confirm',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Confirmation email"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => ""
                );
                break;

            case 'emailbasicadminnotification':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'email_admin_notification_subj',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Basic admin notification subject"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => "emailbasicadminnotificationbody"
                );
                break;

            case 'emailbasicadminnotificationbody':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'email_admin_notification',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Basic admin notification"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;

            case 'emaildetailedadminnotification':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'email_admin_responses_subj',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Detailed admin notification subject"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "",
                    'associated' => "emaildetailedadminnotificationbody"
                );
                break;

            case 'emaildetailedadminnotificationbody':
                $aData = array(
                    'type' => 1,
                    'dbColumn' => 'email_admin_responses',
                    'id1' => '',
                    'id2' => '',
                    'gid' => false,
                    'qid' => false,
                    'description' => gT("Detailed admin notification"),
                    'HTMLeditorType' => "email",
                    'HTMLeditorDisplay' => "Modal",
                    'associated' => ""
                );
                break;
        }
        return $aData;
    }

    /**
     *
     *
     * @return array
     */
    public function getAllTranslateFields()
    {
        return array_map([$this, 'setupTranslateFields'], $this->getTabNames());
    }

    /**
     * Returns all tab names.
     *
     * @return string[]
     */
    public function getTabNames()
    {
        return [
            "title",
            "welcome",
            "group",
            "question",
            "subquestion",
            "answer",
            "emailinvite",
            "emailreminder",
            "emailconfirmation",
            "emailregistration",
            "emailbasicadminnotification",
            "emaildetailedadminnotification"
        ];
    }

    /**
     * Returns the survey object
     *
     * @return Survey
     */
    public function getSurvey()
    {
        return $this->survey;
    }
}
