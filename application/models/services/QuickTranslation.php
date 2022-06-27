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
     *
     * @param string $type
     * @param string $action  must be 'queryto', 'querybase', 'queryupdate'
     * @param $iSurveyID
     * @param $tolang
     * @param $baselang
     * @param string $id1
     * @param string $id2
     * @param string $iScaleID
     * @param string $new
     * @return array|\CActiveRecord|int|mixed|SurveyLanguageSetting[]|void|null
     */
    public function query($type, $action, $iSurveyID, $tolang, $baselang, $id1 = "", $id2 = "", $iScaleID = "", $new = "")
    {
        // TODO: Fallthru on purpose or not?
        switch ($action) {
            case "queryto":
                $baselang = $tolang;
            /* FALLTHRU */
            case "querybase":
                $this->getTranslations($type, $baselang);
                break;
            /* FALLTHRU */
            case "queryupdate":
                $this->updateTranslations($type,$tolang,$new);
        }
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
                    ->findAllByAttributes(['sid' => $this->survey->sid, 'parent_qid' => 0], array('order' => 'group_order, t.question_order, t.scale_id'));
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
                    ->findAllByAttributes(array(), array('order' => 'group_order, question.question_order, t.scale_id, t.sortorder', 'condition' => 'question.sid=:sid', 'params' => array(':sid' => $this->survey->sid)));
        }
    }

    /**
     * @param $fieldName  string the field name from frontend
     * @param $tolang string shortcut for language (e.g. 'de')
     * @param $new   string the new value to save as translation
     * @param $id1 int  groupid or questionid
     * @param $answerCode string the answer code
     * @param $iScaleID
     * @return int|void
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
        }
    }
}
